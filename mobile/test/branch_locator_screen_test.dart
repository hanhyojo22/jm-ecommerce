import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:geolocator/geolocator.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/core/location_service.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/models/store_branch.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/providers/store_locator_providers.dart';
import 'package:jmmart/src/screens/branch_locator_screen.dart';
import 'package:jmmart/src/theme.dart';
import 'package:latlong2/latlong.dart';

const _capitol = StoreBranch(
  id: 'cebu-capitol',
  branchId: 'B-CAPITOL',
  name: 'Everyday Pharmacy Cebu Capitol',
  address: 'Cebu City, Cebu',
  latitude: 10.3160,
  longitude: 123.8912,
  description: 'Beside Cebu Provincial Capitol, north end of Osmena Blvd',
  addressLine: 'Osmena Boulevard',
  barangay: 'Capitol Site',
  city: 'Cebu City',
  province: 'Cebu',
  postalCode: '6000',
  phone: '+639171234567',
  openingTime: '07:00',
  closingTime: '22:00',
  isOpen: true,
  open24Hours: false,
);

const _fuente24 = StoreBranch(
  id: 'fuente-osmena',
  branchId: 'B-FUENTE',
  name: 'Everyday Pharmacy Fuente Osmena',
  address: 'Cebu City, Cebu',
  latitude: 10.3098,
  longitude: 123.8931,
  description: 'Across Fuente Osmena Rotonda',
  addressLine: 'Fuente Osmena Circle',
  barangay: 'Santa Cruz',
  city: 'Cebu City',
  province: 'Cebu',
  postalCode: '6000',
  phone: '+639172345678',
  isOpen: true,
  open24Hours: true,
);

final _mactanClosed = StoreBranch(
  id: 'mactan-newtown',
  branchId: 'B-MACTAN',
  name: 'Everyday Pharmacy Mactan Newtown',
  address: 'Lapu-Lapu City, Cebu',
  latitude: 10.3054,
  longitude: 124.0117,
  description: 'Near Mactan Newtown Beach Entrance',
  addressLine: 'Mactan Circumferential Rd',
  barangay: 'Mactan',
  city: 'Lapu-Lapu City',
  province: 'Cebu',
  postalCode: '6015',
  phone: '+639174567890',
  openingTime: '08:00',
  closingTime: '21:00',
  isOpen: false,
  open24Hours: false,
  nextOpeningAt: DateTime(2026, 11, 27, 8, 0),
);

void main() {
  group('StoreBranch and locator helpers', () {
    test('parses detailed address, phone, hours, and coordinates', () {
      final parsed = StoreBranch.fromJson({
        'id': 'B-1',
        'slug': 'everyday-pharmacy-cebu-capitol',
        'name': 'Everyday Pharmacy Cebu Capitol',
        'description': 'Beside Provincial Capitol',
        'address_line': 'Osmena Blvd',
        'barangay': 'Capitol Site',
        'city': 'Cebu City',
        'province': 'Cebu',
        'postal_code': '6000',
        'phone': '+639171234567',
        'latitude': 10.3160,
        'longitude': 123.8912,
        'opening_time': '07:00:00',
        'closing_time': '22:00:00',
        'open_24_hours': false,
        'is_open': true,
      });

      expect(parsed.shortName, 'Cebu Capitol');
      expect(
        parsed.fullAddress,
        'Osmena Blvd, Capitol Site, Cebu City, Cebu, 6000',
      );
      expect(parsed.formattedCoordinates, '10.3160° N, 123.8912° E');
      expect(parsed.formattedClosingTime, '10:00 PM');
      expect(parsed.formattedHoursRange, '7:00 AM – 10:00 PM');
    });

    test('computes estimated drive and walk times', () {
      const item = BranchDistance(_capitol, 2.2);
      expect(item.estimatedDriveMinutes, 6);
      expect(item.estimatedWalkMinutes, 28);
      expect(
        const BranchDistance(_capitol, null).estimatedDriveMinutes,
        isNull,
      );
    });

    test(
      'clusters nearby pins when zoomed out and separates when zoomed in',
      () {
        final branches = [
          const BranchDistance(_capitol, 1.4),
          const BranchDistance(_fuente24, 2.1),
          BranchDistance(_mactanClosed, 12.4),
        ];

        final unclustered = clusterBranches(branches, zoom: 12.5);
        expect(unclustered, hasLength(3));
        expect(unclustered.every((c) => !c.isCluster), isTrue);

        final clustered = clusterBranches(branches, zoom: 10.0);
        expect(clustered, hasLength(2));
        expect(clustered.first.isCluster, isTrue);
        expect(clustered.first.items, hasLength(2));
        expect(clustered.last.isCluster, isFalse);
      },
    );

    test('filters by query, open status, 24h flag, and map bounds', () {
      final branches = [
        const BranchDistance(_capitol, 1.4),
        const BranchDistance(_fuente24, 2.1),
        BranchDistance(_mactanClosed, 12.4),
      ];

      expect(
        filterLocatorBranches(
          branches,
          query: '',
          filter: BranchFilter.openNow,
        ).map((b) => b.branch.id),
        ['cebu-capitol', 'fuente-osmena'],
      );
      expect(
        filterLocatorBranches(
          branches,
          query: '',
          filter: BranchFilter.open24Hours,
        ).map((b) => b.branch.id),
        ['fuente-osmena'],
      );
      expect(
        filterLocatorBranches(
          branches,
          query: 'Rotonda',
          filter: BranchFilter.all,
        ).map((b) => b.branch.id),
        ['fuente-osmena'],
      );

      final cebuBounds = LatLngBounds(
        const LatLng(10.300, 123.880),
        const LatLng(10.330, 123.910),
      );
      expect(
        filterLocatorBranches(
          branches,
          query: '',
          filter: BranchFilter.all,
          areaBounds: cebuBounds,
        ).map((b) => b.branch.id),
        ['cebu-capitol', 'fuente-osmena'],
      );
    });
  });

  group('BranchLocatorScreen', () {
    testWidgets(
      'shows physical branch cards, Nearest and 24 Hours badges, without shopping buttons',
      (tester) async {
        await _pumpLocator(tester);

        expect(find.text('3 branches near you'), findsOneWidget);
        expect(find.text('Cebu Capitol'), findsWidgets);
        expect(find.text('Nearest'), findsOneWidget);
        expect(find.text('24 Hours'), findsWidgets);
        expect(find.text('Directions'), findsWidgets);
        expect(find.text('Branch info'), findsWidgets);
        expect(find.text('Shop here'), findsNothing);
        expect(find.text('Current branch'), findsNothing);
      },
    );

    testWidgets('search and filter chips narrow branches and recover cleanly', (
      tester,
    ) async {
      await _pumpLocator(tester);

      await tester.tap(find.text('24 Hours').first);
      await tester.pumpAndSettle();
      expect(find.text('1 branch near you'), findsOneWidget);
      expect(find.text('Fuente Osmena'), findsWidgets);

      await tester.enterText(find.byType(TextField), 'Nonexistent Place');
      await tester.pumpAndSettle();
      expect(find.text('No branches match those filters'), findsOneWidget);

      await tester.tap(find.text('Show all branches'));
      await tester.pumpAndSettle();
      expect(find.text('3 branches near you'), findsOneWidget);
    });

    testWidgets(
      'single-tap detent toggle cycles Expand, Show map, and Show list',
      (tester) async {
        await _pumpLocator(tester);

        expect(find.text('Expand'), findsOneWidget);
        await tester.tap(find.text('Expand'));
        await tester.pumpAndSettle();
        expect(find.text('Show map'), findsOneWidget);

        await tester.tap(find.text('Show map'));
        await tester.pumpAndSettle();
        expect(find.text('Show list'), findsOneWidget);

        await tester.tap(find.text('Show list'));
        await tester.pumpAndSettle();
        expect(find.text('Expand'), findsOneWidget);
      },
    );

    testWidgets(
      'opens Branch Detail sheet with expandable hours, Copy, Call, and Get Directions',
      (tester) async {
        final launched = <Uri>[];
        await _pumpLocator(
          tester,
          launchUri: (uri) async {
            launched.add(uri);
            return true;
          },
        );

        await tester.tap(find.text('Branch info').first);
        await tester.pumpAndSettle();

        expect(find.text('Everyday Pharmacy Cebu Capitol'), findsOneWidget);
        expect(find.text('Cebu Capitol Branch'), findsOneWidget);
        expect(find.text('Get Directions to Cebu Capitol'), findsOneWidget);

        await tester.tap(find.text('Opening Hours'));
        await tester.pumpAndSettle();
        expect(find.text('7:00 AM – 10:00 PM'), findsOneWidget);

        await tester.tap(find.text('Call'));
        await tester.pumpAndSettle();
        expect(launched.single.scheme, 'tel');
        expect(launched.single.path, '+639171234567');

        await tester.tap(find.text('Get Directions to Cebu Capitol'));
        await tester.pumpAndSettle();
        expect(find.text('Navigate to Cebu Capitol'), findsOneWidget);

        await tester.tap(find.text('Google Maps'));
        await tester.pumpAndSettle();
        expect(launched.last.host, 'www.google.com');
        expect(launched.last.queryParameters['destination'], '10.316,123.8912');
      },
    );

    testWidgets('Directions chooser launches Waze when selected', (
      tester,
    ) async {
      final launched = <Uri>[];
      await _pumpLocator(
        tester,
        launchUri: (uri) async {
          launched.add(uri);
          return true;
        },
      );

      await tester.tap(find.text('Directions').first);
      await tester.pumpAndSettle();
      expect(find.text('Navigate to Cebu Capitol'), findsOneWidget);

      await tester.tap(find.text('Waze'));
      await tester.pumpAndSettle();
      expect(launched.single.host, 'waze.com');
      expect(launched.single.queryParameters['ll'], '10.316,123.8912');
    });

    testWidgets('GPS denied state shows location banner without distances', (
      tester,
    ) async {
      await _pumpLocator(tester, locationIssue: LocationIssue.denied);

      expect(
        find.text('Allow location access to see distance from you.'),
        findsOneWidget,
      );
      expect(find.text('Allow'), findsOneWidget);
      expect(find.text('3 branches'), findsOneWidget);
      expect(find.text('Nearest'), findsNothing);
    });

    testWidgets(
      'renders cleanly on compact width, 1.3x text scale, and dark mode',
      (tester) async {
        await _pumpLocator(
          tester,
          width: 360,
          height: 740,
          textScale: 1.3,
          brightness: Brightness.dark,
        );

        expect(find.text('3 branches near you'), findsOneWidget);
        expect(tester.takeException(), isNull);
      },
    );
  });
}

Future<void> _pumpLocator(
  WidgetTester tester, {
  LocationIssue? locationIssue,
  Future<bool> Function(Uri uri)? launchUri,
  double width = 390,
  double height = 844,
  double textScale = 1.0,
  Brightness brightness = Brightness.light,
}) async {
  tester.view.physicalSize = Size(width, height);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        isSignedInProvider.overrideWithValue(false),
        apiClientProvider.overrideWithValue(_FakeApi()),
        storeBranchesProvider.overrideWith(
          (ref) async => [_capitol, _fuente24, _mactanClosed],
        ),
        locationServiceProvider.overrideWithValue(
          _FakeLocationService(failure: locationIssue),
        ),
      ],
      child: MaterialApp(
        theme: buildAppTheme(brightness: brightness),
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(context).copyWith(
            textScaler: TextScaler.linear(textScale),
            disableAnimations: true,
          ),
          child: child!,
        ),
        home: BranchLocatorScreen(launchUri: launchUri),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

class _FakeLocationService extends LocationService {
  const _FakeLocationService({this.failure});

  final LocationIssue? failure;

  @override
  Future<Position> currentPosition({bool fresh = false}) async {
    if (failure != null) {
      throw LocationFailure(failure!);
    }
    return Position(
      latitude: 10.3180,
      longitude: 123.8905,
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

class _FakeApi extends ApiClient {
  _FakeApi()
    : super(baseUrl: 'http://localhost', tokenProvider: () async => null);

  @override
  Future<Cart> cart() async =>
      const Cart(branchId: null, lines: [], selectedCount: 0, subtotal: '0.00');
}
