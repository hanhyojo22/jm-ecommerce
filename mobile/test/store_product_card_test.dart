import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/screens/store_screen.dart';
import 'package:jmmart/src/theme.dart';

void main() {
  testWidgets('store cards strike the old price beside the sale price', (
    tester,
  ) async {
    await tester.pumpWidget(_card(compareAtPrice: '300.00'));

    final oldPrice = tester.widget<Text>(find.text('₱300.00'));
    expect(oldPrice.style?.decoration, TextDecoration.lineThrough);
    expect(find.text('-15%'), findsOneWidget);

    final salePrice = tester.widget<Text>(find.text('₱255.00'));
    expect(
      salePrice.style?.color,
      Theme.of(tester.element(find.text('₱255.00'))).colorScheme.error,
    );
  });
}

Widget _card({String? compareAtPrice}) {
  return MaterialApp(
    theme: buildAppTheme(),
    home: Scaffold(
      body: Center(
        child: SizedBox(
          width: 180,
          height: 280,
          child: StoreProductCard(
            product: Product(
              id: 'product-1',
              name: 'Cargo Pants',
              slug: 'cargo-pants',
              price: '255.00',
              effectivePrice: '255.00',
              compareAtPrice: compareAtPrice,
              inStock: true,
              hasVariants: false,
              requiresVariantSelection: false,
              freeShipping: false,
              stockQuantity: 3,
              soldQuantity: 0,
              ratingAverage: 0,
              ratingCount: 0,
            ),
            onTap: () {},
          ),
        ),
      ),
    ),
  );
}
