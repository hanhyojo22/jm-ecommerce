import 'dart:async';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:geolocator/geolocator.dart';
import 'package:go_router/go_router.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/core/api_exception.dart';
import 'package:jmmart/src/core/attachment_picker.dart';
import 'package:jmmart/src/core/discount_id_reader.dart';
import 'package:jmmart/src/core/location_service.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/models/store_branch.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/providers/checkout_providers.dart';
import 'package:jmmart/src/providers/store_locator_providers.dart';
import 'package:jmmart/src/screens/checkout_screen.dart';
import 'package:jmmart/src/screens/checkout_success_screen.dart';
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
  const locator = StoreLocatorState(branches: [BranchDistance(_branch, 1.2)]);

  testWidgets('checkout shows the complete order on one page', (tester) async {
    final api = _CheckoutApi(rx: true, online: true, seniorPwd: '20.00');
    await _pump(tester, api, locator: locator);
    expect(find.text('Order summary'), findsWidgets);
    expect(find.text('Deliver to'), findsOneWidget);
    expect(find.text('Items'), findsOneWidget);
    expect(find.text('Amoxicillin 500mg'), findsOneWidget);
    expect(find.text('Prescription'), findsOneWidget);
    expect(find.text('Senior citizen / PWD'), findsOneWidget);
    expect(find.text('Payment method'), findsOneWidget);
    expect(find.text('Place order'), findsOneWidget);
    expect(find.textContaining('Continue to'), findsNothing);
    expect(api.orderAttempts, 0);
  });

  testWidgets('checkout counts units and lets the buyer edit the cart', (
    tester,
  ) async {
    await _pump(tester, _CheckoutApi(rx: false, quantity: 3), locator: locator);
    expect(find.text('Total (3 items)'), findsOneWidget);
    await tester.tap(find.text('Edit cart'));
    await tester.pumpAndSettle();
    expect(find.text('Cart route'), findsOneWidget);
  });

  testWidgets('placing a simple order uses the created order snapshot', (
    tester,
  ) async {
    final api = _CheckoutApi(rx: false)
      ..cartFailure = true
      ..response = PlacedOrder.fromJson({
        'id': 'order-1',
        'number': 'EP-20418',
        'total': '149.00',
      });
    await _pump(tester, api, locator: locator);
    await tester.tap(find.text('Place order'));
    await tester.pumpAndSettle();
    expect(find.text('Order placed'), findsOneWidget);
    expect(find.text('Order #EP-20418'), findsOneWidget);
    expect(api.orderAttempts, 1);
  });

  testWidgets('prescription is requested in a sheet before submission', (
    tester,
  ) async {
    final api = _CheckoutApi(rx: true);
    await _pump(tester, api, locator: locator);
    await tester.tap(find.text('Place order'));
    await tester.pumpAndSettle();
    expect(find.text('Add a photo of your prescription.'), findsOneWidget);
    expect(api.orderAttempts, 0);
    await tester.tap(find.text('Take photos'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Take a photo'));
    await tester.pumpAndSettle();
    expect(find.text('1 page'), findsOneWidget);
    await tester.tap(find.text('Done'));
    await tester.pumpAndSettle();
    expect(find.text('1 page added'), findsOneWidget);
    await tester.tap(find.text('Place order'));
    await tester.pumpAndSettle();
    expect(api.placed?.prescriptions.length, 1);
  });

  testWidgets('discount ID sheet updates total and sends the card', (
    tester,
  ) async {
    final api = _CheckoutApi(rx: false, seniorPwd: '20.00');
    await _pump(tester, api, locator: locator);
    await tester.ensureVisible(find.text('Apply eligible discount'));
    await tester.tap(find.text('Apply eligible discount'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Senior citizen / PWD').last);
    await tester.pumpAndSettle();
    await tester.enterText(
      find.widgetWithText(TextField, 'ID number'),
      'SC-123',
    );
    await tester.enterText(
      find.widgetWithText(TextField, 'Name on the ID'),
      'Ana Buyer',
    );
    await tester.tap(find.text('Done'));
    await tester.pumpAndSettle();
    expect(
      find.text('Add a photo of your senior citizen or PWD ID.'),
      findsOneWidget,
    );
    expect(find.text('₱149.00'), findsWidgets);
    await tester.tap(find.text('Add photo of ID'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Choose from gallery'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Done'));
    await tester.pumpAndSettle();
    expect(find.text('Discount ID selected'), findsOneWidget);
    expect(find.text('₱129.00'), findsWidgets);
    await tester.tap(find.text('Place order'));
    await tester.pumpAndSettle();
    expect(api.placed?.discountCard?.idNumber, 'SC-123');
  });

  testWidgets('the save checkbox sends save_discount_card', (tester) async {
    final api = _CheckoutApi(rx: false, seniorPwd: '20.00');
    await _pump(tester, api, locator: locator);
    await tester.ensureVisible(find.text('Apply eligible discount'));
    await tester.tap(find.text('Apply eligible discount'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Senior citizen / PWD').last);
    await tester.pumpAndSettle();
    await tester.enterText(find.widgetWithText(TextField, 'ID number'), 'SC-9');
    await tester.enterText(
      find.widgetWithText(TextField, 'Name on the ID'),
      'Lola Ana',
    );
    await tester.ensureVisible(find.text('Save this ID for next time'));
    await tester.tap(find.text('Save this ID for next time'));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Add photo of ID'));
    await tester.tap(find.text('Add photo of ID'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Choose from gallery'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Done'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Place order'));
    await tester.pumpAndSettle();
    expect(api.placed?.discountCard?.idNumber, 'SC-9');
    expect(api.placed?.saveDiscountCard, isTrue);
  });

  testWidgets('payment selection changes the displayed total', (tester) async {
    await _pump(
      tester,
      _CheckoutApi(rx: false, online: true),
      locator: locator,
    );
    expect(find.text('₱149.00'), findsWidgets);
    await tester.ensureVisible(find.text('GCash'));
    await tester.tap(find.text('GCash'));
    await tester.pumpAndSettle();
    expect(find.text('₱154.50'), findsWidgets);
    await tester.ensureVisible(find.text('Pickup · Free'));
    await tester.tap(find.text('Pickup · Free'));
    await tester.pumpAndSettle();
    expect(find.text('₱103.50'), findsWidgets);
  });

  testWidgets('missing address opens the address sheet and blocks order', (
    tester,
  ) async {
    final api = _CheckoutApi(rx: false, hasSavedAddresses: false);
    await _pump(tester, api, locator: locator);
    await tester.tap(find.text('Place order'));
    await tester.pumpAndSettle();
    expect(find.text('Delivery address'), findsOneWidget);
    expect(find.text('Enter the recipient name.'), findsOneWidget);
    expect(api.orderAttempts, 0);
  });

  testWidgets('preferred address is shown when no saved address exists', (
    tester,
  ) async {
    final api = _CheckoutApi(
      rx: false,
      hasSavedAddresses: false,
      preferredAddress: const Address(
        recipientName: 'Ana Buyer',
        phone: '09171234567',
        addressLine: '18 Mabini Street',
        barangay: 'Poblacion',
        city: 'Makati',
        province: 'Metro Manila',
        postalCode: '1200',
      ),
    );
    await _pump(tester, api, locator: locator);
    expect(find.textContaining('18 Mabini Street'), findsOneWidget);
    await tester.tap(find.text('Place order'));
    await tester.pumpAndSettle();
    expect(api.placedAddress?.addressLine, '18 Mabini Street');
  });

  testWidgets('unavailable delivery cash blocks ordering, pickup allows it', (
    tester,
  ) async {
    final api = _CheckoutApi(rx: false, cashAvailable: false);
    await _pump(tester, api, locator: locator);
    await tester.tap(find.text('Place order'));
    await tester.pump();
    expect(api.orderAttempts, 0);
    expect(
      find.text('Choose another payment method or switch to pickup.'),
      findsOneWidget,
    );
    await tester.ensureVisible(find.text('Pickup · Free'));
    await tester.tap(find.text('Pickup · Free'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Place order'));
    await tester.pumpAndSettle();
    expect(api.placed?.fulfillment, 'pickup');
  });

  testWidgets('an unconfirmed delivery pin blocks ordering', (tester) async {
    final api = _CheckoutApi(rx: false);
    await _pump(tester, api, locator: locator, location: const _NoLocation());
    await tester.tap(find.text('Place order'));
    await tester.pump();
    expect(
      find.text('Set the delivery pin so the rider can find you.'),
      findsOneWidget,
    );
    expect(api.orderAttempts, 0);
  });

  testWidgets('pending submission cannot be sent twice', (tester) async {
    final api = _CheckoutApi(rx: false)
      ..pendingOrder = Completer<PlacedOrder>();
    await _pump(tester, api, locator: locator);
    await tester.tap(find.text('Place order'));
    await tester.pump();
    expect(api.orderAttempts, 1);
    expect(
      tester.widget<FilledButton>(find.byType(FilledButton).first).onPressed,
      isNull,
    );
    api.pendingOrder!.complete(
      PlacedOrder.fromJson({'id': 'order-1', 'number': 'JM-1'}),
    );
    await tester.pumpAndSettle();
  });

  testWidgets('a failed quote refresh hides Place order until retry succeeds', (
    tester,
  ) async {
    final api = _CheckoutApi(rx: false);
    await _pump(tester, api, locator: locator);
    api.quoteFailure = true;
    ProviderScope.containerOf(tester.element(find.byType(CheckoutScreen)))
        .invalidate(checkoutQuoteProvider);
    await tester.pumpAndSettle();
    expect(find.text('Try again'), findsOneWidget);
    expect(find.text('Place order'), findsNothing);

    api.quoteFailure = false;
    await tester.tap(find.text('Try again'));
    await tester.pumpAndSettle();
    expect(find.text('Place order'), findsOneWidget);
    expect(api.orderAttempts, 0);
  });

  testWidgets('a deleted saved ID reopens the ID sheet for correction', (
    tester,
  ) async {
    final api = _CheckoutApi(
      rx: false,
      seniorPwd: '28.57',
      failSavedCard: true,
      cards: const [
        {
          'id': 'card-1',
          'type': 'pwd',
          'name': 'Lolo Ben',
          'id_number_masked': '•••••7788',
          'has_document': true,
        },
      ],
    );
    await _pump(tester, api, locator: locator);
    await tester.ensureVisible(find.text('Apply eligible discount'));
    await tester.tap(find.text('Apply eligible discount'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Senior citizen / PWD').last);
    await tester.pumpAndSettle();
    expect(find.text('Lolo Ben'), findsOneWidget);
    await tester.tap(find.text('Done'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Place order'));
    await tester.pumpAndSettle();
    expect(find.widgetWithText(TextField, 'ID number'), findsOneWidget);
    expect(find.widgetWithText(TextField, 'Name on the ID'), findsOneWidget);
    expect(api.orderAttempts, 1);
  });

  testWidgets('closed delivery starts with pickup', (tester) async {
    final api = _CheckoutApi(
      rx: false,
      closedUntil: DateTime.now()
          .add(const Duration(hours: 2))
          .toUtc()
          .toIso8601String(),
    );
    await _pump(tester, api, locator: locator);
    expect(find.textContaining('Makati branch is closed.'), findsOneWidget);
    await tester.tap(find.text('Place order'));
    await tester.pumpAndSettle();
    expect(api.placed?.fulfillment, 'pickup');
  });

  testWidgets('delivery outside the branch radius cannot be ordered', (
    tester,
  ) async {
    final api = _CheckoutApi(rx: false);
    await _pump(
      tester,
      api,
      locator: locator,
      location: const _HereLocation(latitude: 14.65, longitude: 121.02),
    );
    expect(
      find.textContaining('Makati branch delivers within 5 km'),
      findsOneWidget,
    );
    await tester.tap(find.text('Place order'));
    await tester.pump();
    expect(api.orderAttempts, 0);
  });

  for (final (width, height, scale) in [
    (320.0, 844.0, 1.0),
    (390.0, 844.0, 2.0),
    (844.0, 390.0, 1.3),
  ]) {
    testWidgets('one-page checkout fits ${width}x$height at ${scale}x text', (
      tester,
    ) async {
      await _pump(
        tester,
        _CheckoutApi(rx: true),
        locator: locator,
        width: width,
        height: height,
        scale: scale,
      );
      expect(find.text('Place order'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  }
}

Future<void> _pump(
  WidgetTester tester,
  _CheckoutApi api, {
  required StoreLocatorState locator,
  LocationService location = const _HereLocation(),
  double width = 390,
  double height = 1600,
  double scale = 1,
  Brightness brightness = Brightness.light,
  String idPhotoText = '',
}) async {
  tester.view.physicalSize = Size(width, height);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  final router = GoRouter(
    routes: [
      GoRoute(path: '/', builder: (_, _) => const CheckoutScreen()),
      GoRoute(
        path: '/cart',
        builder: (_, _) => const Scaffold(body: Text('Cart route')),
      ),
      GoRoute(
        path: '/checkout/success',
        builder: (_, state) => CheckoutSuccessScreen(
          details: state.extra! as CheckoutSuccessDetails,
        ),
      ),
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
        discountIdReaderProvider.overrideWithValue(_IdText(idPhotoText)),
      ],
      child: MaterialApp.router(
        theme: buildAppTheme(brightness: brightness),
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

class _NoLocation extends LocationService {
  const _NoLocation();

  @override
  Future<LocationPermission> permission() async =>
      LocationPermission.deniedForever;

  @override
  Future<Position> currentPosition({bool fresh = false}) async =>
      throw const LocationFailure(LocationIssue.denied);
}

class _IdText implements DiscountIdReader {
  const _IdText(this.text);

  final String text;

  @override
  Future<String> readText(PickedAttachment photo) async => text;
}

class _PickedRx implements AttachmentPicker {
  const _PickedRx();

  @override
  Future<PickedAttachment?> takePhoto() async => PickedAttachment(
    name: 'camera.jpg',
    bytes: Uint8List.fromList([4, 5, 6]),
  );

  @override
  Future<PickedAttachment?> scanCard() async =>
      PickedAttachment(name: 'scan.jpg', bytes: Uint8List.fromList([7, 8, 9]));

  @override
  Future<PickedAttachment> joinCardSides(
    PickedAttachment front,
    PickedAttachment back,
  ) async => PickedAttachment(name: 'front-back.png', bytes: front.bytes);

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
  String? discountCardId,
  bool saveDiscountCard,
  PickedAttachment? discountDocument,
});

class _CheckoutApi extends ApiClient {
  _CheckoutApi({
    required this.rx,
    this.closedUntil,
    this.seniorPwd = '0.00',
    this.cards = const [],
    this.failSavedCard = false,
    this.online = false,
    this.cashAvailable = true,
    this.hasSavedAddresses = true,
    this.preferredAddress,
    this.quantity = 1,
  }) : super(baseUrl: 'http://localhost', tokenProvider: () async => null);

  final bool rx;
  final String? closedUntil;
  final String seniorPwd;
  final List<Map<String, dynamic>> cards;
  final bool failSavedCard;
  final bool online;
  final bool cashAvailable;
  final bool hasSavedAddresses;
  final Address? preferredAddress;
  final int quantity;
  Address? placedAddress;
  int orderAttempts = 0;
  Completer<PlacedOrder>? pendingOrder;
  ApiException? failure;
  bool quoteFailure = false;
  _Placed? placed;
  bool cartFailure = false;
  PlacedOrder? response;

  @override
  Future<CheckoutQuote> checkoutQuote() async => quoteFailure
      ? throw const ApiException(
          code: 'quote.unavailable',
          message: 'Try again later.',
        )
      : CheckoutQuote.fromJson({
          'items': [
            {
              'id': 'line-1',
              'product_id': 'amox',
              'quantity': quantity,
              'selected': true,
              'available': true,
              'unit_price': '100.00',
              'line_total': '${quantity * 100}.00',
              'product': {
                'name': 'Amoxicillin 500mg',
                'slug': 'amoxicillin',
                'requires_prescription': rx,
                'store': {'name': 'Makati branch', 'slug': 'makati'},
              },
            },
          ],
          'subtotal': '${quantity * 100}.00',
          'senior_pwd_discount': seniorPwd,
          'discount_cards': cards,
          'branch': {
            'id': 'B-MAKATI',
            'name': 'Makati branch',
            'address_line': '10 Rizal Street',
            'city': 'Makati',
          },
          'fulfillment_methods': {
            'delivery': {
              'fee': '49.00',
              'total': '${quantity * 100 + 49}.00',
              'payment_fees': {if (online) 'gcash': '5.50'},
              'payment_fees_with_card': {if (online) 'gcash': '4.50'},
              'closed_until': closedUntil,
            },
            'pickup': {
              'fee': '0.00',
              'total': '${quantity * 100}.00',
              'payment_fees': {if (online) 'gcash': '3.50'},
              'payment_fees_with_card': {if (online) 'gcash': '2.50'},
            },
          },
          'store_totals': [
            {'name': 'Makati branch', 'total': '${quantity * 100}.00'},
          ],
          'preferred_address': preferredAddress?.toJson(),
          'addresses': [
            if (hasSavedAddresses)
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
            'cod': {'available': cashAvailable, 'fee': '0.00'},
            if (online) 'gcash': {'available': true, 'fee': '5.50'},
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
    String? discountCardId,
    bool saveDiscountCard = false,
    PickedAttachment? discountDocument,
    String? note,
    String? successUrl,
    String? cancelUrl,
  }) async {
    orderAttempts++;
    if (failure case final failure?) {
      throw failure;
    }
    if (failSavedCard && discountCardId != null) {
      throw const ApiException(
        code: 'resource.not_found',
        message: 'Not found.',
        statusCode: 404,
      );
    }

    placedAddress = address;
    placed = (
      fulfillment: fulfillmentMethod,
      latitude: deliveryLatitude,
      longitude: deliveryLongitude,
      prescriptions: prescriptions,
      discountCard: discountCard,
      discountCardId: discountCardId,
      saveDiscountCard: saveDiscountCard,
      discountDocument: discountDocument,
    );
    return pendingOrder?.future ??
        response ??
        PlacedOrder.fromJson({'id': 'order-1', 'number': 'JM-1'});
  }

  @override
  Future<Cart> cart() async => cartFailure
      ? throw const ApiException(code: 'network_error', message: 'Unavailable')
      : Cart.empty;
}
