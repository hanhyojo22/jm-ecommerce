import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/models.dart';
import 'auth_providers.dart';
import 'store_locator_providers.dart';

class CartNotifier extends AsyncNotifier<Cart> {
  /// How long the stepper waits after the last tap before telling the server.
  static const quantityDebounce = Duration(milliseconds: 350);

  /// Quantities shown on screen that the server has not confirmed yet, by line.
  final _unconfirmed = <String, int>{};

  /// Each line as the server last had it before a burst of taps, for going back offline.
  final _confirmedLines = <String, CartLine?>{};
  final _timers = <String, Timer>{};
  final _waiters = <String, Completer<void>>{};
  final _latestRequest = <String, int>{};
  int _requestCount = 0;
  int _appliedRequest = 0;

  @override
  Future<Cart> build() async {
    ref.onDispose(() {
      for (final timer in _timers.values) {
        timer.cancel();
      }
      for (final waiter in _waiters.values) {
        waiter.complete();
      }
      _timers.clear();
      _waiters.clear();
      _unconfirmed.clear();
      _confirmedLines.clear();
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
    for (final MapEntry(key: lineId, value: quantity) in _unconfirmed.entries) {
      shown = shown.withLineQuantity(lineId, quantity);
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

  Future<void> updateQuantity(String itemId, int quantity) async {
    final request = ++_requestCount;
    _applyServerCart(
      await ref.read(apiClientProvider).updateCartItem(itemId, quantity),
      request,
    );
  }

  /// Shows [quantity] for [itemId] at once and sends only the last of a burst of taps.
  /// The server's cart replaces the guess when it answers; if it refuses, the cart is
  /// fetched again (or the line put back when offline) and only the last tap's future
  /// fails, so one refusal shows one message. Zero removes the line straight away, but
  /// never as part of a burst: a double tap on "−" at 2 stops at 1. Prices and totals
  /// stay the server's throughout.
  Future<void> setQuantity(String itemId, int quantity) {
    final current = state.value;
    if (current == null || (quantity == 0 && _timers.containsKey(itemId))) {
      return Future.value();
    }
    _confirmedLines.putIfAbsent(
      itemId,
      () => current.lines.where((line) => line.id == itemId).firstOrNull,
    );
    _unconfirmed[itemId] = quantity;
    state = AsyncData(current.withLineQuantity(itemId, quantity));

    // The earlier tap of this burst is superseded; only the newest one reports.
    _waiters.remove(itemId)?.complete();
    final waiter = _waiters[itemId] = Completer<void>();
    _timers.remove(itemId)?.cancel();
    if (quantity == 0) {
      _sendQuantity(itemId);
    } else {
      _timers[itemId] = Timer(quantityDebounce, () => _sendQuantity(itemId));
    }

    return waiter.future;
  }

  Future<void> _sendQuantity(String itemId) async {
    _timers.remove(itemId);
    final quantity = _unconfirmed[itemId];
    final waiter = _waiters.remove(itemId);
    if (quantity == null) {
      waiter?.complete();
      return;
    }
    final request = _latestRequest[itemId] = ++_requestCount;
    final api = ref.read(apiClientProvider);
    // Whether no newer tap on this line is queued or in flight behind this request.
    bool isLatest() =>
        _latestRequest[itemId] == request && !_timers.containsKey(itemId);

    try {
      final cart = quantity == 0
          ? await api.removeCartItem(itemId)
          : await api.updateCartItem(itemId, quantity);
      if (isLatest()) {
        _settle(itemId);
      }
      _applyServerCart(cart, request);
      waiter?.complete();
    } catch (error, stack) {
      if (!isLatest()) {
        // A newer tap decides what this line ends up as, and reports its own outcome.
        waiter?.complete();
        return;
      }
      final confirmedLine = _confirmedLines[itemId];
      _settle(itemId);
      final refetch = ++_requestCount;
      try {
        _applyServerCart(await api.cart(), refetch);
      } catch (_) {
        // Offline: put back only this line as the server last had it.
        final current = state.value;
        if (ref.mounted && current != null) {
          state = AsyncData(_restoreLine(current, itemId, confirmedLine));
        }
      }
      waiter?.completeError(error, stack);
    }
  }

  void _settle(String itemId) {
    _unconfirmed.remove(itemId);
    _confirmedLines.remove(itemId);
    _latestRequest.remove(itemId);
  }

  static Cart _restoreLine(Cart cart, String itemId, CartLine? line) {
    final lines = [
      for (final current in cart.lines)
        if (current.id != itemId) current,
    ];
    if (line != null) {
      final index = cart.lines.indexWhere((current) => current.id == itemId);
      lines.insert(index < 0 ? lines.length : index, line);
    }

    return Cart(
      id: cart.id,
      branchId: cart.branchId,
      lines: lines,
      selectedCount: cart.selectedCount,
      subtotal: cart.subtotal,
    );
  }

  Future<void> remove(String itemId) async {
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
