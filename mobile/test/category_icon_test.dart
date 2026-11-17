import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/screens/catalog_screen.dart';
import 'package:phosphor_icons/phosphor_icons.dart';

void main() {
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
