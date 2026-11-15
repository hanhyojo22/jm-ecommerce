import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:uuid/uuid.dart';

import '../core/api_exception.dart';
import '../core/attachment_picker.dart';
import '../core/formatters.dart';
import '../core/location_service.dart';
import '../models/models.dart';
import '../providers/auth_providers.dart';
import '../providers/cart_providers.dart';
import '../providers/checkout_providers.dart';
import '../providers/store_locator_providers.dart';
import '../theme.dart';
import '../widgets/common.dart';

class CheckoutScreen extends ConsumerStatefulWidget {
  const CheckoutScreen({super.key});

  @override
  ConsumerState<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends ConsumerState<CheckoutScreen> {
  final _formKey = GlobalKey<FormState>();
  final _recipient = TextEditingController();
  final _phone = TextEditingController();
  final _addressLine = TextEditingController();
  final _city = TextEditingController();
  final _province = TextEditingController();
  final _postalCode = TextEditingController();

  final _requestId = const Uuid().v4();
  String? _selectedAddressId;
  bool _useNewAddress = false;
  bool _initialised = false;
  String _paymentMethod = 'cod';
  String _fulfillment = 'delivery';
  PickedAttachment? _prescription;
  bool _placing = false;

  /// Why delivery can't be offered: the API needs the phone's location as the
  /// pin and refuses addresses outside the branch's radius. Null when it can.
  String? get _deliveryBlockedReason {
    final locator = ref.read(nearestStoreProvider).value;
    if (locator == null || locator.branches.isEmpty) {
      return null;
    }
    if (locator.issue != null) {
      return 'Turn on location to get delivery. Pickup is available.';
    }
    if (locator.activeBranch?.isOutOfDeliveryRange ?? false) {
      return 'You are outside the delivery area of ${locator.activeBranch!.branch.name}. Pickup is available.';
    }
    return null;
  }

  String get _method =>
      _deliveryBlockedReason == null ? _fulfillment : 'pickup';

  /// The online payment fee on what the chosen fulfillment brings the total to.
  String? _paymentFee(CheckoutQuote quote, String method) =>
      quote.fulfillment[_method]?.paymentFees[method] ??
      quote.paymentOptions[method]?.fee;

  @override
  void dispose() {
    _recipient.dispose();
    _phone.dispose();
    _addressLine.dispose();
    _city.dispose();
    _province.dispose();
    _postalCode.dispose();
    super.dispose();
  }

  void _initialise(CheckoutQuote quote) {
    if (_initialised) {
      return;
    }

    _initialised = true;

    if (quote.addresses.isNotEmpty) {
      _selectedAddressId = quote.addresses.first.id;
    } else if (quote.preferredAddress != null) {
      _useNewAddress = true;
      _fill(quote.preferredAddress!);
    } else {
      _useNewAddress = true;
    }

    _paymentMethod = quote.paymentOptions['cod']?.available == true
        ? 'cod'
        : _firstAvailablePayment(quote);
  }

  String _firstAvailablePayment(CheckoutQuote quote) {
    for (final entry in quote.paymentOptions.entries) {
      if (entry.value.available) {
        return entry.key;
      }
    }

    return 'cod';
  }

  void _fill(Address address) {
    _recipient.text = address.recipientName;
    _phone.text = address.phone;
    _addressLine.text = address.addressLine;
    _city.text = address.city;
    _province.text = address.province;
    _postalCode.text = address.postalCode;
  }

  Address? _address(CheckoutQuote quote) {
    if (!_useNewAddress) {
      for (final address in quote.addresses) {
        if (address.id == _selectedAddressId) {
          return address;
        }
      }

      return null;
    }

    return Address(
      recipientName: _recipient.text.trim(),
      phone: _phone.text.trim(),
      addressLine: _addressLine.text.trim(),
      city: _city.text.trim(),
      province: _province.text.trim(),
      postalCode: _postalCode.text.trim(),
    );
  }

  Future<void> _placeOrder(CheckoutQuote quote) async {
    final address = _address(quote);

    if (address == null) {
      _snack('Choose a delivery address.');
      return;
    }

    if (_useNewAddress && !(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    if (quote.needsPrescription && _prescription == null) {
      _snack('Add a photo of your prescription.');
      return;
    }

    setState(() => _placing = true);

    try {
      final method = _method;
      // shortcut: the delivery pin is where the phone is, not the typed address;
      // add a map pin picker when customers order for someone elsewhere.
      final pin = method == 'delivery'
          ? await ref.read(locationServiceProvider).currentPosition()
          : null;
      final placed = await ref
          .read(apiClientProvider)
          .placeOrder(
            address: address,
            paymentMethod: _paymentMethod,
            clientRequestId: _requestId,
            fulfillmentMethod: method,
            deliveryLatitude: pin?.latitude,
            deliveryLongitude: pin?.longitude,
            prescription: _prescription,
          );

      await ref.read(cartProvider.notifier).refresh();

      if (!mounted) {
        return;
      }

      if (placed.paymentUrl != null) {
        final uri = Uri.tryParse(placed.paymentUrl!);

        if (uri != null) {
          await launchUrl(uri, mode: LaunchMode.externalApplication);
        }
      }

      if (placed.paymentError != null && mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(placed.paymentError!)));
      }

      if (mounted) {
        context.pushReplacement('/orders/${placed.order.id}');
      }
    } on LocationFailure {
      if (mounted) {
        _snack('Turn on location for delivery, or choose pickup.');
      }
    } on ApiException catch (error) {
      if (mounted) {
        _snack(error.friendly);
      }
    } finally {
      if (mounted) {
        setState(() => _placing = false);
      }
    }
  }

  Future<void> _pickPrescription({required bool photo}) async {
    final picker = ref.read(attachmentPickerProvider);
    try {
      final picked = photo
          ? await picker.pickImage()
          : await picker.pickDocument();
      if (picked != null && mounted) {
        setState(() => _prescription = picked);
      }
    } on AttachmentTooLargeException {
      _snack('Choose a file under 5 MB.');
    }
  }

  String _total(CheckoutQuote quote) => addMoney(
    quote.fulfillment[_method]?.total ?? quote.subtotal,
    _paymentFee(quote, _paymentMethod) ?? '0.00',
  );

  void _snack(String message) {
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    final quote = ref.watch(checkoutQuoteProvider);
    // Delivery depends on where the phone is relative to the branch.
    ref.watch(nearestStoreProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Checkout')),
      body: AsyncView(
        value: quote,
        onRetry: () {
          _initialised = false;
          ref.invalidate(checkoutQuoteProvider);
        },
        builder: (value) {
          _initialise(value);

          return Column(
            children: [
              Expanded(
                child: Form(
                  key: _formKey,
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                    children: [
                      _itemsCard(value),
                      const SizedBox(height: 14),
                      _fulfillmentCard(value),
                      if (value.needsPrescription) ...[
                        const SizedBox(height: 14),
                        _prescriptionCard(),
                      ],
                      const SizedBox(height: 14),
                      _addressCard(value),
                      const SizedBox(height: 14),
                      _paymentCard(value),
                      const SizedBox(height: 14),
                      _totalsCard(value),
                    ],
                  ),
                ),
              ),
              SafeArea(
                top: false,
                child: Container(
                  padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
                  decoration: BoxDecoration(
                    color: Theme.of(context).colorScheme.surfaceContainerLowest,
                    border: Border(
                      top: BorderSide(
                        color: Theme.of(context).colorScheme.outlineVariant,
                      ),
                    ),
                  ),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      LayoutBuilder(
                        builder: (context, constraints) {
                          final largeText =
                              MediaQuery.textScalerOf(context).scale(14) > 18;
                          final total = Text(
                            money(_total(value)),
                            textAlign: TextAlign.end,
                            style: Theme.of(context).textTheme.titleLarge
                                ?.copyWith(
                                  fontWeight: FontWeight.w800,
                                  color: Theme.of(context).colorScheme.primary,
                                ),
                          );

                          if (largeText) {
                            return Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                Text(
                                  'Total',
                                  style: Theme.of(context).textTheme.bodyMedium
                                      ?.copyWith(
                                        color: Theme.of(context)
                                            .colorScheme
                                            .onSurfaceVariant,
                                      ),
                                ),
                                const SizedBox(height: 2),
                                Align(
                                  alignment: Alignment.centerRight,
                                  child: FittedBox(
                                    fit: BoxFit.scaleDown,
                                    alignment: Alignment.centerRight,
                                    child: total,
                                  ),
                                ),
                              ],
                            );
                          }

                          return Row(
                            children: [
                              Expanded(
                                child: Text(
                                  'Total',
                                  style: Theme.of(context).textTheme.bodyMedium
                                      ?.copyWith(
                                        color: Theme.of(context)
                                            .colorScheme
                                            .onSurfaceVariant,
                                      ),
                                ),
                              ),
                              const SizedBox(width: 12),
                              Flexible(
                                child: FittedBox(
                                  fit: BoxFit.scaleDown,
                                  alignment: Alignment.centerRight,
                                  child: total,
                                ),
                              ),
                            ],
                          );
                        },
                      ),
                      const SizedBox(height: 8),
                      FilledButton(
                        onPressed: _placing ? null : () => _placeOrder(value),
                        child: _placing
                            ? const SizedBox(
                                width: 22,
                                height: 22,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              )
                            : const Text('Place order'),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _itemsCard(CheckoutQuote quote) {
    final largeText = MediaQuery.textScalerOf(context).scale(14) > 18;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              '${quote.lines.length} ${quote.lines.length == 1 ? 'item' : 'items'} from ${quote.branchName ?? 'your branch'}',
              style: Theme.of(context).textTheme.titleSmall
                  ?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            ...quote.lines.map(
              (line) => Padding(
                padding: const EdgeInsets.symmetric(vertical: 6),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    NetworkThumb(
                      url: line.imageUrl,
                      width: 44,
                      height: 44,
                      radius: appDetailRadius,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            line.productName,
                            maxLines: largeText ? 2 : 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontWeight: FontWeight.w600),
                          ),
                          Text(
                            '${line.quantity} × ${money(line.unitPrice)}',
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                          if (largeText) ...[
                            const SizedBox(height: 4),
                            FittedBox(
                              fit: BoxFit.scaleDown,
                              alignment: Alignment.centerLeft,
                              child: Text(
                                money(line.lineTotal),
                                style: const TextStyle(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                    if (!largeText)
                      Text(
                        money(line.lineTotal),
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _addressCard(CheckoutQuote quote) {
    final theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              _method == 'pickup' ? 'Contact details' : 'Delivery address',
              style: theme.textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 8),
            RadioGroup<String>(
              groupValue: _useNewAddress ? '__new__' : _selectedAddressId,
              onChanged: (value) {
                if (value == null) {
                  return;
                }

                if (value == '__new__') {
                  setState(() {
                    _useNewAddress = true;

                    if (_recipient.text.isEmpty &&
                        quote.preferredAddress != null) {
                      _fill(quote.preferredAddress!);
                    }
                  });
                } else {
                  setState(() {
                    _useNewAddress = false;
                    _selectedAddressId = value;
                  });
                }
              },
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  ...quote.addresses.map(
                    (address) => RadioListTile<String>(
                      contentPadding: EdgeInsets.zero,
                      value: address.id ?? address.addressLine,
                      title: Text(
                        address.recipientName,
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                      subtitle: Text(
                        '${address.addressLine}, ${address.city}, ${address.province} ${address.postalCode}\n${address.phone}',
                      ),
                      isThreeLine: true,
                    ),
                  ),
                  const RadioListTile<String>(
                    contentPadding: EdgeInsets.zero,
                    value: '__new__',
                    title: Text('Use a new address'),
                  ),
                ],
              ),
            ),
            if (_useNewAddress) ...[
              const SizedBox(height: 6),
              TextFormField(
                controller: _recipient,
                textCapitalization: TextCapitalization.words,
                decoration: const InputDecoration(labelText: 'Recipient name'),
                validator: (value) => (value ?? '').trim().isEmpty
                    ? 'Enter the recipient name.'
                    : null,
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _phone,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(
                  labelText: 'Phone',
                  hintText: '09xxxxxxxxx',
                ),
                validator: (value) =>
                    RegExp(r'^(?:\+63|0)9\d{9}$').hasMatch((value ?? '').trim())
                    ? null
                    : 'Use a valid PH mobile number.',
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _addressLine,
                textCapitalization: TextCapitalization.words,
                decoration: const InputDecoration(labelText: 'Street address'),
                validator: (value) => (value ?? '').trim().isEmpty
                    ? 'Enter the street address.'
                    : null,
              ),
              const SizedBox(height: 10),
              _cityProvinceFields(context),
              const SizedBox(height: 10),
              TextFormField(
                controller: _postalCode,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Postal code'),
                validator: (value) =>
                    RegExp(r'^\d{4}$').hasMatch((value ?? '').trim())
                    ? null
                    : 'Use a 4-digit postal code.',
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _cityProvinceFields(BuildContext context) {
    final city = TextFormField(
      controller: _city,
      textCapitalization: TextCapitalization.words,
      decoration: const InputDecoration(labelText: 'City'),
      validator: (value) =>
          (value ?? '').trim().isEmpty ? 'Enter the city.' : null,
    );
    final province = TextFormField(
      controller: _province,
      textCapitalization: TextCapitalization.words,
      decoration: const InputDecoration(labelText: 'Province'),
      validator: (value) =>
          (value ?? '').trim().isEmpty ? 'Enter the province.' : null,
    );

    return LayoutBuilder(
      builder: (context, constraints) {
        // Side-by-side labels clip on narrow screens with large text.
        final stack =
            constraints.maxWidth < 300 ||
            MediaQuery.textScalerOf(context).scale(14) > 21;

        if (stack) {
          return Column(children: [city, const SizedBox(height: 10), province]);
        }

        return Row(
          children: [
            Expanded(child: city),
            const SizedBox(width: 10),
            Expanded(child: province),
          ],
        );
      },
    );
  }

  Widget _paymentCard(CheckoutQuote quote) {
    final theme = Theme.of(context);
    final largeText = MediaQuery.textScalerOf(context).scale(14) > 18;
    final labels = {
      'cod': _method == 'pickup' ? 'Pay at the counter' : 'Cash on delivery',
      'gcash': 'GCash',
      'maya': 'Maya',
      'card': 'Card',
    };

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Payment',
              style: theme.textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 4),
            RadioGroup<String>(
              groupValue: _paymentMethod,
              onChanged: (value) {
                if (value != null) {
                  setState(() => _paymentMethod = value);
                }
              },
              child: Column(
                children: quote.paymentOptions.entries.map((entry) {
                  // The cash-on-delivery limit is for deliveries; the counter stays open.
                  final option = entry.key == 'cod' && _method == 'pickup'
                      ? const PaymentOption(available: true, fee: '0.00')
                      : PaymentOption(
                          available: entry.value.available,
                          fee: _paymentFee(quote, entry.key),
                          reason: entry.value.reason,
                        );

                  return RadioListTile<String>(
                    contentPadding: EdgeInsets.zero,
                    value: entry.key,
                    enabled: option.available,
                    title: largeText
                        ? Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                labels[entry.key] ?? entry.key,
                                style: const TextStyle(
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              if (option.fee != null &&
                                  double.tryParse(option.fee!) != 0)
                                Text(
                                  '+ ${money(option.fee)}',
                                  style: theme.textTheme.bodySmall,
                                ),
                            ],
                          )
                        : Row(
                            children: [
                              Expanded(
                                child: Text(
                                  labels[entry.key] ?? entry.key,
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ),
                              if (option.fee != null &&
                                  double.tryParse(option.fee!) != 0) ...[
                                const SizedBox(width: 8),
                                Text(
                                  '+ ${money(option.fee)}',
                                  style: theme.textTheme.bodySmall,
                                ),
                              ],
                            ],
                          ),
                    subtitle: option.available
                        ? null
                        : Text(
                            option.reason ?? 'Not available for this cart.',
                            style: theme.textTheme.bodySmall,
                          ),
                  );
                }).toList(),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _fulfillmentCard(CheckoutQuote quote) {
    final theme = Theme.of(context);
    final blocked = _deliveryBlockedReason;
    final deliveryFee = quote.fulfillment['delivery']?.fee ?? '0.00';
    final branch = quote.branchName ?? 'your branch';
    final branchAddress = quote.branchAddress ?? '';

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              'How to get it',
              style: theme.textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 8),
            SegmentedButton<String>(
              segments: [
                ButtonSegment(
                  value: 'delivery',
                  enabled: blocked == null,
                  label: Text(
                    (double.tryParse(deliveryFee) ?? 0) > 0
                        ? 'Delivery · ${money(deliveryFee)}'
                        : 'Delivery · Free',
                  ),
                ),
                const ButtonSegment(
                  value: 'pickup',
                  label: Text('Pickup · Free'),
                ),
              ],
              selected: {_method},
              onSelectionChanged: (choice) =>
                  setState(() => _fulfillment = choice.single),
            ),
            const SizedBox(height: 8),
            Text(
              _method == 'pickup'
                  ? [
                      'Pick up at $branch',
                      if (branchAddress.isNotEmpty) branchAddress,
                    ].join(' · ')
                  : 'Delivered from $branch to where you are now.',
              style: theme.textTheme.bodySmall,
            ),
            if (blocked != null) ...[
              const SizedBox(height: 4),
              Text(
                blocked,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _prescriptionCard() {
    final theme = Theme.of(context);
    final picked = _prescription;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              'Prescription',
              style: theme.textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              picked == null
                  ? 'Some items need a doctor’s prescription. A pharmacist checks it before your order is prepared.'
                  : 'Added: ${picked.name}',
              style: theme.textTheme.bodySmall,
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                OutlinedButton.icon(
                  onPressed: () => _pickPrescription(photo: true),
                  icon: const Icon(Icons.photo_outlined, size: 18),
                  label: Text(picked == null ? 'Add photo' : 'Replace photo'),
                ),
                OutlinedButton.icon(
                  onPressed: () => _pickPrescription(photo: false),
                  icon: const Icon(Icons.picture_as_pdf_outlined, size: 18),
                  label: const Text('Add PDF'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _totalsCard(CheckoutQuote quote) {
    final theme = Theme.of(context);
    final fee = _paymentFee(quote, _paymentMethod) ?? '0.00';
    final deliveryFee = quote.fulfillment[_method]?.fee ?? '0.00';
    final total = _total(quote);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          children: [
            ...quote.storeTotals.map(
              (store) => Padding(
                padding: const EdgeInsets.symmetric(vertical: 3),
                child: _amountRow(
                  label: _storeTotalLabel(quote, store),
                  amount: Text(money(store.total)),
                ),
              ),
            ),
            const Divider(height: 20),
            _amountRow(
              label: const Text('Subtotal'),
              amount: Text(money(quote.subtotal)),
            ),
            if ((double.tryParse(deliveryFee) ?? 0) > 0)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: _amountRow(
                  label: const Text('Delivery fee'),
                  amount: Text(money(deliveryFee)),
                ),
              ),
            if ((double.tryParse(fee) ?? 0) > 0)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: _amountRow(
                  label: Text(
                    'Online payment fee (${_paymentMethod.toUpperCase()})',
                  ),
                  amount: Text(money(fee)),
                ),
              ),
            const SizedBox(height: 8),
            _amountRow(
              label: Text(
                'Total',
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
              amount: Text(
                money(total),
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                  color: theme.colorScheme.primary,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _storeTotalLabel(CheckoutQuote quote, StoreTotal store) {
    final slugs = quote.lines
        .where((line) => line.storeName == store.name)
        .map((line) => line.storeSlug)
        .toSet();
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        StoreAvatar(
          name: store.name,
          storeSlug: slugs.length == 1 ? slugs.single : null,
          radius: 14,
        ),
        const SizedBox(width: 8),
        Flexible(child: Text(store.name)),
      ],
    );
  }

  Widget _amountRow({required Widget label, required Widget amount}) {
    if (MediaQuery.textScalerOf(context).scale(14) <= 18) {
      return Row(
        children: [
          Expanded(child: label),
          const SizedBox(width: 8),
          amount,
        ],
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        label,
        const SizedBox(height: 2),
        Align(
          alignment: Alignment.centerRight,
          child: FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerRight,
            child: amount,
          ),
        ),
      ],
    );
  }
}
