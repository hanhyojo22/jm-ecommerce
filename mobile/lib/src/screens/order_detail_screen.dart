import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api_exception.dart';
import '../core/formatters.dart';
import '../models/models.dart';
import '../providers/auth_providers.dart';
import '../providers/order_providers.dart';
import '../theme.dart';
import '../widgets/common.dart';

class OrderDetailScreen extends ConsumerStatefulWidget {
  const OrderDetailScreen({super.key, required this.orderId});

  final String orderId;

  @override
  ConsumerState<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends ConsumerState<OrderDetailScreen> {
  bool _busy = false;
  bool _summaryExpanded = false;

  String _statusHeadline(OrderDetail order) => switch (order.status) {
    'delivered' => 'Completed',
    'cancelled' => 'Order Closed',
    'shipped' => 'On the way',
    'confirmed' || 'processing' => 'Preparing order',
    'pending' when order.canPay => 'Awaiting Payment',
    _ => statusLabel(order.status),
  };

  String _statusMessage(OrderDetail order) => switch (order.status) {
    'delivered' => 'You have confirmed that your order has been delivered and received. Thank you for shopping with us.',
    'cancelled' => 'You have closed your order. We hope to see you again soon!',
    'shipped' =>
      'Confirm receipt when the parcel arrives so the store can be paid.',
    'confirmed' || 'processing' => 'The seller has confirmed your order and is preparing your parcel for dispatch.',
    'pending' when order.canPay => 'Complete your online payment so the store can confirm and ship your order.',
    _ => 'Placed ${dateTime(order.createdAt)}. We will update you as it moves.',
  };

  int _activeStep(String status) => switch (status) {
    'pending' => 0,
    'confirmed' || 'processing' => 1,
    'shipped' => 2,
    'delivered' => 3,
    _ => -1,
  };

  ShapeBorder _cardShape(ColorScheme scheme) => RoundedRectangleBorder(
    borderRadius: BorderRadius.circular(14),
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
        value: order,
        onRetry: () => ref.invalidate(orderProvider(widget.orderId)),
        builder: (value) => Column(
          children: [
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

  /// Card 1: Status hero header + 4-step visual progress tracker + Pay now CTA.
  Widget _statusStepperCard(OrderDetail order) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final largeText = MediaQuery.textScalerOf(context).scale(14) > 18;
    final accent = statusColour(context, order.status);
    final step = _activeStep(order.status);

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
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [
                  accent.withValues(alpha: 0.14),
                  scheme.surfaceContainerLowest,
                ],
              ),
            ),
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
                      if (!largeText) StatusPill(status: order.status),
                    ],
                  ),
                  if (largeText) ...[
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
                    _OrderProgressStepper(activeStep: step),
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

  /// Card 2: Delivery Address right below status + integrated Money Back Guarantee.
  Widget _deliveryAndGuaranteeCard(OrderDetail order) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

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
                    borderRadius: BorderRadius.circular(10),
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
                        'Delivery Address',
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
                      Text(
                        '${order.delivery.addressLine}, ${order.delivery.city}, ${order.delivery.province} ${order.delivery.postalCode}',
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
                          'JMMart Money Back Guarantee',
                          style: theme.textTheme.labelLarge?.copyWith(
                            fontWeight: FontWeight.w800,
                            fontSize: 13,
                          ),
                        ),
                        Text(
                          'Get the item you ordered or your money back.',
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
    final storeName = sellerOrder.store?.name ?? 'Store';
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
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Flexible(
                      child: InkWell(
                        borderRadius: BorderRadius.circular(6),
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
                    Flexible(
                      child: FittedBox(
                        fit: BoxFit.scaleDown,
                        child: StatusPill(status: sellerOrder.status),
                      ),
                    ),
                  ],
                ),
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
                    onReturnInfo: () => _showGuaranteeSheet(order),
                  ),
                ],
                if (sellerOrder.canConfirmReceipt ||
                    sellerOrder.canRateStore ||
                    sellerOrder.storeRating != null) ...[
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
                      if (sellerOrder.canRateStore)
                        OutlinedButton.icon(
                          onPressed: _busy
                              ? null
                              : () => _rateStore(sellerOrder),
                          icon: const Icon(Icons.storefront_outlined, size: 18),
                          label: const Text('Rate store'),
                        )
                      else if (sellerOrder.storeRating != null)
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 6),
                          child: Text(
                            'You rated this store ${sellerOrder.storeRating} out of 5',
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
          Divider(height: 1, color: scheme.outlineVariant),
          IntrinsicHeight(
            child: Row(
              children: [
                Expanded(
                  child: InkWell(
                    onTap: () => _showGuaranteeSheet(order),
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
                            color: scheme.error,
                          ),
                          const SizedBox(width: 6),
                          Flexible(
                            child: Text(
                              'Help',
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
                              'Chat with Seller',
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
                      borderRadius: BorderRadius.circular(6),
                      onTap: () async {
                        await Clipboard.setData(
                          ClipboardData(text: order.number),
                        );
                        if (mounted) {
                          _snack('Order number copied.');
                        }
                      },
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 2,
                        ),
                        decoration: BoxDecoration(
                          color: scheme.primary.withValues(alpha: 0.10),
                          borderRadius: BorderRadius.circular(6),
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
              _summaryRow('Shipping Fee', money(order.shippingAmount)),
              const SizedBox(height: 8),
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

  /// Sticky bottom bar with Logistic Detail + primary store/order action.
  Widget _bottomBar(OrderDetail order) {
    final scheme = Theme.of(context).colorScheme;
    final largeText = MediaQuery.textScalerOf(context).scale(14) > 18;
    if (largeText) {
      return const SizedBox.shrink();
    }

    final shape = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(8),
    );

    return DecoratedBox(
      decoration: BoxDecoration(
        color: scheme.surfaceContainerLowest,
        border: Border(top: BorderSide(color: scheme.outlineVariant)),
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          child: Wrap(
            alignment: WrapAlignment.end,
            spacing: 10,
            runSpacing: 8,
            children: [
              OutlinedButton(
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size(0, 40),
                  padding: const EdgeInsets.symmetric(horizontal: 18),
                  shape: shape,
                  foregroundColor: scheme.onSurface,
                  side: BorderSide(color: scheme.outlineVariant),
                ),
                onPressed: () => _showLogisticSheet(order),
                child: const Text('Logistic Detail'),
              ),
              if (order.sellerOrders.isNotEmpty &&
                  order.sellerOrders.first.store?.slug != null &&
                  order.sellerOrders.first.store!.slug.isNotEmpty)
                OutlinedButton(
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size(0, 40),
                    padding: const EdgeInsets.symmetric(horizontal: 18),
                    shape: shape,
                    foregroundColor: scheme.primary,
                    side: BorderSide(color: scheme.primary),
                  ),
                  onPressed: () => context.push(
                    '/store/${order.sellerOrders.first.store!.slug}',
                  ),
                  child: const Text('Visit Store'),
                ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _showGuaranteeSheet(OrderDetail order) async {
    await showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      useSafeArea: true,
      builder: (context) {
        final theme = Theme.of(context);
        return Padding(
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'JMMart Money Back Guarantee',
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 10),
              Text(
                'Your payment is protected until you confirm that your order has arrived in good condition. Need help with an item? Message the store directly using "Chat with Seller".',
                style: theme.textTheme.bodyMedium,
              ),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: () => Navigator.of(context).pop(),
                child: const Text('Got it'),
              ),
            ],
          ),
        );
      },
    );
  }

  Future<void> _showLogisticSheet(OrderDetail order) async {
    await showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      useSafeArea: true,
      builder: (context) {
        final theme = Theme.of(context);
        return Padding(
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Logistic Detail · ${order.shortNumber}',
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
              Text(
                '${order.delivery.addressLine}, ${order.delivery.city}, ${order.delivery.province} ${order.delivery.postalCode}',
              ),
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

  Future<void> _pay(OrderDetail order) async {
    setState(() => _busy = true);

    try {
      final url = await ref.read(apiClientProvider).payOrder(order.id);

      if (url != null && url.isNotEmpty) {
        final uri = Uri.tryParse(url);

        if (uri != null) {
          await launchUrl(uri, mode: LaunchMode.externalApplication);
        }
      }

      ref.invalidate(orderProvider(widget.orderId));
      ref.invalidate(ordersProvider);
    } on ApiException catch (error) {
      if (mounted) {
        _snack(error.friendly);
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _confirm(SellerOrder sellerOrder) async {
    setState(() => _busy = true);

    try {
      await ref.read(apiClientProvider).confirmReceived(sellerOrder.id);
      ref.invalidate(orderProvider(widget.orderId));
      ref.invalidate(ordersProvider);

      if (mounted) {
        _snack(
          'Thanks for confirming your ${sellerOrder.store?.name ?? 'store'} order.',
        );
      }
    } on ApiException catch (error) {
      if (mounted) {
        _snack(error.friendly);
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _chat(SellerOrder sellerOrder) async {
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
        _snack(error.friendly);
      }
    }
  }

  Future<void> _rateStore(SellerOrder sellerOrder) async {
    final input = await showStoreRatingSheet(
      context,
      sellerOrder.store?.name ?? 'this store',
    );

    if (input == null) {
      return;
    }

    setState(() => _busy = true);

    try {
      await ref
          .read(apiClientProvider)
          .rateStore(
            sellerOrderId: sellerOrder.id,
            rating: input.rating,
            comment: input.body,
          );
      ref.invalidate(orderProvider(widget.orderId));

      if (mounted) {
        _snack('Thanks for rating ${sellerOrder.store?.name ?? 'the store'}.');
      }
    } on ApiException catch (error) {
      if (mounted) {
        _snack(error.friendly);
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _review(OrderLine line) async {
    final input = await showProductReviewSheet(context, line.productName);

    if (input == null) {
      return;
    }

    setState(() => _busy = true);

    try {
      await ref
          .read(apiClientProvider)
          .submitReview(
            orderItemId: line.id,
            rating: input.rating,
            body: input.body,
          );
      ref.invalidate(orderProvider(widget.orderId));
      ref.invalidate(ordersProvider);

      if (mounted) {
        _snack('Your review was published.');
      }
    } on ApiException catch (error) {
      if (mounted) {
        _snack(error.friendly);
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  void _snack(String message) {
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(message)));
  }
}

/// 4-step horizontal order progress tracker: Placed → Preparing → On the way → Delivered.
class _OrderProgressStepper extends StatelessWidget {
  const _OrderProgressStepper({required this.activeStep});

  final int activeStep;

  static const _steps = [
    (label: 'Placed', icon: Icons.receipt_long_rounded),
    (label: 'Preparing', icon: Icons.inventory_2_rounded),
    (label: 'On the way', icon: Icons.local_shipping_rounded),
    (label: 'Delivered', icon: Icons.check_circle_rounded),
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
    final buttonShape = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(6),
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
              radius: 8,
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
                        borderRadius: BorderRadius.circular(4),
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
                  if (!largeText && status != 'cancelled') ...[
                    const SizedBox(height: 6),
                    Wrap(
                      spacing: 6,
                      runSpacing: 4,
                      children: const [
                        _DetailTag(label: 'Free Returns'),
                        _DetailTag(label: '7 Days Local Supplier Warranty'),
                      ],
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
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size(0, 34),
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    shape: buttonShape,
                    foregroundColor: scheme.primary,
                    side: BorderSide(color: scheme.primary),
                  ),
                  onPressed: busy ? null : onReview,
                  child: const Text('Review'),
                ),
              if (!largeText && status == 'delivered')
                OutlinedButton(
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size(0, 34),
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    shape: buttonShape,
                    foregroundColor: scheme.onSurface,
                    side: BorderSide(color: scheme.outlineVariant),
                  ),
                  onPressed: onReturnInfo,
                  child: const Text('Return/Refund'),
                ),
              if (!largeText && status == 'cancelled')
                OutlinedButton(
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size(0, 34),
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    shape: buttonShape,
                    foregroundColor: scheme.onSurfaceVariant,
                    side: BorderSide(color: scheme.outlineVariant),
                  ),
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

class _DetailTag extends StatelessWidget {
  const _DetailTag({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: scheme.primary.withValues(alpha: 0.09),
        borderRadius: BorderRadius.circular(3),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: scheme.primary,
          fontSize: 10.5,
          fontWeight: FontWeight.w600,
          height: 1.2,
        ),
      ),
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

Future<ReviewInput?> showStoreRatingSheet(
  BuildContext context,
  String storeName,
) => showModalBottomSheet<ReviewInput>(
  context: context,
  isScrollControlled: true,
  showDragHandle: true,
  useSafeArea: true,
  builder: (context) => _ReviewSheet(
    title: 'Rate $storeName',
    fieldLabel: 'How was the seller? (optional)',
    submitLabel: 'Submit rating',
    maxLength: 1000,
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
