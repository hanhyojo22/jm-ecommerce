import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/core/api_exception.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/providers/cart_providers.dart';
import 'package:jmmart/src/providers/connectivity_providers.dart';
import 'package:jmmart/src/theme.dart';
import 'package:jmmart/src/widgets/common.dart';
import 'package:jmmart/src/widgets/fly_to_cart.dart';
import 'package:jmmart/src/providers/store_locator_providers.dart';

void main() {
  test('badge counts total quantity and add uses the returned cart', () async {
    final api = _CartApi();
    final container = ProviderContainer(
      overrides: [
        isSignedInProvider.overrideWithValue(true),
        apiClientProvider.overrideWithValue(api),
        activeBranchIdProvider.overrideWith((ref) async => 'B-TEST'),
      ],
    );
    addTearDown(container.dispose);
    container.listen(cartCountProvider, (_, _) {});
    await container.read(cartProvider.future);
    expect(container.read(cartCountProvider), 2);
    await container.read(cartProvider.notifier).add(productId: 'shirt');
    expect(container.read(cartCountProvider), 3);
    expect(api.cartReads, 1);
    expect(api.adds, 1);
  });

  test(
    'the cart bar total counts unselected lines but not unavailable ones',
    () {
      final cart = Cart.fromJson({
        'subtotal': '0.00',
        'items': [
          {
            'id': 'a',
            'available': true,
            'selected': false,
            'line_total': '18.00',
          },
          {
            'id': 'b',
            'available': true,
            'selected': true,
            'line_total': '4.50',
          },
          {'id': 'c', 'available': false, 'line_total': '99.00'},
        ],
      });

      expect(cart.availableTotal, '22.50');
    },
  );

  testWidgets(
    'home add counts taps while pending and increases quantity without opening product',
    (tester) async {
      final api = _CartApi()..pending = Completer<Cart>();
      var productTaps = 0;
      await _pumpCard(tester, api, onTap: () => productTaps++);
      expect(
        (tester.getCenter(find.text('₱250.00')).dy -
                tester.getCenter(find.byTooltip('Add to cart')).dy)
            .abs(),
        lessThan(10),
      );
      expect(find.text('Cart quantity: 2'), findsOneWidget);
      await tester.tap(find.byTooltip('Add to cart'));
      await tester.pump();
      expect(api.adds, 1);
      expect(productTaps, 0);
      // A second tap while the first add is on its way still counts.
      await tester.tap(find.byTooltip('Add to cart'));
      await tester.pump();
      expect(api.adds, 1);
      api.pending!.complete(_cart(3));
      await tester.pump();
      expect(find.text('Added to cart'), findsOneWidget);
      await _settleQuantity(tester);
      expect(find.text('Added to cart'), findsNothing);
      expect(api.updatedTo, 4);
      expect(find.text('Cart quantity: 4'), findsOneWidget);
      expect(api.cartReads, 1);
    },
  );

  testWidgets('failed add preserves count and offers another attempt', (
    tester,
  ) async {
    final api = _CartApi()..fail = true;
    await _pumpCard(tester, api);
    await tester.tap(find.byTooltip('Add to cart'));
    await tester.pumpAndSettle();
    expect(find.text('Cart quantity: 2'), findsOneWidget);
    expect(tester.widget<IconButton>(_addButton).onPressed, isNotNull);
    api.fail = false;
    await tester.tap(find.byTooltip('Add to cart'));
    await tester.pumpAndSettle();
    expect(find.text('Cart quantity: 3'), findsOneWidget);
  });

  testWidgets(
    'variants open product options without adding a default variant',
    (tester) async {
      final api = _CartApi();
      var productTaps = 0;
      await _pumpCard(tester, api, variants: true, onTap: () => productTaps++);
      await tester.tap(find.text('Options'));
      expect(productTaps, 1);
      expect(api.adds, 0);
    },
  );

  testWidgets('a product already in the cart adds one more on every tap', (
    tester,
  ) async {
    final api = _CartApi()..cartProduct = 'shirt';
    await _pumpCard(tester, api);
    expect(find.byTooltip('Remove one'), findsNothing);
    expect(_inCart(2), findsOneWidget);
    // No count badge on the card's cart icon.
    expect(find.text('2'), findsNothing);
    expect(find.byType(Badge), findsNothing);

    await tester.tap(find.byTooltip('Add to cart'));
    await _settleQuantity(tester);
    expect(api.updatedTo, 3);
    expect(_inCart(3), findsOneWidget);

    await tester.tap(find.byTooltip('Add to cart'));
    await _settleQuantity(tester);
    expect(api.updatedTo, 4);
    expect(_inCart(4), findsOneWidget);
    expect(api.adds, 0);
  });

  testWidgets('taps stop at the branch stock and say why', (tester) async {
    final api = _CartApi()..cartProduct = 'shirt';
    await _pumpCard(tester, api, stock: 2);

    await tester.tap(find.byTooltip('Add to cart'));
    await tester.pumpAndSettle();
    expect(find.text('Only 2 left at this branch.'), findsOneWidget);
    await _settleQuantity(tester);
    expect(api.updates, 0);
    expect(_inCart(2), findsOneWidget);
  });

  testWidgets('offline, a tap says so and leaves the cart alone', (
    tester,
  ) async {
    final api = _CartApi();
    await _pumpCard(tester, api, online: false);
    // In the app the offline bar keeps the connection state listened to.
    final container = ProviderScope.containerOf(
      tester.element(find.byType(ProductCard)),
    );
    container.listen(isOnlineProvider, (_, _) {});
    await tester.pump();

    await tester.tap(find.byTooltip('Add to cart'));
    await tester.pumpAndSettle();
    expect(
      find.text("No internet connection. Your cart wasn't changed."),
      findsOneWidget,
    );
    await _settleQuantity(tester);
    expect(api.adds, 0);
    expect(api.updates, 0);
  });

  for (final scale in [1.0, 1.3]) {
    testWidgets('narrow cards keep prices and reviews readable at ${scale}x', (
      tester,
    ) async {
      final api = _CartApi()
        ..cartProduct = 'shirt'
        ..startQuantity = 10;
      await _pumpCard(tester, api, width: 139, textScale: scale);

      final price = tester.renderObject<RenderBox>(find.text('₱250.00'));
      final paintedWidth =
          (price.localToGlobal(Offset(price.size.width, 0)) -
                  price.localToGlobal(Offset.zero))
              .distance;
      expect(paintedWidth, greaterThan(50));
      expect(
        tester.getBottomLeft(find.text('₱250.00')).dy,
        lessThan(tester.getTopLeft(find.byTooltip('Add to cart')).dy),
      );
      expect(
        tester
            .renderObject<RenderParagraph>(find.text('No reviews'))
            .didExceedMaxLines,
        isFalse,
      );
      expect(tester.takeException(), isNull);

      await tester.tap(find.byTooltip('Add to cart'));
      await _settleQuantity(tester);
      expect(_inCart(11), findsOneWidget);
      expect(api.updatedTo, 11);
      expect(tester.takeException(), isNull);
    });
  }

  testWidgets('the badge shows the new quantity before the server answers', (
    tester,
  ) async {
    final api = _CartApi()..cartProduct = 'shirt';
    await _pumpCard(tester, api);

    await tester.tap(find.byTooltip('Add to cart'));
    await tester.pump();
    expect(_inCart(3), findsOneWidget);
    expect(find.text('Cart quantity: 3'), findsOneWidget);
    expect(api.updates, 0);

    await _settleQuantity(tester);
    expect(api.updatedTo, 3);
  });

  testWidgets('quick taps send one request with the final quantity', (
    tester,
  ) async {
    final api = _CartApi()..cartProduct = 'shirt';
    await _pumpCard(tester, api);

    for (var tap = 0; tap < 3; tap++) {
      await tester.tap(find.byTooltip('Add to cart'));
      await tester.pump(const Duration(milliseconds: 100));
    }
    expect(_inCart(5), findsOneWidget);

    await _settleQuantity(tester);
    expect(api.updates, 1);
    expect(api.updatedTo, 5);
    expect(_inCart(5), findsOneWidget);
  });

  testWidgets('a refused change puts the real quantity back and says why', (
    tester,
  ) async {
    final api = _CartApi()
      ..cartProduct = 'shirt'
      ..failUpdate = true;
    await _pumpCard(tester, api);

    await tester.tap(find.byTooltip('Add to cart'));
    await tester.pump();
    expect(_inCart(3), findsOneWidget);

    await _settleQuantity(tester);
    expect(_inCart(2), findsOneWidget);
    expect(find.text('Only 2 left at this branch.'), findsOneWidget);
  });

  testWidgets('only the last of several refused taps reports the refusal', (
    tester,
  ) async {
    final api = _CartApi()
      ..cartProduct = 'shirt'
      ..failUpdate = true;
    final container = _container(api);
    container.listen(cartProvider, (_, _) {});
    await container.read(cartProvider.future);

    final outcomes = <String>[];
    for (final quantity in [3, 4, 5]) {
      container
          .read(cartProvider.notifier)
          .setQuantity('line', quantity)
          .then(
            (_) => outcomes.add('ok'),
            onError: (_) => outcomes.add('error'),
          );
    }
    await tester.pump(CartNotifier.quantityDebounce);
    await tester.pump();

    expect(api.updates, 1);
    expect(outcomes, ['ok', 'ok', 'error']);
  });

  testWidgets('one card\'s server reply leaves another card\'s unsent tap', (
    tester,
  ) async {
    final api = _CartApi()..twoLines = true;
    await _pumpTwoCards(tester, api);
    expect(_inCart(2), findsNWidgets(2));

    await tester.tap(find.byTooltip('Add to cart').first);
    await tester.pump(const Duration(milliseconds: 200));
    await tester.tap(find.byTooltip('Add to cart').last);
    // The shirt's request goes out and its reply (soap still 2) lands first.
    await tester.pump(const Duration(milliseconds: 200));
    await tester.pump();
    expect(api.updates, 1);
    expect(_inCart(3), findsNWidgets(2));

    await _settleQuantity(tester);
    expect(api.updates, 2);
    expect(_inCart(3), findsNWidgets(2));
  });

  testWidgets('changing one product in the cart leaves other cards alone', (
    tester,
  ) async {
    final api = _CartApi()..cartProduct = 'shirt';
    await _pumpTwoCards(tester, api);
    final rebuilt = <Element>{};
    debugOnRebuildDirtyWidget = (element, builtOnce) {
      if (element.widget.runtimeType.toString() == '_ProductCartButton') {
        rebuilt.add(element);
      }
    };
    addTearDown(() => debugOnRebuildDirtyWidget = null);

    await tester.tap(find.byTooltip('Add to cart').first);
    await _settleQuantity(tester);

    expect(api.updatedTo, 3);
    // Only the shirt's button redraws; the soap card's button is untouched.
    expect(rebuilt, hasLength(1));
  });

  testWidgets('a guest who taps add is sent to sign in, not to the cart', (
    tester,
  ) async {
    final api = _CartApi();
    await _pumpCard(tester, api, signedIn: false);
    expect(find.text('Cart quantity: 0'), findsOneWidget);
    await tester.tap(find.byTooltip('Add to cart'));
    await tester.pumpAndSettle();
    expect(find.text('Sign-in page'), findsOneWidget);
    expect(api.cartReads, 0);
    expect(api.adds, 0);
  });

  testWidgets('sold-out homepage products cannot be added', (tester) async {
    final api = _CartApi();
    await _pumpCard(tester, api, inStock: false);
    // No cart icon at all; the photo's "Sold out" flag says why.
    expect(_addButton, findsNothing);
    expect(api.adds, 0);
  });

  testWidgets('tapping add to cart pops the button and flies to the cart tab', (
    tester,
  ) async {
    final api = _CartApi();
    final cartKey = GlobalKey<CartBadgeIconState>();
    await _pumpCard(tester, api, cartKey: cartKey);

    await tester.tap(find.byTooltip('Add to cart'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 60));
    final scale = tester.widget<ScaleTransition>(
      find.ancestor(
        of: find.byTooltip('Add to cart'),
        matching: find.byType(ScaleTransition),
      ),
    );
    expect(scale.scale.value, isNot(1.0));
    // In-flight overlay thumbnail is travelling towards the cart icon.
    expect(find.byType(ClipOval), findsOneWidget);
    expect(find.text('Added to cart'), findsNothing);

    await tester.pump(const Duration(milliseconds: 1100));
    expect(find.text('Added to cart'), findsOneWidget);

    await tester.pumpAndSettle();
    expect(find.text('Added to cart'), findsNothing);
    expect(find.byType(ClipOval), findsNothing);
  });
}

ProviderContainer _container(_CartApi api) {
  final container = ProviderContainer(
    overrides: [
      isSignedInProvider.overrideWithValue(true),
      apiClientProvider.overrideWithValue(api),
      activeBranchIdProvider.overrideWith((ref) async => 'B-TEST'),
    ],
  );
  addTearDown(container.dispose);
  return container;
}

/// Waits out the cart's pause after the last tap, then lets the request finish.
Future<void> _settleQuantity(WidgetTester tester) async {
  await tester.pump(CartNotifier.quantityDebounce);
  await tester.pumpAndSettle();
}

Future<void> _pumpTwoCards(WidgetTester tester, _CartApi api) async {
  Widget card(String id) => SizedBox(
    width: 190,
    height: 450,
    child: ProductCard(
      discovery: true,
      showAddToCart: true,
      onTap: () {},
      product: Product.fromJson({
        'id': id,
        'name': 'Product $id',
        'slug': id,
        'price': '250.00',
        'in_stock': true,
      }),
    ),
  );
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        isSignedInProvider.overrideWithValue(true),
        apiClientProvider.overrideWithValue(api),
        activeBranchIdProvider.overrideWith((ref) async => 'B-TEST'),
      ],
      child: MaterialApp(
        theme: buildAppTheme(),
        home: Scaffold(body: Row(children: [card('shirt'), card('soap')])),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

Future<void> _pumpCard(
  WidgetTester tester,
  _CartApi api, {
  bool variants = false,
  bool inStock = true,
  bool signedIn = true,
  VoidCallback? onTap,
  GlobalKey<CartBadgeIconState>? cartKey,
  int? stock,
  double width = 190,
  double textScale = 1,
  bool online = true,
}) async {
  Widget card = Scaffold(
    body: Column(
      children: [
        Consumer(
          builder: (context, ref, child) =>
              Text('Cart quantity: ${ref.watch(cartCountProvider)}'),
        ),
        if (cartKey != null) CartBadgeIcon(key: cartKey),
        SizedBox(
          width: width,
          height: 450,
          child: ProductCard(
            discovery: true,
            showAddToCart: true,
            stackCartControls: width < 150 * textScale,
            onTap: onTap ?? () {},
            product: Product.fromJson({
              'id': 'shirt',
              'name': 'Test shirt',
              'slug': 'shirt',
              'price': '250.00',
              'in_stock': inStock,
              'has_variants': variants,
              'stock_quantity': ?stock,
            }),
          ),
        ),
      ],
    ),
  );
  if (cartKey != null) {
    card = CartFlightTarget(targetKey: cartKey, child: card);
  }
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        isSignedInProvider.overrideWithValue(signedIn),
        apiClientProvider.overrideWithValue(api),
        activeBranchIdProvider.overrideWith((ref) async => 'B-TEST'),
        isOnlineProvider.overrideWith((ref) => Stream.value(online)),
      ],
      child: MaterialApp.router(
        theme: buildAppTheme(),
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(context)
              .copyWith(textScaler: TextScaler.linear(textScale)),
          child: child!,
        ),
        routerConfig: GoRouter(
          routes: [
            GoRoute(path: '/', builder: (context, state) => card),
            GoRoute(
              path: '/sign-in',
              builder: (context, state) => const Text('Sign-in page'),
            ),
          ],
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

Cart _cart(int quantity, {String productId = 'shirt'}) => Cart.fromJson({
  'items': [
    {
      'id': 'line',
      'product_id': productId,
      'quantity': quantity,
      'product': {'name': 'Test shirt'},
    },
  ],
});

class _CartApi extends ApiClient {
  _CartApi()
    : super(baseUrl: 'http://localhost', tokenProvider: () async => null);
  int cartReads = 0;
  int adds = 0;
  int updates = 0;
  int removes = 0;
  int startQuantity = 2;
  bool fail = false;
  bool failUpdate = false;

  /// A cart holding both the shirt ('line') and the soap ('line-soap'), two of each.
  bool twoLines = false;
  final _twoLineQuantities = {'line': 2, 'line-soap': 2};

  Cart _twoLineCart() => Cart.fromJson({
    'items': [
      for (final MapEntry(key: id, value: quantity)
          in _twoLineQuantities.entries)
        {
          'id': id,
          'product_id': id == 'line' ? 'shirt' : 'soap',
          'quantity': quantity,
          'product': {'name': id},
        },
    ],
  });
  Completer<Cart>? pending;

  /// Which product the starting cart holds; 'other' keeps the card on its Add button.
  String cartProduct = 'other';
  int? updatedTo;

  @override
  Future<Cart> cart() async {
    cartReads++;
    if (twoLines) {
      return _twoLineCart();
    }
    return _cart(startQuantity, productId: cartProduct);
  }

  @override
  Future<Cart> updateCartItem(String id, int quantity) async {
    updates++;
    if (failUpdate) {
      throw const ApiException(
        code: 'cart.stock',
        message: 'Only 2 left at this branch.',
      );
    }
    updatedTo = quantity;
    if (twoLines) {
      _twoLineQuantities[id] = quantity;
      return _twoLineCart();
    }
    return _cart(quantity, productId: cartProduct);
  }

  @override
  Future<Cart> removeCartItem(String itemId) async {
    removes++;
    return Cart.empty;
  }

  @override
  Future<Cart> addToCart({
    String? branch,
    required String productId,
    int quantity = 1,
    String? variantId,
    bool buyNow = false,
  }) async {
    expect(productId, 'shirt');
    expect(quantity, 1);
    adds++;
    if (fail) {
      throw const ApiException(
        code: 'network.error',
        message: 'No connection.',
      );
    }
    return pending == null ? _cart(3) : pending!.future;
  }
}

/// The card's plain tote Add to cart button.
final _addButton = find.ancestor(
  of: find.byTooltip('Add to cart'),
  matching: find.byType(IconButton),
);

/// The card announces its cart quantity to screen readers; it draws no number.
Finder _inCart(int quantity) => find.byWidgetPredicate(
  (widget) =>
      widget is Semantics && widget.properties.value == '$quantity in cart',
);
