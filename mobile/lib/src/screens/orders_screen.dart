import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/formatters.dart';
import '../models/models.dart';
import '../providers/order_providers.dart';
import '../widgets/common.dart';
import '../widgets/shell.dart';

class OrdersScreen extends ConsumerStatefulWidget {
  const OrdersScreen({super.key});

  @override
  ConsumerState<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends ConsumerState<OrdersScreen> {
  static const _tabs = [
    (key: 'all', label: 'All'),
    (key: 'to_pay', label: 'To pay'),
    (key: 'to_receive', label: 'On the way'),
    (key: 'delivered', label: 'Delivered'),
    (key: 'returns', label: 'Returns'),
  ];

  String _tab = 'all';

  @override
  Widget build(BuildContext context) {
    final orders = ref.watch(ordersProvider(_tab));

    return Column(
      children: [
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          child: Row(
            children: _tabs.map((tab) {
              final count = orders.value?.tabCounts[tab.key] ?? 0;

              return Padding(
                padding: const EdgeInsets.only(right: 8),
                child: ChoiceChip(
                  label: Text(count > 0 ? '${tab.label} ($count)' : tab.label),
                  selected: _tab == tab.key,
                  showCheckmark: false,
                  onSelected: (_) => setState(() => _tab = tab.key),
                ),
              );
            }).toList(),
          ),
        ),
        Expanded(
          child: AsyncView(
            value: orders,
            onRetry: () => ref.invalidate(ordersProvider(_tab)),
            builder: (result) {
              if (result.orders.isEmpty) {
                return EmptyState(
                  icon: Icons.receipt_long_outlined,
                  title: 'No orders here',
                  message: 'Orders you place will appear in this tab.',
                  action: FilledButton(
                    onPressed: () =>
                        ref.read(shellTabProvider.notifier).select(0),
                    child: const Text('Start shopping'),
                  ),
                );
              }

              return RefreshIndicator(
                onRefresh: () async {
                  ref.invalidate(ordersProvider(_tab));
                  await ref.read(ordersProvider(_tab).future);
                },
                child: ListView.separated(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(16, 6, 16, 24),
                  itemCount: result.orders.length,
                  separatorBuilder: (context, index) =>
                      const SizedBox(height: 12),
                  itemBuilder: (context, index) =>
                      _OrderCard(order: result.orders[index]),
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}

class _OrderCard extends StatelessWidget {
  const _OrderCard({required this.order});

  final SellerOrder order;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final largeText = MediaQuery.textScalerOf(context).scale(14) > 18;
    final thumbnails = Wrap(
      spacing: 8,
      runSpacing: 8,
      crossAxisAlignment: WrapCrossAlignment.center,
      children: [
        ...order.lines
            .take(3)
            .map(
              (line) => NetworkThumb(
                url: line.imageUrl,
                width: 56,
                height: 56,
                radius: 10,
              ),
            ),
        if (order.lines.length > 3)
          Text('+${order.lines.length - 3}', style: theme.textTheme.bodySmall),
      ],
    );
    final totals = Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        largeText
            ? FittedBox(
                fit: BoxFit.scaleDown,
                alignment: Alignment.centerRight,
                child: Text(
                  money(order.total),
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
              )
            : Text(
                money(order.total),
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
        Text(
          '${order.lines.length} ${order.lines.length == 1 ? 'item' : 'items'}',
          style: theme.textTheme.bodySmall,
        ),
      ],
    );

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: order.orderId == null
            ? null
            : () => context.push('/orders/${order.orderId}'),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  CircleAvatar(
                    radius: 18,
                    backgroundColor: theme.colorScheme.primaryContainer,
                    foregroundColor: theme.colorScheme.onPrimaryContainer,
                    child: Text(initials(order.store?.name ?? 'Store')),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          order.store?.name ?? 'Store',
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                        Text(
                          order.orderShortNumber ?? '',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant,
                          ),
                        ),
                        if (largeText) ...[
                          const SizedBox(height: 6),
                          StatusPill(status: order.status),
                        ],
                      ],
                    ),
                  ),
                  if (!largeText) StatusPill(status: order.status),
                ],
              ),
              const SizedBox(height: 12),
              if (largeText)
                Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    thumbnails,
                    const SizedBox(height: 8),
                    Align(alignment: Alignment.centerRight, child: totals),
                  ],
                )
              else
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(child: thumbnails),
                    const SizedBox(width: 10),
                    totals,
                  ],
                ),
              const SizedBox(height: 10),
              SizedBox(
                width: double.infinity,
                child: Wrap(
                  alignment: WrapAlignment.spaceBetween,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  spacing: 8,
                  runSpacing: 4,
                  children: [
                    Text(
                      order.orderCreatedAt == null
                          ? ''
                          : dateTime(order.orderCreatedAt),
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Flexible(
                          child: Text(
                            order.canConfirmReceipt
                                ? 'Tap to confirm receipt'
                                : 'View order',
                            textAlign: TextAlign.end,
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: theme.colorScheme.primary,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                        const Icon(Icons.chevron_right, size: 18),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
