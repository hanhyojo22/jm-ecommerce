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
  testWidgets(
    'an Rx order needs a prescription, and without location it is picked up',
    (tester) async {
      final api = _CheckoutApi(rx: true);
      await _pump(
        tester,
        api,
        locator: const StoreLocatorState(
          branches: [BranchDistance(_branch, null)],
          issue: LocationIssue.denied,
        ),
      );

      expect(find.text('Pickup · Free'), findsOneWidget);
      expect(
        find.text('Turn on location to get delivery. Pickup is available.'),
        findsOneWidget,
      );
      expect(find.text('Pay at the counter'), findsOneWidget);

      await tester.tap(find.text('Place order'));
      await tester.pump();
      expect(find.text('Add a photo of your prescription.'), findsOneWidget);
      expect(api.placed, isNull);

      await tester.ensureVisible(find.text('Add photo'));
      await tester.tap(find.text('Add photo'));
      await tester.pumpAndSettle();
      expect(find.text('Added: rx.jpg'), findsOneWidget);
      tester
          .state<ScaffoldMessengerState>(find.byType(ScaffoldMessenger))
          .removeCurrentSnackBar();
      await tester.pumpAndSettle();

      await tester.tap(find.text('Place order'));
      await tester.pumpAndSettle();

      expect(api.placed!.fulfillment, 'pickup');
      expect(api.placed!.prescription!.name, 'rx.jpg');
      expect(api.placed!.latitude, isNull);
      expect(find.text('Order route'), findsOneWidget);
    },
  );

  testWidgets(
    'a delivery inside the radius sends the phone location and adds the fee',
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
      expect(api.placed!.prescription, isNull);
    },
  );
}

Future<void> _pump(
  WidgetTester tester,
  _CheckoutApi api, {
  required StoreLocatorState locator,
}) async {
  tester.view.physicalSize = const Size(390, 1600);
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
        apiClientProvider.overrideWithValue(api),
        activeBranchIdProvider.overrideWith((ref) async => 'B-MAKATI'),
        nearestStoreProvider.overrideWith(() => _FixedLocator(locator)),
        locationServiceProvider.overrideWithValue(const _HereLocation()),
        attachmentPickerProvider.overrideWithValue(const _PickedRx()),
      ],
      child: MaterialApp.router(theme: buildAppTheme(), routerConfig: router),
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
  const _HereLocation();

  @override
  Future<Position> currentPosition() async => Position(
    latitude: 14.55,
    longitude: 121.02,
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

class _PickedRx implements AttachmentPicker {
  const _PickedRx();

  @override
  Future<PickedAttachment?> pickImage() async =>
      PickedAttachment(name: 'rx.jpg', bytes: Uint8List.fromList([1, 2, 3]));

  @override
  Future<PickedAttachment?> pickDocument() async => null;
}

typedef _Placed = ({
  String fulfillment,
  double? latitude,
  double? longitude,
  PickedAttachment? prescription,
});

class _CheckoutApi extends ApiClient {
  _CheckoutApi({required this.rx})
    : super(baseUrl: 'http://localhost', tokenProvider: () async => null);

  final bool rx;
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
    'branch': {
      'id': 'B-MAKATI',
      'name': 'Makati branch',
      'address_line': '10 Rizal Street',
      'city': 'Makati',
    },
    'fulfillment_methods': {
      'delivery': {'fee': '49.00', 'total': '149.00', 'payment_fees': {}},
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
    PickedAttachment? prescription,
    String? note,
    String? successUrl,
    String? cancelUrl,
  }) async {
    placed = (
      fulfillment: fulfillmentMethod,
      latitude: deliveryLatitude,
      longitude: deliveryLongitude,
      prescription: prescription,
    );
    return PlacedOrder.fromJson({'id': 'order-1', 'number': 'JM-1'});
  }

  @override
  Future<Cart> cart() async => Cart.empty;
}
