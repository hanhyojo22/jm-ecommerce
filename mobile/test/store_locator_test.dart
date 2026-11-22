import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:geolocator/geolocator.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/core/location_service.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/models/store_branch.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/providers/store_locator_providers.dart';

const _near = StoreBranch(
  id: 'near',
  branchId: 'B-NEAR',
  name: 'Near',
  address: 'A',
  latitude: 14.555,
  longitude: 121.025,
  maxDeliveryRadiusKm: 5,
);
const _far = StoreBranch(
  id: 'far',
  branchId: 'B-FAR',
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
  Future<Position> currentPosition({bool fresh = false}) async {
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

/// A phone that can travel between tests steps.
class _MovingLocationService extends LocationService {
  _MovingLocationService(this.latitude, this.longitude);

  double latitude;
  double longitude;

  @override
  Future<Position> currentPosition({bool fresh = false}) async => Position(
    latitude: latitude,
    longitude: longitude,
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

class _UnexpectedLocationService extends LocationService {
  const _UnexpectedLocationService();

  @override
  Future<Position> currentPosition({bool fresh = false}) =>
      throw StateError('Location must not be requested');
}

/// A server cart that remembers which branch it buys from.
class _CartBranchApi extends ApiClient {
  _CartBranchApi(this.savedBranchId)
    : super(baseUrl: 'http://localhost', tokenProvider: () async => null);

  String? savedBranchId;
  Set<String> stocked = {};
  bool hasItems = false;

  @override
  Future<Set<String>> branchesWithStock(String productId) async => stocked;

  @override
  Future<Cart> cart() async => Cart(
    branchId: savedBranchId,
    lines: [
      if (hasItems)
        const CartLine(
          id: 'line',
          productId: 'biogesic',
          quantity: 1,
          selected: true,
          available: true,
          unitPrice: '6.00',
          lineTotal: '6.00',
          productName: 'Biogesic',
          productSlug: 'biogesic',
          storeName: 'Far',
          storeSlug: 'far',
          hasVariants: false,
        ),
    ],
    selectedCount: 0,
    subtotal: '0.00',
  );

  @override
  Future<Cart> setCartBranch(String branchId) async {
    savedBranchId = branchId;
    return cart();
  }
}

ProviderContainer _container({
  LocationIssue? failure,
  List<StoreBranch> branches = const [_far, _near],
  LocationService location = const _FakeLocationService(),
  ApiClient? api,
}) {
  final container = ProviderContainer(
    overrides: [
      isSignedInProvider.overrideWithValue(true),
      apiClientProvider.overrideWithValue(api ?? _CartBranchApi(null)),
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

  test(
    'each launch starts at the nearest branch and moves the cart there',
    () async {
      final api = _CartBranchApi('B-FAR');
      final state = await _container(api: api)
          .read(nearestStoreProvider.future);
      await Future<void>.delayed(Duration.zero);

      expect(state.activeBranch!.branch.id, 'near');
      expect(api.savedBranchId, 'B-NEAR');
    },
  );

  test(
    'without a location the branch saved on the cart carries over',
    () async {
      final state = await _container(
        api: _CartBranchApi('B-FAR'),
        failure: LocationIssue.denied,
      ).read(nearestStoreProvider.future);

      expect(state.activeBranch!.branch.id, 'far');
    },
  );

  test(
    'a saved branch that is no longer listed falls back to the nearest',
    () async {
      final container = _container(api: _CartBranchApi('B-CLOSED'));

      expect(await container.read(activeBranchIdProvider.future), 'B-NEAR');
    },
  );

  test('picking a branch saves it on the cart', () async {
    final api = _CartBranchApi(null);
    final container = _container(api: api);
    expect(await container.read(activeBranchIdProvider.future), 'B-NEAR');

    container.read(nearestStoreProvider.notifier).selectBranch('far');
    await Future<void>.delayed(Duration.zero);

    expect(api.savedBranchId, 'B-FAR');
    expect(await container.read(activeBranchIdProvider.future), 'B-FAR');
  });

  test(
    'stocked elsewhere suggests the nearest other branch that has it',
    () async {
      final api = _CartBranchApi(null)..stocked = {'B-NEAR', 'B-FAR'};
      final container = _container(api: api);

      // Near is the active branch, so the suggestion is the other one.
      final other = await container.read(stockedElsewhereProvider('p1').future);
      expect(other!.branch.id, 'far');

      api.stocked = {'B-NEAR'};
      container.invalidate(stockedElsewhereProvider('p1'));
      expect(
        await container.read(stockedElsewhereProvider('p1').future),
        isNull,
      );
    },
  );

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

  test(
    'a launch after a trip asks before moving a cart with items far away',
    () async {
      final api = _CartBranchApi('B-FAR')..hasItems = true;
      final container = _container(api: api);
      final state = await container.read(nearestStoreProvider.future);
      final locator = container.read(nearestStoreProvider.notifier);

      expect(state.activeBranch!.branch.id, 'far');
      expect(api.savedBranchId, 'B-FAR');
      final suggestion = locator.takeLaunchSuggestion();
      expect(suggestion!.branch.id, 'near');
      expect(locator.takeLaunchSuggestion(), isNull);

      locator.acceptMove();
      await Future<void>.delayed(Duration.zero);
      expect(
        container.read(nearestStoreProvider).value!.activeBranch!.branch.id,
        'near',
      );
      expect(api.savedBranchId, 'B-NEAR');
    },
  );

  group('after the app sat in the background', () {
    // Starts beside Near; Far is about 18 km away.
    Future<(ProviderContainer, _MovingLocationService, _CartBranchApi)>
    atNear() async {
      final phone = _MovingLocationService(14.5547, 121.0244);
      final api = _CartBranchApi(null);
      final container = _container(location: phone, api: api);
      await container.read(nearestStoreProvider.future);
      return (container, phone, api);
    }

    test(
      'an empty cart follows the customer to the new nearest branch',
      () async {
        final (container, phone, api) = await atNear();
        phone
          ..latitude = 14.70
          ..longitude = 121.10;

        final suggestion = await container
            .read(nearestStoreProvider.notifier)
            .recheckAfterAway(cartIsEmpty: true);
        await Future<void>.delayed(Duration.zero);

        expect(suggestion, isNull);
        expect(
          container.read(nearestStoreProvider).value!.activeBranch!.branch.id,
          'far',
        );
        expect(api.savedBranchId, 'B-FAR');
      },
    );

    test(
      'a cart with items asks first, and a "Keep" is not asked again',
      () async {
        final (container, phone, _) = await atNear();
        final locator = container.read(nearestStoreProvider.notifier);
        phone
          ..latitude = 14.70
          ..longitude = 121.10;

        final suggestion = await locator.recheckAfterAway(cartIsEmpty: false);
        expect(suggestion!.branch.id, 'far');
        expect(
          container.read(nearestStoreProvider).value!.activeBranch!.branch.id,
          'near',
        );

        locator.declineMove(suggestion);
        expect(await locator.recheckAfterAway(cartIsEmpty: false), isNull);
        expect(
          container.read(nearestStoreProvider).value!.activeBranch!.branch.id,
          'near',
        );
      },
    );

    test('switching moves the cart to the suggested branch', () async {
      final (container, phone, api) = await atNear();
      final locator = container.read(nearestStoreProvider.notifier);
      phone
        ..latitude = 14.70
        ..longitude = 121.10;

      await locator.recheckAfterAway(cartIsEmpty: false);
      locator.acceptMove();
      await Future<void>.delayed(Duration.zero);

      expect(
        container.read(nearestStoreProvider).value!.activeBranch!.branch.id,
        'far',
      );
      expect(api.savedBranchId, 'B-FAR');
    });

    test('a short trip, or a branch the customer picked, stays put', () async {
      final (container, phone, _) = await atNear();
      final locator = container.read(nearestStoreProvider.notifier);

      // Just past halfway: Far is now nearest, but Near is under 10 km away, so no change.
      phone
        ..latitude = 14.6304
        ..longitude = 121.064;
      expect(await locator.recheckAfterAway(cartIsEmpty: true), isNull);
      expect(
        container.read(nearestStoreProvider).value!.activeBranch!.branch.id,
        'near',
      );

      // Picked Far by hand, then back beside Near: 18 km is under the 30 km for a pick.
      locator.selectBranch('far');
      phone
        ..latitude = 14.5547
        ..longitude = 121.0244;
      expect(await locator.recheckAfterAway(cartIsEmpty: true), isNull);
      expect(
        container.read(nearestStoreProvider).value!.activeBranch!.branch.id,
        'far',
      );
    });
  });
}
