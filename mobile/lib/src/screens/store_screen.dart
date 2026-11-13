import 'package:flutter/foundation.dart' show listEquals;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/formatters.dart';
import '../models/models.dart';
import '../providers/catalog_providers.dart';
import '../theme.dart';
import '../widgets/common.dart';
import '../widgets/store_ratings_sheet.dart';

const _shopGreenDeep = Color(0xFF071F1B);
const _shopGreen = Color(0xFF0B3B34);
const _shopGreenBright = Color(0xFF177565);
const _shopGold = Color(0xFFF0C36A);
const _gridGap = 12.0;
const _gridInset = 12.0;

class StoreScreen extends ConsumerStatefulWidget {
  const StoreScreen({super.key, required this.slug});

  final String slug;

  @override
  ConsumerState<StoreScreen> createState() => _StoreScreenState();
}

class _StoreScreenState extends ConsumerState<StoreScreen> {
  final _searchController = TextEditingController();
  final _searchFocus = FocusNode();
  String _query = '';
  String? _categorySlug;

  @override
  void dispose() {
    _searchFocus.dispose();
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _refresh() async {
    ref.invalidate(storeProvider(widget.slug));
    ref.invalidate(storeProductsProvider(widget.slug));
    try {
      await Future.wait([
        ref.read(storeProvider(widget.slug).future),
        ref.read(storeProductsProvider(widget.slug).future),
      ]);
    } catch (_) {
      // The providers keep the failure, so the screen can show its retry state.
    }
  }

  void _clearSearch() {
    _searchController.clear();
    setState(() => _query = '');
  }

  void _clearFilters() {
    FocusScope.of(context).unfocus();
    _searchController.clear();
    setState(() {
      _query = '';
      _categorySlug = null;
    });
  }

  void _retry() {
    ref.invalidate(storeProvider(widget.slug));
    ref.invalidate(storeProductsProvider(widget.slug));
  }

  @override
  Widget build(BuildContext context) {
    final store = ref.watch(storeProvider(widget.slug));
    final products = ref.watch(storeProductsProvider(widget.slug));

    return Scaffold(
      appBar: _storeAppBar(context, store.value?.name),
      body: AsyncView(
        value: store,
        onRetry: _retry,
        builder: (value) => _storeBody(context, value, products),
      ),
    );
  }

  PreferredSizeWidget _storeAppBar(BuildContext context, String? storeName) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final header = theme.brightness == Brightness.dark
        ? _shopGreenDeep
        : _shopGreen;
    final scaler = MediaQuery.textScalerOf(context).clamp(maxScaleFactor: 1.6);
    final probe = TextPainter(
      text: TextSpan(text: 'Ag', style: theme.textTheme.bodyLarge),
      textDirection: TextDirection.ltr,
      textScaler: scaler,
    )..layout();
    final lineHeight = probe.height;
    probe.dispose();
    final searchHeight = (lineHeight + 24).clamp(44.0, 56.0);
    final inputPadding = ((searchHeight - lineHeight) / 2).clamp(0.0, 20.0);
    final trailingSize = searchHeight - 8;
    final hint = storeName == null || storeName.trim().isEmpty
        ? 'Search this store'
        : 'Search in $storeName';

    final reduceMotion = MediaQuery.disableAnimationsOf(context);

    return AppBar(
      backgroundColor: header,
      foregroundColor: Colors.white,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      scrolledUnderElevation: 0,
      systemOverlayStyle: SystemUiOverlayStyle.light,
      toolbarHeight: searchHeight + 16,
      titleSpacing: 2,
      title: ListenableBuilder(
        listenable: _searchFocus,
        builder: (context, child) {
          final focused = _searchFocus.hasFocus;
          final active = focused || _query.isNotEmpty;

          return AnimatedContainer(
            duration: reduceMotion
                ? Duration.zero
                : const Duration(milliseconds: 180),
            curve: Curves.easeOutCubic,
            height: searchHeight,
            padding: const EdgeInsetsDirectional.only(start: 12, end: 4),
            decoration: BoxDecoration(
              color: scheme.surfaceContainerLowest,
              borderRadius: BorderRadius.circular(8),
              border: Border.all(
                color: focused
                    ? scheme.secondary
                    : scheme.secondary.withValues(alpha: 0.75),
                width: focused ? 2 : 1.5,
              ),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: focused ? 0.16 : 0.08),
                  blurRadius: focused ? 10 : 4,
                  offset: const Offset(0, 2),
                ),
              ],
            ),
            child: Row(
              children: [
                Icon(
                  Icons.search_rounded,
                  size: 20,
                  color: active ? scheme.primary : scheme.onSurfaceVariant,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: MediaQuery(
                    data: MediaQuery.of(context).copyWith(textScaler: scaler),
                    child: Semantics(
                      label: hint,
                      child: TextField(
                        controller: _searchController,
                        focusNode: _searchFocus,
                        onChanged: (value) => setState(() => _query = value),
                        onSubmitted: (_) => _searchFocus.unfocus(),
                        onTapOutside: (_) => _searchFocus.unfocus(),
                        autocorrect: false,
                        enableSuggestions: false,
                        maxLines: 1,
                        textInputAction: TextInputAction.search,
                        style: theme.textTheme.bodyLarge?.copyWith(
                          color: scheme.onSurface,
                          fontWeight: FontWeight.w600,
                        ),
                        decoration: InputDecoration(
                          hintText: hint,
                          hintMaxLines: 1,
                          filled: false,
                          isDense: true,
                          contentPadding: EdgeInsets.symmetric(
                            vertical: inputPadding,
                          ),
                          border: InputBorder.none,
                          enabledBorder: InputBorder.none,
                          focusedBorder: InputBorder.none,
                        ),
                      ),
                    ),
                  ),
                ),
                if (_query.isEmpty)
                  const SizedBox(width: 10)
                else
                  IconButton(
                    tooltip: 'Clear search',
                    onPressed: _clearSearch,
                    color: scheme.onSurfaceVariant,
                    style: IconButton.styleFrom(
                      fixedSize: Size.square(trailingSize),
                      minimumSize: Size.square(trailingSize),
                      padding: EdgeInsets.zero,
                    ),
                    icon: const Icon(Icons.close_rounded, size: 20),
                  ),
              ],
            ),
          );
        },
      ),
      actions: [
        IconButton(
          tooltip: 'Open cart',
          onPressed: () => context.push('/cart'),
          icon: const Icon(Icons.shopping_cart_outlined),
        ),
        const SizedBox(width: 4),
      ],
    );
  }

  Widget _storeBody(
    BuildContext context,
    StoreInfo store,
    AsyncValue<ProductPage> products,
  ) {
    final theme = Theme.of(context);
    final dark = theme.brightness == Brightness.dark;
    final headerStart = dark ? _shopGreenDeep : _shopGreen;
    final headerEnd = dark ? _shopGreen : _shopGreenBright;
    final productsLoaded = products.hasValue;
    final all = products.value?.products ?? const <Product>[];
    final categories = _categoriesOf(all);
    final selectedSlug = _selectedCategory(categories);
    final visible = _visibleProducts(all, selectedSlug);
    final stats = productsLoaded ? _StoreStats.fromProducts(all) : null;
    final itemCount =
        products.value?.total ?? store.productsCount ?? all.length;
    final filtering = _query.trim().isNotEmpty || selectedSlug != null;
    final showFilterSummary = filtering && productsLoaded;
    final scaler = MediaQuery.textScalerOf(context);
    final tabsHeight = scaler.scale(14) * 1.25 + 26;

    return RefreshIndicator(
      onRefresh: _refresh,
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverToBoxAdapter(
            child: _StoreHero(
              store: store,
              stats: stats,
              itemCount: itemCount,
              headerStart: headerStart,
              headerEnd: headerEnd,
            ),
          ),
          SliverPersistentHeader(
            pinned: true,
            delegate: _CategoryTabsDelegate(
              categories: categories,
              selectedSlug: selectedSlug,
              height: tabsHeight,
              onSelected: (slug) {
                FocusScope.of(context).unfocus();
                setState(() => _categorySlug = slug);
              },
            ),
          ),
          if (showFilterSummary)
            SliverToBoxAdapter(
              child: _FilterSummary(
                count: visible.length,
                onClear: _clearFilters,
              ),
            ),
          ...products.when(
            data: (_) => _gridSlivers(context, store, all, visible, filtering),
            loading: () => [_skeletonSliver(context)],
            error: (error, stack) => [
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.symmetric(vertical: 28),
                  child: ErrorView(
                    error: error,
                    onRetry: () =>
                        ref.invalidate(storeProductsProvider(widget.slug)),
                  ),
                ),
              ),
            ],
          ),
          const SliverToBoxAdapter(child: SizedBox(height: 12)),
        ],
      ),
    );
  }

  List<Widget> _gridSlivers(
    BuildContext context,
    StoreInfo store,
    List<Product> all,
    List<Product> visible,
    bool filtering,
  ) {
    if (visible.isEmpty) {
      return [
        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.only(top: 8, bottom: 32),
            child: EmptyState(
              icon: filtering
                  ? Icons.search_off_rounded
                  : Icons.storefront_outlined,
              title: filtering ? 'No products found' : 'No products yet',
              message: filtering
                  ? 'Try a different search or category.'
                  : all.isEmpty
                  ? 'This store has not published any products.'
                  : 'This category has no products right now.',
            ),
          ),
        ),
      ];
    }

    return [
      SliverLayoutBuilder(
        builder: (context, constraints) {
          final metrics = _gridMetrics(context, constraints.crossAxisExtent);

          return SliverPadding(
            padding: const EdgeInsets.fromLTRB(
              _gridInset,
              _gridInset,
              _gridInset,
              28,
            ),
            sliver: SliverGrid(
              gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: metrics.columns,
                crossAxisSpacing: _gridGap,
                mainAxisSpacing: _gridGap,
                mainAxisExtent: metrics.mainAxisExtent,
              ),
              delegate: SliverChildBuilderDelegate((context, index) {
                final product = visible[index];

                return StoreProductCard(
                  product: product,
                  onTap: () => context.push(
                    '/product/${product.store?.slug ?? store.slug}/${product.slug}',
                  ),
                );
              }, childCount: visible.length),
            ),
          );
        },
      ),
    ];
  }

  Widget _skeletonSliver(BuildContext context) {
    return SliverLayoutBuilder(
      builder: (context, constraints) {
        final metrics = _gridMetrics(context, constraints.crossAxisExtent);

        return SliverPadding(
          padding: const EdgeInsets.fromLTRB(
            _gridInset,
            _gridInset,
            _gridInset,
            28,
          ),
          sliver: SliverGrid(
            gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: metrics.columns,
              crossAxisSpacing: _gridGap,
              mainAxisSpacing: _gridGap,
              mainAxisExtent: metrics.mainAxisExtent,
            ),
            delegate: SliverChildBuilderDelegate(
              (context, index) => Semantics(
                container: index == 0,
                liveRegion: index == 0,
                label: index == 0 ? 'Loading products' : null,
                child: ExcludeSemantics(
                  child: Card(
                    clipBehavior: Clip.antiAlias,
                    child: SizedBox.expand(child: SkeletonBox(radius: 18)),
                  ),
                ),
              ),
              childCount: metrics.columns * 2,
            ),
          ),
        );
      },
    );
  }

  ({int columns, double mainAxisExtent}) _gridMetrics(
    BuildContext context,
    double crossAxisExtent,
  ) {
    final scaler = MediaQuery.textScalerOf(context);
    final width = crossAxisExtent - _gridInset * 2;
    final largeText = scaler.scale(14) > 20 && width < 600;
    final columns = largeText ? 1 : (width / 150).floor().clamp(2, 3);
    final cardWidth = (width - (columns - 1) * _gridGap) / columns;
    // Match StoreProductCard's text block, plus a pixel for rounding slack.
    final contentHeight =
        8 +
        scaler.scale(35) +
        6 +
        scaler.scale(38) +
        4 +
        scaler.scale(15.6) +
        11;

    return (columns: columns, mainAxisExtent: cardWidth + contentHeight);
  }

  String? _selectedCategory(List<Category> categories) {
    final slug = _categorySlug;
    if (slug == null) {
      return null;
    }

    return categories.any((category) => category.slug == slug) ? slug : null;
  }

  List<Product> _visibleProducts(List<Product> all, String? categorySlug) {
    final query = _query.trim().toLowerCase();

    return all
        .where((product) {
          if (categorySlug != null && product.category?.slug != categorySlug) {
            return false;
          }
          if (query.isEmpty) {
            return true;
          }

          return product.name.toLowerCase().contains(query) ||
              (product.brand?.toLowerCase().contains(query) ?? false) ||
              (product.category?.name.toLowerCase().contains(query) ?? false);
        })
        .toList(growable: false);
  }

  List<Category> _categoriesOf(List<Product> products) {
    final bySlug = <String, Category>{};
    for (final product in products) {
      final category = product.category;
      if (category != null && category.slug.isNotEmpty) {
        bySlug.putIfAbsent(category.slug, () => category);
      }
    }

    return bySlug.values.toList()
      ..sort((a, b) => a.name.toLowerCase().compareTo(b.name.toLowerCase()));
  }
}

class _StoreHero extends StatelessWidget {
  const _StoreHero({
    required this.store,
    required this.stats,
    required this.itemCount,
    required this.headerStart,
    required this.headerEnd,
  });

  final StoreInfo store;
  final _StoreStats? stats;
  final int itemCount;
  final Color headerStart;
  final Color headerEnd;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final hasBanner = (store.bannerUrl ?? '').isNotEmpty;
    final secondary = Colors.white.withValues(alpha: 0.9);

    return ClipRRect(
      borderRadius: const BorderRadius.vertical(
        bottom: Radius.circular(appSurfaceRadius),
      ),
      child: Stack(
        children: [
          Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [headerStart, headerEnd],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
              ),
            ),
          ),
          if (hasBanner)
            Positioned.fill(
              child: NetworkThumb(
                url: store.bannerUrl,
                fit: BoxFit.cover,
                cacheWidth: 1200,
              ),
            ),
          if (hasBanner)
            Positioned.fill(
              child: ColoredBox(color: headerStart.withValues(alpha: 0.34)),
            ),
          if (hasBanner)
            Positioned.fill(
              child: DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [
                      Colors.black.withValues(alpha: 0.16),
                      Colors.black.withValues(alpha: 0.64),
                    ],
                  ),
                ),
              ),
            ),
          Positioned(
            right: -52,
            top: -64,
            child: _Glow(color: _shopGold, size: 200, opacity: 0.1),
          ),
          Positioned(
            left: -70,
            bottom: 20,
            child: _Glow(color: Colors.white, size: 220, opacity: 0.05),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 18, 16, 20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      padding: const EdgeInsets.all(3),
                      decoration: const BoxDecoration(
                        color: Colors.white,
                        shape: BoxShape.circle,
                      ),
                      child: StoreAvatar(
                        name: store.name,
                        logoUrl: store.logoUrl,
                        radius: 31,
                        backgroundColor: headerEnd,
                        foregroundColor: Colors.white,
                      ),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            store.name,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.headlineSmall?.copyWith(
                              color: Colors.white,
                              fontWeight: FontWeight.w800,
                              height: 1.12,
                              letterSpacing: -0.4,
                            ),
                          ),
                          if (store.city != null &&
                              store.city!.trim().isNotEmpty) ...[
                            const SizedBox(height: 5),
                            Row(
                              children: [
                                Icon(
                                  Icons.location_on_outlined,
                                  size: 15,
                                  color: secondary,
                                ),
                                const SizedBox(width: 4),
                                Flexible(
                                  child: Text(
                                    store.city!,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: theme.textTheme.bodySmall?.copyWith(
                                      color: secondary,
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ],
                          if (store.description != null &&
                              store.description!.trim().isNotEmpty) ...[
                            const SizedBox(height: 7),
                            Text(
                              store.description!,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: theme.textTheme.bodyMedium?.copyWith(
                                color: secondary,
                                height: 1.4,
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                _StoreStatsPanel(
                  store: store,
                  stats: stats,
                  itemCount: itemCount,
                ),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    const _TrustPill(
                      icon: Icons.payments_outlined,
                      label: 'Cash on delivery',
                    ),
                    if (stats?.hasFreeShipping == true)
                      const _TrustPill(
                        icon: Icons.local_shipping_outlined,
                        label: 'Free shipping',
                      ),
                    if (store.acceptsOnlinePayments)
                      const _TrustPill(
                        icon: Icons.credit_card_rounded,
                        label: 'Online payment',
                      ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _StoreStatsPanel extends StatelessWidget {
  const _StoreStatsPanel({
    required this.store,
    required this.stats,
    required this.itemCount,
  });

  final StoreInfo store;
  final _StoreStats? stats;
  final int itemCount;

  @override
  Widget build(BuildContext context) {
    final ratingCount = store.ratingCount ?? 0;
    final rated = ratingCount > 0;
    final ratingValue = rated ? store.ratingAverage.toStringAsFixed(1) : '—';
    final soldValue = stats == null ? '—' : _compactCount(stats!.sold);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(appSurfaceRadius),
        border: Border.all(color: Colors.white.withValues(alpha: 0.18)),
      ),
      child: Row(
        children: [
          Expanded(
            child: InkWell(
              borderRadius: BorderRadius.circular(appSurfaceRadius),
              onTap: () => showStoreRatings(context, store),
              child: _StatCell(
                icon: rated ? Icons.star_rounded : Icons.star_outline_rounded,
                value: ratingValue,
                label: rated ? '$ratingCount ratings' : 'ratings',
                semanticsLabel: rated
                    ? 'Store rated $ratingValue out of 5 from $ratingCount ratings. Show ratings'
                    : 'No store ratings yet. Show ratings',
              ),
            ),
          ),
          const _StatDivider(),
          Expanded(
            child: _StatCell(
              value: soldValue,
              label: 'sold',
              semanticsLabel: stats == null
                  ? 'Sales are loading'
                  : '${stats!.sold} items sold',
            ),
          ),
          const _StatDivider(),
          Expanded(
            child: _StatCell(
              value: _compactCount(itemCount),
              label: 'items',
              semanticsLabel: '$itemCount products',
            ),
          ),
        ],
      ),
    );
  }
}

class _StatCell extends StatelessWidget {
  const _StatCell({
    required this.value,
    required this.label,
    required this.semanticsLabel,
    this.icon,
  });

  final String value;
  final String label;
  final String semanticsLabel;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Semantics(
      label: semanticsLabel,
      excludeSemantics: true,
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            mainAxisSize: MainAxisSize.min,
            children: [
              if (icon != null) ...[
                Icon(icon, size: 16, color: _shopGold),
                const SizedBox(width: 4),
              ],
              Flexible(
                child: Text(
                  value,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.titleMedium?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                    height: 1.2,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 2),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
            style: theme.textTheme.labelSmall?.copyWith(
              color: Colors.white.withValues(alpha: 0.9),
            ),
          ),
        ],
      ),
    );
  }
}

class _StatDivider extends StatelessWidget {
  const _StatDivider();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 1,
      height: 34,
      color: Colors.white.withValues(alpha: 0.18),
    );
  }
}

class _TrustPill extends StatelessWidget {
  const _TrustPill({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white.withValues(alpha: 0.16)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 15, color: Colors.white.withValues(alpha: 0.92)),
          const SizedBox(width: 5),
          Flexible(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.labelSmall?.copyWith(
                color: Colors.white,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Glow extends StatelessWidget {
  const _Glow({required this.color, required this.size, required this.opacity});

  final Color color;
  final double size;
  final double opacity;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: color.withValues(alpha: opacity),
      ),
    );
  }
}

class _FilterSummary extends StatelessWidget {
  const _FilterSummary({required this.count, required this.onClear});

  final int count;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 8, 0),
      child: Wrap(
        alignment: WrapAlignment.spaceBetween,
        crossAxisAlignment: WrapCrossAlignment.center,
        spacing: 8,
        runSpacing: 4,
        children: [
          Text(
            '$count ${count == 1 ? 'result' : 'results'}',
            style: theme.textTheme.bodyMedium?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
          TextButton(
            onPressed: onClear,
            style: TextButton.styleFrom(
              padding: const EdgeInsets.symmetric(horizontal: 10),
              minimumSize: const Size(0, 40),
              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
            ),
            child: const Text('Clear filters'),
          ),
        ],
      ),
    );
  }
}

class _CategoryTabsDelegate extends SliverPersistentHeaderDelegate {
  const _CategoryTabsDelegate({
    required this.categories,
    required this.selectedSlug,
    required this.onSelected,
    required this.height,
  });

  final List<Category> categories;
  final String? selectedSlug;
  final ValueChanged<String?> onSelected;
  final double height;

  @override
  double get minExtent => height;

  @override
  double get maxExtent => height;

  @override
  Widget build(
    BuildContext context,
    double shrinkOffset,
    bool overlapsContent,
  ) {
    final theme = Theme.of(context);

    return Material(
      color: theme.colorScheme.surface,
      child: Container(
        decoration: BoxDecoration(
          border: Border(
            bottom: BorderSide(
              color: theme.colorScheme.outlineVariant.withValues(alpha: 0.55),
            ),
          ),
        ),
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 8),
          itemCount: categories.length + 1,
          separatorBuilder: (context, index) => const SizedBox(width: 2),
          itemBuilder: (context, index) {
            final category = index == 0 ? null : categories[index - 1];
            final selected = category?.slug == selectedSlug;

            return _CategoryTab(
              label: category?.name ?? 'All',
              selected: selected,
              onTap: () => onSelected(category?.slug),
            );
          },
        ),
      ),
    );
  }

  @override
  bool shouldRebuild(covariant _CategoryTabsDelegate oldDelegate) {
    return oldDelegate.height != height ||
        oldDelegate.selectedSlug != selectedSlug ||
        !listEquals(oldDelegate.categories, categories);
  }
}

class _CategoryTab extends StatelessWidget {
  const _CategoryTab({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Semantics(
      button: true,
      selected: selected,
      label: label,
      child: ExcludeSemantics(
        child: InkWell(
          onTap: onTap,
          child: Container(
            alignment: Alignment.center,
            padding: const EdgeInsets.symmetric(horizontal: 13),
            decoration: BoxDecoration(
              border: Border(
                bottom: BorderSide(
                  color: selected ? scheme.primary : Colors.transparent,
                  width: 3,
                ),
              ),
            ),
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.labelLarge?.copyWith(
                color: selected ? scheme.primary : scheme.onSurfaceVariant,
                fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// A dense storefront card with the sale and delivery facts buyers scan first.
class StoreProductCard extends StatelessWidget {
  const StoreProductCard({
    super.key,
    required this.product,
    required this.onTap,
  });

  final Product product;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final discount = _discountPercent(product);
    final meta = _meta(context);
    final labels = <String>[
      product.name,
      money(product.effectivePrice),
      if (discount != null) 'was ${money(product.compareAtPrice)}',
      if (discount != null) '$discount percent off',
      if (product.freeShipping) 'Free shipping',
      if (!product.inStock) 'Sold out',
      if (product.ratingCount > 0)
        'Rated ${product.ratingAverage.toStringAsFixed(1)} out of 5 from ${product.ratingCount} reviews',
      if (product.soldQuantity > 0) '${product.soldQuantity} sold',
    ];

    return Semantics(
      button: true,
      label: labels.join(', '),
      onTap: onTap,
      child: ExcludeSemantics(
        child: Card(
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: onTap,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Expanded(
                  child: Stack(
                    fit: StackFit.expand,
                    children: [
                      NetworkThumb(
                        url: product.thumbnailUrl,
                        width: double.infinity,
                        cacheWidth: 600,
                      ),
                      if (!product.inStock)
                        ColoredBox(
                          color: scheme.surface.withValues(alpha: 0.35),
                        ),
                      if (discount != null)
                        Positioned(
                          left: 8,
                          top: 8,
                          child: _DiscountBadge(percent: discount),
                        ),
                      if (!product.inStock)
                        const Positioned.fill(
                          child: Center(child: _SoldOutBadge()),
                        ),
                      if (product.freeShipping && product.inStock)
                        const Positioned(
                          left: 8,
                          right: 8,
                          bottom: 8,
                          child: Align(
                            alignment: Alignment.bottomLeft,
                            child: _FreeShippingBadge(),
                          ),
                        ),
                    ],
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(10, 8, 10, 10),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        product.name,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          fontWeight: FontWeight.w600,
                          height: 1.25,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Align(
                        alignment: Alignment.topLeft,
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            FittedBox(
                              fit: BoxFit.scaleDown,
                              alignment: Alignment.centerLeft,
                              child: Text(
                                money(product.effectivePrice),
                                maxLines: 1,
                                style: theme.textTheme.titleMedium?.copyWith(
                                  color: discount != null
                                      ? scheme.error
                                      : scheme.primary,
                                  fontWeight: FontWeight.w800,
                                  height: 1.25,
                                  letterSpacing: -0.4,
                                ),
                              ),
                            ),
                            if (discount != null) ...[
                              const SizedBox(height: 1),
                              FittedBox(
                                fit: BoxFit.scaleDown,
                                alignment: Alignment.centerLeft,
                                child: Text(
                                  money(product.compareAtPrice),
                                  maxLines: 1,
                                  style: theme.textTheme.bodySmall?.copyWith(
                                    color: scheme.onSurfaceVariant,
                                    decoration: TextDecoration.lineThrough,
                                    decorationColor: scheme.onSurfaceVariant,
                                    decorationThickness: 1.5,
                                    fontFeatures: const [
                                      FontFeature.tabularFigures(),
                                    ],
                                  ),
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                      const SizedBox(height: 4),
                      meta,
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _meta(BuildContext context) {
    final theme = Theme.of(context);
    final style = theme.textTheme.bodySmall?.copyWith(
      color: theme.colorScheme.onSurfaceVariant,
      height: 1.3,
    );
    final hasRating = product.ratingCount > 0;
    final hasSold = product.soldQuantity > 0;

    if (!hasRating && !hasSold) {
      final fallback =
          product.category?.name ?? (product.inStock ? 'In stock' : 'Sold out');

      return Text(
        fallback,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: style,
      );
    }

    return Row(
      children: [
        if (hasRating) ...[
          Icon(
            Icons.star_rounded,
            size: 13,
            color: theme.colorScheme.secondary,
          ),
          const SizedBox(width: 2),
          Text(product.ratingAverage.toStringAsFixed(1), style: style),
        ],
        if (hasRating && hasSold) ...[
          const SizedBox(width: 4),
          Text('·', style: style),
          const SizedBox(width: 4),
        ],
        if (hasSold)
          Flexible(
            child: Text(
              '${_compactCount(product.soldQuantity)} sold',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: style,
            ),
          ),
      ],
    );
  }
}

class _DiscountBadge extends StatelessWidget {
  const _DiscountBadge({required this.percent});

  final int percent;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
      decoration: BoxDecoration(
        color: scheme.primary,
        borderRadius: BorderRadius.circular(appSurfaceRadius),
      ),
      child: Text(
        '-$percent%',
        style: theme.textTheme.labelSmall?.copyWith(
          color: scheme.onPrimary,
          fontWeight: FontWeight.w800,
          height: 1.1,
        ),
      ),
    );
  }
}

class _SoldOutBadge extends StatelessWidget {
  const _SoldOutBadge();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: theme.colorScheme.surfaceContainerLowest.withValues(alpha: 0.94),
        borderRadius: BorderRadius.circular(appSurfaceRadius),
        border: Border.all(color: theme.colorScheme.outlineVariant),
      ),
      child: Text(
        'Sold out',
        style: theme.textTheme.labelSmall?.copyWith(
          fontWeight: FontWeight.w700,
          height: 1.1,
        ),
      ),
    );
  }
}

class _FreeShippingBadge extends StatelessWidget {
  const _FreeShippingBadge();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
      decoration: BoxDecoration(
        color: scheme.surfaceContainerLowest.withValues(alpha: 0.94),
        borderRadius: BorderRadius.circular(appSurfaceRadius),
        border: Border.all(color: scheme.outlineVariant.withValues(alpha: 0.5)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.local_shipping_outlined, size: 13, color: scheme.primary),
          const SizedBox(width: 4),
          Flexible(
            child: Text(
              'Free shipping',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.labelSmall?.copyWith(
                color: scheme.onSurface,
                fontWeight: FontWeight.w700,
                height: 1.1,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _StoreStats {
  const _StoreStats({required this.sold, required this.hasFreeShipping});

  final int sold;
  final bool hasFreeShipping;

  factory _StoreStats.fromProducts(List<Product> products) {
    var sold = 0;
    var hasFreeShipping = false;

    for (final product in products) {
      sold += product.soldQuantity;
      hasFreeShipping = hasFreeShipping || product.freeShipping;
    }

    return _StoreStats(sold: sold, hasFreeShipping: hasFreeShipping);
  }
}

int? _discountPercent(Product product) {
  final compare = double.tryParse(product.compareAtPrice ?? '') ?? 0;
  final current = double.tryParse(product.effectivePrice) ?? 0;
  if (current <= 0 || compare <= current) {
    return null;
  }

  final percent = (((compare - current) / compare) * 100).round();

  return percent.clamp(1, 99);
}

String _compactCount(int value) {
  if (value >= 1000000) {
    final millions = value / 1000000;

    return '${millions.toStringAsFixed(millions >= 10 ? 0 : 1)}M';
  }
  if (value >= 1000) {
    final thousands = value / 1000;

    return '${thousands.toStringAsFixed(thousands >= 10 ? 0 : 1)}K';
  }

  return '$value';
}
