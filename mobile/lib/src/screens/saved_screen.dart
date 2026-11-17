import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../providers/catalog_providers.dart';
import '../widgets/common.dart';
import '../widgets/shell.dart';
import 'product_screen.dart';

/// The buyer's wishlist: products saved to buy later, with today's price and branch stock.
class SavedScreen extends ConsumerWidget {
  const SavedScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final saved = ref.watch(savedProductsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Saved items')),
      body: switch (saved) {
        AsyncData(:final value) when value.isEmpty => EmptyState(
          icon: Icons.favorite_border_rounded,
          title: 'Nothing saved yet',
          message: 'Tap the heart on a product to keep it here for later.',
          action: FilledButton(
            onPressed: () {
              ref.read(shellTabProvider.notifier).select(0);
              context.go('/');
            },
            child: const Text('Browse the shop'),
          ),
        ),
        AsyncData(:final value) => RefreshIndicator(
          onRefresh: () => ref.refresh(savedProductsProvider.future),
          child: LayoutBuilder(
            builder: (context, viewport) {
              final columns = viewport.maxWidth >= 600 ? 3 : 2;
              final rows = (value.length / columns).ceil();

              return ListView.builder(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(12),
                itemCount: rows,
                itemBuilder: (context, row) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      for (var column = 0; column < columns; column++) ...[
                        if (column > 0) const SizedBox(width: 12),
                        Expanded(
                          child: row * columns + column < value.length
                              ? Stack(
                                  children: [
                                    ProductCard(
                                      product: value[row * columns + column],
                                      discovery: true,
                                      showAddToCart: true,
                                      onTap: () {
                                        final product =
                                            value[row * columns + column];
                                        context.push(
                                          '/product/${product.store?.slug ?? ''}/${product.slug}',
                                        );
                                      },
                                    ),
                                    Positioned(
                                      top: 4,
                                      right: 4,
                                      child: Material(
                                        color: Theme.of(context)
                                            .colorScheme
                                            .surface
                                            .withValues(alpha: 0.9),
                                        shape: const CircleBorder(),
                                        child: SaveProductButton(
                                          productId:
                                              value[row * columns + column].id,
                                        ),
                                      ),
                                    ),
                                  ],
                                )
                              : const SizedBox.shrink(),
                        ),
                      ],
                    ],
                  ),
                ),
              );
            },
          ),
        ),
        AsyncError(:final error) => ErrorView(
          error: error,
          onRetry: () => ref.invalidate(savedProductsProvider),
        ),
        _ => const Center(child: CircularProgressIndicator()),
      },
    );
  }
}
