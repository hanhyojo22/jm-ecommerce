import 'dart:ui' show SemanticsAction;

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/theme.dart';
import 'package:jmmart/src/widgets/family_banner_carousel.dart';

void main() {
  testWidgets('swiping and indicators select each family banner', (
    tester,
  ) async {
    await _pump(tester);
    expect(_selected('Show banner 1: Family care'), findsOneWidget);

    await tester.drag(find.byType(PageView), const Offset(-350, 0));
    await tester.pumpAndSettle();
    expect(_selected('Show banner 2: Morning joy'), findsOneWidget);

    await tester.tap(find.bySemanticsLabel('Show banner 4: Bedtime comfort'));
    await tester.pumpAndSettle();
    expect(_selected('Show banner 4: Bedtime comfort'), findsOneWidget);

    await tester.drag(find.byType(PageView), const Offset(350, 0));
    await tester.pumpAndSettle();
    expect(_selected('Show banner 3: Family moments'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('banner and indicators expose working accessible actions', (
    tester,
  ) async {
    var shops = 0;
    final semantics = tester.ensureSemantics();
    try {
      await _pump(tester, onShop: () => shops++);

      final banner = find.bySemanticsLabel(
        'Care for the whole family. Shop essentials. Banner 1 of 4.',
      );
      expect(
        tester
            .getSemantics(banner)
            .getSemanticsData()
            .hasAction(SemanticsAction.tap),
        isTrue,
      );
      await tester.tap(banner);
      expect(shops, 1);
      await expectLater(tester, meetsGuideline(androidTapTargetGuideline));
      await expectLater(tester, meetsGuideline(labeledTapTargetGuideline));
    } finally {
      semantics.dispose();
    }
  });

  testWidgets('reduced motion navigates without an animation', (tester) async {
    await _pump(tester, reducedMotion: true);
    await tester.tap(find.bySemanticsLabel('Show banner 4: Bedtime comfort'));
    await tester.pump();

    expect(_selected('Show banner 4: Bedtime comfort'), findsOneWidget);
    expect(tester.widget<PageView>(find.byType(PageView)).controller!.page, 3);
    expect(tester.takeException(), isNull);
  });

  for (final width in [320.0, 844.0]) {
    for (final scale in [1.0, 2.0]) {
      for (final brightness in Brightness.values) {
        testWidgets(
          'banners fit ${width}px at ${scale}x in ${brightness.name}',
          (tester) async {
            await _pump(
              tester,
              width: width,
              scale: scale,
              brightness: brightness,
            );

            for (var index = 0; index < 4; index++) {
              tester
                  .widget<PageView>(find.byType(PageView))
                  .controller!
                  .jumpToPage(index);
              await tester.pumpAndSettle();

              expect(
                find.text('Shop essentials').hitTestable(),
                findsOneWidget,
              );
              expect(tester.takeException(), isNull);
            }
          },
        );
      }
    }
  }
}

Finder _selected(String label) => find.byWidgetPredicate(
  (widget) =>
      widget is Semantics &&
      widget.properties.label == label &&
      widget.properties.selected == true,
);

Future<void> _pump(
  WidgetTester tester, {
  VoidCallback? onShop,
  bool reducedMotion = false,
  double width = 390,
  double scale = 1,
  Brightness brightness = Brightness.light,
}) async {
  tester.view.physicalSize = Size(width, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  await tester.pumpWidget(
    MaterialApp(
      theme: buildAppTheme(brightness: brightness),
      builder: (context, child) => MediaQuery(
        data: MediaQuery.of(context).copyWith(
          disableAnimations: reducedMotion,
          textScaler: TextScaler.linear(scale),
        ),
        child: child!,
      ),
      home: Scaffold(
        body: Padding(
          padding: const EdgeInsets.all(16),
          child: FamilyBannerCarousel(onShop: onShop ?? () {}),
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}
