import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/models/medication.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/screens/medicine_people_screen.dart';
import 'package:jmmart/src/screens/medicine_setup_screen.dart';
import 'package:jmmart/src/screens/medicines_screen.dart';

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
}

Widget _app(_MedicineApi api, Widget home) => ProviderScope(
  overrides: [
    isSignedInProvider.overrideWithValue(true),
    apiClientProvider.overrideWithValue(api),
  ],
  child: MaterialApp(home: home),
);

MedicationPlan _plan({
  String kind = 'maintenance',
  List<String> times = const ['08:00'],
  int? minHours,
  DateTime? reorderOn,
}) => MedicationPlan(
  id: 'plan-1',
  productId: 'prod-1',
  productName: 'Amlodipine 5 mg Tablet',
  medicineKind: kind,
  doseQuantity: 1,
  doseUnit: 'tablet',
  doseTimes: times,
  startsOn: DateTime(2026, 12, 1),
  status: 'active',
  unitsLeft: 10,
  minHoursBetween: minHours,
  reorderOn: reorderOn,
);

class _MedicineApi extends ApiClient {
  _MedicineApi({
    required this.consented,
    this.plans = const [],
    this.warning,
    this.invitations = const [],
  }) : super(baseUrl: 'http://localhost', tokenProvider: () async => null);

  bool consented;
  final List<MedicationPlan> plans;
  final String? warning;
  final List<CaregiverLink> invitations;
  final accepted = <String>[];
  final consentCalls = <bool>[];
  final logged = <Map<String, dynamic>>[];
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
  Future<List<CareProfile>> careProfiles() async => const [];

  @override
  Future<Caregivers> caregivers() async =>
      Caregivers(caregivers: const [], invitations: invitations);

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
