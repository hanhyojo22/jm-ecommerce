import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/theme.dart';
import 'package:jmmart/src/widgets/common.dart';

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

  testWidgets('discovery cards badge prescription medicines in clinical indigo, never COD', (
    tester,
  ) async {
    await tester.pumpWidget(_card(discovery: true));
    expect(find.text('Rx'), findsNothing);
    expect(find.text('COD'), findsNothing);

    await tester.pumpWidget(_card(discovery: true, requiresPrescription: true));
    final rxText = find.text('Rx');
    expect(rxText, findsOneWidget);
    final scheme = Theme.of(tester.element(rxText)).colorScheme;
    final widget = tester.widget<Text>(rxText);
    expect(widget.style?.color, scheme.onTertiaryContainer);
    final box = tester.widget<DecoratedBox>(
      find.ancestor(of: rxText, matching: find.byType(DecoratedBox)).first,
    );
    expect((box.decoration as BoxDecoration).color, scheme.tertiaryContainer);
  });

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
