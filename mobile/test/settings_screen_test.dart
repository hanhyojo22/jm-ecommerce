import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/providers/theme_providers.dart';
import 'package:jmmart/src/screens/settings_screen.dart';
import 'package:jmmart/src/theme.dart';
import 'package:jmmart/src/widgets/shell.dart';

void main() {
  test('parseThemeMode handles stored values and falls back to system', () {
    expect(parseThemeMode('light'), ThemeMode.light);
    expect(parseThemeMode('dark'), ThemeMode.dark);
    expect(parseThemeMode('system'), ThemeMode.system);
    expect(parseThemeMode('unknown'), ThemeMode.system);
    expect(parseThemeMode(null), ThemeMode.system);
  });

  test(
    'loadThemeMode reads persisted mode and handles missing files',
    () async {
      final dir = await Directory.systemTemp.createTemp('jmmart_theme_test_');
      addTearDown(() => dir.delete(recursive: true));
      final file = File('${dir.path}/appearance.json');

      expect(await loadThemeMode(file), ThemeMode.system);

      await file.writeAsString('{"theme_mode":"dark"}');
      expect(await loadThemeMode(file), ThemeMode.dark);

      await file.writeAsString('not-json');
      expect(await loadThemeMode(file), ThemeMode.system);
    },
  );

  testWidgets('turning cart reminders off saves and shows the new state', (
    tester,
  ) async {
    final api = _SettingsApi();
    await _pump(tester, api);

    expect(_switch(tester).value, isTrue);

    await tester.tap(find.text('Cart reminders'));
    await tester.pumpAndSettle();

    expect(api.updates, [false]);
    expect(_switch(tester).value, isFalse);
  });

  testWidgets('a failed cart reminders save keeps it on and shows a notice', (
    tester,
  ) async {
    final api = _SettingsApi()..fail = true;
    await _pump(tester, api);

    await tester.tap(find.text('Cart reminders'));
    await tester.pumpAndSettle();

    expect(api.updates, [false]);
    expect(find.text('Could not save. Try again.'), findsOneWidget);
    expect(_switch(tester).value, isTrue);
  });

  testWidgets('appearance selector updates themeModeProvider live', (
    tester,
  ) async {
    final api = _SettingsApi();
    final container = ProviderContainer(
      overrides: [
        isSignedInProvider.overrideWithValue(true),
        apiClientProvider.overrideWithValue(api),
      ],
    );
    addTearDown(container.dispose);

    await tester.pumpWidget(
      UncontrolledProviderScope(
        container: container,
        child: MaterialApp(
          theme: buildAppTheme(),
          darkTheme: buildAppTheme(brightness: Brightness.dark),
          home: const SettingsScreen(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(container.read(themeModeProvider), ThemeMode.system);

    await tester.tap(find.text('Dark'));
    await tester.pumpAndSettle();
    expect(container.read(themeModeProvider), ThemeMode.dark);
    expect(
      find.text('Always use dark navy surfaces for low-light reading'),
      findsOneWidget,
    );

    await tester.tap(find.text('Light'));
    await tester.pumpAndSettle();
    expect(container.read(themeModeProvider), ThemeMode.light);
    expect(find.text('Always use crisp daylight surfaces'), findsOneWidget);
  });

  testWidgets('help center sheet opens FAQs and jumps to pharmacist messages', (
    tester,
  ) async {
    final api = _SettingsApi();
    final container = ProviderContainer(
      overrides: [
        isSignedInProvider.overrideWithValue(true),
        apiClientProvider.overrideWithValue(api),
      ],
    );
    addTearDown(container.dispose);

    await tester.pumpWidget(
      UncontrolledProviderScope(
        container: container,
        child: MaterialApp(
          theme: buildAppTheme(),
          home: const SettingsScreen(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(find.text('Help center & FAQs'), 200);
    await tester.tap(find.text('Help center & FAQs'));
    await tester.pumpAndSettle();

    expect(find.text('Help center'), findsOneWidget);
    expect(find.text('Uploading a prescription'), findsOneWidget);

    await tester.tap(find.text('Message a pharmacist').last);
    await tester.pumpAndSettle();

    expect(container.read(shellTabProvider).index, 2);
  });
}

Switch _switch(WidgetTester tester) =>
    tester.widget<Switch>(find.byType(Switch));

Future<void> _pump(WidgetTester tester, _SettingsApi api) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        isSignedInProvider.overrideWithValue(true),
        apiClientProvider.overrideWithValue(api),
      ],
      child: MaterialApp(
        theme: buildAppTheme(),
        darkTheme: buildAppTheme(brightness: Brightness.dark),
        home: const SettingsScreen(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

class _SettingsApi extends ApiClient {
  _SettingsApi()
    : super(baseUrl: 'http://localhost', tokenProvider: () async => null);

  bool cartReminders = true;
  bool fail = false;
  final List<bool> updates = [];

  Account _account() => Account(
    id: 'buyer-1',
    name: 'QA Buyer',
    email: 'buyer@example.test',
    accountType: 'buyer',
    emailVerified: true,
    isPlatformAdministrator: false,
    cartReminders: cartReminders,
    medicationReminders: true,
    profileComplete: true,
  );

  @override
  Future<Account> me() async => _account();

  @override
  Future<Account> updateCartReminders(bool enabled) async {
    updates.add(enabled);
    if (fail) {
      throw Exception('offline');
    }
    cartReminders = enabled;
    return _account();
  }
}
