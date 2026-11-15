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

  /// The user's pick (a branch slug), kept on the server cart so it survives a
  /// restart; null means "use the nearest branch".
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
    final branches = await ref.watch(storeBranchesProvider.future);
    // No store has a pin yet: don't ask for location just to show nothing.
    if (branches.isEmpty) {
      return const StoreLocatorState(branches: []);
    }
    final savedBranchId = await _savedBranchId();
    final saved = branches
        .where((b) => b.branchId == savedBranchId)
        .firstOrNull
        ?.id;
    try {
      final position = await ref
          .watch(locationServiceProvider)
          .currentPosition();
      return StoreLocatorState(
        branches: LocationService.sortByDistance(
          branches,
          position.latitude,
          position.longitude,
        ),
        selectedBranchId: saved,
      );
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
      // Saved on the cart, which also re-checks its lines against the new branch.
      ref
          .read(apiClientProvider)
          .setCartBranch(chosen)
          .then((_) {
            if (ref.mounted) {
              ref.invalidate(cartProvider);
            }
          }, onError: (_) {});
    }
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
    final id = (await ref.watch(
      nearestStoreProvider.future,
    )).activeBranch?.branch.branchId;
    return id == null || id.isEmpty ? null : id;
  } catch (_) {
    return null;
  }
});
