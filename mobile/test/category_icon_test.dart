import 'dart:ui' as ui;

import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/screens/catalog_screen.dart';
import 'package:phosphor_icons/phosphor_icons.dart';

void main() {
  testWidgets('pharmacy category artwork loads from the app bundle', (
    tester,
  ) async {
    await tester.runAsync(() async {
      for (final slug in [
        'pain-fever',
        'cough-colds',
        'vitamins-supplements',
        'first-aid',
        'prescription-medicines',
      ]) {
        final asset = await rootBundle.load('assets/categories/$slug.webp');
        final codec = await ui.instantiateImageCodec(
          asset.buffer.asUint8List(asset.offsetInBytes, asset.lengthInBytes),
        );
        final frame = await codec.getNextFrame();

        expect(frame.image.width, greaterThan(0), reason: slug);
        expect(frame.image.height, greaterThan(0), reason: slug);

        frame.image.dispose();
        codec.dispose();
      }
    });
  });

  test('pharmacy categories get icons from words in their names', () {
    expect(
      categoryIcon('Prescription Medicines'),
      PhosphorIconsRegular.prescription,
    );
    expect(
      categoryIcon('Pain & Fever'),
      PhosphorIconsRegular.thermometerSimple,
    );
    expect(categoryIcon('Cough & Colds'), PhosphorIconsRegular.wind);
    expect(categoryIcon('First Aid'), PhosphorIconsRegular.firstAidKit);
    expect(
      categoryIcon('Vitamins & Supplements'),
      PhosphorIconsRegular.sparkle,
    );
    expect(categoryIcon('Babies & Kids'), PhosphorIconsRegular.baby);
  });

  test('words only match whole, so lookalike names fall back to the pill', () {
    expect(categoryIcon('Kidney Care'), PhosphorIconsRegular.pill);
    expect(categoryIcon('IV Fluids'), PhosphorIconsRegular.pill);
    expect(categoryIcon('Seasonal Picks'), PhosphorIconsRegular.pill);
  });
}
