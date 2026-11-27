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
import 'package:jmmart/src/widgets/place_fields.dart';

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

Future<void> _pick(WidgetTester tester, String field, String name) async {
  await tester.tap(find.widgetWithText(TextFormField, field));
  await tester.pumpAndSettle();
  await tester.tap(find.widgetWithText(ListTile, name));
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('an older address must be completed before it saves', (
    tester,
  ) async {
    final saved = await _openForm(
      tester,
      overrides: [apiClientProvider.overrideWithValue(_LookupApi())],
      initial: const Address(
        id: 'address',
        recipientName: 'Test Customer',
        phone: '09171234567',
        addressLine: 'Blue gate, 12 Rizal Street',
        city: 'Cebu City',
        province: '',
        postalCode: '',
      ),
    );
    await tester.ensureVisible(find.text('Update address'));
    await tester.tap(find.text('Update address'));
    await tester.pumpAndSettle();

    expect(saved, isNull);
    expect(find.text('Choose the barangay.'), findsOneWidget);
    expect(find.text('Choose the province.'), findsOneWidget);
    expect(find.text('Use a 4-digit postal code.'), findsOneWidget);

    // Fixing a field clears its error without pressing Save again, and a
    // province that has the saved city keeps it.
    await _pick(tester, 'Province', 'Cebu');
    expect(find.text('Choose the province.'), findsNothing);
    expect(find.text('Cebu City'), findsOneWidget);
  });

  testWidgets('each place list narrows to the one before it', (tester) async {
    final api = _LookupApi();
    await _openForm(
      tester,
      overrides: [apiClientProvider.overrideWithValue(api)],
      initial: const Address(
        recipientName: 'Test Customer',
        phone: '09171234567',
        addressLine: '12 Rizal Street',
        barangay: 'Camputhaw',
        city: 'Cebu City',
        province: 'Cebu',
        postalCode: '6000',
      ),
    );

    // Makati is not in Cebu, so the city and barangay empty out.
    await _pick(tester, 'Province', 'Metro Manila');
    expect(find.text('Cebu City'), findsNothing);
    expect(find.text('Camputhaw'), findsNothing);

    await tester.tap(find.widgetWithText(TextFormField, 'City / municipality'));
    await tester.pumpAndSettle();
    expect(find.text('Mandaue City'), findsNothing);
    await tester.tap(find.text('Makati City'));
    await tester.pumpAndSettle();

    await tester.tap(find.widgetWithText(TextFormField, 'Barangay'));
    await tester.pumpAndSettle();
    expect(api.barangaysAsked, [('Metro Manila', 'Makati City')]);
    expect(find.text('Bel-Air'), findsOneWidget);

    // A barangay missing from the list can still be typed.
    await tester.enterText(find.byType(TextField).last, 'New Barangay');
    await tester.pumpAndSettle();
    await tester.tap(find.text('Use “New Barangay”'));
    await tester.pumpAndSettle();
    expect(find.text('New Barangay'), findsOneWidget);
  });

  test('looked-up names take the listed spelling and province', () {
    const provinces = {
      'Cebu': ['Cebu City', 'Mandaue City', 'San Remigio'],
      'Metro Manila': ['Makati City', 'Manila'],
      'Antique': ['San Remigio'],
    };
    final province = TextEditingController(text: 'Metro Manila');
    final city = TextEditingController(text: 'City of Mandaue');

    matchPlaceNames(provinces, province, city);
    expect((province.text, city.text), ('Cebu', 'Mandaue City'));

    // Two provinces have a San Remigio, so the named province stays.
    city.text = 'San Remigio';
    province.text = 'cebu';
    matchPlaceNames(provinces, province, city);
    expect((province.text, city.text), ('Cebu', 'San Remigio'));
  });

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

    await tester.tap(find.text('Use my current location'));
    await tester.pumpAndSettle();

    expect(api.asked, (10.316, 123.8912));
    expect(find.text('Osmeña Boulevard'), findsOneWidget);
    expect(find.text('Camputhaw'), findsOneWidget);
    expect(find.text('Cebu City'), findsOneWidget);
    expect(find.text('6000'), findsOneWidget);
    // The lookup found no province; the city's only province fills it.
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

    final street = find.widgetWithText(
      TextFormField,
      'Unit / house no. and street',
    );
    await tester.enterText(street, 'ca');
    await tester.pump(const Duration(milliseconds: 400));
    expect(api.searched, isEmpty, reason: 'two letters are too few to search');

    await tester.enterText(street, 'camput');
    await tester.pump(const Duration(milliseconds: 400));
    await tester.pumpAndSettle();
    expect(api.searched, ['camput']);

    await tester.tap(find.text('Juana Osmeña Extension, Camputhaw, Cebu City'));
    await tester.pumpAndSettle();

    expect(find.text('Juana Osmeña Extension'), findsOneWidget);
    expect(find.text('Camputhaw'), findsOneWidget);
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
      'address_line': 'Osmeña Boulevard',
      'barangay': 'Camputhaw',
      'city': 'Cebu City',
      'province': '',
      'postal_code': '6000',
    };
  }

  final List<String> searched = [];
  final List<(String, String)> barangaysAsked = [];

  @override
  Future<Map<String, List<String>>> provinces() async => {
    'Cebu': ['Cebu City', 'Mandaue City'],
    'Metro Manila': ['Makati City', 'Manila'],
  };

  @override
  Future<List<String>> barangays(String province, String city) async {
    barangaysAsked.add((province, city));
    return city == 'Cebu City' ? ['Apas', 'Camputhaw'] : ['Bel-Air'];
  }

  @override
  Future<List<Map<String, dynamic>>> searchAddresses(String query) async {
    searched.add(query);
    return [
      {
        'label': 'Juana Osmeña Extension, Camputhaw, Cebu City',
        'address_line': 'Juana Osmeña Extension',
        'barangay': 'Camputhaw',
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
