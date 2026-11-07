import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/api_exception.dart';
import 'package:jmmart/src/widgets/common.dart';

Widget _view(Object error) =>
    MaterialApp(home: Scaffold(body: ErrorView(error: error)));

void main() {
  testWidgets('offers sign out when the API refuses the session', (
    tester,
  ) async {
    await tester.pumpWidget(
      _view(
        const ApiException(
          code: 'auth.unauthenticated',
          message: 'Sign in to continue.',
          statusCode: 401,
        ),
      ),
    );

    expect(find.text('Sign out'), findsOneWidget);
  });

  testWidgets('offers sign out when the Clerk email is unverified', (
    tester,
  ) async {
    await tester.pumpWidget(
      _view(
        const ApiException(
          code: 'auth.email_unverified',
          message: 'Verify your email address to continue.',
          statusCode: 403,
        ),
      ),
    );

    expect(find.text('Verify your email address to continue.'), findsOneWidget);
    expect(find.text('Sign out'), findsOneWidget);
  });

  testWidgets('does not offer sign out for ordinary failures', (tester) async {
    await tester.pumpWidget(
      _view(
        const ApiException(
          code: 'request.failed',
          message: 'Server error.',
          statusCode: 500,
        ),
      ),
    );

    expect(find.text('Sign out'), findsNothing);
  });
}
