import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/api_exception.dart';
import 'package:jmmart/src/theme.dart';
import 'package:jmmart/src/widgets/status_notice.dart';
import 'package:phosphor_icons/phosphor_icons.dart';

void main() {
  testWidgets('success is nonblocking and expires without an extra tap', (
    tester,
  ) async {
    var taps = 0;
    final key = GlobalKey<ScaffoldState>();
    await _pump(tester, key, onTap: () => taps++);
    AppNotice.of(key.currentContext!)
        .show('Your review was published.', kind: NoticeKind.success);
    await tester.pumpAndSettle();
    expect(find.byIcon(PhosphorIconsRegular.checkCircle), findsOneWidget);
    expect(find.byType(Dialog), findsNothing);
    await tester.tap(find.text('Keep shopping'));
    expect(taps, 1);
    await tester.pump(const Duration(seconds: 4));
    await tester.pumpAndSettle();
    expect(find.byType(StatusNoticeCard), findsNothing);
  });

  testWidgets('failure persists and Retry runs once without leaving the page', (
    tester,
  ) async {
    var retries = 0;
    final key = GlobalKey<ScaffoldState>();
    await _pump(tester, key);
    AppNotice.of(key.currentContext!)
        .show('Could not send your message.', onAction: () => retries++);
    await tester.pumpAndSettle();
    await tester.pump(const Duration(seconds: 10));
    expect(find.byIcon(PhosphorIconsRegular.warningCircle), findsOneWidget);
    await tester.tap(find.text('Retry'));
    await tester.pumpAndSettle();
    expect(retries, 1);
    expect(find.byType(StatusNoticeCard), findsNothing);
    expect(find.text('Keep shopping'), findsOneWidget);
  });

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

  testWidgets('success stays available in screen reader mode', (tester) async {
    final key = GlobalKey<ScaffoldState>();
    await _pump(tester, key, accessible: true);
    AppNotice.of(key.currentContext!)
        .show('Profile saved.', kind: NoticeKind.success);
    await tester.pumpAndSettle();
    await tester.pump(const Duration(seconds: 10));
    expect(find.text('Profile saved.'), findsOneWidget);
    await tester.tap(find.byTooltip('Dismiss message'));
    await tester.pumpAndSettle();
    expect(find.byType(StatusNoticeCard), findsNothing);
  });

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
          await tester.tap(find.byTooltip('Dismiss message'));
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
