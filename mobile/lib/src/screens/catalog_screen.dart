import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../providers/catalog_providers.dart';
import '../widgets/common.dart';

class CatalogScreen extends ConsumerStatefulWidget {
  const CatalogScreen({super.key});

  @override
  ConsumerState<CatalogScreen> createState() => _CatalogScreenState();
}

class _CatalogScreenState extends ConsumerState<CatalogScreen> {
  final _searchController = TextEditingController();
  Timer? _debounce;

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  void _onSearchChanged(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () {
      ref.read(catalogFilterProvider.notifier).setQuery(value);
    });
  }

  @override
  Widget build(BuildContext context) {
    final filter = ref.watch(catalogFilterProvider);
    final products = ref.watch(productsProvider);
    final categories = ref.watch(categoriesProvider);
    final theme = Theme.of(context);

    return NotificationListener<ScrollNotification>(
      onNotification: (notification) {
        if (notification.metrics.axis == Axis.vertical &&
            notification.metrics.extentAfter < 500) {
          unawaited(ref.read(productsProvider.notifier).loadMore());
        }

        return false;
      },
      child: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(productsProvider);
          ref.invalidate(categoriesProvider);
          await ref.read(productsProvider.future);
        },
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
              sliver: SliverToBoxAdapter(
                child: TextField(
                  controller: _searchController,
                  onChanged: _onSearchChanged,
                  onSubmitted: (value) =>
                      ref.read(catalogFilterProvider.notifier).setQuery(value),
                  textInputAction: TextInputAction.search,
                  decoration: InputDecoration(
                    hintText: 'Search products, stores, brands',
                    prefixIcon: const Icon(Icons.search_rounded),
                    suffixIcon: filter.query.isEmpty
                        ? null
                        : IconButton(
                            onPressed: () {
                              _searchController.clear();
                              ref
                                  .read(catalogFilterProvider.notifier)
                                  .setQuery('');
                            },
                            icon: const Icon(Icons.close_rounded),
                          ),
                  ),
                ),
              ),
            ),
            SliverToBoxAdapter(
              child: SizedBox(
                height: 56,
                child: categories.value == null
                    ? const SizedBox.shrink()
                    : ListView(
                        scrollDirection: Axis.horizontal,
                        padding: const EdgeInsets.symmetric(
                          horizontal: 16,
                          vertical: 10,
                        ),
                        children: [
                          Padding(
                            padding: const EdgeInsets.only(right: 8),
                            child: ChoiceChip(
                              label: const Text('All'),
                              selected: filter.category == null,
                              onSelected: (_) => ref
                                  .read(catalogFilterProvider.notifier)
                                  .setCategory(null),
                            ),
                          ),
                          ...?categories.value?.map(
                            (category) => Padding(
                              padding: const EdgeInsets.only(right: 8),
                              child: ChoiceChip(
                                label: Text(category.name),
                                selected: filter.category == category.slug,
                                onSelected: (selected) => ref
                                    .read(catalogFilterProvider.notifier)
                                    .setCategory(
                                      selected ? category.slug : null,
                                    ),
                              ),
                            ),
                          ),
                        ],
                      ),
              ),
            ),
            ...switch (products) {
              AsyncData(:final value) => _productSlivers(value, theme),
              AsyncError(:final error) => [
                SliverFillRemaining(
                  hasScrollBody: false,
                  child: ErrorView(
                    error: error,
                    onRetry: () => ref.invalidate(productsProvider),
                  ),
                ),
              ],
              _ => const [
                SliverFillRemaining(
                  hasScrollBody: false,
                  child: Center(child: CircularProgressIndicator()),
                ),
              ],
            },
          ],
        ),
      ),
    );
  }

  List<Widget> _productSlivers(ProductFeed feed, ThemeData theme) {
    final products = feed.products;

    if (products.isEmpty) {
      return [
        SliverFillRemaining(
          hasScrollBody: false,
          child: EmptyState(
            icon: Icons.search_off_rounded,
            title: 'Nothing matches that search',
            message: 'Try another word, or browse a different category.',
            action: OutlinedButton(
              onPressed: () {
                _searchController.clear();
                ref.read(catalogFilterProvider.notifier)
                  ..setQuery('')
                  ..setCategory(null);
              },
              child: const Text('Clear filters'),
            ),
          ),
        ),
      ];
    }

    return [
      SliverPadding(
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
        sliver: SliverToBoxAdapter(
          child: Text(
            '${feed.total > 0 ? feed.total : products.length} products',
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
        ),
      ),
      SliverPadding(
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
        sliver: SliverGrid(
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 2,
            crossAxisSpacing: 12,
            mainAxisSpacing: 12,
            childAspectRatio: 0.66,
          ),
          delegate: SliverChildBuilderDelegate((context, index) {
            final product = products[index];

            return ProductCard(
              product: product,
              onTap: () => context.push(
                '/product/${product.store?.slug ?? ''}/${product.slug}',
              ),
            );
          }, childCount: products.length),
        ),
      ),
      if (feed.loadingMore)
        const SliverToBoxAdapter(
          child: Padding(
            padding: EdgeInsets.only(bottom: 28),
            child: Center(
              child: SizedBox.square(
                dimension: 22,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
            ),
          ),
        ),
    ];
  }
}
