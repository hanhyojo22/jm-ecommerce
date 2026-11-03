import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/models.dart';
import 'auth_providers.dart';

class CartNotifier extends AsyncNotifier<Cart> {
  @override
  Future<Cart> build() => ref.watch(apiClientProvider).cart();

  Future<void> refresh() async {
    state = AsyncData(await ref.read(apiClientProvider).cart());
  }

  Future<void> add({
    required String productId,
    int quantity = 1,
    String? variantId,
  }) async {
    await ref
        .read(apiClientProvider)
        .addToCart(
          productId: productId,
          quantity: quantity,
          variantId: variantId,
        );
    await refresh();
  }

  Future<void> updateQuantity(String itemId, int quantity) async {
    final cart = await ref
        .read(apiClientProvider)
        .updateCartItem(itemId, quantity);
    state = AsyncData(cart);
  }

  Future<void> remove(String itemId) async {
    final cart = await ref.read(apiClientProvider).removeCartItem(itemId);
    state = AsyncData(cart);
  }

  Future<void> selectOnly(List<String> itemIds) async {
    await ref.read(apiClientProvider).updateCartSelection(itemIds);
    await refresh();
  }

  Future<void> clearSelection() async {
    await ref.read(apiClientProvider).updateCartSelection(const []);
    await refresh();
  }
}

final cartProvider = AsyncNotifierProvider<CartNotifier, Cart>(
  CartNotifier.new,
);

final cartCountProvider = Provider<int>((ref) {
  final cart = ref.watch(cartProvider).value;

  return cart?.lines.length ?? 0;
});
