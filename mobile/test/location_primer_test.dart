import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:geolocator/geolocator.dart';
import 'package:jmmart/src/core/location_service.dart';
import 'package:jmmart/src/providers/store_locator_providers.dart';
import 'package:jmmart/src/widgets/store_locator.dart';

class _AskingLocation extends LocationService {
  _AskingLocation(this.status, {this.answer = LocationPermission.whileInUse});

  final LocationPermission status;

  /// What Android's dialog answers; deniedForever with no dialog when blocked.
  final LocationPermission answer;
  int requests = 0;
  int settingsOpened = 0;

  @override
  Future<LocationPermission> permission() async => status;

  @override
  Future<void> openAppSettings() async => settingsOpened++;

  @override
  Future<LocationPermission> requestPermission() async {
    requests++;
    return answer;
  }
}

/// A button that runs [askForLocation]; its last answer lands in [answers].
Future<List<bool>> _pump(
  WidgetTester tester,
  LocationService location, {
  bool automatic = false,
}) async {
  final answers = <bool>[];
  await tester.pumpWidget(
    ProviderScope(
      overrides: [locationServiceProvider.overrideWithValue(location)],
      child: MaterialApp(
        home: Scaffold(
          body: Consumer(
            builder: (context, ref, _) => TextButton(
              onPressed: () async => answers.add(
                await askForLocation(context, ref, automatic: automatic),
              ),
              child: const Text('Ask'),
            ),
          ),
        ),
      ),
    ),
  );
  return answers;
}

Future<void> _ask(WidgetTester tester) async {
  await tester.tap(find.text('Ask'));
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('explains first, then asks Android once the customer agrees', (
    tester,
  ) async {
    final location = _AskingLocation(LocationPermission.denied);
    final answers = await _pump(tester, location);

    await _ask(tester);
    expect(find.text('Find your nearest branch'), findsOneWidget);
    expect(location.requests, 0);

    await tester.tap(find.text('Allow location'));
    await tester.pumpAndSettle();
    expect(location.requests, 1);
    expect(answers, [true]);
  });

  testWidgets('Not now leaves Android\'s dialog unasked', (tester) async {
    final location = _AskingLocation(LocationPermission.denied);
    final answers = await _pump(tester, location);

    await _ask(tester);
    await tester.tap(find.text('Not now'));
    await tester.pumpAndSettle();

    expect(location.requests, 0);
    expect(answers, [false]);
  });

  testWidgets('when Android answers blocked without a dialog, Settings opens', (
    tester,
  ) async {
    // Blocked in an earlier launch: the check still says denied.
    final location = _AskingLocation(
      LocationPermission.denied,
      answer: LocationPermission.deniedForever,
    );
    final answers = await _pump(tester, location);

    await _ask(tester);
    await tester.tap(find.text('Allow location'));
    await tester.pumpAndSettle();

    expect(location.requests, 1);
    expect(location.settingsOpened, 1);
    expect(answers, [false]);
  });

  testWidgets('an automatic prompt shows at most once per launch', (
    tester,
  ) async {
    // The earlier tests already showed the primer in this launch.
    final location = _AskingLocation(LocationPermission.denied);
    final answers = await _pump(tester, location, automatic: true);

    await _ask(tester);

    expect(find.text('Find your nearest branch'), findsNothing);
    expect(answers, [false]);
  });

  testWidgets('once Android blocks the dialog, a tap opens Settings instead', (
    tester,
  ) async {
    final location = _AskingLocation(LocationPermission.deniedForever);
    final answers = await _pump(tester, location);

    await _ask(tester);

    expect(find.text('Find your nearest branch'), findsNothing);
    expect(location.requests, 0);
    expect(location.settingsOpened, 1);
    expect(answers, [false]);
  });
}
