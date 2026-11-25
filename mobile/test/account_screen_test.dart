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
import 'package:jmmart/src/providers/store_locator_providers.dart';

void main() {
  testWidgets(
    'saved ID removal requires confirmation and cancel keeps the ID',
    (tester) async {
      final api = _AccountApi()
        ..cards = [
          const DiscountCard(
            id: 'id-1',
            type: 'pwd',
            name: 'QA Buyer',
            idNumberMasked: '****1234',
          ),
        ];
      await _pump(tester, api);
      await tester.scrollUntilVisible(find.byTooltip('Delete'), 200);
      await tester.tap(find.byTooltip('Delete'));
      await tester.pumpAndSettle();
      expect(api.deletedIds, isEmpty);
      await tester.tap(find.text('Keep ID'));
      await tester.pumpAndSettle();
      expect(api.deletedIds, isEmpty);
      expect(find.text('QA Buyer'), findsWidgets);
      await tester.tap(find.byTooltip('Delete'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Remove ID'));
      await tester.pumpAndSettle();
      expect(api.deletedIds, ['id-1']);
      expect(find.text('Saved ID removed.'), findsOneWidget);
      expect(find.byTooltip('Delete'), findsNothing);
    },
  );

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
        overrides: [
          isSignedInProvider.overrideWithValue(true),
          apiClientProvider.overrideWithValue(api),
          activeBranchIdProvider.overrideWith((ref) async => 'B-TEST'),
        ],
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
      // No marketplace extras: membership badge, Member Center or repeated shortcuts.
      expect(find.text('Member Center'), findsNothing);
      expect(find.text('BUYER'), findsNothing);
      expect(find.text('Your Cart'), findsNothing);
      expect(find.text('Delivery addresses'), findsOneWidget);
      expect(find.text('Help'), findsOneWidget);
      expect(find.text('My Orders'), findsOneWidget);
      expect(find.text('View All Orders'), findsOneWidget);
      expect(find.text('To Pay'), findsOneWidget);
      expect(find.text('Preparing'), findsOneWidget);
      expect(find.text('On the way'), findsOneWidget);
      expect(find.text('To Review'), findsOneWidget);

      await tester.tap(find.text('On the way'));
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
      overrides: [
        isSignedInProvider.overrideWithValue(true),
        apiClientProvider.overrideWithValue(api),
        activeBranchIdProvider.overrideWith((ref) async => 'B-TEST'),
      ],
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
  List<DiscountCard> cards = [];
  final deletedIds = <String>[];

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
  Future<List<DiscountCard>> discountCards() async => cards;

  @override
  Future<void> deleteDiscountCard(String id) async {
    deletedIds.add(id);
    cards = cards.where((card) => card.id != id).toList();
  }

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
