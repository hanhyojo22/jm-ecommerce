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
  int _step = 0;
  bool _returnToReview = false;
  bool _editingAddress = false;
  bool _addressSheetOpen = false;
  bool _addressAttempted = false;
  bool _documentsAttempted = false;
  final _stepScroll = List.generate(4, (_) => ScrollController());

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
    for (final controller in _stepScroll) {
      controller.dispose();
    }
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
    _editingAddress = _useNewAddress;
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
      _snack('Choose a delivery address.');
      return false;
    }

    if (_useNewAddress && !_validateAddressForm()) {
      return false;
    }

    if (_method == 'delivery') {
      if (_pin == null || !_pinConfirmed) {
        _snack('Set the delivery pin so the rider can find you.');
        return false;
      }
      final problem = _pinProblem;
      if (problem != null) {
        _snack(problem);
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
      if (!inSheet) {
        setState(() => _editingAddress = true);
      }
      _reveal(invalid.first.context);
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

  bool _needsDocuments(CheckoutQuote quote) =>
      quote.needsPrescription || quote.offersSeniorPwdDiscount;

  void _goToStep(int step) {
    FocusManager.instance.primaryFocus?.unfocus();
    setState(() => _step = step);
    if (_stepScroll[step].hasClients) {
      _stepScroll[step].jumpTo(0);
    }
  }

  void _continue(CheckoutQuote quote) {
    final valid = switch (_step) {
      0 => _validateDelivery(quote),
      1 => _validateDocuments(quote),
      2 => _validatePayment(quote),
      _ => false,
    };
    if (!valid) {
      return;
    }
    final next = _returnToReview
        ? 3
        : _step == 0 && !_needsDocuments(quote)
        ? 2
        : _step + 1;
    _returnToReview = false;
    _goToStep(next);
  }

  void _editStep(int step) {
    _returnToReview = true;
    _goToStep(step);
  }

  bool _validatePayment(CheckoutQuote quote) {
    if ((_method == 'pickup' && _paymentMethod == 'cod') ||
        quote.paymentOptions[_paymentMethod]?.available == true) {
      return true;
    }
    _snack('Choose an available payment method.');
    return false;
  }

  void _back(CheckoutQuote? quote) {
    if (_placing) {
      return;
    }
    if (_returnToReview) {
      _returnToReview = false;
      _goToStep(3);
    } else if (_step == 0) {
      Navigator.of(context).maybePop();
    } else {
      _goToStep(
        _step == 2 && (quote == null || !_needsDocuments(quote))
            ? 0
            : _step - 1,
      );
    }
  }

  Future<void> _placeOrder(CheckoutQuote quote) async {
    if (_placing) {
      return;
    }
    if (!_validateDelivery(quote)) {
      _editStep(0);
      return;
    }
    if (!_validateDocuments(quote)) {
      _editStep(1);
      return;
    }
    if (!_validatePayment(quote)) {
      _editStep(2);
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
          _editStep(1);
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
        _snack('Your ID photos could not be read. Try again.');
        return;
      }
      if (!mounted) {
        return;
      }
      setState(() => _cardPhoto = photo);
      // Only the front: the back can carry an emergency contact's name.
      await _fillFromCardPhoto(front);
    } on AttachmentTooLargeException {
      _snack('Choose a photo under 5 MB.');
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
      _snack('Filled in from your ID photo. Check it matches your card.');
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
    ref.watch(nearestStoreProvider);
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final currentQuote = quote.hasError ? null : quote.value;
    if (currentQuote != null) {
      _initialise(currentQuote);
    }

    return PopScope(
      canPop: _step == 0 && !_placing && !_returnToReview,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) {
          _back(quote.value);
        }
      },
      child: Scaffold(
        backgroundColor: scheme.surfaceContainerLowest,
        appBar: AppBar(
          backgroundColor: scheme.surfaceContainerLowest,
          surfaceTintColor: Colors.transparent,
          centerTitle: true,
          leading: BackButton(
            onPressed: _placing ? null : () => _back(quote.value),
          ),
          title: Text(
            'Checkout',
            style: theme.textTheme.titleMedium?.copyWith(
              fontSize: 18,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
        bottomNavigationBar:
            currentQuote != null && MediaQuery.viewInsetsOf(context).bottom == 0
            ? Align(
                heightFactor: 1,
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 640),
                  child: _checkoutFooter(currentQuote),
                ),
              )
            : null,
        body: AsyncView(
          value: quote,
          onRetry: () {
            _initialised = false;
            ref.invalidate(checkoutQuoteProvider);
          },
          builder: (value) {
            _initialise(value);
            return LayoutBuilder(
              builder: (context, constraints) {
                final compact = constraints.maxHeight < 420;
                return Align(
                  alignment: Alignment.topCenter,
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 640),
                    child: Column(
                      children: [
                        _progress(
                          value,
                          compact:
                              compact ||
                              (constraints.maxWidth < 480 &&
                                  MediaQuery.textScalerOf(context).scale(14) >
                                      16.8),
                        ),
                        Expanded(
                          child: Form(
                            key: _formKey,
                            autovalidateMode: _addressAttempted
                                ? AutovalidateMode.onUserInteraction
                                : AutovalidateMode.disabled,
                            child: IndexedStack(
                              index: _step,
                              children: [
                                _stepPage(0, [
                                  _pageHeading(
                                    'Delivery or pickup',
                                    'Your medicines, from ${value.branchName ?? 'your branch'}.',
                                  ),
                                  _fulfillmentCard(value),
                                  const SizedBox(height: 24),
                                  _addressCard(value),
                                ]),
                                _stepPage(1, [
                                  _pageHeading(
                                    value.needsPrescription
                                        ? value.offersSeniorPwdDiscount
                                              ? 'Prescription & ID'
                                              : 'Prescription'
                                        : 'Discount ID',
                                    value.needsPrescription
                                        ? 'For the medicines in your order.'
                                        : 'Use your senior citizen or PWD ID, if eligible.',
                                  ),
                                  if (value.needsPrescription)
                                    _prescriptionCard(value),
                                  if (value.needsPrescription &&
                                      value.offersSeniorPwdDiscount)
                                    const Divider(height: 40),
                                  if (value.offersSeniorPwdDiscount)
                                    _discountCardSection(value),
                                ]),
                                _stepPage(2, [
                                  _pageHeading(
                                    'Payment method',
                                    'Choose how you would like to pay.',
                                  ),
                                  _paymentCard(value),
                                  const Divider(height: 40),
                                  _paymentSummary(value),
                                ]),
                                _stepPage(3, [
                                  _pageHeading('Review your order', null),
                                  _summaryCard(value),
                                  const SizedBox(height: 12),
                                  _reviewDetails(value),
                                ]),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            );
          },
        ),
      ),
    );
  }

  Widget _stepPage(int step, List<Widget> children) => ListView(
    key: PageStorageKey('checkout-step-$step'),
    controller: _stepScroll[step],
    padding: EdgeInsets.fromLTRB(20, _shortViewport ? 12 : 22, 20, 24),
    children: children,
  );

  Widget _pageHeading(String title, String? description) {
    final theme = Theme.of(context);
    return Padding(
      padding: EdgeInsets.only(bottom: _shortViewport ? 14 : 22),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Semantics(
            header: true,
            child: Text(
              title,
              style: theme.textTheme.headlineSmall?.copyWith(
                fontWeight: FontWeight.w800,
                letterSpacing: -0.5,
                height: 1.2,
              ),
            ),
          ),
          if (description != null) ...[
            const SizedBox(height: 8),
            Text(
              description,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _progress(CheckoutQuote quote, {required bool compact}) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final steps = [
      (index: 0, label: 'Delivery'),
      if (_needsDocuments(quote)) (index: 1, label: 'Documents'),
      (index: 2, label: 'Payment'),
      (index: 3, label: 'Review'),
    ];
    final position = steps.indexWhere((step) => step.index == _step);
    final current = steps[position < 0 ? 0 : position];
    if (compact) {
      return Padding(
        padding: const EdgeInsets.fromLTRB(20, 4, 20, 12),
        child: Semantics(
          label: 'Step ${position + 1} of ${steps.length}, ${current.label}',
          excludeSemantics: true,
          child: Row(
            children: [
              Expanded(
                child: Text(
                  current.label,
                  style: theme.textTheme.labelLarge?.copyWith(
                    color: scheme.primary,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              Text(
                '${position + 1}/${steps.length}',
                style: theme.textTheme.bodySmall,
              ),
            ],
          ),
        ),
      );
    }
    return DecoratedBox(
      decoration: BoxDecoration(
        border: Border(bottom: BorderSide(color: scheme.outlineVariant)),
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            for (final (index, step) in steps.indexed)
              Expanded(
                child: Semantics(
                  label:
                      'Step ${index + 1} of ${steps.length}, ${step.label}${step.index == _step
                          ? ', current'
                          : step.index < _step
                          ? ', completed'
                          : ''}',
                  selected: step.index == _step,
                  child: InkWell(
                    borderRadius: BorderRadius.circular(appDetailRadius),
                    onTap: step.index < _step && !_placing
                        ? () => _step == 3
                              ? _editStep(step.index)
                              : _goToStep(step.index)
                        : null,
                    child: Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 4,
                        vertical: 6,
                      ),
                      child: ExcludeSemantics(
                        child: Column(
                          children: [
                            Container(
                              width: 24,
                              height: 24,
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                color: step.index == _step
                                    ? scheme.primary
                                    : step.index < _step
                                    ? scheme.primaryContainer
                                    : scheme.surfaceContainerLowest,
                                border: Border.all(
                                  color: step.index <= _step
                                      ? Colors.transparent
                                      : scheme.outline,
                                ),
                              ),
                              alignment: Alignment.center,
                              child: step.index < _step
                                  ? Icon(
                                      Icons.check_rounded,
                                      size: 16,
                                      color: scheme.onPrimaryContainer,
                                    )
                                  : Text(
                                      '${index + 1}',
                                      textScaler: TextScaler.noScaling,
                                      style: theme.textTheme.labelSmall
                                          ?.copyWith(
                                            color: step.index == _step
                                                ? scheme.onPrimary
                                                : scheme.onSurfaceVariant,
                                            fontWeight: FontWeight.w700,
                                          ),
                                    ),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              step.label,
                              textAlign: TextAlign.center,
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: step.index <= _step
                                    ? scheme.primary
                                    : scheme.onSurfaceVariant,
                                fontWeight: step.index == _step
                                    ? FontWeight.w800
                                    : FontWeight.w500,
                              ),
                            ),
                            const SizedBox(height: 10),
                            Container(
                              height: 3,
                              decoration: BoxDecoration(
                                color: step.index <= _step
                                    ? scheme.primary
                                    : scheme.outlineVariant,
                                borderRadius: BorderRadius.circular(2),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  bool get _shortViewport => MediaQuery.sizeOf(context).height < 500;

  Widget _checkoutFooter(CheckoutQuote quote) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final review = _step == 3;
    final compact = _shortViewport;
    final label = review
        ? 'Place order'
        : _returnToReview
        ? 'Save & return to review'
        : _step == 0 && _needsDocuments(quote)
        ? 'Continue to documents'
        : _step < 2
        ? 'Continue to payment'
        : 'Continue to review';
    final totalLabel = Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Flexible(
          child: Text(
            'Order total',
            style: theme.textTheme.bodySmall?.copyWith(
              color: scheme.onSurfaceVariant,
            ),
          ),
        ),
        const SizedBox(width: 4),
        Icon(
          Icons.expand_more_rounded,
          size: 16,
          color: scheme.onSurfaceVariant,
        ),
      ],
    );
    final amount = Text(
      money(_total(quote)),
      style: theme.textTheme.titleLarge?.copyWith(
        fontSize: compact ? 22 : 24,
        fontWeight: FontWeight.w800,
        letterSpacing: -0.5,
      ),
    );
    final total = Semantics(
      liveRegion: true,
      label: 'Order total ${money(_total(quote))}. View order summary',
      button: true,
      onTap: _placing ? null : () => _showOrderSummary(quote),
      excludeSemantics: true,
      child: InkWell(
        onTap: _placing ? null : () => _showOrderSummary(quote),
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 48),
          child: compact
              ? Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [totalLabel, amount],
                )
              : Wrap(
                  alignment: WrapAlignment.spaceBetween,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  spacing: 16,
                  runSpacing: 4,
                  children: [totalLabel, amount],
                ),
        ),
      ),
    );
    final action = FilledButton(
      style: FilledButton.styleFrom(
        minimumSize: Size.fromHeight(compact ? 48 : 54),
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 15),
        shape: const StadiumBorder(),
      ),
      onPressed: _placing
          ? null
          : review
          ? () => _placeOrder(quote)
          : () => _continue(quote),
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
    );
    return SafeArea(
      top: false,
      child: Container(
        padding: EdgeInsets.fromLTRB(
          20,
          compact ? 8 : 12,
          20,
          compact ? 8 : 16,
        ),
        decoration: BoxDecoration(
          color: scheme.surfaceContainerLowest,
          border: Border(top: BorderSide(color: scheme.outlineVariant)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (compact)
              Row(
                children: [
                  Expanded(flex: 2, child: total),
                  const SizedBox(width: 16),
                  Expanded(flex: 3, child: action),
                ],
              )
            else ...[
              total,
              const SizedBox(height: 12),
              action,
            ],
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
                        'Order summary',
                        style: Theme.of(context).textTheme.titleLarge,
                      ),
                    ),
                    IconButton(
                      tooltip: 'Close order summary',
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

  Widget _reviewDetails(CheckoutQuote quote) {
    final address = _address(quote);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Divider(),
        _reviewDetail(
          icon: _method == 'pickup'
              ? Icons.storefront_outlined
              : Icons.location_on_outlined,
          title: _method == 'pickup'
              ? 'Pickup at ${quote.branchName ?? 'your branch'}'
              : address?.recipientName ?? 'Delivery details',
          detail: [
            if (_method == 'pickup') ...[
              quote.branchAddress ?? '',
              address?.recipientName ?? '',
            ] else
              address?.fullAddress ?? '',
            address?.phone ?? '',
          ].where((part) => part.isNotEmpty).join('\n'),
          editLabel: 'Edit contact details',
          onEdit: () {
            _editStep(0);
            _openAddressEditor(quote);
          },
        ),
        if (quote.needsPrescription)
          _reviewDetail(
            icon: Icons.description_outlined,
            title: 'Prescription',
            detail:
                '${_prescriptions.length} ${_prescriptions.length == 1 ? 'page' : 'pages'} added',
            onEdit: () => _editStep(1),
          ),
        if (_useCard && quote.offersSeniorPwdDiscount)
          _reviewDetail(
            icon: Icons.badge_outlined,
            title: 'Senior citizen / PWD',
            detail: 'Discount ID added',
            onEdit: () => _editStep(1),
          ),
        _reviewDetail(
          icon: Icons.payments_outlined,
          title: _paymentLabel,
          detail: _paymentTiming,
          editLabel: 'Edit payment method',
          onEdit: () => _editStep(2),
        ),
      ],
    );
  }

  Widget _reviewDetail({
    required IconData icon,
    required String title,
    required String detail,
    required VoidCallback onEdit,
    String? editLabel,
  }) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 3),
            child: Icon(icon, size: 20, color: theme.colorScheme.primary),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 4),
                Text(
                  detail,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ),
          TextButton(
            style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
            onPressed: _placing ? null : onEdit,
            child: Text('Edit', semanticsLabel: editLabel ?? 'Edit $title'),
          ),
        ],
      ),
    );
  }

  /// A flat checkout section, with optional status or edit controls.
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
    final count = quote.lines.length;

    return _section(
      icon: Icons.receipt_long_outlined,
      title: 'Order summary',
      showHeading: false,
      children: [
        ExpansionTile(
          tilePadding: EdgeInsets.zero,
          childrenPadding: const EdgeInsets.only(top: 4),
          initiallyExpanded: true,
          shape: const Border(),
          collapsedShape: const Border(),
          title: Text(
            inSheet ? 'Items' : 'Order summary',
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

  String get _paymentLabel => switch (_paymentMethod) {
    'cod' => _method == 'pickup' ? 'Pay at the counter' : 'Cash on delivery',
    'gcash' => 'GCash',
    'maya' => 'Maya',
    'card' => 'Card',
    _ => _paymentMethod,
  };

  String get _paymentTiming => _paymentMethod == 'cod'
      ? _method == 'pickup'
            ? 'Pay at the counter when collecting.'
            : 'Pay cash when your order arrives.'
      : 'Continue to your payment provider after placing your order.';

  Widget _paymentSummary(CheckoutQuote quote) {
    final theme = Theme.of(context);
    final fee = _paymentFee(quote, _paymentMethod) ?? '0.00';
    final deliveryFee = quote.fulfillment[_method]?.fee ?? '0.00';
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
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

    return Padding(
      padding: EdgeInsets.zero,
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
            if (_documentsAttempted && _discountError(quote) != null)
              _inlineError(_discountError(quote)!, key: _discountKey),
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
                    setState(() => _cardType = value.first),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _cardNumber,
                textInputAction: TextInputAction.next,
                onChanged: (_) {
                  if (_documentsAttempted) {
                    setState(() {});
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
                    setState(() {});
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
    final address = _address(quote);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (!_editingAddress) ...[
          Row(
            children: [
              Expanded(
                child: Text(
                  _method == 'pickup' ? 'Contact details' : 'Deliver to',
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              TextButton(
                style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
                onPressed: () => _openAddressEditor(quote),
                child: const Text(
                  'Edit',
                  semanticsLabel: 'Edit contact details',
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.home_outlined, color: scheme.primary, size: 22),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      address?.recipientName ?? 'Choose an address',
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                    if (address != null) ...[
                      const SizedBox(height: 5),
                      Text(
                        address.phone,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: scheme.onSurfaceVariant,
                        ),
                      ),
                      if (_method == 'delivery') ...[
                        const SizedBox(height: 5),
                        Text(
                          address.fullAddress,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: scheme.onSurfaceVariant,
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
        // Keep validators mounted when the summary replaces the address editor.
        Visibility(
          visible: _editingAddress && !_addressSheetOpen,
          maintainState: true,
          child: _addressEditor(quote),
        ),
        if (_method == 'delivery') ...[
          const SizedBox(height: 16),
          _pinSection(theme),
        ],
      ],
    );
  }

  Future<void> _openAddressEditor(CheckoutQuote quote) async {
    setState(() => _addressSheetOpen = true);
    final saved = await showModalBottomSheet<bool>(
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
                          ? AutovalidateMode.onUserInteraction
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
                SafeArea(
                  top: false,
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(20, 12, 20, 16),
                    child: FilledButton(
                      onPressed: () {
                        if (!_useNewAddress ||
                            _validateAddressForm(inSheet: true)) {
                          setState(() => _editingAddress = false);
                          Navigator.pop(sheetContext, true);
                        } else {
                          updateSheet(() {});
                        }
                      },
                      child: const Text('Save address'),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
    if (!mounted) {
      return;
    }
    setState(() => _addressSheetOpen = false);
    if (saved == true && _returnToReview) {
      _continue(quote);
    }
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
        trailing: TextButton(
          style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
          onPressed: () {
            if (!_useNewAddress || _validateAddressForm()) {
              FocusManager.instance.primaryFocus?.unfocus();
              updateState(() => _editingAddress = false);
            }
          },
          child: const Text('Done'),
        ),
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
    final text = Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(
          problem == null ? Icons.location_on_outlined : Icons.error_outline,
          color: problem == null ? scheme.primary : scheme.error,
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                problem == null
                    ? 'Delivery pin confirmed'
                    : 'Outside the delivery area',
                style: theme.textTheme.bodyMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                  color: problem == null ? scheme.onSurface : scheme.error,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                problem ??
                    (km == null
                        ? 'Your rider will use this location.'
                        : '${km.toStringAsFixed(1)} km from ${branch!.name}${branch.maxDeliveryRadiusKm == null ? '' : ' · inside the delivery area'}'),
                style: theme.textTheme.bodySmall?.copyWith(
                  color: problem == null
                      ? scheme.onSurfaceVariant
                      : scheme.error,
                ),
              ),
            ],
          ),
        ),
        if (problem == null) ...[
          const SizedBox(width: 6),
          Icon(Icons.check_rounded, size: 18, color: ok),
        ],
      ],
    );
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 14, 8, 6),
      decoration: BoxDecoration(
        color: scheme.primaryContainer.withValues(alpha: 0.45),
        borderRadius: BorderRadius.circular(appSurfaceRadius),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          text,
          Align(
            alignment: AlignmentDirectional.centerEnd,
            child: TextButton(
              style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
              onPressed: _openPinPicker,
              child: const Text('Adjust pin'),
            ),
          ),
        ],
      ),
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
      showHeading: false,
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
                  controlAffinity: ListTileControlAffinity.trailing,
                  secondary: Icon(
                    entry.key == 'cod'
                        ? Icons.payments_outlined
                        : entry.key == 'card'
                        ? Icons.credit_card_rounded
                        : Icons.account_balance_wallet_outlined,
                    color: option.available
                        ? scheme.primary
                        : scheme.onSurfaceVariant,
                  ),
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
        const SizedBox(height: 4),
        Text(
          _paymentTiming,
          style: theme.textTheme.bodySmall?.copyWith(
            color: scheme.onSurfaceVariant,
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

    final largeText =
        MediaQuery.textScalerOf(context).scale(14) > 18 &&
        MediaQuery.sizeOf(context).width < 600;
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
      showHeading: false,
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

  /// A selectable delivery or pickup segment; closed delivery cannot be tapped.
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
    final foreground = selected ? scheme.onPrimary : scheme.onSurface;
    return Semantics(
      selected: selected,
      button: true,
      enabled: enabled,
      inMutuallyExclusiveGroup: true,
      child: Material(
        color: selected ? scheme.primary : scheme.surfaceContainerLow,
        borderRadius: BorderRadius.circular(12),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: enabled ? () => setState(() => _fulfillment = value) : null,
          child: Opacity(
            opacity: enabled ? 1 : 0.45,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
              child: Row(
                children: [
                  Icon(icon, color: foreground, size: 22),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(
                          label,
                          style: theme.textTheme.labelLarge?.copyWith(
                            color: foreground,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          detail,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: foreground,
                          ),
                        ),
                      ],
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
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                OutlinedButton.icon(
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size(48, 48),
                  ),
                  onPressed: () => _pickPrescription(photo: true),
                  icon: const Icon(Icons.photo_camera_outlined, size: 20),
                  label: Text(pages.isEmpty ? 'Take photos' : 'Add photo'),
                ),
                OutlinedButton.icon(
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size(48, 48),
                  ),
                  onPressed: () => _pickPrescription(photo: false),
                  icon: const Icon(Icons.upload_file_outlined, size: 20),
                  label: const Text('Upload file'),
                ),
              ],
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
          onPressed: () => setState(
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
