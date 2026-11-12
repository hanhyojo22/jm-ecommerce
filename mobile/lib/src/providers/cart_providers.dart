import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/models.dart';
import 'auth_providers.dart';

class CartNotifier extends AsyncNotifier<Cart> {
  @override
  Future<Cart> build() => ref.watch(apiClientProvider).cart();

  Future<void> refresh() async {
    final cart = await ref.read(apiClientProvider).cart();
    if (ref.mounted) {
      state = AsyncData(cart);
    }
  }

  /// Counts the quantity in the badge straight away, then swaps in the
  /// server's cart; a failed request takes the optimistic quantity back out.
  /// Returns the server's cart so callers need not read the provider again.
  Future<Cart> add({
    required String productId,
    int quantity = 1,
    String? variantId,
    bool buyNow = false,
  }) async {
    final pending = ref.read(pendingCartQuantityProvider.notifier)
      ..shift(quantity);

    try {
      final cart = await ref
          .read(apiClientProvider)
          .addToCart(
            productId: productId,
            quantity: quantity,
            variantId: variantId,
            buyNow: buyNow,
          );
      if (ref.mounted) {
        state = AsyncData(cart);
      }
      return cart;
    } finally {
      pending.shift(-quantity);
    }
  }

  Future<void> updateQuantity(String itemId, int quantity) async {
    final cart = await ref
        .read(apiClientProvider)
        .updateCartItem(itemId, quantity);
    if (ref.mounted) {
      state = AsyncData(cart);
    }
  }

  Future<void> remove(String itemId) async {
    final cart = await ref.read(apiClientProvider).removeCartItem(itemId);
    if (ref.mounted) {
      state = AsyncData(cart);
    }
  }

  /// The selection response already carries the new count and subtotal, so the
  /// lines are re-ticked locally instead of refetching the whole cart.
  Future<void> selectOnly(List<String> itemIds) async {
    final result = await ref
        .read(apiClientProvider)
        .updateCartSelection(itemIds);
    if (!ref.mounted) return;
    final current = state.value;
    if (current == null) {
      return;
    }
    final ids = itemIds.toSet();
    state = AsyncData(
      Cart(
        id: current.id,
        lines: [
          for (final line in current.lines)
            line.withSelected(ids.contains(line.id)),
        ],
        selectedCount: result.selectedCount,
        subtotal: result.subtotal,
      ),
    );
  }

  Future<void> clearSelection() async {
    await selectOnly(const []);
  }
}

final cartProvider = AsyncNotifierProvider.autoDispose<CartNotifier, Cart>(
  CartNotifier.new,
);

/// Quantity sent to the server but not yet confirmed.
class PendingCartQuantity extends Notifier<int> {
  @override
  int build() => 0;

  void shift(int quantity) => state += quantity;
}

final pendingCartQuantityProvider = NotifierProvider<PendingCartQuantity, int>(
  PendingCartQuantity.new,
);

final cartCountProvider = Provider.autoDispose<int>((ref) {
  final cart = ref.watch(cartProvider).value;
  final confirmed =
      cart?.lines.fold<int>(0, (count, line) => count + line.quantity) ?? 0;

  return confirmed + ref.watch(pendingCartQuantityProvider);
});
