import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:phosphor_icons/phosphor_icons.dart';

import '../core/formatters.dart';
import '../models/models.dart';
import '../providers/catalog_providers.dart';
import '../providers/store_locator_providers.dart';
import '../theme.dart';
import '../widgets/common.dart';
import '../widgets/family_banner_carousel.dart';
import '../widgets/product_feed.dart';
import '../widgets/shell.dart';
import '../widgets/store_locator.dart';

class CatalogScreen extends ConsumerStatefulWidget {
  const CatalogScreen({super.key});

  @override
  ConsumerState<CatalogScreen> createState() => _CatalogScreenState();
}

class _CatalogScreenState extends ConsumerState<CatalogScreen> {
  final _scrollController = ScrollController();
  final _productsHeadingKey = GlobalKey();
  void _clearFilters() {
    ref.read(catalogFilterProvider.notifier)
      ..setQuery('')
      ..setCategory(null);
  }

  void _showDeals() {
    ref.read(catalogFilterProvider.notifier).showDeals();
  }

  void _selectCategory(String? slug) {
    ref.read(catalogFilterProvider.notifier).setCategory(slug);
  }

  void _browseCategory(String slug) {
    ref.read(catalogFilterProvider.notifier)
      ..setQuery('')
      ..setCategory(slug);
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  void _scrollToTop() {
    if (!_scrollController.hasClients || _scrollController.offset <= 0) {
      return;
    }

    if (MediaQuery.disableAnimationsOf(context)) {
      _scrollController.jumpTo(0);
      return;
    }

    _scrollController.animateTo(
      0,
      duration: const Duration(milliseconds: 180),
      curve: Curves.easeOutCubic,
    );
  }

  Future<void> _refreshProducts() async {
    ref.invalidate(categoriesProvider);
    ref.invalidate(dealsProvider);
    ref.invalidate(bestSellersProvider);
    ref.invalidate(productsProvider);
    try {
      await ref.read(productsProvider.future);
    } catch (_) {
      // The provider exposes the failure while retaining the loaded products.
    }
  }

  @override
  Widget build(BuildContext context) {
    ref.listen(catalogFilterProvider, (previous, next) {
      if (previous != next && _scrollController.hasClients) {
        _scrollController.jumpTo(0);
      }
    });
    final filter = ref.watch(catalogFilterProvider);
    final products = ref.watch(productsProvider);
    final categories = ref.watch(categoriesProvider);
    final theme = Theme.of(context);
    final browsing = filter.isDefault;
    final deals = ref.watch(dealsProvider).value;
    final boughtBefore = ref.watch(boughtBeforeProvider).value ?? const [];
    final bestSellers = ref.watch(bestSellersProvider).value ?? const [];
    final locator = ref.watch(nearestStoreProvider).value;
    // Stock and prices are per branch, so the list waits until one is known.
    final needsBranch =
        locator != null &&
        locator.branches.isNotEmpty &&
        locator.activeBranch == null;
    // Back on the Shop tab clears a search, category or deals list before leaving the app.
    final shopTabActive = ref.watch(shellTabProvider).index == 0;
    final previousFeed = products.value;
    final feed = previousFeed?.filter == filter ? previousFeed : null;
    final refreshing = products.isLoading && feed != null;

    final scheme = theme.colorScheme;
    final headerScaler = MediaQuery.textScalerOf(context)
        .clamp(maxScaleFactor: 1.6);
    final barHeight = headerScaler.scale(48).clamp(48.0, 56.0);
    // Looks like the search field, but opens the full search page, as tapping search does in
    // most shopping apps; the page has the keyboard, history and suggestions.
    final dark = scheme.brightness == Brightness.dark;
    final searchBar = Semantics(
      button: true,
      label: 'Search medicines',
      excludeSemantics: true,
      child: Material(
        color: scheme.surfaceContainerLowest,
        elevation: 0,
        shadowColor: Colors.transparent,
        shape: StadiumBorder(side: BorderSide(color: scheme.outlineVariant)),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: () => context.push('/search'),
          child: SizedBox(
            height: barHeight,
            child: Row(
              children: [
                Padding(
                  padding: const EdgeInsetsDirectional.only(start: 16, end: 10),
                  child: Icon(
                    Icons.search_rounded,
                    size: 20,
                    color: scheme.onSurfaceVariant,
                  ),
                ),
                Expanded(
                  child: DefaultTextStyle.merge(
                    style: theme.textTheme.bodyLarge?.copyWith(
                      color: scheme.onSurfaceVariant,
                      fontWeight: FontWeight.w500,
                    ),
                    child: const _ScrollingSearchPlaceholder(),
                  ),
                ),
                Padding(
                  padding: const EdgeInsetsDirectional.only(end: 6),
                  child: Container(
                    width: barHeight - 12,
                    height: barHeight - 12,
                    decoration: BoxDecoration(
                      color: dark ? scheme.secondary : scheme.primary,
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      Icons.search_rounded,
                      size: 22,
                      color: dark ? scheme.onSecondary : scheme.onPrimary,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );

    final slab = skySlab(scheme);
    // The shell drops its app bar on this tab, so this band sits behind the status bar.
    final search = AnnotatedRegion<SystemUiOverlayStyle>(
      value:
          (ThemeData.estimateBrightnessForColor(slab.fill) == Brightness.dark
                  ? SystemUiOverlayStyle.light
                  : SystemUiOverlayStyle.dark)
              .copyWith(statusBarColor: Colors.transparent),
      child: Material(
        color: slab.fill,
        borderRadius: const BorderRadius.vertical(bottom: Radius.circular(24)),
        child: MediaQuery.withClampedTextScaling(
          maxScaleFactor: 1.6,
          child: Padding(
            padding: EdgeInsetsDirectional.fromSTEB(
              16,
              MediaQuery.paddingOf(context).top + 10,
              16,
              16,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                // Stock and prices are per branch, so the branch scopes the search below it.
                BranchHeader(ink: slab.ink),
                const SizedBox(height: 8),
                searchBar,
              ],
            ),
          ),
        ),
      ),
    );

    return PopScope(
      canPop: !shopTabActive || filter.isDefault,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop && shopTabActive) {
          _clearFilters();
        }
      },
      child: Column(
        children: [
          search,
          if (products.hasError && feed != null)
            MaterialBanner(
              content: const Text('Could not refresh products.'),
              actions: [
                TextButton(
                  onPressed: _refreshProducts,
                  child: const Text('Retry refresh'),
                ),
              ],
            ),
          if (refreshing)
            LinearProgressIndicator(
              minHeight: 2,
              semanticsLabel: 'Updating products',
              color: theme.colorScheme.primary,
              backgroundColor: theme.colorScheme.surfaceContainerHighest,
            ),
          Expanded(
            child: LayoutBuilder(
              builder: (context, constraints) {
                final grid = productGrid(context, constraints);
                final feedSlivers = ProductFeedSlivers(
                  context: context,
                  ref: ref,
                  gridDelegate: grid.delegate,
                  onClearFilters: _clearFilters,
                  onBrowseCategory: _browseCategory,
                  onBackToTop: _scrollToTop,
                  headingKey: _productsHeadingKey,
                );

                return NotificationListener<ScrollNotification>(
                  onNotification: (notification) {
                    if (notification.depth == 0 &&
                        notification.metrics.axis == Axis.vertical &&
                        notification.metrics.extentAfter < 500) {
                      unawaited(ref.read(productsProvider.notifier).loadMore());
                    }

                    return false;
                  },
                  child: RefreshIndicator(
                    onRefresh: _refreshProducts,
                    child: CustomScrollView(
                      controller: _scrollController,
                      physics: const AlwaysScrollableScrollPhysics(),
                      keyboardDismissBehavior:
                          ScrollViewKeyboardDismissBehavior.onDrag,
                      slivers: [
                        if (browsing &&
                            !needsBranch &&
                            feed?.products.isNotEmpty == true)
                          SliverPadding(
                            padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                            sliver: SliverToBoxAdapter(
                              child: FamilyBannerCarousel(
                                onShop: () => context.push('/search'),
                              ),
                            ),
                          ),
                        SliverToBoxAdapter(
                          child: Material(
                            type: MaterialType.transparency,
                            child: categories.when(
                              data: (items) => items.isEmpty
                                  ? const SizedBox.shrink()
                                  : Padding(
                                      padding: const EdgeInsets.only(top: 20),
                                      child: _CategoryStrip(
                                        categories: items,
                                        selected: filter.category,
                                        dealsActive: filter.onSale,
                                        onSelected: _selectCategory,
                                      ),
                                    ),
                              loading: () => const Padding(
                                padding: EdgeInsets.symmetric(
                                  horizontal: 16,
                                  vertical: 16,
                                ),
                                child: LinearProgressIndicator(),
                              ),
                              error: (error, stack) => Padding(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 16,
                                ),
                                child: TextButton.icon(
                                  onPressed: () =>
                                      ref.invalidate(categoriesProvider),
                                  icon: const Icon(Icons.refresh_rounded),
                                  label: const Text('Reload categories'),
                                ),
                              ),
                            ),
                          ),
                        ),
                        if (browsing && needsBranch)
                          const SliverPadding(
                            padding: EdgeInsets.fromLTRB(16, 16, 16, 0),
                            sliver: SliverToBoxAdapter(
                              child: LocationFallbackCard(),
                            ),
                          ),
                        if (needsBranch)
                          SliverFillRemaining(
                            hasScrollBody: false,
                            child: EmptyState(
                              icon: Icons.storefront_outlined,
                              title: 'Choose your branch',
                              message: 'We show the medicines your branch has in stock.',
                              action: FilledButton(
                                onPressed: () =>
                                    showBranchSelectorSheet(context),
                                child: const Text('Pick a branch'),
                              ),
                            ),
                          )
                        else ...[
                          if (browsing && boughtBefore.isNotEmpty)
                            SliverToBoxAdapter(
                              child: _ProductRail(
                                title: 'Order again',
                                products: boughtBefore,
                              ),
                            ),
                          if (browsing && deals != null)
                            SliverToBoxAdapter(
                              child: _TopDeals(
                                products: deals,
                                onSeeAll: _showDeals,
                              ),
                            ),
                          if (browsing && bestSellers.isNotEmpty)
                            SliverToBoxAdapter(
                              child: _ProductRail(
                                title: 'Best sellers at your branch',
                                products: bestSellers,
                                ranked: true,
                              ),
                            ),
                          if (products.hasError && feed == null)
                            SliverFillRemaining(
                              hasScrollBody: false,
                              child: ErrorView(
                                error: products.error!,
                                onRetry: () => ref.invalidate(productsProvider),
                              ),
                            )
                          else if (feed != null)
                            ...feedSlivers.products(
                              feed,
                              categories.value ?? const [],
                            )
                          else
                            ...feedSlivers.skeleton(grid.cardWidth),
                        ],
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _ScrollingSearchPlaceholder extends StatefulWidget {
  const _ScrollingSearchPlaceholder();

  @override
  State<_ScrollingSearchPlaceholder> createState() =>
      _ScrollingSearchPlaceholderState();
}

class _ScrollingSearchPlaceholderState
    extends State<_ScrollingSearchPlaceholder> {
  static const _hints = [
    'Search medicines',
    'Search generic or brand name',
    'Search vitamins',
    'Search first aid',
  ];
  Timer? _timer;
  int _index = 0;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _timer?.cancel();
    final media = MediaQuery.of(context);
    if (media.disableAnimations ||
        media.accessibleNavigation ||
        !TickerMode.valuesOf(context).enabled) {
      _index = 0;
      return;
    }
    _timer = Timer.periodic(const Duration(seconds: 3), (_) {
      if (WidgetsBinding.instance.lifecycleState != AppLifecycleState.resumed) {
        return;
      }
      setState(() => _index = (_index + 1) % _hints.length);
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => SizedBox(
    width: double.infinity,
    child: ClipRect(
      child: AnimatedSwitcher(
        duration: const Duration(milliseconds: 300),
        switchInCurve: Curves.easeOutCubic,
        switchOutCurve: Curves.easeInCubic,
        layoutBuilder: (currentChild, previousChildren) => Stack(
          alignment: AlignmentDirectional.centerStart,
          children: [...previousChildren, ?currentChild],
        ),
        transitionBuilder: (child, animation) => FadeTransition(
          opacity: animation,
          child: SlideTransition(
            position: Tween<Offset>(
              begin: Offset(0, child.key == ValueKey(_index) ? 1 : -1),
              end: Offset.zero,
            ).animate(animation),
            child: child,
          ),
        ),
        child: Text(
          _hints[_index],
          key: ValueKey(_index),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
      ),
    ),
  );
}

/// "Deals for you": the deepest markdowns in the loaded feed, in a swipeable row.
class _TopDeals extends StatelessWidget {
  const _TopDeals({required this.products, required this.onSeeAll});

  final List<Product> products;
  final VoidCallback onSeeAll;

  @override
  Widget build(BuildContext context) {
    final deals =
        [
            for (final product in products)
              (
                product: product,
                discount: discountPercent(
                  product.effectivePrice,
                  product.compareAtPrice,
                ),
              ),
          ].where((deal) => deal.discount > 0).toList()
          ..sort((a, b) => b.discount.compareTo(a.discount));

    if (deals.isEmpty) {
      return const SizedBox.shrink();
    }

    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Padding(
      padding: const EdgeInsets.only(top: 28),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: Row(
              children: [
                Expanded(
                  child: Semantics(
                    header: true,
                    child: Text.rich(
                      TextSpan(
                        children: [
                          TextSpan(
                            text: 'Top ',
                            style: TextStyle(color: scheme.primary),
                          ),
                          const TextSpan(text: 'deals'),
                        ],
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontSize: 20,
                        fontWeight: FontWeight.w800,
                        letterSpacing: -0.4,
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                TextButton(
                  onPressed: onSeeAll,
                  style: TextButton.styleFrom(
                    foregroundColor: scheme.primary,
                    minimumSize: const Size(64, 48),
                    padding: const EdgeInsets.symmetric(horizontal: 12),
                    tapTargetSize: MaterialTapTargetSize.padded,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(appSurfaceRadius),
                    ),
                    textStyle: theme.textTheme.labelMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  child: const Text('See all'),
                ),
              ],
            ),
          ),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            // Same height for every card in the rail, buttons on one line.
            child: IntrinsicHeight(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  for (final deal in deals.take(10))
                    Padding(
                      padding: const EdgeInsetsDirectional.only(end: 10),
                      child: SizedBox(
                        width: 160,
                        child: ProductCard(
                          product: deal.product,
                          discovery: true,
                          showAddToCart: true,
                          fillHeight: true,
                          onTap: () => context.push(
                            '/product/${deal.product.store?.slug ?? ''}/${deal.product.slug}',
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// A titled sideways row of product cards, such as "Order again"; [ranked] numbers them 1, 2, 3….
class _ProductRail extends StatelessWidget {
  const _ProductRail({
    required this.title,
    required this.products,
    this.ranked = false,
  });

  final String title;
  final List<Product> products;
  final bool ranked;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Padding(
      padding: const EdgeInsets.only(top: 28),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
            child: Semantics(
              header: true,
              child: Text(
                title,
                style: theme.textTheme.titleLarge?.copyWith(
                  fontSize: 19,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.4,
                ),
              ),
            ),
          ),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            // Same height for every card in the rail, buttons on one line.
            child: IntrinsicHeight(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  for (final (index, product) in products.indexed)
                    Padding(
                      padding: const EdgeInsetsDirectional.only(end: 10),
                      child: SizedBox(
                        width: 160,
                        // Passthrough keeps the rail's equal heights reaching the card.
                        child: Stack(
                          fit: StackFit.passthrough,
                          children: [
                            ProductCard(
                              product: product,
                              discovery: true,
                              showAddToCart: true,
                              fillHeight: true,
                              onTap: () => context.push(
                                '/product/${product.store?.slug ?? ''}/${product.slug}',
                              ),
                            ),
                            if (ranked)
                              PositionedDirectional(
                                start: 8,
                                top: 8,
                                child: Semantics(
                                  label: 'Number ${index + 1}',
                                  excludeSemantics: true,
                                  child: Container(
                                    constraints: const BoxConstraints(
                                      minWidth: 28,
                                      minHeight: 28,
                                    ),
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 6,
                                    ),
                                    alignment: Alignment.center,
                                    decoration: BoxDecoration(
                                      color: index < 3
                                          ? scheme.primary
                                          : scheme.surfaceContainerHighest,
                                      borderRadius: BorderRadius.circular(
                                        appDetailRadius,
                                      ),
                                    ),
                                    child: Text(
                                      '${index + 1}',
                                      style: theme.textTheme.labelLarge
                                          ?.copyWith(
                                            color: index < 3
                                                ? scheme.onPrimary
                                                : scheme.onSurface,
                                            fontWeight: FontWeight.w800,
                                          ),
                                    ),
                                  ),
                                ),
                              ),
                          ],
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Head office names categories freely, so the icon follows words in the name.
/// Keys match whole words (plural allowed); a trailing `*` matches a word prefix,
/// so "Kidney Care" never gets the baby icon nor "IV Fluids" the cold icon.
IconData categoryIcon(String name) {
  final lower = name.toLowerCase();
  bool has(List<String> keys) => keys.any((key) {
    final prefix = key.endsWith('*');
    final word = RegExp.escape(prefix ? key.substring(0, key.length - 1) : key);
    return RegExp(prefix ? '\\b$word' : '\\b${word}s?\\b').hasMatch(lower);
  });

  if (has(['prescription*', 'rx'])) {
    return PhosphorIconsRegular.prescription;
  }
  if (has(['pain', 'fever'])) {
    return PhosphorIconsRegular.thermometerSimple;
  }
  if (has(['cough', 'cold', 'flu', 'allerg*'])) {
    return PhosphorIconsRegular.wind;
  }
  if (has(['first aid', 'wound'])) {
    return PhosphorIconsRegular.firstAidKit;
  }
  if (has(['vitamin', 'supplement'])) {
    return PhosphorIconsRegular.sparkle;
  }
  if (has(['baby', 'babies', 'kid', 'child*', 'mother*'])) {
    return PhosphorIconsRegular.baby;
  }
  if (has(['skin*', 'beauty', 'personal care'])) {
    return PhosphorIconsRegular.drop;
  }
  if (has(['heart', 'blood', 'diabet*'])) {
    return PhosphorIconsRegular.heartbeat;
  }
  if (has(['eye'])) {
    return PhosphorIconsRegular.eye;
  }
  if (has(['oral', 'dental', 'tooth', 'teeth'])) {
    return PhosphorIconsRegular.tooth;
  }
  if (has(['device', 'equipment', 'mask'])) {
    return PhosphorIconsRegular.faceMask;
  }
  return PhosphorIconsRegular.pill;
}

class _CategoryStrip extends StatelessWidget {
  const _CategoryStrip({
    required this.categories,
    required this.selected,
    required this.onSelected,
    this.dealsActive = false,
  });

  final List<Category> categories;
  final String? selected;

  /// The deals list is open, so "All" is offered as the way back.
  final bool dealsActive;
  final ValueChanged<String?> onSelected;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scaler = MediaQuery.textScalerOf(context);
    final categoryLabelStyle = theme.textTheme.labelMedium;
    final categoryLabelHeight =
        scaler.scale(categoryLabelStyle?.fontSize ?? 12) *
        (categoryLabelStyle?.height ?? 1.5);
    const categoryOrder = [
      'electronics',
      'fashion',
      'home-living',
      'health-beauty',
      'sports-outdoors',
    ];
    final sortedCategories = [...categories]
      ..sort((a, b) {
        final aIndex = categoryOrder.indexOf(a.slug);
        final bIndex = categoryOrder.indexOf(b.slug);
        final byPosition = (aIndex < 0 ? categoryOrder.length : aIndex)
            .compareTo(bIndex < 0 ? categoryOrder.length : bIndex);
        if (byPosition != 0) {
          return byPosition;
        }
        final byCount = b.productCount.compareTo(a.productCount);
        return byCount != 0 ? byCount : a.name.compareTo(b.name);
      });
    final options = <({String name, String? slug})>[
      if (selected != null || dealsActive) (name: 'All', slug: null),
      for (final category in sortedCategories)
        (name: category.name, slug: category.slug),
    ];

    return Semantics(
      container: true,
      label: 'Shop by category',
      child: SizedBox(
        height: 72 + categoryLabelHeight * 2 + 16,
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 16),
          itemCount: options.length,
          separatorBuilder: (context, index) => const SizedBox(width: 8),
          itemBuilder: (context, index) {
            final option = options[index];
            final active =
                option.slug == selected &&
                !(option.slug == null && dealsActive);
            final scheme = theme.colorScheme;

            return SizedBox(
              // Wide enough at 2× text that one-word names like "Electronics" never split mid-word.
              width: scaler.scale(92).clamp(92, 184),
              child: Semantics(
                button: true,
                selected: active,
                label: option.name,
                onTap: () => onSelected(active ? null : option.slug),
                excludeSemantics: true,
                child: Material(
                  color: Colors.transparent,
                  child: InkWell(
                    borderRadius: BorderRadius.circular(appSurfaceRadius),
                    onTap: () => onSelected(active ? null : option.slug),
                    child: Column(
                      children: [
                        AnimatedContainer(
                          duration: MediaQuery.disableAnimationsOf(context)
                              ? Duration.zero
                              : const Duration(milliseconds: 200),
                          curve: Curves.easeOutCubic,
                          width: 72,
                          height: 72,
                          decoration: BoxDecoration(
                            color: active
                                ? scheme.primaryContainer
                                : scheme.secondaryContainer,
                            shape: BoxShape.circle,
                            border: Border.all(
                              color: active
                                  ? scheme.primary
                                  : Colors.transparent,
                              width: 2,
                            ),
                          ),
                          clipBehavior: Clip.antiAlias,
                          child: option.slug == null
                              ? Icon(
                                  active
                                      ? PhosphorIconsBold.check
                                      : PhosphorIconsRegular.squaresFour,
                                  size: 26,
                                  color: active
                                      ? scheme.onPrimaryContainer
                                      : scheme.onSecondaryContainer,
                                )
                              : Padding(
                                  padding: const EdgeInsets.all(8),
                                  child: Image.asset(
                                    'assets/categories/${option.slug}.webp',
                                    fit: BoxFit.contain,
                                    cacheWidth: 192,
                                    excludeFromSemantics: true,
                                    errorBuilder:
                                        (context, error, stackTrace) => Icon(
                                          categoryIcon(option.name),
                                          size: 26,
                                          color: active
                                              ? scheme.onPrimaryContainer
                                              : scheme.onSecondaryContainer,
                                        ),
                                  ),
                                ),
                        ),
                        const SizedBox(height: 10),
                        Text(
                          option.name,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          textAlign: TextAlign.center,
                          style: theme.textTheme.labelMedium?.copyWith(
                            color: active
                                ? theme.colorScheme.primary
                                : theme.colorScheme.onSurface,
                            fontWeight: active
                                ? FontWeight.w700
                                : FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}
