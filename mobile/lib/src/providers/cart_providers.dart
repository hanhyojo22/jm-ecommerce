import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/models.dart';
import 'auth_providers.dart';
import 'store_locator_providers.dart';

/// The cart as the app shows it, with what the server has not confirmed yet.
@immutable
class CartState {
  const CartState(
    this.cart, {
    this.updatingItemIds = const {},
    this.addsOnTheWay = 0,
    this.addingProductIds = const {},
  });

  /// The server's cart with every unconfirmed quantity already applied.
  final Cart cart;

  /// Cart items whose quantity on screen still waits for the server.
  final Set<String> updatingItemIds;

  /// How many of products not yet in [cart] are on their way to it.
  final int addsOnTheWay;

  /// Products whose first add to the cart is on its way.
  final Set<String> addingProductIds;

  /// Whether the subtotal is not final yet.
  bool get isUpdating => updatingItemIds.isNotEmpty || addsOnTheWay > 0;

  /// Every item counted, including those still on their way.
  int get itemCount =>
      cart.lines.fold<int>(0, (count, line) => count + line.quantity) +
      addsOnTheWay;
}

class CartNotifier extends AsyncNotifier<CartState> {
  /// How long the cart waits after the last tap before telling the server.
  static const quantityDebounce = Duration(milliseconds: 350);

  /// The last cart the server sent; what is shown is worked out from it.
  Cart? _serverCart;

  /// Cart items with a quantity on screen that the server has not confirmed yet.
  final _pending = <String, _PendingItem>{};

  /// Products added from a card whose first add is still on its way, by product.
  final _firstAdds = <String, _FirstAdd>{};

  /// Quantity sent by [add] that the server has not answered yet.
  int _addsInFlight = 0;
  int _requestCount = 0;
  int _appliedRequest = 0;

  @override
  Future<CartState> build() async {
    // Unsent taps are dropped without an outcome: they were neither added nor refused.
    ref.onDispose(() {
      for (final pending in _pending.values) {
        pending.timer?.cancel();
      }
      _pending.clear();
      _firstAdds.clear();
      // Adds already sent stay counted until the server answers them.
    });
    _serverCart = null;
    if (!ref.watch(isSignedInProvider)) {
      _serverCart = const Cart(lines: [], selectedCount: 0, subtotal: '0.00');
      return _shown();
    }
    final request = ++_requestCount;
    final cart = await ref.watch(apiClientProvider).cart();
    if (request >= _appliedRequest) {
      _appliedRequest = request;
      _serverCart = cart;
    }
    return _shown();
  }

  CartState _shown() {
    var cart = _serverCart!;
    for (final MapEntry(key: itemId, value: pending) in _pending.entries) {
      cart = cart.withLineQuantity(itemId, pending.quantity);
    }
    return CartState(
      cart,
      updatingItemIds: {..._pending.keys},
      addsOnTheWay: _firstAdds.values.fold(
        _addsInFlight,
        (count, firstAdd) => count + firstAdd.queuedTaps,
      ),
      addingProductIds: {..._firstAdds.keys},
    );
  }

  /// Shows the cart again after any change; the one place the state is set.
  void _publish() {
    if (ref.mounted && _serverCart != null) {
      state = AsyncData(_shown());
    }
  }

  /// Every server cart lands here: replies older than one already shown are dropped.
  void _applyServerCart(Cart cart, int request) {
    if (request >= _appliedRequest) {
      _appliedRequest = request;
      _serverCart = cart;
    }
    _publish();
  }

  Future<void> refresh() async {
    final request = ++_requestCount;
    _applyServerCart(await ref.read(apiClientProvider).cart(), request);
  }

  /// Counts [quantity] in the badge straight away, then shows the server's cart; a failed
  /// request takes it back out. Returns the server's cart so callers need not read the
  /// provider again.
  Future<Cart> add({
    required String productId,
    int quantity = 1,
    String? variantId,
    bool buyNow = false,
  }) async {
    final api = ref.read(apiClientProvider);
    final branch = ref.read(activeBranchIdProvider.future);
    final request = ++_requestCount;
    _addsInFlight += quantity;
    _publish();

    final Cart cart;
    try {
      cart = await api.addToCart(
        productId: productId,
        quantity: quantity,
        variantId: variantId,
        buyNow: buyNow,
        branch: await branch,
      );
    } catch (_) {
      _addsInFlight -= quantity;
      _publish();
      rethrow;
    }
    _addsInFlight -= quantity;
    _applyServerCart(cart, request);
    return cart;
  }

  /// One more of [product] (without variants), as a product card's tap asks: added to
  /// the cart if it is not there yet, otherwise its quantity goes up and only the last of
  /// quick taps is sent. Taps made while the first add is on its way count too.
  ///
  /// Returns null, changing nothing, when the branch has no more in stock. Otherwise the
  /// future reports the tap: the first add succeeds or fails on its own, and among the
  /// taps after it only the newest one completes. Taps that a later tap, a removal or a
  /// closed cart overtook never complete.
  Future<void>? addOne(Product product) {
    // While the cart is still loading the tap is sent as an add; the server adds it to
    // whatever quantity the cart already holds.
    final line = state.value?.cart.lines
        .where((line) => line.productId == product.id && line.variantId == null)
        .firstOrNull;
    final firstAdd = _firstAdds[product.id];
    final wanted =
        (line?.quantity ?? 0) +
        (firstAdd == null ? 0 : 1 + firstAdd.queuedTaps) +
        1;
    final stock = product.stockQuantity;
    if (stock > 0 && wanted > stock) {
      return null;
    }
    if (line != null) {
      return setQuantity(line.id, wanted);
    }
    if (firstAdd != null) {
      firstAdd.queuedTaps++;
      final waiter = firstAdd.queuedWaiter = Completer<void>();
      _publish();
      return waiter.future;
    }
    return _addFirst(product.id);
  }

  Future<void> _addFirst(String productId) async {
    final firstAdd = _firstAdds[productId] = _FirstAdd();
    var dropped = false;
    final Cart cart;
    try {
      cart = await add(productId: productId);
    } finally {
      // A closed cart already forgot this add, and the taps queued behind it with it.
      dropped = !identical(_firstAdds[productId], firstAdd);
      if (!dropped) {
        _firstAdds.remove(productId);
        _publish();
      }
    }
    final line = cart.lines
        .where((line) => line.productId == productId && line.variantId == null)
        .firstOrNull;
    if (dropped || firstAdd.queuedTaps == 0 || line == null) {
      return;
    }
    // Taps queued behind the first add were confirmed by its toast; they only report a refusal.
    final queuedWaiter = firstAdd.queuedWaiter;
    setQuantity(line.id, line.quantity + firstAdd.queuedTaps).catchError((
      Object error,
      StackTrace stack,
    ) {
      queuedWaiter?.completeError(error, stack);
    });
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

  /// Moves the cart to [branchId]; the server checks every item against that branch.
  /// Taps not sent yet are dropped, as the items they were for are being re-checked.
  Future<void> changeBranch(String branchId) async {
    _dropAllPending();
    final request = ++_requestCount;
    _publish();
    _applyServerCart(
      await ref.read(apiClientProvider).setCartBranch(branchId),
      request,
    );
  }

  /// Shows [quantity] for [itemId] at once and sends only the last of a burst of taps.
  /// The server's cart replaces the guess when it answers; if it refuses, the cart is
  /// fetched again (or the server's last cart shown when offline) and the newest tap's
  /// future fails. Only the newest tap of a burst completes. Removing a cart item goes
  /// through [remove]. Prices and totals stay the server's throughout.
  Future<void> setQuantity(String itemId, int quantity) {
    assert(quantity > 0, 'Use remove() to take an item out of the cart.');
    // Sent even while the cart reloads; the reloaded cart shows it until the server answers.
    final pending = _pending.putIfAbsent(itemId, _PendingItem.new)
      ..quantity = quantity;
    final waiter = pending.waiter = Completer<void>();
    pending.timer?.cancel();
    pending.timer = Timer(quantityDebounce, () => _sendQuantity(itemId));
    _publish();

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
    // Whether no newer tap, removal or closed cart has overtaken this request.
    bool isLatest() =>
        identical(_pending[itemId], pending) &&
        pending.request == request &&
        pending.timer == null;

    try {
      final cart = await api.updateCartItem(itemId, pending.quantity);
      final latest = isLatest();
      if (latest) {
        _pending.remove(itemId);
      }
      _applyServerCart(cart, request);
      if (latest) {
        waiter?.complete();
      }
    } catch (error, stack) {
      if (!isLatest()) {
        // A newer tap or a removal decides what this item ends up as.
        return;
      }
      _pending.remove(itemId);
      final refetch = ++_requestCount;
      try {
        _applyServerCart(await api.cart(), refetch);
      } catch (_) {
        // Offline: the server's last cart is shown, without the refused quantity.
        _publish();
      }
      waiter?.completeError(error, stack);
    }
  }

  /// Forgets every unsent tap, so none can overwrite a change the customer made since.
  void _dropAllPending() {
    for (final pending in _pending.values) {
      pending.timer?.cancel();
    }
    _pending.clear();
  }

  Future<void> remove(String itemId) async {
    // Taps on this item that were not confirmed yet lose to its removal.
    _pending.remove(itemId)?.timer?.cancel();
    final request = ++_requestCount;
    _publish();
    _applyServerCart(
      await ref.read(apiClientProvider).removeCartItem(itemId),
      request,
    );
  }

  /// The selection response already carries the new count and subtotal, so the
  /// items are re-ticked locally instead of refetching the whole cart.
  Future<void> selectOnly(List<String> itemIds) async {
    final result = await ref
        .read(apiClientProvider)
        .updateCartSelection(itemIds);
    final serverCart = _serverCart;
    if (!ref.mounted || serverCart == null) {
      return;
    }
    final ids = itemIds.toSet();
    _serverCart = Cart(
      id: serverCart.id,
      branchId: serverCart.branchId,
      lines: [
        for (final line in serverCart.lines)
          line.withSelected(ids.contains(line.id)),
      ],
      selectedCount: result.selectedCount,
      subtotal: result.subtotal,
    );
    _publish();
  }
}

/// One cart item's taps that the server has not confirmed yet.
class _PendingItem {
  int quantity = 0;

  /// Sends the burst once the taps pause.
  Timer? timer;

  /// Reports the newest tap's outcome.
  Completer<void>? waiter;

  /// The newest request sent for this item.
  int? request;
}

/// A product's first add from a card, with the taps made while it is on its way.
class _FirstAdd {
  int queuedTaps = 0;

  /// Reports a refusal of the queued taps to the newest of them.
  Completer<void>? queuedWaiter;
}

final cartProvider = AsyncNotifierProvider.autoDispose<CartNotifier, CartState>(
  CartNotifier.new,
);

final cartCountProvider = Provider.autoDispose<int>(
  (ref) => ref.watch(cartProvider).value?.itemCount ?? 0,
);
