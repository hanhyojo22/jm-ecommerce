import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/models/medication.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/screens/medicine_history_screen.dart';
import 'package:jmmart/src/screens/medicine_people_screen.dart';
import 'package:jmmart/src/screens/medicine_setup_screen.dart';
import 'package:jmmart/src/screens/medicines_screen.dart';
import 'package:jmmart/src/theme.dart';

void main() {
  testWidgets('the consent screen comes first and opting in shows the list', (
    tester,
  ) async {
    final api = _MedicineApi(consented: false);
    await tester.pumpWidget(_app(api, const MedicinesScreen()));
    await tester.pumpAndSettle();

    expect(find.text('Never miss a dose'), findsOneWidget);
    expect(api.consentCalls, isEmpty);

    await tester.tap(find.text('I agree, turn on reminders'));
    await tester.pumpAndSettle();

    expect(api.consentCalls, [true]);
    expect(find.text('No reminders yet'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('a form prefilled from the label saves the pharmacy directions', (
    tester,
  ) async {
    final api = _MedicineApi(consented: true);
    final router = GoRouter(
      routes: [
        GoRoute(
          path: '/',
          builder: (context, state) => const Scaffold(body: Text('Home')),
        ),
        GoRoute(
          path: '/setup',
          builder: (context, state) => const MedicineSetupScreen(
            suggestion: MedicationSuggestion(
              orderItemId: 'item-1',
              productId: 'prod-1',
              productName: 'Amlodipine 5 mg Tablet',
              quantity: 1,
              directions: MedicationDirections(
                medicineKind: 'maintenance',
                packUnits: 30,
                doseQuantity: 1,
                doseUnit: 'tablet',
                dosesPerDay: 2,
              ),
            ),
          ),
        ),
      ],
    );
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          isSignedInProvider.overrideWithValue(true),
          apiClientProvider.overrideWithValue(api),
        ],
        child: MaterialApp.router(routerConfig: router),
      ),
    );
    router.push('/setup');
    await tester.pumpAndSettle();

    expect(find.text('8:00 AM'), findsOneWidget);
    expect(find.text('8:00 PM'), findsOneWidget);
    expect(find.text('How many did you receive?'), findsNothing);

    await tester.ensureVisible(find.text('Save reminders'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Save reminders'));
    await tester.pumpAndSettle();

    expect(api.started, isNotNull);
    expect(api.started!['order_item_id'], 'item-1');
    expect(api.started!['medicine_kind'], 'maintenance');
    expect(api.started!['dose_quantity'], 1.0);
    expect(api.started!['dose_times'], ['08:00', '20:00']);
    expect(api.started!['show_name_on_lock_screen'], false);
    expect(find.text('Home'), findsOneWidget);
  });

  testWidgets('"I took one" warns when an as-needed dose comes too soon', (
    tester,
  ) async {
    final api = _MedicineApi(
      consented: true,
      plans: [_plan(kind: 'as_needed', times: const [], minHours: 4)],
      warning: 'too_soon',
    );
    await tester.pumpWidget(_app(api, const MedicinesScreen()));
    await tester.pumpAndSettle();

    await tester.tap(find.text('I took one'));
    await tester.pumpAndSettle();

    expect(api.logged, hasLength(1));
    expect(api.logged.single['status'], 'taken');
    expect(
      find.text("It's been less than 4 hours since your last dose."),
      findsOneWidget,
    );
  });

  testWidgets(
    'an invitation shows who asked, next to a compact Accept button',
    (tester) async {
      final api = _MedicineApi(
        consented: false,
        invitations: const [
          CaregiverLink(
            id: 'link-1',
            status: 'pending',
            invitedEmail: 'anak@example.com',
            patientName: 'Franil Escalicas',
          ),
        ],
      );
      await tester.pumpWidget(
        _app(
          api,
          Theme(
            data: ThemeData(
              filledButtonTheme: FilledButtonThemeData(
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(52),
                ),
              ),
            ),
            child: const MedicinePeopleScreen(),
          ),
        ),
      );
      await tester.pumpAndSettle();

      final name = tester.getSize(find.text('Franil Escalicas'));
      expect(name.width, greaterThan(100));
      expect(tester.getSize(find.text('Accept')).width, lessThan(200));
      await tester.tap(find.text('Accept'));
      await tester.pumpAndSettle();
      expect(api.accepted, ['link-1']);
    },
  );

  testWidgets('a running-low plan offers a reorder', (tester) async {
    final api = _MedicineApi(
      consented: true,
      plans: [_plan(reorderOn: DateUtils.dateOnly(DateTime.now()))],
    );
    await tester.pumpWidget(_app(api, const MedicinesScreen()));
    await tester.pumpAndSettle();

    expect(find.text('Amlodipine 5 mg Tablet is running low.'), findsOneWidget);
    expect(find.text('Reorder'), findsOneWidget);
  });

  testWidgets('tapping a dose time pill logs that scheduled slot', (
    tester,
  ) async {
    final today = DateUtils.dateOnly(DateTime.now());
    final eightAm = DateTime(today.year, today.month, today.day, 8, 0);
    final api = _MedicineApi(
      consented: true,
      plans: [
        _plan(
          startsOn: today.subtract(const Duration(days: 1)),
          times: const ['08:00', '20:00'],
          answeredSlots: [eightAm],
        ),
      ],
    );
    await tester.pumpWidget(
      _app(api, const MedicinesScreen(), theme: buildAppTheme()),
    );
    await tester.pumpAndSettle();

    expect(find.text('1 of 2 doses marked today'), findsOneWidget);

    await tester.tap(find.text('8:00 PM'));
    await tester.pumpAndSettle();
    expect(find.text('Mark as taken'), findsOneWidget);

    await tester.tap(find.text('Mark as taken'));
    await tester.pumpAndSettle();

    expect(api.logged, hasLength(1));
    expect(api.logged.single['status'], 'taken');
    expect(api.logged.single['scheduled_for'], contains('T20:00:00+08:00'));
    expect(tester.takeException(), isNull);
  });

  testWidgets('dose history renders adherence and legend in dark theme', (
    tester,
  ) async {
    final api = _MedicineApi(
      consented: true,
      history: [
        DoseHistory(
          planId: 'plan-1',
          productName: 'Amlodipine 5 mg Tablet',
          medicineKind: 'maintenance',
          takenCount: 1,
          adherence: 0.5,
          slots: [
            DoseSlot(
              scheduledFor: DateTime(2026, 11, 28, 8, 0),
              status: 'taken',
            ),
            DoseSlot(
              scheduledFor: DateTime(2026, 11, 28, 20, 0),
              status: 'not_marked',
            ),
          ],
        ),
      ],
    );
    await tester.pumpWidget(
      _app(
        api,
        const MedicineHistoryScreen(),
        theme: buildAppTheme(brightness: Brightness.dark),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('50%'), findsOneWidget);
    expect(find.text('Taken'), findsOneWidget);
    expect(find.text('Skipped'), findsOneWidget);
    expect(find.text('Not marked'), findsOneWidget);
    expect(find.text('8:00 AM'), findsOneWidget);
    expect(find.text('8:00 PM'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('adding a family member uses relationship chips and saves', (
    tester,
  ) async {
    final api = _MedicineApi(consented: true);
    await tester.pumpWidget(
      _app(api, const MedicinePeopleScreen(), theme: buildAppTheme()),
    );
    await tester.pumpAndSettle();

    expect(find.text('No family profiles yet'), findsOneWidget);
    await tester.tap(find.text('Add family member'));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextFormField).first, 'Rosa Cruz');
    await tester.tap(find.text('Lola'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Add family member').last);
    await tester.pumpAndSettle();

    expect(api.savedProfiles, hasLength(1));
    expect(api.savedProfiles.single.name, 'Rosa Cruz');
    expect(api.savedProfiles.single.relation, 'Lola');
    expect(tester.takeException(), isNull);
  });

  testWidgets('inviting a caregiver previews privacy and sends invite', (
    tester,
  ) async {
    final api = _MedicineApi(
      consented: true,
      profiles: const [CareProfile(id: 'p1', name: 'Lola Rosa', relation: 'Lola')],
      plans: [_plan(profileId: 'p1')],
    );
    await tester.pumpWidget(
      _app(
        api,
        const MedicinePeopleScreen(),
        theme: buildAppTheme(brightness: Brightness.dark),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Lola Rosa'), findsOneWidget);
    expect(find.text('Lola · 1 reminder'), findsOneWidget);

    await tester.ensureVisible(find.text('Invite a caregiver'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Invite a caregiver'));
    await tester.pumpAndSettle();

    expect(
      find.text('"You haven\'t marked your 8:00 AM medicine as taken."'),
      findsOneWidget,
    );

    await tester.enterText(
      find.byType(TextFormField),
      'caregiver@example.com',
    );
    await tester.tap(find.text('Include medicine names in alerts'));
    await tester.pumpAndSettle();

    expect(
      find.text('"You haven\'t marked your 8:00 AM Amlodipine as taken."'),
      findsOneWidget,
    );

    await tester.tap(find.text('Send invitation'));
    await tester.pumpAndSettle();

    expect(api.invitedCaregivers, hasLength(1));
    expect(api.invitedCaregivers.single.email, 'caregiver@example.com');
    expect(api.invitedCaregivers.single.shareNames, true);
    expect(tester.takeException(), isNull);
  });
}

Widget _app(_MedicineApi api, Widget home, {ThemeData? theme}) => ProviderScope(
  overrides: [
    isSignedInProvider.overrideWithValue(true),
    apiClientProvider.overrideWithValue(api),
  ],
  child: MaterialApp(theme: theme, home: home),
);

MedicationPlan _plan({
  String kind = 'maintenance',
  List<String> times = const ['08:00'],
  int? minHours,
  DateTime? reorderOn,
  DateTime? startsOn,
  List<DateTime> answeredSlots = const [],
  String? profileId,
}) => MedicationPlan(
  id: 'plan-1',
  productId: 'prod-1',
  productName: 'Amlodipine 5 mg Tablet',
  profileId: profileId,
  medicineKind: kind,
  doseQuantity: 1,
  doseUnit: 'tablet',
  doseTimes: times,
  startsOn: startsOn ?? DateTime(2026, 12, 1),
  status: 'active',
  unitsLeft: 10,
  minHoursBetween: minHours,
  reorderOn: reorderOn,
  answeredSlots: answeredSlots,
);

class _MedicineApi extends ApiClient {
  _MedicineApi({
    required this.consented,
    this.plans = const [],
    this.warning,
    this.invitations = const [],
    this.history = const [],
    this.profiles = const [],
  }) : super(baseUrl: 'http://localhost', tokenProvider: () async => null);

  bool consented;
  final List<MedicationPlan> plans;
  final String? warning;
  final List<CaregiverLink> invitations;
  final List<DoseHistory> history;
  final List<CareProfile> profiles;
  final accepted = <String>[];
  final consentCalls = <bool>[];
  final logged = <Map<String, dynamic>>[];
  final savedProfiles = <({String? id, String name, String? relation})>[];
  final invitedCaregivers = <({String email, bool shareNames})>[];
  Map<String, dynamic>? started;

  @override
  Future<Account> me() async => Account(
    id: 'u1',
    name: 'Maria',
    email: 'maria@example.com',
    accountType: 'buyer',
    emailVerified: true,
    isPlatformAdministrator: false,
    medicationReminders: consented,
  );

  @override
  Future<void> setMedicationConsent(bool enabled) async {
    consentCalls.add(enabled);
    consented = enabled;
  }

  @override
  Future<List<MedicationPlan>> medicationPlans() async => plans;

  @override
  Future<List<MedicationSuggestion>> medicationSuggestions() async => const [];

  @override
  Future<List<CareProfile>> careProfiles() async => profiles;

  @override
  Future<CareProfile> saveCareProfile({
    String? id,
    required String name,
    String? relation,
  }) async {
    savedProfiles.add((id: id, name: name, relation: relation));
    return CareProfile(id: id ?? 'p-new', name: name, relation: relation);
  }

  @override
  Future<void> inviteCaregiver({
    required String email,
    bool shareMedicineNames = false,
  }) async {
    invitedCaregivers.add((email: email, shareNames: shareMedicineNames));
  }

  @override
  Future<Caregivers> caregivers() async =>
      Caregivers(caregivers: const [], invitations: invitations);

  @override
  Future<List<DoseHistory>> medicationHistory({
    String? profileId,
    int days = 30,
  }) async => history;

  @override
  Future<void> acceptCaregiver(String id) async => accepted.add(id);

  @override
  Future<MedicationPlan> startMedicationPlan(Map<String, dynamic> body) async {
    started = body;
    return _plan();
  }

  @override
  Future<({MedicationPlan plan, String? warning})> logDoses(
    String planId,
    List<Map<String, dynamic>> logs,
  ) async {
    logged.addAll(logs);
    return (plan: plans.first, warning: warning);
  }
}
