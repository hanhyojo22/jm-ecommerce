import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/api_exception.dart';
import '../core/formatters.dart';
import '../models/models.dart';
import '../providers/cart_providers.dart';
import '../widgets/common.dart';

class CartScreen extends ConsumerWidget {
  const CartScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cart = ref.watch(cartProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Your cart')),
      body: AsyncView(
        value: cart,
        onRetry: () => ref.invalidate(cartProvider),
        builder: (value) {
          if (value.lines.isEmpty) {
            return EmptyState(
              icon: Icons.shopping_bag_outlined,
              title: 'Your cart is empty',
              message: 'Products you add from the catalog will appear here.',
              action: FilledButton(
                onPressed: () {
                  if (context.canPop()) {
                    context.pop();
                  } else {
                    context.go('/');
                  }
                },
                child: const Text('Browse products'),
              ),
            );
          }

          final theme = Theme.of(context);
          final available = value.lines.where((line) => line.available);
          final allSelected =
              available.isNotEmpty && available.every((line) => line.selected);
          final stores = <String, List<CartLine>>{};
          for (final line in value.lines) {
            stores.putIfAbsent(line.storeSlug, () => []).add(line);
          }

          return Column(
            children: [
              Expanded(
                child: RefreshIndicator(
                  onRefresh: () => ref.read(cartProvider.notifier).refresh(),
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 20),
                    children: [
                      InkWell(
                        borderRadius: BorderRadius.circular(12),
                        onTap: available.isEmpty
                            ? null
                            : () => _selectAll(ref, value, !allSelected),
                        // Matches the line rows' start inset so every checkbox shares one column.
                        child: Padding(
                          padding: const EdgeInsetsDirectional.only(start: 4),
                          child: Row(
                            children: [
                              Checkbox(
                                value: allSelected,
                                onChanged: available.isEmpty
                                    ? null
                                    : (checked) => _selectAll(
                                        ref,
                                        value,
                                        checked ?? false,
                                      ),
                              ),
                              Expanded(
                                child: Text(
                                  'Select all (${available.length})',
                                  style: theme.textTheme.bodyMedium?.copyWith(
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 4),
                      for (final group in stores.values) ...[
                        Card(
                          margin: EdgeInsets.zero,
                          clipBehavior: Clip.antiAlias,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              InkWell(
                                onTap: () => context.push(
                                  '/store/${group.first.storeSlug}',
                                ),
                                child: Padding(
                                  padding: const EdgeInsets.fromLTRB(
                                    16,
                                    12,
                                    12,
                                    12,
                                  ),
                                  child: Row(
                                    children: [
                                      Icon(
                                        Icons.storefront_outlined,
                                        size: 20,
                                        color: theme.colorScheme.primary,
                                      ),
                                      const SizedBox(width: 8),
                                      Expanded(
                                        child: Text(
                                          group.first.storeName,
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis,
                                          style: theme.textTheme.titleSmall
                                              ?.copyWith(
                                                fontWeight: FontWeight.w700,
                                              ),
                                        ),
                                      ),
                                      Icon(
                                        Icons.chevron_right_rounded,
                                        color:
                                            theme.colorScheme.onSurfaceVariant,
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                              for (final line in group) ...[
                                Divider(
                                  height: 1,
                                  color: theme.colorScheme.outlineVariant
                                      .withValues(alpha: 0.6),
                                ),
                                _CartLineRow(
                                  line: line,
                                  onToggle: (selected) =>
                                      _toggle(ref, value, line, selected),
                                  onQuantity: (quantity) =>
                                      _updateQuantity(ref, line, quantity),
                                  onRemove: () => _remove(ref, line),
                                ),
                              ],
                            ],
                          ),
                        ),
                        const SizedBox(height: 12),
                      ],
                    ],
                  ),
                ),
              ),
              SafeArea(
                top: false,
                child: Container(
                  padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
                  decoration: BoxDecoration(
                    color: theme.colorScheme.surfaceContainerLowest,
                    border: Border(
                      top: BorderSide(color: theme.colorScheme.outlineVariant),
                    ),
                  ),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Wrap(
                        alignment: WrapAlignment.spaceBetween,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        spacing: 12,
                        runSpacing: 2,
                        children: [
                          Text(
                            value.selectedCount == 0
                                ? 'Select items to check out'
                                : 'Subtotal · ${value.selectedCount} ${value.selectedCount == 1 ? 'item' : 'items'}',
                            style: theme.textTheme.bodyMedium?.copyWith(
                              color: theme.colorScheme.onSurfaceVariant,
                            ),
                          ),
                          if (value.selectedCount > 0)
                            Text(
                              money(value.subtotal),
                              style: theme.textTheme.titleLarge?.copyWith(
                                fontWeight: FontWeight.w800,
                                color: theme.colorScheme.primary,
                              ),
                            ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      FilledButton(
                        onPressed: value.selectedCount == 0
                            ? null
                            : () => context.push('/checkout'),
                        child: Text(
                          value.selectedCount == 0
                              ? 'Checkout'
                              : 'Checkout (${value.selectedCount})',
                        ),
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

  Future<void> _toggle(
    WidgetRef ref,
    Cart cart,
    CartLine line,
    bool selected,
  ) async {
    final ids = cart.lines
        .where((item) => item.selected)
        .map((item) => item.id)
        .toSet();

    if (selected) {
      ids.add(line.id);
    } else {
      ids.remove(line.id);
    }

    try {
      await ref.read(cartProvider.notifier).selectOnly(ids.toList());
    } on ApiException catch (error) {
      _snack(ref, error.friendly);
    }
  }

  Future<void> _selectAll(WidgetRef ref, Cart cart, bool selected) async {
    try {
      await ref
          .read(cartProvider.notifier)
          .selectOnly(
            selected
                ? cart.lines
                      .where((line) => line.available)
                      .map((line) => line.id)
                      .toList()
                : const [],
          );
    } on ApiException catch (error) {
      _snack(ref, error.friendly);
    }
  }

  Future<void> _updateQuantity(
    WidgetRef ref,
    CartLine line,
    int quantity,
  ) async {
    try {
      await ref.read(cartProvider.notifier).updateQuantity(line.id, quantity);
    } on ApiException catch (error) {
      _snack(ref, error.friendly);
    }
  }

  Future<void> _remove(WidgetRef ref, CartLine line) async {
    try {
      await ref.read(cartProvider.notifier).remove(line.id);
    } on ApiException catch (error) {
      _snack(ref, error.friendly);
    }
  }

  void _snack(WidgetRef ref, String message) {
    final context = ref.context;

    if (context.mounted) {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
    }
  }
}

class _CartLineRow extends StatelessWidget {
  const _CartLineRow({
    required this.line,
    required this.onToggle,
    required this.onQuantity,
    required this.onRemove,
  });

  final CartLine line;
  final ValueChanged<bool> onToggle;
  final ValueChanged<int> onQuantity;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsetsDirectional.fromSTEB(4, 12, 16, 8),
      child: Column(
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(
                height: 72,
                child: Center(
                  child: Checkbox(
                    value: line.selected,
                    onChanged: line.available
                        ? (value) => onToggle(value ?? false)
                        : null,
                  ),
                ),
              ),
              Opacity(
                opacity: line.available ? 1 : 0.45,
                child: NetworkThumb(url: line.imageUrl, width: 72, height: 72),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      line.productName,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        fontWeight: FontWeight.w600,
                        height: 1.25,
                      ),
                    ),
                    if (line.variantLabel != null) ...[
                      const SizedBox(height: 2),
                      Text(
                        line.variantLabel!,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant,
                        ),
                      ),
                    ],
                    if (!line.available) ...[
                      const SizedBox(height: 4),
                      Text(
                        'Unavailable — remove it to continue',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: theme.colorScheme.error,
                        ),
                      ),
                    ],
                    const SizedBox(height: 6),
                    Text(
                      money(line.unitPrice),
                      style: theme.textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                        color: theme.colorScheme.primary,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              IconButton(
                tooltip: 'Remove',
                onPressed: onRemove,
                color: theme.colorScheme.onSurfaceVariant,
                icon: const Icon(Icons.delete_outline_rounded),
              ),
              QuantityStepper(
                quantity: line.quantity,
                max: 100,
                onChanged: onQuantity,
              ),
            ],
          ),
        ],
      ),
    );
  }
}
