import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:timezone/data/latest.dart' as tz_data;
import 'package:timezone/timezone.dart' as tz;

import '../models/medication.dart';
import 'push_notifications.dart';

enum AlarmKind {
  dose,
  nudge,
  count,
  reorder,
  expiry,
  courseComplete,
  keepGoing,
}

/// One local notification to schedule. [at] is Philippine wall-clock time.
class PlannedAlarm {
  const PlannedAlarm({
    required this.kind,
    required this.at,
    required this.title,
    required this.body,
    required this.payload,
    this.withDoseActions = false,
  });

  final AlarmKind kind;
  final DateTime at;
  final String title;
  final String body;
  final String payload;
  final bool withDoseActions;

  /// Stable for the same reminder and changes when its time or text changes,
  /// so a sync only touches the reminders that differ.
  int get id => _id('${kind.name}|$payload|$title|$body', at);
}

const _directions = "Follow your doctor's or pharmacist's directions.";

/// Android keeps about 500 pending alarms per app; stay well under it.
const maxAlarms = 400;

/// Every reminder for the next [days] days (fewer when that would pass
/// [maxAlarms]), in time order, ending with a nudge to open the app so the
/// window gets rebuilt. [now] is Philippine wall-clock time.
List<PlannedAlarm> buildAlarms(
  List<MedicationPlan> plans,
  DateTime now, {
  int days = 14,
}) {
  final active = plans.where((plan) => plan.isActive).toList();
  if (active.isEmpty) {
    return const [];
  }

  for (var window = days; window >= 1; window--) {
    final alarms = _alarmsFor(active, now, window);
    if (alarms.length + 1 <= maxAlarms || window == 1) {
      if (alarms.isEmpty) {
        return alarms;
      }
      final last = alarms.last.at.add(const Duration(minutes: 5));
      return [
        ...alarms,
        PlannedAlarm(
          kind: AlarmKind.keepGoing,
          at: last,
          title: 'Open the app to keep your reminders going',
          body: 'Your medicine reminders are set up to here.',
          payload: '/medicines',
        ),
      ];
    }
  }

  return const [];
}

List<PlannedAlarm> _alarmsFor(
  List<MedicationPlan> plans,
  DateTime now,
  int window,
) {
  final today = DateTime(now.year, now.month, now.day);
  final end = today.add(Duration(days: window));
  bool inWindow(DateTime at) => at.isAfter(now) && at.isBefore(end);
  final alarms = <PlannedAlarm>[];

  for (final plan in plans) {
    final who = _whose(plan);
    final name = plan.showNameOnLockScreen ? plan.productName : null;

    if (!plan.isAsNeeded) {
      DateTime? lastDose;
      for (var day = 0; day < window; day++) {
        final date = today.add(Duration(days: day));
        if (date.isBefore(plan.startsOn) ||
            (plan.endsOn != null && date.isAfter(plan.endsOn!))) {
          continue;
        }
        for (final time in plan.doseTimes) {
          final parts = time.split(':');
          final at = DateTime(
            date.year,
            date.month,
            date.day,
            int.parse(parts[0]),
            int.parse(parts[1]),
          );
          lastDose = at;
          if (plan.answeredSlots.contains(at)) {
            continue;
          }
          final payload =
              '/medicines/dose?plan=${plan.id}&slot=${Uri.encodeQueryComponent(_manilaIso(at))}';
          if (inWindow(at)) {
            alarms.add(
              PlannedAlarm(
                kind: AlarmKind.dose,
                at: at,
                title: name == null
                    ? 'Time for $who medicine'
                    : 'Time for ${_possessive(plan)}$name',
                body:
                    '${name == null ? 'Take' : '$name: take'} ${plan.doseLabel}. $_directions',
                payload: payload,
                withDoseActions: true,
              ),
            );
          }
          final nudge = at.add(const Duration(minutes: 30));
          if (inWindow(nudge)) {
            alarms.add(
              PlannedAlarm(
                kind: AlarmKind.nudge,
                at: nudge,
                title: name == null
                    ? 'Did you take $who medicine?'
                    : 'Did you take $name?',
                body:
                    'Tap Taken or Skip so your supply count stays right. $_directions',
                payload: payload,
                withDoseActions: true,
              ),
            );
          }
        }
      }
      if (plan.medicineKind == 'course' &&
          plan.endsOn != null &&
          lastDose != null) {
        final done = lastDose.add(const Duration(hours: 1));
        if (inWindow(done) && _sameDay(lastDose, plan.endsOn!)) {
          alarms.add(
            PlannedAlarm(
              kind: AlarmKind.courseComplete,
              at: done,
              title: 'Course complete',
              body: name == null
                  ? 'That was the last dose of $who course.'
                  : 'That was the last dose of $name.',
              payload: '/medicines',
            ),
          );
        }
      }
    }

    if (plan.countPromptOn != null) {
      final at = _nineAm(plan.countPromptOn!);
      if (inWindow(at)) {
        alarms.add(
          PlannedAlarm(
            kind: AlarmKind.count,
            at: at,
            title: 'How many are left?',
            body: name == null
                ? "Tell us roughly how many ${plan.doseUnit}s are left of $who medicine, so we remind you before it runs out."
                : 'About how many ${plan.doseUnit}s of $name are left?',
            payload: '/medicines?count=${plan.id}',
          ),
        );
      }
    }

    if (plan.expiresOn != null) {
      for (final at in [
        _nineAm(plan.expiresOn!.subtract(const Duration(days: 30))),
        _nineAm(plan.expiresOn!),
      ]) {
        if (inWindow(at)) {
          final today = _sameDay(at, plan.expiresOn!);
          alarms.add(
            PlannedAlarm(
              kind: AlarmKind.expiry,
              at: at,
              title: today
                  ? 'Medicine expires today'
                  : 'Medicine expiring soon',
              body: today
                  ? "Don't use ${name ?? '$who medicine'} after today. $_directions"
                  : '${name ?? 'Some of $who medicine'} expires in 30 days.',
              payload: '/medicines',
            ),
          );
        }
      }
    }
  }

  alarms.addAll(_reorderAlarms(plans, inWindow));
  alarms.sort((a, b) => a.at.compareTo(b.at));
  return alarms;
}

/// One reminder for every medicine running low within a week of the first.
List<PlannedAlarm> _reorderAlarms(
  List<MedicationPlan> plans,
  bool Function(DateTime) inWindow,
) {
  final due =
      plans
          .where((plan) => plan.reorderOn != null && plan.productId != null)
          .toList()
        ..sort((a, b) => a.reorderOn!.compareTo(b.reorderOn!));
  final alarms = <PlannedAlarm>[];
  var index = 0;
  while (index < due.length) {
    final first = due[index].reorderOn!;
    final group = due
        .skip(index)
        .takeWhile((plan) => plan.reorderOn!.difference(first).inDays <= 7)
        .toList();
    index += group.length;
    final at = _nineAm(first);
    if (!inWindow(at)) {
      continue;
    }
    final single = group.length == 1 ? group.single : null;
    alarms.add(
      PlannedAlarm(
        kind: AlarmKind.reorder,
        at: at,
        title: single == null
            ? '${group.length} medicines are running low'
            : single.showNameOnLockScreen
            ? 'Running low on ${single.productName}'
            : 'Running low on ${_whose(single)} medicine',
        body: 'Reorder now so it arrives before you run out.',
        payload:
            '/medicines/reorder?plans=${group.map((plan) => plan.id).join(',')}',
      ),
    );
  }
  return alarms;
}

String _whose(MedicationPlan plan) =>
    plan.profileName == null ? 'your' : "${plan.profileName}'s";

String _possessive(MedicationPlan plan) =>
    plan.profileName == null ? '' : "${plan.profileName}'s ";

bool _sameDay(DateTime a, DateTime b) =>
    a.year == b.year && a.month == b.month && a.day == b.day;

DateTime _nineAm(DateTime date) => DateTime(date.year, date.month, date.day, 9);

String _two(int value) => value.toString().padLeft(2, '0');

String _manilaIso(DateTime at) =>
    '${at.year}-${_two(at.month)}-${_two(at.day)}T${_two(at.hour)}:${_two(at.minute)}:00+08:00';

/// A stable 31-bit id (FNV-1a), so the same reminder keeps its id across syncs.
int _id(String key, DateTime at) {
  var hash = 0x811c9dc5;
  for (final unit in '$key|${at.toIso8601String()}'.codeUnits) {
    hash ^= unit;
    hash = (hash * 0x01000193) & 0xffffffff;
  }
  return hash & 0x7fffffff;
}

/// Schedules [buildAlarms] on the phone. Alarms ring offline and at the exact
/// minute when Android allows exact alarms (a few minutes late otherwise).
class MedicationAlarms {
  MedicationAlarms._();

  static const channel = AndroidNotificationChannel(
    'medicine_reminders',
    'Medicine reminders',
    description: 'Dose, refill and expiry reminders you set up.',
    importance: Importance.high,
  );

  static final _plugin = FlutterLocalNotificationsPlugin();
  static final _manila = () {
    tz_data.initializeTimeZones();
    return tz.getLocation('Asia/Manila');
  }();

  static Future<void>? _running;
  static List<MedicationPlan>? _waiting;

  /// Brings the scheduled reminders in line with a fresh 14-day window. One
  /// sync runs at a time; plans that arrive meanwhile are applied right after,
  /// so an older sync can never re-add a reminder a newer one removed.
  static Future<void> sync(List<MedicationPlan> plans) async {
    if (_running != null) {
      _waiting = plans;
      return _running;
    }
    _running = _apply(plans);
    try {
      await _running;
    } finally {
      _running = null;
    }
    final next = _waiting;
    _waiting = null;
    if (next != null) {
      await sync(next);
    }
  }

  /// The plugin rewrites its saved list on every schedule and cancel, so only
  /// the reminders that changed are touched.
  static Future<void> _apply(List<MedicationPlan> plans) async {
    try {
      await PushNotifications.initializeLocal();
      await PushNotifications.openLaunchNotification();
      final android = _plugin
          .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin
          >();
      final wanted = {
        for (final alarm in buildAlarms(plans, _nowInManila())) alarm.id: alarm,
      };
      final pending = await _plugin.pendingNotificationRequests();
      final pendingIds = {for (final request in pending) request.id};
      for (final request in pending) {
        if ((request.payload?.startsWith('/medicines') ?? false) &&
            !wanted.containsKey(request.id)) {
          await _plugin.cancel(request.id);
        }
      }
      final exact = await android?.canScheduleExactNotifications() ?? false;
      for (final alarm in wanted.values) {
        if (pendingIds.contains(alarm.id)) {
          continue;
        }
        await _plugin.zonedSchedule(
          alarm.id,
          alarm.title,
          alarm.body,
          tz.TZDateTime(
            _manila,
            alarm.at.year,
            alarm.at.month,
            alarm.at.day,
            alarm.at.hour,
            alarm.at.minute,
          ),
          NotificationDetails(android: _details(alarm)),
          uiLocalNotificationDateInterpretation:
              UILocalNotificationDateInterpretation.absoluteTime,
          androidScheduleMode: exact
              ? AndroidScheduleMode.exactAllowWhileIdle
              : AndroidScheduleMode.inexactAllowWhileIdle,
          payload: alarm.payload,
        );
      }
    } catch (error, stack) {
      // Reminders must never break the app; the next sync tries again.
      debugPrint(
        '[Medicine] alarm sync failed: $error '
        '$stack',
      );
    }
  }

  /// Asks once, when the buyer turns reminders on, for alarms at the exact
  /// minute. Asking on every sync would open Android settings at each launch;
  /// without it reminders still ring, a few minutes late.
  static Future<void> requestExactTiming() async {
    try {
      final android = _plugin
          .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin
          >();
      if (!(await android?.canScheduleExactNotifications() ?? true)) {
        await android?.requestExactAlarmsPermission();
      }
    } catch (error) {
      debugPrint('[Medicine] exact alarm request failed: $error');
    }
  }

  /// Cancels the 30-minute follow-up once a dose is marked taken or skipped.
  static Future<void> cancelNudge(String payload) async {
    try {
      final pending = await _plugin.pendingNotificationRequests();
      for (final request in pending) {
        if (request.payload == payload &&
            (request.title?.startsWith('Did you take') ?? false)) {
          await _plugin.cancel(request.id);
        }
      }
    } catch (error) {
      debugPrint('[Medicine] could not cancel the nudge: $error');
    }
  }

  /// Only medicine reminders: the plugin's cancelAll() would also clear
  /// chat and order notifications already on screen.
  static Future<void> cancelMedicineAlarms() async {
    final pending = await _plugin.pendingNotificationRequests();
    for (final request in pending) {
      if (request.payload?.startsWith('/medicines') ?? false) {
        await _plugin.cancel(request.id);
      }
    }
  }

  static DateTime _nowInManila() {
    final now = tz.TZDateTime.now(_manila);
    return DateTime(
      now.year,
      now.month,
      now.day,
      now.hour,
      now.minute,
      now.second,
    );
  }

  static AndroidNotificationDetails _details(
    PlannedAlarm alarm,
  ) => AndroidNotificationDetails(
    channel.id,
    channel.name,
    channelDescription: channel.description,
    importance: Importance.high,
    priority: Priority.high,
    icon: PushNotifications.notificationIcon,
    category: AndroidNotificationCategory.reminder,
    styleInformation: BigTextStyleInformation(alarm.body),
    // shortcut: Taken/Skip open the app to post the log (no background
    // isolate); upgrade if buyers want to mark a dose without the app opening.
    actions: alarm.withDoseActions
        ? const [
            AndroidNotificationAction(
              'taken',
              'Taken',
              showsUserInterface: true,
              cancelNotification: true,
            ),
            AndroidNotificationAction(
              'skip',
              'Skip',
              showsUserInterface: true,
              cancelNotification: true,
            ),
          ]
        : null,
  );
}
