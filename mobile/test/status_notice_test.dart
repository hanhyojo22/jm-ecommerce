import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/api_exception.dart';
import 'package:jmmart/src/theme.dart';
import 'package:jmmart/src/widgets/status_notice.dart';
import 'package:phosphor_icons/phosphor_icons.dart';

void main() {
  testWidgets(
    'success uses the minimal ink pill without a close button, respects bottomInset, and expires',
    (tester) async {
      var taps = 0;
      final key = GlobalKey<ScaffoldState>();
      await _pump(tester, key, onTap: () => taps++);
      AppNotice.of(
        key.currentContext!,
        bottomInset: 64,
      ).show('Your review was published.', kind: NoticeKind.success);
      await tester.pumpAndSettle();
      expect(find.byType(StatusNoticeCard), findsOneWidget);
      expect(find.byIcon(PhosphorIconsRegular.checkCircle), findsOneWidget);
      expect(find.byTooltip('Dismiss message'), findsNothing);
      expect(find.text('Your review was published.'), findsOneWidget);
      expect(find.byType(Dialog), findsNothing);
      expect(
        tester.getBottomLeft(find.byType(StatusNoticeCard)).dy,
        lessThan(540),
      );
      await tester.tap(find.text('Keep shopping'));
      expect(taps, 1);
      await tester.pump(const Duration(seconds: 4));
      await tester.pumpAndSettle();
      expect(find.text('Your review was published.'), findsNothing);
    },
  );

  testWidgets('Undo renders arrowCounterClockwise inline and runs action once', (
    tester,
  ) async {
    var undos = 0;
    final key = GlobalKey<ScaffoldState>();
    await _pump(tester, key);
    AppNotice.of(key.currentContext!).show(
      'Removed Biogesic 500mg Tablet',
      kind: NoticeKind.info,
      actionLabel: 'Undo',
      onAction: () => undos++,
    );
    await tester.pumpAndSettle();
    expect(
      find.byIcon(PhosphorIconsRegular.arrowCounterClockwise),
      findsOneWidget,
    );
    await tester.tap(find.text('Undo'));
    await tester.pumpAndSettle();
    expect(undos, 1);
    expect(find.byType(StatusNoticeCard), findsNothing);
  });

  testWidgets('RefreshNoticeStrip displays warning icon and runs action', (
    tester,
  ) async {
    var retries = 0;
    await tester.pumpWidget(
      MaterialApp(
        theme: buildAppTheme(),
        home: Scaffold(
          body: RefreshNoticeStrip(
            message: 'Could not refresh products.',
            actionLabel: 'Try again',
            onAction: () => retries++,
          ),
        ),
      ),
    );
    expect(find.text('Could not refresh products.'), findsOneWidget);
    expect(find.byIcon(PhosphorIconsRegular.warningCircle), findsOneWidget);
    await tester.tap(find.text('Try again'));
    expect(retries, 1);
  });

  testWidgets(
    'Retry runs once before the error expires without leaving the page',
    (tester) async {
      var retries = 0;
      final key = GlobalKey<ScaffoldState>();
      await _pump(tester, key);
      AppNotice.of(key.currentContext!)
          .show('Could not send your message.', onAction: () => retries++);
      await tester.pumpAndSettle();
      await tester.pump(const Duration(seconds: 1));
      expect(find.byIcon(PhosphorIconsRegular.warningCircle), findsOneWidget);
      await tester.tap(find.text('Retry'));
      await tester.pumpAndSettle();
      expect(retries, 1);
      expect(find.byType(StatusNoticeCard), findsNothing);
      expect(find.text('Keep shopping'), findsOneWidget);
    },
  );

  testWidgets('a failed retry keeps its error and recovery action available', (
    tester,
  ) async {
    final key = GlobalKey<ScaffoldState>();
    await _pump(tester, key);
    AppNotice.of(key.currentContext!).show(
      'Could not refresh.',
      onAction: () async {
        throw const ApiException(
          code: 'network.error',
          message: 'Still offline.',
        );
      },
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Retry'));
    await tester.pumpAndSettle();
    expect(find.text('Still offline.'), findsOneWidget);
    expect(find.text('Retry'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('a new message replaces the old message instead of queuing', (
    tester,
  ) async {
    final key = GlobalKey<ScaffoldState>();
    await _pump(tester, key);
    final notice = AppNotice.of(key.currentContext!);
    notice.show('Saved.', kind: NoticeKind.success);
    await tester.pumpAndSettle();
    notice.show('Your branch has only two left.', kind: NoticeKind.info);
    notice.show('Your branch has only two left.', kind: NoticeKind.info);
    await tester.pumpAndSettle();
    expect(find.byType(StatusNoticeCard), findsOneWidget);
    expect(find.text('Saved.'), findsNothing);
    await tester.tap(find.byTooltip('Dismiss message'));
    await tester.pumpAndSettle();
    expect(find.byType(StatusNoticeCard), findsNothing);
  });

  testWidgets(
    'error auto-dismisses after three seconds in screen reader mode',
    (tester) async {
      final key = GlobalKey<ScaffoldState>();
      await _pump(tester, key, accessible: true);
      AppNotice.of(key.currentContext!)
          .show('Could not refresh.', onAction: () {});
      await tester.pumpAndSettle();
      await tester.pump(const Duration(seconds: 2));
      expect(find.text('Could not refresh.'), findsOneWidget);
      await tester.pump(const Duration(milliseconds: 1200));
      await tester.pumpAndSettle();
      expect(find.byType(StatusNoticeCard), findsNothing);
    },
  );

  for (final kind in [NoticeKind.error, NoticeKind.info]) {
    testWidgets('$kind dismisses after three seconds without a tap', (
      tester,
    ) async {
      final key = GlobalKey<ScaffoldState>();
      await _pump(tester, key);
      AppNotice.of(key.currentContext!).show('Temporary notice.', kind: kind);
      await tester.pumpAndSettle();
      await tester.pump(const Duration(seconds: 2));
      expect(find.byType(StatusNoticeCard), findsOneWidget);
      await tester.pump(const Duration(milliseconds: 1200));
      await tester.pumpAndSettle();
      expect(find.byType(StatusNoticeCard), findsNothing);
    });
  }

  testWidgets('the card clears the checkout controls', (tester) async {
    final key = GlobalKey<ScaffoldState>();
    await _pump(tester, key, footer: true);
    AppNotice.of(key.currentContext!).show('Check your delivery details.');
    await tester.pumpAndSettle();
    expect(
      tester.getBottomLeft(find.byType(StatusNoticeCard)).dy,
      lessThan(tester.getTopLeft(find.text('Place order')).dy),
    );
    expect(find.text('Place order').hitTestable(), findsOneWidget);
  });

  for (final brightness in Brightness.values) {
    for (final kind in NoticeKind.values) {
      testWidgets(
        '$kind supports large text in $brightness with the keyboard open',
        (tester) async {
          tester.view.physicalSize = const Size(320, 568);
          tester.view.devicePixelRatio = 1;
          addTearDown(tester.view.resetPhysicalSize);
          addTearDown(tester.view.resetDevicePixelRatio);
          final key = GlobalKey<ScaffoldState>();
          await _pump(
            tester,
            key,
            brightness: brightness,
            scale: 2,
            keyboard: true,
          );
          AppNotice.of(key.currentContext!).show(
            'Your request could not be completed. Check your connection and try again.',
            kind: kind,
            onAction: kind == NoticeKind.error ? () {} : null,
          );
          await tester.pumpAndSettle();
          expect(tester.takeException(), isNull);
          expect(find.byType(StatusNoticeCard), findsOneWidget);
          if (kind == NoticeKind.success) {
            await tester.pump(const Duration(seconds: 4));
          } else {
            await tester.tap(find.byTooltip('Dismiss message'));
          }
          await tester.pumpAndSettle();
        },
      );
    }
  }
}

Future<void> _pump(
  WidgetTester tester,
  GlobalKey<ScaffoldState> key, {
  VoidCallback? onTap,
  bool accessible = false,
  bool footer = false,
  bool keyboard = false,
  double scale = 1,
  Brightness brightness = Brightness.light,
}) async {
  await tester.pumpWidget(
    MaterialApp(
      theme: buildAppTheme(brightness: brightness),
      builder: (context, child) => MediaQuery(
        data: MediaQuery.of(context).copyWith(
          accessibleNavigation: accessible,
          textScaler: TextScaler.linear(scale),
          viewInsets: EdgeInsets.only(bottom: keyboard ? 180 : 0),
        ),
        child: child!,
      ),
      home: Scaffold(
        key: key,
        appBar: AppBar(title: const Text('Shop')),
        body: Center(
          child: TextButton(
            onPressed: onTap ?? () {},
            child: const Text('Keep shopping'),
          ),
        ),
        bottomNavigationBar: footer
            ? SafeArea(
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: FilledButton(
                    onPressed: () {},
                    child: const Text('Place order'),
                  ),
                ),
              )
            : null,
      ),
    ),
  );
}
