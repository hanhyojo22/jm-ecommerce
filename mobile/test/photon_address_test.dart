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
