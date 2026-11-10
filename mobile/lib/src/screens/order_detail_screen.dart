import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api_exception.dart';
import '../core/formatters.dart';
import '../models/models.dart';
import '../providers/auth_providers.dart';
import '../providers/order_providers.dart';
import '../widgets/common.dart';

class OrderDetailScreen extends ConsumerStatefulWidget {
  const OrderDetailScreen({super.key, required this.orderId});

  final String orderId;

  @override
  ConsumerState<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends ConsumerState<OrderDetailScreen> {
  bool _busy = false;

  @override
  Widget build(BuildContext context) {
    final order = ref.watch(orderProvider(widget.orderId));

    return Scaffold(
      appBar: AppBar(
        title: Text(order.value?.shortNumber ?? 'Order'),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            onPressed: () => ref.invalidate(orderProvider(widget.orderId)),
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: AsyncView(
        value: order,
        onRetry: () => ref.invalidate(orderProvider(widget.orderId)),
        builder: (value) => RefreshIndicator(
          onRefresh: () async {
            ref.invalidate(orderProvider(widget.orderId));
            await ref.read(orderProvider(widget.orderId).future);
          },
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
            children: [
              _header(value),
              const SizedBox(height: 12),
              _delivery(value),
              const SizedBox(height: 12),
              _payment(value),
              const SizedBox(height: 12),
              ...value.sellerOrders.map(
                (sellerOrder) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: _sellerOrder(value, sellerOrder),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _header(OrderDetail order) {
    final theme = Theme.of(context);
    final largeText = MediaQuery.textScalerOf(context).scale(14) > 18;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (largeText)
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Order ${order.shortNumber}',
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 6),
                  StatusPill(status: order.status),
                ],
              )
            else
              Row(
                children: [
                  Expanded(
                    child: Text(
                      'Order ${order.shortNumber}',
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  StatusPill(status: order.status),
                ],
              ),
            const SizedBox(height: 6),
            Text(
              'Placed ${dateTime(order.createdAt)}',
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 14),
            LayoutBuilder(
              builder: (context, constraints) {
                final stack = largeText || constraints.maxWidth < 300;
                final price = Text(
                  money(order.total),
                  style: theme.textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: theme.colorScheme.primary,
                  ),
                );
                final payButton = largeText
                    ? FilledButton(
                        style: FilledButton.styleFrom(
                          minimumSize: const Size(0, 48),
                          padding: const EdgeInsets.symmetric(horizontal: 16),
                        ),
                        onPressed: _busy ? null : () => _pay(order),
                        child: const Text('Pay now'),
                      )
                    : FilledButton.icon(
                        // The shared button style stretches to the available width,
                        // which is infinite inside a Row with an Expanded sibling.
                        style: FilledButton.styleFrom(
                          minimumSize: const Size(0, 48),
                          padding: const EdgeInsets.symmetric(horizontal: 16),
                        ),
                        onPressed: _busy ? null : () => _pay(order),
                        icon: const Icon(Icons.lock_open_rounded),
                        label: const Text('Pay now'),
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
                      if (order.canPay) ...[
                        const SizedBox(height: 10),
                        payButton,
                      ],
                    ],
                  );
                }

                return Row(
                  children: [
                    Expanded(child: price),
                    if (order.canPay) ...[const SizedBox(width: 12), payButton],
                  ],
                );
              },
            ),
            if (order.canConfirmReceipt) ...[
              const SizedBox(height: 10),
              Text(
                'Confirm receipt when the parcel arrives so the store can be paid.',
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

  Widget _delivery(OrderDetail order) {
    final theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Delivery',
              style: theme.textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 10),
            Text(
              order.delivery.recipientName,
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            Text(order.delivery.phone),
            const SizedBox(height: 4),
            Text(
              '${order.delivery.addressLine}, ${order.delivery.city}, ${order.delivery.province} ${order.delivery.postalCode}',
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
    );
  }

  Widget _payment(OrderDetail order) {
    final theme = Theme.of(context);
    final payment = order.payment;
    final largeText = MediaQuery.textScalerOf(context).scale(14) > 18;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(Icons.payments_outlined, color: theme.colorScheme.primary),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    payment?.label ?? 'Cash on delivery',
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  Text(
                    payment == null
                        ? 'Payment details pending'
                        : 'Status: ${payment.status}',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                  if (largeText && payment != null) ...[
                    const SizedBox(height: 4),
                    FittedBox(
                      fit: BoxFit.scaleDown,
                      alignment: Alignment.centerLeft,
                      child: Text(
                        money(payment.amount),
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                    ),
                  ],
                ],
              ),
            ),
            if (!largeText && payment != null)
              Text(
                money(payment.amount),
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
          ],
        ),
      ),
    );
  }

  Widget _sellerOrder(OrderDetail order, SellerOrder sellerOrder) {
    final theme = Theme.of(context);
    final largeText = MediaQuery.textScalerOf(context).scale(14) > 18;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                CircleAvatar(
                  radius: 16,
                  backgroundColor: theme.colorScheme.primaryContainer,
                  foregroundColor: theme.colorScheme.onPrimaryContainer,
                  child: Text(initials(sellerOrder.store?.name ?? 'Store')),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        sellerOrder.store?.name ?? 'Store',
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                      if (largeText) ...[
                        const SizedBox(height: 6),
                        StatusPill(status: sellerOrder.status),
                      ],
                    ],
                  ),
                ),
                if (!largeText) StatusPill(status: sellerOrder.status),
              ],
            ),
            const SizedBox(height: 10),
            ...sellerOrder.lines.map(
              (line) => Padding(
                padding: const EdgeInsets.symmetric(vertical: 6),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    NetworkThumb(
                      url: line.imageUrl,
                      width: 54,
                      height: 54,
                      radius: 10,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            line.productName,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontWeight: FontWeight.w600),
                          ),
                          if (line.variantLabel != null)
                            Text(
                              line.variantLabel!,
                              style: theme.textTheme.bodySmall,
                            ),
                          Text(
                            '${line.quantity} × ${money(line.unitPrice)}',
                            style: theme.textTheme.bodySmall,
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
                            if (sellerOrder.status == 'delivered' &&
                                !line.reviewed)
                              TextButton(
                                onPressed: _busy ? null : () => _review(line),
                                child: const Text('Review'),
                              ),
                          ],
                        ],
                      ),
                    ),
                    if (!largeText)
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Text(
                            money(line.lineTotal),
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                          if (sellerOrder.status == 'delivered' &&
                              !line.reviewed)
                            TextButton(
                              onPressed: _busy ? null : () => _review(line),
                              child: const Text('Review'),
                            ),
                        ],
                      ),
                  ],
                ),
              ),
            ),
            const Divider(height: 22),
            Row(
              children: [
                Expanded(
                  child: Text(
                    '${sellerOrder.lines.length} ${sellerOrder.lines.length == 1 ? 'item' : 'items'} · ${money(sellerOrder.total)}',
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                ),
                TextButton.icon(
                  onPressed: _busy ? null : () => _chat(sellerOrder),
                  icon: const Icon(Icons.chat_bubble_outline_rounded, size: 18),
                  label: const Text('Chat'),
                ),
              ],
            ),
            if (sellerOrder.canConfirmReceipt)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: FilledButton.tonal(
                  onPressed: _busy ? null : () => _confirm(sellerOrder),
                  child: const Text('Confirm received'),
                ),
              ),
            if (sellerOrder.canRateStore)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: OutlinedButton.icon(
                  onPressed: _busy ? null : () => _rateStore(sellerOrder),
                  icon: const Icon(Icons.storefront_outlined, size: 18),
                  label: const Text('Rate store'),
                ),
              )
            else if (sellerOrder.storeRating != null)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Text(
                  'You rated this store ${sellerOrder.storeRating} out of 5',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
                ),
              ),
          ],
        ),
      ),
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
