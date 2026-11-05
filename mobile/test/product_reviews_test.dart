import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/core/api_exception.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/providers/catalog_providers.dart';
import 'package:jmmart/src/screens/product_screen.dart';
import 'package:jmmart/src/theme.dart';

void main() {
  testWidgets('product shows written comments and paginates reviews', (
    tester,
  ) async {
    final api = _ReviewApi();
    await _pumpProduct(tester, api);
    await tester.scrollUntilVisible(find.text('Customer reviews'), 400);
    await tester.pumpAndSettle();
    expect(find.text('Fits perfectly and feels comfortable.'), findsOneWidget);
    expect(find.text('Great fit'), findsOneWidget);
    expect(find.text('Ana'), findsOneWidget);
    expect(find.text('Verified purchase'), findsOneWidget);
    expect(find.text('Nov 1, 2026'), findsOneWidget);
    await tester.ensureVisible(find.text('Next reviews'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Next reviews'));
    await tester.pumpAndSettle();
    expect(api.requestedPages, [1, 2]);
    expect(find.text('Second page comment'), findsOneWidget);
    expect(find.text('Fits perfectly and feels comfortable.'), findsNothing);
    await tester.ensureVisible(find.text('Previous reviews'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Previous reviews'));
    await tester.pumpAndSettle();
    expect(find.text('Fits perfectly and feels comfortable.'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('review failure can be retried without hiding product details', (
    tester,
  ) async {
    final api = _ReviewApi()..fail = true;
    await _pumpProduct(tester, api);
    expect(find.text('Test shirt'), findsWidgets);
    await tester.scrollUntilVisible(find.text('Customer reviews'), 400);
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Retry reviews'));
    await tester.pumpAndSettle();
    api.fail = false;
    api.empty = true;
    await tester.tap(find.text('Retry reviews'));
    await tester.pumpAndSettle();
    expect(find.text('No reviews yet.'), findsOneWidget);
    expect(find.text('Retry reviews'), findsNothing);
    expect(find.text('Next reviews'), findsNothing);
  });
}

Future<void> _pumpProduct(WidgetTester tester, _ReviewApi api) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(api)],
      child: MaterialApp(
        theme: buildAppTheme(),
        home: const ProductScreen(
          productKey: ProductKey(store: 'store', slug: 'shirt'),
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

class _ReviewApi extends ApiClient {
  _ReviewApi()
    : super(baseUrl: 'http://localhost', tokenProvider: () async => null);
  bool fail = false;
  bool empty = false;
  final requestedPages = <int>[];

  @override
  Future<Cart> cart() async => Cart.empty;

  @override
  Future<Product> product({
    required String store,
    required String slug,
  }) async => Product.fromJson({
    'id': 'shirt',
    'name': 'Test shirt',
    'slug': slug,
    'price': '250.00',
    'effective_price': '250.00',
    'rating': {'average': 5, 'count': 2},
  });

  @override
  Future<ReviewPage> productReviews({
    required String store,
    required String product,
    int page = 1,
  }) async {
    expect(store, 'store');
    expect(product, 'shirt');
    requestedPages.add(page);
    if (fail) {
      throw const ApiException(
        code: 'network.error',
        message: 'No connection.',
      );
    }
    return ReviewPage(
      reviews: empty
          ? []
          : [
              ProductReview.fromJson({
                'rating': 5,
                'author': 'Ana',
                'title': page == 1 ? 'Great fit' : null,
                'body': page == 1
                    ? 'Fits perfectly and feels comfortable.'
                    : 'Second page comment',
                'created_at': '2026-11-01T00:00:00Z',
                'verified_purchase': true,
              }),
            ],
      page: page,
      lastPage: empty ? 1 : 2,
    );
  }
}
