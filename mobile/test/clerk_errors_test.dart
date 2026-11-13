import 'package:clerk_auth/clerk_auth.dart' as clerk;
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/app.dart';

void main() {
  testWidgets(
    'wrapped Clerk connection failures show a short connection notice',
    (tester) async {
      final error = clerk.ClerkError.external(
        'ClientException with SocketException: Connection timed out '
        '(OS Error: Connection timed out, errno = 110), '
        'uri=https://example.clerk.accounts.dev/v1/client/sessions/sess_x/tokens',
      );
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (context) => TextButton(
                onPressed: () => showClerkError(context, error),
                child: const Text('Report connection error'),
              ),
            ),
          ),
        ),
      );

      await tester.tap(find.text('Report connection error'));
      await tester.pump();

      expect(tester.takeException(), isNull);
      expect(find.text(clerkOfflineMessage), findsOneWidget);
      expect(find.textContaining('SocketException'), findsNothing);
      expect(find.textContaining('sess_x'), findsNothing);
    },
  );

  test('Clerk network failures are recognised as being offline', () {
    // The exact error from a phone that lost DNS mid-session.
    expect(
      isClerkNetworkFailure(
        "ClientException with SocketException: Failed host lookup: 'promoted-koala-1363.clerk.accounts.dev' "
        '(OS Error: No address associated with hostname, errno = 7), '
        'uri=https://promoted-koala-1363.clerk.accounts.dev/v1/client/sessions/sess_x/tokens',
      ),
      isTrue,
    );
    expect(isClerkNetworkFailure('Connection timed out'), isTrue);
    expect(isClerkNetworkFailure('Network is unreachable'), isTrue);
    expect(
      isClerkNetworkFailure(
        clerk.ClerkError.external(
          'TimeoutException after 0:00:30.000000: Future not completed',
        ).toString(),
      ),
      isTrue,
    );
  });

  test('real auth errors are still shown as Clerk reports them', () {
    expect(isClerkNetworkFailure('Password is incorrect'), isFalse);
    expect(isClerkNetworkFailure('Session expired'), isFalse);
  });
}
