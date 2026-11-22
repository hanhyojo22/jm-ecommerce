import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/startup.dart';
import 'package:jmmart/src/widgets/startup_splash.dart';

const _readyApp = MaterialApp(home: Scaffold(body: Text('Ready to shop')));

void main() {
  testWidgets('a ready app opens after the one-time splash animation', (
    tester,
  ) async {
    await tester.pumpWidget(AppStartup(application: Future.value(_readyApp)));
    await _loadArtwork(tester);
    await tester.pump(const Duration(milliseconds: 800));

    expect(find.byType(StartupSplash), findsOneWidget);
    expect(find.text('Ready to shop'), findsNothing);

    await tester.pump(const Duration(milliseconds: 1400));
    await tester.pump();

    expect(find.text('Ready to shop'), findsOneWidget);
    expect(find.byType(StartupSplash), findsNothing);
    await tester.pump(const Duration(seconds: 2));
    expect(find.text('Ready to shop'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('slow initialization keeps the settled splash until ready', (
    tester,
  ) async {
    final application = Completer<Widget>();
    await tester.pumpWidget(AppStartup(application: application.future));
    await _loadArtwork(tester);
    await tester.pump(const Duration(seconds: 2));
    await tester.pump();

    expect(find.byType(StartupSplash), findsOneWidget);
    expect(find.text('EVERYDAY\nPHARMACY'), findsNothing);
    expect(_brandWordmark, findsOneWidget);
    expect(find.text('Ready to shop'), findsNothing);

    application.complete(_readyApp);
    await tester.pumpAndSettle();

    expect(find.text('Ready to shop'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('initialization failure opens the existing recovery screen', (
    tester,
  ) async {
    final application = Completer<Widget>();
    await tester.pumpWidget(AppStartup(application: application.future));
    application.completeError(StateError('Sign-in service unavailable'));
    await tester.pumpAndSettle();

    expect(find.text('Could not start Everyday Pharmacy'), findsOneWidget);
    expect(find.textContaining('Check your connection'), findsOneWidget);
    expect(find.byType(StartupSplash), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('reduced motion completes without the sunrise delay', (
    tester,
  ) async {
    var completed = false;
    await tester.pumpWidget(
      MaterialApp(
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(context).copyWith(disableAnimations: true),
          child: child!,
        ),
        home: StartupSplash(onComplete: () => completed = true),
      ),
    );
    await tester.pump();

    expect(completed, isTrue);
    expect(_brandWordmark, findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('splash fits a small phone with large text and landscape', (
    tester,
  ) async {
    addTearDown(() => tester.binding.setSurfaceSize(null));
    for (final size in [const Size(320, 568), const Size(740, 320)]) {
      await tester.binding.setSurfaceSize(size);
      await tester.pumpWidget(
        MaterialApp(
          builder: (context, child) => MediaQuery(
            data: MediaQuery.of(context).copyWith(
              disableAnimations: true,
              textScaler: const TextScaler.linear(2),
            ),
            child: child!,
          ),
          home: StartupSplash(key: ValueKey(size), onComplete: () {}),
        ),
      );
      await tester.pump();

      expect(_brandWordmark, findsOneWidget);
      expect(tester.takeException(), isNull);
    }
  });
}

final _brandWordmark = find.byWidgetPredicate(
  (widget) =>
      widget is Image &&
      widget.image is AssetImage &&
      (widget.image as AssetImage).assetName ==
          'assets/branding/everyday-company-logo.png',
);

Future<void> _loadArtwork(WidgetTester tester) async {
  await tester.runAsync(() async {
    await precacheImage(
      const AssetImage('assets/branding/everyday-emblem.webp'),
      tester.element(find.byType(StartupSplash)),
    );
  });
  await tester.pump();
}
