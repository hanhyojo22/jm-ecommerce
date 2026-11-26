import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart' show Position;

import '../core/location_service.dart';
import '../models/models.dart' show Cart;
import '../models/store_branch.dart';
import 'auth_providers.dart';
import 'cart_providers.dart';

final locationServiceProvider = Provider((ref) => const LocationService());

final storeBranchesProvider = FutureProvider<List<StoreBranch>>(
  (ref) => ref.watch(apiClientProvider).storeLocations(),
);

class StoreLocatorState {
  const StoreLocatorState({
    required this.branches,
    this.issue,
    this.selectedBranchId,
  });

  /// Sorted closest first when the location is known, otherwise in
  /// repository order with null distances.
  final List<BranchDistance> branches;

  /// Why the location is unknown; null when it was found.
  final LocationIssue? issue;

  /// The user's pick (a branch slug) for this session; null means "use the nearest
  /// branch". Kept on the server cart, but a launch that finds the location starts at the
  /// nearest again; only without a location does the cart's branch carry over.
  final String? selectedBranchId;

  /// Null when location failed or there are no branches.
  BranchDistance? get nearest => issue == null ? branches.firstOrNull : null;

  /// A picked branch that has since closed or lost its pin falls back to the nearest.
  BranchDistance? get activeBranch =>
      branches.where((b) => b.branch.id == selectedBranchId).firstOrNull ??
      nearest;

  bool get isWithinDeliveryRadius =>
      activeBranch?.isWithinDeliveryRadius ?? false;

  StoreLocatorState withSelection(String? branchId) => StoreLocatorState(
    branches: branches,
    issue: issue,
    selectedBranchId: branchId,
  );
}

class NearestStoreNotifier extends AsyncNotifier<StoreLocatorState> {
  @override
  Future<StoreLocatorState> build() async {
    // Started together: each is a slow round trip, and the catalog waits on both.
    final savedCart = _savedCart();
    final branches = await ref.watch(storeBranchesProvider.future);
    // No store has a pin yet: don't ask for location just to show nothing.
    if (branches.isEmpty) {
      return const StoreLocatorState(branches: []);
    }
    final cart = await savedCart;
    final savedBranchId = cart?.branchId;
    final saved = branches
        .where((b) => b.branchId == savedBranchId)
        .firstOrNull
        ?.id;
    try {
      final position = await ref
          .watch(locationServiceProvider)
          .currentPosition();
      final sorted = LocationService.sortByDistance(
        branches,
        position.latitude,
        position.longitude,
      );
      // Every launch starts at the nearest branch: an earlier pick, or a branch that was nearest
      // before a pin moved, must not keep the customer at a far one. The cart follows.
      final nearest = sorted.firstOrNull?.branch.branchId;
      final savedKm = sorted
          .where((b) => b.branch.branchId == savedBranchId)
          .firstOrNull
          ?.distanceKm;
      // A cart with items from a branch now far away (after a trip) is asked about first.
      if (saved != null &&
          nearest != savedBranchId &&
          (cart?.lines.isNotEmpty ?? false) &&
          (savedKm ?? 0) > _farKm) {
        _measured = sorted;
        _launchSuggestion = sorted.first;
        return StoreLocatorState(branches: sorted, selectedBranchId: saved);
      }
      if (nearest != null && nearest.isNotEmpty && nearest != savedBranchId) {
        _saveOnCart(nearest);
      }
      return StoreLocatorState(branches: sorted);
    } on LocationFailure catch (failure) {
      return StoreLocatorState(
        branches: [for (final b in branches) BranchDistance(b, null)],
        issue: failure.issue,
        selectedBranchId: saved,
      );
    }
  }

  /// The server cart, for the branch it buys from; a failed read just means none.
  Future<Cart?> _savedCart() async {
    try {
      return await ref.read(apiClientProvider).cart();
    } catch (_) {
      return null;
    }
  }

  /// Beyond this from the active branch, the customer has travelled.
  static const _farKm = 10;

  BranchDistance? _launchSuggestion;

  /// The nearer branch this launch found for a cart from a far one, once; ask with it,
  /// then call [acceptMove] or [declineMove].
  BranchDistance? takeLaunchSuggestion() {
    final suggestion = _launchSuggestion;
    _launchSuggestion = null;
    return suggestion;
  }

  /// Distances from the last [recheckAfterAway] that found a better branch.
  List<BranchDistance>? _measured;

  /// The branch the customer chose to stay away from; not suggested again.
  String? _declinedBranchId;

  /// Measures again after the app sat in the background, which may have been a trip. When
  /// the active branch is now far (10 km, or 30 km from a branch the customer picked) and
  /// another is nearest: an empty cart moves there quietly, otherwise that branch is
  /// returned so the customer can be asked first. Null when nothing should change.
  Future<BranchDistance?> recheckAfterAway({required bool cartIsEmpty}) async {
    final current = state.value;
    final active = current?.activeBranch;
    if (current == null || active == null) {
      return null;
    }
    final Position position;
    try {
      // Fresh: the last known fix may be from before the trip.
      position = await ref
          .read(locationServiceProvider)
          .currentPosition(fresh: true);
    } on LocationFailure {
      return null;
    }
    final measured = LocationService.sortByDistance(
      [for (final b in current.branches) b.branch],
      position.latitude,
      position.longitude,
    );
    final nearest = measured.first;
    final activeKm = measured
        .firstWhere((b) => b.branch.id == active.branch.id)
        .distanceKm;
    final farKm = current.selectedBranchId == null ? _farKm : 30;
    if (nearest.branch.id == active.branch.id ||
        nearest.branch.id == _declinedBranchId ||
        (activeKm ?? 0) <= farKm) {
      return null;
    }
    _measured = measured;
    if (cartIsEmpty) {
      acceptMove();
      return null;
    }
    return nearest;
  }

  /// Moves to the branch [recheckAfterAway] found; the cart follows.
  void acceptMove() {
    final measured = _measured;
    if (measured == null) {
      return;
    }
    _measured = null;
    state = AsyncData(StoreLocatorState(branches: measured));
    final nearest = measured.first.branch.branchId;
    if (nearest.isNotEmpty) {
      _saveOnCart(nearest);
    }
  }

  /// Stays at the current branch and stops suggesting [suggestion].
  void declineMove(BranchDistance suggestion) {
    _measured = null;
    _declinedBranchId = suggestion.branch.id;
  }

  void selectBranch(String branchId) => _select(branchId);

  void useNearest() => _select(null);

  void _select(String? branchId) {
    final current = state.value;
    if (current == null) {
      return;
    }
    final next = current.withSelection(branchId);
    state = AsyncData(next);
    final chosen = next.activeBranch?.branch.branchId;
    if (chosen != null && chosen.isNotEmpty) {
      _saveOnCart(chosen);
    }
  }

  /// Saved on the cart, which also re-checks its items against the new branch. A failure
  /// quietly leaves the cart as it was.
  void _saveOnCart(String branchId) {
    ref.read(cartProvider.notifier).changeBranch(branchId).ignore();
  }
}

final nearestStoreProvider =
    AsyncNotifierProvider<NearestStoreNotifier, StoreLocatorState>(
      NearestStoreNotifier.new,
    );

/// The branch the catalog, product and cart calls ask for: the saved pick, else
/// the nearest; null (company-wide) when neither is known yet.
final activeBranchIdProvider = FutureProvider<String?>((ref) async {
  try {
    final id = (await ref.watch(nearestStoreProvider.future))
        .activeBranch
        ?.branch
        .branchId;
    return id == null || id.isEmpty ? null : id;
  } catch (_) {
    return null;
  }
});

/// The closest branch other than the active one that has [productId] on hand,
/// using the distances the locator already measured; null when none does.
final stockedElsewhereProvider = FutureProvider.autoDispose
    .family<BranchDistance?, String>((ref, productId) async {
      final stocked = await ref
          .watch(apiClientProvider)
          .branchesWithStock(productId);
      final locator = await ref.watch(nearestStoreProvider.future);
      final active = locator.activeBranch?.branch.branchId;

      return locator.branches
          .where(
            (b) =>
                b.branch.branchId != active &&
                stocked.contains(b.branch.branchId),
          )
          .firstOrNull;
    });
