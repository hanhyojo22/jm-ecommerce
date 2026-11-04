import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/widgets/common.dart';

void main() {
  testWidgets('tapping the store row opens the store, not the product', (
    tester,
  ) async {
    var storeTaps = 0;
    var productTaps = 0;

    await tester.pumpWidget(
      _card(
        onTap: () => productTaps++,
        onStoreTap: () => storeTaps++,
      ),
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
}

Widget _card({
  VoidCallback? onTap,
  VoidCallback? onStoreTap,
  bool showStore = true,
}) {
  return MaterialApp(
    home: Scaffold(
      body: Center(
        child: SizedBox(
          width: 180,
          height: 280,
          child: ProductCard(
            product: const Product(
              id: 'product-1',
              name: 'Cargo Pants',
              slug: 'cargo-pants',
              price: '255.00',
              effectivePrice: '255.00',
              inStock: true,
              hasVariants: false,
              requiresVariantSelection: false,
              freeShipping: false,
              stockQuantity: 3,
              soldQuantity: 0,
              ratingAverage: 0,
              ratingCount: 0,
              store: StoreInfo(id: 'store-1', name: 'JM Store', slug: 'jm'),
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
