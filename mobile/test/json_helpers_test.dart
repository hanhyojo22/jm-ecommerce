import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/models/models.dart';

void main() {
  test('mapOf and listOf tolerate missing or wrong-shaped JSON', () {
    expect(mapOf(null), isEmpty);
    expect(mapOf('text'), isEmpty);
    expect(mapOf({1: 'a'}), {'1': 'a'});
    expect(listOf(null), isEmpty);
    expect(
      listOf([
        {'id': 1},
        'skip me',
        null,
      ]),
      [
        {'id': 1},
      ],
    );
  });

  test('str keeps strings, stringifies other values, and uses the fallback for null', () {
    expect(str('JM-1001'), 'JM-1001');
    expect(str(42), '42');
    expect(str(null, 'none'), 'none');
  });

  test('integer accepts numbers and numeric strings', () {
    expect(integer(3), 3);
    expect(integer(3.9), 3);
    expect(integer('7'), 7);
    expect(integer('seven', -1), -1);
    expect(integer(null), 0);
  });

  test('decimal accepts numbers and money strings', () {
    expect(decimal(4), 4.0);
    expect(decimal('1998.50'), 1998.5);
    expect(decimal('n/a', 1), 1);
    expect(decimal(null), 0);
  });

  test('boolean understands the forms the API sends', () {
    expect(boolean(true), isTrue);
    expect(boolean(1), isTrue);
    expect(boolean('1'), isTrue);
    expect(boolean('true'), isTrue);
    expect(boolean(0), isFalse);
    expect(boolean('false'), isFalse);
    expect(boolean(null), isFalse);
    expect(boolean(null, true), isTrue);
    expect(boolean('false', true), isFalse);
  });
}
