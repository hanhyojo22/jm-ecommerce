import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/formatters.dart';

void main() {
  group('addMoney', () {
    test('adds decimal strings without floating-point rounding', () {
      expect(addMoney('0.10', '0.20'), '0.30');
      expect(addMoney('2500.00', '125.50'), '2625.50');
      expect(addMoney('0.00', '0.00'), '0.00');
    });

    test('handles thousands separators and short fractions', () {
      expect(addMoney('2,500.00', '1.00'), '2501.00');
      expect(addMoney('10.5', '0.25'), '10.75');
    });
  });

  test('discountPercent floors the markdown and ignores non-sales', () {
    expect(discountPercent('104.25', '299.00'), 65);
    expect(discountPercent('200.00', '200.00'), 0);
    expect(discountPercent('250.00', '200.00'), 0);
    expect(discountPercent('200.00', null), 0);
  });

  test('initials skip symbols between words', () {
    expect(initials('Trail & Tide'), 'TT');
    expect(initials('JM'), 'J');
    expect(initials('  '), '');
  });
}
