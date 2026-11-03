import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/app.dart';
import 'package:jmmart/src/theme.dart';

void main() {
  testWidgets('the missing Clerk configuration is explained', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(theme: buildAppTheme(), home: const MissingConfigScreen()),
    );

    expect(find.text('Clerk is not configured'), findsOneWidget);
    expect(find.textContaining('CLERK_PUBLISHABLE_KEY'), findsOneWidget);
  });
}
