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
    expect(_selected('Show banner 1: Everyday care'), findsOneWidget);

    await tester.drag(find.byType(PageView), const Offset(-350, 0));
    await tester.pumpAndSettle();
    expect(_selected('Show banner 2: Free delivery'), findsOneWidget);

    await tester.tap(find.bySemanticsLabel('Show banner 4: Prescription care'));
    await tester.pumpAndSettle();
    expect(_selected('Show banner 4: Prescription care'), findsOneWidget);

    await tester.drag(find.byType(PageView), const Offset(350, 0));
    await tester.pumpAndSettle();
    expect(_selected('Show banner 3: 24/7 branches'), findsOneWidget);
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
        'Care for the whole family. Browse vitamins. Banner 1 of 4.',
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

  testWidgets('each slide invokes its dedicated action callback when set', (
    tester,
  ) async {
    final browsedCategories = <String>[];
    var dealsShown = 0;
    var branchesOpened = 0;

    await tester.pumpWidget(
      MaterialApp(
        theme: buildAppTheme(),
        home: Scaffold(
          body: Padding(
            padding: const EdgeInsets.all(16),
            child: FamilyBannerCarousel(
              onShop: () {},
              onBrowseCategory: browsedCategories.add,
              onShowDeals: () => dealsShown++,
              onOpenBranches: () => branchesOpened++,
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Browse vitamins').hitTestable());
    expect(browsedCategories, ['vitamins-supplements']);

    await tester.tap(find.bySemanticsLabel('Show banner 2: Free delivery'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('See top deals').hitTestable());
    expect(dealsShown, 1);

    await tester.tap(find.bySemanticsLabel('Show banner 3: 24/7 branches'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Find a branch').hitTestable());
    expect(branchesOpened, 1);

    await tester.tap(find.bySemanticsLabel('Show banner 4: Prescription care'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Browse Rx meds').hitTestable());
    expect(browsedCategories, ['vitamins-supplements', 'prescription-medicines']);
  });

  testWidgets('reduced motion navigates without an animation', (tester) async {
    await _pump(tester, reducedMotion: true);
    await tester.tap(find.bySemanticsLabel('Show banner 4: Prescription care'));
    await tester.pump();

    expect(_selected('Show banner 4: Prescription care'), findsOneWidget);
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

            const ctas = [
              'Browse vitamins',
              'See top deals',
              'Find a branch',
              'Browse Rx meds',
            ];
            for (var index = 0; index < 4; index++) {
              tester
                  .widget<PageView>(find.byType(PageView))
                  .controller!
                  .jumpToPage(index);
              await tester.pumpAndSettle();

              expect(
                find.text(ctas[index]).hitTestable(),
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
