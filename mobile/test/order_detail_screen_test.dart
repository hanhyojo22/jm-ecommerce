import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/core/api_exception.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/screens/order_detail_screen.dart';
import 'package:jmmart/src/screens/orders_screen.dart';
import 'package:jmmart/src/theme.dart';

void main() {
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
    await tester.tap(find.text('Visit Store'));
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
    await tester.ensureVisible(find.text('Chat with Seller'));
    await tester.tap(find.text('Chat with Seller'));
    await tester.pump();
    await tester.tap(find.text('Chat with Seller'));
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
        find.text('Return request: Waiting for store'),
        180,
      );
      expect(find.text('Return request: Waiting for store'), findsOneWidget);
      expect(find.text('Free Returns'), findsNothing);
      expect(find.text('7 Days Local Supplier Warranty'), findsNothing);
    },
  );

  testWidgets('receipt confirmation needs approval and supports cancellation', (
    tester,
  ) async {
    final api = _OrderApi()..status = 'shipped';
    await _pump(tester, api);
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
        await tester.ensureVisible(find.text('Got it'));
        await tester.pump();
        await tester.tap(find.text('Got it'));
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
          ],
        )
      : null;
  if (router != null) {
    addTearDown(router.dispose);
  }
  await tester.pumpWidget(
    ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(api)],
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
          'status': status,
          'can_confirm_receipt': status == 'shipped',
          if (returnStatus != null) 'latest_return': {'status': returnStatus},
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
  }) {
    chats++;
    return pendingChat!.future;
  }
}
