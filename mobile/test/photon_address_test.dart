import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/api_client.dart';

/// Properties as Photon returned them for Cebu City on 2026-11-20.
Map<String, dynamic> _feature(Map<String, dynamic> properties) => {
  'type': 'Feature',
  'properties': {
    ...properties,
    'state': 'Central Visayas',
    'country': 'Philippines',
    'countrycode': 'PH',
  },
};

void main() {
  test('a point exposes latitude and longitude in the correct order', () {
    final fields = photonAddressFields({
      ..._feature({'osm_key': 'place', 'name': 'Makati', 'city': 'Makati'}),
      'geometry': {
        'type': 'Point',
        'coordinates': [121.0244, 14.5547],
      },
    });
    expect(fields['latitude'], 14.5547);
    expect(fields['longitude'], 121.0244);
    expect(fields['label'], 'Makati');
  });

  for (final coordinates in <Object?>[
    null,
    [],
    [121],
    ['121', 14],
    [121, 91],
    [181, 14],
    [double.nan, 14],
  ]) {
    test('invalid coordinates $coordinates cannot become a pin', () {
      final fields = photonAddressFields({
        ..._feature({'osm_key': 'building', 'name': 'Sample building'}),
        'geometry': {'type': 'Point', 'coordinates': coordinates},
      });
      expect(fields.containsKey('latitude'), isFalse);
      expect(fields.containsKey('longitude'), isFalse);
      expect(fields['label'], 'Sample building');
    });
  }

  test('a street reads as street and barangay', () {
    expect(
      photonAddressFields(
        _feature({
          'osm_key': 'highway',
          'osm_value': 'residential',
          'type': 'street',
          'name': 'Juana Osmeña Extension',
          'locality': 'Purok 4',
          'district': 'Camputhaw',
          'city': 'Cebu City',
          'postcode': '6000',
        }),
      ),
      {
        'label': 'Juana Osmeña Extension, Camputhaw, Cebu City',
        'address_line': 'Juana Osmeña Extension',
        'barangay': 'Camputhaw',
        'city': 'Cebu City',
        'province': '',
        'postal_code': '6000',
      },
    );
  });

  test('a building keeps its name, street and barangay', () {
    final fields = photonAddressFields(
      _feature({
        'osm_key': 'building',
        'name': 'Winland Tower A',
        'street': 'Juana Osmeña Extension',
        'district': 'Camputhaw',
        'city': 'Cebu City',
        'postcode': '6000',
      }),
    );

    expect(fields['address_line'], 'Winland Tower A, Juana Osmeña Extension');
    expect(fields['barangay'], 'Camputhaw');
  });

  test('a barangay fills only the barangay, and odd postcodes are dropped', () {
    final fields = photonAddressFields(
      _feature({
        'osm_key': 'place',
        'osm_value': 'suburb',
        'name': 'Camputhaw',
        'city': 'Cebu City',
        'postcode': 'PH-6000',
      }),
    );

    expect(fields['address_line'], '');
    expect(fields['barangay'], 'Camputhaw');
    expect(fields['label'], 'Camputhaw, Cebu City');
    expect(fields['postal_code'], '');
  });
}
