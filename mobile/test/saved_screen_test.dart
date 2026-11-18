import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/providers/cart_providers.dart';
import 'package:jmmart/src/providers/store_locator_providers.dart';
import 'package:jmmart/src/screens/saved_screen.dart';

void main() {
  testWidgets('a saved product is listed and the heart removes it', (
    tester,
  ) async {
    final api = _SavedApi();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          isSignedInProvider.overrideWithValue(true),
          apiClientProvider.overrideWithValue(api),
          activeBranchIdProvider.overrideWith((ref) async => 'B-TEST'),
          cartCountProvider.overrideWithValue(0),
        ],
        child: const MaterialApp(home: SavedScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Paracetamol 500mg'), findsOneWidget);
    expect(api.branch, 'B-TEST');

    await tester.tap(find.byTooltip('Remove from saved items'));
    await tester.pumpAndSettle();

    expect(api.removed, ['P1']);
    expect(find.text('Nothing saved yet'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

class _SavedApi extends ApiClient {
  _SavedApi()
    : super(baseUrl: 'http://localhost', tokenProvider: () async => null);

  final List<String> removed = [];
  String? branch;

  @override
  Future<List<Product>> savedProducts({String? branch}) async {
    this.branch = branch;

    return removed.contains('P1')
        ? const []
        : const [
            Product(
              id: 'P1',
              name: 'Paracetamol 500mg',
              slug: 'paracetamol-500mg',
              price: '6.50',
              effectivePrice: '6.50',
              inStock: true,
              hasVariants: false,
              requiresVariantSelection: false,
              freeShipping: false,
              stockQuantity: 5,
              soldQuantity: 0,
              ratingAverage: 0,
              ratingCount: 0,
              store: StoreInfo(id: 'store', name: 'Botika', slug: 'botika'),
            ),
          ];
  }

  @override
  Future<void> unsaveProduct(String productId) async => removed.add(productId);
}
