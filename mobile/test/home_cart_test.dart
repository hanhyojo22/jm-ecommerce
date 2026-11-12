import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/core/api_exception.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/providers/cart_providers.dart';
import 'package:jmmart/src/theme.dart';
import 'package:jmmart/src/widgets/common.dart';

void main() {
  test('badge counts total quantity and add uses the returned cart', () async {
    final api = _CartApi();
    final container = ProviderContainer(
      overrides: [apiClientProvider.overrideWithValue(api)],
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
  VoidCallback? onTap,
}) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(api)],
      child: MaterialApp(
        theme: buildAppTheme(),
        home: Scaffold(
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
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

Cart _cart(int quantity) => Cart.fromJson({
  'items': [
    {
      'id': 'line',
      'product_id': 'shirt',
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

  @override
  Future<Cart> cart() async {
    cartReads++;
    return _cart(2);
  }

  @override
  Future<Cart> addToCart({
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
