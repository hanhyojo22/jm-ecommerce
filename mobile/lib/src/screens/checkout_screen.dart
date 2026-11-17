import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:go_router/go_router.dart';
import 'package:latlong2/latlong.dart';
import 'package:phosphor_icons/phosphor_icons.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:uuid/uuid.dart';

import '../core/api_exception.dart';
import '../core/attachment_picker.dart';
import '../core/formatters.dart';
import '../core/location_service.dart';
import '../models/models.dart';
import '../models/store_branch.dart';
import '../providers/auth_providers.dart';
import '../providers/cart_providers.dart';
import '../providers/checkout_providers.dart';
import '../providers/store_locator_providers.dart';
import '../widgets/common.dart';
import '../widgets/delivery_pin_map.dart';

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

  /// The city of a reused address; a new address takes the branch's city.
  String _savedCity = '';

  final _requestId = const Uuid().v4();
  String? _selectedAddressId;
  bool _useNewAddress = false;
  bool _initialised = false;
  String _paymentMethod = 'cod';
  String _fulfillment = 'delivery';
  PickedAttachment? _prescription;
  bool _placing = false;

  /// Where the rider goes. Starts at the phone's location, or at the branch
  /// when location is off, and follows the map as the customer moves it.
  LatLng? _pin;
  bool _locatedPhone = false;

  /// The branch the cart buys from, which the delivery radius is measured from.
  StoreBranch? _deliveryBranch;

  /// True once locating is over; with no pin by then, only pickup works.
  bool _located = false;

  @override
  void initState() {
    super.initState();
    _locate();
  }

  Future<void> _locate() async {
    _deliveryBranch = await _cartBranch();
    LatLng? start;
    try {
      final here = await ref.read(locationServiceProvider).currentPosition();
      start = LatLng(here.latitude, here.longitude);
      _locatedPhone = true;
    } on LocationFailure {
      final branch = _deliveryBranch;
      start = branch == null ? null : LatLng(branch.latitude, branch.longitude);
    }
    if (mounted) {
      setState(() {
        _pin ??= start;
        _located = true;
      });
    }
  }

  Future<StoreBranch?> _cartBranch() async {
    try {
      final locator = await ref.read(nearestStoreProvider.future);
      final cartBranchId = await ref.read(activeBranchIdProvider.future);
      return locator.branches
              .where((item) => item.branch.branchId == cartBranchId)
              .firstOrNull
              ?.branch ??
          locator.activeBranch?.branch;
    } catch (_) {
      return null;
    }
  }

  /// Why the pin cannot be delivered to, checked like the API does.
  String? get _pinProblem {
    final branch = _deliveryBranch;
    final pin = _pin;
    final radius = branch?.maxDeliveryRadiusKm;
    if (branch == null || pin == null || radius == null) {
      return null;
    }
    final km =
        Geolocator.distanceBetween(
          branch.latitude,
          branch.longitude,
          pin.latitude,
          pin.longitude,
        ) /
        1000;
    return km > radius
        ? 'This pin is ${km.toStringAsFixed(1)} km away. ${branch.name} delivers within ${radius.toStringAsFixed(0)} km. Move the pin or choose pickup.'
        : null;
  }

  String get _method => _fulfillment;

  /// The online payment fee on what the chosen fulfillment brings the total to.
  String? _paymentFee(CheckoutQuote quote, String method) =>
      quote.fulfillment[_method]?.paymentFees[method] ??
      quote.paymentOptions[method]?.fee;

  @override
  void dispose() {
    _recipient.dispose();
    _phone.dispose();
    _addressLine.dispose();
    super.dispose();
  }

  void _initialise(CheckoutQuote quote) {
    if (_initialised) {
      return;
    }

    _initialised = true;

    if (quote.fulfillment['delivery']?.closedUntil != null) {
      _fulfillment = 'pickup';
    }

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
    _savedCity = address.city;
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
      city: _savedCity.isNotEmpty ? _savedCity : (quote.branchCity ?? ''),
      province: '',
      postalCode: '',
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

    if (_method == 'delivery') {
      if (_pin == null) {
        _snack('Move the map to set the delivery pin.');
        return;
      }
      final problem = _pinProblem;
      if (problem != null) {
        _snack(problem);
        return;
      }
    }

    setState(() => _placing = true);

    try {
      final method = _method;
      final pin = method == 'delivery' ? _pin : null;
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
                    padding: const EdgeInsets.fromLTRB(12, 8, 12, 24),
                    children: [
                      _fulfillmentCard(value),
                      const SizedBox(height: 12),
                      _addressCard(value),
                      if (value.needsPrescription) ...[
                        const SizedBox(height: 12),
                        _prescriptionCard(),
                      ],
                      const SizedBox(height: 12),
                      _paymentCard(value),
                      const SizedBox(height: 12),
                      _summaryCard(value),
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
                                  letterSpacing: -0.5,
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
                      const SizedBox(height: 10),
                      FilledButton(
                        style: FilledButton.styleFrom(
                          minimumSize: const Size.fromHeight(54),
                          shape: const StadiumBorder(),
                        ),
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

  /// One card per checkout step, each opened by the same icon-and-title header.
  Widget _section({
    required IconData icon,
    required String title,
    required List<Widget> children,
  }) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Container(
                  width: 32,
                  height: 32,
                  decoration: BoxDecoration(
                    color: scheme.primaryContainer,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Icon(icon, size: 18, color: scheme.primary),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    title,
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            ...children,
          ],
        ),
      ),
    );
  }

  /// Bordered, highlighted Material radio tile shared by addresses and payment.
  ShapeBorder _choiceShape(bool selected) {
    final scheme = Theme.of(context).colorScheme;
    return RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(14),
      side: BorderSide(
        color: selected ? scheme.primary : scheme.outlineVariant,
        width: selected ? 1.6 : 1.2,
      ),
    );
  }

  Widget _summaryCard(CheckoutQuote quote) {
    final theme = Theme.of(context);
    final largeText = MediaQuery.textScalerOf(context).scale(14) > 18;
    final fee = _paymentFee(quote, _paymentMethod) ?? '0.00';
    final deliveryFee = quote.fulfillment[_method]?.fee ?? '0.00';
    final count = quote.lines.length;

    return _section(
      icon: Icons.receipt_long_outlined,
      title: 'Order summary',
      children: [
        Text(
          '$count ${count == 1 ? 'item' : 'items'} from ${quote.branchName ?? 'your branch'}',
          style: theme.textTheme.bodySmall?.copyWith(
            color: theme.colorScheme.onSurfaceVariant,
          ),
        ),
        const SizedBox(height: 6),
        ...quote.lines.map(
          (line) => Padding(
            padding: const EdgeInsets.symmetric(vertical: 6),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                NetworkThumb(
                  url: line.imageUrl,
                  width: 48,
                  height: 48,
                  radius: 10,
                  placeholderIcon: PhosphorIconsRegular.pill,
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
                            style: const TextStyle(fontWeight: FontWeight.w700),
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
        const Divider(height: 24),
        // One branch's total would only repeat the subtotal.
        if (quote.storeTotals.length > 1) ...[
          ...quote.storeTotals.map(
            (store) => Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: _amountRow(
                label: _storeTotalLabel(quote, store),
                amount: Text(money(store.total)),
              ),
            ),
          ),
        ],
        _amountRow(
          label: const Text('Subtotal'),
          amount: Text(money(quote.subtotal)),
        ),
        if ((double.tryParse(deliveryFee) ?? 0) > 0)
          Padding(
            padding: const EdgeInsets.only(top: 6),
            child: _amountRow(
              label: const Text('Delivery fee'),
              amount: Text(money(deliveryFee)),
            ),
          ),
        if ((double.tryParse(fee) ?? 0) > 0)
          Padding(
            padding: const EdgeInsets.only(top: 6),
            child: _amountRow(
              label: Text(
                'Online payment fee (${_paymentMethod.toUpperCase()})',
              ),
              amount: Text(money(fee)),
            ),
          ),
        const SizedBox(height: 10),
        _amountRow(
          label: Text(
            'Total',
            style: theme.textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w800,
            ),
          ),
          amount: Text(
            money(_total(quote)),
            style: theme.textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
      ],
    );
  }

  Widget _addressCard(CheckoutQuote quote) {
    final theme = Theme.of(context);

    final scheme = theme.colorScheme;
    final selectedValue = _useNewAddress ? '__new__' : _selectedAddressId;

    return _section(
      icon: _method == 'pickup'
          ? Icons.person_outline_rounded
          : Icons.location_on_outlined,
      title: _method == 'pickup' ? 'Contact details' : 'Deliver to',
      children: [
        if (_method == 'delivery') ...[
          _pinSection(theme),
          const SizedBox(height: 12),
        ],
        RadioGroup<String>(
          groupValue: _useNewAddress ? '__new__' : _selectedAddressId,
          onChanged: (value) {
            if (value == null) {
              return;
            }

            if (value == '__new__') {
              setState(() {
                _useNewAddress = true;

                if (_recipient.text.isEmpty && quote.preferredAddress != null) {
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
              for (final address in quote.addresses) ...[
                RadioListTile<String>(
                  contentPadding: const EdgeInsets.fromLTRB(4, 4, 14, 4),
                  value: address.id ?? address.addressLine,
                  selected:
                      selectedValue == (address.id ?? address.addressLine),
                  shape: _choiceShape(
                    selectedValue == (address.id ?? address.addressLine),
                  ),
                  tileColor: scheme.surfaceContainerLowest,
                  selectedTileColor: scheme.primaryContainer.withValues(
                    alpha: 0.35,
                  ),
                  title: Text(
                    address.recipientName,
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  subtitle: Text(
                    '${[address.addressLine, address.city].where((part) => part.isNotEmpty).join(', ')}\n${address.phone}',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: scheme.onSurfaceVariant,
                    ),
                  ),
                  isThreeLine: true,
                ),
                const SizedBox(height: 8),
              ],
              RadioListTile<String>(
                contentPadding: const EdgeInsets.fromLTRB(4, 0, 14, 0),
                value: '__new__',
                selected: selectedValue == '__new__',
                shape: _choiceShape(selectedValue == '__new__'),
                tileColor: scheme.surfaceContainerLowest,
                selectedTileColor: scheme.primaryContainer.withValues(
                  alpha: 0.35,
                ),
                title: const Text(
                  'Use a new address',
                  style: TextStyle(fontWeight: FontWeight.w600),
                ),
                secondary: Icon(
                  Icons.add_location_alt_outlined,
                  color: scheme.primary,
                ),
                controlAffinity: ListTileControlAffinity.leading,
              ),
            ],
          ),
        ),
        if (_useNewAddress) ...[
          const SizedBox(height: 14),
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
          if (_method == 'delivery') ...[
            const SizedBox(height: 10),
            TextFormField(
              controller: _addressLine,
              textCapitalization: TextCapitalization.sentences,
              decoration: const InputDecoration(
                labelText: 'House no., street, landmark',
                hintText: 'Blue gate beside the sari-sari store',
              ),
              validator: (value) => (value ?? '').trim().isEmpty
                  ? 'Tell the rider how to find you.'
                  : null,
            ),
          ],
        ],
      ],
    );
  }

  Widget _pinSection(ThemeData theme) {
    final pin = _pin;
    final problem = _pinProblem;
    final branch = _deliveryBranch;

    if (pin == null && _located) {
      return Text(
        'We could not find your location or the branch on the map. Turn on location, or choose pickup.',
        style: theme.textTheme.bodySmall?.copyWith(
          color: theme.colorScheme.error,
        ),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (pin == null)
          const SizedBox(
            height: 220,
            child: Center(
              child: CircularProgressIndicator(semanticsLabel: 'Finding you'),
            ),
          )
        else
          DeliveryPinMap(
            initial: pin,
            branch: branch == null
                ? null
                : LatLng(branch.latitude, branch.longitude),
            onMoved: (center) => setState(() => _pin = center),
          ),
        const SizedBox(height: 8),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(
              Icons.pan_tool_alt_outlined,
              size: 16,
              color: theme.colorScheme.onSurfaceVariant,
            ),
            const SizedBox(width: 6),
            Expanded(
              child: Text(
                _locatedPhone ? 'Drag the map so the pin sits on your door.' : 'We could not find your location. Drag the map to your door.',
                style: theme.textTheme.bodySmall,
              ),
            ),
          ],
        ),
        if (problem != null) ...[
          const SizedBox(height: 4),
          Text(
            problem,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.error,
            ),
          ),
        ],
      ],
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

    final scheme = theme.colorScheme;
    final options = [
      for (final entry in quote.paymentOptions.entries)
        (
          key: entry.key,
          // The cash-on-delivery limit is for deliveries; the counter stays open.
          option: entry.key == 'cod' && _method == 'pickup'
              ? const PaymentOption(available: true, fee: '0.00')
              : PaymentOption(
                  available: entry.value.available,
                  fee: _paymentFee(quote, entry.key),
                  reason: entry.value.reason,
                ),
        ),
    ];
    // Cash keeps its own row (and reason) even when unavailable; online methods
    // that are off for the same reason fold into one line instead of a row each.
    final shown = options
        .where((o) => o.option.available || o.key == 'cod')
        .toList();
    final unavailableByReason = <String, List<String>>{};
    for (final o in options.where(
      (o) => !o.option.available && o.key != 'cod',
    )) {
      unavailableByReason
          .putIfAbsent(
            o.option.reason ?? 'Not available for this cart.',
            () => [],
          )
          .add(labels[o.key] ?? o.key);
    }
    String joinNames(List<String> names) => names.length < 2
        ? names.join()
        : '${names.sublist(0, names.length - 1).join(', ')} and ${names.last}';

    return _section(
      icon: Icons.payments_outlined,
      title: 'Payment',
      children: [
        RadioGroup<String>(
          groupValue: _paymentMethod,
          onChanged: (value) {
            if (value != null) {
              setState(() => _paymentMethod = value);
            }
          },
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: shown.map((entry) {
              final option = entry.option;

              return Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: RadioListTile<String>(
                  contentPadding: const EdgeInsets.fromLTRB(4, 0, 14, 0),
                  value: entry.key,
                  enabled: option.available,
                  selected: _paymentMethod == entry.key,
                  shape: _choiceShape(_paymentMethod == entry.key),
                  tileColor: scheme.surfaceContainerLowest,
                  selectedTileColor: scheme.primaryContainer.withValues(
                    alpha: 0.35,
                  ),
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
                ),
              );
            }).toList(),
          ),
        ),
        for (final group in unavailableByReason.entries)
          Padding(
            padding: const EdgeInsets.only(top: 2),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  Icons.info_outline_rounded,
                  size: 16,
                  color: scheme.onSurfaceVariant,
                ),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    '${joinNames(group.value)}: ${group.key}',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: scheme.onSurfaceVariant,
                    ),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }

  Widget _fulfillmentCard(CheckoutQuote quote) {
    final theme = Theme.of(context);
    final deliveryFee = quote.fulfillment['delivery']?.fee ?? '0.00';
    final branch = quote.branchName ?? 'your branch';
    final branchAddress = quote.branchAddress ?? '';
    final closedUntil = quote.fulfillment['delivery']?.closedUntil;

    final largeText = MediaQuery.textScalerOf(context).scale(14) > 18;
    final delivery = _fulfillmentOption(
      value: 'delivery',
      icon: Icons.delivery_dining_outlined,
      label: (double.tryParse(deliveryFee) ?? 0) > 0
          ? 'Delivery · ${money(deliveryFee)}'
          : 'Delivery · Free',
      detail: closedUntil == null ? 'To your door' : 'Closed right now',
      enabled: closedUntil == null,
    );
    final pickup = _fulfillmentOption(
      value: 'pickup',
      icon: Icons.storefront_outlined,
      label: 'Pickup · Free',
      detail: 'At the branch',
      enabled: true,
    );

    return _section(
      icon: Icons.local_mall_outlined,
      title: 'How to get it',
      children: [
        if (largeText) ...[
          delivery,
          const SizedBox(height: 10),
          pickup,
        ] else
          IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Expanded(child: delivery),
                const SizedBox(width: 10),
                Expanded(child: pickup),
              ],
            ),
          ),
        if (closedUntil != null) ...[
          const SizedBox(height: 10),
          Text(
            '$branch is closed. Delivery opens ${opensLabel(closedUntil)}; you can still order for pickup.',
            style: theme.textTheme.bodyMedium?.copyWith(
              color: theme.colorScheme.error,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
        const SizedBox(height: 10),
        Text(
          _method == 'pickup'
              ? [
                  'Pick up at $branch',
                  if (branchAddress.isNotEmpty) branchAddress,
                ].join(' · ')
              : 'Delivered from $branch to your pin.',
          style: theme.textTheme.bodySmall?.copyWith(
            color: theme.colorScheme.onSurfaceVariant,
          ),
        ),
      ],
    );
  }

  /// A selectable delivery or pickup tile; a closed option cannot be tapped.
  Widget _fulfillmentOption({
    required String value,
    required IconData icon,
    required String label,
    required String detail,
    required bool enabled,
  }) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final selected = _method == value;
    final shape = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(14),
      side: BorderSide(
        color: selected ? scheme.primary : scheme.outlineVariant,
        width: selected ? 1.6 : 1.2,
      ),
    );

    return Semantics(
      selected: selected,
      inMutuallyExclusiveGroup: true,
      child: Material(
        color: selected
            ? scheme.primaryContainer.withValues(alpha: 0.35)
            : scheme.surfaceContainerLowest,
        shape: shape,
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: enabled ? () => setState(() => _fulfillment = value) : null,
          child: Opacity(
            opacity: enabled ? 1 : 0.45,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(14, 12, 12, 12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Icon(icon, color: scheme.primary),
                      const Spacer(),
                      Icon(
                        selected
                            ? Icons.check_circle_rounded
                            : Icons.circle_outlined,
                        size: 20,
                        color: selected ? scheme.primary : scheme.outline,
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  Text(
                    label,
                    style: theme.textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    detail,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: scheme.onSurfaceVariant,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _prescriptionCard() {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final picked = _prescription;
    final largeText = MediaQuery.textScalerOf(context).scale(14) > 18;
    final pill = const StadiumBorder();
    final photoButton = FilledButton.tonalIcon(
      style: FilledButton.styleFrom(
        minimumSize: const Size(0, 48),
        shape: pill,
      ),
      onPressed: () => _pickPrescription(photo: true),
      icon: const Icon(Icons.photo_camera_outlined, size: 18),
      label: Text(
        picked == null ? 'Add photo' : 'Replace photo',
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
      ),
    );
    final pdfButton = OutlinedButton.icon(
      style: OutlinedButton.styleFrom(
        minimumSize: const Size(0, 48),
        shape: pill,
      ),
      onPressed: () => _pickPrescription(photo: false),
      icon: const Icon(Icons.picture_as_pdf_outlined, size: 18),
      label: const Text(
        'Add PDF',
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
      ),
    );

    return _section(
      icon: Icons.medication_outlined,
      title: 'Prescription',
      children: [
        DecoratedBox(
          decoration: BoxDecoration(
            color: picked == null
                ? scheme.tertiaryContainer
                : scheme.primaryContainer.withValues(alpha: 0.5),
            borderRadius: BorderRadius.circular(14),
          ),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  picked == null
                      ? Icons.receipt_long_outlined
                      : Icons.check_circle_rounded,
                  size: 20,
                  color: picked == null
                      ? scheme.onTertiaryContainer
                      : scheme.primary,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    picked == null
                        ? 'Some items need a doctor’s prescription. A pharmacist checks it before your order is prepared.'
                        : 'Added: ${picked.name}',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: picked == null
                          ? scheme.onTertiaryContainer
                          : scheme.onSurface,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        if (largeText) ...[
          photoButton,
          const SizedBox(height: 8),
          pdfButton,
        ] else
          Row(
            children: [
              Expanded(child: photoButton),
              const SizedBox(width: 10),
              Expanded(child: pdfButton),
            ],
          ),
      ],
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
