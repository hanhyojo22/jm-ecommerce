import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/app.dart';

void main() {
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
  });

  test('real auth errors are still shown as Clerk reports them', () {
    expect(isClerkNetworkFailure('Password is incorrect'), isFalse);
    expect(isClerkNetworkFailure('Session expired'), isFalse);
  });
}
