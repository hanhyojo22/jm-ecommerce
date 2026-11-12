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
    await _openVariations(tester);
    await tester.tap(find.text('Yellow'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Large'));
    await tester.pumpAndSettle();
    expect(
      tester
          .widget<ChoiceChip>(find.widgetWithText(ChoiceChip, 'Large'))
          .selected,
      isTrue,
    );
    expect(find.text('2 in stock'), findsOneWidget);
    await _closeSheet(tester);
    expect(api.adds, 0, reason: 'Closing the picker keeps the choice only.');
    expect(find.text('₱325.00'), findsOneWidget);
    expect(find.text('Blue · Yellow  |  Small · Large'), findsOneWidget);
    expect(find.text('Selected: Yellow / Large'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('SKU SHIRT-YELLOW'),
      400,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.drag(find.byType(ListView).first, const Offset(0, 1800));
    await tester.pumpAndSettle();
    expect(_firstPhoto(tester), 'https://example.test/yellow.jpg');
    final gallery = find
        .descendant(
          of: find.byType(PageView),
          matching: find.byType(Scrollable),
        )
        .first;
    expect(tester.state<ScrollableState>(gallery).position.pixels, 0);

    await tester.drag(find.byType(PageView), const Offset(-500, 0));
    await tester.pumpAndSettle();
    await _openVariations(tester);
    await tester.tap(find.text('Blue'));
    await tester.pumpAndSettle();
    expect(
      tester
          .widget<ChoiceChip>(find.widgetWithText(ChoiceChip, 'Large'))
          .selected,
      isFalse,
      reason: 'Blue has no Large, so the size is cleared.',
    );
    await tester.tap(find.text('Small'));
    await tester.pumpAndSettle();
    await _closeSheet(tester);
    expect(_firstPhoto(tester), 'https://example.test/blue.jpg');
    expect(tester.state<ScrollableState>(gallery).position.pixels, 0);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'picking a variant resets the quantity and caps it at that stock',
    (tester) async {
      final api = _ProductApi()
        ..variants = true
        ..coloredVariants = true;
      await _pump(tester, api);
      await _openVariations(tester);
      await tester.tap(find.byIcon(Icons.add));
      await tester.pump();
      await tester.tap(find.byIcon(Icons.add));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Yellow'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Large'));
      await tester.pumpAndSettle();
      expect(find.bySemanticsLabel('Quantity 1'), findsOneWidget);
      for (var tap = 0; tap < 3; tap++) {
        await tester.tap(find.byIcon(Icons.add));
        await tester.pump();
      }
      expect(find.bySemanticsLabel('Quantity 2'), findsOneWidget);
      await tester.tap(find.text('Confirm'));
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
    await _openVariations(tester);
    await tester.tap(find.text('Large'));
    await tester.pumpAndSettle();
    await _closeSheet(tester);
    expect(find.text('₱250.00'), findsOneWidget);
    expect(find.text('₱200.00'), findsNothing);
  });

  testWidgets('a sold-out variant cannot be selected', (tester) async {
    final api = _ProductApi()
      ..variants = true
      ..largeInStock = false;
    await _pump(tester, api);
    await _openVariations(tester);
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

  testWidgets('buy now opens checkout after a single cart request', (
    tester,
  ) async {
    final api = _ProductApi()..honorsBuyNow = true;
    await _pump(tester, api);
    await tester.tap(find.text('Buy now'));
    await tester.pumpAndSettle();
    expect(api.adds, 1);
    expect(api.sentBuyNow, isTrue);
    expect(api.selectionRequests, 0);
    expect(find.text('Checkout route'), findsOneWidget);
  });

  testWidgets('add to cart blocks duplicate taps and updates the cart badge', (
    tester,
  ) async {
    final api = _ProductApi()..pending = Completer<Cart>();
    await _pump(tester, api);
    await tester.tap(find.text('Add to cart'));
    await tester.pump();
    await tester.tap(find.text('Add to cart'));
    await tester.pump();
    expect(api.adds, 1);
    // The badge counts the item before the server answers.
    expect(find.text('1'), findsOneWidget);
    expect(
      tester
          .widget<FilledButton>(find.widgetWithText(FilledButton, 'Buy now'))
          .onPressed,
      isNull,
    );
    api.pending!.complete(api.addedCart);
    await tester.pumpAndSettle();
    expect(find.text('3'), findsOneWidget);
    expect(find.text('Checkout route'), findsNothing);
  });

  testWidgets('a failed add takes the optimistic item back out of the badge', (
    tester,
  ) async {
    final api = _ProductApi()..pending = Completer<Cart>();
    await _pump(tester, api);
    await tester.tap(find.text('Add to cart'));
    await tester.pump();
    expect(find.text('1'), findsOneWidget);

    api.pending!.completeError(
      const ApiException(
        code: 'insufficient_stock',
        message: 'Only 0 left in stock.',
        statusCode: 422,
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('1'), findsNothing);
    expect(find.textContaining('left in stock'), findsOneWidget);
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
        tester
            .widget<FilledButton>(find.widgetWithText(FilledButton, 'Buy now'))
            .onPressed,
        isNotNull,
      );
    },
  );

  testWidgets('buying without options opens the picker and continues', (
    tester,
  ) async {
    final api = _ProductApi()..variants = true;
    await _pump(tester, api);
    await tester.tap(find.text('Buy now'));
    await tester.pumpAndSettle();
    expect(api.adds, 0);
    expect(
      tester
          .widget<FilledButton>(find.widgetWithText(FilledButton, 'Confirm'))
          .onPressed,
      isNull,
    );
    await tester.tap(find.text('Small'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Confirm'));
    await tester.pumpAndSettle();
    expect(api.variantId, 'small');
    expect(find.text('Checkout route'), findsOneWidget);
  });

  testWidgets('sold out products cannot be added or bought', (tester) async {
    await _pump(tester, _ProductApi()..inStock = false);
    for (final label in ['Sold out', 'Add to cart']) {
      expect(
        tester
            .widget<FilledButton>(find.widgetWithText(FilledButton, label))
            .onPressed,
        isNull,
      );
    }
  });

  for (final action in ['Store']) {
    testWidgets('$action shortcut opens its destination', (tester) async {
      await _pump(tester, _ProductApi());
      await tester.tap(find.text(action));
      await tester.pumpAndSettle();
      expect(find.text('$action route'), findsOneWidget);
    });
  }

  testWidgets('the seller block renders with store and related products', (
    tester,
  ) async {
    await _pump(tester, _ProductApi()..withStore = true);
    expect(tester.takeException(), isNull);
    await tester.drag(find.byType(ListView).first, const Offset(0, -300));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Visit store'));
    await tester.pumpAndSettle();
    expect(find.text('Store route'), findsOneWidget);
  });

  testWidgets('header cart shortcut opens the cart', (tester) async {
    await _pump(tester, _ProductApi());
    await tester.tap(find.byTooltip('Open cart'));
    await tester.pumpAndSettle();
    expect(find.text('Cart route'), findsOneWidget);
  });

  testWidgets('tapping a gallery photo opens the full-screen image viewer', (
    tester,
  ) async {
    final api = _ProductApi()
      ..variants = true
      ..coloredVariants = true;
    await _pump(tester, api);
    await tester.tap(find.byType(PageView));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 250));
    expect(find.byKey(const ValueKey('image-viewer-zoom')), findsOneWidget);
    await tester.tap(find.byTooltip('Close'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 250));
    expect(find.byKey(const ValueKey('image-viewer-zoom')), findsNothing);
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
          expect(find.text('Add to cart').hitTestable(), findsOneWidget);
          expect(tester.takeException(), isNull);
        },
      );
    }
  }
}

Future<void> _openVariations(WidgetTester tester) async {
  await tester.tap(find.text('Add to cart'));
  await tester.pumpAndSettle();
}

Future<void> _closeSheet(WidgetTester tester) async {
  await tester.tapAt(const Offset(20, 20));
  await tester.pumpAndSettle();
}

String? _firstPhoto(WidgetTester tester) => tester
    .widgetList<NetworkThumb>(
      find.descendant(
        of: find.byType(PageView),
        matching: find.byType(NetworkThumb),
      ),
    )
    .first
    .url;

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
  bool withStore = false;
  bool coloredVariants = false;
  bool largeInStock = true;
  String effectivePrice = '250.00';
  int? addedQuantity;
  bool inStock = true;
  bool failSelection = false;
  bool honorsBuyNow = false;
  bool sentBuyNow = false;
  int selectionRequests = 0;
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
    'store': withStore
        ? {'id': 's', 'name': 'JM', 'slug': 'store', 'city': 'Cebu'}
        : null,
    'more_from_store': [
      if (withStore)
        {'id': 'pants', 'name': 'Pants', 'slug': 'pants', 'price': '1.00'},
    ],
    'related_products': [
      if (withStore)
        {'id': 'hoodie', 'name': 'Hoodie', 'slug': 'hoodie', 'price': '1.00'},
    ],
    'requires_variant_selection': variants,
    'has_variants': variants,
    'variants': variants
        ? [
            for (final size in ['Small', 'Large'])
              {
                'id': size.toLowerCase(),
                'label': coloredVariants
                    ? '${size == 'Small' ? 'Blue' : 'Yellow'} / $size'
                    : size,
                'color': coloredVariants
                    ? (size == 'Small' ? 'Blue' : 'Yellow')
                    : null,
                'size': size,
                'price': coloredVariants
                    ? (size == 'Small' ? '275.00' : '325.00')
                    : null,
                'sku': coloredVariants
                    ? (size == 'Small' ? 'SHIRT-BLUE' : 'SHIRT-YELLOW')
                    : null,
                'in_stock': size == 'Small' || largeInStock,
                'stock_quantity': coloredVariants && size == 'Large' ? 2 : 5,
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
    bool buyNow = false,
  }) async {
    expect(productId, 'shirt');
    adds++;
    addedQuantity = quantity;
    this.variantId = variantId;
    sentBuyNow = buyNow;
    if (buyNow && honorsBuyNow) {
      return current = Cart.fromJson({
        'items': [
          {
            'id': 'shirt-line',
            'product_id': 'shirt',
            'product_variant_id': variantId,
            'quantity': 1,
            'selected': true,
          },
          {
            'id': 'other-line',
            'product_id': 'other',
            'quantity': 2,
            'selected': false,
          },
        ],
        'selected_count': 1,
      });
    }
    return current = pending == null ? addedCart : await pending!.future;
  }

  @override
  Future<({int selectedCount, String subtotal})> updateCartSelection(
    List<String> ids,
  ) async {
    selectionRequests++;
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
