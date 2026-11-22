import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/screens/checkout_success_screen.dart';
import 'package:jmmart/src/theme.dart';

void main() {
  testWidgets(
    'order actions are available before the illustrated celebration finishes',
    (tester) async {
      await _pump(tester, _details(), settle: false);
      expect(find.text('Order placed'), findsOneWidget);
      expect(find.text('Order #EP-20418'), findsOneWidget);
      expect(find.text('\u20b1149.00'), findsOneWidget);
      expect(
        tester
            .widget<FilledButton>(
              find.widgetWithText(FilledButton, 'View order'),
            )
            .onPressed,
        isNotNull,
      );
      await tester.tap(find.text('View order'));
      await tester.pumpAndSettle();
      expect(find.text('Order order-1'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('the image settles once and reduced motion finishes in 150ms', (
    tester,
  ) async {
    await _pump(tester, _details(), reduced: true, settle: false);
    await tester.pump(const Duration(milliseconds: 160));
    await tester.pump();
    expect(tester.binding.hasScheduledFrame, isFalse);
    expect(find.text('Order placed'), findsOneWidget);
    await tester.pump(const Duration(seconds: 2));
    expect(tester.binding.hasScheduledFrame, isFalse);
  });

  testWidgets(
    'the confetti finishes once and does not restart while the order stays open',
    (tester) async {
      await _pump(tester, _details(), settle: false);
      await tester.pump(const Duration(milliseconds: 1000));
      expect(tester.binding.hasScheduledFrame, isTrue);
      expect(find.text('View order').hitTestable(), findsOneWidget);
      await tester.pump(const Duration(milliseconds: 900));
      await tester.pumpAndSettle();
      expect(tester.binding.hasScheduledFrame, isFalse);
      await tester.pump(const Duration(seconds: 2));
      expect(tester.binding.hasScheduledFrame, isFalse);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'Back opens the created order and Continue shopping opens the store',
    (tester) async {
      await _pump(tester, _details());
      await tester.binding.handlePopRoute();
      await tester.pumpAndSettle();
      expect(find.text('Order order-1'), findsOneWidget);
      await _pump(tester, _details());
      await tester.tap(find.text('Continue shopping'));
      await tester.pumpAndSettle();
      expect(find.text('Store home'), findsOneWidget);
    },
  );

  testWidgets('pickup and non-prescription orders have accurate details', (
    tester,
  ) async {
    await _pump(tester, _details(pickup: true, prescription: false));
    expect(find.text('Pay at the counter'), findsOneWidget);
    expect(find.text('Pickup · Makati branch'), findsOneWidget);
    expect(find.textContaining('prescription'), findsNothing);
  });

  testWidgets(
    'online payment waits for Continue to payment before opening a provider',
    (tester) async {
      final launched = <String>[];
      const channel = MethodChannel('plugins.flutter.io/url_launcher');
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
          .setMockMethodCallHandler(channel, (call) async {
            if (call.method == 'launch') {
              launched.add((call.arguments as Map)['url'] as String);
            }
            return true;
          });
      addTearDown(
        () => TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
            .setMockMethodCallHandler(channel, null),
      );
      await _pump(tester, _details(online: true));
      expect(find.text('GCash · pending'), findsOneWidget);
      expect(
        find.text('Your order is saved. Complete your payment to continue.'),
        findsOneWidget,
      );
      expect(launched, isEmpty);
      await tester.tap(find.text('Continue to payment'));
      await tester.pumpAndSettle();
      expect(launched, ['https://payments.example.test/order-1']);
      expect(find.text('Order order-1'), findsOneWidget);
    },
  );

  testWidgets(
    'an unavailable payment provider offers the saved order as recovery',
    (tester) async {
      const channel = MethodChannel('plugins.flutter.io/url_launcher');
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
          .setMockMethodCallHandler(channel, (_) async => false);
      addTearDown(
        () => TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
            .setMockMethodCallHandler(channel, null),
      );
      await _pump(tester, _details(online: true));
      await tester.tap(find.text('Continue to payment'));
      await tester.pumpAndSettle();
      expect(find.text('Order placed'), findsOneWidget);
      expect(
        find.text(
          'Could not open payment. Open your order to continue payment.',
        ),
        findsOneWidget,
      );
      await tester.tap(find.text('View order'));
      await tester.pumpAndSettle();
      expect(find.text('Order order-1'), findsOneWidget);
    },
  );

  testWidgets(
    'a payment setup failure is retained without losing the created order',
    (tester) async {
      await _pump(
        tester,
        _details(
          online: true,
          paymentError: 'Payment is temporarily unavailable.',
        ),
      );
      expect(find.text('Order placed'), findsOneWidget);
      expect(find.text('Payment is temporarily unavailable.'), findsOneWidget);
      expect(find.text('Continue to payment'), findsNothing);
      expect(find.text('View order'), findsOneWidget);
    },
  );

  testWidgets('an already paid order is not labelled payment pending', (
    tester,
  ) async {
    await _pump(tester, _details(online: true, paid: true));
    expect(find.text('GCash'), findsOneWidget);
    expect(find.text('GCash · pending'), findsNothing);
    expect(find.text('Continue to payment'), findsNothing);
    expect(find.text('View order'), findsOneWidget);
  });

  for (final (width, height, scale) in [
    (320.0, 844.0, 2.0),
    (390.0, 844.0, 1.0),
    (844.0, 390.0, 1.3),
    (768.0, 1024.0, 1.3),
  ]) {
    for (final brightness in Brightness.values) {
      testWidgets(
        'success fits $width x $height at $scale text in ${brightness.name}',
        (tester) async {
          await _pump(
            tester,
            _details(
              branch: 'Makati branch near the central business district',
            ),
            width: width,
            height: height,
            scale: scale,
            brightness: brightness,
          );
          expect(find.text('View order').hitTestable(), findsOneWidget);
          expect(find.text('Continue shopping').hitTestable(), findsOneWidget);
          expect(tester.takeException(), isNull);
        },
      );
    }
  }
}

CheckoutSuccessDetails _details({
  bool online = false,
  bool paid = false,
  bool pickup = false,
  bool prescription = true,
  String? paymentError,
  String branch = 'Makati branch',
}) => CheckoutSuccessDetails(
  placed: PlacedOrder.fromJson({
    'id': 'order-1',
    'number': 'EP-20418',
    'total': '149.00',
    'prescription': prescription ? {'status': 'pending'} : null,
    if (online)
      'payment': {
        'method': 'gcash',
        'label': 'GCash',
        'status': paid ? 'paid' : 'pending',
      },
    if (online) 'payment_url': 'https://payments.example.test/order-1',
    'payment_error': paymentError,
  }),
  branchName: branch,
  paymentMethod: online ? 'gcash' : 'cod',
  fulfillmentMethod: pickup ? 'pickup' : 'delivery',
  needsPrescription: prescription,
);

Future<void> _pump(
  WidgetTester tester,
  CheckoutSuccessDetails details, {
  double width = 390,
  double height = 844,
  double scale = 1,
  bool reduced = false,
  bool settle = true,
  Brightness brightness = Brightness.light,
}) async {
  await tester.pumpWidget(const SizedBox.shrink());
  tester.view.physicalSize = Size(width, height);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  final router = GoRouter(
    initialLocation: '/checkout/success',
    routes: [
      GoRoute(
        path: '/',
        builder: (_, _) => const Scaffold(body: Text('Store home')),
      ),
      GoRoute(
        path: '/checkout/success',
        builder: (_, _) => CheckoutSuccessScreen(details: details),
      ),
      GoRoute(
        path: '/orders/:id',
        builder: (_, state) =>
            Scaffold(body: Text('Order ${state.pathParameters['id']}')),
      ),
    ],
  );
  addTearDown(router.dispose);
  await tester.pumpWidget(
    MaterialApp.router(
      theme: buildAppTheme(brightness: brightness),
      routerConfig: router,
      builder: (context, child) => MediaQuery(
        data: MediaQuery.of(context).copyWith(
          textScaler: TextScaler.linear(scale),
          disableAnimations: reduced,
        ),
        child: child!,
      ),
    ),
  );
  if (settle) await tester.pumpAndSettle();
}
