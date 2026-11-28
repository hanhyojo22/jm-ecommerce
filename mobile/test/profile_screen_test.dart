import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/core/api_exception.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/screens/profile_screen.dart';

const _googleAccount = Account(
  id: 'buyer-1',
  name: 'Franil',
  email: 'buyer@example.test',
  accountType: 'buyer',
  emailVerified: true,
  isPlatformAdministrator: false,
  profileComplete: false,
);

Future<void> _open(
  WidgetTester tester,
  _ProfileApi api, {
  bool completing = false,
}) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(api)],
      child: MaterialApp(
        home: Builder(
          builder: (context) => Scaffold(
            body: TextButton(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) => Scaffold(
                    body: ProfileForm(
                      account: _googleAccount,
                      completing: completing,
                    ),
                  ),
                ),
              ),
              child: const Text('Open'),
            ),
          ),
        ),
      ),
    ),
  );
  await tester.tap(find.text('Open'));
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('the Google name is kept and the birth date is required', (
    tester,
  ) async {
    final api = _ProfileApi();
    await _open(tester, api, completing: true);

    expect(find.text('Franil'), findsOneWidget);
    expect(find.text('Not now'), findsOneWidget);
    expect(find.text('Delete account'), findsNothing);

    await tester.enterText(
      find.widgetWithText(TextFormField, 'Full name'),
      'Franil Escalica',
    );
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Mobile number'),
      '09171234567',
    );
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    expect(find.text('Choose your birth date.'), findsOneWidget);
    expect(api.saved, isNull);

    await tester.tap(find.widgetWithText(TextFormField, 'Birth date'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('15'));
    await tester.tap(find.text('OK'));
    await tester.pumpAndSettle();
    expect(find.text('Choose your birth date.'), findsNothing);

    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    expect(api.saved?.name, 'Franil Escalica');
    expect(api.saved?.phone, '09171234567');
    expect(api.saved?.birthDate?.day, 15);
    expect(api.saved?.sex, isNull);
  });

  testWidgets('deleting asks first and explains why it was refused', (
    tester,
  ) async {
    final api = _ProfileApi();
    await _open(tester, api);

    await tester.ensureVisible(
      find.widgetWithText(OutlinedButton, 'Delete account'),
    );
    await tester.tap(find.widgetWithText(OutlinedButton, 'Delete account'));
    await tester.pumpAndSettle();
    expect(find.textContaining('Past orders, prescriptions'), findsOneWidget);

    await tester.tap(find.text('Keep account'));
    await tester.pumpAndSettle();
    expect(api.deleteCalls, 0);

    await tester.tap(find.widgetWithText(OutlinedButton, 'Delete account'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilledButton, 'Delete account'));
    await tester.pumpAndSettle();

    expect(api.deleteCalls, 1);
    expect(find.text('You have an order in progress.'), findsOneWidget);
  });

  testWidgets('selecting sex on the segmented track saves the chosen value', (
    tester,
  ) async {
    final api = _ProfileApi();
    await _open(tester, api, completing: true);

    await tester.enterText(
      find.widgetWithText(TextFormField, 'Full name'),
      'Maria Santos',
    );
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Mobile number'),
      '09171234567',
    );
    await tester.tap(find.widgetWithText(TextFormField, 'Birth date'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('15'));
    await tester.tap(find.text('OK'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Female'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    expect(api.saved?.sex, 'female');
    expect(tester.takeException(), isNull);
  });
}

class _ProfileApi extends ApiClient {
  _ProfileApi()
    : super(baseUrl: 'http://localhost', tokenProvider: () async => null);

  Account? saved;
  int deleteCalls = 0;

  @override
  Future<Account> me() async => _googleAccount;

  @override
  Future<Account> updateProfile({
    required String name,
    required DateTime birthDate,
    required String phone,
    String? sex,
  }) async {
    return saved = Account(
      id: 'buyer-1',
      name: name,
      email: 'buyer@example.test',
      accountType: 'buyer',
      emailVerified: true,
      isPlatformAdministrator: false,
      birthDate: birthDate,
      phone: phone,
      sex: sex,
    );
  }

  @override
  Future<void> deleteAccount() async {
    deleteCalls++;
    throw const ApiException(
      code: 'validation.failed',
      message: 'You have an order in progress.',
    );
  }
}
