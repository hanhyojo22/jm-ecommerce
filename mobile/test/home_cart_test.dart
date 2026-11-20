import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/core/api_exception.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/providers/cart_providers.dart';
import 'package:jmmart/src/theme.dart';
import 'package:jmmart/src/widgets/common.dart';
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
    'home add disables while pending and increases quantity without opening product',
    (tester) async {
      final api = _CartApi()..pending = Completer<Cart>();
      var productTaps = 0;
      await _pumpCard(tester, api, onTap: () => productTaps++);
      expect(find.text('Cart quantity: 2'), findsOneWidget);
      await tester.tap(find.byTooltip('Add to cart'));
      await tester.pump();
      expect(api.adds, 1);
      expect(productTaps, 0);
      expect(tester.widget<IconButton>(_addButton).onPressed, isNull);
      api.pending!.complete(_cart(3));
      await tester.pumpAndSettle();
      expect(find.text('Cart quantity: 3'), findsOneWidget);
      expect(find.text('Test shirt added to cart.'), findsOneWidget);
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

  testWidgets('a product already in the cart shows a quantity stepper', (
    tester,
  ) async {
    final api = _CartApi()..cartProduct = 'shirt';
    await _pumpCard(tester, api);
    expect(find.byTooltip('Add to cart'), findsNothing);
    expect(find.text('2'), findsOneWidget);

    await tester.tap(find.byTooltip('Add one'));
    await _settleQuantity(tester);
    expect(api.updatedTo, 3);
    expect(find.text('3'), findsOneWidget);

    await tester.tap(find.byTooltip('Remove one'));
    await _settleQuantity(tester);
    expect(api.updatedTo, 2);
    expect(api.adds, 0);
  });

  testWidgets('the stepper shows the new quantity before the server answers', (
    tester,
  ) async {
    final api = _CartApi()..cartProduct = 'shirt';
    await _pumpCard(tester, api);

    await tester.tap(find.byTooltip('Add one'));
    await tester.pump();
    expect(find.text('3'), findsOneWidget);
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
      await tester.tap(find.byTooltip('Add one'));
      await tester.pump(const Duration(milliseconds: 100));
    }
    expect(find.text('5'), findsOneWidget);

    await _settleQuantity(tester);
    expect(api.updates, 1);
    expect(api.updatedTo, 5);
    expect(find.text('5'), findsOneWidget);
  });

  testWidgets('a refused change puts the real quantity back and says why', (
    tester,
  ) async {
    final api = _CartApi()
      ..cartProduct = 'shirt'
      ..failUpdate = true;
    await _pumpCard(tester, api);

    await tester.tap(find.byTooltip('Add one'));
    await tester.pump();
    expect(find.text('3'), findsOneWidget);

    await _settleQuantity(tester);
    expect(find.text('2'), findsOneWidget);
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

  testWidgets('a double tap on minus at two stops at one', (tester) async {
    final api = _CartApi()..cartProduct = 'shirt';
    await _pumpCard(tester, api);

    await tester.tap(find.byTooltip('Remove one'));
    await tester.pump();
    // The same spot is now the trash; a second tap in the same burst is ignored.
    await tester.tap(find.byTooltip('Remove from cart'));
    await tester.pump();
    expect(find.text('1'), findsOneWidget);

    await _settleQuantity(tester);
    expect(api.removes, 0);
    expect(api.updatedTo, 1);
    expect(find.text('1'), findsOneWidget);
  });

  testWidgets('one card\'s server reply leaves another card\'s unsent tap', (
    tester,
  ) async {
    final api = _CartApi()..twoLines = true;
    await _pumpTwoCards(tester, api);
    expect(find.text('2'), findsNWidgets(2));

    await tester.tap(find.byTooltip('Add one').first);
    await tester.pump(const Duration(milliseconds: 200));
    await tester.tap(find.byTooltip('Add one').last);
    // The shirt's request goes out and its reply (soap still 2) lands first.
    await tester.pump(const Duration(milliseconds: 200));
    await tester.pump();
    expect(api.updates, 1);
    expect(find.text('3'), findsNWidgets(2));

    await _settleQuantity(tester);
    expect(api.updates, 2);
    expect(find.text('3'), findsNWidgets(2));
  });

  testWidgets('the trash at one removes the line', (tester) async {
    final api = _CartApi()
      ..cartProduct = 'shirt'
      ..startQuantity = 1;
    await _pumpCard(tester, api);

    await tester.tap(find.byTooltip('Remove from cart'));
    await tester.pump();
    expect(find.byTooltip('Add to cart'), findsOneWidget);

    await _settleQuantity(tester);
    expect(api.removes, 1);
    expect(find.text('Cart quantity: 0'), findsOneWidget);
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

    await tester.tap(find.byTooltip('Add one'));
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

/// Waits out the stepper's pause after the last tap, then lets the request finish.
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
}) async {
  final card = Scaffold(
    body: Column(
      children: [
        Consumer(
          builder: (context, ref, child) =>
              Text('Cart quantity: ${ref.watch(cartCountProvider)}'),
        ),
        SizedBox(
          width: 190,
          height: 450,
          child: ProductCard(
            discovery: true,
            showAddToCart: true,
            onTap: onTap ?? () {},
            product: Product.fromJson({
              'id': 'shirt',
              'name': 'Test shirt',
              'slug': 'shirt',
              'price': '250.00',
              'in_stock': inStock,
              'has_variants': variants,
            }),
          ),
        ),
      ],
    ),
  );
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        isSignedInProvider.overrideWithValue(signedIn),
        apiClientProvider.overrideWithValue(api),
        activeBranchIdProvider.overrideWith((ref) async => 'B-TEST'),
      ],
      child: MaterialApp.router(
        theme: buildAppTheme(),
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
