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

  testWidgets(
    'home add disables while pending and increases quantity without opening product',
    (tester) async {
      final api = _CartApi()..pending = Completer<Cart>();
      var productTaps = 0;
      await _pumpCard(tester, api, onTap: () => productTaps++);
      expect(find.text('Cart quantity: 2'), findsOneWidget);
      await tester.tap(find.text('Add to cart'));
      await tester.pump();
      expect(api.adds, 1);
      expect(productTaps, 0);
      expect(
        tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
        isNull,
      );
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
    await tester.tap(find.text('Add to cart'));
    await tester.pumpAndSettle();
    expect(find.text('Cart quantity: 2'), findsOneWidget);
    expect(
      tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
      isNotNull,
    );
    api.fail = false;
    await tester.tap(find.text('Add to cart'));
    await tester.pumpAndSettle();
    expect(find.text('Cart quantity: 3'), findsOneWidget);
  });

  testWidgets(
    'variants open product options without adding a default variant',
    (tester) async {
      final api = _CartApi();
      var productTaps = 0;
      await _pumpCard(tester, api, variants: true, onTap: () => productTaps++);
      await tester.tap(find.text('Choose options'));
      expect(productTaps, 1);
      expect(api.adds, 0);
    },
  );

  testWidgets('a product already in the cart shows a quantity stepper', (
    tester,
  ) async {
    final api = _CartApi()..cartProduct = 'shirt';
    await _pumpCard(tester, api);
    expect(find.text('Add to cart'), findsNothing);
    expect(find.text('2'), findsOneWidget);

    await tester.tap(find.byTooltip('Add one'));
    await tester.pumpAndSettle();
    expect(api.updatedTo, 3);
    expect(find.text('3'), findsOneWidget);

    await tester.tap(find.byTooltip('Remove one'));
    await tester.pumpAndSettle();
    expect(api.updatedTo, 2);
    expect(api.adds, 0);
  });

  testWidgets('a guest who taps add is sent to sign in, not to the cart', (
    tester,
  ) async {
    final api = _CartApi();
    await _pumpCard(tester, api, signedIn: false);
    expect(find.text('Cart quantity: 0'), findsOneWidget);
    await tester.tap(find.text('Add to cart'));
    await tester.pumpAndSettle();
    expect(find.text('Sign-in page'), findsOneWidget);
    expect(api.cartReads, 0);
    expect(api.adds, 0);
  });

  testWidgets('sold-out homepage products cannot be added', (tester) async {
    final api = _CartApi();
    await _pumpCard(tester, api, inStock: false);
    expect(
      tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
      isNull,
    );
    expect(api.adds, 0);
  });
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
  bool fail = false;
  Completer<Cart>? pending;

  /// Which product the starting cart holds; 'other' keeps the card on its Add button.
  String cartProduct = 'other';
  int? updatedTo;

  @override
  Future<Cart> cart() async {
    cartReads++;
    return _cart(2, productId: cartProduct);
  }

  @override
  Future<Cart> updateCartItem(String id, int quantity) async {
    updatedTo = quantity;
    return _cart(quantity, productId: cartProduct);
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
