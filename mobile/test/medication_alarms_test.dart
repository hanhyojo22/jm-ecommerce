import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/medication_alarms.dart';
import 'package:jmmart/src/models/medication.dart';

MedicationPlan plan({
  String id = 'p1',
  String name = 'Amlodipine 5 mg Tablet',
  String kind = 'maintenance',
  List<String> times = const ['08:00', '20:00'],
  bool showName = false,
  String? profile,
  DateTime? endsOn,
  DateTime? reorderOn,
  DateTime? countPromptOn,
  DateTime? expiresOn,
}) => MedicationPlan(
  id: id,
  productId: 'prod-$id',
  productName: name,
  medicineKind: kind,
  doseQuantity: 1,
  doseUnit: 'tablet',
  doseTimes: times,
  startsOn: DateTime(2026, 12, 1),
  endsOn: endsOn,
  status: 'active',
  unitsLeft: 30,
  showNameOnLockScreen: showName,
  profileId: profile == null ? null : 'c1',
  profileName: profile,
  reorderOn: reorderOn,
  countPromptOn: countPromptOn,
  expiresOn: expiresOn,
);

void main() {
  final now = DateTime(2026, 12, 10, 6, 0);

  test('two daily times over 14 days give a dose and a nudge each', () {
    final alarms = buildAlarms([plan()], now);

    expect(alarms.where((a) => a.kind == AlarmKind.dose), hasLength(28));
    expect(alarms.where((a) => a.kind == AlarmKind.nudge), hasLength(28));
    final first = alarms.firstWhere((a) => a.kind == AlarmKind.dose);
    expect(first.at, DateTime(2026, 12, 10, 8));
    expect(first.withDoseActions, isTrue);
    final link = Uri.parse(first.payload);
    expect(link.path, '/medicines/dose');
    expect(link.queryParameters, {
      'plan': 'p1',
      'slot': '2026-12-10T08:00:00+08:00',
    });
    expect(
      alarms.firstWhere((a) => a.kind == AlarmKind.nudge).at,
      DateTime(2026, 12, 10, 8, 30),
    );
    expect(
      first.body,
      endsWith("Follow your doctor's or pharmacist's directions."),
    );
  });

  test(
    'the lock screen hides the medicine name unless the buyer allows it',
    () {
      final hidden = buildAlarms([plan()], now).first;
      final family = buildAlarms([plan(profile: 'Lola')], now).first;
      final shown = buildAlarms([plan(showName: true)], now).first;

      expect(hidden.title, 'Time for your medicine');
      expect(hidden.body, isNot(contains('Amlodipine')));
      expect(family.title, "Time for Lola's medicine");
      expect(shown.title, 'Time for Amlodipine 5 mg Tablet');
    },
  );

  test('medicines running low within a week share one reorder alarm', () {
    final alarms = buildAlarms([
      plan(id: 'a', reorderOn: DateTime(2026, 12, 12)),
      plan(id: 'b', name: 'Metformin', reorderOn: DateTime(2026, 12, 15)),
    ], now);

    final reorders = alarms.where((a) => a.kind == AlarmKind.reorder).toList();
    expect(reorders, hasLength(1));
    expect(reorders.single.at, DateTime(2026, 12, 12, 9));
    expect(reorders.single.title, '2 medicines are running low');
    expect(reorders.single.payload, '/medicines/reorder?plans=a,b');
  });

  test('as-needed medicine gets no dose alarms', () {
    final alarms = buildAlarms([plan(kind: 'as_needed', times: [])], now);

    expect(alarms.where((a) => a.kind == AlarmKind.dose), isEmpty);
  });

  test('a course stops at its last day and says it is complete', () {
    final alarms = buildAlarms([
      plan(kind: 'course', endsOn: DateTime(2026, 12, 12)),
    ], now);

    final doses = alarms.where((a) => a.kind == AlarmKind.dose).toList();
    expect(doses.last.at, DateTime(2026, 12, 12, 20));
    expect(doses, hasLength(6));
    final complete = alarms.singleWhere(
      (a) => a.kind == AlarmKind.courseComplete,
    );
    expect(complete.at, DateTime(2026, 12, 12, 21));
  });

  test('expiry alarms come 30 days before and on the day', () {
    final alarms = buildAlarms([plan(expiresOn: DateTime(2026, 12, 20))], now);

    expect(alarms.where((a) => a.kind == AlarmKind.expiry).map((a) => a.at), [
      DateTime(2026, 12, 20, 9),
    ]);

    final later = buildAlarms([plan(expiresOn: DateTime(2027, 1, 5))], now);
    expect(
      later.where((a) => a.kind == AlarmKind.expiry).map((a) => a.at),
      isEmpty,
      reason: 'Dec 6 (30 days before) is already past and Jan 5 is outside the 14-day window',
    );
  });

  test('the count prompt and the keep-going alarm are scheduled', () {
    final alarms = buildAlarms([
      plan(countPromptOn: DateTime(2026, 12, 14)),
    ], now);

    expect(
      alarms.singleWhere((a) => a.kind == AlarmKind.count).at,
      DateTime(2026, 12, 14, 9),
    );
    expect(alarms.last.kind, AlarmKind.keepGoing);
    expect(alarms.last.title, 'Open the app to keep your reminders going');
  });

  test('alarm ids are unique', () {
    final alarms = buildAlarms([
      plan(id: 'a'),
      plan(id: 'b', profile: 'Lola'),
    ], now);

    expect(alarms.map((a) => a.id).toSet(), hasLength(alarms.length));
  });

  test('an alarm keeps its id until its time or text changes', () {
    final first = buildAlarms([plan()], now).first;
    final again = buildAlarms([plan()], now).first;
    final renamed = buildAlarms([plan(showName: true)], now).first;
    final moved = buildAlarms([
      plan(times: const ['09:00', '20:00']),
    ], now).first;

    expect(again.id, first.id);
    expect(renamed.id, isNot(first.id));
    expect(moved.id, isNot(first.id));
  });

  test('caps alarms at 400', () {
    final plans = [
      for (var index = 0; index < 6; index++)
        plan(id: 'p$index', times: ['06:30', '12:00', '18:00', '22:00']),
    ];

    final alarms = buildAlarms(plans, now);

    expect(alarms.length, lessThanOrEqualTo(400));
    expect(alarms.last.kind, AlarmKind.keepGoing);
  });
}
