import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/models.dart';
import 'auth_providers.dart';
import 'store_locator_providers.dart';

class CartNotifier extends AsyncNotifier<Cart> {
  /// How long the stepper waits after the last tap before telling the server.
  static const quantityDebounce = Duration(milliseconds: 350);

  /// Lines with a quantity on screen that the server has not confirmed yet.
  final _pending = <String, _PendingLine>{};
  int _requestCount = 0;
  int _appliedRequest = 0;

  @override
  Future<Cart> build() async {
    // Unsent taps are dropped without an outcome: they were neither added nor refused.
    ref.onDispose(() {
      for (final line in _pending.values) {
        line.drop();
      }
      _pending.clear();
    });
    if (!ref.watch(isSignedInProvider)) {
      return const Cart(lines: [], selectedCount: 0, subtotal: '0.00');
    }
    return ref.watch(apiClientProvider).cart();
  }

  /// Every server cart lands here: replies older than one already shown are dropped, and
  /// quantities still waiting to be sent stay on screen instead of snapping back.
  void _applyServerCart(Cart cart, int request) {
    if (!ref.mounted || request < _appliedRequest) {
      return;
    }
    _appliedRequest = request;
    var shown = cart;
    for (final MapEntry(key: lineId, value: line) in _pending.entries) {
      shown = shown.withLineQuantity(lineId, line.quantity);
    }
    state = AsyncData(shown);
  }

  Future<void> refresh() async {
    final request = ++_requestCount;
    _applyServerCart(await ref.read(apiClientProvider).cart(), request);
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
    final request = ++_requestCount;

    try {
      final cart = await ref
          .read(apiClientProvider)
          .addToCart(
            productId: productId,
            quantity: quantity,
            variantId: variantId,
            buyNow: buyNow,
            branch: await ref.read(activeBranchIdProvider.future),
          );
      _applyServerCart(cart, request);
      return cart;
    } finally {
      pending.shift(-quantity);
    }
  }

  /// Refills the cart from a past order and returns the names the branch could not add.
  Future<List<String>> reorder(String orderId) async {
    final request = ++_requestCount;
    final result = await ref
        .read(apiClientProvider)
        .reorder(
          orderId,
          branch: await ref.read(activeBranchIdProvider.future),
        );
    _applyServerCart(result.cart, request);
    return result.skipped;
  }

  /// Whether a quantity on screen still waits for the server, so the subtotal is not final.
  bool get isUpdating => _pending.isNotEmpty;

  /// Whether [itemId]'s quantity on screen still waits for the server.
  bool isUpdatingItem(String itemId) => _pending.containsKey(itemId);

  /// Shows [quantity] for [itemId] at once and sends only the last of a burst of taps.
  /// The server's cart replaces the guess when it answers; if it refuses, the cart is
  /// fetched again (or the line put back when offline) and only the last tap's future
  /// fails, so one refusal shows one message. Removing a line goes through [remove].
  /// Prices and totals stay the server's throughout.
  Future<void> setQuantity(String itemId, int quantity) {
    assert(quantity > 0, 'Use remove() to take a line out of the cart.');
    final current = state.value;
    if (current == null) {
      return Future.value();
    }
    final pending = _pending.putIfAbsent(
      itemId,
      () => _PendingLine(
        current.lines.where((line) => line.id == itemId).firstOrNull,
      ),
    )..quantity = quantity;
    state = AsyncData(current.withLineQuantity(itemId, quantity));

    // The earlier tap of this burst is superseded; only the newest one reports.
    pending.waiter?.complete();
    final waiter = pending.waiter = Completer<void>();
    pending.timer?.cancel();
    pending.timer = Timer(quantityDebounce, () => _sendQuantity(itemId));

    return waiter.future;
  }

  Future<void> _sendQuantity(String itemId) async {
    final pending = _pending[itemId];
    if (pending == null) {
      return;
    }
    final waiter = pending.waiter;
    pending
      ..timer = null
      ..waiter = null;
    final request = pending.request = ++_requestCount;
    final api = ref.read(apiClientProvider);
    // Whether no newer tap on this line is queued or in flight behind this request.
    bool isLatest() =>
        identical(_pending[itemId], pending) &&
        pending.request == request &&
        pending.timer == null;

    try {
      final cart = await api.updateCartItem(itemId, pending.quantity);
      if (isLatest()) {
        _forget(itemId);
      } else if (identical(_pending[itemId], pending)) {
        // A newer tap is still to come; if it fails offline, this is what to go back to.
        pending.confirmedLine = cart.lines
            .where((line) => line.id == itemId)
            .firstOrNull;
      }
      _applyServerCart(cart, request);
      if (!pending.dropped) {
        waiter?.complete();
      }
    } catch (error, stack) {
      if (pending.dropped) {
        return;
      }
      if (!isLatest()) {
        // A newer tap decides what this line ends up as, and reports its own outcome.
        waiter?.complete();
        return;
      }
      _forget(itemId);
      final refetch = ++_requestCount;
      try {
        _applyServerCart(await api.cart(), refetch);
      } catch (_) {
        // Offline: put back only this line as the server last had it.
        final current = state.value;
        if (ref.mounted && current != null) {
          state = AsyncData(current.withLine(itemId, pending.confirmedLine));
        }
      }
      waiter?.completeError(error, stack);
    }
  }

  /// Takes [itemId] out of the unconfirmed lines. Watchers are told even when no new cart
  /// is shown, because [isUpdating] may have changed.
  void _forget(String itemId) {
    if (_pending.remove(itemId) != null && ref.mounted) {
      ref.notifyListeners();
    }
  }

  /// Forgets [itemId]'s taps, so they cannot overwrite its removal.
  /// Their futures never complete: the taps were neither added nor refused.
  void _dropPending(String itemId) {
    _pending[itemId]?.drop();
    _forget(itemId);
  }

  Future<void> remove(String itemId) async {
    _dropPending(itemId);
    final request = ++_requestCount;
    _applyServerCart(
      await ref.read(apiClientProvider).removeCartItem(itemId),
      request,
    );
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

/// One cart line's taps that the server has not confirmed yet.
class _PendingLine {
  _PendingLine(this.confirmedLine);

  /// The line as the server last had it, to put back when a tap fails offline.
  CartLine? confirmedLine;
  int quantity = 0;

  /// Sends the burst once the taps pause.
  Timer? timer;

  /// Reports the newest tap's outcome.
  Completer<void>? waiter;

  /// The newest request sent for this line.
  int? request;

  /// Set once a removal or a closed cart overtook these taps; a reply then reports nothing.
  bool dropped = false;

  void drop() {
    dropped = true;
    timer?.cancel();
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
