import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:geolocator/geolocator.dart';
import 'package:go_router/go_router.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/core/attachment_picker.dart';
import 'package:jmmart/src/core/location_service.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/models/store_branch.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/providers/checkout_providers.dart';
import 'package:jmmart/src/providers/store_locator_providers.dart';
import 'package:jmmart/src/screens/checkout_screen.dart';
import 'package:jmmart/src/theme.dart';

const _branch = StoreBranch(
  id: 'makati',
  branchId: 'B-MAKATI',
  name: 'Makati branch',
  address: 'Makati',
  latitude: 14.5547,
  longitude: 121.0244,
  maxDeliveryRadiusKm: 5,
);

void main() {
  testWidgets('a senior citizen card takes the discount off the total', (
    tester,
  ) async {
    final api = _CheckoutApi(rx: false, seniorPwd: '28.57');
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, null)],
        issue: LocationIssue.denied,
      ),
      location: const _NoLocation(),
    );
    await tester.tap(find.text('Pickup · Free'));
    await tester.pumpAndSettle();
    expect(find.text('₱100.00'), findsWidgets);

    await tester.ensureVisible(find.text('Use my discount'));
    await tester.tap(find.text('Use my discount'));
    await tester.pumpAndSettle();
    expect(find.text('₱71.43'), findsWidgets);

    await tester.tap(find.text('Place order'));
    await tester.pump();
    expect(
      find.text('Enter the ID number and the name on the ID for the discount.'),
      findsOneWidget,
    );
    expect(api.placed, isNull);

    await tester.enterText(find.widgetWithText(TextField, 'ID number'), 'SC-9');
    await tester.enterText(
      find.widgetWithText(TextField, 'Name on the ID'),
      'Lola Ana',
    );
    tester
        .state<ScaffoldMessengerState>(find.byType(ScaffoldMessenger))
        .removeCurrentSnackBar();
    await tester.pumpAndSettle();
    await tester.tap(find.text('Place order'));
    await tester.pumpAndSettle();

    expect(api.placed!.discountCard, (
      type: 'senior',
      idNumber: 'SC-9',
      name: 'Lola Ana',
    ));
    expect(tester.takeException(), isNull);
  });

  testWidgets('an Rx pickup order needs a prescription and sends no pin', (
    tester,
  ) async {
    final api = _CheckoutApi(rx: true);
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, null)],
        issue: LocationIssue.denied,
      ),
      location: const _NoLocation(),
    );

    expect(
      find.text('We could not find your location. Drag the map to your door.'),
      findsOneWidget,
    );
    await tester.tap(find.text('Pickup · Free'));
    await tester.pumpAndSettle();
    expect(find.text('Pay at the counter'), findsOneWidget);

    await tester.tap(find.text('Place order'));
    await tester.pump();
    expect(find.text('Add a photo of your prescription.'), findsOneWidget);
    expect(api.placed, isNull);

    await tester.ensureVisible(find.text('Add photos'));
    await tester.tap(find.text('Add photos'));
    await tester.pumpAndSettle();
    expect(
      find.text('1 page added. Include every page of the prescription.'),
      findsOneWidget,
    );
    expect(find.text('rx.jpg'), findsOneWidget);
    tester
        .state<ScaffoldMessengerState>(find.byType(ScaffoldMessenger))
        .removeCurrentSnackBar();
    await tester.pumpAndSettle();

    await tester.tap(find.text('Place order'));
    await tester.pumpAndSettle();

    expect(api.placed!.fulfillment, 'pickup');
    expect(api.placed!.prescriptions.single.name, 'rx.jpg');
    expect(api.placed!.latitude, isNull);
    expect(find.text('Order route'), findsOneWidget);
  });

  testWidgets(
    'a delivery inside the radius sends the map pin and adds the fee',
    (tester) async {
      final api = _CheckoutApi(rx: false);
      await _pump(
        tester,
        api,
        locator: const StoreLocatorState(
          branches: [BranchDistance(_branch, 1.2)],
        ),
      );

      expect(find.text('Delivery · ₱49.00'), findsOneWidget);
      expect(find.text('Delivery fee'), findsOneWidget);
      expect(find.text('₱149.00'), findsWidgets);

      await tester.tap(find.text('Place order'));
      await tester.pumpAndSettle();

      expect(api.placed!.fulfillment, 'delivery');
      expect(api.placed!.latitude, 14.55);
      expect(api.placed!.longitude, 121.02);
      expect(api.placed!.prescriptions, isEmpty);
    },
  );

  testWidgets('a closed branch starts on pickup and greys out delivery', (
    tester,
  ) async {
    final api = _CheckoutApi(
      rx: false,
      closedUntil: DateTime.now()
          .add(const Duration(hours: 2))
          .toUtc()
          .toIso8601String(),
    );
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, 1.2)],
      ),
    );

    expect(find.textContaining('Makati branch is closed.'), findsOneWidget);
    expect(find.text('Pay at the counter'), findsOneWidget);

    await tester.tap(find.text('Delivery · ₱49.00'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Place order'));
    await tester.pumpAndSettle();
    expect(api.placed!.fulfillment, 'pickup');
  });

  for (final width in [320.0, 390.0]) {
    for (final scale in [1.0, 2.0]) {
      testWidgets('checkout fits $width at ${scale}x text', (tester) async {
        await _pump(
          tester,
          _CheckoutApi(rx: true),
          locator: const StoreLocatorState(
            branches: [BranchDistance(_branch, 1.2)],
          ),
          width: width,
          scale: scale,
        );
        // Lay out every section, down to the order summary.
        await tester.drag(find.byType(ListView), const Offset(0, -4000));
        await tester.pumpAndSettle();

        expect(tester.takeException(), isNull);
        expect(find.text('Order summary'), findsOneWidget);
      });
    }
  }

  testWidgets('a pin outside the delivery radius cannot be ordered', (
    tester,
  ) async {
    final api = _CheckoutApi(rx: false);
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, 1.2)],
      ),
      location: const _HereLocation(latitude: 14.65, longitude: 121.02),
    );

    final problem = find.textContaining('Makati branch delivers within 5 km');
    expect(problem, findsOneWidget);

    await tester.tap(find.text('Place order'));
    await tester.pump();
    expect(problem, findsWidgets);
    expect(api.placed, isNull);
  });
}

Future<void> _pump(
  WidgetTester tester,
  _CheckoutApi api, {
  required StoreLocatorState locator,
  LocationService location = const _HereLocation(),
  double width = 390,
  double scale = 1,
}) async {
  tester.view.physicalSize = Size(width, 1600);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  final router = GoRouter(
    routes: [
      GoRoute(path: '/', builder: (_, _) => const CheckoutScreen()),
      GoRoute(
        path: '/orders/:id',
        builder: (_, _) => const Scaffold(body: Text('Order route')),
      ),
    ],
  );
  addTearDown(router.dispose);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        isSignedInProvider.overrideWithValue(true),
        apiClientProvider.overrideWithValue(api),
        activeBranchIdProvider.overrideWith((ref) async => 'B-MAKATI'),
        nearestStoreProvider.overrideWith(() => _FixedLocator(locator)),
        locationServiceProvider.overrideWithValue(location),
        attachmentPickerProvider.overrideWithValue(const _PickedRx()),
      ],
      child: MaterialApp.router(
        theme: buildAppTheme(),
        routerConfig: router,
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(context)
              .copyWith(textScaler: TextScaler.linear(scale)),
          child: child!,
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

class _FixedLocator extends NearestStoreNotifier {
  _FixedLocator(this.fixed);

  final StoreLocatorState fixed;

  @override
  Future<StoreLocatorState> build() async => fixed;
}

class _HereLocation extends LocationService {
  const _HereLocation({this.latitude = 14.55, this.longitude = 121.02});

  final double latitude;
  final double longitude;

  @override
  Future<LocationPermission> permission() async =>
      LocationPermission.whileInUse;

  @override
  Future<Position> currentPosition() async => Position(
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

class _NoLocation extends LocationService {
  const _NoLocation();

  @override
  Future<LocationPermission> permission() async =>
      LocationPermission.deniedForever;

  @override
  Future<Position> currentPosition() async =>
      throw const LocationFailure(LocationIssue.denied);
}

class _PickedRx implements AttachmentPicker {
  const _PickedRx();

  @override
  Future<PickedAttachment?> pickImage() async =>
      PickedAttachment(name: 'rx.jpg', bytes: Uint8List.fromList([1, 2, 3]));

  @override
  Future<PickedAttachment?> pickDocument() async => null;

  @override
  Future<List<PickedAttachment>> pickImages() async => [
    PickedAttachment(name: 'rx.jpg', bytes: Uint8List.fromList([1, 2, 3])),
  ];

  @override
  Future<List<PickedAttachment>> pickDocuments() async => const [];
}

typedef _Placed = ({
  String fulfillment,
  double? latitude,
  double? longitude,
  List<PickedAttachment> prescriptions,
  ({String type, String idNumber, String name})? discountCard,
});

class _CheckoutApi extends ApiClient {
  _CheckoutApi({required this.rx, this.closedUntil, this.seniorPwd = '0.00'})
    : super(baseUrl: 'http://localhost', tokenProvider: () async => null);

  final bool rx;
  final String? closedUntil;
  final String seniorPwd;
  _Placed? placed;

  @override
  Future<CheckoutQuote> checkoutQuote() async => CheckoutQuote.fromJson({
    'items': [
      {
        'id': 'line-1',
        'product_id': 'amox',
        'quantity': 1,
        'selected': true,
        'available': true,
        'unit_price': '100.00',
        'line_total': '100.00',
        'product': {
          'name': 'Amoxicillin 500mg',
          'slug': 'amoxicillin',
          'requires_prescription': rx,
          'store': {'name': 'Makati branch', 'slug': 'makati'},
        },
      },
    ],
    'subtotal': '100.00',
    'senior_pwd_discount': seniorPwd,
    'branch': {
      'id': 'B-MAKATI',
      'name': 'Makati branch',
      'address_line': '10 Rizal Street',
      'city': 'Makati',
    },
    'fulfillment_methods': {
      'delivery': {
        'fee': '49.00',
        'total': '149.00',
        'payment_fees': {},
        'closed_until': closedUntil,
      },
      'pickup': {'fee': '0.00', 'total': '100.00', 'payment_fees': {}},
    },
    'store_totals': [
      {'name': 'Makati branch', 'total': '100.00'},
    ],
    'addresses': [
      {
        'id': 'addr-1',
        'recipient_name': 'Ana Buyer',
        'phone': '09171234567',
        'address_line': '1 Mabini Street',
        'city': 'Makati',
        'province': 'Metro Manila',
        'postal_code': '1200',
      },
    ],
    'payment_methods': {
      'cod': {'available': true, 'fee': '0.00'},
    },
  });

  @override
  Future<PlacedOrder> placeOrder({
    required Address address,
    required String paymentMethod,
    required String clientRequestId,
    String fulfillmentMethod = 'delivery',
    double? deliveryLatitude,
    double? deliveryLongitude,
    List<PickedAttachment> prescriptions = const [],
    ({String type, String idNumber, String name})? discountCard,
    String? note,
    String? successUrl,
    String? cancelUrl,
  }) async {
    placed = (
      fulfillment: fulfillmentMethod,
      latitude: deliveryLatitude,
      longitude: deliveryLongitude,
      prescriptions: prescriptions,
      discountCard: discountCard,
    );
    return PlacedOrder.fromJson({'id': 'order-1', 'number': 'JM-1'});
  }

  @override
  Future<Cart> cart() async => Cart.empty;
}
