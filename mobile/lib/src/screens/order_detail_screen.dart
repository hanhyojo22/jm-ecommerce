import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api_exception.dart';
import '../core/attachment_picker.dart';
import '../core/formatters.dart';
import '../models/models.dart';
import '../providers/auth_providers.dart';
import '../providers/cart_providers.dart';
import '../providers/checkout_providers.dart';
import '../providers/order_providers.dart';
import '../theme.dart';
import '../widgets/common.dart';
import '../widgets/status_notice.dart';
import 'order_support_screen.dart';

class OrderDetailScreen extends ConsumerStatefulWidget {
  const OrderDetailScreen({super.key, required this.orderId, this.returnFor});

  final String orderId;

  /// A branch order id from the orders list's Return/Refund button: its
  /// return form opens as soon as the order has loaded.
  final String? returnFor;

  @override
  ConsumerState<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends ConsumerState<OrderDetailScreen> {
  bool _busy = false;
  bool _summaryExpanded = false;
  bool _returnOpened = false;
  Timer? _refreshTimer;

  @override
  void initState() {
    super.initState();
    if (widget.returnFor != null) {
      ref.listenManual(orderProvider(widget.orderId), (_, next) {
        final order = next.value;
        if (order == null || _returnOpened) {
          return;
        }
        final branchOrder = order.sellerOrders
            .where((s) => s.id == widget.returnFor)
            .firstOrNull;
        if (branchOrder == null) {
          return;
        }
        _returnOpened = true;
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (mounted) {
            _openSupport(order, branchOrder, requestReturn: true);
          }
        });
      }, fireImmediately: true);
    }
    _refreshTimer = Timer.periodic(const Duration(seconds: 15), (_) {
      if (!mounted ||
          _busy ||
          WidgetsBinding.instance.lifecycleState != AppLifecycleState.resumed ||
          ModalRoute.of(context)?.isCurrent != true) {
        return;
      }
      final order = ref.read(orderProvider(widget.orderId));
      if (!order.isLoading &&
          order.value != null &&
          !['delivered', 'cancelled'].contains(order.value!.status)) {
        ref.invalidate(orderProvider(widget.orderId));
      }
    });
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    super.dispose();
  }

  String _statusHeadline(OrderDetail order) => switch (order.status) {
    'delivered' => 'Completed',
    'cancelled' => 'Order Closed',
    'shipped' => order.isPickup ? 'Ready for pickup' : 'Out for delivery',
    'confirmed' || 'processing' => 'Preparing order',
    'pending' when order.canPay => 'Awaiting Payment',
    _ => statusLabel(order.status),
  };

  String _statusMessage(OrderDetail order) => switch (order.status) {
    'delivered' => 'You have confirmed that your order has been delivered and received. Thank you for shopping with us.',
    'cancelled' => 'You have closed your order. We hope to see you again soon!',
    'shipped' when order.isPickup => 'Your order is ready at the branch. Bring your order number when you pick it up.',
    'shipped' => 'The rider is on the way. Confirm receipt once it arrives.',
    'confirmed' || 'processing' => 'The pharmacy is preparing your order.',
    'pending' when order.canPay =>
      'Complete your online payment so the pharmacy can prepare your order.',
    // The placed time already sits under the headline.
    _ => 'We will update you as it moves.',
  };

  int _activeStep(String status) => switch (status) {
    'pending' => 0,
    'confirmed' || 'processing' => 1,
    'shipped' => 2,
    'delivered' => 3,
    _ => -1,
  };

  ShapeBorder _cardShape(ColorScheme scheme) => RoundedRectangleBorder(
    borderRadius: BorderRadius.circular(appSurfaceRadius),
    side: BorderSide(color: scheme.outlineVariant.withValues(alpha: 0.7)),
  );

  @override
  Widget build(BuildContext context) {
    final order = ref.watch(orderProvider(widget.orderId));
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      backgroundColor: scheme.surface,
      appBar: AppBar(
        title: Text(
          order.value == null
              ? 'Order Details'
              : 'Order ${order.value!.shortNumber}',
        ),
        actions: [
          IconButton(
            tooltip: 'Cart',
            onPressed: () => context.push('/cart'),
            icon: const Icon(Icons.shopping_cart_outlined),
          ),
          IconButton(
            tooltip: 'Refresh',
            onPressed: () => ref.invalidate(orderProvider(widget.orderId)),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: AsyncView(
        // A failed refresh keeps the order on screen with a retry banner.
        value: order.hasValue ? AsyncData(order.requireValue) : order,
        onRetry: () => ref.invalidate(orderProvider(widget.orderId)),
        builder: (value) => Column(
          children: [
            if (order.hasError)
              RefreshNoticeStrip(
                message: 'Could not refresh this order.',
                actionLabel: 'Retry',
                onAction: () => ref.invalidate(orderProvider(widget.orderId)),
              ),
            Expanded(
              child: RefreshIndicator(
                onRefresh: () async {
                  ref.invalidate(orderProvider(widget.orderId));
                  await ref.read(orderProvider(widget.orderId).future);
                },
                child: ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(12, 8, 12, 24),
                  children: [
                    _statusStepperCard(value),
                    const SizedBox(height: 10),
                    if (value.prescriptionStatus != null) ...[
                      _prescriptionCard(value),
                      const SizedBox(height: 10),
                    ],
                    _deliveryAndGuaranteeCard(value),
                    const SizedBox(height: 10),
                    for (final sellerOrder in value.sellerOrders) ...[
                      _sellerOrderCard(value, sellerOrder),
                      const SizedBox(height: 10),
                    ],
                    _orderSummaryCard(value),
                  ],
                ),
              ),
            ),
            _bottomBar(value),
          ],
        ),
      ),
    );
  }

  Widget _prescriptionCard(OrderDetail order) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final rejected = order.prescriptionStatus == 'rejected';
    final needsPhoto = order.prescriptionStatus == 'needs_photo';
    final (title, message) = switch (order.prescriptionStatus) {
      'needs_photo' => (
        'Send a clearer prescription photo',
        'The pharmacist says: ${order.prescriptionReason ?? 'the photo could not be read.'}',
      ),
      'approved' => (
        'Prescription approved',
        'A pharmacist approved your prescription.',
      ),
      'rejected' => (
        'Prescription not accepted',
        'Your order was cancelled: ${order.prescriptionReason ?? 'the pharmacist could not accept it.'}',
      ),
      _ => (
        'Prescription in review',
        'Pharmacist is reviewing your prescription.',
      ),
    };

    return Card(
      margin: EdgeInsets.zero,
      shape: _cardShape(scheme),
      color: rejected ? scheme.errorContainer : null,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: theme.textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w800,
                color: rejected ? scheme.onErrorContainer : null,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              message,
              style: theme.textTheme.bodySmall?.copyWith(
                color: rejected ? scheme.onErrorContainer : null,
              ),
            ),
            if (order.prescriptionPages.isNotEmpty) ...[
              const SizedBox(height: 10),
              Text(
                '${order.prescriptionPages.length} ${order.prescriptionPages.length == 1 ? 'page' : 'pages'} sent',
                style: theme.textTheme.labelMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                  color: rejected ? scheme.onErrorContainer : null,
                ),
              ),
              const SizedBox(height: 4),
              for (final page in order.prescriptionPages)
                Padding(
                  padding: const EdgeInsets.only(bottom: 2),
                  child: Row(
                    children: [
                      Icon(
                        Icons.description_outlined,
                        size: 15,
                        color: rejected
                            ? scheme.onErrorContainer
                            : scheme.onSurfaceVariant,
                      ),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          page.name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: rejected ? scheme.onErrorContainer : null,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
            ],
            if (needsPhoto) ...[
              const SizedBox(height: 10),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  FilledButton.icon(
                    onPressed: _busy
                        ? null
                        : () => _resendPrescription(order, photo: true),
                    icon: const Icon(Icons.photo_camera_outlined, size: 18),
                    label: const Text('Send new pages'),
                  ),
                  OutlinedButton.icon(
                    onPressed: _busy
                        ? null
                        : () => _resendPrescription(order, photo: false),
                    icon: const Icon(Icons.picture_as_pdf_outlined, size: 18),
                    label: const Text('Send PDF'),
                  ),
                ],
              ),
            ],
            if (order.prescriptionTimeline.isNotEmpty) ...[
              const SizedBox(height: 10),
              Theme(
                data: theme.copyWith(dividerColor: Colors.transparent),
                child: ExpansionTile(
                  tilePadding: EdgeInsets.zero,
                  childrenPadding: EdgeInsets.zero,
                  dense: true,
                  title: Text(
                    'Review history',
                    style: theme.textTheme.labelLarge?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  children: [
                    for (final event in order.prescriptionTimeline)
                      Align(
                        alignment: Alignment.centerLeft,
                        child: Padding(
                          padding: const EdgeInsets.only(bottom: 6),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                switch (event.action) {
                                  'prescription.submitted' =>
                                    'You sent the prescription',
                                  'prescription.resubmitted' =>
                                    'You sent a new copy',
                                  'prescription.approved' =>
                                    'Prescription approved',
                                  'prescription.rejected' =>
                                    'Prescription rejected',
                                  'prescription.needs_photo' =>
                                    'Clearer copy requested',
                                  _ => event.action,
                                },
                                style: theme.textTheme.bodySmall?.copyWith(
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              if (event.at != null)
                                Text(
                                  dateTime(event.at!),
                                  style: theme.textTheme.bodySmall?.copyWith(
                                    color: scheme.onSurfaceVariant,
                                  ),
                                ),
                              if (event.reason != null)
                                Text(
                                  event.reason!,
                                  style: theme.textTheme.bodySmall?.copyWith(
                                    color: scheme.onSurfaceVariant,
                                  ),
                                ),
                            ],
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Future<void> _resendPrescription(
    OrderDetail order, {
    required bool photo,
  }) async {
    final picker = ref.read(attachmentPickerProvider);
    final List<PickedAttachment> picked;
    try {
      picked = photo ? await picker.pickImages() : await picker.pickDocuments();
    } on AttachmentTooLargeException {
      _showFeedback('Choose a file under 5 MB.');
      return;
    }
    if (picked.isEmpty || !mounted) return;
    if (picked.length > 10) {
      _showFeedback('Up to 10 prescription pages.');
      return;
    }

    setState(() => _busy = true);
    try {
      await ref.read(apiClientProvider).resubmitPrescription(order.id, picked);
      ref.invalidate(orderProvider(widget.orderId));
      _showFeedback(
        'Prescription sent for review.',
        kind: NoticeKind.success,
      );
    } on ApiException catch (error) {
      _showFeedback(error.friendly);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  /// Card 1: Status hero header + 4-step visual progress tracker + Pay now CTA.
  Widget _statusStepperCard(OrderDetail order) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final largeText = MediaQuery.textScalerOf(context).scale(14) > 18;
    final accent = statusColour(context, order.status);
    final step = _activeStep(order.status);
    final showPill = statusLabel(order.status) != _statusHeadline(order);

    final payButton = largeText
        ? FilledButton(
            style: FilledButton.styleFrom(
              minimumSize: const Size(0, 44),
              padding: const EdgeInsets.symmetric(horizontal: 16),
            ),
            onPressed: _busy ? null : () => _pay(order),
            child: const Text('Pay now'),
          )
        : FilledButton.icon(
            style: FilledButton.styleFrom(
              minimumSize: const Size(0, 44),
              padding: const EdgeInsets.symmetric(horizontal: 16),
            ),
            onPressed: _busy ? null : () => _pay(order),
            icon: const Icon(Icons.lock_open_rounded, size: 18),
            label: const Text('Pay now'),
          );

    return Material(
      color: scheme.surfaceContainerLowest,
      shape: _cardShape(scheme),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          DecoratedBox(
            // A flat tint of the status colour, not a fade.
            decoration: BoxDecoration(color: accent.withValues(alpha: 0.10)),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        width: 34,
                        height: 34,
                        decoration: BoxDecoration(
                          color: accent.withValues(alpha: 0.16),
                          shape: BoxShape.circle,
                        ),
                        child: Icon(
                          order.status == 'cancelled'
                              ? Icons.cancel_outlined
                              : order.status == 'delivered'
                              ? Icons.check_circle_rounded
                              : Icons.local_shipping_outlined,
                          size: 19,
                          color: accent,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _statusHeadline(order),
                              style: theme.textTheme.titleMedium?.copyWith(
                                fontWeight: FontWeight.w800,
                                fontSize: 18,
                              ),
                            ),
                            Text(
                              'Placed ${dateTime(order.createdAt)}',
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: scheme.onSurfaceVariant,
                              ),
                            ),
                          ],
                        ),
                      ),
                      // The pill only repeats a headline like "Order placed".
                      if (!largeText && showPill)
                        StatusPill(status: order.status),
                    ],
                  ),
                  if (largeText && showPill) ...[
                    const SizedBox(height: 8),
                    StatusPill(status: order.status),
                  ],
                  const SizedBox(height: 10),
                  Text(
                    _statusMessage(order),
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: scheme.onSurfaceVariant,
                      fontSize: 13,
                      height: 1.35,
                    ),
                  ),
                  if (!largeText && step >= 0) ...[
                    const SizedBox(height: 14),
                    _OrderProgressStepper(
                      activeStep: step,
                      isPickup: order.isPickup,
                    ),
                  ],
                  if (order.canPay) ...[
                    const SizedBox(height: 14),
                    LayoutBuilder(
                      builder: (context, constraints) {
                        final stack = largeText || constraints.maxWidth < 300;
                        final price = Text(
                          money(order.total),
                          style: theme.textTheme.headlineSmall?.copyWith(
                            fontWeight: FontWeight.w800,
                            color: scheme.primary,
                          ),
                        );

                        if (stack) {
                          return Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              Align(
                                alignment: Alignment.centerLeft,
                                child: FittedBox(
                                  fit: BoxFit.scaleDown,
                                  alignment: Alignment.centerLeft,
                                  child: price,
                                ),
                              ),
                              const SizedBox(height: 10),
                              payButton,
                            ],
                          );
                        }

                        return Row(
                          children: [
                            Expanded(child: price),
                            const SizedBox(width: 12),
                            payButton,
                          ],
                        );
                      },
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  /// Card 2: Delivery Address right below status, with the wrong-item help row.
  Widget _deliveryAndGuaranteeCard(OrderDetail order) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final pickupSellerOrder = order.isPickup && order.sellerOrders.isNotEmpty
        ? order.sellerOrders.first
        : null;
    final pickupStore = pickupSellerOrder?.store;

    return Material(
      color: scheme.surfaceContainerLowest,
      shape: _cardShape(scheme),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 34,
                  height: 34,
                  decoration: BoxDecoration(
                    color: scheme.primary.withValues(alpha: 0.10),
                    borderRadius: BorderRadius.circular(appDetailRadius),
                  ),
                  child: Icon(
                    Icons.location_on_rounded,
                    size: 19,
                    color: scheme.primary,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        order.isPickup
                            ? pickupStore?.name == null
                                  ? 'Pickup at the branch'
                                  : 'Pickup at ${pickupStore!.name}'
                            : 'Delivery Address',
                        style: theme.textTheme.labelLarge?.copyWith(
                          fontWeight: FontWeight.w800,
                          color: scheme.primary,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Wrap(
                        spacing: 8,
                        runSpacing: 2,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        children: [
                          Text(
                            order.delivery.recipientName,
                            style: const TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 14,
                            ),
                          ),
                          Text(
                            order.delivery.phone,
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: scheme.onSurfaceVariant,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 3),
                      if (order.isPickup && pickupStore != null) ...[
                        Text(
                          [pickupStore.addressLine, pickupStore.city]
                              .where((part) => part != null && part.isNotEmpty)
                              .join(', '),
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: scheme.onSurfaceVariant,
                            fontSize: 13,
                            height: 1.3,
                          ),
                        ),
                        if (pickupStore.phone != null)
                          Text(
                            'Call the branch: ${pickupStore.phone}',
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: scheme.onSurfaceVariant,
                              fontSize: 13,
                              height: 1.3,
                            ),
                          ),
                        if (pickupStore.openingTime != null &&
                            pickupStore.closingTime != null)
                          Text(
                            'Open ${pickupStore.openingTime}–${pickupStore.closingTime} daily',
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: scheme.onSurfaceVariant,
                              fontSize: 13,
                              height: 1.3,
                            ),
                          ),
                        // pickupStore came from this seller order, so it is set here.
                        if (pickupSellerOrder!.pickupReadyAt != null &&
                            (pickupSellerOrder.status == 'shipped' ||
                                pickupSellerOrder.status == 'delivered'))
                          Padding(
                            padding: const EdgeInsets.only(top: 6),
                            child: Text(
                              '${pickupSellerOrder.status == 'delivered' ? 'Picked up' : 'Ready for pickup'} since ${dateTime(pickupSellerOrder.pickupReadyAt!)}',
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: scheme.primary,
                                fontWeight: FontWeight.w700,
                                fontSize: 13,
                              ),
                            ),
                          ),
                      ] else
                        Text(
                          order.delivery.fullAddress,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: scheme.onSurfaceVariant,
                            fontSize: 13,
                            height: 1.3,
                          ),
                        ),
                      if (order.delivery.note != null &&
                          order.delivery.note!.trim().isNotEmpty) ...[
                        const SizedBox(height: 6),
                        Text(
                          'Note: ${order.delivery.note}',
                          style: theme.textTheme.bodySmall?.copyWith(
                            fontStyle: FontStyle.italic,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
          Divider(height: 1, color: scheme.outlineVariant),
          InkWell(
            onTap: () => _showGuaranteeSheet(order),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 11),
              child: Row(
                children: [
                  Icon(
                    Icons.verified_user_rounded,
                    size: 18,
                    color: scheme.primary,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Wrong or missing item?',
                          style: theme.textTheme.labelLarge?.copyWith(
                            fontWeight: FontWeight.w800,
                            fontSize: 13,
                          ),
                        ),
                        Text(
                          'Report an issue within ${returnWindowLabel(order.sellerOrders.isEmpty ? 24 : order.sellerOrders.first.returnWindowHours)} of delivery.',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: scheme.onSurfaceVariant,
                            fontSize: 11.5,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Icon(
                    Icons.chevron_right_rounded,
                    size: 18,
                    color: scheme.onSurfaceVariant,
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  /// Card 3: Store & Order Items card with Help & Chat with Seller footer.
  Widget _sellerOrderCard(OrderDetail order, SellerOrder sellerOrder) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final largeText = MediaQuery.textScalerOf(context).scale(14) > 18;
    final storeName = sellerOrder.store?.name ?? 'Pharmacy';
    final storeSlug = sellerOrder.store?.slug;

    return Material(
      color: scheme.surfaceContainerLowest,
      shape: _cardShape(scheme),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    // The name takes whatever the status pill leaves, instead of a fixed half.
                    Expanded(
                      child: InkWell(
                        borderRadius: BorderRadius.circular(appSurfaceRadius),
                        onTap: storeSlug != null && storeSlug.isNotEmpty
                            ? () => context.push('/store/$storeSlug')
                            : null,
                        child: Padding(
                          padding: const EdgeInsets.symmetric(vertical: 2),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              StoreAvatar(
                                name: storeName,
                                logoUrl: sellerOrder.store?.logoUrl,
                                radius: 14,
                              ),
                              const SizedBox(width: 8),
                              Flexible(
                                child: Text(
                                  storeName,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: theme.textTheme.titleSmall?.copyWith(
                                    fontWeight: FontWeight.w800,
                                    fontSize: 15,
                                  ),
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
                        ),
                      ),
                    ),
                    // One branch's status already leads the page; repeat it only when it differs.
                    if (order.sellerOrders.length > 1 ||
                        sellerOrder.status != order.status) ...[
                      const SizedBox(width: 8),
                      ConstrainedBox(
                        constraints: const BoxConstraints(maxWidth: 160),
                        child: FittedBox(
                          fit: BoxFit.scaleDown,
                          child: StatusPill(status: sellerOrder.status),
                        ),
                      ),
                    ],
                  ],
                ),
                if (sellerOrder.riderName case final rider?) ...[
                  const SizedBox(height: 12),
                  _RiderCard(name: rider, phone: sellerOrder.riderPhone),
                ],
                for (var i = 0; i < sellerOrder.lines.length; i++) ...[
                  if (i > 0) ...[
                    const SizedBox(height: 10),
                    Divider(
                      height: 1,
                      color: scheme.outlineVariant.withValues(alpha: 0.5),
                    ),
                  ],
                  const SizedBox(height: 12),
                  _OrderDetailItemRow(
                    line: sellerOrder.lines[i],
                    status: sellerOrder.status,
                    largeText: largeText,
                    busy: _busy,
                    onReview: () => _review(sellerOrder.lines[i]),
                    onReturnInfo: () =>
                        _openSupport(order, sellerOrder, requestReturn: true),
                  ),
                ],
                if (sellerOrder.latestReturnStatus
                    case final returnStatus?) ...[
                  const SizedBox(height: 12),
                  Text(
                    'Return request: ${statusLabel(returnStatus)}',
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
                if (sellerOrder.canConfirmReceipt) ...[
                  const SizedBox(height: 12),
                  Wrap(
                    alignment: WrapAlignment.end,
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      if (sellerOrder.canConfirmReceipt)
                        FilledButton.tonal(
                          onPressed: _busy ? null : () => _confirm(sellerOrder),
                          child: const Text('Confirm received'),
                        ),
                    ],
                  ),
                ],
              ],
            ),
          ),
          Divider(height: 1, color: scheme.outlineVariant),
          IntrinsicHeight(
            child: Row(
              children: [
                Expanded(
                  child: InkWell(
                    onTap: () => _openSupport(order, sellerOrder),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 11,
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(
                            Icons.headset_mic_rounded,
                            size: 17,
                            color: scheme.onSurfaceVariant,
                          ),
                          const SizedBox(width: 6),
                          Flexible(
                            child: Text(
                              'Order help',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: theme.textTheme.bodyMedium?.copyWith(
                                fontWeight: FontWeight.w600,
                                fontSize: 13,
                              ),
                            ),
                          ),
                          const SizedBox(width: 3),
                          Icon(
                            Icons.chevron_right_rounded,
                            size: 17,
                            color: scheme.onSurfaceVariant,
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
                VerticalDivider(width: 1, color: scheme.outlineVariant),
                Expanded(
                  child: InkWell(
                    onTap: _busy ? null : () => _chat(sellerOrder),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 11,
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(
                            Icons.chat_bubble_rounded,
                            size: 16,
                            color: scheme.primary,
                          ),
                          const SizedBox(width: 6),
                          Flexible(
                            child: Text(
                              'Chat with pharmacy',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: theme.textTheme.bodyMedium?.copyWith(
                                fontWeight: FontWeight.w700,
                                fontSize: 13,
                                color: scheme.primary,
                              ),
                            ),
                          ),
                          const SizedBox(width: 3),
                          Icon(
                            Icons.chevron_right_rounded,
                            size: 17,
                            color: scheme.primary,
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  /// Card 4: Price Breakdown + Order No. with Copy + expandable Order Summary details.
  Widget _orderSummaryCard(OrderDetail order) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final payment = order.payment;

    return Material(
      color: scheme.surfaceContainerLowest,
      shape: _cardShape(scheme),
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 6),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Total',
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                    fontSize: 15,
                  ),
                ),
                Flexible(
                  child: Text(
                    money(order.total),
                    textAlign: TextAlign.end,
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                      fontSize: 17,
                      color: scheme.primary,
                    ),
                  ),
                ),
              ],
            ),
            // Explains a total below the item prices without opening the summary.
            if ((double.tryParse(order.discountAmount) ?? 0) > 0)
              Align(
                alignment: AlignmentDirectional.centerEnd,
                child: Text(
                  'Includes SC/PWD discount −${money(order.discountAmount)}',
                  textAlign: TextAlign.end,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: statusColour(context, 'completed'),
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            const SizedBox(height: 12),
            Wrap(
              alignment: WrapAlignment.spaceBetween,
              crossAxisAlignment: WrapCrossAlignment.center,
              spacing: 8,
              runSpacing: 4,
              children: [
                Text(
                  'Order No.',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                    fontSize: 14,
                  ),
                ),
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Flexible(
                      child: Text(
                        order.number,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: scheme.onSurfaceVariant,
                          fontSize: 13.5,
                        ),
                      ),
                    ),
                    const SizedBox(width: 6),
                    InkWell(
                      borderRadius: BorderRadius.circular(appSurfaceRadius),
                      onTap: () async {
                        await Clipboard.setData(
                          ClipboardData(text: order.number),
                        );
                        if (mounted) {
                          _showFeedback(
                            'Order number copied.',
                            kind: NoticeKind.success,
                          );
                        }
                      },
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 2,
                        ),
                        decoration: BoxDecoration(
                          color: scheme.primary.withValues(alpha: 0.10),
                          borderRadius: BorderRadius.circular(appSurfaceRadius),
                        ),
                        child: Text(
                          'Copy',
                          style: TextStyle(
                            color: scheme.primary,
                            fontWeight: FontWeight.w800,
                            fontSize: 12.5,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
            if (_summaryExpanded) ...[
              const SizedBox(height: 12),
              Divider(height: 1, color: scheme.outlineVariant),
              const SizedBox(height: 12),
              _summaryRow('Subtotal', money(order.subtotal)),
              const SizedBox(height: 8),
              _summaryRow('Delivery fee', money(order.shippingAmount)),
              const SizedBox(height: 8),
              if ((double.tryParse(order.discountAmount) ?? 0) > 0) ...[
                _summaryRow(
                  '${order.discountCardType == 'pwd' ? 'PWD' : 'Senior citizen'} discount',
                  '−${money(order.discountAmount)}',
                ),
                const SizedBox(height: 4),
                Text(
                  'Show the ID ending ${order.discountCardNumber ?? ''} when you receive the order.',
                  style: Theme.of(context).textTheme.bodySmall
                      ?.copyWith(color: scheme.onSurfaceVariant),
                ),
                const SizedBox(height: 8),
              ],
              _summaryRow(
                'Payment Method',
                payment?.label ?? 'Cash on delivery',
              ),
              if (payment != null) ...[
                const SizedBox(height: 8),
                _summaryRow('Payment Status', statusLabel(payment.status)),
              ],
              const SizedBox(height: 8),
              _summaryRow('Created Time', dateTime(order.createdAt)),
            ],
            const SizedBox(height: 10),
            Divider(height: 1, color: scheme.outlineVariant),
            InkWell(
              onTap: () => setState(() => _summaryExpanded = !_summaryExpanded),
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 11),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Flexible(
                      child: Text(
                        _summaryExpanded
                            ? 'Hide Order Summary'
                            : 'View Order Summary',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: scheme.primary,
                          fontWeight: FontWeight.w700,
                          fontSize: 13.5,
                        ),
                      ),
                    ),
                    const SizedBox(width: 4),
                    Icon(
                      _summaryExpanded
                          ? Icons.keyboard_arrow_up_rounded
                          : Icons.keyboard_arrow_down_rounded,
                      size: 20,
                      color: scheme.primary,
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

  Widget _summaryRow(String label, String value) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Flexible(
          child: Text(
            label,
            style: theme.textTheme.bodySmall?.copyWith(
              color: scheme.onSurfaceVariant,
              fontSize: 13,
            ),
          ),
        ),
        const SizedBox(width: 12),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.end,
            style: theme.textTheme.bodySmall?.copyWith(
              color: scheme.onSurface,
              fontWeight: FontWeight.w600,
              fontSize: 13,
            ),
          ),
        ),
      ],
    );
  }

  /// Sticky bottom bar: two equal secondary actions, and Order again as the
  /// one full-width primary action on a delivered order, nearest the thumb.
  Widget _bottomBar(OrderDetail order) {
    final scheme = Theme.of(context).colorScheme;
    // Large text keeps only delivery details so the bar doesn't cover the
    // list; the store stays reachable from its name on the store card.
    final largeText = MediaQuery.textScalerOf(context).scale(14) > 18;
    final storeSlug = order.sellerOrders.firstOrNull?.store?.slug;
    final showBranch = !largeText && storeSlug != null && storeSlug.isNotEmpty;
    final secondary = OutlinedButton.styleFrom(
      minimumSize: const Size(0, 48),
      padding: const EdgeInsets.symmetric(horizontal: 12),
      shape: const StadiumBorder(),
      foregroundColor: scheme.onSurface,
      side: BorderSide(color: scheme.outlineVariant, width: 1.2),
    );

    // Full width: the page body is a Column, which would otherwise shrink the
    // bar to its buttons and centre it with gaps at both sides.
    return SizedBox(
      width: double.infinity,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: scheme.surfaceContainerLowest,
          border: Border(top: BorderSide(color: scheme.outlineVariant)),
        ),
        child: SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton.icon(
                        style: secondary,
                        onPressed: () => _showLogisticSheet(order),
                        icon: const Icon(
                          Icons.local_shipping_outlined,
                          size: 18,
                        ),
                        label: const Text(
                          'Delivery details',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ),
                    if (showBranch) ...[
                      const SizedBox(width: 10),
                      Expanded(
                        child: OutlinedButton.icon(
                          style: secondary,
                          onPressed: () => context.push('/store/$storeSlug'),
                          icon: const Icon(Icons.storefront_outlined, size: 18),
                          label: const Text(
                            'Visit branch',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
                if (order.status == 'delivered') ...[
                  const SizedBox(height: 10),
                  FilledButton.icon(
                    style: FilledButton.styleFrom(
                      minimumSize: const Size.fromHeight(52),
                      shape: const StadiumBorder(),
                    ),
                    onPressed: _busy ? null : () => _orderAgain(order),
                    icon: const Icon(Icons.replay_rounded, size: 20),
                    label: const Text('Order again'),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _showGuaranteeSheet(OrderDetail order) async {
    if (order.sellerOrders.isEmpty) {
      return;
    }
    if (order.sellerOrders.length == 1) {
      await _openSupport(order, order.sellerOrders.single);
      return;
    }
    final selected = await showModalBottomSheet<SellerOrder>(
      context: context,
      showDragHandle: true,
      builder: (context) => ListView(
        shrinkWrap: true,
        children: [
          for (final branchOrder in order.sellerOrders)
            ListTile(
              title: Text(branchOrder.store?.name ?? 'Pharmacy'),
              onTap: () => Navigator.pop(context, branchOrder),
            ),
        ],
      ),
    );
    if (selected != null && mounted) {
      await _openSupport(order, selected);
    }
  }

  Future<void> _openSupport(
    OrderDetail order,
    SellerOrder branchOrder, {
    bool requestReturn = false,
  }) async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => OrderSupportScreen(
          order: order,
          sellerOrder: branchOrder,
          requestReturn: requestReturn,
        ),
      ),
    );
    if (mounted) {
      ref.invalidate(orderProvider(widget.orderId));
      ref.invalidate(ordersProvider);
    }
  }

  Future<void> _showLogisticSheet(OrderDetail order) async {
    await showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      useSafeArea: true,
      builder: (context) {
        final theme = Theme.of(context);
        return SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Delivery details · ${order.shortNumber}',
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 10),
              Text(
                'Status: ${_statusHeadline(order)}',
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 6),
              Text(
                'Recipient: ${order.delivery.recipientName} (${order.delivery.phone})',
              ),
              const SizedBox(height: 4),
              Text(order.delivery.fullAddress),
              const SizedBox(height: 6),
              Text(
                'Placed: ${dateTime(order.createdAt)}',
                style: theme.textTheme.bodySmall,
              ),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: () => Navigator.of(context).pop(),
                child: const Text('Close'),
              ),
            ],
          ),
        );
      },
    );
  }

  /// Refreshes the order and list through the container, which outlives this
  /// screen, so a reply that lands after the user leaves stays safe.
  void _refreshOrders(ProviderContainer container) {
    container.invalidate(orderProvider(widget.orderId));
    container.invalidate(ordersProvider);
  }

  Future<void> _pay(OrderDetail order) async {
    final container = ProviderScope.containerOf(context, listen: false);
    setState(() => _busy = true);

    try {
      final url = await container.read(apiClientProvider).payOrder(order.id);
      final uri = url == null ? null : Uri.tryParse(url);
      final opened =
          uri != null &&
          uri.hasScheme &&
          await launchUrl(uri, mode: LaunchMode.externalApplication);

      _refreshOrders(container);

      if (!opened && mounted) {
        _showFeedback('Could not open payment. Please try again.');
      }
    } on ApiException catch (error) {
      if (mounted) {
        _showFeedback(error.friendly);
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _confirm(SellerOrder sellerOrder) async {
    // Confirming releases payment to the store, so it must be deliberate.
    final approved = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Confirm you received this order?'),
        content: Text(
          'Only confirm once every item from '
          '${sellerOrder.store?.name ?? 'the pharmacy'} has arrived in good '
          'condition.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Not yet'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Yes, received'),
          ),
        ],
      ),
    );

    if (approved != true || !mounted) {
      return;
    }

    final container = ProviderScope.containerOf(context, listen: false);
    setState(() => _busy = true);

    try {
      await container.read(apiClientProvider).confirmReceived(sellerOrder.id);
      _refreshOrders(container);

      if (mounted) {
        _showFeedback(
          'Thanks for confirming your ${sellerOrder.store?.name ?? 'store'} order.',
          kind: NoticeKind.success,
        );
      }
    } on ApiException catch (error) {
      if (mounted) {
        _showFeedback(error.friendly);
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _orderAgain(OrderDetail order) async {
    if (_busy) {
      return;
    }
    setState(() => _busy = true);

    try {
      final skipped = await ref.read(cartProvider.notifier).reorder(order.id);
      if (!mounted) {
        return;
      }
      _showFeedback(
        skipped.isEmpty
            ? 'Added to your cart.'
            : 'Added to your cart. Your branch doesn’t have ${skipped.join(', ')} right now.',
        kind: skipped.isEmpty ? NoticeKind.success : NoticeKind.info,
      );
      context.push('/cart');
    } on ApiException catch (error) {
      if (mounted) {
        _showFeedback(error.friendly);
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _chat(SellerOrder sellerOrder) async {
    if (_busy) {
      return;
    }
    setState(() => _busy = true);

    try {
      final conversation = await ref
          .read(apiClientProvider)
          .startConversation(sellerOrderId: sellerOrder.id);

      if (mounted) {
        context.push(
          '/conversations/${conversation.id}?seller_order_id=${sellerOrder.id}',
        );
      }
    } on ApiException catch (error) {
      if (mounted) {
        _showFeedback(error.friendly);
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _review(OrderLine line) async {
    final input = await showProductReviewSheet(context, line.productName);

    if (input == null || !mounted) {
      return;
    }

    final container = ProviderScope.containerOf(context, listen: false);
    setState(() => _busy = true);

    try {
      await container
          .read(apiClientProvider)
          .submitReview(
            orderItemId: line.id,
            rating: input.rating,
            body: input.body,
          );
      _refreshOrders(container);

      if (mounted) {
        _showFeedback('Your review was published.', kind: NoticeKind.success);
      }
    } on ApiException catch (error) {
      if (mounted) {
        _showFeedback(error.friendly);
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  void _showFeedback(String message, {NoticeKind kind = NoticeKind.error}) {
    AppNotice.of(context).show(message, kind: kind);
  }
}

/// 4-step horizontal order progress tracker: Placed → Preparing → On the way → Delivered.
class _OrderProgressStepper extends StatelessWidget {
  const _OrderProgressStepper({
    required this.activeStep,
    this.isPickup = false,
  });

  final int activeStep;
  final bool isPickup;

  List<({String label, IconData icon})> get _steps => [
    (label: 'Placed', icon: Icons.receipt_long_rounded),
    (label: 'Preparing', icon: Icons.inventory_2_rounded),
    isPickup
        ? (label: 'Ready for pickup', icon: Icons.storefront_rounded)
        : (label: 'Out for delivery', icon: Icons.delivery_dining_rounded),
    (
      label: isPickup ? 'Picked up' : 'Delivered',
      icon: Icons.check_circle_rounded,
    ),
  ];

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Row(
      children: [
        for (var i = 0; i < _steps.length; i++) ...[
          Expanded(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Container(
                        height: 2.5,
                        color: i == 0
                            ? Colors.transparent
                            : i <= activeStep
                            ? scheme.primary
                            : scheme.outlineVariant,
                      ),
                    ),
                    Container(
                      width: 26,
                      height: 26,
                      decoration: BoxDecoration(
                        color: i <= activeStep
                            ? scheme.primary
                            : scheme.surfaceContainerHighest,
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        _steps[i].icon,
                        size: 14,
                        color: i <= activeStep
                            ? scheme.onPrimary
                            : scheme.onSurfaceVariant,
                      ),
                    ),
                    Expanded(
                      child: Container(
                        height: 2.5,
                        color: i == _steps.length - 1
                            ? Colors.transparent
                            : i < activeStep
                            ? scheme.primary
                            : scheme.outlineVariant,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 5),
                Text(
                  _steps[i].label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.center,
                  style: theme.textTheme.labelSmall?.copyWith(
                    fontSize: 10.5,
                    fontWeight: i <= activeStep
                        ? FontWeight.w800
                        : FontWeight.w500,
                    color: i <= activeStep
                        ? scheme.primary
                        : scheme.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }
}

class _OrderDetailItemRow extends StatelessWidget {
  const _OrderDetailItemRow({
    required this.line,
    required this.status,
    required this.largeText,
    required this.busy,
    required this.onReview,
    required this.onReturnInfo,
  });

  final OrderLine line;
  final String status;
  final bool largeText;
  final bool busy;
  final VoidCallback onReview;
  final VoidCallback onReturnInfo;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final thumbSize = largeText ? 58.0 : 80.0;
    // Same neutral pill as the bottom bar's secondary actions.
    final itemAction = OutlinedButton.styleFrom(
      minimumSize: const Size(0, 44),
      padding: const EdgeInsets.symmetric(horizontal: 18),
      shape: const StadiumBorder(),
      foregroundColor: scheme.onSurface,
      side: BorderSide(color: scheme.outlineVariant, width: 1.2),
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            NetworkThumb(
              url: line.imageUrl,
              width: thumbSize,
              height: thumbSize,
              radius: appDetailRadius,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    line.productName,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      height: 1.25,
                    ),
                  ),
                  if (line.variantLabel != null &&
                      line.variantLabel!.trim().isNotEmpty) ...[
                    const SizedBox(height: 6),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 3,
                      ),
                      decoration: BoxDecoration(
                        color: scheme.surfaceContainerHighest.withValues(
                          alpha: 0.65,
                        ),
                        borderRadius: BorderRadius.circular(appDetailRadius),
                      ),
                      child: Text(
                        line.variantLabel!,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: scheme.onSurfaceVariant,
                          fontSize: 12,
                        ),
                      ),
                    ),
                  ],
                  const SizedBox(height: 8),
                  Wrap(
                    alignment: WrapAlignment.spaceBetween,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    spacing: 8,
                    runSpacing: 2,
                    children: [
                      Text(
                        money(line.unitPrice),
                        style: theme.textTheme.bodyMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                          fontSize: 14.5,
                        ),
                      ),
                      Text(
                        'Qty: ${line.quantity}',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: scheme.onSurface,
                          fontWeight: FontWeight.w600,
                          fontSize: 13.5,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
        if ((status == 'delivered' && (!line.reviewed || !largeText)) ||
            (!largeText && status == 'cancelled')) ...[
          const SizedBox(height: 10),
          Wrap(
            alignment: WrapAlignment.end,
            spacing: 8,
            runSpacing: 6,
            children: [
              if (status == 'delivered' && !line.reviewed)
                OutlinedButton(
                  style: itemAction,
                  onPressed: busy ? null : onReview,
                  child: const Text('Review'),
                ),
              if (!largeText && status == 'delivered')
                OutlinedButton(
                  style: itemAction,
                  onPressed: onReturnInfo,
                  child: const Text('Return/Refund'),
                ),
              if (!largeText && status == 'cancelled')
                OutlinedButton(
                  style: itemAction,
                  onPressed: null,
                  child: const Text('Cancelled'),
                ),
            ],
          ),
        ],
      ],
    );
  }
}

Future<ReviewInput?> showProductReviewSheet(
  BuildContext context,
  String productName,
) => showModalBottomSheet<ReviewInput>(
  context: context,
  isScrollControlled: true,
  showDragHandle: true,
  useSafeArea: true,
  builder: (context) => _ReviewSheet(
    title: 'Review $productName',
    fieldLabel: 'Your review (optional)',
    submitLabel: 'Publish review',
    maxLength: 3000,
  ),
);

class ReviewInput {
  const ReviewInput({required this.rating, required this.body});

  final int rating;
  final String body;
}

class _ReviewSheet extends StatefulWidget {
  const _ReviewSheet({
    required this.title,
    required this.fieldLabel,
    required this.submitLabel,
    required this.maxLength,
  });

  final String title;
  final String fieldLabel;
  final String submitLabel;
  final int maxLength;

  @override
  State<_ReviewSheet> createState() => _ReviewSheetState();
}

class _ReviewSheetState extends State<_ReviewSheet> {
  final _body = TextEditingController();
  int _rating = 5;

  @override
  void dispose() {
    _body.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        top: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              widget.title,
              style: Theme.of(context).textTheme.titleMedium
                  ?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 12),
            Semantics(
              label: 'Rating: $_rating out of 5',
              child: Row(
                children: List.generate(5, (index) {
                  final star = index + 1;

                  return IconButton(
                    tooltip: '$star star${star == 1 ? '' : 's'}',
                    onPressed: () => setState(() => _rating = star),
                    icon: Icon(
                      star <= _rating
                          ? Icons.star_rounded
                          : Icons.star_outline_rounded,
                      color: Theme.of(context).colorScheme.secondary,
                      size: 32,
                    ),
                  );
                }),
              ),
            ),
            const SizedBox(height: 6),
            TextField(
              controller: _body,
              maxLines: 4,
              maxLength: widget.maxLength,
              decoration: InputDecoration(
                labelText: widget.fieldLabel,
                alignLabelWithHint: true,
              ),
            ),
            const SizedBox(height: 10),
            FilledButton(
              onPressed: () => Navigator.of(context)
                  .pop(ReviewInput(rating: _rating, body: _body.text.trim())),
              child: Text(widget.submitLabel),
            ),
          ],
        ),
      ),
    );
  }
}

/// The rider who has the order, with a one-tap call.
class _RiderCard extends StatelessWidget {
  const _RiderCard({required this.name, this.phone});

  final String name;
  final String? phone;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final number = phone;

    return DecoratedBox(
      decoration: BoxDecoration(
        color: scheme.primaryContainer.withValues(alpha: 0.35),
        borderRadius: BorderRadius.circular(appSurfaceRadius),
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 10, 8, 10),
        child: Row(
          children: [
            Icon(Icons.two_wheeler_rounded, color: scheme.primary),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Your rider', style: theme.textTheme.labelMedium),
                  Text(
                    name,
                    style: theme.textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  if (number != null)
                    Text(number, style: theme.textTheme.bodySmall),
                ],
              ),
            ),
            if (number != null)
              FilledButton.icon(
                // The theme's full-width minimum breaks inside a Row.
                style: FilledButton.styleFrom(minimumSize: const Size(0, 44)),
                onPressed: () => launchUrl(Uri(scheme: 'tel', path: number)),
                icon: const Icon(Icons.call_rounded, size: 18),
                label: const Text('Call'),
              ),
          ],
        ),
      ),
    );
  }
}
