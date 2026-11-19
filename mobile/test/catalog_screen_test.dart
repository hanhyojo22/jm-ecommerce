import 'dart:async';
import 'dart:ui' show SemanticsAction;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/core/api_exception.dart';
import 'package:jmmart/src/core/location_service.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/models/store_branch.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/providers/cart_providers.dart';
import 'package:jmmart/src/providers/catalog_providers.dart';
import 'package:jmmart/src/providers/order_providers.dart';
import 'package:jmmart/src/providers/store_locator_providers.dart';
import 'package:jmmart/src/screens/catalog_screen.dart';
import 'package:jmmart/src/screens/search_screen.dart';
import 'package:jmmart/src/theme.dart';
import 'package:jmmart/src/widgets/common.dart';
import 'package:jmmart/src/widgets/shell.dart';

void main() {
  testWidgets('search placeholder scrolls without submitting a search', (
    tester,
  ) async {
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
    final api = _CatalogApi();
    await _pumpCatalog(tester, api);
    final requests = api.firstPageRequests;
    expect(find.text('Search medicines'), findsOneWidget);

    await tester.pump(const Duration(seconds: 3));
    await tester.pumpAndSettle();

    expect(find.text('Search generic or brand name'), findsOneWidget);
    expect(api.firstPageRequests, requests);
    expect(tester.takeException(), isNull);
  });

  testWidgets('reduced motion keeps the search placeholder still', (
    tester,
  ) async {
    await _pumpCatalog(tester, _CatalogApi(), disableAnimations: true);
    await tester.pump(const Duration(seconds: 10));
    expect(find.text('Search medicines'), findsOneWidget);
    expect(find.text('Search generic or brand name'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  test(
    'browsing keeps popular sorting across pages and searching uses relevance',
    () async {
      final api = _CatalogApi()..paginated = true;
      final container = ProviderContainer(
        overrides: [
          isSignedInProvider.overrideWithValue(true),
          apiClientProvider.overrideWithValue(api),
          activeBranchIdProvider.overrideWith((ref) async => 'B-TEST'),
          cartCountProvider.overrideWithValue(0),
        ],
      );
      addTearDown(container.dispose);
      final subscription = container.listen(productsProvider, (_, _) {});
      addTearDown(subscription.close);

      await container.read(productsProvider.future);
      expect(api.lastSort, 'popular');
      await container.read(productsProvider.notifier).loadMore();
      expect(api.lastSort, 'popular');
      container.read(catalogFilterProvider.notifier).setCategory('fashion');
      await container.read(productsProvider.future);
      expect(api.lastSort, 'popular');
      container.read(catalogFilterProvider.notifier).setQuery('shirt');
      await container.read(productsProvider.future);
      expect(api.lastSort, 'relevance');
    },
  );

  testWidgets('the end of the catalog offers a working return to the top', (
    tester,
  ) async {
    await _pumpCatalog(tester, _CatalogApi(), disableAnimations: true);
    await tester.fling(
      find.byType(CustomScrollView),
      const Offset(0, -6000),
      3000,
    );
    await tester.pumpAndSettle();
    expect(
      find.text('You have seen all 12 products').hitTestable(),
      findsOneWidget,
    );

    await tester.tap(find.text('Back to top'));
    await tester.pumpAndSettle();

    final scroll = tester.widget<CustomScrollView>(
      find.byType(CustomScrollView),
    );
    expect(scroll.controller!.offset, 0);
    expect(find.text(_homeMarker).hitTestable(), findsOneWidget);
  });

  testWidgets('a new filter does not display products from the previous feed', (
    tester,
  ) async {
    final api = _CatalogApi();
    await _pumpCatalog(tester, api);
    api.pendingFirstPage = Completer<ProductPage>();
    await tester.tap(find.text('Fashion'));
    await tester.pump();

    expect(find.byType(ProductCard), findsNothing);
    expect(find.bySemanticsLabel('Loading products'), findsOneWidget);

    api.pendingFirstPage!.complete(
      ProductPage(
        products: [_product(99, name: 'Matching bag')],
        page: 1,
        lastPage: 1,
        total: 1,
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Matching bag'), findsOneWidget);
  });

  testWidgets('an initial catalog failure can be retried', (tester) async {
    final api = _CatalogApi()..failFirstPage = true;
    await _pumpCatalog(tester, api);
    expect(find.byType(ProductCard), findsNothing);
    expect(find.text('Try again'), findsOneWidget);

    api.failFirstPage = false;
    await tester.tap(find.text('Try again'));
    await tester.pumpAndSettle();

    expect(find.byType(ProductCard), findsWidgets);
    expect(find.text('Try again'), findsNothing);
  });

  testWidgets('a failed refresh keeps products and offers a retry', (
    tester,
  ) async {
    final api = _CatalogApi()..paginated = true;
    await _pumpCatalog(tester, api);
    api.failFirstPage = true;

    final refreshed = expectLater(
      tester
          .widget<RefreshIndicator>(find.byType(RefreshIndicator))
          .onRefresh(),
      completes,
    );
    await tester.pumpAndSettle();
    await refreshed;

    expect(find.byType(ProductCard), findsWidgets);
    expect(find.text('Could not refresh products.'), findsOneWidget);
    await tester.drag(find.byType(CustomScrollView), const Offset(0, -2200));
    await tester.pumpAndSettle();
    expect(api.nextPageRequests, 0);
    api.failFirstPage = false;
    await tester.tap(find.text('Retry refresh'));
    await tester.pumpAndSettle();
    expect(find.text('Could not refresh products.'), findsNothing);
    expect(find.byType(ProductCard), findsWidgets);
  });

  testWidgets('scrolling does not rebuild product cards already in view', (
    tester,
  ) async {
    await _pumpCatalog(tester, _CatalogApi());
    final scroll = tester.widget<CustomScrollView>(
      find.byType(CustomScrollView),
    );
    scroll.controller!.jumpTo(400);
    await tester.pumpAndSettle();
    final card = find.byWidgetPredicate(
      (widget) => widget is ProductCard && widget.product.id == '0',
    );
    expect(card, findsOneWidget);
    final element = tester.element(card);
    var rebuilds = 0;
    final previous = debugOnRebuildDirtyWidget;
    debugOnRebuildDirtyWidget = (candidate, builtOnce) {
      previous?.call(candidate, builtOnce);
      if (identical(candidate, element)) rebuilds++;
    };
    addTearDown(() => debugOnRebuildDirtyWidget = previous);

    await tester.drag(find.byType(CustomScrollView), const Offset(0, -40));
    await tester.pumpAndSettle();

    expect(
      rebuilds,
      0,
      reason:
          'Scrolling should lay out retained cards without rebuilding them.',
    );
  });

  test('overlapping pages do not repeat a product in the feed', () async {
    final api = _CatalogApi()..paginated = true;
    final container = ProviderContainer(
      overrides: [
        isSignedInProvider.overrideWithValue(true),
        apiClientProvider.overrideWithValue(api),
        activeBranchIdProvider.overrideWith((ref) async => 'B-TEST'),
        cartCountProvider.overrideWithValue(0),
      ],
    );
    addTearDown(container.dispose);
    final subscription = container.listen(productsProvider, (_, _) {});
    addTearDown(subscription.close);
    final initial = await container.read(productsProvider.future);
    api.pendingPage = Completer<ProductPage>()
      ..complete(
        ProductPage(
          products: [initial.products.last, _product(99)],
          page: 2,
          lastPage: 2,
          total: 13,
        ),
      );

    await container.read(productsProvider.notifier).loadMore();

    final products = container.read(productsProvider).requireValue.products;
    expect(products.map((product) => product.id), [
      ...initial.products.map((product) => product.id),
      '99',
    ]);
  });

  for (final fails in [false, true]) {
    test(
      'a stale next page cannot overwrite a refreshed feed ($fails)',
      () async {
        final api = _CatalogApi()..paginated = true;
        final container = ProviderContainer(
          overrides: [
            isSignedInProvider.overrideWithValue(true),
            apiClientProvider.overrideWithValue(api),
            activeBranchIdProvider.overrideWith((ref) async => 'B-TEST'),
            cartCountProvider.overrideWithValue(0),
          ],
        );
        addTearDown(container.dispose);
        final subscription = container.listen(productsProvider, (_, _) {});
        addTearDown(subscription.close);
        await container.read(productsProvider.future);
        api.pendingPage = Completer<ProductPage>();
        final loading = container.read(productsProvider.notifier).loadMore();
        container.invalidate(productsProvider);
        final refreshed = await container.read(productsProvider.future);
        if (fails) {
          api.pendingPage!.completeError(
            const ApiException(
              code: 'network.error',
              message: 'No connection.',
            ),
          );
        } else {
          api.pendingPage!.complete(
            const ProductPage(products: [], page: 2, lastPage: 2, total: 24),
          );
        }
        await loading;
        expect(container.read(productsProvider).value, same(refreshed));
      },
    );
  }

  test('an in-flight next page safely finishes after disposal', () async {
    final api = _CatalogApi()..paginated = true;
    final container = ProviderContainer(
      overrides: [
        isSignedInProvider.overrideWithValue(true),
        apiClientProvider.overrideWithValue(api),
        activeBranchIdProvider.overrideWith((ref) async => 'B-TEST'),
        cartCountProvider.overrideWithValue(0),
      ],
    );
    container.listen(productsProvider, (_, _) {});
    await container.read(productsProvider.future);
    api.pendingPage = Completer<ProductPage>();
    final loading = container.read(productsProvider.notifier).loadMore();
    container.dispose();
    api.pendingPage!.complete(
      const ProductPage(products: [], page: 2, lastPage: 2, total: 24),
    );
    await expectLater(loading, completes);
  });

  testWidgets('categories support TalkBack and Android touch targets', (
    tester,
  ) async {
    final semantics = tester.ensureSemantics();
    try {
      await _pumpCatalog(tester, _CatalogApi());
      final category = tester.getSemantics(
        find.bySemanticsLabel('Home & living'),
      );
      expect(
        category.getSemanticsData().hasAction(SemanticsAction.tap),
        isTrue,
      );
      await expectLater(tester, meetsGuideline(androidTapTargetGuideline));
      await expectLater(tester, meetsGuideline(labeledTapTargetGuideline));
    } finally {
      semantics.dispose();
    }
  });

  testWidgets('categories without artwork remain visible and selectable', (
    tester,
  ) async {
    final api = _CatalogApi()
      ..categoryItems = const [
        Category(
          id: 'seasonal',
          name: 'Seasonal Picks',
          slug: 'seasonal-picks',
          productCount: 3,
        ),
      ];
    await _pumpCatalog(tester, api);
    expect(tester.takeException(), isNull);

    await tester.tap(find.text('Seasonal Picks'));
    await tester.pumpAndSettle();

    expect(api.lastCategory, 'seasonal-picks');
    expect(find.text('All'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('category selection filters products and All resets it', (
    tester,
  ) async {
    final api = _CatalogApi();
    await _pumpCatalog(tester, api);
    await tester.tap(find.text('Home & living'));
    await tester.pumpAndSettle();

    expect(api.lastCategory, 'home-living');
    expect(find.text(_homeMarker), findsNothing);
    expect(find.text('Home & living'), findsNWidgets(2));

    await tester.tap(find.text('All'));
    await tester.pumpAndSettle();
    expect(api.lastCategory, isNull);
    expect(find.text(_homeMarker), findsOneWidget);
  });

  testWidgets('search stays visible while browsing the product feed', (
    tester,
  ) async {
    await _pumpCatalog(tester, _CatalogApi());
    await tester.drag(find.byType(CustomScrollView), const Offset(0, -650));
    await tester.pumpAndSettle();

    final search = find.bySemanticsLabel('Search medicines');
    expect(tester.getTopLeft(search).dy, greaterThanOrEqualTo(0));
    expect(tester.getBottomLeft(search).dy, lessThan(120));
    expect(search.hitTestable(), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('failed categories can be retried without blocking products', (
    tester,
  ) async {
    final api = _CatalogApi()..failCategories = true;
    await _pumpCatalog(tester, api);
    expect(find.text('Reload categories'), findsOneWidget);
    expect(find.text('Popular products'), findsOneWidget);

    api.failCategories = false;
    await tester.tap(find.text('Reload categories'));
    await tester.pumpAndSettle();
    expect(find.text('Home & living'), findsOneWidget);
    expect(find.text('Reload categories'), findsNothing);
  });

  testWidgets('quick actions open delivered orders and the branch chat', (
    tester,
  ) async {
    final api = _CatalogApi();
    await _pumpShopRouter(tester, api);
    final container = ProviderScope.containerOf(
      tester.element(find.byType(CatalogScreen)),
    );

    await tester.tap(find.text('Refill medicines'));
    await tester.pump();
    expect(container.read(ordersTabProvider), 'delivered');
    expect(container.read(shellTabProvider).index, 1);

    await tester.tap(find.text('Send prescription'));
    await tester.pumpAndSettle();
    expect(api.chatBranch, 'B-TEST');
    expect(find.text('Chat C-1'), findsOneWidget);
  });

  testWidgets('best sellers show ranked at the branch', (tester) async {
    final api = _CatalogApi()
      ..bestSellerProducts = [
        _product(91, name: 'Cetirizine 10 mg'),
        _product(92, name: 'Ascorbic Acid 500 mg'),
      ];
    await _pumpCatalog(tester, api);

    expect(find.text('Best sellers at your branch'), findsOneWidget);
    expect(find.text('Cetirizine 10 mg'), findsOneWidget);
    expect(find.bySemanticsLabel('Number 1'), findsOneWidget);
    expect(find.bySemanticsLabel('Number 2'), findsOneWidget);
  });

  testWidgets('without a known branch the shop asks for one first', (
    tester,
  ) async {
    await _pumpCatalog(
      tester,
      _CatalogApi(),
      locator: const StoreLocatorState(
        branches: [
          BranchDistance(
            StoreBranch(
              id: 'cebu',
              branchId: 'B-CEBU',
              name: 'Cebu branch',
              address: 'Cebu',
              latitude: 10.3,
              longitude: 123.9,
            ),
            null,
          ),
        ],
        issue: LocationIssue.denied,
      ),
    );

    expect(find.text('Choose your branch'), findsOneWidget);
    expect(find.text('Popular products'), findsNothing);
  });

  testWidgets('the branch picker shows the delivery fee', (tester) async {
    await _pumpCatalog(
      tester,
      _CatalogApi(),
      locator: const StoreLocatorState(
        branches: [
          BranchDistance(
            StoreBranch(
              id: 'cebu',
              branchId: 'B-CEBU',
              name: 'Cebu branch',
              address: 'Cebu',
              latitude: 10.3,
              longitude: 123.9,
              deliveryFee: '49.00',
              freeDeliveryMinimum: '500.00',
            ),
            1.1,
          ),
        ],
      ),
    );

    expect(find.text('1.1 km'), findsOneWidget);
    expect(find.text('Nearest branch'), findsOneWidget);
    expect(find.text('₱49.00'), findsOneWidget);
    expect(find.text('Free over ₱500.00'), findsOneWidget);

    await tester.tap(find.textContaining('Cebu branch'));
    await tester.pumpAndSettle();

    expect(
      find.textContaining('₱49.00 delivery, free over ₱500.00'),
      findsOneWidget,
    );
  });

  testWidgets('the branch header shows open, open 24 hours, or closed', (
    tester,
  ) async {
    StoreLocatorState locator({bool open = true}) => StoreLocatorState(
      branches: [
        BranchDistance(
          StoreBranch(
            id: 'cebu',
            branchId: 'B-CEBU',
            name: 'Cebu branch',
            address: 'Cebu',
            latitude: 10.3,
            longitude: 123.9,
            maxDeliveryRadiusKm: 8,
            isOpen: open,
            nextOpeningAt: open ? null : DateTime(2030, 1, 7, 8),
          ),
          2.5,
        ),
      ],
    );

    await _pumpCatalog(tester, _CatalogApi(), locator: locator());
    expect(find.text('Open'), findsOneWidget);

    await tester.pumpWidget(const SizedBox());
    await _pumpCatalog(
      tester,
      _CatalogApi(),
      locator: StoreLocatorState(
        branches: [
          BranchDistance(
            const StoreBranch(
              id: 'cebu',
              branchId: 'B-CEBU',
              name: 'Cebu branch',
              address: 'Cebu',
              latitude: 10.3,
              longitude: 123.9,
              open24Hours: true,
            ),
            2.5,
          ),
        ],
      ),
    );
    expect(find.text('24 hours'), findsOneWidget);
    expect(find.textContaining('min'), findsNothing);

    await tester.pumpWidget(const SizedBox());
    await _pumpCatalog(tester, _CatalogApi(), locator: locator(open: false));
    expect(find.text('Closed'), findsOneWidget);
    // intl puts a narrow no-break space before AM.
    expect(find.textContaining('Opens Mon 8:00'), findsOneWidget);
  });

  testWidgets('medicines bought before show in an Order again row', (
    tester,
  ) async {
    final api = _CatalogApi()
      ..boughtBeforeProducts = [_product(90, name: 'Losartan 50 mg')];
    await _pumpCatalog(tester, api);

    expect(find.text('Order again'), findsOneWidget);
    expect(find.text('Losartan 50 mg'), findsOneWidget);
  });

  testWidgets('an empty marketplace offers refresh and recovers', (
    tester,
  ) async {
    final api = _CatalogApi()..empty = true;
    await _pumpCatalog(tester, api);
    expect(find.text('No products yet'), findsOneWidget);
    expect(find.text('Clear filters'), findsNothing);

    api.empty = false;
    await tester.tap(find.text('Refresh products'));
    await tester.pumpAndSettle();
    expect(find.text('Popular products'), findsOneWidget);
    expect(find.text('No products yet'), findsNothing);
  });

  testWidgets(
    'a failed next page waits for retry instead of retrying every scroll frame',
    (tester) async {
      final api = _CatalogApi()
        ..paginated = true
        ..failNextPage = true;
      await _pumpCatalog(tester, api);
      await tester.fling(
        find.byType(CustomScrollView),
        const Offset(0, -4500),
        3000,
      );
      await tester.pumpAndSettle();

      expect(api.nextPageRequests, 1);
      api.failNextPage = false;
      await tester.tap(find.text('Retry loading more'));
      await tester.pumpAndSettle();
      expect(api.nextPageRequests, 2);
      expect(find.text('Retry loading more'), findsNothing);
    },
  );

  testWidgets('the product grid shows a skeleton while the first page loads', (
    tester,
  ) async {
    final semantics = tester.ensureSemantics();
    try {
      final api = _CatalogApi()..pendingFirstPage = Completer<ProductPage>();
      await _pumpCatalog(tester, api, settle: false);

      expect(find.bySemanticsLabel('Search medicines'), findsOneWidget);
      expect(find.bySemanticsLabel('Loading products'), findsOneWidget);

      api.pendingFirstPage!.complete(
        ProductPage(products: [_product(0)], page: 1, lastPage: 1, total: 1),
      );
      await tester.pumpAndSettle();

      expect(find.bySemanticsLabel('Loading products'), findsNothing);
      expect(find.text('Popular products'), findsOneWidget);
    } finally {
      semantics.dispose();
    }
  });

  testWidgets('top deals list only marked-down products, deepest first', (
    tester,
  ) async {
    final api = _CatalogApi()
      ..deals = [
        _product(50, name: 'Small saving', compareAt: '60000.00'),
        _product(51, name: 'Big saving', compareAt: '110000.00'),
      ];
    await _pumpCatalog(tester, api);

    // The deals strip precedes the feed (whose cards repeat the badges).
    expect(find.text('See all'), findsOneWidget);
    expect(
      tester.getTopLeft(find.text('-50%').first).dx,
      lessThan(tester.getTopLeft(find.text('-8%').first).dx),
    );

    await tester.tap(find.text('Fashion'));
    await tester.pumpAndSettle();
    expect(find.text('See all'), findsNothing);
  });

  testWidgets('See all opens the deals list and Back returns home', (
    tester,
  ) async {
    final api = _CatalogApi()
      ..deals = [_product(50, name: 'Big saving', compareAt: '110000.00')];
    await _pumpCatalog(tester, api);

    await tester.tap(find.text('See all'));
    await tester.pumpAndSettle();
    expect(api.dealsFeedRequests, 1);
    expect(find.text('Deals'), findsOneWidget);
    expect(find.text(_homeMarker), findsNothing);

    await tester.binding.handlePopRoute();
    await tester.pumpAndSettle();
    expect(find.text(_homeMarker), findsOneWidget);

    await tester.tap(find.text('See all'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('All'));
    await tester.pumpAndSettle();
    expect(find.text(_homeMarker), findsOneWidget);
  });

  testWidgets('top deals stay hidden when nothing is on sale', (tester) async {
    await _pumpCatalog(tester, _CatalogApi());
    expect(find.text('See all'), findsNothing);
  });

  testWidgets(
    'the search box opens search and back returns the Shop as it was',
    (tester) async {
      final api = _CatalogApi();
      await _pumpShopRouter(tester, api);
      await tester.tap(find.text('Fashion'));
      await tester.pumpAndSettle();

      await _openSearch(tester);
      expect(find.text('Medicine, brand or symptom'), findsOneWidget);
      expect(
        tester.widget<TextField>(find.byType(TextField)).focusNode!.hasFocus,
        isTrue,
      );

      await tester.enterText(find.byType(TextField), 'linen');
      await tester.testTextInput.receiveAction(TextInputAction.search);
      await tester.pumpAndSettle();
      expect(api.lastQuery, 'linen');
      expect(api.lastCategory, isNull);
      expect(find.text('12 medicines'), findsOneWidget);

      await tester.tap(find.byTooltip('Back'));
      await tester.pumpAndSettle();
      expect(find.byType(SearchScreen), findsNothing);
      expect(api.lastQuery, isEmpty);
      expect(api.lastCategory, 'fashion');
    },
  );

  testWidgets(
    'typing shows names, categories and medicines, and See all opens that many results',
    (tester) async {
      final api = _CatalogApi()
        ..suggestions = SearchSuggestions(
          names: const [(name: 'Paracetamol', count: 2)],
          categories: const [(slug: 'fashion', name: 'Fashion', count: 3)],
          products: [
            (
              product: _product(98, name: 'Paracetamol 500 mg Tablet'),
              atBranch: true,
            ),
            (product: _product(99, name: 'Paracetamol Syrup'), atBranch: false),
          ],
          total: 12,
        );
      await _pumpShopRouter(tester, api);
      await _openSearch(tester);
      final initialCatalogRequests = api.firstPageRequests;

      await tester.enterText(find.byType(TextField), 'para');
      await tester.pump(const Duration(milliseconds: 250));
      await tester.pumpAndSettle();

      expect(api.lastSuggestionQuery, 'para');
      expect(api.lastSuggestionBranch, 'B-TEST');
      expect(api.firstPageRequests, initialCatalogRequests);
      expect(
        find.text('Paracetamol 500 mg Tablet', findRichText: true),
        findsOneWidget,
      );
      expect(find.text('In stock here'), findsOneWidget);
      expect(find.text('Other branches'), findsOneWidget);
      expect(find.text('Fashion  3'), findsOneWidget);

      await tester.tap(find.text('See all 12 results for “para”'));
      await tester.pumpAndSettle();
      expect(api.lastQuery, 'para');
      expect(find.text('12 medicines'), findsOneWidget);
      expect(find.text('See all 12 results for “para”'), findsNothing);
    },
  );

  testWidgets('a category suggestion searches within that category', (
    tester,
  ) async {
    final api = _CatalogApi()
      ..suggestions = const SearchSuggestions(
        categories: [(slug: 'fashion', name: 'Fashion', count: 3)],
        total: 3,
      );
    await _pumpShopRouter(tester, api);
    await _openSearch(tester);
    await tester.enterText(find.byType(TextField), 'linen');
    await tester.pump(const Duration(milliseconds: 250));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Fashion  3'));
    await tester.pumpAndSettle();
    expect(api.lastQuery, 'linen');
    expect(api.lastCategory, 'fashion');
    expect(find.widgetWithText(InputChip, 'Fashion'), findsOneWidget);

    await tester.tap(find.byTooltip('Show every category'));
    await tester.pumpAndSettle();
    expect(api.lastCategory, isNull);
  });

  testWidgets('suggestions wait for two characters', (tester) async {
    final api = _CatalogApi();
    await _pumpShopRouter(tester, api);
    await _openSearch(tester);
    await tester.enterText(find.byType(TextField), 'l');
    await tester.pump(const Duration(milliseconds: 300));
    await tester.pumpAndSettle();

    expect(api.lastSuggestionQuery, isNull);
    expect(find.textContaining('results for'), findsNothing);
  });

  testWidgets('filling a suggested name keeps typing without searching', (
    tester,
  ) async {
    final api = _CatalogApi()
      ..suggestions = const SearchSuggestions(
        names: [(name: 'Paracetamol', count: 2)],
      );
    await _pumpShopRouter(tester, api);
    await _openSearch(tester);
    final initialCatalogRequests = api.firstPageRequests;
    await tester.enterText(find.byType(TextField), 'para');
    await tester.pump(const Duration(milliseconds: 250));
    await tester.pumpAndSettle();

    await tester.tap(find.byTooltip('Use Paracetamol'));
    await tester.pump(const Duration(milliseconds: 250));
    await tester.pumpAndSettle();

    expect(
      tester.widget<TextField>(find.byType(TextField)).controller!.text,
      'Paracetamol',
    );
    expect(api.lastSuggestionQuery, 'Paracetamol');
    expect(api.firstPageRequests, initialCatalogRequests);
  });

  testWidgets(
    'before typing, recent searches and recently viewed show and can be cleared',
    (tester) async {
      final api = _CatalogApi();
      await _pumpShopRouter(
        tester,
        api,
        initialHistory: SearchHistory(
          recentSearches: const ['biogesic', 'vitamin c'],
          recentlyViewed: [
            _product(77, name: 'Cetirizine 10mg', slug: 'cetirizine-10mg'),
          ],
        ),
      );
      await _openSearch(tester);

      expect(find.text('Recent searches'), findsOneWidget);
      expect(find.text('Recently viewed'), findsOneWidget);
      expect(find.text('Cetirizine 10mg'), findsOneWidget);
      expect(find.text('Shop by need'), findsOneWidget);
      expect(find.text('Have a prescription?'), findsOneWidget);

      await tester.tap(find.byTooltip('Remove vitamin c'));
      await tester.pumpAndSettle();
      expect(find.text('vitamin c'), findsNothing);

      await tester.tap(find.text('biogesic'));
      await tester.pumpAndSettle();
      expect(api.lastQuery, 'biogesic');

      await tester.tap(find.byTooltip('Clear search'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Clear history').first);
      await tester.pumpAndSettle();
      expect(find.text('Recent searches'), findsNothing);
      expect(find.text('Recently viewed'), findsNothing);
    },
  );

  testWidgets(
    'results sort by price and can leave out prescription medicines',
    (tester) async {
      final api = _CatalogApi();
      await _pumpShopRouter(tester, api);
      await _openSearch(tester);
      await tester.enterText(find.byType(TextField), 'linen');
      await tester.testTextInput.receiveAction(TextInputAction.search);
      await tester.pumpAndSettle();
      expect(api.lastSort, 'relevance');

      await tester.tap(find.byTooltip('Sort results'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Price: low to high'));
      await tester.pumpAndSettle();
      expect(api.lastSort, 'price_asc');

      await tester.tap(find.text('No prescription'));
      await tester.pumpAndSettle();
      expect(api.lastNoPrescription, isTrue);
      expect(api.lastSort, 'price_asc');
    },
  );

  testWidgets('an empty search offers categories that open the Shop on them', (
    tester,
  ) async {
    final api = _CatalogApi()..emptySearch = true;
    await _pumpShopRouter(tester, api);
    await _openSearch(tester);
    await tester.enterText(find.byType(TextField), 'lamp');
    await tester.testTextInput.receiveAction(TextInputAction.search);
    await tester.pumpAndSettle();

    expect(find.text('Nothing matches that search'), findsOneWidget);
    await tester.tap(find.widgetWithText(ActionChip, 'Fashion'));
    await tester.pumpAndSettle();

    expect(find.byType(SearchScreen), findsNothing);
    expect(api.lastQuery, isEmpty);
    expect(api.lastCategory, 'fashion');
  });

  testWidgets('a search the branch lacks lists what other branches have', (
    tester,
  ) async {
    final api = _CatalogApi()
      ..emptySearch = true
      ..elsewhereProducts = [_product(77, name: 'Losartan 50 mg')];
    await _pumpShopRouter(tester, api);
    await _openSearch(tester);
    await tester.enterText(find.byType(TextField), 'losartan');
    await tester.testTextInput.receiveAction(TextInputAction.search);
    await tester.pumpAndSettle();

    expect(find.text('Not at your branch'), findsOneWidget);
    expect(find.text('Losartan 50 mg'), findsOneWidget);
    expect(find.text('Nothing matches that search'), findsNothing);
  });

  testWidgets(
    'an ingredient search shows each result group under its heading',
    (tester) async {
      final api = _CatalogApi()
        ..searchPage = ProductPage(
          products: [
            _product(1, name: 'Biogesic 500 mg Tablet'),
            _product(
              2,
              name: 'RiteMED 500 mg Tablet',
              searchSection: 'Other brands of Paracetamol',
            ),
            _product(
              3,
              name: 'Neozep Forte Tablet',
              searchSection: 'Also contains Paracetamol',
            ),
          ],
          page: 1,
          lastPage: 1,
          total: 3,
          searchIngredient: 'Paracetamol',
        );
      await _pumpShopRouter(tester, api);
      await _openSearch(tester);
      await tester.enterText(find.byType(TextField), 'acetaminophen');
      await tester.testTextInput.receiveAction(TextInputAction.search);
      await tester.pumpAndSettle();

      expect(find.text('3 medicines'), findsOneWidget);
      expect(
        find.text('Showing results for Paracetamol (acetaminophen)'),
        findsOneWidget,
      );
      expect(find.text('Other brands of Paracetamol'), findsOneWidget);
      await tester.drag(find.byType(CustomScrollView), const Offset(0, -600));
      await tester.pumpAndSettle();
      expect(find.text('Also contains Paracetamol'), findsOneWidget);
    },
  );

  for (final scale in [1.0, 2.0]) {
    testWidgets('the search page fits 320px at ${scale}x text', (tester) async {
      final api = _CatalogApi()
        ..suggestions = SearchSuggestions(
          names: const [(name: 'Paracetamol', count: 2)],
          categories: const [(slug: 'fashion', name: 'Fashion', count: 3)],
          products: [
            (
              product: _product(98, name: 'Paracetamol 500 mg Tablet'),
              atBranch: false,
            ),
          ],
          total: 12,
        );
      await _pumpShopRouter(
        tester,
        api,
        width: 320,
        textScale: scale,
        initialHistory: SearchHistory(
          recentSearches: const ['cough syrup for kids'],
          recentlyViewed: [_product(77, name: 'Cetirizine 10mg')],
        ),
      );
      await _openSearch(tester);
      expect(tester.takeException(), isNull);

      await tester.enterText(find.byType(TextField), 'para');
      await tester.pump(const Duration(milliseconds: 250));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);

      await tester.testTextInput.receiveAction(TextInputAction.search);
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
    });
  }

  for (final width in [320.0, 375.0, 390.0, 768.0, 844.0]) {
    for (final scale in [1.0, 1.3, 2.0]) {
      testWidgets('catalog fits ${width}px at ${scale}x text', (tester) async {
        await _pumpCatalog(
          tester,
          _CatalogApi(),
          width: width,
          height: width == 844 ? 390 : 844,
          textScale: scale,
        );
        expect(tester.takeException(), isNull);
        await tester.drag(find.byType(CustomScrollView), const Offset(0, -600));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
      });
    }
  }
}

Future<void> _pumpCatalog(
  WidgetTester tester,
  _CatalogApi api, {
  double width = 390,
  double height = 844,
  double textScale = 1,
  bool settle = true,
  bool disableAnimations = false,
  StoreLocatorState? locator,
  SearchHistory? initialHistory,
}) async {
  tester.view.physicalSize = Size(width, height);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        isSignedInProvider.overrideWithValue(true),
        apiClientProvider.overrideWithValue(api),
        activeBranchIdProvider.overrideWith((ref) async => 'B-TEST'),
        cartCountProvider.overrideWithValue(0),
        storeBranchesProvider.overrideWith((ref) async => const []),
        if (locator != null)
          nearestStoreProvider.overrideWith(() => _FixedLocator(locator)),
        if (initialHistory != null)
          initialSearchHistoryProvider.overrideWithValue(initialHistory),
      ],
      child: MaterialApp(
        theme: buildAppTheme(),
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(context).copyWith(
            textScaler: TextScaler.linear(textScale),
            disableAnimations: disableAnimations,
          ),
          child: child!,
        ),
        home: const Scaffold(body: CatalogScreen()),
      ),
    ),
  );
  if (settle) {
    await tester.pumpAndSettle();
  } else {
    await tester.pump();
  }
}

/// The Shop at `/` and search at `/search`, as in the app, so search opens from the Shop.
Future<void> _pumpShopRouter(
  WidgetTester tester,
  _CatalogApi api, {
  double width = 390,
  double height = 844,
  double textScale = 1,
  SearchHistory? initialHistory,
}) async {
  tester.view.physicalSize = Size(width, height);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  final router = GoRouter(
    routes: [
      GoRoute(
        path: '/',
        builder: (context, state) => const Scaffold(body: CatalogScreen()),
      ),
      GoRoute(
        path: '/search',
        builder: (context, state) => const SearchScreen(),
      ),
      GoRoute(
        path: '/conversations/:id',
        builder: (context, state) => Text('Chat ${state.pathParameters['id']}'),
      ),
    ],
  );
  addTearDown(router.dispose);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        isSignedInProvider.overrideWithValue(true),
        apiClientProvider.overrideWithValue(api),
        activeBranchIdProvider.overrideWith((ref) async => 'B-TEST'),
        cartCountProvider.overrideWithValue(0),
        storeBranchesProvider.overrideWith((ref) async => const []),
        if (initialHistory != null)
          initialSearchHistoryProvider.overrideWithValue(initialHistory),
      ],
      child: MaterialApp.router(
        theme: buildAppTheme(),
        routerConfig: router,
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(context).copyWith(
            textScaler: TextScaler.linear(textScale),
            disableAnimations: true,
          ),
          child: child!,
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

Future<void> _openSearch(WidgetTester tester) async {
  await tester.tap(find.bySemanticsLabel('Search medicines'));
  await tester.pumpAndSettle();
  expect(find.byType(SearchScreen), findsOneWidget);
}

Product _product(
  int index, {
  String? name,
  String? slug,
  String? compareAt,
  String? searchSection,
}) => Product(
  id: '$index',
  searchSection: searchSection,
  name: name ?? 'Everyday linen shirt with a long product name',
  slug: slug ?? 'shirt-$index',
  price: '55000.00',
  effectivePrice: '55000.00',
  compareAtPrice: compareAt,
  inStock: true,
  hasVariants: false,
  requiresVariantSelection: false,
  freeShipping: false,
  stockQuantity: 3,
  soldQuantity: 0,
  ratingAverage: 4.8,
  ratingCount: 12,
  store: const StoreInfo(id: 'store', name: 'Independent store', slug: 'store'),
);

class _CatalogApi extends ApiClient {
  _CatalogApi()
    : super(baseUrl: 'http://localhost', tokenProvider: () async => null);

  String? lastQuery = '';
  String? lastCategory;
  String? lastSort;
  String? lastSuggestionQuery;
  String? lastSuggestionBranch;
  bool lastNoPrescription = false;
  SearchSuggestions? suggestions;
  bool failCategories = false;
  List<Category>? categoryItems;
  List<Product> boughtBeforeProducts = const [];
  List<Product> elsewhereProducts = const [];
  ProductPage? searchPage;

  @override
  Future<List<Product>> boughtBefore(String branch) async =>
      boughtBeforeProducts;

  List<Product> bestSellerProducts = const [];

  @override
  Future<List<Product>> bestSellers(String branch) async => bestSellerProducts;

  String? chatBranch;

  @override
  Future<Conversation> startConversation({
    String? productId,
    String? sellerOrderId,
    String? branch,
  }) async {
    chatBranch = branch;
    return Conversation.fromJson({'id': 'C-1'});
  }

  bool failFirstPage = false;
  bool empty = false;
  bool emptySearch = false;
  bool paginated = false;
  bool failNextPage = false;
  int nextPageRequests = 0;
  int firstPageRequests = 0;
  Completer<ProductPage>? pendingPage;
  Completer<ProductPage>? pendingFirstPage;
  List<Product> deals = const [];
  int dealsFeedRequests = 0;

  @override
  Future<List<Category>> categories() async {
    if (failCategories) {
      throw const ApiException(
        code: 'network.error',
        message: 'No connection.',
      );
    }
    return categoryItems ??
        const [
          Category(
            id: 'home',
            name: 'Home & living',
            slug: 'home-living',
            productCount: 20,
          ),
          Category(
            id: 'fashion',
            name: 'Fashion',
            slug: 'fashion',
            productCount: 5,
          ),
        ];
  }

  @override
  Future<ProductPage> products({
    String? branch,
    String? query,
    String? category,
    String? seller,
    String? sort,
    bool onSale = false,
    bool noPrescription = false,
    int page = 1,
    int perPage = 20,
  }) async {
    if (onSale) {
      if (perPage == catalogPageSize) dealsFeedRequests++;
      return ProductPage(
        products: deals,
        page: 1,
        lastPage: 1,
        total: deals.length,
      );
    }
    if (branch == null && (query?.isNotEmpty ?? false)) {
      return ProductPage(
        products: elsewhereProducts,
        page: 1,
        lastPage: 1,
        total: elsewhereProducts.length,
      );
    }
    lastQuery = query;
    lastCategory = category;
    lastSort = sort;
    lastNoPrescription = noPrescription;
    if (searchPage != null && (query?.isNotEmpty ?? false)) {
      return searchPage!;
    }
    if (page == 1) firstPageRequests++;
    if (page == 1 && failFirstPage) {
      throw const ApiException(
        code: 'network.error',
        message: 'No connection.',
      );
    }
    if (page == 1 && pendingFirstPage != null) {
      return pendingFirstPage!.future;
    }
    if (page > 1) {
      nextPageRequests++;
      if (pendingPage != null) return pendingPage!.future;
      if (failNextPage) {
        throw const ApiException(
          code: 'network.error',
          message: 'No connection.',
        );
      }
    }
    final noMatches = empty || (emptySearch && (query?.isNotEmpty ?? false));

    return ProductPage(
      products: noMatches
          ? const []
          : [
              if (page == 1 && (query?.isEmpty ?? true)) ...deals,
              ...List.generate(
                12,
                (index) => _product((page - 1) * 12 + index),
              ),
            ],
      page: page,
      lastPage: paginated ? 2 : 1,
      total: noMatches
          ? 0
          : paginated
          ? 24
          : 12,
    );
  }

  @override
  Future<SearchSuggestions> searchSuggestions(
    String query, {
    String? branch,
  }) async {
    lastSuggestionQuery = query;
    lastSuggestionBranch = branch;

    return suggestions ??
        SearchSuggestions(
          products: [
            (
              product: _product(
                99,
                name: 'Linen weekend shirt',
                slug: 'linen-weekend-shirt',
              ),
              atBranch: true,
            ),
          ],
        );
  }
}

/// Shown only on the Shop's home, not on a category, search or deals list.
const _homeMarker = 'Send prescription';

class _FixedLocator extends NearestStoreNotifier {
  _FixedLocator(this.fixed);

  final StoreLocatorState fixed;

  @override
  Future<StoreLocatorState> build() async => fixed;
}
