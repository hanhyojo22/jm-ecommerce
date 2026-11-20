import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/misc.dart' show Override;
import 'package:flutter_test/flutter_test.dart';
import 'package:geolocator/geolocator.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/core/location_service.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/providers/store_locator_providers.dart';
import 'package:jmmart/src/screens/addresses_screen.dart';

Future<Address?> _openForm(
  WidgetTester tester, {
  Address? initial,
  List<Override> overrides = const [],
}) async {
  Address? saved;
  await tester.pumpWidget(
    ProviderScope(
      overrides: overrides,
      child: MaterialApp(
        home: Builder(
          builder: (context) => Scaffold(
            body: TextButton(
              onPressed: () async {
                saved = await showModalBottomSheet<Address>(
                  context: context,
                  isScrollControlled: true,
                  builder: (_) => AddressForm(initial: initial),
                );
              },
              child: const Text('Open form'),
            ),
          ),
        ),
      ),
    ),
  );
  await tester.tap(find.text('Open form'));
  await tester.pumpAndSettle();
  return saved;
}

void main() {
  testWidgets(
    'a checkout address can be edited without province or postal code',
    (tester) async {
      Address? saved;
      await tester.pumpWidget(
        ProviderScope(
          child: MaterialApp(
            home: Builder(
              builder: (context) => Scaffold(
                body: TextButton(
                  onPressed: () async {
                    saved = await showModalBottomSheet<Address>(
                      context: context,
                      isScrollControlled: true,
                      builder: (_) => const AddressForm(
                        initial: Address(
                          id: 'address',
                          recipientName: 'Test Customer',
                          phone: '09171234567',
                          addressLine: 'Blue gate, 12 Rizal Street',
                          city: 'Cebu City',
                          province: '',
                          postalCode: '',
                        ),
                      ),
                    );
                  },
                  child: const Text('Edit address'),
                ),
              ),
            ),
          ),
        ),
      );
      await tester.tap(find.text('Edit address'));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Update address'));
      await tester.tap(find.text('Update address'));
      await tester.pumpAndSettle();
      expect(saved?.addressLine, 'Blue gate, 12 Rizal Street');
      expect(saved?.province, '');
      expect(saved?.postalCode, '');
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('Use my current location fills the address fields', (
    tester,
  ) async {
    final api = _LookupApi();
    await _openForm(
      tester,
      overrides: [
        apiClientProvider.overrideWithValue(api),
        locationServiceProvider.overrideWithValue(const _Here()),
      ],
    );

    await tester.enterText(
      find.widgetWithText(TextFormField, 'Province (optional)'),
      'Cebu',
    );
    await tester.tap(find.text('Use my current location'));
    await tester.pumpAndSettle();

    expect(api.asked, (10.316, 123.8912));
    expect(find.text('Osmeña Boulevard, Camputhaw'), findsOneWidget);
    expect(find.text('Cebu City'), findsOneWidget);
    expect(find.text('6000'), findsOneWidget);
    // The lookup found no province, so what the customer typed stays.
    expect(find.text('Cebu'), findsOneWidget);
  });

  testWidgets('typing part of an address offers matching places', (
    tester,
  ) async {
    final api = _LookupApi();
    await _openForm(
      tester,
      overrides: [apiClientProvider.overrideWithValue(api)],
    );

    final street = find.widgetWithText(TextFormField, 'Street address');
    await tester.enterText(street, 'ca');
    await tester.pump(const Duration(milliseconds: 400));
    expect(api.searched, isEmpty, reason: 'two letters are too few to search');

    await tester.enterText(street, 'camput');
    await tester.pump(const Duration(milliseconds: 400));
    await tester.pumpAndSettle();
    expect(api.searched, ['camput']);

    await tester.tap(find.text('Juana Osmeña Extension, Camputhaw, Cebu City'));
    await tester.pumpAndSettle();

    expect(find.text('Juana Osmeña Extension, Camputhaw'), findsOneWidget);
    expect(find.text('Cebu City'), findsOneWidget);
    expect(find.text('6000'), findsOneWidget);
    expect(api.searched, ['camput'], reason: 'picking does not search again');
  });

  testWidgets('a blocked location explains how to turn it on', (tester) async {
    await _openForm(
      tester,
      overrides: [
        apiClientProvider.overrideWithValue(_LookupApi()),
        locationServiceProvider.overrideWithValue(const _Blocked()),
      ],
    );

    await tester.tap(find.text('Use my current location'));
    await tester.pumpAndSettle();

    expect(
      find.text('Location is off for this app. Turn it on in Settings.'),
      findsOneWidget,
    );
  });
}

class _LookupApi extends ApiClient {
  _LookupApi()
    : super(baseUrl: 'http://localhost', tokenProvider: () async => null);

  (double, double)? asked;

  @override
  Future<Map<String, dynamic>> lookupAddress(
    double latitude,
    double longitude,
  ) async {
    asked = (latitude, longitude);
    return {
      'address_line': 'Osmeña Boulevard, Camputhaw',
      'city': 'Cebu City',
      'province': '',
      'postal_code': '6000',
    };
  }

  final List<String> searched = [];

  @override
  Future<List<Map<String, dynamic>>> searchAddresses(String query) async {
    searched.add(query);
    return [
      {
        'label': 'Juana Osmeña Extension, Camputhaw, Cebu City',
        'address_line': 'Juana Osmeña Extension, Camputhaw',
        'city': 'Cebu City',
        'province': '',
        'postal_code': '6000',
      },
    ];
  }
}

class _Here extends LocationService {
  const _Here();

  @override
  Future<LocationPermission> permission() async =>
      LocationPermission.whileInUse;

  @override
  Future<Position> currentPosition({bool fresh = false}) async => Position(
    latitude: 10.316,
    longitude: 123.8912,
    timestamp: DateTime(2026),
    accuracy: 5,
    altitude: 0,
    altitudeAccuracy: 0,
    heading: 0,
    headingAccuracy: 0,
    speed: 0,
    speedAccuracy: 0,
  );
}

class _Blocked extends LocationService {
  const _Blocked();

  @override
  Future<LocationPermission> permission() async =>
      LocationPermission.deniedForever;

  @override
  Future<Position> currentPosition({bool fresh = false}) async =>
      throw const LocationFailure(LocationIssue.deniedForever);
}
