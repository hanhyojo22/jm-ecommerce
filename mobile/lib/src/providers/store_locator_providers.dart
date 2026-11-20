import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/location_service.dart';
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
    final savedBranch = _savedBranchId();
    final branches = await ref.watch(storeBranchesProvider.future);
    // No store has a pin yet: don't ask for location just to show nothing.
    if (branches.isEmpty) {
      return const StoreLocatorState(branches: []);
    }
    final savedBranchId = await savedBranch;
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

  /// The branch the server cart buys from; a failed read just means none.
  Future<String?> _savedBranchId() async {
    try {
      return (await ref.read(apiClientProvider).cart()).branchId;
    } catch (_) {
      return null;
    }
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

  /// Saved on the cart, which also re-checks its lines against the new branch.
  void _saveOnCart(String branchId) {
    ref.read(apiClientProvider).setCartBranch(branchId).then((_) {
      if (ref.mounted) {
        ref.invalidate(cartProvider);
      }
    }, onError: (_) {});
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
