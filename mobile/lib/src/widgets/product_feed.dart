import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/formatters.dart';
import '../models/models.dart';
import '../providers/catalog_providers.dart';
import '../theme.dart';
import 'common.dart';

/// Grid columns and card width for a product list in [constraints]: two to four columns,
/// one at large text on a phone.
({SliverGridDelegateWithFixedCrossAxisCount delegate, double cardWidth})
productGrid(BuildContext context, BoxConstraints constraints) {
  final scaler = MediaQuery.textScalerOf(context);
  final width = constraints.maxWidth - 32;
  final columns = scaler.scale(14) > 21 && width < 600
      ? 1
      : (width / 190).floor().clamp(2, 4);
  final cardWidth = (width - (columns - 1) * 10) / columns;

  return (
    delegate: SliverGridDelegateWithFixedCrossAxisCount(
      crossAxisCount: columns,
      crossAxisSpacing: 10,
      mainAxisSpacing: 10,
      // Loading skeleton only; loaded cards hug their own height.
      mainAxisExtent: cardWidth + scaler.scale(157),
    ),
    cardWidth: cardWidth,
  );
}

/// The product list shared by Shop and the search page: headings, the "Other brands of …"
/// search sections, staggered masonry card columns, paging, and the empty and "Not at your branch" states.
class ProductFeedSlivers {
  const ProductFeedSlivers({
    required this.context,
    required this.ref,
    required this.gridDelegate,
    required this.cardWidth,
    required this.onClearFilters,
    required this.onBrowseCategory,
    required this.onBackToTop,
    this.headingKey,
    this.showHeading = true,
  });

  final BuildContext context;
  final WidgetRef ref;
  final SliverGridDelegateWithFixedCrossAxisCount gridDelegate;
  final double cardWidth;
  final VoidCallback onClearFilters;
  final ValueChanged<String> onBrowseCategory;
  final VoidCallback onBackToTop;
  final Key? headingKey;

  /// The search page draws its own count and sort row instead.
  final bool showHeading;

  /// The loaded feed: its heading, grouped search sections, paging, and the empty states.
  List<Widget> products(ProductFeed feed, List<Category> categories) {
    final theme = Theme.of(context);
    final products = feed.products;

    if (products.isEmpty) {
      final filtered = !feed.filter.isDefault;
      final suggestions = categories
          .where((category) => category.productCount > 0)
          .take(4)
          .toList(growable: false);
      final query = feed.filter.query;
      final elsewhere = query.isEmpty
          ? const <Product>[]
          : ref.watch(elsewhereSearchProvider(query)).value ?? const [];

      if (elsewhere.isNotEmpty) {
        return [
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 28, 16, 4),
            sliver: SliverToBoxAdapter(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Semantics(
                    header: true,
                    child: Text(
                      'Not at your branch',
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontSize: 20,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Other branches have these. Open one to see the nearest branch that has it.',
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                ],
              ),
            ),
          ),
          SliverList.list(
            children: [
              for (final product in elsewhere)
                ListTile(
                  leading: NetworkThumb(
                    url: product.thumbnailUrl,
                    width: 48,
                    height: 48,
                  ),
                  title: Text(product.name),
                  subtitle: Text(money(product.effectivePrice)),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () => context.push(
                    '/product/${product.store?.slug ?? ''}/${product.slug}',
                  ),
                ),
            ],
          ),
        ];
      }

      return [
        SliverFillRemaining(
          hasScrollBody: false,
          child: EmptyState(
            icon: Icons.search_off_rounded,
            title: filtered ? 'Nothing matches that search' : 'No products yet',
            message: filtered
                ? 'Try a different word, or start from a category.'
                : 'Pull down to refresh and see what is new.',
            action: filtered
                ? Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      OutlinedButton(
                        onPressed: onClearFilters,
                        child: const Text('Clear filters'),
                      ),
                      if (suggestions.isNotEmpty) ...[
                        const SizedBox(height: 20),
                        Text(
                          'Browse categories',
                          style: theme.textTheme.labelLarge?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Wrap(
                          alignment: WrapAlignment.center,
                          spacing: 8,
                          runSpacing: 8,
                          children: [
                            for (final category in suggestions)
                              ActionChip(
                                label: Text(category.name),
                                onPressed: () =>
                                    onBrowseCategory(category.slug),
                              ),
                          ],
                        ),
                      ],
                    ],
                  )
                : OutlinedButton(
                    onPressed: () => ref.invalidate(productsProvider),
                    child: const Text('Refresh products'),
                  ),
          ),
        ),
      ];
    }

    return [
      if (showHeading)
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(16, 28, 16, 12),
          sliver: SliverToBoxAdapter(
            // Same row shape as the loading skeleton, so nothing jumps when products arrive.
            child: Row(
              key: headingKey,
              crossAxisAlignment: CrossAxisAlignment.baseline,
              textBaseline: TextBaseline.alphabetic,
              children: [
                Expanded(
                  child: Semantics(
                    header: true,
                    child: Text(
                      feed.filter.query.isNotEmpty
                          ? 'Results for “${feed.filter.query}”'
                          : feed.filter.onSale
                          ? 'Deals'
                          : categories
                                    .where(
                                      (category) =>
                                          category.slug == feed.filter.category,
                                    )
                                    .firstOrNull
                                    ?.name ??
                                'Popular products',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontSize: 19,
                        fontWeight: FontWeight.w800,
                        letterSpacing: -0.4,
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Text(
                  '${feed.total > 0 ? feed.total : products.length} ${(feed.total > 0 ? feed.total : products.length) == 1 ? 'product' : 'products'}',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ),
        ),
      if (feed.searchIngredient != null)
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
          sliver: SliverToBoxAdapter(
            child: Text(
              'Showing results for ${feed.searchIngredient} (${feed.filter.query})',
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ),
        ),
      // An ingredient search comes back grouped ("Other brands of …", "Also contains …");
      // each group gets its heading and its own masonry block.
      for (final (index, section) in _searchSections(products).indexed) ...[
        if (section.first.searchSection != null)
          SliverPadding(
            padding: EdgeInsets.fromLTRB(16, index == 0 ? 0 : 4, 16, 12),
            sliver: SliverToBoxAdapter(
              child: Semantics(
                header: true,
                child: Text(
                  section.first.searchSection!,
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
          ),
        _productMasonry(section),
      ],
      if (feed.loadMoreFailed)
        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
            child: Column(
              children: [
                const Text('Could not load more products.'),
                TextButton(
                  style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
                  onPressed: () =>
                      ref.read(productsProvider.notifier).loadMore(retry: true),
                  child: const Text('Retry loading more'),
                ),
              ],
            ),
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
      if (!feed.hasMore)
        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
            child: Column(
              children: [
                Text(
                  'You have seen all ${products.length} ${products.length == 1 ? 'product' : 'products'}',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
                const SizedBox(height: 8),
                TextButton.icon(
                  style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
                  onPressed: onBackToTop,
                  icon: const Icon(Icons.arrow_upward_rounded, size: 18),
                  label: const Text('Back to top'),
                ),
              ],
            ),
          ),
        ),
    ];
  }

  /// Consecutive products that share a search heading. One run when nothing is grouped.
  List<List<Product>> _searchSections(List<Product> products) {
    final sections = <List<Product>>[];
    for (final product in products) {
      if (sections.isEmpty ||
          sections.last.first.searchSection != product.searchSection) {
        sections.add([product]);
      } else {
        sections.last.add(product);
      }
    }

    return sections;
  }

  Widget _buildFeedCard(Product product) => ProductCard(
    product: product,
    discovery: true,
    showAddToCart: true,
    stackCartControls: ProductCard.stacksCartAt(context, cardWidth),
    onTap: () =>
        context.push('/product/${product.store?.slug ?? ''}/${product.slug}'),
  );

  // Staggered masonry columns: every card hugs its own natural content height
  // with no stretched whitespace inside or between cards.
  Widget _productMasonry(List<Product> products) {
    final columns = gridDelegate.crossAxisCount;

    if (columns <= 1) {
      return SliverPadding(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
        sliver: SliverList.builder(
          itemCount: products.length,
          itemBuilder: (context, index) => Padding(
            key: ValueKey(products[index].id),
            padding: EdgeInsets.only(bottom: gridDelegate.mainAxisSpacing),
            child: _buildFeedCard(products[index]),
          ),
        ),
      );
    }

    final buckets = List.generate(columns, (_) => <Product>[]);
    for (var i = 0; i < products.length; i++) {
      buckets[i % columns].add(products[i]);
    }

    return SliverPadding(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
      sliver: SliverToBoxAdapter(
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            for (var column = 0; column < columns; column++) ...[
              if (column > 0) SizedBox(width: gridDelegate.crossAxisSpacing),
              Expanded(
                child: Column(
                  children: [
                    for (final product in buckets[column])
                      Padding(
                        key: ValueKey(product.id),
                        padding: EdgeInsets.only(
                          bottom: gridDelegate.mainAxisSpacing,
                        ),
                        child: _buildFeedCard(product),
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

  /// Placeholder cards while the first page loads.
  List<Widget> skeleton(double cardWidth) {
    return [
      if (showHeading)
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(16, 28, 16, 12),
          sliver: SliverToBoxAdapter(
            child: Semantics(
              container: true,
              liveRegion: true,
              label: 'Loading products',
              child: ExcludeSemantics(
                child: Row(
                  children: [
                    SkeletonBox(width: 168, height: 20),
                    const Spacer(),
                    SkeletonBox(width: 64, height: 14),
                  ],
                ),
              ),
            ),
          ),
        ),
      SliverPadding(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
        sliver: SliverGrid(
          gridDelegate: gridDelegate,
          delegate: SliverChildBuilderDelegate(
            (context, index) => ExcludeSemantics(
              child: Card(
                child: Padding(
                  padding: const EdgeInsets.all(8),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Expanded(
                        child: SkeletonBox(
                          width: double.infinity,
                          radius: appSurfaceRadius,
                        ),
                      ),
                      const SizedBox(height: 12),
                      SkeletonBox(width: cardWidth - 24, height: 12),
                      const SizedBox(height: 6),
                      SkeletonBox(width: cardWidth * 0.58, height: 12),
                      const SizedBox(height: 12),
                      SkeletonBox(width: cardWidth * 0.48, height: 16),
                      const SizedBox(height: 16),
                      SkeletonBox(width: cardWidth * 0.58, height: 10),
                      const SizedBox(height: 12),
                    ],
                  ),
                ),
              ),
            ),
            childCount: gridDelegate.crossAxisCount * 3,
          ),
        ),
      ),
    ];
  }
}
