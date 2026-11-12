import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/providers/order_providers.dart';
import 'package:jmmart/src/screens/account_screen.dart';
import 'package:jmmart/src/theme.dart';
import 'package:jmmart/src/widgets/shell.dart';

void main() {
  test('cart reminders default on unless the account turned them off', () {
    Account parse(Map<String, dynamic> extra) => Account.fromJson({
      'id': 'u1',
      'name': 'Ana',
      'email': 'a@x.test',
      ...extra,
    });

    expect(parse({}).cartReminders, isTrue);
    expect(parse({'cart_reminders': true}).cartReminders, isTrue);
    expect(parse({'cart_reminders': false}).cartReminders, isFalse);
  });

  testWidgets('turning cart reminders off saves and shows the new state', (
    tester,
  ) async {
    final api = _AccountApi();
    await _pump(tester, api);

    expect(_switch(tester).value, isTrue);

    await tester.tap(find.text('Cart reminders'));
    await tester.pumpAndSettle();

    expect(api.updates, [false]);
    expect(_switch(tester).value, isFalse);
  });

  testWidgets('a failed save keeps reminders on and says so', (tester) async {
    final api = _AccountApi()..fail = true;
    await _pump(tester, api);

    await tester.tap(find.text('Cart reminders'));
    await tester.pumpAndSettle();

    expect(api.updates, [false]);
    expect(find.text('Could not save. Try again.'), findsOneWidget);
    expect(_switch(tester).value, isTrue);
  });

  testWidgets(
    'account screen renders Lazada sections and My Orders shortcuts',
    (tester) async {
      final api = _AccountApi();
      final container = ProviderContainer(
        overrides: [apiClientProvider.overrideWithValue(api)],
      );
      addTearDown(container.dispose);

      await tester.pumpWidget(
        UncontrolledProviderScope(
          container: container,
          child: MaterialApp(
            theme: buildAppTheme(),
            home: const Scaffold(body: AccountScreen()),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('QA Buyer'), findsOneWidget);
      expect(find.text('Member Center'), findsOneWidget);
      expect(find.text('My Orders'), findsOneWidget);
      expect(find.text('View All Orders'), findsOneWidget);
      expect(find.text('To Pay'), findsOneWidget);
      expect(find.text('To Ship'), findsOneWidget);
      expect(find.text('To Receive'), findsOneWidget);
      expect(find.text('To Review'), findsOneWidget);
      expect(find.text('My Channels'), findsOneWidget);

      await tester.tap(find.text('To Receive'));
      await tester.pumpAndSettle();

      expect(container.read(ordersTabProvider), 'to_receive');
      expect(container.read(shellTabProvider).index, 1);
    },
  );
}

SwitchListTile _switch(WidgetTester tester) =>
    tester.widget<SwitchListTile>(find.byType(SwitchListTile));

Future<void> _pump(WidgetTester tester, _AccountApi api) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(api)],
      child: MaterialApp(
        theme: buildAppTheme(),
        home: const Scaffold(body: AccountScreen()),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

class _AccountApi extends ApiClient {
  _AccountApi()
    : super(baseUrl: 'http://localhost', tokenProvider: () async => null);

  bool cartReminders = true;
  bool fail = false;
  final List<bool> updates = [];

  Account _account() => Account(
    id: 'buyer-1',
    name: 'QA Buyer',
    email: 'buyer@example.test',
    accountType: 'buyer',
    emailVerified: true,
    isPlatformAdministrator: false,
    cartReminders: cartReminders,
  );

  @override
  Future<Account> me() async => _account();

  @override
  Future<Account> updateCartReminders(bool enabled) async {
    updates.add(enabled);
    if (fail) {
      throw Exception('offline');
    }
    cartReminders = enabled;
    return _account();
  }
}
