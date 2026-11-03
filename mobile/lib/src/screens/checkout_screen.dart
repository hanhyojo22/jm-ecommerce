import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:uuid/uuid.dart';

import '../core/api_exception.dart';
import '../core/formatters.dart';
import '../models/models.dart';
import '../providers/auth_providers.dart';
import '../providers/cart_providers.dart';
import '../providers/checkout_providers.dart';
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
  bool _placing = false;

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
    if (!_useNewAddress && _selectedAddressId != null) {
      for (final address in quote.addresses) {
        if (address.id == _selectedAddressId) {
          return address;
        }
      }
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

    setState(() => _placing = true);

    try {
      final placed = await ref
          .read(apiClientProvider)
          .placeOrder(
            address: address,
            paymentMethod: _paymentMethod,
            clientRequestId: _requestId,
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

  void _snack(String message) {
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    final quote = ref.watch(checkoutQuoteProvider);

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
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                  child: FilledButton(
                    onPressed: _placing ? null : () => _placeOrder(value),
                    child: _placing
                        ? const SizedBox(
                            width: 22,
                            height: 22,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : Text('Place order · ${money(value.subtotal)}'),
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
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              '${quote.lines.length} ${quote.lines.length == 1 ? 'item' : 'items'} from ${quote.storeTotals.length} ${quote.storeTotals.length == 1 ? 'store' : 'stores'}',
              style: Theme.of(context).textTheme.titleSmall
                  ?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            ...quote.lines.map(
              (line) => Padding(
                padding: const EdgeInsets.symmetric(vertical: 6),
                child: Row(
                  children: [
                    NetworkThumb(
                      url: line.imageUrl,
                      width: 44,
                      height: 44,
                      radius: 10,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            line.productName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontWeight: FontWeight.w600),
                          ),
                          Text(
                            '${line.quantity} × ${money(line.unitPrice)}',
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ],
                      ),
                    ),
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
              'Delivery address',
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
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      controller: _city,
                      textCapitalization: TextCapitalization.words,
                      decoration: const InputDecoration(labelText: 'City'),
                      validator: (value) => (value ?? '').trim().isEmpty
                          ? 'Enter the city.'
                          : null,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: TextFormField(
                      controller: _province,
                      textCapitalization: TextCapitalization.words,
                      decoration: const InputDecoration(labelText: 'Province'),
                      validator: (value) => (value ?? '').trim().isEmpty
                          ? 'Enter the province.'
                          : null,
                    ),
                  ),
                ],
              ),
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

  Widget _paymentCard(CheckoutQuote quote) {
    final theme = Theme.of(context);
    const labels = {
      'cod': 'Cash on delivery',
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
                  final option = entry.value;

                  return RadioListTile<String>(
                    contentPadding: EdgeInsets.zero,
                    value: entry.key,
                    enabled: option.available,
                    title: Row(
                      children: [
                        Text(
                          labels[entry.key] ?? entry.key,
                          style: const TextStyle(fontWeight: FontWeight.w600),
                        ),
                        if (option.fee != null &&
                            double.tryParse(option.fee!) != 0) ...[
                          const Spacer(),
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

  Widget _totalsCard(CheckoutQuote quote) {
    final fee = quote.paymentOptions[_paymentMethod]?.fee ?? '0.00';
    final total =
        (double.tryParse(quote.subtotal) ?? 0) + (double.tryParse(fee) ?? 0);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          children: [
            ...quote.storeTotals.map(
              (store) => Padding(
                padding: const EdgeInsets.symmetric(vertical: 3),
                child: Row(
                  children: [
                    Expanded(child: Text(store.name)),
                    Text(money(store.total)),
                  ],
                ),
              ),
            ),
            const Divider(height: 20),
            Row(
              children: [
                const Expanded(child: Text('Subtotal')),
                Text(money(quote.subtotal)),
              ],
            ),
            if ((double.tryParse(fee) ?? 0) > 0)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        'Online payment fee (${_paymentMethod.toUpperCase()})',
                      ),
                    ),
                    Text(money(fee)),
                  ],
                ),
              ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: Text(
                    'Total',
                    style: Theme.of(context).textTheme.titleMedium
                        ?.copyWith(fontWeight: FontWeight.w800),
                  ),
                ),
                Text(
                  money(total.toStringAsFixed(2)),
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: Theme.of(context).colorScheme.primary,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
