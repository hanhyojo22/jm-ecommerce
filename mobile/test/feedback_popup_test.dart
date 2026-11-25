import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/theme.dart';
import 'package:jmmart/src/widgets/feedback_popup.dart';

void main() {
  testWidgets('startup feedback waits for the app navigator', (tester) async {
    final key = GlobalKey<NavigatorState>();
    var reported = false;
    await tester.pumpWidget(
      MaterialApp(
        navigatorKey: key,
        builder: (context, child) {
          if (!reported) {
            reported = true;
            AppFeedback.of(
              context,
              navigatorKey: key,
            ).show('Sign-in is temporarily unavailable.');
          }
          return child!;
        },
        home: const Scaffold(body: Text('Home')),
      ),
    );
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    expect(find.text('Sign-in is temporarily unavailable.'), findsOneWidget);
  });
  testWidgets('success shows an icon and closes automatically', (tester) async {
    final key = GlobalKey<NavigatorState>();
    await _pump(tester, key);
    AppFeedback.of(key.currentContext!)
        .show('Your review was published.', kind: FeedbackKind.success);
    await tester.pumpAndSettle();
    expect(find.byType(FeedbackPopup), findsOneWidget);
    expect(find.byIcon(Icons.check_rounded), findsOneWidget);
    expect(find.text('Your review was published.'), findsOneWidget);
    expect(find.byType(SnackBar), findsNothing);
    await tester.pump(const Duration(seconds: 3));
    await tester.pumpAndSettle();
    expect(find.byType(FeedbackPopup), findsNothing);
    expect(find.text('Home'), findsOneWidget);
  });

  testWidgets('errors remain until dismissed', (tester) async {
    final key = GlobalKey<NavigatorState>();
    await _pump(tester, key);
    AppFeedback.of(key.currentContext!)
        .show('Check your connection and try again.');
    await tester.pumpAndSettle();
    await tester.pump(const Duration(seconds: 10));
    expect(find.byType(FeedbackPopup), findsOneWidget);
    await tester.tap(find.text('Got it'));
    await tester.pumpAndSettle();
    expect(find.byType(FeedbackPopup), findsNothing);
    expect(find.text('Home'), findsOneWidget);
  });

  testWidgets('system back dismisses feedback without leaving the page', (
    tester,
  ) async {
    final key = GlobalKey<NavigatorState>();
    await _pump(tester, key);
    AppFeedback.of(key.currentContext!).show('Could not save.');
    await tester.pumpAndSettle();
    await tester.binding.handlePopRoute();
    await tester.pumpAndSettle();
    expect(find.byType(FeedbackPopup), findsNothing);
    expect(find.text('Home'), findsOneWidget);
  });

  testWidgets('an old success timer cannot pop a newer page', (tester) async {
    final key = GlobalKey<NavigatorState>();
    await _pump(tester, key);
    AppFeedback.of(key.currentContext!)
        .show('Saved.', kind: FeedbackKind.success);
    await tester.pumpAndSettle();
    key.currentState!.push(
      MaterialPageRoute<void>(
        builder: (_) => const Scaffold(body: Text('New page')),
      ),
    );
    await tester.pumpAndSettle();
    await tester.pump(const Duration(seconds: 3));
    await tester.pumpAndSettle();
    expect(find.text('New page'), findsOneWidget);
    key.currentState!.pop();
    await tester.pumpAndSettle();
    expect(find.byType(FeedbackPopup), findsNothing);
    expect(find.text('Home'), findsOneWidget);
  });

  testWidgets(
    'a newer error replaces success without being closed by its timer',
    (tester) async {
      final key = GlobalKey<NavigatorState>();
      await _pump(tester, key);
      final feedback = AppFeedback.of(key.currentContext!);
      feedback.show('Saved.', kind: FeedbackKind.success);
      await tester.pumpAndSettle();
      feedback.show('Could not update your address.');
      feedback.show('Could not update your address.');
      await tester.pumpAndSettle();
      await tester.pump(const Duration(seconds: 3));
      expect(find.byType(FeedbackPopup), findsOneWidget);
      expect(find.text('Saved.'), findsNothing);
      expect(find.text('Could not update your address.'), findsOneWidget);
      await tester.tap(find.byTooltip('Close message'));
      await tester.pumpAndSettle();
      expect(key.currentState!.canPop(), isFalse);
    },
  );

  testWidgets('feedback follows navigation without popping the destination', (
    tester,
  ) async {
    final key = GlobalKey<NavigatorState>();
    await _pump(tester, key);
    AppFeedback.of(key.currentContext!)
        .show('Added to your cart.', kind: FeedbackKind.success);
    key.currentState!.push(
      MaterialPageRoute<void>(
        builder: (_) => const Scaffold(body: Text('Cart destination')),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Added to your cart.'), findsOneWidget);
    await tester.pump(const Duration(seconds: 3));
    await tester.pumpAndSettle();
    expect(find.text('Cart destination'), findsOneWidget);
    expect(find.byType(FeedbackPopup), findsNothing);
  });

  testWidgets('screen reader mode keeps a success message until dismissed', (
    tester,
  ) async {
    final key = GlobalKey<NavigatorState>();
    await _pump(tester, key, accessible: true);
    AppFeedback.of(key.currentContext!)
        .show('Profile saved.', kind: FeedbackKind.success);
    await tester.pumpAndSettle();
    await tester.pump(const Duration(seconds: 10));
    expect(find.text('Profile saved.'), findsOneWidget);
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();
    expect(find.byType(FeedbackPopup), findsNothing);
  });

  for (final brightness in Brightness.values) {
    for (final kind in FeedbackKind.values) {
      testWidgets('$kind fits a narrow screen with large text in $brightness', (
        tester,
      ) async {
        tester.view.physicalSize = const Size(320, 568);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        final key = GlobalKey<NavigatorState>();
        await _pump(tester, key, brightness: brightness, scale: 2);
        AppFeedback.of(key.currentContext!).show(
          'Your request could not be completed. Check your connection and try again.',
          kind: kind,
        );
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
        expect(find.byType(FeedbackPopup), findsOneWidget);
        await tester.tap(find.byTooltip('Close message'));
        await tester.pumpAndSettle();
      });
    }
  }
}

Future<void> _pump(
  WidgetTester tester,
  GlobalKey<NavigatorState> key, {
  Brightness brightness = Brightness.light,
  double scale = 1,
  bool accessible = false,
}) async {
  await tester.pumpWidget(
    MaterialApp(
      navigatorKey: key,
      theme: buildAppTheme(brightness: brightness),
      builder: (context, child) => MediaQuery(
        data: MediaQuery.of(context).copyWith(
          textScaler: TextScaler.linear(scale),
          accessibleNavigation: accessible,
        ),
        child: child!,
      ),
      home: const Scaffold(body: Text('Home')),
    ),
  );
}
