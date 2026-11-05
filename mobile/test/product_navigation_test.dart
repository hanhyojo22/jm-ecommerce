import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/core/api_exception.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/providers/catalog_providers.dart';
import 'package:jmmart/src/screens/product_screen.dart';
import 'package:jmmart/src/theme.dart';
import 'package:jmmart/src/widgets/common.dart';

void main() {
  for (final height in [640.0, 844.0]) {
    for (final scale in [1.0, 2.0]) {
      testWidgets(
        'photo and price lead the first viewport at ${height}px and ${scale}x text',
        (tester) async {
          final api = _ProductApi()
            ..variants = true
            ..coloredVariants = true;
          await _pump(tester, api, width: 320, height: height, scale: scale);
          expect(find.byType(PageView).hitTestable(), findsOneWidget);
          expect(find.text('1/3').hitTestable(), findsOneWidget);
          expect(find.textContaining('250.00').hitTestable(), findsOneWidget);
          expect(find.text('Buy now').hitTestable(), findsOneWidget);
          final scrollable = find
              .byWidgetPredicate(
                (widget) =>
                    widget is Scrollable &&
                    widget.axisDirection == AxisDirection.down,
              )
              .first;
          expect(tester.state<ScrollableState>(scrollable).position.pixels, 0);
          expect(tester.takeException(), isNull);
        },
      );
    }
  }

  testWidgets('changing a variant updates its photo price stock and SKU', (
    tester,
  ) async {
    final api = _ProductApi()
      ..variants = true
      ..coloredVariants = true;
    await _pump(tester, api);
    await tester.drag(find.byType(PageView), const Offset(-500, 0));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text('Large'),
      400,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('Large'));
    await tester.pumpAndSettle();
    expect(
      tester
          .widget<ChoiceChip>(find.widgetWithText(ChoiceChip, 'Large'))
          .selected,
      isTrue,
    );
    expect(find.text('2 in stock'), findsOneWidget);
    expect(find.text('₱325.00'), findsOneWidget);
    expect(find.text('SKU SHIRT-YELLOW'), findsOneWidget);
    await tester.drag(find.byType(ListView).first, const Offset(0, 1800));
    await tester.pumpAndSettle();
    final photo = tester
        .widgetList<NetworkThumb>(
          find.descendant(
            of: find.byType(PageView),
            matching: find.byType(NetworkThumb),
          ),
        )
        .first;
    expect(photo.url, 'https://example.test/yellow.jpg');
    await tester.drag(find.byType(PageView), const Offset(-500, 0));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text('Large'),
      400,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('Large'));
    await tester.pumpAndSettle();
    await tester.drag(find.byType(ListView).first, const Offset(0, 1800));
    await tester.pumpAndSettle();
    final gallery = find
        .descendant(
          of: find.byType(PageView),
          matching: find.byType(Scrollable),
        )
        .first;
    expect(tester.state<ScrollableState>(gallery).position.pixels, 0);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'switching to a lower-stock variant clamps the quantity sent to cart',
    (tester) async {
      final api = _ProductApi()
        ..variants = true
        ..coloredVariants = true;
      await _pump(tester, api);
      await tester.scrollUntilVisible(
        find.text('Quantity'),
        400,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.pumpAndSettle();
      await tester.tap(find.byIcon(Icons.add));
      await tester.pump();
      await tester.tap(find.byIcon(Icons.add));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Large'));
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('Add to cart'));
      await tester.pumpAndSettle();
      expect(api.addedQuantity, 2);
      expect(api.variantId, 'large');
    },
  );

  testWidgets('an inherited variant price uses the product base price', (
    tester,
  ) async {
    final api = _ProductApi()
      ..variants = true
      ..effectivePrice = '200.00';
    await _pump(tester, api);
    await tester.scrollUntilVisible(
      find.text('Large'),
      400,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('Large'));
    await tester.pumpAndSettle();
    expect(find.text('₱250.00'), findsOneWidget);
    expect(find.text('₱200.00'), findsNothing);
  });

  testWidgets('a sold-out variant cannot be selected', (tester) async {
    final api = _ProductApi()
      ..variants = true
      ..largeInStock = false;
    await _pump(tester, api);
    await tester.scrollUntilVisible(
      find.text('Large'),
      400,
      scrollable: find.byType(Scrollable).first,
    );
    final chip = tester.widget<ChoiceChip>(
      find.widgetWithText(ChoiceChip, 'Large'),
    );
    expect(chip.onSelected, isNull);
    expect(chip.selected, isFalse);
  });

  testWidgets('buy now selects only this product and opens checkout', (
    tester,
  ) async {
    final api = _ProductApi();
    await _pump(tester, api);
    await tester.tap(find.text('Buy now'));
    await tester.pumpAndSettle();
    expect(api.adds, 1);
    expect(api.selected, ['shirt-line']);
    expect(find.text('Checkout route'), findsOneWidget);
  });

  testWidgets('add to cart blocks duplicate taps and updates the cart badge', (
    tester,
  ) async {
    final api = _ProductApi()..pending = Completer<Cart>();
    await _pump(tester, api);
    await tester.tap(find.byTooltip('Add to cart'));
    await tester.pump();
    expect(api.adds, 1);
    expect(
      tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
      isNull,
    );
    api.pending!.complete(api.addedCart);
    await tester.pumpAndSettle();
    expect(find.text('3'), findsOneWidget);
    expect(find.text('Added to your cart.'), findsOneWidget);
    expect(find.text('Checkout route'), findsNothing);
  });

  testWidgets(
    'checkout failure retains the added item and offers cart recovery',
    (tester) async {
      final api = _ProductApi()..failSelection = true;
      await _pump(tester, api);
      await tester.tap(find.text('Buy now'));
      await tester.pumpAndSettle();
      expect(api.adds, 1);
      expect(
        find.textContaining('Added to cart, but checkout could not open.'),
        findsOneWidget,
      );
      expect(find.text('Checkout route'), findsNothing);
      expect(
        tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
        isNotNull,
      );
    },
  );

  testWidgets('required options must be selected before buying', (
    tester,
  ) async {
    final api = _ProductApi()..variants = true;
    await _pump(tester, api);
    await tester.tap(find.text('Buy now'));
    await tester.pumpAndSettle();
    expect(api.adds, 0);
    expect(find.text('Small').hitTestable(), findsOneWidget);
    await tester.ensureVisible(find.text('Small'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Small'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Buy now'));
    await tester.pumpAndSettle();
    expect(api.variantId, 'small');
    expect(find.text('Checkout route'), findsOneWidget);
  });

  testWidgets('sold out products cannot be added or bought', (tester) async {
    await _pump(tester, _ProductApi()..inStock = false);
    expect(
      tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
      isNull,
    );
    expect(
      tester
          .widget<IconButton>(
            find.byWidgetPredicate(
              (widget) =>
                  widget is IconButton && widget.tooltip == 'Add to cart',
            ),
          )
          .onPressed,
      isNull,
    );
  });

  for (final action in ['Store']) {
    testWidgets('$action shortcut opens its destination', (tester) async {
      await _pump(tester, _ProductApi());
      await tester.tap(find.text(action));
      await tester.pumpAndSettle();
      expect(find.text('$action route'), findsOneWidget);
    });
  }

  testWidgets('header cart shortcut opens the cart', (tester) async {
    await _pump(tester, _ProductApi());
    await tester.tap(find.byTooltip('Open cart'));
    await tester.pumpAndSettle();
    expect(find.text('Cart route'), findsOneWidget);
  });

  for (final width in [320.0, 390.0]) {
    for (final scale in [1.0, 2.0]) {
      testWidgets(
        'purchase bar stays visible at ${width}px and ${scale}x text',
        (tester) async {
          await _pump(tester, _ProductApi(), width: width, scale: scale);
          expect(tester.takeException(), isNull);
          expect(find.text('Buy now').hitTestable(), findsOneWidget);
          await tester.drag(find.byType(ListView).first, const Offset(0, -650));
          await tester.pumpAndSettle();
          expect(find.text('Buy now').hitTestable(), findsOneWidget);
          expect(find.byTooltip('Add to cart').hitTestable(), findsOneWidget);
          expect(tester.takeException(), isNull);
        },
      );
    }
  }
}

Future<void> _pump(
  WidgetTester tester,
  _ProductApi api, {
  double width = 390,
  double height = 844,
  double scale = 1,
}) async {
  tester.view.physicalSize = Size(width, height);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  final router = GoRouter(
    routes: [
      GoRoute(
        path: '/',
        builder: (_, _) => const ProductScreen(
          productKey: ProductKey(store: 'store', slug: 'shirt'),
        ),
      ),
      for (final name in ['Cart', 'Store', 'Checkout'])
        GoRoute(
          path: name == 'Store' ? '/store/store' : '/${name.toLowerCase()}',
          builder: (_, _) => Scaffold(body: Text('$name route')),
        ),
    ],
  );
  addTearDown(router.dispose);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(api)],
      child: MaterialApp.router(
        theme: buildAppTheme(),
        routerConfig: router,
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(context).copyWith(
            textScaler: TextScaler.linear(scale),
            padding: const EdgeInsets.only(bottom: 24),
          ),
          child: child!,
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

class _ProductApi extends ApiClient {
  _ProductApi()
    : super(baseUrl: 'http://localhost', tokenProvider: () async => null);
  int adds = 0;
  bool variants = false;
  bool coloredVariants = false;
  bool largeInStock = true;
  String effectivePrice = '250.00';
  int? addedQuantity;
  bool inStock = true;
  bool failSelection = false;
  String? variantId;
  List<String> selected = [];
  Completer<Cart>? pending;
  Cart current = Cart.empty;

  Cart get addedCart => Cart.fromJson({
    'items': [
      {
        'id': 'shirt-line',
        'product_id': 'shirt',
        'product_variant_id': variantId,
        'quantity': 1,
      },
      {'id': 'other-line', 'product_id': 'other', 'quantity': 2},
    ],
  });

  @override
  Future<Product> product({
    required String store,
    required String slug,
  }) async => Product.fromJson({
    'id': 'shirt',
    'name': 'Test shirt',
    'slug': slug,
    'price': '250.00',
    'effective_price': effectivePrice,
    'in_stock': inStock,
    'stock_quantity': 5,
    'images': coloredVariants
        ? [
            {'url': 'https://example.test/shared.jpg'},
            {'url': 'https://example.test/blue.jpg', 'color': 'Blue'},
            {'url': 'https://example.test/yellow.jpg', 'color': 'Yellow'},
          ]
        : [],
    'requires_variant_selection': variants,
    'has_variants': variants,
    'variants': variants
        ? [
            for (final label in ['Small', 'Large'])
              {
                'id': label.toLowerCase(),
                'label': label,
                'color': coloredVariants
                    ? (label == 'Small' ? 'Blue' : 'Yellow')
                    : null,
                'price': coloredVariants
                    ? (label == 'Small' ? '275.00' : '325.00')
                    : null,
                'sku': coloredVariants
                    ? (label == 'Small' ? 'SHIRT-BLUE' : 'SHIRT-YELLOW')
                    : null,
                'in_stock': label == 'Small' || largeInStock,
                'stock_quantity': coloredVariants && label == 'Large' ? 2 : 5,
              },
          ]
        : [],
  });
  @override
  Future<ReviewPage> productReviews({
    required String store,
    required String product,
    int page = 1,
  }) async => const ReviewPage(reviews: [], page: 1, lastPage: 1);
  @override
  Future<Cart> cart() async => current;
  @override
  Future<Cart> addToCart({
    required String productId,
    int quantity = 1,
    String? variantId,
  }) async {
    expect(productId, 'shirt');
    adds++;
    addedQuantity = quantity;
    this.variantId = variantId;
    return current = pending == null ? addedCart : await pending!.future;
  }

  @override
  Future<({int selectedCount, String subtotal})> updateCartSelection(
    List<String> ids,
  ) async {
    if (failSelection) {
      throw const ApiException(
        code: 'network.error',
        message: 'No connection.',
      );
    }
    selected = ids;
    return (selectedCount: 1, subtotal: '250.00');
  }
}
