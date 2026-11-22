import 'dart:async';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:geolocator/geolocator.dart';
import 'package:go_router/go_router.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/core/api_exception.dart';
import 'package:jmmart/src/core/attachment_picker.dart';
import 'package:jmmart/src/core/discount_id_reader.dart';
import 'package:jmmart/src/core/location_service.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/models/store_branch.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/providers/checkout_providers.dart';
import 'package:jmmart/src/providers/store_locator_providers.dart';
import 'package:jmmart/src/screens/checkout_screen.dart';
import 'package:jmmart/src/screens/checkout_success_screen.dart';
import 'package:jmmart/src/theme.dart';

const _branch = StoreBranch(
  id: 'makati',
  branchId: 'B-MAKATI',
  name: 'Makati branch',
  address: 'Makati',
  latitude: 14.5547,
  longitude: 121.0244,
  maxDeliveryRadiusKm: 5,
);

void main() {
  testWidgets(
    'success uses the created order snapshot even if cart refresh fails',
    (tester) async {
      final api = _CheckoutApi(rx: false)
        ..cartFailure = true
        ..response = PlacedOrder.fromJson({
          'id': 'order-1',
          'number': 'EP-20418',
          'total': '168.25',
        });
      await _pump(
        tester,
        api,
        locator: const StoreLocatorState(
          branches: [BranchDistance(_branch, 1.2)],
        ),
      );
      await _openReview(tester);
      await tester.tap(find.text('Place order'));
      await tester.pumpAndSettle();
      expect(find.text('Order placed'), findsOneWidget);
      expect(find.text('Order #EP-20418'), findsOneWidget);
      expect(find.text('\u20b1168.25'), findsOneWidget);
      expect(api.orderAttempts, 1);
      await tester.binding.handlePopRoute();
      await tester.pumpAndSettle();
      expect(find.text('Order route'), findsOneWidget);
      expect(api.orderAttempts, 1);
    },
  );

  testWidgets(
    'payment is separate and review edits return with the selected method',
    (tester) async {
      final api = _CheckoutApi(rx: false, online: true);
      await _pump(
        tester,
        api,
        locator: const StoreLocatorState(
          branches: [BranchDistance(_branch, 1.2)],
        ),
      );
      await _openPayment(tester);
      expect(find.text('Payment method'), findsOneWidget);
      expect(find.text('Place order'), findsNothing);
      await tester.tap(find.text('GCash'));
      await tester.pumpAndSettle();
      await _openReview(tester);
      expect(find.text('Review your order'), findsOneWidget);
      expect(find.byType(RadioListTile<String>), findsNothing);
      expect(find.text('GCash'), findsOneWidget);
      expect(api.orderAttempts, 0);
      await tester.tap(find.bySemanticsLabel('Edit payment method'));
      await tester.pumpAndSettle();
      expect(find.text('Payment method'), findsOneWidget);
      expect(find.text('Save & return to review'), findsOneWidget);
      final gcash = tester.widget<RadioListTile<String>>(
        find.widgetWithText(RadioListTile<String>, 'GCash'),
      );
      expect(gcash.selected, isTrue);
      await tester.tap(find.text('Cash on delivery'));
      await tester.tap(find.text('Save & return to review'));
      await tester.pumpAndSettle();
      expect(find.text('Review your order'), findsOneWidget);
      expect(find.text('Cash on delivery'), findsOneWidget);
      expect(find.text('GCash'), findsNothing);
      expect(api.orderAttempts, 0);
    },
  );

  testWidgets(
    'review address edit saves directly back to review without losing documents',
    (tester) async {
      final api = _CheckoutApi(rx: true);
      await _pump(
        tester,
        api,
        locator: const StoreLocatorState(
          branches: [BranchDistance(_branch, 1.2)],
        ),
      );
      await _openDocuments(tester);
      await tester.tap(find.text('Take photos'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Take a photo'));
      await tester.pumpAndSettle();
      await _openReview(tester);
      await tester.tap(find.bySemanticsLabel('Edit contact details'));
      await tester.pumpAndSettle();
      expect(find.text('Delivery address'), findsOneWidget);
      await tester.tap(find.text('Save address'));
      await tester.pumpAndSettle();
      expect(find.text('Review your order'), findsOneWidget);
      expect(find.text('1 page added'), findsOneWidget);
      expect(find.text('Ana Buyer'), findsOneWidget);
      expect(find.byType(RadioListTile<String>), findsNothing);
      await tester.tap(find.bySemanticsLabel('Edit Prescription'));
      await tester.pumpAndSettle();
      expect(find.text('1 page'), findsOneWidget);
      await tester.tap(find.text('Save & return to review'));
      await tester.pumpAndSettle();
      expect(find.text('Review your order'), findsOneWidget);
      expect(api.orderAttempts, 0);
    },
  );

  testWidgets('total opens a receipt without advancing or submitting', (
    tester,
  ) async {
    final api = _CheckoutApi(rx: false);
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, 1.2)],
      ),
    );
    await tester.tap(find.text('Order total'));
    await tester.pumpAndSettle();
    expect(find.text('Order summary'), findsOneWidget);
    expect(find.text('Subtotal'), findsOneWidget);
    await tester.tap(find.byTooltip('Close order summary'));
    await tester.pumpAndSettle();
    expect(find.text('Delivery or pickup'), findsOneWidget);
    expect(api.orderAttempts, 0);
  });

  testWidgets('address validation reveals the first offscreen error', (
    tester,
  ) async {
    final api = _CheckoutApi(rx: false, hasSavedAddresses: false);
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, 1.2)],
      ),
      width: 320,
      height: 700,
    );
    await tester.scrollUntilVisible(
      find.widgetWithText(TextField, 'Country'),
      200,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pumpAndSettle();
    await _openReview(tester);
    expect(
      find.text('Enter the recipient name.').hitTestable(),
      findsOneWidget,
    );
    expect(api.orderAttempts, 0);
    await tester.enterText(
      find.widgetWithText(TextField, 'Recipient name'),
      'Ana Buyer',
    );
    await tester.pumpAndSettle();
    expect(find.text('Enter the recipient name.'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('address keyboard Next reaches the phone and Done ends entry', (
    tester,
  ) async {
    final api = _CheckoutApi(rx: false, hasSavedAddresses: false);
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, 1.2)],
      ),
    );
    final name = find.widgetWithText(TextField, 'Recipient name');
    await tester.enterText(name, 'Ana Buyer');
    await tester.testTextInput.receiveAction(TextInputAction.next);
    await tester.pumpAndSettle();
    final phone = tester.widget<TextField>(
      find.widgetWithText(TextField, 'Phone'),
    );
    expect(
      tester
          .widgetList<EditableText>(find.byType(EditableText))
          .singleWhere((field) => field.focusNode.hasFocus)
          .controller,
      phone.controller,
    );
    expect(find.byType(AutofillGroup), findsOneWidget);
    await tester.enterText(find.widgetWithText(TextField, 'ZIP code'), '1200');
    await tester.testTextInput.receiveAction(TextInputAction.done);
    await tester.pumpAndSettle();
    expect(
      tester
          .widgetList<EditableText>(find.byType(EditableText))
          .any((field) => field.focusNode.hasFocus),
      isFalse,
    );
    expect(api.orderAttempts, 0);
  });

  testWidgets('prescription errors persist until an attachment is added', (
    tester,
  ) async {
    final api = _CheckoutApi(rx: true);
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, 1.2)],
      ),
    );
    await _openDocuments(tester);
    await _openReview(tester);
    await tester.pump(const Duration(seconds: 8));
    expect(
      find.text('Add a photo of your prescription.').hitTestable(),
      findsOneWidget,
    );
    expect(find.byType(SnackBar), findsNothing);
    await tester.tap(find.text('Take photos'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Take a photo'));
    await tester.pumpAndSettle();
    expect(find.text('Add a photo of your prescription.'), findsNothing);
    expect(
      tester.getSize(find.byTooltip('Remove page 1')).shortestSide,
      greaterThanOrEqualTo(48),
    );
    expect(
      tester.getSize(find.widgetWithText(OutlinedButton, 'Upload file')).height,
      greaterThanOrEqualTo(48),
    );
    expect(api.orderAttempts, 0);
  });

  testWidgets('pickup review shows the contact and phone on separate lines', (
    tester,
  ) async {
    await _pump(
      tester,
      _CheckoutApi(rx: false),
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, 1.2)],
      ),
    );
    await tester.tap(find.text('Pickup · Free'));
    await tester.pumpAndSettle();
    await _openReview(tester);
    expect(find.textContaining('Ana Buyer\n09171234567'), findsOneWidget);
    expect(find.bySemanticsLabel('Edit contact details'), findsOneWidget);
  });

  testWidgets('landscape footer leaves the fulfillment choices visible', (
    tester,
  ) async {
    await _pump(
      tester,
      _CheckoutApi(rx: true),
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, 1.2)],
      ),
      width: 844,
      height: 390,
      scale: 1.3,
    );
    final scaffold = tester.widget<Scaffold>(find.byType(Scaffold).first);
    expect(
      tester.getSize(find.byWidget(scaffold.bottomNavigationBar!)).height,
      lessThan(100),
    );
    expect(find.text('Pickup · Free').hitTestable(), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  for (final brightness in Brightness.values) {
    testWidgets(
      'expanded address supports large text in ${brightness.name} theme',
      (tester) async {
        await _pump(
          tester,
          _CheckoutApi(rx: false, hasSavedAddresses: false),
          locator: const StoreLocatorState(
            branches: [BranchDistance(_branch, 1.2)],
          ),
          width: 320,
          height: 844,
          scale: 2,
          brightness: brightness,
        );
        await tester.scrollUntilVisible(
          find.widgetWithText(TextField, 'Country'),
          200,
          scrollable: find.byType(Scrollable).first,
        );
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
        await _openReview(tester);
        expect(
          find.text('Enter the recipient name.').hitTestable(),
          findsOneWidget,
        );
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets(
    'document editing and review support large text on a narrow phone',
    (tester) async {
      await _pump(
        tester,
        _CheckoutApi(rx: true, seniorPwd: '28.57'),
        locator: const StoreLocatorState(
          branches: [BranchDistance(_branch, 1.2)],
        ),
        width: 320,
        height: 844,
        scale: 2,
      );
      await _openDocuments(tester);
      await tester.scrollUntilVisible(
        find.text('Senior citizen / PWD'),
        160,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.ensureVisible(find.text('Senior citizen / PWD'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Senior citizen / PWD'));
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.widgetWithText(TextField, 'ID number'),
        160,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      await tester.ensureVisible(find.text('Senior citizen / PWD'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Senior citizen / PWD'));
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.text('Take photos'),
        -200,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.ensureVisible(find.text('Take photos'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Take photos'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Take a photo'));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      await _openReview(tester);
      expect(find.text('Review your order'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('full delivery address validates and reaches the order request', (
    tester,
  ) async {
    final api = _CheckoutApi(rx: false, hasSavedAddresses: false);
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, 1.2)],
      ),
    );
    for (final entry in {
      'Recipient name': 'Maria Buyer',
      'Phone': '09170001234',
      'House no., street, landmark': '22 Sample Street',
      'Barangay': 'Poblacion',
      'City / municipality': '',
    }.entries) {
      await tester.enterText(
        find.widgetWithText(TextField, entry.key),
        entry.value,
      );
    }
    await _openReview(tester);
    expect(find.text('Enter the city or municipality.'), findsOneWidget);
    expect(find.text('Enter the province.'), findsOneWidget);
    expect(find.text('Use a 4-digit ZIP code.'), findsOneWidget);
    expect(api.orderAttempts, 0);
    await tester.enterText(
      find.widgetWithText(TextField, 'City / municipality'),
      'Makati',
    );
    await tester.enterText(
      find.widgetWithText(TextField, 'Province'),
      'Metro Manila',
    );
    for (final zip in ['123', '12AB', '12345']) {
      await tester.enterText(find.widgetWithText(TextField, 'ZIP code'), zip);
      await _openReview(tester);
      expect(find.text('Use a 4-digit ZIP code.'), findsOneWidget);
      expect(api.orderAttempts, 0);
    }
    expect(
      tester
          .widget<TextField>(find.widgetWithText(TextField, 'Country'))
          .readOnly,
      isTrue,
    );
    await tester.enterText(find.widgetWithText(TextField, 'ZIP code'), '1200');
    await _openReview(tester);
    expect(
      find.textContaining(
        '22 Sample Street, Poblacion, Makati, Metro Manila 1200, Philippines',
      ),
      findsOneWidget,
    );
    await _submit(tester);
    expect(api.placedAddress?.toJson(), containsPair('city', 'Makati'));
    expect(
      api.placedAddress?.toJson(),
      containsPair('province', 'Metro Manila'),
    );
    expect(api.placedAddress?.toJson(), containsPair('postal_code', '1200'));
    expect(find.text('Order route'), findsOneWidget);
  });

  testWidgets('preferred address retains city province and ZIP code', (
    tester,
  ) async {
    final api = _CheckoutApi(
      rx: false,
      hasSavedAddresses: false,
      preferredAddress: const Address(
        recipientName: 'Ana Buyer',
        phone: '09171234567',
        addressLine: '8 Main Street',
        subdivision: 'Sample Village',
        barangay: 'San Lorenzo',
        city: 'Makati',
        province: 'Metro Manila',
        postalCode: '1223',
      ),
    );
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, 1.2)],
      ),
    );
    expect(
      tester
          .widget<TextField>(
            find.widgetWithText(TextField, 'Subdivision / building (optional)'),
          )
          .controller
          ?.text,
      'Sample Village',
    );
    await tester.enterText(
      find.widgetWithText(TextField, 'Subdivision / building (optional)'),
      'Updated Village',
    );
    await _submit(tester);
    expect(api.placedAddress?.subdivision, 'Updated Village');
    expect(api.placedAddress?.city, 'Makati');
    expect(api.placedAddress?.province, 'Metro Manila');
    expect(api.placedAddress?.postalCode, '1223');
    expect(find.text('Order route'), findsOneWidget);
  });

  testWidgets('new pickup contact does not require a delivery address', (
    tester,
  ) async {
    final api = _CheckoutApi(rx: false, hasSavedAddresses: false);
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, 1.2)],
      ),
    );
    await tester.tap(find.text('Pickup · Free'));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.widgetWithText(TextField, 'Recipient name'),
      'Ana Buyer',
    );
    await tester.enterText(
      find.widgetWithText(TextField, 'Phone'),
      '09171234567',
    );
    expect(find.widgetWithText(TextField, 'ZIP code'), findsNothing);
    await _submit(tester);
    expect(api.placed?.fulfillment, 'pickup');
    expect(find.text('Order route'), findsOneWidget);
  });

  testWidgets('a failed quote refresh hides submission until retry succeeds', (
    tester,
  ) async {
    final api = _CheckoutApi(rx: false);
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, 1.2)],
      ),
    );
    await _openReview(tester);
    api.quoteFailure = true;
    ProviderScope.containerOf(tester.element(find.byType(CheckoutScreen)))
        .invalidate(checkoutQuoteProvider);
    await tester.pumpAndSettle();
    expect(find.text('Try again'), findsOneWidget);
    expect(find.text('Place order'), findsNothing);
    expect(api.orderAttempts, 0);

    api.quoteFailure = false;
    await tester.tap(find.text('Try again'));
    await tester.pumpAndSettle();
    expect(find.text('Review your order'), findsOneWidget);
    expect(find.text('Place order'), findsOneWidget);
    expect(api.orderAttempts, 0);
  });

  testWidgets(
    'a server failure keeps review open when a saved ID was not used',
    (tester) async {
      final api =
          _CheckoutApi(
              rx: false,
              cards: const [
                {'id': 'unused', 'type': 'senior', 'name': 'Ana Buyer'},
              ],
            )
            ..failure = const ApiException(
              code: 'resource.not_found',
              message: 'The item is no longer available.',
              statusCode: 404,
            );
      await _pump(
        tester,
        api,
        locator: const StoreLocatorState(
          branches: [BranchDistance(_branch, 1.2)],
        ),
      );
      await _openReview(tester);
      await _submit(tester);
      expect(find.text('Review your order'), findsOneWidget);
      expect(find.text('Documents'), findsNothing);
      expect(
        find.text(
          'That saved ID is no longer available. Type it again and place the order.',
        ),
        findsNothing,
      );
      expect(api.orderAttempts, 1);
      expect(api.placed, isNull);
    },
  );

  testWidgets('keyboard leaves room for the address editor on a small phone', (
    tester,
  ) async {
    await _pump(
      tester,
      _CheckoutApi(rx: false),
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, 1.2)],
      ),
      width: 320,
      height: 600,
      scale: 1.3,
    );
    await tester.scrollUntilVisible(
      find.bySemanticsLabel('Edit contact details'),
      200,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pumpAndSettle();
    await tester.tap(find.bySemanticsLabel('Edit contact details'));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text('Use a new address'),
      200,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Use a new address'));
    await tester.pumpAndSettle();
    tester.view.viewInsets = const FakeViewPadding(bottom: 300);
    addTearDown(tester.view.resetViewInsets);
    await tester.pumpAndSettle();
    expect(find.text('Order total'), findsNothing);
    expect(tester.takeException(), isNull);
    await tester.scrollUntilVisible(
      find.widgetWithText(TextField, 'Recipient name'),
      180,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pumpAndSettle();
    await tester.enterText(
      find.widgetWithText(TextField, 'Recipient name'),
      'Ana Buyer',
    );
    expect(tester.takeException(), isNull);
    tester.view.resetViewInsets();
    await tester.pumpAndSettle();
    expect(find.text('Order total'), findsOneWidget);
  });

  testWidgets('checkout skips documents when none are needed', (tester) async {
    final api = _CheckoutApi(rx: false);
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, 1.2)],
      ),
    );

    expect(find.text('Documents'), findsNothing);
    expect(find.text('Place order'), findsNothing);
    await _openReview(tester);

    expect(find.text('Review your order'), findsOneWidget);
    expect(find.text('Place order'), findsOneWidget);
    expect(api.orderAttempts, 0);
    await tester.tap(find.byType(BackButton));
    await tester.pumpAndSettle();
    expect(find.text('Payment method'), findsOneWidget);
    await tester.tap(find.byType(BackButton));
    await tester.pumpAndSettle();
    expect(find.text('Delivery or pickup'), findsOneWidget);
    expect(api.orderAttempts, 0);
  });

  testWidgets('Back keeps the prescription and checkout details', (
    tester,
  ) async {
    final api = _CheckoutApi(rx: true);
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, 1.2)],
      ),
    );
    await _openDocuments(tester);
    await tester.tap(find.text('Take photos'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Take a photo'));
    await tester.pumpAndSettle();
    await _openReview(tester);

    await tester.tap(find.byType(BackButton));
    await tester.pumpAndSettle();
    expect(find.text('Payment method'), findsOneWidget);
    await tester.tap(find.byType(BackButton));
    await tester.pumpAndSettle();
    expect(find.text('1 page'), findsOneWidget);
    await tester.binding.handlePopRoute();
    await tester.pumpAndSettle();
    expect(find.text('Delivery or pickup'), findsOneWidget);
    expect(find.text('Ana Buyer'), findsOneWidget);
    await _openDocuments(tester);
    expect(find.text('1 page'), findsOneWidget);
    expect(api.orderAttempts, 0);
  });

  testWidgets('address editing validates before advancing and survives Back', (
    tester,
  ) async {
    final api = _CheckoutApi(rx: true);
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, 1.2)],
      ),
    );
    await tester.tap(find.bySemanticsLabel('Edit contact details'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Use a new address'));
    await tester.pumpAndSettle();
    await _openDocuments(tester);
    expect(find.text('Enter the recipient name.'), findsOneWidget);
    expect(find.text('Delivery'), findsOneWidget);
    expect(api.orderAttempts, 0);

    await tester.enterText(
      find.widgetWithText(TextField, 'Recipient name'),
      'Maria Buyer',
    );
    await tester.enterText(
      find.widgetWithText(TextField, 'Phone'),
      '09170001234',
    );
    await tester.enterText(
      find.widgetWithText(TextField, 'House no., street, landmark'),
      '22 Sample Street',
    );
    await tester.enterText(
      find.widgetWithText(TextField, 'Barangay'),
      'Poblacion',
    );
    await tester.enterText(
      find.widgetWithText(TextField, 'City / municipality'),
      'Makati',
    );
    await tester.enterText(
      find.widgetWithText(TextField, 'Province'),
      'Metro Manila',
    );
    await tester.enterText(find.widgetWithText(TextField, 'ZIP code'), '1200');
    await tester.tap(find.text('Save address'));
    await tester.pumpAndSettle();
    await _openDocuments(tester);
    await tester.tap(find.byType(BackButton));
    await tester.pumpAndSettle();
    expect(find.text('Maria Buyer'), findsOneWidget);
    expect(find.textContaining('22 Sample Street'), findsOneWidget);
    expect(
      find.textContaining('Makati, Metro Manila 1200, Philippines'),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('fees follow the chosen payment, discount and fulfillment', (
    tester,
  ) async {
    final api = _CheckoutApi(
      rx: false,
      seniorPwd: '28.57',
      online: true,
      cards: const [
        {
          'id': 'saved',
          'type': 'senior',
          'name': 'Lola Ana',
          'id_number_masked': '4821',
          'has_document': true,
        },
      ],
    );
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, 1.2)],
      ),
    );
    await _openDocuments(tester);
    await tester.tap(find.text('Senior citizen / PWD'));
    await tester.pumpAndSettle();
    await _openPayment(tester);
    await tester.tap(find.text('GCash'));
    await tester.pumpAndSettle();
    expect(find.text('₱124.93'), findsWidgets);

    await tester.tap(find.byType(BackButton));
    await tester.pumpAndSettle();
    await tester.tap(find.byType(BackButton));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Pickup · Free'));
    await tester.pumpAndSettle();
    await _openDocuments(tester);
    await _openPayment(tester);
    expect(find.text('₱73.93'), findsWidgets);
    expect(api.orderAttempts, 0);
    expect(tester.takeException(), isNull);
  });

  testWidgets('unavailable delivery cash cannot submit but pickup can', (
    tester,
  ) async {
    final api = _CheckoutApi(rx: false, cashAvailable: false);
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, 1.2)],
      ),
    );
    await _openReview(tester);
    await _submit(tester);
    expect(find.text('Choose an available payment method.'), findsOneWidget);
    expect(api.orderAttempts, 0);

    await tester.tap(find.byType(BackButton));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Pickup · Free'));
    await tester.pumpAndSettle();
    await _submit(tester);
    expect(api.placed?.fulfillment, 'pickup');
    expect(api.orderAttempts, 1);
  });

  testWidgets('pending submission blocks repeat taps and Back', (tester) async {
    final pending = Completer<PlacedOrder>();
    final api = _CheckoutApi(rx: false)..pendingOrder = pending;
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, 1.2)],
      ),
    );
    await _openReview(tester);
    final submit = find.widgetWithText(FilledButton, 'Place order');
    await tester.tap(submit);
    await tester.pump();
    expect(api.orderAttempts, 1);
    expect(
      tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
      isNull,
    );
    await tester.binding.handlePopRoute();
    await tester.pump();
    expect(find.text('Review your order'), findsOneWidget);
    expect(api.orderAttempts, 1);
    pending.complete(PlacedOrder.fromJson({'id': 'order-1', 'number': 'JM-1'}));
    await tester.pumpAndSettle();
    expect(find.text('Order placed'), findsOneWidget);
    expect(find.text('Place order'), findsNothing);
    expect(api.orderAttempts, 1);
    await tester.tap(find.text('View order'));
    await tester.pumpAndSettle();
    expect(find.text('Order route'), findsOneWidget);
  });

  testWidgets('a senior citizen card takes the discount off the total', (
    tester,
  ) async {
    final api = _CheckoutApi(rx: false, seniorPwd: '28.57');
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, null)],
        issue: LocationIssue.denied,
      ),
      location: const _NoLocation(),
    );
    await tester.tap(find.text('Pickup · Free'));
    await tester.pumpAndSettle();
    expect(find.text('₱100.00'), findsWidgets);

    await _openDocuments(tester);
    await tester.ensureVisible(find.text('Senior citizen / PWD'));
    await tester.tap(find.text('Senior citizen / PWD'));
    await tester.pumpAndSettle();
    expect(find.text('₱71.43'), findsWidgets);
    expect(find.text('Use a different ID'), findsNothing);

    await _submit(tester);
    await tester.pump();
    expect(
      find.text('Enter the ID number and the name on the ID for the discount.'),
      findsOneWidget,
    );
    expect(api.placed, isNull);

    await tester.enterText(find.widgetWithText(TextField, 'ID number'), 'SC-9');
    await tester.enterText(
      find.widgetWithText(TextField, 'Name on the ID'),
      'Lola Ana',
    );
    tester
        .state<ScaffoldMessengerState>(find.byType(ScaffoldMessenger))
        .removeCurrentSnackBar();
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Add photo of ID'));
    await tester.tap(find.text('Add photo of ID'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Scan front and back'));
    await tester.pumpAndSettle();
    expect(find.text('Now scan the back of your ID'), findsOneWidget);
    await tester.tap(find.text('Scan the back'));
    await tester.pumpAndSettle();
    await _submit(tester);
    await tester.pumpAndSettle();

    expect(api.placed!.discountDocument?.name, 'front-back.png');
    expect(api.placed!.discountCard, (
      type: 'senior',
      idNumber: 'SC-9',
      name: 'Lola Ana',
    ));
    expect(tester.takeException(), isNull);
  });

  testWidgets('closing the back-of-ID prompt attaches no ID photo', (
    tester,
  ) async {
    final api = _CheckoutApi(rx: false, seniorPwd: '28.57');
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, null)],
        issue: LocationIssue.denied,
      ),
      location: const _NoLocation(),
    );
    await tester.tap(find.text('Pickup · Free'));
    await tester.pumpAndSettle();
    await _openDocuments(tester);
    await tester.ensureVisible(find.text('Senior citizen / PWD'));
    await tester.tap(find.text('Senior citizen / PWD'));
    await tester.pumpAndSettle();
    await tester.enterText(find.widgetWithText(TextField, 'ID number'), 'SC-9');
    await tester.enterText(
      find.widgetWithText(TextField, 'Name on the ID'),
      'Lola Ana',
    );

    await tester.ensureVisible(find.text('Add photo of ID'));
    await tester.tap(find.text('Add photo of ID'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Scan front and back'));
    await tester.pumpAndSettle();
    expect(find.text('Now scan the back of your ID'), findsOneWidget);
    await tester.tapAt(const Offset(20, 20));
    await tester.pumpAndSettle();
    expect(find.text('Now scan the back of your ID'), findsNothing);

    await _submit(tester);
    await tester.pump();
    expect(
      find.text('Add a photo of your senior citizen or PWD ID.'),
      findsOneWidget,
    );
    expect(api.placed, isNull);
  });

  testWidgets('an Rx pickup order needs a prescription and sends no pin', (
    tester,
  ) async {
    final api = _CheckoutApi(rx: true);
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, null)],
        issue: LocationIssue.denied,
      ),
      location: const _NoLocation(),
    );

    expect(
      find.text(
        'We could not find your location. Put a pin on your door so the rider knows where to go.',
      ),
      findsOneWidget,
    );
    await tester.tap(find.text('Pickup · Free'));
    await tester.pumpAndSettle();

    await _submit(tester);
    await tester.pump();
    expect(find.text('Add a photo of your prescription.'), findsOneWidget);
    expect(api.placed, isNull);

    expect(find.text('Required'), findsOneWidget);
    expect(find.textContaining('For Amoxicillin 500mg'), findsOneWidget);
    await tester.ensureVisible(find.text('Take photos'));
    await tester.tap(find.text('Take photos'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Take a photo'));
    await tester.pumpAndSettle();
    expect(
      find.text(
        'Include every page, front and back. Up to 10 pages, 5 MB each.',
      ),
      findsOneWidget,
    );
    expect(find.text('1 page'), findsOneWidget);
    expect(find.byTooltip('Remove page 1'), findsOneWidget);
    tester
        .state<ScaffoldMessengerState>(find.byType(ScaffoldMessenger))
        .removeCurrentSnackBar();
    await tester.pumpAndSettle();

    await _openReview(tester);
    expect(find.text('Pay at the counter'), findsOneWidget);
    await _submit(tester);
    await tester.pumpAndSettle();

    expect(api.placed!.fulfillment, 'pickup');
    expect(api.placed!.prescriptions.single.name, 'camera.jpg');
    expect(api.placed!.latitude, isNull);
    expect(find.text('Order route'), findsOneWidget);
  });

  testWidgets(
    'a delivery inside the radius sends the map pin and adds the fee',
    (tester) async {
      final api = _CheckoutApi(rx: false);
      await _pump(
        tester,
        api,
        locator: const StoreLocatorState(
          branches: [BranchDistance(_branch, 1.2)],
        ),
      );

      expect(find.text('Delivery · ₱49.00'), findsOneWidget);
      await _openReview(tester);
      expect(find.text('Delivery fee'), findsOneWidget);
      expect(find.text('₱149.00'), findsWidgets);

      await _submit(tester);
      await tester.pumpAndSettle();

      expect(api.placed!.fulfillment, 'delivery');
      expect(api.placed!.latitude, 14.55);
      expect(api.placed!.longitude, 121.02);
      expect(api.placed!.prescriptions, isEmpty);
    },
  );

  testWidgets('a closed branch starts on pickup and greys out delivery', (
    tester,
  ) async {
    final api = _CheckoutApi(
      rx: false,
      closedUntil: DateTime.now()
          .add(const Duration(hours: 2))
          .toUtc()
          .toIso8601String(),
    );
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, 1.2)],
      ),
    );

    expect(find.textContaining('Makati branch is closed.'), findsOneWidget);

    await tester.tap(find.text('Delivery · ₱49.00'));
    await tester.pumpAndSettle();
    await _openReview(tester);
    expect(find.text('Pay at the counter'), findsOneWidget);
    await _submit(tester);
    await tester.pumpAndSettle();
    expect(api.placed!.fulfillment, 'pickup');
  });

  for (final (width, height, scale) in [
    (320.0, 844.0, 1.0),
    (320.0, 844.0, 2.0),
    (390.0, 844.0, 1.0),
    (390.0, 844.0, 2.0),
    (768.0, 1024.0, 1.3),
    (844.0, 390.0, 1.3),
  ]) {
    testWidgets('checkout fits ${width}x$height at ${scale}x text', (
      tester,
    ) async {
      await _pump(
        tester,
        _CheckoutApi(rx: true),
        locator: const StoreLocatorState(
          branches: [BranchDistance(_branch, 1.2)],
        ),
        width: width,
        height: height,
        scale: scale,
      );
      expect(tester.takeException(), isNull);
      await _openDocuments(tester);
      await tester.scrollUntilVisible(
        find.text('Take photos'),
        220,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Take photos'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Take a photo'));
      await tester.pumpAndSettle();
      await _openReview(tester);
      await tester.drag(find.byType(ListView), const Offset(0, -4000));
      await tester.pumpAndSettle();

      expect(tester.takeException(), isNull);
      expect(find.text('Place order'), findsOneWidget);
    });
  }

  testWidgets('a saved discount ID replaces the typed fields', (tester) async {
    final api = _CheckoutApi(
      rx: false,
      seniorPwd: '28.57',
      cards: const [
        {
          'id': 'card-1',
          'type': 'senior',
          'name': 'Lola Ana',
          'id_number_masked': '•••••0123',
          'has_document': true,
        },
      ],
    );
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, null)],
        issue: LocationIssue.denied,
      ),
      location: const _NoLocation(),
    );
    await tester.tap(find.text('Pickup · Free'));
    await tester.pumpAndSettle();

    await _openDocuments(tester);
    await tester.ensureVisible(find.text('Senior citizen / PWD'));
    await tester.tap(find.text('Senior citizen / PWD'));
    await tester.pumpAndSettle();

    // The saved ID starts selected, so there is nothing to type.
    expect(find.text('Lola Ana'), findsOneWidget);
    expect(find.text('Senior citizen ID · •••••0123'), findsOneWidget);
    expect(find.text('ID photo on file'), findsOneWidget);
    expect(find.widgetWithText(TextField, 'ID number'), findsNothing);

    await tester.tap(find.text('Use a different ID'));
    await tester.pumpAndSettle();
    expect(find.widgetWithText(TextField, 'ID number'), findsOneWidget);

    await tester.tap(find.text('Lola Ana'));
    await tester.pumpAndSettle();
    expect(find.widgetWithText(TextField, 'ID number'), findsNothing);

    await _submit(tester);
    await tester.pumpAndSettle();

    expect(api.placed!.discountCardId, 'card-1');
    expect(api.placed!.discountCard, isNull);
    expect(api.placed!.saveDiscountCard, isFalse);
    // Its photo is on file, so none goes up with the order.
    expect(api.placed!.discountDocument, isNull);
    expect(tester.takeException(), isNull);
  });

  testWidgets('the save checkbox sends save_discount_card', (tester) async {
    final api = _CheckoutApi(rx: false, seniorPwd: '28.57');
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, null)],
        issue: LocationIssue.denied,
      ),
      location: const _NoLocation(),
    );
    await tester.tap(find.text('Pickup · Free'));
    await tester.pumpAndSettle();

    await _openDocuments(tester);
    await tester.ensureVisible(find.text('Senior citizen / PWD'));
    await tester.tap(find.text('Senior citizen / PWD'));
    await tester.pumpAndSettle();
    await tester.enterText(find.widgetWithText(TextField, 'ID number'), 'SC-9');
    await tester.enterText(
      find.widgetWithText(TextField, 'Name on the ID'),
      'Lola Ana',
    );
    await tester.ensureVisible(find.text('Save this ID for next time'));
    await tester.tap(find.text('Save this ID for next time'));
    await tester.pumpAndSettle();

    // A typed ID needs its photo before the order can go.
    await _submit(tester);
    await tester.pumpAndSettle();
    expect(api.placed, isNull);
    expect(
      find.text('Add a photo of your senior citizen or PWD ID.'),
      findsOneWidget,
    );
    tester
        .state<ScaffoldMessengerState>(find.byType(ScaffoldMessenger))
        .removeCurrentSnackBar();
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.text('Add photo of ID'));
    await tester.tap(find.text('Add photo of ID'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Choose from gallery'));
    await tester.pumpAndSettle();
    await _submit(tester);
    await tester.pumpAndSettle();

    expect(api.placed!.discountDocument?.name, 'rx.jpg');
    expect(api.placed!.saveDiscountCard, isTrue);
    expect(api.placed!.discountCardId, isNull);
    expect(api.placed!.discountCard, (
      type: 'senior',
      idNumber: 'SC-9',
      name: 'Lola Ana',
    ));
  });

  testWidgets('the ID photo sets the card type and fills only empty fields', (
    tester,
  ) async {
    final api = _CheckoutApi(rx: false, seniorPwd: '28.57');
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, null)],
        issue: LocationIssue.denied,
      ),
      location: const _NoLocation(),
      idPhotoText: 'PWD ID No.: 07-2201-000-0001234\nName: JUANA DELA CRUZ',
    );
    await tester.tap(find.text('Pickup · Free'));
    await tester.pumpAndSettle();
    await _openDocuments(tester);
    await tester.ensureVisible(find.text('Senior citizen / PWD'));
    await tester.tap(find.text('Senior citizen / PWD'));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.widgetWithText(TextField, 'Name on the ID'),
      'Juana Cruz',
    );

    await tester.ensureVisible(find.text('Add photo of ID'));
    await tester.tap(find.text('Add photo of ID'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Choose from gallery'));
    await tester.pumpAndSettle();

    expect(find.text('Juana Cruz'), findsOneWidget);
    expect(find.text('JUANA DELA CRUZ'), findsNothing);
    expect(
      find.text('Filled in from your ID photo. Check it matches your card.'),
      findsOneWidget,
    );
    tester
        .state<ScaffoldMessengerState>(find.byType(ScaffoldMessenger))
        .removeCurrentSnackBar();
    await tester.pumpAndSettle();
    await _submit(tester);
    await tester.pumpAndSettle();

    expect(api.placed!.discountCard, (
      type: 'pwd',
      idNumber: '07-2201-000-0001234',
      name: 'Juana Cruz',
    ));
  });

  testWidgets('a deleted saved ID falls back to the typed fields', (
    tester,
  ) async {
    final api = _CheckoutApi(
      rx: false,
      seniorPwd: '28.57',
      failSavedCard: true,
      cards: const [
        {
          'id': 'card-1',
          'type': 'pwd',
          'name': 'Lolo Ben',
          'id_number_masked': '•••••7788',
          'has_document': true,
        },
      ],
    );
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, null)],
        issue: LocationIssue.denied,
      ),
      location: const _NoLocation(),
    );
    await tester.tap(find.text('Pickup · Free'));
    await tester.pumpAndSettle();

    await _openDocuments(tester);
    await tester.ensureVisible(find.text('Senior citizen / PWD'));
    await tester.tap(find.text('Senior citizen / PWD'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Lolo Ben'));
    await tester.pumpAndSettle();

    await _submit(tester);
    await tester.pumpAndSettle();

    expect(api.placed, isNull);
    expect(
      find.text(
        'That saved ID is no longer available. Type it again and place the order.',
      ),
      findsOneWidget,
    );
    expect(find.widgetWithText(TextField, 'ID number'), findsOneWidget);
    expect(find.widgetWithText(TextField, 'Name on the ID'), findsOneWidget);
  });

  testWidgets('a pin outside the delivery radius cannot be ordered', (
    tester,
  ) async {
    final api = _CheckoutApi(rx: false);
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, 1.2)],
      ),
      location: const _HereLocation(latitude: 14.65, longitude: 121.02),
    );

    final problem = find.textContaining('Makati branch delivers within 5 km');
    expect(problem, findsOneWidget);

    await _submit(tester);
    await tester.pump();
    expect(problem, findsWidgets);
    expect(api.placed, isNull);
  });

  testWidgets('the pin picker shows a far pin and can switch to pickup', (
    tester,
  ) async {
    final api = _CheckoutApi(rx: false);
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, 1.2)],
      ),
      location: const _HereLocation(latitude: 14.65, longitude: 121.02),
    );

    await tester.tap(find.text('Adjust pin'));
    await tester.pumpAndSettle();
    expect(find.text('Too far for delivery'), findsOneWidget);
    expect(find.textContaining('This pin is'), findsOneWidget);
    final confirm = tester.widget<FilledButton>(
      find.widgetWithText(FilledButton, 'Move the pin closer'),
    );
    expect(confirm.onPressed, isNull);

    await tester.tap(find.text('Choose pickup instead'));
    await tester.pumpAndSettle();
    await _openReview(tester);
    expect(find.text('Pay at the counter'), findsOneWidget);

    await _submit(tester);
    await tester.pumpAndSettle();
    expect(api.placed!.fulfillment, 'pickup');
  });

  testWidgets('with location off, delivery waits for a confirmed pin', (
    tester,
  ) async {
    final api = _CheckoutApi(rx: false);
    await _pump(
      tester,
      api,
      locator: const StoreLocatorState(
        branches: [BranchDistance(_branch, null)],
        issue: LocationIssue.denied,
      ),
      location: const _NoLocation(),
    );

    await _submit(tester);
    await tester.pump();
    expect(
      find.text('Set the delivery pin so the rider can find you.'),
      findsOneWidget,
    );
    expect(api.placed, isNull);
    tester
        .state<ScaffoldMessengerState>(find.byType(ScaffoldMessenger))
        .removeCurrentSnackBar();
    await tester.pumpAndSettle();

    await tester.tap(find.text('Set delivery pin'));
    await tester.pumpAndSettle();
    expect(find.text('Is the pin on your door?'), findsOneWidget);
    await tester.tap(find.text('Confirm pin'));
    await tester.pumpAndSettle();
    expect(find.text('Adjust pin'), findsOneWidget);

    await _submit(tester);
    await tester.pumpAndSettle();
    expect(api.placed!.fulfillment, 'delivery');
    expect(api.placed!.latitude, _branch.latitude);
    expect(api.placed!.longitude, _branch.longitude);
  });
}

Future<void> _openDocuments(WidgetTester tester) async {
  final save = find.widgetWithText(FilledButton, 'Save address');
  if (save.evaluate().isNotEmpty) {
    await tester.tap(save);
    await tester.pumpAndSettle();
    if (save.evaluate().isNotEmpty) {
      return;
    }
  }
  final next = find.widgetWithText(FilledButton, 'Continue to documents');
  if (next.evaluate().isNotEmpty) {
    await tester.tap(next);
    await tester.pumpAndSettle();
  }
}

Future<void> _openPayment(WidgetTester tester) async {
  final next = find.widgetWithText(FilledButton, 'Continue to payment');
  if (next.evaluate().isNotEmpty) {
    await tester.tap(next);
    await tester.pumpAndSettle();
  }
}

Future<void> _openReview(WidgetTester tester) async {
  await _openPayment(tester);
  final next = find.widgetWithText(FilledButton, 'Continue to review');
  if (next.evaluate().isNotEmpty) {
    await tester.tap(next);
    await tester.pumpAndSettle();
  }
}

Future<void> _submit(WidgetTester tester) async {
  for (final label in [
    'Continue to documents',
    'Continue to payment',
    'Continue to review',
    'Place order',
    'View order',
  ]) {
    final button = find.widgetWithText(FilledButton, label);
    if (button.evaluate().isNotEmpty) {
      await tester.tap(button);
      await tester.pumpAndSettle();
    }
  }
}

Future<void> _pump(
  WidgetTester tester,
  _CheckoutApi api, {
  required StoreLocatorState locator,
  LocationService location = const _HereLocation(),
  double width = 390,
  double height = 1600,
  double scale = 1,
  Brightness brightness = Brightness.light,
  String idPhotoText = '',
}) async {
  tester.view.physicalSize = Size(width, height);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  final router = GoRouter(
    routes: [
      GoRoute(path: '/', builder: (_, _) => const CheckoutScreen()),
      GoRoute(
        path: '/checkout/success',
        builder: (_, state) => CheckoutSuccessScreen(
          details: state.extra! as CheckoutSuccessDetails,
        ),
      ),
      GoRoute(
        path: '/orders/:id',
        builder: (_, _) => const Scaffold(body: Text('Order route')),
      ),
    ],
  );
  addTearDown(router.dispose);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        isSignedInProvider.overrideWithValue(true),
        apiClientProvider.overrideWithValue(api),
        activeBranchIdProvider.overrideWith((ref) async => 'B-MAKATI'),
        nearestStoreProvider.overrideWith(() => _FixedLocator(locator)),
        locationServiceProvider.overrideWithValue(location),
        attachmentPickerProvider.overrideWithValue(const _PickedRx()),
        discountIdReaderProvider.overrideWithValue(_IdText(idPhotoText)),
      ],
      child: MaterialApp.router(
        theme: buildAppTheme(brightness: brightness),
        routerConfig: router,
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(context)
              .copyWith(textScaler: TextScaler.linear(scale)),
          child: child!,
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

class _FixedLocator extends NearestStoreNotifier {
  _FixedLocator(this.fixed);

  final StoreLocatorState fixed;

  @override
  Future<StoreLocatorState> build() async => fixed;
}

class _HereLocation extends LocationService {
  const _HereLocation({this.latitude = 14.55, this.longitude = 121.02});

  final double latitude;
  final double longitude;

  @override
  Future<LocationPermission> permission() async =>
      LocationPermission.whileInUse;

  @override
  Future<Position> currentPosition({bool fresh = false}) async => Position(
    latitude: latitude,
    longitude: longitude,
    timestamp: DateTime(2026),
    accuracy: 0,
    altitude: 0,
    altitudeAccuracy: 0,
    heading: 0,
    headingAccuracy: 0,
    speed: 0,
    speedAccuracy: 0,
  );
}

class _NoLocation extends LocationService {
  const _NoLocation();

  @override
  Future<LocationPermission> permission() async =>
      LocationPermission.deniedForever;

  @override
  Future<Position> currentPosition({bool fresh = false}) async =>
      throw const LocationFailure(LocationIssue.denied);
}

class _IdText implements DiscountIdReader {
  const _IdText(this.text);

  final String text;

  @override
  Future<String> readText(PickedAttachment photo) async => text;
}

class _PickedRx implements AttachmentPicker {
  const _PickedRx();

  @override
  Future<PickedAttachment?> takePhoto() async => PickedAttachment(
    name: 'camera.jpg',
    bytes: Uint8List.fromList([4, 5, 6]),
  );

  @override
  Future<PickedAttachment?> scanCard() async =>
      PickedAttachment(name: 'scan.jpg', bytes: Uint8List.fromList([7, 8, 9]));

  @override
  Future<PickedAttachment> joinCardSides(
    PickedAttachment front,
    PickedAttachment back,
  ) async => PickedAttachment(name: 'front-back.png', bytes: front.bytes);

  @override
  Future<PickedAttachment?> pickImage() async =>
      PickedAttachment(name: 'rx.jpg', bytes: Uint8List.fromList([1, 2, 3]));

  @override
  Future<PickedAttachment?> pickDocument() async => null;

  @override
  Future<List<PickedAttachment>> pickImages() async => [
    PickedAttachment(name: 'rx.jpg', bytes: Uint8List.fromList([1, 2, 3])),
  ];

  @override
  Future<List<PickedAttachment>> pickDocuments() async => const [];
}

typedef _Placed = ({
  String fulfillment,
  double? latitude,
  double? longitude,
  List<PickedAttachment> prescriptions,
  ({String type, String idNumber, String name})? discountCard,
  String? discountCardId,
  bool saveDiscountCard,
  PickedAttachment? discountDocument,
});

class _CheckoutApi extends ApiClient {
  _CheckoutApi({
    required this.rx,
    this.closedUntil,
    this.seniorPwd = '0.00',
    this.cards = const [],
    this.failSavedCard = false,
    this.online = false,
    this.cashAvailable = true,
    this.hasSavedAddresses = true,
    this.preferredAddress,
  }) : super(baseUrl: 'http://localhost', tokenProvider: () async => null);

  final bool rx;
  final String? closedUntil;
  final String seniorPwd;
  final List<Map<String, dynamic>> cards;
  final bool failSavedCard;
  final bool online;
  final bool cashAvailable;
  final bool hasSavedAddresses;
  final Address? preferredAddress;
  Address? placedAddress;
  int orderAttempts = 0;
  Completer<PlacedOrder>? pendingOrder;
  ApiException? failure;
  bool quoteFailure = false;
  _Placed? placed;
  bool cartFailure = false;
  PlacedOrder? response;

  @override
  Future<CheckoutQuote> checkoutQuote() async => quoteFailure
      ? throw const ApiException(
          code: 'quote.unavailable',
          message: 'Try again later.',
        )
      : CheckoutQuote.fromJson({
          'items': [
            {
              'id': 'line-1',
              'product_id': 'amox',
              'quantity': 1,
              'selected': true,
              'available': true,
              'unit_price': '100.00',
              'line_total': '100.00',
              'product': {
                'name': 'Amoxicillin 500mg',
                'slug': 'amoxicillin',
                'requires_prescription': rx,
                'store': {'name': 'Makati branch', 'slug': 'makati'},
              },
            },
          ],
          'subtotal': '100.00',
          'senior_pwd_discount': seniorPwd,
          'discount_cards': cards,
          'branch': {
            'id': 'B-MAKATI',
            'name': 'Makati branch',
            'address_line': '10 Rizal Street',
            'city': 'Makati',
          },
          'fulfillment_methods': {
            'delivery': {
              'fee': '49.00',
              'total': '149.00',
              'payment_fees': {if (online) 'gcash': '5.50'},
              'payment_fees_with_card': {if (online) 'gcash': '4.50'},
              'closed_until': closedUntil,
            },
            'pickup': {
              'fee': '0.00',
              'total': '100.00',
              'payment_fees': {if (online) 'gcash': '3.50'},
              'payment_fees_with_card': {if (online) 'gcash': '2.50'},
            },
          },
          'store_totals': [
            {'name': 'Makati branch', 'total': '100.00'},
          ],
          'preferred_address': preferredAddress?.toJson(),
          'addresses': [
            if (hasSavedAddresses)
              {
                'id': 'addr-1',
                'recipient_name': 'Ana Buyer',
                'phone': '09171234567',
                'address_line': '1 Mabini Street',
                'city': 'Makati',
                'province': 'Metro Manila',
                'postal_code': '1200',
              },
          ],
          'payment_methods': {
            'cod': {'available': cashAvailable, 'fee': '0.00'},
            if (online) 'gcash': {'available': true, 'fee': '5.50'},
          },
        });

  @override
  Future<PlacedOrder> placeOrder({
    required Address address,
    required String paymentMethod,
    required String clientRequestId,
    String fulfillmentMethod = 'delivery',
    double? deliveryLatitude,
    double? deliveryLongitude,
    List<PickedAttachment> prescriptions = const [],
    ({String type, String idNumber, String name})? discountCard,
    String? discountCardId,
    bool saveDiscountCard = false,
    PickedAttachment? discountDocument,
    String? note,
    String? successUrl,
    String? cancelUrl,
  }) async {
    orderAttempts++;
    if (failure case final failure?) {
      throw failure;
    }
    if (failSavedCard && discountCardId != null) {
      throw const ApiException(
        code: 'resource.not_found',
        message: 'Not found.',
        statusCode: 404,
      );
    }

    placedAddress = address;
    placed = (
      fulfillment: fulfillmentMethod,
      latitude: deliveryLatitude,
      longitude: deliveryLongitude,
      prescriptions: prescriptions,
      discountCard: discountCard,
      discountCardId: discountCardId,
      saveDiscountCard: saveDiscountCard,
      discountDocument: discountDocument,
    );
    return pendingOrder?.future ??
        response ??
        PlacedOrder.fromJson({'id': 'order-1', 'number': 'JM-1'});
  }

  @override
  Future<Cart> cart() async => cartFailure
      ? throw const ApiException(code: 'network_error', message: 'Unavailable')
      : Cart.empty;
}
