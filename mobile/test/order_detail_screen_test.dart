import 'dart:async';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/core/api_exception.dart';
import 'package:jmmart/src/core/attachment_picker.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/providers/checkout_providers.dart';
import 'package:jmmart/src/screens/order_detail_screen.dart';
import 'package:jmmart/src/screens/orders_screen.dart';
import 'package:jmmart/src/theme.dart';
import 'package:jmmart/src/providers/store_locator_providers.dart';

void main() {
  for (final status in ['awaiting_confirmation', 'awaiting_seller']) {
    testWidgets('a customer can ask support to review $status', (tester) async {
      final api = _OrderApi()
        ..status = 'delivered'
        ..caseStatus = status;
      await _pump(tester, api);
      await tester.scrollUntilVisible(find.text('Return/Refund'), 200);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Return/Refund'));
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byType(TextField),
        'The replacement has not arrived. Please review.',
      );
      await tester.ensureVisible(find.text('Ask support to review'));
      await tester.tap(find.text('Ask support to review'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Confirm'));
      await tester.pumpAndSettle();
      expect(api.caseStatus, 'appealed');
      expect(find.text('Support is reviewing your request'), findsOneWidget);
    });
  }

  testWidgets('a customer can accept the pharmacy return offer', (
    tester,
  ) async {
    final api = _OrderApi()
      ..status = 'delivered'
      ..caseStatus = 'offered'
      ..deadline = DateTime.now()
          .add(const Duration(days: 1))
          .toIso8601String();
    await _pump(tester, api);
    await tester.scrollUntilVisible(find.text('Return/Refund'), 200);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Return/Refund'));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Accept solution'));
    await tester.tap(find.text('Accept solution'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Confirm'));
    await tester.pumpAndSettle();
    expect(api.caseStatus, 'awaiting_fulfillment');
    expect(
      find.text('The pharmacy is arranging your solution'),
      findsOneWidget,
    );
  });

  testWidgets('an active order updates without a manual refresh', (
    tester,
  ) async {
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
    final api = _OrderApi();
    await _pump(tester, api);
    api.status = 'shipped';
    await tester.pump(const Duration(seconds: 16));
    await tester.pumpAndSettle();
    expect(find.text('Out for delivery'), findsWidgets);
  });

  testWidgets('a customer can submit a return from the order screen', (
    tester,
  ) async {
    final api = _OrderApi()..status = 'delivered';
    await _pump(tester, api);
    await tester.scrollUntilVisible(find.text('Return/Refund'), 200);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Return/Refund'));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Tell us what happened'),
      'The item arrived damaged in its sealed package.',
    );
    await tester.ensureVisible(find.text('Send request'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Send request'));
    await tester.pumpAndSettle();
    expect(api.returnQuantities, isNull);
    expect(find.text('Choose at least one affected item.'), findsOneWidget);

    await tester.ensureVisible(find.text('0 of 2'));
    await tester.tap(find.text('0 of 2'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('1 of 2').last);
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.textContaining('Add photo'));
    await tester.tap(find.textContaining('Add photo'));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Send request'));
    await tester.tap(find.text('Send request'));
    await tester.pumpAndSettle();
    expect(api.returnQuantities, {'line': 1});
    expect(api.returnPhotos, 1);
    expect(find.text('Waiting for the pharmacy'), findsOneWidget);
  });

  testWidgets('an expired pharmacy offer can no longer be answered', (
    tester,
  ) async {
    final api = _OrderApi()
      ..status = 'delivered'
      ..caseStatus = 'offered';
    await _pump(tester, api);
    await tester.scrollUntilVisible(find.text('Return/Refund'), 200);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Return/Refund'));
    await tester.pumpAndSettle();
    expect(find.text('Accept solution'), findsNothing);
    expect(find.text('Ask support to review'), findsNothing);
    expect(
      find.textContaining('The time to respond has passed'),
      findsOneWidget,
    );
  });

  testWidgets('order help submits a cancellation request before preparation', (
    tester,
  ) async {
    final api = _OrderApi();
    await _pump(tester, api);
    await tester.scrollUntilVisible(find.text('Order help'), 200);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Order help'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Other order help'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Request cancellation').last);
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byType(TextFormField),
      'I selected the wrong medicine and need to cancel.',
    );
    await tester.ensureVisible(find.text('Send request'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Send request'));
    await tester.pumpAndSettle();
    expect(api.issueType, 'cancellation');
    expect(find.text('Your request is waiting for review.'), findsOneWidget);
  });

  testWidgets(
    'a ready pickup order shows the branch steps and its approved prescription',
    (tester) async {
      final api = _OrderApi()
        ..status = 'shipped'
        ..fulfillment = 'pickup'
        ..prescription = {'status': 'approved', 'reason': null};
      await _pump(tester, api);

      expect(find.text('Prescription approved'), findsOneWidget);
      expect(find.text('Ready for pickup'), findsWidgets);
      expect(find.text('Picked up'), findsOneWidget);
      expect(find.text('Pickup at Independent store'), findsOneWidget);
    },
  );

  testWidgets('a delivered order can be ordered again into the cart', (
    tester,
  ) async {
    final api = _OrderApi()..status = 'delivered';
    await _pump(tester, api, routed: true);
    expect(find.text('Order again'), findsOneWidget);

    await tester.tap(find.text('Order again'));
    await tester.pumpAndSettle();

    expect(api.reordered, 'order');
    expect(find.textContaining('doesn’t have Cetirizine 10mg'), findsOneWidget);
    expect(find.text('Cart destination'), findsOneWidget);
  });

  testWidgets('Buy again on a delivered order card refills the cart', (
    tester,
  ) async {
    final api = _OrderApi()..status = 'delivered';
    await _pump(tester, api, routed: true, showList: true);

    await tester.tap(find.text('Buy again'));
    await tester.pumpAndSettle();

    expect(api.reordered, 'order');
    expect(find.text('Cart destination'), findsOneWidget);
  });

  testWidgets('Return/Refund on a delivered order card opens its return form', (
    tester,
  ) async {
    await _pump(
      tester,
      _OrderApi()..status = 'delivered',
      routed: true,
      showList: true,
    );

    await tester.tap(find.text('Return/Refund'));
    await tester.pumpAndSettle();

    expect(
      find.widgetWithText(TextFormField, 'Tell us what happened'),
      findsOneWidget,
    );
  });

  testWidgets('an order not yet delivered has no order again button', (
    tester,
  ) async {
    await _pump(tester, _OrderApi()..status = 'shipped');
    expect(find.text('Order again'), findsNothing);
  });

  testWidgets('an order out for delivery shows the rider with a call button', (
    tester,
  ) async {
    final api = _OrderApi()
      ..status = 'shipped'
      ..rider = {'name': 'Juan Dela Cruz', 'phone': '09171234567'};
    await _pump(tester, api);

    expect(find.text('Your rider'), findsOneWidget);
    expect(find.text('Juan Dela Cruz'), findsOneWidget);
    expect(find.text('09171234567'), findsOneWidget);
    expect(find.widgetWithText(FilledButton, 'Call'), findsOneWidget);
  });

  testWidgets(
    'a pharmacist asking for a clearer photo lets the customer send one',
    (tester) async {
      final api = _OrderApi()
        ..prescription = {
          'status': 'needs_photo',
          'reason': 'The photo is blurry.',
        };
      await _pump(tester, api);

      expect(find.text('Send a clearer prescription photo'), findsOneWidget);
      expect(
        find.text('The pharmacist says: The photo is blurry.'),
        findsOneWidget,
      );

      await tester.tap(find.text('Send new pages'));
      await tester.pumpAndSettle();

      expect(api.resubmitted, 'clear.jpg');
      expect(find.text('Prescription in review'), findsOneWidget);
    },
  );

  testWidgets('a rejected prescription explains why the order was cancelled', (
    tester,
  ) async {
    final api = _OrderApi()
      ..status = 'cancelled'
      ..prescription = {
        'status': 'rejected',
        'reason': 'The prescription has expired.',
      };
    await _pump(tester, api);

    expect(find.text('Prescription not accepted'), findsOneWidget);
    expect(
      find.text('Your order was cancelled: The prescription has expired.'),
      findsOneWidget,
    );
  });

  testWidgets('store links open the registered storefront route', (
    tester,
  ) async {
    await _pump(tester, _OrderApi(), routed: true);
    await tester.tap(find.text('Independent store'));
    await tester.pumpAndSettle();
    expect(find.text('Store destination'), findsOneWidget);
  });

  testWidgets('delivery details remain reachable with large text', (
    tester,
  ) async {
    await _pump(tester, _OrderApi(), scale: 2);
    expect(find.text('Delivery details'), findsOneWidget);
    await tester.tap(find.text('Delivery details'));
    await tester.pumpAndSettle();
    expect(find.text('Close'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('order list store link uses the same storefront route', (
    tester,
  ) async {
    await _pump(tester, _OrderApi(), routed: true, showList: true);
    expect(find.text('Free Returns'), findsNothing);
    await tester.tap(find.text('Independent store'));
    await tester.pumpAndSettle();
    expect(find.text('Store destination'), findsOneWidget);
  });

  testWidgets('footer store shortcut opens the storefront', (tester) async {
    await _pump(tester, _OrderApi(), routed: true);
    await tester.tap(find.text('Visit branch'));
    await tester.pumpAndSettle();
    expect(find.text('Store destination'), findsOneWidget);
  });

  testWidgets(
    'leaving while payment is pending does not access disposed state',
    (tester) async {
      final api = _OrderApi()..payable = true;
      api.pendingPayment = Completer<String?>();
      await _pump(tester, api);
      await tester.tap(find.text('Pay now'));
      await tester.pump();
      await tester.pumpWidget(const SizedBox());
      api.pendingPayment!.complete(null);
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('repeated chat taps start one request and failure recovers', (
    tester,
  ) async {
    final api = _OrderApi()..pendingChat = Completer<Conversation>();
    await _pump(tester, api);
    await tester.ensureVisible(find.text('Chat with pharmacy'));
    await tester.tap(find.text('Chat with pharmacy'));
    await tester.pump();
    await tester.tap(find.text('Chat with pharmacy'));
    await tester.pump();
    expect(api.chats, 1);
    api.pendingChat!.completeError(
      const ApiException(code: 'network', message: 'Could not open chat'),
    );
    await tester.pumpAndSettle();
    expect(find.text('Could not open chat'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'delivered order shows existing return status without unbacked promises',
    (tester) async {
      final api = _OrderApi()
        ..status = 'delivered'
        ..returnStatus = 'awaiting_seller';
      await _pump(tester, api);
      await tester.scrollUntilVisible(
        find.text('Return request: Waiting for pharmacy'),
        180,
      );
      expect(find.text('Return request: Waiting for pharmacy'), findsOneWidget);
      expect(find.text('Free Returns'), findsNothing);
      expect(find.text('7 Days Local Supplier Warranty'), findsNothing);
    },
  );

  testWidgets('receipt confirmation needs approval and supports cancellation', (
    tester,
  ) async {
    final api = _OrderApi()..status = 'shipped';
    await _pump(tester, api);
    await tester.ensureVisible(find.text('Confirm received'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Confirm received'));
    await tester.pumpAndSettle();
    expect(api.confirmations, 0);
    expect(find.byType(AlertDialog), findsOneWidget);
    await tester.tap(find.text('Not yet'));
    await tester.pumpAndSettle();
    expect(api.confirmations, 0);
    await tester.tap(find.text('Confirm received'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Yes, received'));
    await tester.pumpAndSettle();
    expect(api.confirmations, 1);
  });

  testWidgets('missing payment checkout URL gives recovery feedback', (
    tester,
  ) async {
    await _pump(tester, _OrderApi()..payable = true);
    await tester.tap(find.text('Pay now'));
    await tester.pumpAndSettle();
    expect(
      find.text('Could not open payment. Please try again.'),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('failed refresh keeps the order and offers retry', (
    tester,
  ) async {
    final api = _OrderApi();
    await _pump(tester, api);
    api.failRead = true;
    await tester.tap(find.byTooltip('Refresh'));
    await tester.pumpAndSettle();
    expect(find.text('Everyday shirt'), findsOneWidget);
    expect(find.text('Could not refresh this order.'), findsOneWidget);
    api.failRead = false;
    await tester.tap(find.text('Retry'));
    await tester.pumpAndSettle();
    expect(find.text('Could not refresh this order.'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  for (final dark in [false, true]) {
    testWidgets(
      'order help fits a narrow landscape screen with large text ($dark)',
      (tester) async {
        await _pump(
          tester,
          _OrderApi(),
          width: 568,
          height: 320,
          scale: 2,
          dark: dark,
        );
        await tester.scrollUntilVisible(find.text('Order help'), 160);
        // ensureVisible jumps the scroll; lay out before tapping the new spot.
        await tester.pump();
        await tester.tap(find.text('Order help'));
        await tester.pumpAndSettle();
        expect(find.text('Send request'), findsOneWidget);
        await tester.tap(find.byTooltip('Back'));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
      },
    );
  }
}

Future<void> _pump(
  WidgetTester tester,
  _OrderApi api, {
  bool routed = false,
  bool showList = false,
  double scale = 1,
  double width = 390,
  double height = 844,
  bool dark = false,
}) async {
  tester.view.physicalSize = Size(width, height);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  final theme = buildAppTheme(
    brightness: dark ? Brightness.dark : Brightness.light,
  );
  Widget builder(BuildContext context, Widget? child) => MediaQuery(
    data: MediaQuery.of(context).copyWith(textScaler: TextScaler.linear(scale)),
    child: child!,
  );
  final router = routed
      ? GoRouter(
          routes: [
            GoRoute(
              path: '/',
              builder: (context, state) => showList
                  ? const Scaffold(body: OrdersScreen())
                  : const OrderDetailScreen(orderId: 'order'),
            ),
            GoRoute(
              path: '/store/:slug',
              builder: (context, state) =>
                  const Scaffold(body: Text('Store destination')),
            ),
            GoRoute(
              path: '/cart',
              builder: (context, state) =>
                  const Scaffold(body: Text('Cart destination')),
            ),
            GoRoute(
              path: '/orders/:id',
              builder: (context, state) => OrderDetailScreen(
                orderId: state.pathParameters['id']!,
                returnFor: state.uri.queryParameters['return'],
              ),
            ),
          ],
        )
      : null;
  if (router != null) {
    addTearDown(router.dispose);
  }
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        isSignedInProvider.overrideWithValue(true),
        apiClientProvider.overrideWithValue(api),
        activeBranchIdProvider.overrideWith((ref) async => 'B-TEST'),
        attachmentPickerProvider.overrideWithValue(const _ClearPhoto()),
      ],
      child: router == null
          ? MaterialApp(
              theme: theme,
              builder: builder,
              home: const OrderDetailScreen(orderId: 'order'),
            )
          : MaterialApp.router(
              theme: theme,
              builder: builder,
              routerConfig: router,
            ),
    ),
  );
  await tester.pumpAndSettle();
}

class _OrderApi extends ApiClient {
  _OrderApi()
    : super(baseUrl: 'http://localhost', tokenProvider: () async => null);
  String status = 'pending';
  bool payable = false;
  bool failRead = false;
  int confirmations = 0;
  int chats = 0;
  String? returnStatus;
  Completer<String?>? pendingPayment;
  String fulfillment = 'delivery';
  Map<String, dynamic>? prescription;
  Map<String, dynamic>? rider;
  String? reordered;
  String? issueType;
  String? caseStatus;
  Map<String, int>? returnQuantities;
  int returnPhotos = 0;
  String deadline = '2020-01-01T00:00:00Z';

  @override
  Future<Map<String, dynamic>> returnRequest(String id) async => {
    'id': id,
    'status': caseStatus,
    'details': 'The item arrived damaged.',
    'can_respond': true,
    'deadline': deadline,
    'events': [],
  };

  @override
  Future<Map<String, dynamic>> updateReturnRequest(
    String id,
    String action, {
    String? note,
    PickedAttachment? proof,
  }) async {
    caseStatus = action == 'appeal' ? 'appealed' : 'awaiting_fulfillment';
    return returnRequest(id);
  }

  @override
  Future<void> openOrderIssue(
    String sellerOrderId,
    String type,
    String details,
  ) async {
    issueType = type;
  }

  @override
  Future<Map<String, dynamic>> openReturnRequest(
    String sellerOrderId, {
    required String reason,
    required String remedy,
    required String details,
    required Map<String, int> quantities,
    List<PickedAttachment> photos = const [],
  }) async {
    returnQuantities = Map.of(quantities);
    returnPhotos = photos.length;
    return {
      'id': 'request',
      'status': 'awaiting_seller',
      'details': details,
      'can_respond': true,
      'events': [],
    };
  }

  @override
  Future<({Cart cart, List<String> skipped})> reorder(
    String orderId, {
    String? branch,
  }) async {
    reordered = orderId;
    return (cart: Cart.empty, skipped: ['Cetirizine 10mg']);
  }

  String? resubmitted;

  @override
  Future<OrderDetail> resubmitPrescription(
    String orderId,
    List<PickedAttachment> prescriptions,
  ) async {
    resubmitted = prescriptions.single.name;
    this.prescription = {'status': 'pending', 'reason': null};
    return order(orderId);
  }

  Completer<Conversation>? pendingChat;

  @override
  Future<OrderDetail> order(String id) async {
    if (failRead) {
      throw const ApiException(code: 'network', message: 'Offline');
    }
    return OrderDetail.fromJson({
      'id': id,
      'number': 'JM-1001',
      'short_number': '#1001',
      'status': status,
      'created_at': '2026-11-08T10:00:00Z',
      'subtotal': '1998.00',
      'shipping_amount': '0.00',
      'total': '1998.00',
      'can_pay': payable,
      'fulfillment_method': fulfillment,
      'prescription': prescription,
      'delivery': {
        'recipient_name': 'Test buyer',
        'phone': '09000000000',
        'address_line': 'Test street',
        'city': 'Test city',
        'province': 'Test province',
        'postal_code': '1000',
      },
      'seller_orders': [
        {
          'id': 'seller-order',
          'order': {'id': id},
          'status': status,
          'can_confirm_receipt': status == 'shipped',
          'rider': rider,
          if (caseStatus != null)
            'latest_return': {'id': 'request', 'status': caseStatus}
          else if (returnStatus != null)
            'latest_return': {'status': returnStatus},
          'subtotal': '1998.00',
          'shipping_amount': '0.00',
          'total': '1998.00',
          'store': {
            'id': 'store',
            'name': 'Independent store',
            'slug': 'independent',
          },
          'items': [
            {
              'id': 'line',
              'product_name': 'Everyday shirt',
              'unit_price': '999.00',
              'quantity': 2,
              'line_total': '1998.00',
            },
          ],
        },
      ],
    });
  }

  @override
  Future<OrdersResult> orders({String tab = 'all', String? search}) async =>
      OrdersResult(
        orders: (await order('order')).sellerOrders,
        tabCounts: const {},
      );

  @override
  Future<SellerOrder> confirmReceived(String sellerOrderId) async {
    confirmations++;
    status = 'delivered';
    return (await order('order')).sellerOrders.single;
  }

  @override
  Future<String?> payOrder(
    String id, {
    String? successUrl,
    String? cancelUrl,
  }) async => pendingPayment == null ? null : await pendingPayment!.future;

  @override
  Future<Conversation> startConversation({
    String? productId,
    String? sellerOrderId,
    String? branch,
  }) {
    chats++;
    return pendingChat!.future;
  }
}

class _ClearPhoto implements AttachmentPicker {
  const _ClearPhoto();

  @override
  Future<PickedAttachment?> takePhoto() async => PickedAttachment(
    name: 'camera.jpg',
    bytes: Uint8List.fromList([4, 5, 6]),
  );

  @override
  Future<PickedAttachment?> pickImage() async =>
      PickedAttachment(name: 'clear.jpg', bytes: Uint8List.fromList([1, 2, 3]));

  @override
  Future<PickedAttachment?> pickDocument() async => null;

  @override
  Future<List<PickedAttachment>> pickImages() async => [
    PickedAttachment(name: 'clear.jpg', bytes: Uint8List.fromList([1, 2, 3])),
  ];

  @override
  Future<List<PickedAttachment>> pickDocuments() async => const [];
}
