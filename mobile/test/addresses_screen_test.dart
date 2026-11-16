import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/screens/addresses_screen.dart';

void main() {
  testWidgets(
    'a checkout address can be edited without province or postal code',
    (tester) async {
      Address? saved;
      await tester.pumpWidget(
        MaterialApp(
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
}
