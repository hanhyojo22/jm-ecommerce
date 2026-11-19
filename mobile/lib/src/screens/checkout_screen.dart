import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
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
import '../theme.dart';
import '../widgets/common.dart';
import '../widgets/delivery_pin_map.dart';
import 'delivery_pin_screen.dart';
import '../widgets/store_locator.dart';

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
  final _cardNumber = TextEditingController();
  final _cardName = TextEditingController();

  /// Senior citizen / PWD discount: on when the customer ticks it, with the card they will show.
  bool _useCard = false;
  String _cardType = 'senior';

  /// The saved ID chosen at checkout; null means the customer typed a new one.
  String? _savedCardId;
  bool _saveCard = false;

  /// Photo of the discount ID; needed unless the chosen saved ID has one on file.
  PickedAttachment? _cardPhoto;

  /// The city of a reused address; a new address takes the branch's city.
  String _savedCity = '';

  final _requestId = const Uuid().v4();
  String? _selectedAddressId;
  bool _useNewAddress = false;
  bool _initialised = false;
  String _paymentMethod = 'cod';
  String _fulfillment = 'delivery';
  List<PickedAttachment> _prescriptions = const [];
  bool _placing = false;

  /// Where the rider goes. Starts at the phone's location, or at the branch
  /// when location is off, and follows the map as the customer moves it.
  LatLng? _pin;

  /// The pin is trusted once it came from the phone or the customer confirmed it;
  /// a pin parked on the branch because location was off is not.
  bool _pinConfirmed = false;

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
    // The delivery pin starts at the phone when location is allowed.
    if (mounted) {
      await askForLocation(context, ref, automatic: true);
    }
    LatLng? start;
    try {
      final here = await ref.read(locationServiceProvider).currentPosition();
      start = LatLng(here.latitude, here.longitude);
      _pinConfirmed = true;
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
    final km = pinDistanceKm(branch, pin);
    return km > radius
        ? 'This pin is ${km.toStringAsFixed(1)} km away. ${branch.name} delivers within ${radius.toStringAsFixed(0)} km. Move the pin or choose pickup.'
        : null;
  }

  String get _method => _fulfillment;

  /// The online payment fee on what the chosen fulfillment brings the total to.
  String? _paymentFee(CheckoutQuote quote, String method) =>
      (_cardDiscount(quote) != null
          ? quote.fulfillment[_method]?.paymentFeesWithCard[method]
          : null) ??
      quote.fulfillment[_method]?.paymentFees[method] ??
      quote.paymentOptions[method]?.fee;

  @override
  void dispose() {
    _recipient.dispose();
    _phone.dispose();
    _addressLine.dispose();
    _cardNumber.dispose();
    _cardName.dispose();
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

    // A saved ID is ready to use; typing a different one is the exception.
    _savedCardId = quote.discountCards.firstOrNull?.id;
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

    if (quote.needsPrescription && _prescriptions.isEmpty) {
      _snack('Add a photo of your prescription.');
      return;
    }

    final useCard = _useCard && quote.offersSeniorPwdDiscount;
    if (useCard &&
        _savedCardId == null &&
        (_cardNumber.text.trim().isEmpty || _cardName.text.trim().isEmpty)) {
      _snack('Enter the ID number and the name on the ID for the discount.');
      return;
    }
    if (useCard && _cardPhoto == null && !_savedCardHasPhoto(quote)) {
      _snack('Add a photo of your senior citizen or PWD ID.');
      return;
    }

    if (_method == 'delivery') {
      if (_pin == null || !_pinConfirmed) {
        _snack('Set the delivery pin so the rider can find you.');
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
            prescriptions: _prescriptions,
            discountCard: useCard && _savedCardId == null
                ? (
                    type: _cardType,
                    idNumber: _cardNumber.text.trim(),
                    name: _cardName.text.trim(),
                  )
                : null,
            discountCardId: useCard ? _savedCardId : null,
            saveDiscountCard: useCard && _savedCardId == null && _saveCard,
            discountDocument: useCard ? _cardPhoto : null,
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
        // The saved ID may have been deleted on another device; fall back to typing it.
        final lostSavedCard =
            _savedCardId != null &&
            (error.statusCode == 404 ||
                (error.statusCode == 422 &&
                    error.fields.containsKey('discount_card_id')));

        if (lostSavedCard) {
          setState(() => _savedCardId = null);
        }

        _snack(
          lostSavedCard
              ? 'That saved ID is no longer available. Type it again and place the order.'
              : error.friendly,
        );
      }
    } finally {
      if (mounted) {
        setState(() => _placing = false);
      }
    }
  }

  Future<void> _openPinPicker() async {
    final start = _pin;
    if (start == null) {
      return;
    }
    final choice = await Navigator.of(context).push<PinChoice>(
      MaterialPageRoute(
        fullscreenDialog: true,
        builder: (_) =>
            DeliveryPinScreen(initial: start, branch: _deliveryBranch),
      ),
    );
    if (choice == null || !mounted) {
      return;
    }
    setState(() {
      if (choice.pickup) {
        _fulfillment = 'pickup';
      } else {
        _pin = choice.pin;
        _pinConfirmed = true;
      }
    });
  }

  Future<void> _pickPrescription({required bool photo}) async {
    final picker = ref.read(attachmentPickerProvider);
    try {
      final List<PickedAttachment> picked;
      if (photo) {
        final camera = await _askCamera();
        if (camera == null) {
          return;
        }
        final shot = camera ? await picker.takePhoto() : null;
        picked = camera ? [?shot] : await picker.pickImages();
      } else {
        picked = await picker.pickDocuments();
      }
      if (picked.isEmpty || !mounted) {
        return;
      }

      final room = 10 - _prescriptions.length;
      if (room <= 0) {
        _snack('A prescription can have up to 10 pages.');
        return;
      }

      setState(
        () => _prescriptions = [..._prescriptions, ...picked.take(room)],
      );
      if (picked.length > room) {
        _snack('Only the first $room pages were added.');
      }
    } on AttachmentTooLargeException {
      _snack('Choose a file under 5 MB.');
    }
  }

  bool _savedCardHasPhoto(CheckoutQuote quote) => quote.discountCards.any(
    (card) => card.id == _savedCardId && card.hasDocument,
  );

  /// Asks camera (true) or gallery (false); null when the sheet is dismissed.
  Future<bool?> _askCamera() => showModalBottomSheet<bool>(
    context: context,
    showDragHandle: true,
    builder: (context) => SafeArea(
      child: Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_camera_outlined),
              title: const Text('Take a photo'),
              onTap: () => Navigator.pop(context, true),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Choose from gallery'),
              onTap: () => Navigator.pop(context, false),
            ),
          ],
        ),
      ),
    ),
  );

  Future<void> _pickCardPhoto() async {
    try {
      final camera = await _askCamera();
      if (camera == null) {
        return;
      }
      final picker = ref.read(attachmentPickerProvider);
      final photo = camera
          ? await picker.takePhoto()
          : await picker.pickImage();
      if (photo != null && mounted) {
        setState(() => _cardPhoto = photo);
      }
    } on AttachmentTooLargeException {
      _snack('Choose a photo under 5 MB.');
    }
  }

  String _total(CheckoutQuote quote) => addMoney(
    addMoney(
      quote.fulfillment[_method]?.total ?? quote.subtotal,
      _paymentFee(quote, _paymentMethod) ?? '0.00',
    ),
    _cardDiscount(quote) == null ? '0.00' : '-${_cardDiscount(quote)}',
  );

  /// The discount when the customer is using a card and something is a medicine.
  String? _cardDiscount(CheckoutQuote quote) =>
      _useCard && quote.offersSeniorPwdDiscount
      ? quote.seniorPwdDiscount
      : null;

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
                        _prescriptionCard(value),
                      ],
                      if (value.offersSeniorPwdDiscount) ...[
                        const SizedBox(height: 12),
                        _discountCardSection(value),
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
                  child: Builder(
                    builder: (context) {
                      final theme = Theme.of(context);
                      final largeText =
                          MediaQuery.textScalerOf(context).scale(14) > 18;
                      final total = Column(
                        mainAxisSize: MainAxisSize.min,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Total',
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: theme.colorScheme.onSurfaceVariant,
                            ),
                          ),
                          FittedBox(
                            fit: BoxFit.scaleDown,
                            alignment: Alignment.centerLeft,
                            child: Text(
                              money(_total(value)),
                              style: theme.textTheme.titleLarge?.copyWith(
                                fontWeight: FontWeight.w800,
                                letterSpacing: -0.5,
                              ),
                            ),
                          ),
                        ],
                      );
                      final button = FilledButton(
                        style: FilledButton.styleFrom(
                          backgroundColor: Theme.of(context)
                              .colorScheme
                              .secondary,
                          foregroundColor: Theme.of(context)
                              .colorScheme
                              .onSecondary,
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
                      );

                      // Large text needs the button's full width; otherwise they share a row.
                      return largeText
                          ? Column(
                              mainAxisSize: MainAxisSize.min,
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                total,
                                const SizedBox(height: 10),
                                button,
                              ],
                            )
                          : Row(
                              children: [
                                ConstrainedBox(
                                  constraints: const BoxConstraints(
                                    maxWidth: 140,
                                  ),
                                  child: total,
                                ),
                                const SizedBox(width: 14),
                                Expanded(child: button),
                              ],
                            );
                    },
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  /// One card per checkout step, headed by an inline icon and title, with an
  /// optional status tag at the right.
  Widget _section({
    required IconData icon,
    required String title,
    required List<Widget> children,
    Widget? trailing,
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
                Icon(icon, size: 20, color: scheme.primary),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    title,
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                if (trailing != null) ...[const SizedBox(width: 8), trailing],
              ],
            ),
            const SizedBox(height: 12),
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
        if (_cardDiscount(quote) case final discount?)
          Padding(
            padding: const EdgeInsets.only(top: 6),
            child: _amountRow(
              label: const Text('SC/PWD discount'),
              amount: Text(
                '−${money(discount)}',
                style: TextStyle(color: statusColour(context, 'completed')),
              ),
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

  Widget _discountCardSection(CheckoutQuote quote) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final ok = statusColour(context, 'completed');

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Heading and switch are one row, so the card stays one line while off.
            MergeSemantics(
              child: InkWell(
                borderRadius: BorderRadius.circular(appSurfaceRadius),
                onTap: () => setState(() => _useCard = !_useCard),
                child: Row(
                  children: [
                    Icon(Icons.badge_outlined, size: 20, color: scheme.primary),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Senior citizen / PWD',
                            style: theme.textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          Text(
                            '${_useCard ? 'Saving' : 'Save'} ${money(quote.seniorPwdDiscount)} on medicines',
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: ok,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ],
                      ),
                    ),
                    Switch(
                      value: _useCard,
                      onChanged: (value) => setState(() => _useCard = value),
                    ),
                  ],
                ),
              ),
            ),
            if (_useCard) ...[
              const SizedBox(height: 12),
              if (quote.discountCards.isNotEmpty)
                RadioGroup<String>(
                  groupValue: _savedCardId ?? '__typed__',
                  onChanged: (value) => setState(
                    () => _savedCardId = value == null || value == '__typed__'
                        ? null
                        : value,
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      for (final card in quote.discountCards)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: RadioListTile<String>(
                            contentPadding: const EdgeInsets.fromLTRB(
                              4,
                              4,
                              8,
                              4,
                            ),
                            value: card.id,
                            selected: _savedCardId == card.id,
                            shape: _choiceShape(_savedCardId == card.id),
                            selectedTileColor: scheme.primaryContainer
                                .withValues(alpha: 0.35),
                            title: Text(
                              card.name,
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            subtitle: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Text(
                                  '${card.typeLabel} ID · ${card.idNumberMasked}',
                                ),
                                if (_savedCardId == card.id && card.hasDocument)
                                  _savedPhotoLine(theme),
                              ],
                            ),
                          ),
                        ),
                      RadioListTile<String>(
                        contentPadding: const EdgeInsets.fromLTRB(4, 0, 8, 0),
                        value: '__typed__',
                        selected: _savedCardId == null,
                        shape: _choiceShape(_savedCardId == null),
                        selectedTileColor: scheme.primaryContainer.withValues(
                          alpha: 0.35,
                        ),
                        title: const Text(
                          'Use a different ID',
                          style: TextStyle(fontWeight: FontWeight.w700),
                        ),
                      ),
                    ],
                  ),
                ),
              if (_savedCardId == null) ...[
                if (quote.discountCards.isNotEmpty) const SizedBox(height: 12),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(
                      value: 'senior',
                      label: Text('Senior citizen'),
                    ),
                    ButtonSegment(value: 'pwd', label: Text('PWD')),
                  ],
                  selected: {_cardType},
                  onSelectionChanged: (value) =>
                      setState(() => _cardType = value.first),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _cardNumber,
                  maxLength: 40,
                  decoration: const InputDecoration(
                    labelText: 'ID number',
                    counterText: '',
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _cardName,
                  maxLength: 120,
                  textCapitalization: TextCapitalization.words,
                  decoration: const InputDecoration(
                    labelText: 'Name on the ID',
                    hintText: 'As printed on the card',
                    counterText: '',
                  ),
                ),
              ],
              if (!_savedCardHasPhoto(quote)) ...[
                const SizedBox(height: 12),
                _idPhotoSlot(theme),
              ],
              if (_savedCardId == null && quote.discountCards.length < 3)
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  value: _saveCard,
                  onChanged: (value) =>
                      setState(() => _saveCard = value ?? false),
                  controlAffinity: ListTileControlAffinity.leading,
                  title: const Text('Save this ID for next time'),
                ),
              const SizedBox(height: 12),
              Divider(height: 1, color: scheme.outlineVariant),
              const SizedBox(height: 12),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    Icons.front_hand_outlined,
                    size: 20,
                    color: scheme.primary,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text.rich(
                      TextSpan(
                        children: [
                          TextSpan(
                            text: 'Show this ID when you receive your order. ',
                            style: TextStyle(
                              color: scheme.onSurface,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          const TextSpan(
                            text: 'Without it the discount can’t be given. For medicines only.',
                          ),
                        ],
                      ),
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: scheme.onSurfaceVariant,
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }

  /// Inside the chosen saved ID: its photo is on file, or a newer one replaces it.
  Widget _savedPhotoLine(ThemeData theme) {
    final ok = statusColour(context, 'completed');
    final replaced = _cardPhoto != null;

    return Row(
      children: [
        Icon(Icons.check_circle_rounded, size: 16, color: ok),
        const SizedBox(width: 4),
        Expanded(
          child: Text(
            replaced ? 'New photo added' : 'ID photo on file',
            style: theme.textTheme.bodySmall?.copyWith(
              color: ok,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
        TextButton(
          style: TextButton.styleFrom(
            minimumSize: const Size(0, 40),
            padding: const EdgeInsets.symmetric(horizontal: 8),
          ),
          onPressed: replaced
              ? () => setState(() => _cardPhoto = null)
              : _pickCardPhoto,
          child: Text(replaced ? 'Undo' : 'Replace photo'),
        ),
      ],
    );
  }

  /// The ID photo the branch checks: a tap target until one is added, then a preview.
  Widget _idPhotoSlot(ThemeData theme) {
    final scheme = theme.colorScheme;
    final photo = _cardPhoto;

    return Material(
      color: scheme.surfaceContainerLow,
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(appSurfaceRadius),
        side: BorderSide(
          color: photo == null
              ? scheme.outline
              : statusColour(context, 'completed'),
          width: 1.2,
        ),
      ),
      child: InkWell(
        onTap: photo == null ? _pickCardPhoto : null,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              if (photo == null)
                CircleAvatar(
                  radius: 20,
                  backgroundColor: scheme.secondaryContainer,
                  child: Icon(
                    Icons.add_a_photo_outlined,
                    color: scheme.primary,
                  ),
                )
              else
                ClipRRect(
                  borderRadius: BorderRadius.circular(appDetailRadius),
                  child: Image.memory(
                    photo.bytes,
                    width: 44,
                    height: 44,
                    cacheWidth: 132,
                    fit: BoxFit.cover,
                    errorBuilder: (_, _, _) => SizedBox.square(
                      dimension: 44,
                      child: Icon(Icons.badge_outlined, color: scheme.primary),
                    ),
                  ),
                ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      photo == null
                          ? 'Add photo of ID'
                          : 'Photo of your ID added',
                      style: theme.textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    Text(
                      photo == null
                          ? 'Only the pharmacy filling your order can see it.'
                          : photo.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: scheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              ),
              if (photo != null)
                IconButton(
                  tooltip: 'Remove ID photo',
                  onPressed: () => setState(() => _cardPhoto = null),
                  icon: const Icon(Icons.close_rounded),
                ),
            ],
          ),
        ),
      ),
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
    final scheme = theme.colorScheme;
    final pin = _pin;
    final problem = _pinProblem;
    final branch = _deliveryBranch;

    if (pin == null && _located) {
      return Text(
        'We could not find your location or the branch on the map. Turn on location, or choose pickup.',
        style: theme.textTheme.bodySmall?.copyWith(color: scheme.error),
      );
    }

    if (pin == null) {
      return const SizedBox(
        height: 124,
        child: Center(
          child: CircularProgressIndicator(semanticsLabel: 'Finding you'),
        ),
      );
    }

    // Location was off: the pin only sits on the branch, so ask for a real one.
    if (!_pinConfirmed) {
      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: scheme.surfaceContainer,
          borderRadius: BorderRadius.circular(appSurfaceRadius),
        ),
        child: Column(
          children: [
            Text(
              'We could not find your location. Put a pin on your door so the rider knows where to go.',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodySmall?.copyWith(
                color: scheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 10),
            FilledButton.tonalIcon(
              style: FilledButton.styleFrom(
                minimumSize: const Size(0, 44),
                shape: const StadiumBorder(),
              ),
              onPressed: _openPinPicker,
              icon: const Icon(Icons.add_location_alt_outlined, size: 18),
              label: const Text('Set delivery pin'),
            ),
          ],
        ),
      );
    }

    final km = branch == null ? null : pinDistanceKm(branch, pin);
    final ok = statusColour(context, 'completed');

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        DeliveryPinMap(
          pin: pin,
          branch: branch == null
              ? null
              : LatLng(branch.latitude, branch.longitude),
          onTap: _openPinPicker,
        ),
        if (problem != null || km != null) ...[
          const SizedBox(height: 10),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                problem == null
                    ? Icons.check_circle_rounded
                    : Icons.error_rounded,
                size: 18,
                color: problem == null ? ok : scheme.error,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  problem ??
                      '${km!.toStringAsFixed(1)} km from ${branch!.name}${branch.maxDeliveryRadiusKm == null ? '' : ' · inside the delivery area'}',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: problem == null ? ok : scheme.error,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
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
                    '${_joinNames(group.value)}: ${group.key}',
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

  Widget _prescriptionCard(CheckoutQuote quote) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final pages = _prescriptions;
    final names = {
      for (final line in quote.lines)
        if (line.requiresPrescription) line.productName,
    }.toList();
    final muted = theme.textTheme.bodySmall?.copyWith(
      color: scheme.onSurfaceVariant,
    );

    return _section(
      icon: Icons.medication_outlined,
      title: 'Prescription',
      trailing: pages.isEmpty
          ? _tag('Needed', statusColour(context, 'pending'))
          : _tag(
              '${pages.length} ${pages.length == 1 ? 'page' : 'pages'}',
              statusColour(context, 'completed'),
              icon: Icons.check_circle_rounded,
            ),
      children: [
        Text.rich(
          TextSpan(
            children: [
              if (names.isNotEmpty) ...[
                const TextSpan(text: 'For '),
                TextSpan(
                  text: _joinNames(names),
                  style: TextStyle(
                    color: scheme.onSurface,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const TextSpan(text: '. '),
              ],
              const TextSpan(
                text: 'A pharmacist checks it before your order is prepared.',
              ),
            ],
          ),
          style: muted,
        ),
        const SizedBox(height: 12),
        if (pages.isEmpty)
          Material(
            color: scheme.surfaceContainerLow,
            clipBehavior: Clip.antiAlias,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(appSurfaceRadius),
              side: BorderSide(color: scheme.outline, width: 1.2),
            ),
            child: InkWell(
              onTap: () => _pickPrescription(photo: true),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 22,
                      backgroundColor: scheme.primary,
                      child: Icon(
                        Icons.photo_camera_outlined,
                        color: scheme.onPrimary,
                      ),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Take photos of every page',
                            style: theme.textTheme.titleSmall?.copyWith(
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          Text(
                            'Up to 10 pages · sharp and readable',
                            style: muted,
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          )
        else ...[
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              for (final (index, page) in pages.indexed)
                _pageThumb(index, page),
              if (pages.length < 10)
                Material(
                  color: scheme.surfaceContainerLow,
                  clipBehavior: Clip.antiAlias,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(8),
                    side: BorderSide(color: scheme.outline, width: 1.2),
                  ),
                  child: InkWell(
                    onTap: () => _pickPrescription(photo: true),
                    child: SizedBox(
                      width: 76,
                      height: 100,
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(
                            Icons.add_a_photo_outlined,
                            color: scheme.primary,
                          ),
                          const SizedBox(height: 4),
                          FittedBox(
                            fit: BoxFit.scaleDown,
                            child: Text(
                              'Add page',
                              style: theme.textTheme.labelMedium?.copyWith(
                                color: scheme.primary,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            'Include every page, front and back. ${pages.length} of 10 used.',
            style: muted,
          ),
        ],
        Align(
          alignment: Alignment.centerLeft,
          child: TextButton.icon(
            style: TextButton.styleFrom(minimumSize: const Size(0, 44)),
            onPressed: () => _pickPrescription(photo: false),
            icon: const Icon(Icons.picture_as_pdf_outlined, size: 18),
            label: Text(pages.isEmpty ? 'Upload a PDF instead' : 'Add a PDF'),
          ),
        ),
      ],
    );
  }

  /// One prescription page: its photo (or a PDF face), its number, and a remove button.
  Widget _pageThumb(int index, PickedAttachment page) {
    final scheme = Theme.of(context).colorScheme;
    final face = ColoredBox(
      color: scheme.surfaceContainerLowest,
      child: Center(
        child: Icon(
          page.isImage ? Icons.image_outlined : Icons.picture_as_pdf_outlined,
          color: scheme.primary,
        ),
      ),
    );

    return SizedBox(
      width: 76,
      height: 100,
      child: Stack(
        children: [
          Positioned.fill(
            child: DecoratedBox(
              position: DecorationPosition.foreground,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: scheme.outlineVariant),
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: page.isImage
                    ? Image.memory(
                        page.bytes,
                        fit: BoxFit.cover,
                        cacheWidth: 228,
                        errorBuilder: (_, _, _) => face,
                      )
                    : face,
              ),
            ),
          ),
          Positioned(
            left: 5,
            bottom: 5,
            child: Container(
              constraints: const BoxConstraints(minWidth: 20),
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
              decoration: BoxDecoration(
                color: Colors.black.withValues(alpha: 0.72),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Text(
                '${index + 1}',
                textAlign: TextAlign.center,
                textScaler: TextScaler.noScaling,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ),
          Positioned(
            top: 0,
            right: 0,
            child: IconButton(
              tooltip: 'Remove page ${index + 1}',
              style: IconButton.styleFrom(
                backgroundColor: Colors.black.withValues(alpha: 0.65),
                foregroundColor: Colors.white,
                fixedSize: const Size.square(32),
                minimumSize: const Size.square(32),
                padding: EdgeInsets.zero,
              ),
              iconSize: 18,
              onPressed: () => setState(
                () => _prescriptions = [..._prescriptions]..removeAt(index),
              ),
              icon: const Icon(Icons.close_rounded),
            ),
          ),
        ],
      ),
    );
  }

  /// A small status pill beside a section title.
  Widget _tag(String label, Color colour, {IconData? icon}) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
    decoration: BoxDecoration(
      color: colour.withValues(alpha: 0.12),
      borderRadius: BorderRadius.circular(999),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (icon != null) ...[
          Icon(icon, size: 16, color: colour),
          const SizedBox(width: 4),
        ],
        Text(
          label,
          style: Theme.of(context).textTheme.labelMedium
              ?.copyWith(color: colour, fontWeight: FontWeight.w800),
        ),
      ],
    ),
  );

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

/// "A", "A and B", "A, B and C".
String _joinNames(List<String> names) => names.length < 2
    ? names.join()
    : '${names.sublist(0, names.length - 1).join(', ')} and ${names.last}';
