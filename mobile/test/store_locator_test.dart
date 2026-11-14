import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:geolocator/geolocator.dart';
import 'package:jmmart/src/core/location_service.dart';
import 'package:jmmart/src/models/store_branch.dart';
import 'package:jmmart/src/providers/store_locator_providers.dart';

const _near = StoreBranch(
  id: 'near',
  name: 'Near',
  address: 'A',
  latitude: 14.555,
  longitude: 121.025,
  maxDeliveryRadiusKm: 5,
);
const _far = StoreBranch(
  id: 'far',
  name: 'Far',
  address: 'B',
  latitude: 14.70,
  longitude: 121.10,
  maxDeliveryRadiusKm: 5,
);

class _FakeLocationService extends LocationService {
  const _FakeLocationService({this.failure});

  final LocationIssue? failure;

  @override
  Future<Position> currentPosition() async {
    if (failure != null) {
      throw LocationFailure(failure!);
    }
    return Position(
      latitude: 14.5547,
      longitude: 121.0244,
      timestamp: DateTime(2026),
      accuracy: 0,
      altitude: 0,
      altitudeAccuracy: 0,
      heading: 0,
      headingAccuracy: 0,
      speed: 0,
      speedAccuracy: 0,
    );
  }
}

class _UnexpectedLocationService extends LocationService {
  const _UnexpectedLocationService();

  @override
  Future<Position> currentPosition() =>
      throw StateError('Location must not be requested');
}

ProviderContainer _container({
  LocationIssue? failure,
  List<StoreBranch> branches = const [_far, _near],
  LocationService location = const _FakeLocationService(),
}) {
  final container = ProviderContainer(
    overrides: [
      storeBranchesProvider.overrideWith((ref) async => branches),
      locationServiceProvider.overrideWithValue(
        failure == null ? location : _FakeLocationService(failure: failure),
      ),
    ],
  );
  addTearDown(container.dispose);
  return container;
}

void main() {
  test('sorts branches closest first and checks the delivery radius', () async {
    final state = await _container().read(nearestStoreProvider.future);

    expect(state.branches.map((b) => b.branch.id), ['near', 'far']);
    expect(state.nearest!.distanceKm, lessThan(1));
    expect(state.isWithinDeliveryRadius, isTrue);
    expect(state.branches.last.isWithinDeliveryRadius, isFalse);
  });

  test('manual pick overrides the nearest branch until reset', () async {
    final container = _container();
    await container.read(nearestStoreProvider.future);
    final notifier = container.read(nearestStoreProvider.notifier);

    notifier.selectBranch('far');
    var state = container.read(nearestStoreProvider).value!;
    expect(state.activeBranch!.branch.id, 'far');
    expect(state.isWithinDeliveryRadius, isFalse);

    notifier.useNearest();
    state = container.read(nearestStoreProvider).value!;
    expect(state.activeBranch!.branch.id, 'near');
  });

  test('no pinned stores skips the location request', () async {
    final state = await _container(
      branches: const [],
      location: const _UnexpectedLocationService(),
    ).read(nearestStoreProvider.future);

    expect(state.issue, isNull);
    expect(state.nearest, isNull);
    expect(state.activeBranch, isNull);
  });

  test('parses the API store and hides the badge without a radius', () {
    final store = StoreBranch.fromJson({
      'slug': 'northwind',
      'name': 'Northwind',
      'city': 'Manila',
      'province': 'Metro Manila',
      'latitude': 14.5547,
      'longitude': 121.0244,
      'delivery_radius_km': null,
    });

    expect(store.id, 'northwind');
    expect(store.address, 'Manila, Metro Manila');
    expect(store.maxDeliveryRadiusKm, isNull);
    expect(BranchDistance(store, 50).isOutOfDeliveryRange, isFalse);
    expect(BranchDistance(_near, 50).isOutOfDeliveryRange, isTrue);
  });

  test('denied permission keeps branches pickable without distances', () async {
    final container = _container(failure: LocationIssue.deniedForever);
    final state = await container.read(nearestStoreProvider.future);

    expect(state.issue, LocationIssue.deniedForever);
    expect(state.activeBranch, isNull);
    expect(state.branches.every((b) => b.distanceKm == null), isTrue);

    container.read(nearestStoreProvider.notifier).selectBranch('near');
    final picked = container.read(nearestStoreProvider).value!;
    expect(picked.activeBranch!.branch.id, 'near');
    expect(picked.isWithinDeliveryRadius, isFalse);
  });
}
