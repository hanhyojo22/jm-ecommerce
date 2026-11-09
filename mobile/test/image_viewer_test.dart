import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/widgets/image_viewer.dart';

void main() {
  double scaleOf(WidgetTester tester) => tester
      .widget<InteractiveViewer>(
        find.byKey(const ValueKey('image-viewer-zoom')),
      )
      .transformationController!
      .value
      .getMaxScaleOnAxis();

  Future<void> doubleTap(WidgetTester tester) async {
    final center = tester.getCenter(
      find.byKey(const ValueKey('image-viewer-gestures')),
    );
    await tester.tapAt(center);
    await tester.pump(const Duration(milliseconds: 60));
    await tester.tapAt(center);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
  }

  testWidgets('double-tap zooms in and a second double-tap zooms back out', (
    tester,
  ) async {
    await tester.pumpWidget(
      const MaterialApp(home: ImageViewer(url: 'http://localhost/photo.png')),
    );
    await tester.pump();

    expect(scaleOf(tester), closeTo(1, 0.01));

    await doubleTap(tester);
    expect(scaleOf(tester), closeTo(ImageViewer.doubleTapScale, 0.01));

    await doubleTap(tester);
    expect(scaleOf(tester), closeTo(1, 0.01));
  });

  testWidgets('pinching zooms in, within the maximum', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(home: ImageViewer(url: 'http://localhost/photo.png')),
    );
    await tester.pump();

    final center = tester.getCenter(
      find.byKey(const ValueKey('image-viewer-gestures')),
    );
    final first = await tester.startGesture(center - const Offset(20, 0));
    final second = await tester.startGesture(center + const Offset(20, 0));
    for (var step = 1; step <= 10; step++) {
      await first.moveTo(center - Offset(20.0 + step * 20, 0));
      await second.moveTo(center + Offset(20.0 + step * 20, 0));
      await tester.pump();
    }
    await first.up();
    await second.up();
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));

    final scale = scaleOf(tester);
    expect(scale, greaterThan(1.5));
    expect(scale, lessThanOrEqualTo(ImageViewer.maxScale));
  });

  testWidgets('the save action only shows when saving is offered', (
    tester,
  ) async {
    await tester.pumpWidget(
      const MaterialApp(home: ImageViewer(url: 'http://localhost/photo.png')),
    );
    await tester.pump();

    expect(find.byTooltip('Close'), findsOneWidget);
    expect(find.byTooltip('Save image'), findsNothing);
  });
}
