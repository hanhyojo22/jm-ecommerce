import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/models/store_branch.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/providers/order_providers.dart';
import 'package:jmmart/src/providers/store_locator_providers.dart';
import 'package:jmmart/src/screens/account_screen.dart';
import 'package:jmmart/src/theme.dart';
import 'package:jmmart/src/widgets/shell.dart';

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
      expect(find.textContaining('QA Buyer'), findsWidgets);
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

  testWidgets(
    'redesigned account screen renders grouped sections, orders shortcuts, branch locator, and settings entry',
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

      expect(find.text('Account'), findsOneWidget);
      expect(find.byTooltip('Settings'), findsOneWidget);
      expect(find.text('QA Buyer'), findsOneWidget);
      expect(find.text('Verified'), findsOneWidget);
      expect(find.text('My orders'), findsOneWidget);
      expect(find.text('View all'), findsOneWidget);
      expect(find.text('To pay'), findsOneWidget);
      expect(find.text('Preparing'), findsOneWidget);
      expect(find.text('On the way'), findsOneWidget);
      expect(find.text('To review'), findsOneWidget);
      expect(find.text('Returns'), findsOneWidget);

      expect(find.text('Pharmacy & care'), findsOneWidget);
      expect(find.text('Medicine reminders'), findsOneWidget);
      expect(find.text('Branch locator'), findsOneWidget);
      expect(find.text('Pharmacist messages'), findsOneWidget);

      await tester.tap(find.text('On the way'));
      await tester.pumpAndSettle();

      expect(container.read(ordersTabProvider), 'to_receive');
      expect(container.read(shellTabProvider).index, 1);

      await tester.scrollUntilVisible(find.text('Sign out'), 200);
      expect(find.text('Shopping'), findsOneWidget);
      expect(find.text('Delivery addresses'), findsOneWidget);
      expect(find.text('Saved items'), findsOneWidget);
      expect(find.text('My reviews'), findsOneWidget);
      expect(find.text('Settings'), findsOneWidget);
      expect(
        find.text('Dose schedules, refills & expiry alerts'),
        findsNothing,
      );
      expect(find.text('Manage saved delivery locations'), findsNothing);

      // Sign out opens confirmation dialog before signing out.
      await tester.tap(find.text('Sign out'));
      await tester.pumpAndSettle();
      expect(find.text('Sign out of Everyday Pharmacy?'), findsOneWidget);
      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();
      expect(find.text('Sign out of Everyday Pharmacy?'), findsNothing);
    },
  );
}

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
    profileComplete: true,
  );

  @override
  Future<Account> me() async => _account();

  @override
  Future<List<DiscountCard>> discountCards() async => cards;

  @override
  Future<List<Conversation>> conversations({String? search}) async => const [];

  @override
  Future<List<StoreBranch>> storeLocations() async => const [];

  @override
  Future<void> deleteDiscountCard(String id) async {
    deletedIds.add(id);
    cards = cards.where((card) => card.id != id).toList();
  }
}
