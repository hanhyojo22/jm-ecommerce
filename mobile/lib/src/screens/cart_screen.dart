import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/api_exception.dart';
import '../core/formatters.dart';
import '../models/models.dart';
import '../providers/cart_providers.dart';
import '../widgets/common.dart';
import '../widgets/shell.dart';

class CartScreen extends ConsumerWidget {
  const CartScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cart = ref.watch(cartProvider);

    return AsyncView(
      value: cart,
      onRetry: () => ref.invalidate(cartProvider),
      builder: (value) {
        if (value.lines.isEmpty) {
          return EmptyState(
            icon: Icons.shopping_bag_outlined,
            title: 'Your cart is empty',
            message: 'Products you add from the catalog will appear here.',
            action: FilledButton(
              onPressed: () => ref.read(shellTabProvider.notifier).select(0),
              child: const Text('Browse products'),
            ),
          );
        }

        return Column(
          children: [
            Expanded(
              child: RefreshIndicator(
                onRefresh: () => ref.read(cartProvider.notifier).refresh(),
                child: ListView.separated(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 20),
                  itemCount: value.lines.length,
                  separatorBuilder: (context, index) =>
                      const SizedBox(height: 10),
                  itemBuilder: (context, index) {
                    final line = value.lines[index];

                    return _CartLineCard(
                      line: line,
                      onToggle: (selected) =>
                          _toggle(ref, value, line, selected),
                      onQuantity: (quantity) =>
                          _updateQuantity(ref, line, quantity),
                      onRemove: () => _remove(ref, line),
                    );
                  },
                ),
              ),
            ),
            SafeArea(
              top: false,
              child: Container(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
                decoration: BoxDecoration(
                  color: Colors.white,
                  border: Border(
                    top: BorderSide(
                      color: Theme.of(context).colorScheme.outlineVariant,
                    ),
                  ),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            '${value.selectedCount} selected',
                            style: Theme.of(context).textTheme.bodySmall
                                ?.copyWith(
                                  color: Theme.of(context)
                                      .colorScheme
                                      .onSurfaceVariant,
                                ),
                          ),
                          Text(
                            money(value.subtotal),
                            style: Theme.of(context).textTheme.titleLarge
                                ?.copyWith(fontWeight: FontWeight.w800),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 16),
                    SizedBox(
                      width: 190,
                      child: FilledButton(
                        onPressed: value.selectedCount == 0
                            ? null
                            : () => context.push('/checkout'),
                        child: const Text('Checkout'),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        );
      },
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

class _CartLineCard extends StatelessWidget {
  const _CartLineCard({
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

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Checkbox(
              value: line.selected,
              onChanged: line.available
                  ? (value) => onToggle(value ?? false)
                  : null,
            ),
            NetworkThumb(url: line.imageUrl, width: 72, height: 72),
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
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  if (line.variantLabel != null)
                    Text(
                      line.variantLabel!,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                  Text(
                    line.storeName,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      Text(
                        money(line.unitPrice),
                        style: theme.textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w800,
                          color: theme.colorScheme.primary,
                        ),
                      ),
                      const Spacer(),
                      QuantityStepper(
                        quantity: line.quantity,
                        max: 100,
                        onChanged: onQuantity,
                      ),
                    ],
                  ),
                  if (!line.available)
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Text(
                        'Unavailable — remove it to continue',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: theme.colorScheme.error,
                        ),
                      ),
                    ),
                ],
              ),
            ),
            IconButton(
              tooltip: 'Remove',
              onPressed: onRemove,
              icon: const Icon(Icons.delete_outline_rounded),
            ),
          ],
        ),
      ),
    );
  }
}
