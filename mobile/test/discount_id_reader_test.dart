import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/discount_id_reader.dart';

void main() {
  test('reads a labelled OSCA card', () {
    final fields = parseDiscountId('''
Republic of the Philippines
City of Cebu
OFFICE OF THE SENIOR CITIZENS AFFAIRS
Name: JUANA DELA CRUZ
Address: 10 Rizal St., Brgy. Capitol Site
Date of Birth: 01/02/1950 Sex: F
OSCA ID No.: 2023-04521
''');

    expect(fields.number, '2023-04521');
    expect(fields.name, 'JUANA DELA CRUZ');
  });

  test(
    'reads a Unified PWD ID number and a name printed above its caption',
    () {
      final fields = parseDiscountId('''
PERSON WITH DISABILITY
MARIA L. SANTOS
NAME
Type of Disability: Visual
07-2201-000-0001234
''');

      expect(fields.number, '07-2201-000-0001234');
      expect(fields.name, 'MARIA L. SANTOS');
    },
  );

  test('takes the value from the next line when the label stands alone', () {
    final fields = parseDiscountId('''
ID NO.
SC 88123
Name
Pedro Reyes Jr.
''');

    expect(fields.number, '88123');
    expect(fields.name, 'Pedro Reyes Jr.');
  });

  test('returns nothing it cannot find instead of guessing', () {
    final fields = parseDiscountId('''
Republic of the Philippines
Signature of Card Holder
Valid until 2028
''');

    expect(fields.number, isNull);
    expect(fields.name, isNull);
  });
}
