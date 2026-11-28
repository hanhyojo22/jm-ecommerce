import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/providers/catalog_providers.dart';
import 'package:jmmart/src/theme.dart';
import 'package:jmmart/src/widgets/common.dart';
import 'package:jmmart/src/widgets/product_feed.dart';

void main() {
  testWidgets(
    'unrated discovery cards show clean stock metadata instead of hollow stars',
    (tester) async {
      final semantics = tester.ensureSemantics();
      try {
        await tester.pumpWidget(_card(discovery: true));
        final name = find.text('Cargo Pants');
        // A one-line name takes only the space it needs, with no reserved blank line.
        expect(tester.getSize(name).height, lessThan(30));
        final stock = find.text('In stock');
        expect(stock, findsOneWidget);
        expect(find.text('No reviews'), findsNothing);
        expect(find.byIcon(Icons.star_outline_rounded), findsNothing);
        expect(find.byIcon(Icons.star_rounded), findsNothing);
        expect(
          tester.getTopLeft(stock).dy,
          greaterThan(tester.getBottomLeft(name).dy),
        );
        expect(
          tester.getBottomLeft(stock).dy,
          lessThan(tester.getTopLeft(find.text('₱255.00')).dy),
        );
      } finally {
        semantics.dispose();
      }
    },
  );

  testWidgets('a 4.5 rating fills four stars and half the fifth', (
    tester,
  ) async {
    await tester.pumpWidget(
      _card(discovery: true, ratingCount: 3, rating: 4.5),
    );
    expect(find.byIcon(Icons.star_rounded), findsNWidgets(4));
    expect(find.byIcon(Icons.star_half_rounded), findsOneWidget);
    expect(find.text('No reviews'), findsNothing);
  });

  testWidgets('discovery cards hug their content with a square photo', (
    tester,
  ) async {
    await tester.pumpWidget(_card(discovery: true));
    final plainHeight = tester.getSize(find.byType(ProductCard)).height;
    final lastLineBottom = tester.getBottomLeft(find.text('₱255.00')).dy;
    final cardBottom = tester.getBottomLeft(find.byType(Card)).dy;
    // No blank band under the last line.
    expect(cardBottom - lastLineBottom, lessThanOrEqualTo(16));

    await tester.pumpWidget(_card(discovery: true, compareAtPrice: '300.00'));
    // The struck price adds height to its own card instead of being reserved on every card.
    expect(
      tester.getSize(find.byType(ProductCard)).height,
      greaterThan(plainHeight),
    );
  });

  testWidgets('cards show neither the store name nor its city', (tester) async {
    var productTaps = 0;
    await tester.pumpWidget(_card(discovery: true, onTap: () => productTaps++));

    expect(find.text('JM Store'), findsNothing);
    expect(find.text('Laguna'), findsNothing);

    await tester.tap(find.text('Cargo Pants'));
    await tester.pump();
    expect(productTaps, 1);
  });

  testWidgets('discovery ratings include the review count for TalkBack', (
    tester,
  ) async {
    final semantics = tester.ensureSemantics();
    try {
      await tester.pumpWidget(_card(discovery: true, ratingCount: 12));
      expect(find.text('4.8 (12)'), findsOneWidget);
      expect(
        find.bySemanticsLabel(RegExp('Rated 4.8 out of 5, 12 reviews')),
        findsOneWidget,
      );
    } finally {
      semantics.dispose();
    }
  });

  testWidgets(
    'discovery cards badge prescription medicines in clinical indigo, never COD',
    (tester) async {
      await tester.pumpWidget(_card(discovery: true));
      expect(find.text('Rx'), findsNothing);
      expect(find.text('COD'), findsNothing);

      await tester.pumpWidget(
        _card(discovery: true, requiresPrescription: true),
      );
      final rxText = find.text('Rx');
      expect(rxText, findsOneWidget);
      final scheme = Theme.of(tester.element(rxText)).colorScheme;
      final widget = tester.widget<Text>(rxText);
      expect(widget.style?.color, scheme.onTertiaryContainer);
      final box = tester.widget<DecoratedBox>(
        find.ancestor(of: rxText, matching: find.byType(DecoratedBox)).first,
      );
      expect((box.decoration as BoxDecoration).color, scheme.tertiaryContainer);
    },
  );

  testWidgets('discovery cards label sold out stock', (tester) async {
    await tester.pumpWidget(_card(discovery: true, inStock: false));
    expect(find.text('Sold out'), findsOneWidget);
  });

  testWidgets('available discovery cards do not show sold out', (tester) async {
    await tester.pumpWidget(_card(discovery: true));
    expect(find.text('Sold out'), findsNothing);
  });

  testWidgets('discovery cards strike the old price below the sale price', (
    tester,
  ) async {
    await tester.pumpWidget(_card(discovery: true, compareAtPrice: '300.00'));

    final oldPrice = tester.widget<Text>(find.text('₱300.00'));
    expect(oldPrice.style?.decoration, TextDecoration.lineThrough);
    expect(
      tester.getTopLeft(find.text('₱300.00')).dy,
      greaterThan(tester.getTopLeft(find.text('₱255.00')).dy),
    );
    expect(find.text('-15%'), findsOneWidget);

    final salePrice = tester.widget<Text>(find.text('₱255.00'));
    expect(
      salePrice.style?.color,
      Theme.of(tester.element(find.text('₱255.00'))).colorScheme.error,
    );
  });

  testWidgets(
    'masonry product feed lets neighboring cards hug their own heights without stretching whitespace',
    (tester) async {
      const store = StoreInfo(
        id: 'store-1',
        name: 'Everyday Branch',
        slug: 'everyday',
        city: 'Manila',
      );
      const shortProduct = Product(
        id: 'p-short',
        name: 'Bandage',
        slug: 'bandage',
        price: '25.00',
        effectivePrice: '25.00',
        inStock: true,
        hasVariants: false,
        requiresVariantSelection: false,
        requiresPrescription: false,
        freeShipping: false,
        stockQuantity: 10,
        soldQuantity: 0,
        ratingAverage: 0,
        ratingCount: 0,
        store: store,
      );
      const tallProduct = Product(
        id: 'p-tall',
        name: 'Extra Strength Extended Release Multivitamin Complex With Zinc And Electrolytes 100 Tablets',
        genericName: 'ASCORBIC ACID + ZINC SULFATE MONOHYDRATE',
        slug: 'multivitamin',
        price: '450.00',
        effectivePrice: '399.00',
        compareAtPrice: '450.00',
        inStock: true,
        hasVariants: false,
        requiresVariantSelection: false,
        requiresPrescription: false,
        freeShipping: false,
        stockQuantity: 10,
        soldQuantity: 0,
        ratingAverage: 4.9,
        ratingCount: 24,
        store: store,
      );
      const thirdProduct = Product(
        id: 'p-third',
        name: 'Alcohol 70%',
        slug: 'alcohol',
        price: '65.00',
        effectivePrice: '65.00',
        inStock: true,
        hasVariants: false,
        requiresVariantSelection: false,
        requiresPrescription: false,
        freeShipping: false,
        stockQuantity: 10,
        soldQuantity: 0,
        ratingAverage: 0,
        ratingCount: 0,
        store: store,
      );

      final feed = ProductFeed(
        filter: const CatalogFilter(),
        products: const [shortProduct, tallProduct, thirdProduct],
        page: 1,
        lastPage: 1,
        total: 3,
      );

      tester.view.physicalSize = const Size(390, 900);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      await tester.pumpWidget(
        ProviderScope(
          overrides: [isSignedInProvider.overrideWithValue(false)],
          child: MaterialApp(
            theme: buildAppTheme(),
            home: Scaffold(
              body: Consumer(
                builder: (context, ref, _) => LayoutBuilder(
                  builder: (context, constraints) {
                    final grid = productGrid(context, constraints);
                    final slivers = ProductFeedSlivers(
                      context: context,
                      ref: ref,
                      gridDelegate: grid.delegate,
                      cardWidth: grid.cardWidth,
                      onClearFilters: () {},
                      onBrowseCategory: (_) {},
                      onBackToTop: () {},
                    );
                    return CustomScrollView(
                      slivers: slivers.products(feed, const []),
                    );
                  },
                ),
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      final shortCard = find.ancestor(
        of: find.text('Bandage'),
        matching: find.byType(ProductCard),
      );
      final tallCard = find.ancestor(
        of: find.textContaining('Multivitamin Complex'),
        matching: find.byType(ProductCard),
      );
      final thirdCard = find.ancestor(
        of: find.text('Alcohol 70%'),
        matching: find.byType(ProductCard),
      );

      final shortRect = tester.getRect(shortCard);
      final tallRect = tester.getRect(tallCard);
      final thirdRect = tester.getRect(thirdCard);

      // Side-by-side cards are allowed to differ in height instead of stretching.
      expect(shortRect.height, lessThan(tallRect.height - 20));
      // The next card in the left column packs directly below the short card (10dp gap),
      // rather than waiting for the taller right card to end.
      expect(thirdRect.top, closeTo(shortRect.bottom + 10, 1));
      expect(thirdRect.top, lessThan(tallRect.bottom));
    },
  );
}

Widget _card({
  VoidCallback? onTap,
  bool discovery = false,
  bool inStock = true,
  int ratingCount = 0,
  double rating = 4.8,
  String? compareAtPrice,
  bool requiresPrescription = false,
}) {
  return MaterialApp(
    theme: buildAppTheme(),
    home: Scaffold(
      body: Center(
        child: SizedBox(
          width: 180,
          // Feed cards hug their content, as in the masonry feed.
          height: discovery ? null : 280,
          child: ProductCard(
            discovery: discovery,
            product: Product(
              id: 'product-1',
              name: 'Cargo Pants',
              slug: 'cargo-pants',
              price: '255.00',
              effectivePrice: '255.00',
              compareAtPrice: compareAtPrice,
              inStock: inStock,
              hasVariants: false,
              requiresVariantSelection: false,
              requiresPrescription: requiresPrescription,
              freeShipping: false,
              stockQuantity: inStock ? 3 : 0,
              soldQuantity: 0,
              ratingAverage: ratingCount > 0 ? rating : 0,
              ratingCount: ratingCount,
              store: const StoreInfo(
                id: 'store-1',
                name: 'JM Store',
                slug: 'jm',
                city: 'Laguna',
              ),
            ),
            onTap: onTap ?? () {},
          ),
        ),
      ),
    ),
  );
}
