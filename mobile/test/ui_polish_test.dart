import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/screens/cart_screen.dart';
import 'package:jmmart/src/screens/order_detail_screen.dart';
import 'package:jmmart/src/screens/orders_screen.dart';
import 'package:jmmart/src/screens/store_screen.dart';
import 'package:jmmart/src/theme.dart';
import 'package:jmmart/src/providers/store_locator_providers.dart';

void main() {
  test('light and dark themes expose distinct readable surfaces', () {
    final light = buildAppTheme();
    final dark = buildAppTheme(brightness: Brightness.dark);

    expect(light.colorScheme.brightness, Brightness.light);
    expect(dark.colorScheme.brightness, Brightness.dark);
    expect(
      dark.scaffoldBackgroundColor.computeLuminance(),
      lessThan(light.scaffoldBackgroundColor.computeLuminance()),
    );
    expect(
      dark.colorScheme.onSurface.computeLuminance(),
      greaterThan(dark.colorScheme.surface.computeLuminance()),
    );
    expect(dark.cardTheme.color, isNot(light.cardTheme.color));
    expect(light.badgeTheme.backgroundColor, light.colorScheme.primary);
    expect(
      light.navigationBarTheme.iconTheme?.resolve({
        WidgetState.selected,
      })?.color,
      light.colorScheme.primary,
    );
    expect(light.navigationBarTheme.indicatorColor, isNot(Colors.transparent));
  });

  testWidgets('order detail header fits a phone with large text', (
    tester,
  ) async {
    _phone(tester, scale: 2);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          isSignedInProvider.overrideWithValue(true),
          apiClientProvider.overrideWithValue(_OrderApi()),
          activeBranchIdProvider.overrideWith((ref) async => 'B-TEST'),
        ],
        child: _app(const OrderDetailScreen(orderId: 'order-1')),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Pay now'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'order detail renders stepper, delivery card, order help, chat, order number copy, and summary toggle',
    (tester) async {
      _phone(tester, scale: 1);

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            isSignedInProvider.overrideWithValue(true),
            apiClientProvider.overrideWithValue(_DeliveredOrderApi()),
            activeBranchIdProvider.overrideWith((ref) async => 'B-TEST'),
          ],
          child: _app(const OrderDetailScreen(orderId: 'order-1')),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Placed'), findsOneWidget);
      expect(find.text('Preparing'), findsOneWidget);
      expect(find.text('Out for delivery'), findsOneWidget);
      expect(find.text('Delivered'), findsWidgets);
      expect(find.text('Delivery details'), findsOneWidget);

      await tester.scrollUntilVisible(find.text('Delivery Address'), 200);
      expect(find.text('Delivery Address'), findsOneWidget);
      expect(find.text('Wrong or missing item?'), findsOneWidget);

      await tester.scrollUntilVisible(find.text('Order help'), 200);
      expect(find.text('Order help'), findsOneWidget);
      await tester.scrollUntilVisible(find.text('Chat with pharmacy'), 200);
      expect(find.text('Chat with pharmacy'), findsOneWidget);

      await tester.scrollUntilVisible(find.text('View Order Summary'), 250);
      await tester.ensureVisible(find.text('View Order Summary'));
      await tester.pumpAndSettle();
      expect(find.text('Order No.'), findsOneWidget);
      expect(find.text('Copy'), findsOneWidget);

      await tester.tap(find.text('View Order Summary'));
      await tester.pumpAndSettle();

      expect(find.text('Hide Order Summary'), findsOneWidget);
      expect(find.text('Delivery fee'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('a dense cart line fits a phone with large text', (tester) async {
    _phone(tester, scale: 2);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          isSignedInProvider.overrideWithValue(true),
          apiClientProvider.overrideWithValue(_CartApi()),
          activeBranchIdProvider.overrideWith((ref) async => 'B-TEST'),
        ],
        child: _app(const CartScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Checkout'), findsOneWidget);
    expect(find.text('Everyday linen shirt'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('the cart buys every line the branch can sell', (tester) async {
    final api = _SelectAllApi();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          isSignedInProvider.overrideWithValue(true),
          apiClientProvider.overrideWithValue(api),
          activeBranchIdProvider.overrideWith((ref) async => 'B-TEST'),
        ],
        child: _app(const CartScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(api.selected, ['in-stock']);
    expect(find.byType(Checkbox), findsNothing);
  });

  testWidgets('order tabs and image strip fit a phone with large text', (
    tester,
  ) async {
    _phone(tester, scale: 2);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          isSignedInProvider.overrideWithValue(true),
          apiClientProvider.overrideWithValue(_OrdersApi()),
          activeBranchIdProvider.overrideWith((ref) async => 'B-TEST'),
        ],
        child: _app(const Scaffold(body: OrdersScreen())),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Out for delivery'), findsOneWidget);
    expect(find.text('Tap to confirm receipt'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'orders screen renders Lazada My Orders header, search, and multi-item rows',
    (tester) async {
      _phone(tester, scale: 1);
      final api = _OrdersApi();

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            isSignedInProvider.overrideWithValue(true),
            apiClientProvider.overrideWithValue(api),
            activeBranchIdProvider.overrideWith((ref) async => 'B-TEST'),
          ],
          child: _app(const Scaffold(body: OrdersScreen())),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('My Orders'), findsOneWidget);
      expect(find.text('Filter'), findsOneWidget);
      expect(find.text('Preparing'), findsOneWidget);
      expect(find.text('To review'), findsOneWidget);
      // Up to 3 full Lazada item rows are shown with Qty and +1 more
      expect(find.text('Everyday linen shirt'), findsOneWidget);
      expect(find.text('Handmade ceramic mug'), findsOneWidget);
      expect(find.text('Cotton tote bag'), findsOneWidget);
      expect(find.text('+1 more'), findsOneWidget);
      expect(find.textContaining('Total(4 Items):'), findsOneWidget);

      await tester.enterText(find.byType(TextField), 'Independent');
      await tester.testTextInput.receiveAction(TextInputAction.search);
      await tester.pumpAndSettle();

      expect(api.lastSearch, 'Independent');
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('store search filters the product grid', (tester) async {
    _phone(tester, scale: 1);
    await _pumpStore(tester);

    await tester.enterText(find.byType(TextField), 'mug');
    await tester.pumpAndSettle();

    expect(find.text('1 result'), findsOneWidget);
    expect(find.text('Handmade ceramic mug'), findsOneWidget);
    expect(
      find.text('Everyday linen shirt with a long product name'),
      findsNothing,
    );

    await tester.tap(find.text('Clear filters'));
    await tester.pumpAndSettle();

    expect(find.text('2 results'), findsNothing);
    expect(
      find.text('Everyday linen shirt with a long product name'),
      findsOneWidget,
    );
    expect(find.text('Handmade ceramic mug'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('store category tabs filter the product grid', (tester) async {
    _phone(tester, scale: 1);
    await _pumpStore(tester);

    await tester.tap(find.text('Home & Living'));
    await tester.pumpAndSettle();

    expect(find.text('1 result'), findsOneWidget);
    expect(find.text('Handmade ceramic mug'), findsOneWidget);
    expect(
      find.text('Everyday linen shirt with a long product name'),
      findsNothing,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('the store grid fits one column on a phone with large text', (
    tester,
  ) async {
    _phone(tester, scale: 2);
    await _pumpStore(tester);

    final verticalScroll = find.byWidgetPredicate(
      (widget) =>
          widget is Scrollable && widget.axisDirection == AxisDirection.down,
    );
    await tester.scrollUntilVisible(
      find.byType(StoreProductCard).first,
      240,
      scrollable: verticalScroll,
    );
    await tester.pumpAndSettle();

    final cardWidth = tester.getSize(find.byType(StoreProductCard).first).width;
    expect(cardWidth, greaterThan(250));
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pumpStore(WidgetTester tester) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        isSignedInProvider.overrideWithValue(true),
        apiClientProvider.overrideWithValue(_StoreApi()),
        activeBranchIdProvider.overrideWith((ref) async => 'B-TEST'),
      ],
      child: _app(const StoreScreen(slug: 'store')),
    ),
  );
  await tester.pumpAndSettle();
}

void _phone(WidgetTester tester, {required double scale}) {
  tester.view.physicalSize = const Size(320, 640);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  _scale = scale;
}

double _scale = 1;

Widget _app(Widget home) => MaterialApp(
  theme: buildAppTheme(),
  builder: (context, child) => MediaQuery(
    data: MediaQuery.of(context)
        .copyWith(textScaler: TextScaler.linear(_scale)),
    child: child!,
  ),
  home: home,
);

OrderDetail _order({String status = 'pending'}) => OrderDetail(
  id: 'order-1',
  number: 'JM-1001',
  shortNumber: '#1001',
  status: 'pending',
  createdAt: '2026-11-08T10:00:00Z',
  subtotal: '1998.00',
  shippingAmount: '0.00',
  total: '1998.00',
  delivery: const DeliveryInfo(
    recipientName: 'Ana Dela Cruz',
    phone: '09171234567',
    addressLine: '12 Mabini Street, Barangay San Lorenzo',
    city: 'Makati City',
    province: 'Metro Manila',
    postalCode: '1223',
  ),
  sellerOrders: [
    SellerOrder(
      id: 'seller-order-1',
      status: status,
      subtotal: '1998.00',
      shippingAmount: '0.00',
      total: '1998.00',
      canConfirmReceipt: false,
      store: const StoreInfo(
        id: 'store',
        name: 'The Independent Store',
        slug: 'store',
      ),
      orderId: 'order-1',
      orderNumber: 'JM-1001',
      orderShortNumber: '#1001',
      orderTotal: '1998.00',
      orderCreatedAt: '2026-11-08T10:00:00Z',
      lines: const [
        OrderLine(
          id: 'line-1',
          productName: 'Everyday linen shirt with a long product name',
          variantLabel: 'Large / Sage green',
          unitPrice: '999.00',
          quantity: 2,
          lineTotal: '1998.00',
        ),
      ],
    ),
  ],
  canPay: true,
  canConfirmReceipt: false,
);

class _OrderApi extends ApiClient {
  _OrderApi()
    : super(baseUrl: 'http://localhost', tokenProvider: () async => null);

  @override
  Future<OrderDetail> order(String id) async => _order();
}

class _DeliveredOrderApi extends ApiClient {
  _DeliveredOrderApi()
    : super(baseUrl: 'http://localhost', tokenProvider: () async => null);

  @override
  Future<OrderDetail> order(String id) async => _order(status: 'delivered');
}

class _CartApi extends ApiClient {
  _CartApi()
    : super(baseUrl: 'http://localhost', tokenProvider: () async => null);

  @override
  Future<Cart> cart() async => const Cart(
    lines: [
      CartLine(
        id: 'line-1',
        productId: 'product-1',
        quantity: 99,
        selected: true,
        available: true,
        unitPrice: '999.00',
        lineTotal: '98901.00',
        productName: 'Everyday linen shirt',
        productSlug: 'shirt',
        storeName: 'The Independent Store',
        storeSlug: 'store',
        hasVariants: true,
        variantLabel: 'Large / Sage green',
      ),
      CartLine(
        id: 'line-2',
        productId: 'product-2',
        quantity: 1,
        selected: true,
        available: true,
        unitPrice: '1299.00',
        lineTotal: '1299.00',
        productName: 'Handmade ceramic mug with a long name',
        productSlug: 'mug',
        storeName: 'The Independent Store',
        storeSlug: 'store',
        hasVariants: false,
      ),
    ],
    selectedCount: 2,
    subtotal: '100200.00',
  );
}

class _OrdersApi extends ApiClient {
  _OrdersApi()
    : super(baseUrl: 'http://localhost', tokenProvider: () async => null);

  String? lastSearch;

  @override
  Future<OrdersResult> orders({String tab = 'all', String? search}) async {
    lastSearch = search;
    return const OrdersResult(
      orders: [
        SellerOrder(
          id: 'seller-order-1',
          status: 'shipped',
          subtotal: '1998.00',
          shippingAmount: '0.00',
          total: '1998.00',
          canConfirmReceipt: true,
          store: StoreInfo(
            id: 'store',
            name: 'The Independent Store',
            slug: 'store',
          ),
          orderId: 'order-1',
          orderShortNumber: '#1001',
          orderCreatedAt: '2026-11-08T10:00:00Z',
          lines: [
            OrderLine(
              id: 'line-1',
              productName: 'Everyday linen shirt',
              unitPrice: '999.00',
              quantity: 1,
              lineTotal: '999.00',
            ),
            OrderLine(
              id: 'line-2',
              productName: 'Handmade ceramic mug',
              unitPrice: '999.00',
              quantity: 1,
              lineTotal: '999.00',
            ),
            OrderLine(
              id: 'line-3',
              productName: 'Cotton tote bag',
              unitPrice: '999.00',
              quantity: 1,
              lineTotal: '999.00',
            ),
            OrderLine(
              id: 'line-4',
              productName: 'Linen apron',
              unitPrice: '999.00',
              quantity: 1,
              lineTotal: '999.00',
            ),
          ],
        ),
      ],
      tabCounts: {'all': 1, 'to_receive': 1},
    );
  }
}

class _StoreApi extends ApiClient {
  _StoreApi()
    : super(baseUrl: 'http://localhost', tokenProvider: () async => null);

  @override
  Future<StoreInfo> store(String slug) async => const StoreInfo(
    id: 'store',
    name: 'The Independent Store',
    slug: 'store',
    city: 'Makati City',
    description: 'Small-batch home goods made by independent makers.',
    productsCount: 2,
  );

  @override
  Future<ProductPage> products({
    String? branch,
    String? query,
    String? category,
    String? seller,
    String? sort,
    bool onSale = false,
    int page = 1,
    int perPage = 20,
  }) async => ProductPage(
    products: const [
      Product(
        id: 'product-1',
        name: 'Everyday linen shirt with a long product name',
        slug: 'shirt',
        price: '999.00',
        effectivePrice: '999.00',
        inStock: true,
        hasVariants: false,
        requiresVariantSelection: false,
        freeShipping: true,
        stockQuantity: 4,
        soldQuantity: 2,
        ratingAverage: 4.8,
        ratingCount: 12,
        category: Category(
          id: 'fashion',
          name: 'Fashion',
          slug: 'fashion',
          productCount: 1,
        ),
        store: StoreInfo(
          id: 'store',
          name: 'The Independent Store',
          slug: 'store',
        ),
      ),
      Product(
        id: 'product-2',
        name: 'Handmade ceramic mug',
        slug: 'mug',
        price: '599.00',
        effectivePrice: '599.00',
        inStock: true,
        hasVariants: false,
        requiresVariantSelection: false,
        freeShipping: false,
        stockQuantity: 8,
        soldQuantity: 1,
        ratingAverage: 4.5,
        ratingCount: 4,
        category: Category(
          id: 'home',
          name: 'Home & Living',
          slug: 'home',
          productCount: 1,
        ),
        store: StoreInfo(
          id: 'store',
          name: 'The Independent Store',
          slug: 'store',
        ),
      ),
    ],
    page: 1,
    lastPage: 1,
    total: 2,
  );
}

class _SelectAllApi extends ApiClient {
  _SelectAllApi()
    : super(baseUrl: 'http://localhost', tokenProvider: () async => null);

  List<String>? selected;

  CartLine _line(String id, {required bool available}) => CartLine(
    id: id,
    productId: id,
    quantity: 1,
    selected: false,
    available: available,
    unitPrice: '10.00',
    lineTotal: '10.00',
    productName: 'Product $id',
    productSlug: id,
    storeName: 'Store',
    storeSlug: 'store',
    hasVariants: false,
  );

  @override
  Future<Cart> cart() async => Cart(
    lines: [
      _line('in-stock', available: true),
      _line('sold-out', available: false),
    ],
    selectedCount: 0,
    subtotal: '0.00',
  );

  @override
  Future<({int selectedCount, String subtotal})> updateCartSelection(
    List<String> itemIds,
  ) async {
    selected = itemIds;
    return (selectedCount: itemIds.length, subtotal: '10.00');
  }
}
