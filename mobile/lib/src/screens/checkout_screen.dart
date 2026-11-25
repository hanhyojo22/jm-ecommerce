import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:latlong2/latlong.dart';
import 'package:phosphor_icons/phosphor_icons.dart';
import 'package:uuid/uuid.dart';

import '../core/api_exception.dart';
import '../core/attachment_picker.dart';
import '../core/discount_id_reader.dart';
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
import '../widgets/feedback_popup.dart';
import 'delivery_pin_screen.dart';
import 'checkout_success_screen.dart';
import '../widgets/store_locator.dart';

class CheckoutScreen extends ConsumerStatefulWidget {
  const CheckoutScreen({super.key});

  @override
  ConsumerState<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends ConsumerState<CheckoutScreen> {
  final _formKey = GlobalKey<FormState>();
  final _addressSheetFormKey = GlobalKey<FormState>();
  final _prescriptionKey = GlobalKey();
  final _discountKey = GlobalKey();
  final _recipient = TextEditingController();
  final _phone = TextEditingController();
  final _addressLine = TextEditingController();
  final _subdivision = TextEditingController();
  final _barangay = TextEditingController();
  final _city = TextEditingController();
  final _province = TextEditingController();
  final _postal = TextEditingController();
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

  final _requestId = const Uuid().v4();
  String? _selectedAddressId;
  bool _useNewAddress = false;
  bool _initialised = false;
  String _paymentMethod = 'cod';
  String _fulfillment = 'delivery';
  List<PickedAttachment> _prescriptions = const [];
  bool _placing = false;
  bool _addressAttempted = false;
  bool _documentsAttempted = false;
  bool _paymentAttempted = false;
  bool _itemsExpanded = false;
  final _paymentCardKey = GlobalKey();
  final _paymentErrorKey = GlobalKey();
  StateSetter? _documentsSheetRefresh;

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
    _subdivision.dispose();
    _barangay.dispose();
    _city.dispose();
    _province.dispose();
    _postal.dispose();
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
    if (_city.text.isEmpty) {
      _city.text = quote.branchCity ?? '';
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
    _subdivision.text = address.subdivision;
    _barangay.text = address.barangay;
    _city.text = address.city;
    _province.text = address.province;
    _postal.text = address.postalCode;
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
      subdivision: _subdivision.text.trim(),
      barangay: _barangay.text.trim(),
      city: _city.text.trim(),
      province: _province.text.trim(),
      postalCode: _postal.text.trim(),
    );
  }

  bool _validateDelivery(CheckoutQuote quote) {
    if (_address(quote) == null) {
      _showFeedback(
        _method == 'pickup'
            ? 'Add contact details for pickup.'
            : 'Choose a delivery address.',
        kind: FeedbackKind.info,
      );
      return false;
    }

    if (_useNewAddress && !_validateAddressForm()) {
      _openAddressEditor(quote);
      return false;
    }

    if (_method == 'delivery') {
      if (_pin == null || !_pinConfirmed) {
        _showFeedback(
          'Set the delivery pin so the rider can find you.',
          kind: FeedbackKind.info,
        );
        return false;
      }
      final problem = _pinProblem;
      if (problem != null) {
        _showFeedback(problem);
        return false;
      }
    }
    return true;
  }

  void _reveal(BuildContext? contextToReveal, {GlobalKey? key}) {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final target = key?.currentContext ?? contextToReveal;
      if (mounted && target != null && target.mounted) {
        Scrollable.ensureVisible(
          target,
          alignment: 0.12,
          duration: MediaQuery.disableAnimationsOf(context)
              ? Duration.zero
              : const Duration(milliseconds: 200),
        );
      }
    });
  }

  bool _validateAddressForm({bool inSheet = false}) {
    setState(() {
      _addressAttempted = true;
    });
    final invalid = (inSheet ? _addressSheetFormKey : _formKey).currentState
        ?.validateGranularly();
    if (invalid == null) {
      return false;
    }
    if (invalid.isNotEmpty) {
      if (inSheet) {
        _reveal(invalid.first.context);
      }
      return false;
    }
    return true;
  }

  String? _discountError(CheckoutQuote quote) {
    if (!_useCard || !quote.offersSeniorPwdDiscount) {
      return null;
    }
    if (_savedCardId == null &&
        (_cardNumber.text.trim().isEmpty || _cardName.text.trim().isEmpty)) {
      return 'Enter the ID number and the name on the ID for the discount.';
    }
    if (_cardPhoto == null && !_savedCardHasPhoto(quote)) {
      return 'Add a photo of your senior citizen or PWD ID.';
    }
    return null;
  }

  bool _validateDocuments(CheckoutQuote quote) {
    setState(() => _documentsAttempted = true);
    if (quote.needsPrescription && _prescriptions.isEmpty) {
      _reveal(null, key: _prescriptionKey);
      return false;
    }
    if (_discountError(quote) != null) {
      _reveal(null, key: _discountKey);
      return false;
    }
    return true;
  }

  bool _validatePayment(CheckoutQuote quote) {
    setState(() => _paymentAttempted = true);
    if ((_method == 'pickup' && _paymentMethod == 'cod') ||
        quote.paymentOptions[_paymentMethod]?.available == true) {
      return true;
    }
    _reveal(null, key: _paymentErrorKey);
    return false;
  }

  void _back(CheckoutQuote? quote) {
    if (_placing) {
      return;
    }
    Navigator.of(context).maybePop();
  }

  Future<void> _placeOrder(CheckoutQuote quote) async {
    if (_placing) {
      return;
    }
    if (!_validateDelivery(quote)) {
      return;
    }
    if (!_validateDocuments(quote)) {
      if (quote.needsPrescription && _prescriptions.isEmpty) {
        _openDocumentsSheet(quote, prescription: true);
      } else {
        _openDocumentsSheet(quote, prescription: false);
      }
      return;
    }
    if (!_validatePayment(quote)) {
      return;
    }
    final address = _address(quote)!;
    final useCard = _useCard && quote.offersSeniorPwdDiscount;

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

      if (!mounted) {
        return;
      }
      try {
        await ref.read(cartProvider.notifier).refresh();
      } on ApiException {
        // The order already exists; a cart refresh must not invite a second submission.
        if (mounted) {
          ref.invalidate(cartProvider);
        }
      }

      if (!mounted) {
        return;
      }

      context.pushReplacement(
        '/checkout/success',
        extra: CheckoutSuccessDetails(
          placed: placed,
          branchName: quote.branchName,
          paymentMethod: _paymentMethod,
          fulfillmentMethod: method,
          needsPrescription: quote.needsPrescription,
        ),
      );
    } on ApiException catch (error) {
      if (mounted) {
        // The saved ID may have been deleted on another device; fall back to typing it.
        final lostSavedCard =
            useCard &&
            _savedCardId != null &&
            (error.statusCode == 404 ||
                (error.statusCode == 422 &&
                    error.fields.containsKey('discount_card_id')));

        if (lostSavedCard) {
          setState(() => _savedCardId = null);
          _openDocumentsSheet(quote, prescription: false);
        }

        _showFeedback(
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
        _showFeedback('A prescription can have up to 10 pages.');
        return;
      }

      setState(
        () => _prescriptions = [..._prescriptions, ...picked.take(room)],
      );
      _refreshDocumentsSheet();
      if (picked.length > room) {
        _showFeedback(
          'Only the first $room pages were added.',
          kind: FeedbackKind.info,
        );
      }
    } on AttachmentTooLargeException {
      _showFeedback('Choose a file under 5 MB.');
    }
  }

  bool _savedCardHasPhoto(CheckoutQuote quote) => quote.discountCards.any(
    (card) => card.id == _savedCardId && card.hasDocument,
  );

  /// Asks camera (true) or gallery (false); null when the sheet is dismissed.
  Future<bool?> _askCamera({String cameraLabel = 'Take a photo'}) =>
      showModalBottomSheet<bool>(
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
                  title: Text(cameraLabel),
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
      final camera = await _askCamera(cameraLabel: 'Scan front and back');
      if (camera == null) {
        return;
      }
      final picker = ref.read(attachmentPickerProvider);
      if (!camera) {
        final photo = await picker.pickImage();
        if (photo != null && mounted) {
          setState(() => _cardPhoto = photo);
          _refreshDocumentsSheet();
          await _fillFromCardPhoto(photo);
        }
        return;
      }

      final front = await picker.scanCard();
      if (front == null || !mounted || await _askForBack() != true) {
        return;
      }
      final back = await picker.scanCard();
      if (back == null || !mounted) {
        return;
      }
      final PickedAttachment photo;
      try {
        photo = await picker.joinCardSides(front, back);
      } on AttachmentTooLargeException {
        rethrow;
      } catch (_) {
        _showFeedback('Your ID photos could not be read. Try again.');
        return;
      }
      if (!mounted) {
        return;
      }
      setState(() => _cardPhoto = photo);
      _refreshDocumentsSheet();
      // Only the front: the back can carry an emergency contact's name.
      await _fillFromCardPhoto(front);
    } on AttachmentTooLargeException {
      _showFeedback('Choose a photo under 5 MB.');
    }
  }

  /// Asks for the back once the front is scanned; true when the customer goes on.
  Future<bool?> _askForBack() => showModalBottomSheet<bool>(
    context: context,
    showDragHandle: true,
    builder: (context) => SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(24, 0, 24, 16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              'Now scan the back of your ID',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            const Text('Front saved. Turn the card over to scan the back.'),
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: () => Navigator.pop(context, true),
              icon: const Icon(Icons.flip_outlined),
              label: const Text('Scan the back'),
            ),
          ],
        ),
      ),
    ),
  );

  /// Fills the card type, and the ID number and name the customer has not typed yet, from the
  /// photo. Only typing help: all stay editable, and an unreadable photo changes nothing.
  Future<void> _fillFromCardPhoto(PickedAttachment photo) async {
    // Still read when both boxes are typed: the card may show the other card type was picked.
    if (!photo.isImage || _savedCardId != null) {
      return;
    }
    final DiscountIdFields fields;
    try {
      fields = parseDiscountId(
        await ref.read(discountIdReaderProvider).readText(photo),
      );
    } catch (_) {
      return;
    }
    if (!mounted || _savedCardId != null) {
      return;
    }
    var filled = false;
    // The toggle starts on Senior citizen; a scanned PWD card must not stay recorded as one.
    if (fields.type != null && fields.type != _cardType) {
      setState(() => _cardType = fields.type!);
      _refreshDocumentsSheet();
      filled = true;
    }
    if (fields.number != null && _cardNumber.text.trim().isEmpty) {
      _cardNumber.text = fields.number!;
      filled = true;
    }
    if (fields.name != null && _cardName.text.trim().isEmpty) {
      _cardName.text = fields.name!;
      filled = true;
    }
    if (filled) {
      _refreshDocumentsSheet();
      _showFeedback(
        'Filled in from your ID photo. Check it matches your card.',
        kind: FeedbackKind.success,
      );
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
      _useCard && quote.offersSeniorPwdDiscount && _discountError(quote) == null
      ? quote.seniorPwdDiscount
      : null;

  void _showFeedback(String message, {FeedbackKind kind = FeedbackKind.error}) {
    AppFeedback.of(context).show(message, kind: kind);
  }

  /// Highest-priority incomplete or blocking checkout item for the readiness strip.
  ({bool isError, String message, VoidCallback onTap, int count})?
  _primaryBlocker(CheckoutQuote quote) {
    final addr = _address(quote);
    final addressMissing =
        addr == null ||
        (_useNewAddress &&
            (_recipient.text.trim().isEmpty ||
                _phone.text.trim().isEmpty ||
                (_method == 'delivery' && _addressLine.text.trim().isEmpty)));
    final closedUntil = quote.fulfillment['delivery']?.closedUntil;
    final pinMissing =
        _method == 'delivery' && _located && (_pin == null || !_pinConfirmed);
    final pinOutOfRange =
        _method == 'delivery' && _pinConfirmed && _pinProblem != null;
    final rxMissing = quote.needsPrescription && _prescriptions.isEmpty;
    final discountErr = _discountError(quote);
    final paymentUnavailable =
        !((_method == 'pickup' && _paymentMethod == 'cod') ||
            quote.paymentOptions[_paymentMethod]?.available == true);

    final blockers = <({bool isError, String message, VoidCallback onTap})>[
      if (addressMissing)
        (
          isError: false,
          message: _method == 'pickup'
              ? 'Add contact details'
              : 'Add delivery address',
          onTap: () => _openAddressEditor(quote),
        ),
      if (_method == 'delivery' && closedUntil != null)
        (
          isError: true,
          message: 'Delivery closed · Switch to pickup',
          onTap: () => setState(() => _fulfillment = 'pickup'),
        ),
      if (pinMissing)
        (
          isError: false,
          message: 'Set delivery pin so the rider can find you',
          onTap: _pin != null
              ? _openPinPicker
              : () => setState(() => _fulfillment = 'pickup'),
        ),
      if (pinOutOfRange)
        (
          isError: true,
          message: 'Outside delivery area · Adjust pin',
          onTap: _openPinPicker,
        ),
      if (rxMissing)
        (
          isError: false,
          message: 'Add prescription photo',
          onTap: () => _openDocumentsSheet(quote, prescription: true),
        ),
      if (discountErr != null)
        (
          isError: false,
          message: discountErr,
          onTap: () => _openDocumentsSheet(quote, prescription: false),
        ),
      if (paymentUnavailable)
        (
          isError: true,
          message: 'Choose another payment method or switch to pickup',
          onTap: () => _reveal(null, key: _paymentCardKey),
        ),
    ];

    if (blockers.isEmpty) {
      return null;
    }
    final first = blockers.first;
    return (
      isError: first.isError,
      message: first.message,
      onTap: first.onTap,
      count: blockers.length,
    );
  }

  @override
  Widget build(BuildContext context) {
    final quote = ref.watch(checkoutQuoteProvider);
    ref.watch(nearestStoreProvider);
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final currentQuote = quote.hasError ? null : quote.value;
    if (currentQuote != null) {
      _initialise(currentQuote);
    }
    final hideBranchBadge =
        MediaQuery.sizeOf(context).width < 360 ||
        (MediaQuery.sizeOf(context).width < 450 &&
            MediaQuery.textScalerOf(context).scale(14) > 18);
    final branchName = currentQuote?.branchName;
    final deliveryClosed =
        currentQuote?.fulfillment['delivery']?.closedUntil != null;
    final branchBadgeLabel = branchName == null
        ? null
        : deliveryClosed
        ? '$branchName · Closed'
        : _deliveryBranch?.open24Hours == true
        ? '$branchName · 24h'
        : branchName;

    return PopScope(
      canPop: !_placing,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) {
          _back(quote.value);
        }
      },
      child: Scaffold(
        backgroundColor: scheme.surface,
        appBar: AppBar(
          backgroundColor: scheme.surface,
          surfaceTintColor: Colors.transparent,
          scrolledUnderElevation: 0,
          centerTitle: false,
          titleSpacing: 4,
          leading: IconButton(
            tooltip: 'Back to cart',
            onPressed: _placing ? null : () => _back(quote.value),
            icon: const BackButtonIcon(),
          ),
          title: Text(
            'Checkout',
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: theme.textTheme.titleLarge?.copyWith(
              fontSize: 20,
              fontWeight: FontWeight.w800,
              letterSpacing: -0.4,
            ),
          ),
          actions: [
            if (branchBadgeLabel != null && !hideBranchBadge)
              Padding(
                padding: const EdgeInsetsDirectional.only(end: 16),
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 185),
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 5,
                    ),
                    decoration: BoxDecoration(
                      color: scheme.surfaceContainerLowest,
                      borderRadius: BorderRadius.circular(999),
                      border: Border.all(color: _hairline(scheme)),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Container(
                          width: 7,
                          height: 7,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: deliveryClosed
                                ? scheme.error
                                : statusColour(context, 'completed'),
                          ),
                        ),
                        const SizedBox(width: 6),
                        Flexible(
                          child: Text(
                            branchBadgeLabel,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.labelMedium?.copyWith(
                              color: deliveryClosed
                                  ? scheme.error
                                  : scheme.onSurfaceVariant,
                              fontWeight: FontWeight.w600,
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
        bottomNavigationBar:
            currentQuote != null && MediaQuery.viewInsetsOf(context).bottom == 0
            ? _checkoutFooter(currentQuote)
            : null,
        body: AsyncView(
          value: quote,
          onRetry: () {
            _initialised = false;
            ref.invalidate(checkoutQuoteProvider);
          },
          builder: (value) {
            _initialise(value);
            return Align(
              alignment: Alignment.topCenter,
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 640),
                child: Form(
                  key: _formKey,
                  autovalidateMode: _addressAttempted
                      ? AutovalidateMode.onUserInteraction
                      : AutovalidateMode.disabled,
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.fromLTRB(16, 10, 16, 28),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        _checkoutCard(child: _fulfillmentLocationCard(value)),
                        _checkoutCard(child: _itemsCard(value)),
                        if (value.needsPrescription ||
                            value.offersSeniorPwdDiscount)
                          _checkoutCard(child: _pharmacyDiscountsCard(value)),
                        _checkoutCard(
                          key: _paymentCardKey,
                          child: _paymentCard(value),
                        ),
                        _checkoutCard(child: _priceBreakdownCard(value)),
                      ],
                    ),
                  ),
                ),
              ),
            );
          },
        ),
      ),
    );
  }

  Color _hairline(ColorScheme scheme) => scheme.outlineVariant.withValues(
    alpha: scheme.brightness == Brightness.dark ? 0.55 : 0.65,
  );

  Widget _iconTile(
    IconData icon, {
    required Color background,
    required Color foreground,
    double size = 18,
  }) => Container(
    width: 36,
    height: 36,
    decoration: BoxDecoration(
      color: background,
      borderRadius: BorderRadius.circular(10),
    ),
    child: Icon(icon, size: size, color: foreground),
  );

  ButtonStyle _compactTextLinkStyle({Size minimumSize = const Size(44, 40)}) =>
      TextButton.styleFrom(
        visualDensity: VisualDensity.compact,
        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
        minimumSize: minimumSize,
        padding: const EdgeInsetsDirectional.fromSTEB(12, 8, 2, 8),
        alignment: AlignmentDirectional.centerEnd,
        textStyle: Theme.of(context).textTheme.labelLarge
            ?.copyWith(fontSize: 13.5, fontWeight: FontWeight.w700),
      );

  Widget _checkoutCard({Key? key, required Widget child}) {
    final scheme = Theme.of(context).colorScheme;
    final dark = scheme.brightness == Brightness.dark;

    return Padding(
      key: key,
      padding: const EdgeInsets.only(bottom: 14),
      child: Material(
        color: scheme.surfaceContainerLowest,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: BorderSide(
            color: scheme.outlineVariant.withValues(alpha: dark ? 0.5 : 0.65),
          ),
        ),
        clipBehavior: Clip.antiAlias,
        child: Padding(padding: const EdgeInsets.all(16), child: child),
      ),
    );
  }

  Widget _cardHeader({required String title, Widget? badge, Widget? action}) {
    final theme = Theme.of(context);
    final wrapHeader =
        MediaQuery.textScalerOf(context).scale(14) > 20 &&
        MediaQuery.sizeOf(context).width < 400;
    final leading = Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Flexible(
          child: Semantics(
            header: true,
            child: Text(
              title,
              style: theme.textTheme.titleMedium?.copyWith(
                fontSize: 16,
                fontWeight: FontWeight.w800,
                letterSpacing: -0.25,
                height: 1.25,
              ),
            ),
          ),
        ),
        if (badge != null) ...[const SizedBox(width: 6), badge],
      ],
    );
    if (action == null) {
      return leading;
    }
    if (wrapHeader) {
      return Wrap(
        spacing: 8,
        runSpacing: 4,
        alignment: WrapAlignment.spaceBetween,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: [leading, action],
      );
    }
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        Expanded(child: leading),
        const SizedBox(width: 8),
        action,
      ],
    );
  }

  Widget _itemsCard(CheckoutQuote quote) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final largeText =
        MediaQuery.textScalerOf(context).scale(14) > 18 &&
        MediaQuery.sizeOf(context).width < 600;
    final unitCount = quote.lines.fold<int>(
      0,
      (sum, line) => sum + line.quantity,
    );
    final visibleLines = _itemsExpanded || quote.lines.length <= 3
        ? quote.lines
        : quote.lines.take(3).toList();
    final hiddenCount = quote.lines.length - visibleLines.length;
    final hairline = _hairline(scheme);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _cardHeader(
          title: 'Items',
          badge: Text(
            '($unitCount)',
            style: theme.textTheme.bodyMedium?.copyWith(
              color: scheme.onSurfaceVariant,
              fontWeight: FontWeight.w600,
            ),
          ),
          action: TextButton(
            style: _compactTextLinkStyle(),
            onPressed: () => context.go('/cart'),
            child: const Text('Edit cart'),
          ),
        ),
        const SizedBox(height: 14),
        for (var i = 0; i < visibleLines.length; i++) ...[
          if (i > 0) Divider(height: 24, indent: 64, color: hairline),
          _compactItemRow(visibleLines[i], largeText: largeText),
        ],
        if (quote.lines.length > 3) ...[
          Divider(height: 20, color: hairline),
          TextButton.icon(
            style: TextButton.styleFrom(
              minimumSize: const Size.fromHeight(44),
              foregroundColor: scheme.primary,
              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
              textStyle: theme.textTheme.labelLarge?.copyWith(
                fontSize: 13,
                fontWeight: FontWeight.w700,
              ),
            ),
            onPressed: () => setState(() => _itemsExpanded = !_itemsExpanded),
            icon: Icon(
              _itemsExpanded
                  ? Icons.expand_less_rounded
                  : Icons.expand_more_rounded,
              size: 18,
            ),
            label: Text(
              _itemsExpanded
                  ? 'Show fewer items'
                  : 'Show $hiddenCount more items',
            ),
          ),
        ],
      ],
    );
  }

  Widget _compactItemRow(CartLine line, {required bool largeText}) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final titleSpan = Text.rich(
      TextSpan(
        text: line.productName,
        children: [
          if (line.requiresPrescription)
            WidgetSpan(
              alignment: PlaceholderAlignment.middle,
              child: Container(
                margin: const EdgeInsetsDirectional.only(start: 6),
                padding: const EdgeInsets.symmetric(
                  horizontal: 5,
                  vertical: 1.5,
                ),
                decoration: BoxDecoration(
                  color: scheme.tertiaryContainer,
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Text(
                  'Rx',
                  style: TextStyle(
                    fontFamily: appFontFamily,
                    fontSize: 10.5,
                    fontWeight: FontWeight.w800,
                    color: scheme.onTertiaryContainer,
                    height: 1.1,
                  ),
                ),
              ),
            ),
        ],
      ),
      maxLines: 2,
      overflow: TextOverflow.ellipsis,
      style: theme.textTheme.bodyMedium?.copyWith(
        fontWeight: FontWeight.w600,
        height: 1.25,
      ),
    );

    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        Container(
          foregroundDecoration: BoxDecoration(
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: _hairline(scheme)),
          ),
          child: NetworkThumb(
            url: line.imageUrl,
            width: 52,
            height: 52,
            radius: 10,
            placeholderIcon: PhosphorIconsRegular.pill,
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              titleSpan,
              const SizedBox(height: 4),
              Text(
                '${line.quantity} × ${money(line.unitPrice)}',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: scheme.onSurfaceVariant,
                ),
              ),
              if (largeText) ...[
                const SizedBox(height: 3),
                Text(
                  money(line.lineTotal),
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontSize: 14.5,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ],
          ),
        ),
        if (!largeText) ...[
          const SizedBox(width: 10),
          Text(
            money(line.lineTotal),
            style: theme.textTheme.bodyMedium?.copyWith(
              fontSize: 14.5,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ],
    );
  }

  Widget _pharmacyDiscountsCard(CheckoutQuote quote) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final dark = scheme.brightness == Brightness.dark;
    final ok = statusColour(context, 'completed');
    final warn = statusColour(context, 'pending');
    final rxMissing = quote.needsPrescription && _prescriptions.isEmpty;
    final discountErr = _discountError(quote);
    final actionNeeded = rxMissing || discountErr != null;
    final rxNames = {
      for (final line in quote.lines)
        if (line.requiresPrescription) line.productName.split(' ').first,
    }.toList();
    final savedCard = quote.discountCards
        .where((card) => card.id == _savedCardId)
        .firstOrNull;
    final appliedDiscount = _cardDiscount(quote);
    final hairline = _hairline(scheme);
    final stackRxCta =
        MediaQuery.sizeOf(context).width < 350 ||
        MediaQuery.textScalerOf(context).scale(14) > 19;
    final addRxPill = Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
      decoration: BoxDecoration(
        color: dark ? scheme.primaryContainer : scheme.surfaceContainerLowest,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(
          color: dark ? scheme.primaryContainer : const Color(0xFFE5C878),
        ),
      ),
      child: Text(
        'Add Rx',
        style: theme.textTheme.labelMedium?.copyWith(
          color: dark ? scheme.onPrimaryContainer : scheme.primary,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
    final rxMissingLeading = Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        _iconTile(
          Icons.upload_file_rounded,
          background: warn.withValues(alpha: dark ? 0.22 : 0.14),
          foreground: warn,
          size: 20,
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Prescription',
                style: theme.textTheme.bodyMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                rxNames.isEmpty
                    ? 'Required for prescription medicines'
                    : 'Required for ${_joinNames(rxNames)}',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: dark
                      ? const Color(0xFFFFE3A3)
                      : const Color(0xFF7A4F01),
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
        ),
      ],
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _cardHeader(
          title: 'Pharmacy & discounts',
          action: actionNeeded
              ? null
              : Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 9,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: ok.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.check_circle_rounded, size: 15, color: ok),
                      const SizedBox(width: 4),
                      Text(
                        'Ready',
                        style: theme.textTheme.labelMedium?.copyWith(
                          color: ok,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                ),
        ),
        const SizedBox(height: 14),
        if (quote.needsPrescription)
          rxMissing
              ? Material(
                  color: dark
                      ? const Color(0xFF251B06)
                      : const Color(0xFFFFF8E1),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                    side: BorderSide(
                      color: dark
                          ? const Color(0xFF5A4109)
                          : const Color(0xFFF0D58C),
                    ),
                  ),
                  clipBehavior: Clip.antiAlias,
                  child: InkWell(
                    borderRadius: BorderRadius.circular(12),
                    onTap: () => _openDocumentsSheet(quote, prescription: true),
                    child: Padding(
                      padding: const EdgeInsets.all(14),
                      child: stackRxCta
                          ? Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                rxMissingLeading,
                                const SizedBox(height: 10),
                                Padding(
                                  padding: const EdgeInsetsDirectional.only(
                                    start: 48,
                                  ),
                                  child: addRxPill,
                                ),
                              ],
                            )
                          : Row(
                              children: [
                                Expanded(child: rxMissingLeading),
                                const SizedBox(width: 10),
                                addRxPill,
                              ],
                            ),
                    ),
                  ),
                )
              : InkWell(
                  borderRadius: BorderRadius.circular(10),
                  onTap: () => _openDocumentsSheet(quote, prescription: true),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    child: Row(
                      children: [
                        _iconTile(
                          Icons.check_circle_rounded,
                          background: ok.withValues(alpha: 0.12),
                          foreground: ok,
                          size: 20,
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Prescription',
                                style: theme.textTheme.bodyMedium?.copyWith(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                '${_prescriptions.length} ${_prescriptions.length == 1 ? 'page' : 'pages'} added',
                                style: theme.textTheme.bodySmall?.copyWith(
                                  color: scheme.onSurfaceVariant,
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 8),
                        Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              'Manage',
                              style: theme.textTheme.labelMedium?.copyWith(
                                color: scheme.primary,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            const SizedBox(width: 2),
                            Icon(
                              Icons.chevron_right_rounded,
                              size: 18,
                              color: scheme.onSurfaceVariant,
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
        if (quote.needsPrescription && quote.offersSeniorPwdDiscount)
          if (rxMissing)
            const SizedBox(height: 12)
          else
            Divider(height: 22, color: hairline),
        if (quote.offersSeniorPwdDiscount)
          InkWell(
            borderRadius: BorderRadius.circular(10),
            onTap: () => _openDocumentsSheet(quote, prescription: false),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 6),
              child: Row(
                children: [
                  _iconTile(
                    Icons.badge_outlined,
                    background: _useCard && discountErr == null
                        ? ok.withValues(alpha: 0.12)
                        : scheme.primary.withValues(alpha: 0.10),
                    foreground: _useCard && discountErr == null
                        ? ok
                        : scheme.primary,
                    size: 20,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Wrap(
                          spacing: 6,
                          runSpacing: 2,
                          crossAxisAlignment: WrapCrossAlignment.center,
                          children: [
                            Text(
                              'Senior citizen / PWD',
                              style: theme.textTheme.bodyMedium?.copyWith(
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            Text(
                              appliedDiscount != null
                                  ? '−${money(appliedDiscount)}'
                                  : 'Save ${money(quote.seniorPwdDiscount)}',
                              style: theme.textTheme.labelMedium?.copyWith(
                                color: ok,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 2),
                        Text(
                          !_useCard
                              ? 'Apply eligible discount'
                              : discountErr ?? 'Discount ID selected',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: _useCard && discountErr != null
                                ? scheme.error
                                : scheme.onSurfaceVariant,
                            fontWeight: _useCard && discountErr != null
                                ? FontWeight.w600
                                : FontWeight.w400,
                          ),
                        ),
                        if (_useCard &&
                            discountErr == null &&
                            savedCard != null) ...[
                          const SizedBox(height: 2),
                          Text(
                            '${savedCard.typeLabel} ID · ${savedCard.idNumberMasked}',
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: scheme.onSurfaceVariant,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  Switch(
                    value: _useCard,
                    materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    onChanged: (enabled) {
                      setState(() => _useCard = enabled);
                      if (enabled && _discountError(quote) != null) {
                        _openDocumentsSheet(quote, prescription: false);
                      }
                    },
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }

  Widget _priceBreakdownCard(CheckoutQuote quote) {
    final hairline = _hairline(Theme.of(context).colorScheme);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _cardHeader(title: 'Price breakdown'),
        const SizedBox(height: 14),
        if (quote.storeTotals.length > 1) ...[
          for (final store in quote.storeTotals)
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: _amountRow(
                label: _storeTotalLabel(quote, store),
                amount: Text(money(store.total)),
              ),
            ),
          Divider(height: 16, color: hairline),
        ],
        _paymentSummary(quote),
      ],
    );
  }

  Widget _sheetFooterButton({
    required VoidCallback onPressed,
    required String label,
  }) {
    final scheme = Theme.of(context).colorScheme;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: scheme.surfaceContainerLowest,
        border: Border(top: BorderSide(color: _hairline(scheme))),
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 16),
          child: SizedBox(
            width: double.infinity,
            child: FilledButton(
              style: FilledButton.styleFrom(
                minimumSize: const Size.fromHeight(52),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
              ),
              onPressed: onPressed,
              child: Text(label),
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _openDocumentsSheet(
    CheckoutQuote quote, {
    required bool prescription,
  }) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (sheetContext, refresh) {
          _documentsSheetRefresh = refresh;
          return Padding(
            padding: EdgeInsets.only(
              bottom: MediaQuery.viewInsetsOf(sheetContext).bottom,
            ),
            child: ConstrainedBox(
              constraints: BoxConstraints(
                maxHeight: MediaQuery.sizeOf(sheetContext).height * 0.88,
                maxWidth: 640,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(20, 0, 12, 8),
                    child: Row(
                      children: [
                        Expanded(
                          child: Text(
                            prescription
                                ? 'Prescription'
                                : 'Senior citizen / PWD ID',
                            style: Theme.of(context).textTheme.titleLarge,
                          ),
                        ),
                        IconButton(
                          tooltip: 'Close document sheet',
                          onPressed: () => Navigator.pop(sheetContext),
                          icon: const Icon(Icons.close_rounded),
                        ),
                      ],
                    ),
                  ),
                  Flexible(
                    child: SingleChildScrollView(
                      padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
                      child: prescription
                          ? _prescriptionCard(quote)
                          : _discountCardSection(quote),
                    ),
                  ),
                  _sheetFooterButton(
                    label: 'Done',
                    onPressed: () {
                      refresh(() => _documentsAttempted = true);
                      if (prescription && _prescriptions.isEmpty) {
                        return;
                      }
                      if (!prescription && _discountError(quote) != null) {
                        return;
                      }
                      Navigator.pop(sheetContext);
                    },
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
    _documentsSheetRefresh = null;
  }

  void _refreshDocumentsSheet() {
    _documentsSheetRefresh?.call(() {});
  }

  void _updateDocument(VoidCallback change) {
    setState(change);
    _refreshDocumentsSheet();
  }

  bool get _shortViewport => MediaQuery.sizeOf(context).height < 500;

  Widget _checkoutFooter(CheckoutQuote quote) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final dark = scheme.brightness == Brightness.dark;
    final compact = _shortViewport;
    final stackSummary =
        MediaQuery.sizeOf(context).width < 430 &&
        MediaQuery.textScalerOf(context).scale(14) > 20;
    final itemCount = quote.lines.fold<int>(
      0,
      (count, line) => count + line.quantity,
    );
    final discount = _cardDiscount(quote);
    final blocker = _placing ? null : _primaryBlocker(quote);
    const label = 'Place order';

    final totalLabel = Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Flexible(
          child: Text(
            'Total ($itemCount ${itemCount == 1 ? 'item' : 'items'})',
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: theme.textTheme.bodyMedium?.copyWith(
              color: scheme.onSurfaceVariant,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
        const SizedBox(width: 4),
        Icon(
          Icons.keyboard_arrow_up_rounded,
          size: 18,
          color: scheme.onSurfaceVariant,
        ),
      ],
    );
    final amount = FittedBox(
      fit: BoxFit.scaleDown,
      alignment: stackSummary ? Alignment.centerLeft : Alignment.centerRight,
      child: Text(
        money(_total(quote)),
        style: theme.textTheme.titleLarge?.copyWith(
          fontSize: compact ? 20 : 23,
          fontWeight: FontWeight.w800,
          letterSpacing: -0.5,
          height: 1.15,
        ),
      ),
    );
    final savedText = discount == null || compact
        ? null
        : Padding(
            padding: const EdgeInsets.only(top: 2),
            child: Text(
              'Saved ${money(discount)}',
              style: theme.textTheme.labelSmall?.copyWith(
                color: statusColour(context, 'completed'),
                fontWeight: FontWeight.w700,
              ),
            ),
          );

    final total = Semantics(
      liveRegion: true,
      label: 'Order total ${money(_total(quote))}. View price breakdown',
      button: true,
      onTap: _placing ? null : () => _showOrderSummary(quote),
      excludeSemantics: true,
      child: InkWell(
        borderRadius: BorderRadius.circular(10),
        onTap: _placing ? null : () => _showOrderSummary(quote),
        child: ConstrainedBox(
          constraints: BoxConstraints(minHeight: compact ? 36 : 44),
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 2),
            child: stackSummary
                ? Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      totalLabel,
                      const SizedBox(height: 2),
                      amount,
                      ?savedText,
                    ],
                  )
                : Row(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      Expanded(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [totalLabel, ?savedText],
                        ),
                      ),
                      const SizedBox(width: 12),
                      amount,
                    ],
                  ),
          ),
        ),
      ),
    );
    final action = SizedBox(
      width: double.infinity,
      child: FilledButton(
        style: FilledButton.styleFrom(
          minimumSize: Size.fromHeight(compact ? 46 : 52),
          padding: EdgeInsets.symmetric(
            horizontal: 20,
            vertical: compact ? 10 : 14,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
          textStyle: theme.textTheme.titleMedium?.copyWith(
            fontSize: 16,
            fontWeight: FontWeight.w800,
            letterSpacing: -0.15,
          ),
        ),
        onPressed: _placing ? null : () => _placeOrder(quote),
        child: _placing
            ? SizedBox.square(
                dimension: 22,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  color: scheme.primary,
                  semanticsLabel: 'Placing your order',
                ),
              )
            : Text(label, textAlign: TextAlign.center),
      ),
    );

    final stripBg = blocker == null
        ? null
        : blocker.isError
        ? scheme.error.withValues(alpha: 0.08)
        : (dark ? const Color(0xFF251B06) : const Color(0xFFFFF8E1));
    final stripFg = blocker == null
        ? null
        : blocker.isError
        ? scheme.error
        : (dark ? const Color(0xFFFFE3A3) : const Color(0xFF6B4400));

    return DecoratedBox(
      decoration: BoxDecoration(
        color: scheme.surfaceContainerLowest,
        border: Border(
          top: BorderSide(
            color: scheme.outlineVariant.withValues(alpha: dark ? 0.55 : 0.65),
          ),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: dark ? 0.28 : 0.06),
            blurRadius: 16,
            offset: const Offset(0, -4),
          ),
        ],
      ),
      child: SafeArea(
        top: false,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (blocker != null)
              Material(
                color: stripBg,
                child: InkWell(
                  onTap: blocker.onTap,
                  child: Container(
                    decoration: BoxDecoration(
                      border: Border(
                        bottom: BorderSide(
                          color: stripFg!.withValues(alpha: 0.14),
                        ),
                      ),
                    ),
                    child: Align(
                      alignment: Alignment.topCenter,
                      heightFactor: 1,
                      child: ConstrainedBox(
                        constraints: const BoxConstraints(maxWidth: 640),
                        child: ConstrainedBox(
                          constraints: BoxConstraints(
                            minHeight: compact ? 36 : 42,
                          ),
                          child: Padding(
                            padding: EdgeInsets.symmetric(
                              horizontal: 16,
                              vertical: compact ? 7 : 10,
                            ),
                            child: Row(
                              children: [
                                Icon(
                                  blocker.isError
                                      ? Icons.error_outline_rounded
                                      : Icons.info_outline_rounded,
                                  size: 18,
                                  color: stripFg,
                                ),
                                const SizedBox(width: 8),
                                Expanded(
                                  child: Text(
                                    '${blocker.count == 1 ? '1 step left' : '${blocker.count} steps left'}: ${blocker.message}',
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: theme.textTheme.labelMedium
                                        ?.copyWith(
                                          color: stripFg,
                                          fontWeight: FontWeight.w700,
                                        ),
                                  ),
                                ),
                                Icon(
                                  Icons.chevron_right_rounded,
                                  size: 18,
                                  color: stripFg,
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            Align(
              alignment: Alignment.topCenter,
              heightFactor: 1,
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 640),
                child: Padding(
                  padding: EdgeInsets.fromLTRB(
                    16,
                    compact ? 8 : 12,
                    16,
                    compact ? 10 : 16,
                  ),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      total,
                      SizedBox(height: compact ? 8 : 12),
                      action,
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _showOrderSummary(CheckoutQuote quote) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (sheetContext) => ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.sizeOf(sheetContext).height * 0.8,
          maxWidth: 640,
        ),
        child: SafeArea(
          top: false,
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        'Price breakdown',
                        style: Theme.of(context).textTheme.titleLarge,
                      ),
                    ),
                    IconButton(
                      tooltip: 'Close price breakdown',
                      onPressed: () => Navigator.pop(sheetContext),
                      icon: const Icon(Icons.close_rounded),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                _summaryCard(quote, inSheet: true),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _inlineError(String message, {Key? key}) => Padding(
    key: key,
    padding: const EdgeInsets.only(bottom: 12),
    child: Semantics(
      liveRegion: true,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.error_outline_rounded,
            size: 20,
            color: Theme.of(context).colorScheme.error,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              message,
              style: Theme.of(context).textTheme.bodyMedium
                  ?.copyWith(color: Theme.of(context).colorScheme.error),
            ),
          ),
        ],
      ),
    ),
  );

  Widget _section({
    required IconData icon,
    required String title,
    required List<Widget> children,
    Widget? trailing,
    bool showHeading = true,
  }) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final stackHeading =
        MediaQuery.sizeOf(context).width < 400 &&
        MediaQuery.textScalerOf(context).scale(16) > 24;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (showHeading) ...[
          Row(
            children: [
              Icon(icon, size: 20, color: scheme.primary),
              const SizedBox(width: 8),
              Expanded(
                child: Semantics(
                  header: true,
                  child: Text(
                    title,
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ),
              if (trailing != null && !stackHeading) ...[
                const SizedBox(width: 8),
                trailing,
              ],
            ],
          ),
          if (trailing != null && stackHeading) ...[
            const SizedBox(height: 8),
            Align(alignment: Alignment.centerLeft, child: trailing),
          ],
          const SizedBox(height: 12),
        ],
        ...children,
      ],
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

  Widget _summaryCard(CheckoutQuote quote, {bool inSheet = false}) {
    final theme = Theme.of(context);
    final largeText =
        MediaQuery.textScalerOf(context).scale(14) > 18 &&
        MediaQuery.sizeOf(context).width < 600;
    final count = quote.lines.fold<int>(
      0,
      (total, line) => total + line.quantity,
    );

    return _section(
      icon: Icons.receipt_long_outlined,
      title: 'Price breakdown',
      showHeading: false,
      children: [
        ExpansionTile(
          tilePadding: EdgeInsets.zero,
          childrenPadding: const EdgeInsets.only(top: 4),
          initiallyExpanded: true,
          shape: const Border(),
          collapsedShape: const Border(),
          title: Text(
            inSheet ? 'Items' : 'Price breakdown',
            style: theme.textTheme.titleSmall?.copyWith(
              fontWeight: FontWeight.w800,
            ),
          ),
          subtitle: Text(
            '$count ${count == 1 ? 'item' : 'items'} from ${quote.branchName ?? 'your branch'}',
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
          children: [
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
                            maxLines: 3,
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
        _paymentSummary(quote),
      ],
    );
  }

  Widget _paymentSummary(CheckoutQuote quote) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final fee = _paymentFee(quote, _paymentMethod) ?? '0.00';
    final deliveryFee = quote.fulfillment[_method]?.fee ?? '0.00';
    final deliveryFeeValue = double.tryParse(deliveryFee) ?? 0;
    final itemCount = quote.lines.fold<int>(
      0,
      (count, line) => count + line.quantity,
    );
    final ok = statusColour(context, 'completed');
    final hairline = _hairline(scheme);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _amountRow(
          label: Text(
            'Subtotal ($itemCount ${itemCount == 1 ? 'item' : 'items'})',
            style: theme.textTheme.bodyMedium?.copyWith(
              color: scheme.onSurfaceVariant,
            ),
          ),
          amount: Text(
            money(quote.subtotal),
            style: theme.textTheme.bodyMedium?.copyWith(
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.only(top: 10),
          child: _amountRow(
            label: Text(
              _method == 'pickup' ? 'Pickup at branch' : 'Delivery fee',
              style: theme.textTheme.bodyMedium?.copyWith(
                color: scheme.onSurfaceVariant,
              ),
            ),
            amount: Text(
              deliveryFeeValue > 0 ? money(deliveryFee) : 'Free',
              style: theme.textTheme.bodyMedium?.copyWith(
                fontWeight: deliveryFeeValue > 0
                    ? FontWeight.w600
                    : FontWeight.w700,
                color: deliveryFeeValue > 0 ? scheme.onSurface : ok,
              ),
            ),
          ),
        ),
        if (_cardDiscount(quote) case final discount?)
          Padding(
            padding: const EdgeInsets.only(top: 10),
            child: _amountRow(
              label: Text(
                'SC/PWD discount',
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: ok,
                  fontWeight: FontWeight.w700,
                ),
              ),
              amount: Text(
                '−${money(discount)}',
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: ok,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ),
        if ((double.tryParse(fee) ?? 0) > 0)
          Padding(
            padding: const EdgeInsets.only(top: 10),
            child: _amountRow(
              label: Text(
                'Online payment fee (${_paymentMethod.toUpperCase()})',
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: scheme.onSurfaceVariant,
                ),
              ),
              amount: Text(
                money(fee),
                style: theme.textTheme.bodyMedium?.copyWith(
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ),
        Divider(height: 24, color: hairline),
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
              fontSize: 18,
              fontWeight: FontWeight.w800,
              letterSpacing: -0.3,
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

    return Padding(
      padding: EdgeInsets.zero,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Heading and switch are one row, so the card stays one line while off.
          MergeSemantics(
            child: InkWell(
              borderRadius: BorderRadius.circular(appSurfaceRadius),
              onTap: () => _updateDocument(() => _useCard = !_useCard),
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
                    onChanged: (value) =>
                        _updateDocument(() => _useCard = value),
                  ),
                ],
              ),
            ),
          ),
          if (_useCard) ...[
            const SizedBox(height: 12),
            if (_documentsAttempted && _discountError(quote) != null)
              _inlineError(_discountError(quote)!, key: _discountKey),
            if (quote.discountCards.isNotEmpty)
              RadioGroup<String>(
                groupValue: _savedCardId ?? '__typed__',
                onChanged: (value) => _updateDocument(
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
                          contentPadding: const EdgeInsets.fromLTRB(4, 4, 8, 4),
                          value: card.id,
                          selected: _savedCardId == card.id,
                          shape: _choiceShape(_savedCardId == card.id),
                          selectedTileColor: scheme.primaryContainer.withValues(
                            alpha: 0.35,
                          ),
                          title: Text(
                            card.name,
                            style: const TextStyle(fontWeight: FontWeight.w700),
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
                  ButtonSegment(value: 'senior', label: Text('Senior citizen')),
                  ButtonSegment(value: 'pwd', label: Text('PWD')),
                ],
                selected: {_cardType},
                onSelectionChanged: (value) =>
                    _updateDocument(() => _cardType = value.first),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _cardNumber,
                textInputAction: TextInputAction.next,
                onChanged: (_) {
                  if (_documentsAttempted) {
                    _updateDocument(() {});
                  }
                },
                maxLength: 40,
                decoration: const InputDecoration(
                  labelText: 'ID number',
                  counterText: '',
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _cardName,
                textInputAction: TextInputAction.done,
                onChanged: (_) {
                  if (_documentsAttempted) {
                    _updateDocument(() {});
                  }
                },
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
                    _updateDocument(() => _saveCard = value ?? false),
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
              ? () => _updateDocument(() => _cardPhoto = null)
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
                  onPressed: () => _updateDocument(() => _cardPhoto = null),
                  icon: const Icon(Icons.close_rounded),
                ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _locationDetailsSection(CheckoutQuote quote) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final dark = scheme.brightness == Brightness.dark;
    final address = _address(quote);
    final hasRecipient =
        address != null && address.recipientName.trim().isNotEmpty;
    final isPickup = _method == 'pickup';

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _cardHeader(
          title: isPickup ? 'Contact details' : 'Deliver to',
          action: TextButton(
            style: _compactTextLinkStyle(),
            onPressed: () => _openAddressEditor(quote),
            child: Text(
              isPickup ? 'Edit' : 'Change',
              semanticsLabel: isPickup
                  ? 'Edit contact details'
                  : 'Edit delivery address',
            ),
          ),
        ),
        const SizedBox(height: 10),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _iconTile(
              isPickup
                  ? Icons.person_outline_rounded
                  : Icons.location_on_outlined,
              background: scheme.primaryContainer.withValues(
                alpha: dark ? 0.55 : 0.7,
              ),
              foreground: scheme.primary,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    hasRecipient
                        ? address.recipientName
                        : isPickup
                        ? 'Add contact details'
                        : 'Choose an address',
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontSize: 14.5,
                      fontWeight: FontWeight.w700,
                      height: 1.25,
                    ),
                  ),
                  if (hasRecipient) ...[
                    if (address.phone.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        address.phone,
                        style: theme.textTheme.bodySmall?.copyWith(
                          fontSize: 13,
                          color: scheme.onSurfaceVariant,
                          height: 1.4,
                        ),
                      ),
                    ],
                    if (!isPickup && address.fullAddress.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        address.fullAddress,
                        style: theme.textTheme.bodySmall?.copyWith(
                          fontSize: 13,
                          color: scheme.onSurfaceVariant,
                          height: 1.4,
                        ),
                      ),
                    ],
                  ],
                ],
              ),
            ),
          ],
        ),
      ],
    );
  }

  Future<void> _openAddressEditor(CheckoutQuote quote) async {
    await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (sheetContext, updateSheet) => Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.viewInsetsOf(sheetContext).bottom,
          ),
          child: ConstrainedBox(
            constraints: BoxConstraints(
              maxHeight: MediaQuery.sizeOf(sheetContext).height * 0.85,
              maxWidth: 640,
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 0, 12, 8),
                  child: Row(
                    children: [
                      Expanded(
                        child: Text(
                          _method == 'pickup'
                              ? 'Contact details'
                              : 'Delivery address',
                          style: Theme.of(context).textTheme.titleLarge,
                        ),
                      ),
                      IconButton(
                        tooltip: 'Close address editor',
                        onPressed: () => Navigator.pop(sheetContext, false),
                        icon: const Icon(Icons.close_rounded),
                      ),
                    ],
                  ),
                ),
                Flexible(
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
                    child: Form(
                      key: _addressSheetFormKey,
                      autovalidateMode: _addressAttempted
                          ? AutovalidateMode.always
                          : AutovalidateMode.disabled,
                      child: _addressEditor(
                        quote,
                        inSheet: true,
                        update: (change) {
                          setState(change);
                          updateSheet(() {});
                        },
                      ),
                    ),
                  ),
                ),
                _sheetFooterButton(
                  label: 'Save address',
                  onPressed: () {
                    if (!_useNewAddress ||
                        _validateAddressForm(inSheet: true)) {
                      Navigator.pop(sheetContext, true);
                    } else {
                      updateSheet(() {});
                    }
                  },
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _addressEditor(
    CheckoutQuote quote, {
    bool inSheet = false,
    StateSetter? update,
  }) {
    final theme = Theme.of(context);
    final updateState = update ?? setState;
    final scheme = theme.colorScheme;
    final selectedValue = _useNewAddress ? '__new__' : _selectedAddressId;

    return AutofillGroup(
      child: _section(
        icon: _method == 'pickup'
            ? Icons.person_outline_rounded
            : Icons.location_on_outlined,
        title: _method == 'pickup' ? 'Contact details' : 'Deliver to',
        showHeading: !inSheet,
        children: [
          RadioGroup<String>(
            groupValue: _useNewAddress ? '__new__' : _selectedAddressId,
            onChanged: (value) {
              if (value == null) {
                return;
              }

              if (value == '__new__') {
                updateState(() {
                  _useNewAddress = true;

                  if (_recipient.text.isEmpty &&
                      quote.preferredAddress != null) {
                    _fill(quote.preferredAddress!);
                  }
                });
              } else {
                updateState(() {
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
                      '${address.fullAddress}\n${address.phone}',
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
              textInputAction: TextInputAction.next,
              autofillHints: const [AutofillHints.name],
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(labelText: 'Recipient name'),
              validator: (value) => (value ?? '').trim().isEmpty
                  ? 'Enter the recipient name.'
                  : null,
            ),
            const SizedBox(height: 10),
            TextFormField(
              controller: _phone,
              textInputAction: _method == 'delivery'
                  ? TextInputAction.next
                  : TextInputAction.done,
              autofillHints: const [AutofillHints.telephoneNumber],
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
                textInputAction: TextInputAction.next,
                autofillHints: const [AutofillHints.streetAddressLine1],
                textCapitalization: TextCapitalization.sentences,
                decoration: const InputDecoration(
                  labelText: 'House no., street, landmark',
                  hintText: 'Blue gate beside the sari-sari store',
                ),
                validator: (value) => (value ?? '').trim().isEmpty
                    ? 'Tell the rider how to find you.'
                    : null,
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _subdivision,
                textInputAction: TextInputAction.next,
                autofillHints: const [AutofillHints.streetAddressLine2],
                textCapitalization: TextCapitalization.words,
                decoration: const InputDecoration(
                  labelText: 'Subdivision / building (optional)',
                ),
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _barangay,
                textInputAction: TextInputAction.next,
                textCapitalization: TextCapitalization.words,
                decoration: const InputDecoration(labelText: 'Barangay'),
                validator: (value) =>
                    (value ?? '').trim().isEmpty ? 'Enter the barangay.' : null,
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _city,
                textInputAction: TextInputAction.next,
                textCapitalization: TextCapitalization.words,
                autofillHints: const [AutofillHints.addressCity],
                decoration: const InputDecoration(
                  labelText: 'City / municipality',
                ),
                validator: (value) => (value ?? '').trim().isEmpty
                    ? 'Enter the city or municipality.'
                    : null,
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _province,
                textInputAction: TextInputAction.next,
                textCapitalization: TextCapitalization.words,
                autofillHints: const [AutofillHints.addressState],
                decoration: const InputDecoration(labelText: 'Province'),
                validator: (value) =>
                    (value ?? '').trim().isEmpty ? 'Enter the province.' : null,
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _postal,
                textInputAction: TextInputAction.done,
                keyboardType: TextInputType.number,
                autofillHints: const [AutofillHints.postalCode],
                decoration: const InputDecoration(labelText: 'ZIP code'),
                validator: (value) =>
                    RegExp(r'^\d{4}$').hasMatch((value ?? '').trim())
                    ? null
                    : 'Use a 4-digit ZIP code.',
              ),
              const SizedBox(height: 10),
              TextFormField(
                initialValue: Address.country,
                readOnly: true,
                decoration: const InputDecoration(
                  labelText: 'Country',
                  helperMaxLines: 3,
                  helperText:
                      'Delivery is currently available in the Philippines.',
                ),
              ),
            ],
          ],
        ],
      ),
    );
  }

  Widget _pinSection(ThemeData theme) {
    final scheme = theme.colorScheme;
    final dark = scheme.brightness == Brightness.dark;
    final pin = _pin;
    final problem = _pinProblem;
    final branch = _deliveryBranch;
    final ok = statusColour(context, 'completed');
    final warn = statusColour(context, 'pending');
    final stackPinRow =
        MediaQuery.textScalerOf(context).scale(14) > 20 &&
        MediaQuery.sizeOf(context).width < 400;
    final stackRangeActions =
        MediaQuery.sizeOf(context).width < 340 ||
        MediaQuery.textScalerOf(context).scale(14) > 20;

    if (pin == null && _located) {
      return Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: scheme.error.withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              'We could not find your location or the branch on the map. Turn on location, or choose pickup.',
              style: theme.textTheme.bodySmall?.copyWith(
                color: scheme.error,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 10),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton(
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size.fromHeight(44),
                  backgroundColor: scheme.surfaceContainerLowest,
                  foregroundColor: scheme.error,
                  side: BorderSide(color: scheme.error.withValues(alpha: 0.35)),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
                onPressed: () => setState(() => _fulfillment = 'pickup'),
                child: const Text('Switch to pickup'),
              ),
            ),
          ],
        ),
      );
    }

    if (pin == null) {
      return Container(
        height: 92,
        decoration: BoxDecoration(
          color: dark ? scheme.surfaceContainerLow : scheme.surfaceContainer,
          borderRadius: BorderRadius.circular(12),
        ),
        child: const Center(
          child: CircularProgressIndicator(semanticsLabel: 'Finding you'),
        ),
      );
    }

    // Location was off: the pin only sits on the branch, so ask for a real one.
    if (!_pinConfirmed) {
      return Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: dark ? const Color(0xFF251B06) : const Color(0xFFFFF8E1),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: dark ? const Color(0xFF5A4109) : const Color(0xFFF0D58C),
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.location_off_outlined, size: 16, color: warn),
                const SizedBox(width: 6),
                Flexible(
                  child: Text(
                    'Door pin needed',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.labelMedium?.copyWith(
                      color: warn,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 6),
            Text(
              'We could not find your location. Put a pin on your door so the rider knows where to go.',
              style: theme.textTheme.bodySmall?.copyWith(
                color: dark ? const Color(0xFFFFE3A3) : const Color(0xFF6B4400),
              ),
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size.fromHeight(44),
                  backgroundColor: dark
                      ? scheme.primaryContainer
                      : scheme.surfaceContainerLowest,
                  foregroundColor: dark
                      ? scheme.onPrimaryContainer
                      : scheme.primary,
                  side: BorderSide(
                    color: dark
                        ? const Color(0xFF5A4109)
                        : const Color(0xFFE5C878),
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
                onPressed: _openPinPicker,
                icon: const Icon(Icons.add_location_alt_outlined, size: 18),
                label: const Text('Set delivery pin'),
              ),
            ),
          ],
        ),
      );
    }

    final km = branch == null ? null : pinDistanceKm(branch, pin);
    final branchLatLng = branch == null
        ? null
        : LatLng(branch.latitude, branch.longitude);
    final pinStatus = Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(Icons.check_circle_rounded, size: 16, color: ok),
        const SizedBox(width: 6),
        Flexible(
          child: Text(
            km == null
                ? 'Pin confirmed'
                : 'Pin confirmed · ${km.toStringAsFixed(1)} km',
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: theme.textTheme.labelMedium?.copyWith(
              color: ok,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ],
    );
    final adjustPinButton = TextButton(
      style: _compactTextLinkStyle(),
      onPressed: _openPinPicker,
      child: Text(
        'Adjust pin',
        style: theme.textTheme.labelMedium?.copyWith(
          color: scheme.primary,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
    ButtonStyle rangeActionStyle({required bool isError}) =>
        OutlinedButton.styleFrom(
          minimumSize: const Size.fromHeight(44),
          backgroundColor: scheme.surfaceContainerLowest,
          foregroundColor: isError ? scheme.error : scheme.primary,
          side: BorderSide(
            color: isError
                ? scheme.error.withValues(alpha: 0.35)
                : scheme.outlineVariant,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(10),
          ),
        );
    final pickupButton = OutlinedButton(
      style: rangeActionStyle(isError: false),
      onPressed: () => setState(() => _fulfillment = 'pickup'),
      child: const Text('Pickup'),
    );
    final movePinButton = OutlinedButton(
      style: rangeActionStyle(isError: true),
      onPressed: _openPinPicker,
      child: const Text('Move pin'),
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: _hairline(scheme)),
          ),
          clipBehavior: Clip.antiAlias,
          child: DeliveryPinMap(
            pin: pin,
            branch: branchLatLng,
            height: 92,
            showAdjustButton: false,
            borderRadius: BorderRadius.circular(11),
            pinColor: problem == null ? null : scheme.error,
            onTap: _openPinPicker,
          ),
        ),
        const SizedBox(height: 8),
        if (problem == null)
          stackPinRow
              ? Wrap(
                  spacing: 8,
                  runSpacing: 4,
                  alignment: WrapAlignment.spaceBetween,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [pinStatus, adjustPinButton],
                )
              : Row(
                  children: [
                    Expanded(child: pinStatus),
                    const SizedBox(width: 8),
                    adjustPinButton,
                  ],
                )
        else
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: scheme.error.withValues(alpha: 0.08),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      Icons.error_outline_rounded,
                      size: 16,
                      color: scheme.error,
                    ),
                    const SizedBox(width: 6),
                    Flexible(
                      child: Text(
                        'Outside delivery area',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.labelMedium?.copyWith(
                          color: scheme.error,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  problem,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: scheme.error,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 12),
                stackRangeActions
                    ? Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          pickupButton,
                          const SizedBox(height: 8),
                          movePinButton,
                        ],
                      )
                    : Row(
                        children: [
                          Expanded(child: pickupButton),
                          const SizedBox(width: 8),
                          Expanded(child: movePinButton),
                        ],
                      ),
              ],
            ),
          ),
      ],
    );
  }

  Widget _paymentCard(CheckoutQuote quote) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final labels = {
      'cod': _method == 'pickup' ? 'Pay at the counter' : 'Cash on delivery',
      'gcash': 'GCash',
      'maya': 'Maya',
      'card': 'Card',
    };
    final subtitles = {
      'cod': _method == 'pickup'
          ? 'Pay when collecting at the branch'
          : 'Pay cash when your rider arrives',
      'gcash': 'Pay via GCash wallet after placing',
      'maya': 'Pay via Maya wallet after placing',
      'card': 'Visa or Mastercard online checkout',
    };

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

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _cardHeader(title: 'Payment method'),
        const SizedBox(height: 12),
        if (_paymentAttempted &&
            !((_method == 'pickup' && _paymentMethod == 'cod') ||
                quote.paymentOptions[_paymentMethod]?.available == true))
          _inlineError(
            'Choose another payment method or switch to pickup.',
            key: _paymentErrorKey,
          ),
        for (var i = 0; i < shown.length; i++) ...[
          if (i > 0) const SizedBox(height: 8),
          _paymentOptionRow(
            methodKey: shown[i].key,
            label: labels[shown[i].key] ?? shown[i].key,
            subtitle: shown[i].option.available
                ? (subtitles[shown[i].key] ?? '')
                : (shown[i].option.reason ?? 'Not available for this cart.'),
            option: shown[i].option,
            selected: _paymentMethod == shown[i].key,
          ),
        ],
        for (final group in unavailableByReason.entries)
          Padding(
            padding: const EdgeInsets.only(top: 10),
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

  Widget _paymentOptionRow({
    required String methodKey,
    required String label,
    required String subtitle,
    required PaymentOption option,
    required bool selected,
  }) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final dark = scheme.brightness == Brightness.dark;
    final hasFee =
        option.fee != null && (double.tryParse(option.fee!) ?? 0) > 0;
    final showSubtitle = (!option.available || selected) && subtitle.isNotEmpty;
    final icon = methodKey == 'cod'
        ? Icons.payments_outlined
        : methodKey == 'card'
        ? Icons.credit_card_rounded
        : Icons.account_balance_wallet_outlined;
    final activeSelected = selected && option.available;

    return Semantics(
      selected: selected,
      enabled: option.available,
      inMutuallyExclusiveGroup: true,
      button: true,
      child: Opacity(
        opacity: option.available ? 1 : 0.5,
        child: Material(
          color: activeSelected
              ? scheme.primaryContainer.withValues(alpha: dark ? 0.35 : 0.45)
              : Colors.transparent,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
            side: BorderSide(
              color: activeSelected ? scheme.primary : _hairline(scheme),
              width: activeSelected ? 1.5 : 1,
            ),
          ),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: option.available
                ? () => setState(() => _paymentMethod = methodKey)
                : null,
            child: ConstrainedBox(
              constraints: const BoxConstraints(minHeight: 56),
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 12,
                ),
                child: Row(
                  children: [
                    _iconTile(
                      icon,
                      size: 19,
                      background: !option.available
                          ? scheme.surfaceContainer
                          : selected
                          ? scheme.primary.withValues(alpha: dark ? 0.28 : 0.14)
                          : scheme.surfaceContainerLow,
                      foreground: !option.available
                          ? scheme.onSurfaceVariant
                          : selected
                          ? scheme.primary
                          : scheme.onSurfaceVariant,
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Wrap(
                            spacing: 6,
                            runSpacing: 2,
                            crossAxisAlignment: WrapCrossAlignment.center,
                            children: [
                              Text(
                                label,
                                style: theme.textTheme.bodyMedium?.copyWith(
                                  fontSize: 14.5,
                                  fontWeight: selected
                                      ? FontWeight.w700
                                      : FontWeight.w600,
                                ),
                              ),
                              if (option.available && hasFee)
                                Text(
                                  '+ ${money(option.fee)}',
                                  style: theme.textTheme.labelMedium?.copyWith(
                                    color: scheme.onSurfaceVariant,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                            ],
                          ),
                          if (showSubtitle) ...[
                            const SizedBox(height: 2),
                            Text(
                              subtitle,
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: option.available
                                    ? scheme.onSurfaceVariant
                                    : scheme.error,
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(width: 10),
                    Container(
                      width: 20,
                      height: 20,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: activeSelected
                            ? scheme.surfaceContainerLowest
                            : Colors.transparent,
                        border: Border.all(
                          color: activeSelected
                              ? scheme.primary
                              : scheme.outlineVariant,
                          width: activeSelected ? 6 : 1.75,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _fulfillmentLocationCard(CheckoutQuote quote) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final dark = scheme.brightness == Brightness.dark;
    final deliveryFee = quote.fulfillment['delivery']?.fee ?? '0.00';
    final branch = quote.branchName ?? 'your branch';
    final branchAddress = quote.branchAddress ?? '';
    final closedUntil = quote.fulfillment['delivery']?.closedUntil;
    final sky = skySlab(scheme);
    final stackSegment =
        MediaQuery.sizeOf(context).width < 340 ||
        (MediaQuery.textScalerOf(context).scale(14) > 18 &&
            MediaQuery.sizeOf(context).width < 600);

    final delivery = _fulfillmentOption(
      value: 'delivery',
      icon: Icons.delivery_dining_outlined,
      label: closedUntil != null
          ? 'Delivery · Closed'
          : (double.tryParse(deliveryFee) ?? 0) > 0
          ? 'Delivery · ${money(deliveryFee)}'
          : 'Delivery · Free',
      enabled: closedUntil == null,
    );
    final pickup = _fulfillmentOption(
      value: 'pickup',
      icon: Icons.storefront_outlined,
      label: 'Pickup · Free',
      enabled: true,
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(
          padding: const EdgeInsets.all(4),
          decoration: BoxDecoration(
            color: dark ? scheme.surfaceContainerLow : scheme.surfaceContainer,
            borderRadius: BorderRadius.circular(stackSegment ? 14 : 12),
            border: Border.all(color: _hairline(scheme)),
          ),
          child: stackSegment
              ? Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [delivery, const SizedBox(height: 4), pickup],
                )
              : IntrinsicHeight(
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Expanded(child: delivery),
                      const SizedBox(width: 4),
                      Expanded(child: pickup),
                    ],
                  ),
                ),
        ),
        if (closedUntil != null) ...[
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: scheme.error.withValues(alpha: 0.08),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.info_outline_rounded, size: 18, color: scheme.error),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    '$branch is closed. Delivery opens ${opensLabel(closedUntil)}; you can still order for pickup.',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: scheme.error,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
        if (_method == 'pickup') ...[
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: sky.fill,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.storefront_outlined, size: 20, color: sky.ink),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        branch,
                        style: theme.textTheme.titleSmall?.copyWith(
                          color: sky.ink,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      if (branchAddress.isNotEmpty) ...[
                        const SizedBox(height: 2),
                        Text(
                          branchAddress,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: sky.ink.withValues(alpha: 0.9),
                          ),
                        ),
                      ],
                      const SizedBox(height: 4),
                      Text(
                        _deliveryBranch?.open24Hours == true
                            ? 'Open 24 hours · Ready after confirmation'
                            : 'Ready for pickup after confirmation',
                        style: theme.textTheme.labelMedium?.copyWith(
                          color: sky.ink,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          Divider(height: 26, color: _hairline(scheme)),
          _locationDetailsSection(quote),
        ] else ...[
          if (closedUntil == null)
            Divider(height: 28, color: _hairline(scheme))
          else
            const SizedBox(height: 14),
          _locationDetailsSection(quote),
          const SizedBox(height: 14),
          _pinSection(theme),
        ],
        // Keep validators mounted when the summary replaces the address editor.
        Visibility(
          visible: false,
          maintainState: true,
          child: _addressEditor(quote),
        ),
      ],
    );
  }

  /// A selectable delivery or pickup segment; closed delivery cannot be tapped.
  Widget _fulfillmentOption({
    required String value,
    required IconData icon,
    required String label,
    required bool enabled,
  }) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final selected = _method == value;
    final slab = brandSlab(scheme);
    final foreground = selected ? slab.ink : scheme.onSurfaceVariant;
    return Semantics(
      selected: selected,
      button: true,
      enabled: enabled,
      inMutuallyExclusiveGroup: true,
      child: Material(
        color: selected ? slab.fill : Colors.transparent,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: enabled ? () => setState(() => _fulfillment = value) : null,
          child: Opacity(
            opacity: enabled ? 1 : 0.45,
            child: ConstrainedBox(
              constraints: const BoxConstraints(minHeight: 44),
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 10,
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(icon, color: foreground, size: 18),
                    const SizedBox(width: 6),
                    Flexible(
                      child: Text(
                        label,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.labelMedium?.copyWith(
                          fontSize: 13,
                          color: foreground,
                          fontWeight: selected
                              ? FontWeight.w700
                              : FontWeight.w600,
                        ),
                      ),
                    ),
                  ],
                ),
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
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        border: Border.all(color: scheme.outlineVariant),
        borderRadius: BorderRadius.circular(appSurfaceRadius),
      ),
      child: _section(
        icon: Icons.medication_outlined,
        title: 'Prescription',
        trailing: pages.isEmpty
            ? _tag('Required', statusColour(context, 'pending'))
            : _tag(
                '${pages.length} ${pages.length == 1 ? 'page' : 'pages'}',
                statusColour(context, 'completed'),
                icon: Icons.check_circle_rounded,
              ),
        children: [
          Text(
            '${names.isEmpty ? '' : 'For ${_joinNames(names)}. '}A pharmacist checks it before your order is prepared.',
            style: theme.textTheme.bodySmall?.copyWith(
              color: scheme.onSurfaceVariant,
            ),
          ),
          const SizedBox(height: 16),
          if (_documentsAttempted && pages.isEmpty)
            _inlineError(
              'Add a photo of your prescription.',
              key: _prescriptionKey,
            ),
          for (final (index, page) in pages.indexed) ...[
            _pageThumb(index, page),
            const SizedBox(height: 12),
          ],
          if (pages.length < 10)
            Builder(
              builder: (context) {
                final stackRxButtons =
                    MediaQuery.sizeOf(context).width < 340 ||
                    MediaQuery.textScalerOf(context).scale(14) > 20;
                final buttonStyle = OutlinedButton.styleFrom(
                  minimumSize: const Size(0, 48),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                );
                final takePhotos = OutlinedButton.icon(
                  style: buttonStyle,
                  onPressed: () => _pickPrescription(photo: true),
                  icon: const Icon(Icons.photo_camera_outlined, size: 20),
                  label: Text(pages.isEmpty ? 'Take photos' : 'Add photo'),
                );
                final uploadFile = OutlinedButton.icon(
                  style: buttonStyle,
                  onPressed: () => _pickPrescription(photo: false),
                  icon: const Icon(Icons.upload_file_outlined, size: 20),
                  label: const Text('Upload file'),
                );
                return stackRxButtons
                    ? Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          takePhotos,
                          const SizedBox(height: 10),
                          uploadFile,
                        ],
                      )
                    : Row(
                        children: [
                          Expanded(child: takePhotos),
                          const SizedBox(width: 10),
                          Expanded(child: uploadFile),
                        ],
                      );
              },
            ),
          const SizedBox(height: 12),
          Text(
            'Include every page, front and back. Up to 10 pages, 5 MB each.',
            style: theme.textTheme.bodySmall?.copyWith(
              color: scheme.onSurfaceVariant,
            ),
          ),
        ],
      ),
    );
  }

  Widget _pageThumb(int index, PickedAttachment page) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final placeholder = Icon(
      page.isImage ? Icons.image_outlined : Icons.picture_as_pdf_outlined,
      color: scheme.primary,
    );
    return Row(
      children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(8),
          child: SizedBox.square(
            dimension: 44,
            child: ColoredBox(
              color: scheme.surfaceContainerLow,
              child: page.isImage
                  ? Image.memory(
                      page.bytes,
                      fit: BoxFit.cover,
                      cacheWidth: 132,
                      errorBuilder: (_, _, _) => placeholder,
                    )
                  : placeholder,
            ),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                page.name,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              Text(
                'Page ${index + 1} added',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: scheme.onSurfaceVariant,
                ),
              ),
            ],
          ),
        ),
        IconButton(
          tooltip: 'Remove page ${index + 1}',
          constraints: const BoxConstraints(minWidth: 48, minHeight: 48),
          onPressed: () => _updateDocument(
            () => _prescriptions = [..._prescriptions]..removeAt(index),
          ),
          icon: const Icon(Icons.close_rounded),
        ),
      ],
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
        Flexible(
          child: Text(
            label,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.labelMedium
                ?.copyWith(color: colour, fontWeight: FontWeight.w800),
          ),
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

  Widget _amountRow({required Widget label, required Widget amount}) =>
      LayoutBuilder(
        builder: (context, constraints) {
          if (constraints.maxWidth >= 500 ||
              MediaQuery.textScalerOf(context).scale(14) <= 18) {
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
        },
      );
}

/// "A", "A and B", "A, B and C".
String _joinNames(List<String> names) => names.length < 2
    ? names.join()
    : '${names.sublist(0, names.length - 1).join(', ')} and ${names.last}';
