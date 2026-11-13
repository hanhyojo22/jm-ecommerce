import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/widgets/common.dart';

void main() {
  testWidgets(
    'discovery details stay closely grouped and show unrated status',
    (tester) async {
      await tester.pumpWidget(_card(discovery: true));
      final name = find.text('Cargo Pants');
      // A one-line name takes only the space it needs, with no reserved blank line.
      expect(tester.getSize(name).height, lessThan(30));
      final nameBottom = tester.getBottomLeft(name).dy;
      final priceTop = tester.getTopLeft(find.text('₱255.00')).dy;
      expect(priceTop - nameBottom, lessThanOrEqualTo(6));
      final rating = find.text('No reviews yet');
      expect(rating, findsOneWidget);
      final storeTop = tester.getTopLeft(find.text('JM Store')).dy;
      expect(storeTop - tester.getBottomLeft(rating).dy, lessThanOrEqualTo(8));
    },
  );

  testWidgets('discovery seller links do not open the product', (tester) async {
    var storeTaps = 0;
    var productTaps = 0;
    await tester.pumpWidget(
      _card(
        discovery: true,
        onTap: () => productTaps++,
        onStoreTap: () => storeTaps++,
      ),
    );

    await tester.tap(find.text('JM Store'));
    await tester.pump();

    expect(storeTaps, 1);
    expect(productTaps, 0);
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

  testWidgets('tapping the store row opens the store, not the product', (
    tester,
  ) async {
    var storeTaps = 0;
    var productTaps = 0;

    await tester.pumpWidget(
      _card(onTap: () => productTaps++, onStoreTap: () => storeTaps++),
    );

    await tester.tap(find.text('JM Store'));
    await tester.pump();

    expect(storeTaps, 1);
    expect(productTaps, 0);
  });

  testWidgets('the store row can be hidden on a store page', (tester) async {
    await tester.pumpWidget(_card(showStore: false));

    expect(find.text('JM Store'), findsNothing);
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
  VoidCallback? onStoreTap,
  bool showStore = true,
  bool discovery = false,
  bool inStock = true,
  int ratingCount = 0,
  String? compareAtPrice,
}) {
  return MaterialApp(
    home: Scaffold(
      body: Center(
        child: SizedBox(
          width: 180,
          height: 280,
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
              freeShipping: false,
              stockQuantity: inStock ? 3 : 0,
              soldQuantity: 0,
              ratingAverage: ratingCount > 0 ? 4.8 : 0,
              ratingCount: ratingCount,
              store: const StoreInfo(
                id: 'store-1',
                name: 'JM Store',
                slug: 'jm',
              ),
            ),
            onTap: onTap ?? () {},
            onStoreTap: onStoreTap,
            showStore: showStore,
          ),
        ),
      ),
    ),
  );
}
