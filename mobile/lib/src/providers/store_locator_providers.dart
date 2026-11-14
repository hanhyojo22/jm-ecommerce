import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/location_service.dart';
import '../models/store_branch.dart';
import 'auth_providers.dart';

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

  /// The user's manual pick; null means "use the nearest store".
  final String? selectedBranchId;

  /// Null when location failed or there are no branches.
  BranchDistance? get nearest => issue == null ? branches.firstOrNull : null;

  BranchDistance? get activeBranch => selectedBranchId == null
      ? nearest
      : branches.firstWhere((b) => b.branch.id == selectedBranchId);

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
      );
    } on LocationFailure catch (failure) {
      return StoreLocatorState(
        branches: [for (final b in branches) BranchDistance(b, null)],
        issue: failure.issue,
      );
    }
  }

  void selectBranch(String branchId) => _select(branchId);

  void useNearest() => _select(null);

  void _select(String? branchId) {
    final current = state.value;
    if (current != null) {
      state = AsyncData(current.withSelection(branchId));
    }
  }
}

final nearestStoreProvider =
    AsyncNotifierProvider<NearestStoreNotifier, StoreLocatorState>(
      NearestStoreNotifier.new,
    );
