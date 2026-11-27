import 'dart:async';
import 'dart:ui' show lerpDouble;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:phosphor_icons/phosphor_icons.dart';

import '../core/api_exception.dart';
import '../core/formatters.dart';
import '../models/models.dart';
import '../providers/catalog_providers.dart';
import '../providers/connectivity_providers.dart';
import '../providers/store_locator_providers.dart';
import '../theme.dart';
import '../widgets/common.dart';
import '../widgets/family_banner_carousel.dart';
import '../widgets/product_feed.dart';
import '../widgets/shell.dart';
import '../widgets/status_notice.dart';
import '../widgets/store_locator.dart';

class CatalogScreen extends ConsumerStatefulWidget {
  const CatalogScreen({super.key});

  @override
  ConsumerState<CatalogScreen> createState() => _CatalogScreenState();
}

class _CatalogScreenState extends ConsumerState<CatalogScreen> {
  final _scrollController = ScrollController();
  final _productsHeadingKey = GlobalKey();
  final _orderAgainKey = GlobalKey();
  bool _snappingHeader = false;
  double _headerCollapseRange = 0;
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

  void _openReorder(List<Product> boughtBefore) {
    final target = _orderAgainKey.currentContext;
    if (boughtBefore.isNotEmpty && target != null) {
      unawaited(
        Scrollable.ensureVisible(
          target,
          duration: MediaQuery.disableAnimationsOf(context)
              ? Duration.zero
              : const Duration(milliseconds: 240),
          curve: Curves.easeOutCubic,
          alignment: 0.12,
        ),
      );
      return;
    }
    ref.read(shellTabProvider.notifier).select(1);
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
    final locatorAsync = ref.watch(nearestStoreProvider);
    final locator = locatorAsync.value;
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
    final online = ref.watch(isOnlineProvider).value ?? true;

    final scheme = theme.colorScheme;
    final textScaler = MediaQuery.textScalerOf(context);
    final searchStyle = theme.textTheme.bodyLarge!;
    final barHeight =
        (textScaler.scale(searchStyle.fontSize!) * searchStyle.height! + 24)
            .clamp(48.0, double.infinity);
    final topPadding = MediaQuery.paddingOf(context).top;
    final mediaSize = MediaQuery.sizeOf(context);
    final reducedMotion = MediaQuery.disableAnimationsOf(context);
    // Short landscape viewports keep only the search bar pinned so cards still fit.
    final showQuickActions =
        !needsBranch && (!browsing || feed?.products.isNotEmpty == true);
    final pinQuickActions = mediaSize.height >= 480;
    final dockBottomRail = showQuickActions && pinQuickActions;
    final slab = brandSlab(scheme);

    final dark = theme.brightness == Brightness.dark;
    // Looks like the search field, but opens the full search page, as tapping search does in
    // most shopping apps; the page has the keyboard, history and suggestions.
    final searchBar = Semantics(
      button: true,
      label: 'Search medicines',
      onTap: () => context.push('/search'),
      excludeSemantics: true,
      child: DecoratedBox(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(barHeight / 2),
          boxShadow: [
            BoxShadow(
              color: (dark ? Colors.black : const Color(0xFF091947)).withValues(
                alpha: dark ? 0.34 : 0.22,
              ),
              blurRadius: 16,
              offset: const Offset(0, 6),
            ),
          ],
        ),
        child: Material(
          color: dark ? scheme.surfaceContainerLow : scheme.surfaceContainerLowest,
          elevation: 0,
          shadowColor: Colors.transparent,
          shape: StadiumBorder(
            side: BorderSide(
              color: dark
                  ? scheme.outlineVariant
                  : scheme.primary.withValues(alpha: 0.12),
            ),
          ),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: () => context.push('/search'),
            child: SizedBox(
              height: barHeight,
              child: Row(
                children: [
                  Padding(
                    padding: const EdgeInsetsDirectional.only(
                      start: 16,
                      end: 10,
                    ),
                    child: Icon(
                      PhosphorIconsBold.magnifyingGlass,
                      size: 19,
                      color: scheme.primary,
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
                  if (mediaSize.width >= 340 && textScaler.scale(14) <= 18) ...[
                    const SizedBox(width: 8),
                    ExcludeSemantics(
                      child: DecoratedBox(
                        decoration: BoxDecoration(
                          color: scheme.primaryContainer,
                          borderRadius: BorderRadius.circular(999),
                        ),
                        child: Padding(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 9,
                            vertical: 4,
                          ),
                          child: Text(
                            'OTC & Rx',
                            style: theme.textTheme.labelSmall?.copyWith(
                              color: dark
                                  ? scheme.onPrimaryContainer
                                  : scheme.primary,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                  ] else
                    const SizedBox(width: 16),
                ],
              ),
            ),
          ),
        ),
      ),
    );

    final headerDelegate = SliverShopHeaderDelegate(
      topPadding: topPadding,
      viewportWidth: mediaSize.width,
      textScaler: textScaler,
      barHeight: barHeight,
      slab: slab,
      theme: theme,
      locator: locatorAsync,
      searchBar: searchBar,
      reducedMotion: reducedMotion,
      refreshing: refreshing,
      dockBottomRail: dockBottomRail,
      forceFlatBottom: dockBottomRail && !browsing,
      compactOnly: !browsing,
      onTapBranch: () => showBranchSelectorSheet(context),
      onRetryBranches: () => ref.invalidate(storeBranchesProvider),
    );
    _headerCollapseRange = headerDelegate.collapseRange;

    return PopScope(
      canPop: !shopTabActive || filter.isDefault,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop && shopTabActive) {
          _clearFilters();
        }
      },
      child: Column(
        children: [
          Expanded(
            child: LayoutBuilder(
              builder: (context, constraints) {
                final grid = productGrid(context, constraints);
                final feedSlivers = ProductFeedSlivers(
                  context: context,
                  ref: ref,
                  gridDelegate: grid.delegate,
                  cardWidth: grid.cardWidth,
                  onClearFilters: _clearFilters,
                  onBrowseCategory: _browseCategory,
                  onBackToTop: _scrollToTop,
                  headingKey: _productsHeadingKey,
                );

                return NotificationListener<ScrollNotification>(
                  onNotification: (notification) {
                    if (notification.depth != 0) {
                      return false;
                    }
                    if (notification is ScrollStartNotification &&
                        notification.dragDetails != null) {
                      _snappingHeader = false;
                    }
                    if (notification.metrics.axis == Axis.vertical &&
                        notification.metrics.extentAfter < 500) {
                      unawaited(ref.read(productsProvider.notifier).loadMore());
                    }
                    if (notification is ScrollEndNotification &&
                        !_snappingHeader &&
                        !reducedMotion &&
                        _headerCollapseRange > 1 &&
                        _scrollController.hasClients) {
                      final pos = _scrollController.position;
                      if (pos.maxScrollExtent >= _headerCollapseRange &&
                          pos.pixels > 0.5 &&
                          pos.pixels < _headerCollapseRange - 0.5) {
                        final target = pos.pixels >= _headerCollapseRange * 0.45
                            ? _headerCollapseRange
                            : 0.0;
                        _snappingHeader = true;
                        WidgetsBinding.instance.addPostFrameCallback((_) {
                          if (!mounted ||
                              !_scrollController.hasClients ||
                              !_snappingHeader) {
                            _snappingHeader = false;
                            return;
                          }
                          unawaited(
                            _scrollController
                                .animateTo(
                                  target,
                                  duration: const Duration(milliseconds: 180),
                                  curve: Curves.easeOutCubic,
                                )
                                .whenComplete(() {
                                  _snappingHeader = false;
                                }),
                          );
                        });
                      }
                    }

                    return false;
                  },
                  child: RefreshIndicator(
                    edgeOffset: headerDelegate.minExtent,
                    onRefresh: _refreshProducts,
                    child: CustomScrollView(
                      controller: _scrollController,
                      physics: const AlwaysScrollableScrollPhysics(),
                      keyboardDismissBehavior:
                          ScrollViewKeyboardDismissBehavior.onDrag,
                      slivers: [
                        SliverPersistentHeader(
                          pinned: true,
                          delegate: headerDelegate,
                        ),
                        if (showQuickActions)
                          SliverPersistentHeader(
                            pinned: pinQuickActions,
                            delegate: _QuickActionsHeaderDelegate(
                              dealsActive: filter.onSale,
                              compactOnly: !browsing,
                              pinQuickActions: pinQuickActions,
                              viewportWidth: mediaSize.width,
                              slab: slab,
                              theme: theme,
                              textScaler: textScaler,
                              reducedMotion: reducedMotion,
                              textDirection: Directionality.of(context),
                              onDeals: filter.onSale
                                  ? _clearFilters
                                  : _showDeals,
                              onBranches: () => context.push('/branches'),
                              onSaved: () => context.push('/saved'),
                              onReorder: () => _openReorder(boughtBefore),
                            ),
                          ),
                        if (browsing &&
                            !needsBranch &&
                            feed?.products.isNotEmpty == true)
                          SliverPadding(
                            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                            sliver: SliverToBoxAdapter(
                              child: FamilyBannerCarousel(
                                onShop: () => context.push('/search'),
                                onBrowseCategory: (slug) {
                                  final exists =
                                      categories.value?.any(
                                        (category) => category.slug == slug,
                                      ) ??
                                      false;
                                  if (exists) {
                                    _browseCategory(slug);
                                  } else {
                                    context.push('/search');
                                  }
                                },
                                onShowDeals: _showDeals,
                                onOpenBranches: () => context.push('/branches'),
                              ),
                            ),
                          ),
                        if (browsing)
                          categories.when(
                            data: (items) => items.isEmpty
                                ? const SliverToBoxAdapter(
                                    child: SizedBox.shrink(),
                                  )
                                : SliverPadding(
                                    padding: const EdgeInsets.only(top: 20),
                                    sliver: SliverToBoxAdapter(
                                      child: Semantics(
                                        container: true,
                                        label: 'Shop by category',
                                        child: _CategoryCirclesStrip(
                                          options: _sortedCategoryOptions(
                                            items,
                                          ),
                                          onSelected: _selectCategory,
                                        ),
                                      ),
                                    ),
                                  ),
                            loading: () => const SliverToBoxAdapter(
                              child: Padding(
                                padding: EdgeInsets.symmetric(
                                  horizontal: 16,
                                  vertical: 16,
                                ),
                                child: LinearProgressIndicator(),
                              ),
                            ),
                            // The products error below already offers the page's retry.
                            error: (error, stack) =>
                                products.hasError && feed == null
                                ? const SliverToBoxAdapter(
                                    child: SizedBox.shrink(),
                                  )
                                : SliverToBoxAdapter(
                                    child: Padding(
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
                        if (!browsing && !needsBranch)
                          SliverPadding(
                            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                            sliver: SliverToBoxAdapter(
                              child: _ActiveFilterBar(
                                filter: filter,
                                categories: categories.value ?? const [],
                                onClear: _clearFilters,
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
                              message:
                                  'We show the medicines your branch has in stock.',
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
                                key: _orderAgainKey,
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
                              // The page's one retry, so it also reloads branches and categories.
                              child: ErrorView(
                                error: products.error!,
                                onRetry: () {
                                  if (ref
                                      .read(storeBranchesProvider)
                                      .hasError) {
                                    ref.invalidate(storeBranchesProvider);
                                  }
                                  _refreshProducts();
                                },
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
          // Offline, the app-wide bar already says so.
          if (products.hasError && feed != null && online)
            RefreshNoticeStrip(
              message:
                  products.error is ApiException &&
                      (products.error! as ApiException).isNetwork
                  ? 'No internet. Showing earlier products.'
                  : 'Could not refresh products.',
              actionLabel: 'Try again',
              onAction: _refreshProducts,
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

/// "Deals for you": the deepest markdowns in the loaded feed, in a showcase band.
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
    final brandColors = AppBrandColors.of(context);
    final maxDiscount = deals.first.discount;

    return Padding(
      padding: const EdgeInsets.only(top: 20),
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: brandColors.dealsBand,
          border: Border.symmetric(
            horizontal: BorderSide(color: brandColors.dealsBandBorder),
          ),
        ),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
                child: Row(
                  children: [
                    Expanded(
                      child: Wrap(
                        crossAxisAlignment: WrapCrossAlignment.center,
                        spacing: 8,
                        runSpacing: 4,
                        children: [
                          Semantics(
                            header: true,
                            child: Text(
                              'Top deals',
                              style: theme.textTheme.titleLarge?.copyWith(
                                fontSize: 19,
                                fontWeight: FontWeight.w800,
                                letterSpacing: -0.4,
                              ),
                            ),
                          ),
                          MediaQuery.withClampedTextScaling(
                            maxScaleFactor: 1.3,
                            child: DecoratedBox(
                              decoration: BoxDecoration(
                                color: scheme.error,
                                borderRadius: BorderRadius.circular(999),
                              ),
                              child: Padding(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                  vertical: 3,
                                ),
                                child: Text(
                                  'Up to $maxDiscount% off',
                                  maxLines: 1,
                                  style: theme.textTheme.labelSmall?.copyWith(
                                    color: scheme.onError,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ],
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
                          fontWeight: FontWeight.w800,
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
                            width: _railCardWidth,
                            child: ProductCard(
                              product: deal.product,
                              discovery: true,
                              showAddToCart: true,
                              fillHeight: true,
                              stackCartControls: ProductCard.stacksCartAt(
                                context,
                                _railCardWidth,
                              ),
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
        ),
      ),
    );
  }
}

typedef _QuickActionItem = ({String label, IconData icon, VoidCallback onTap});

List<_QuickActionItem> _buildQuickActionItems({
  required VoidCallback onDeals,
  required VoidCallback onBranches,
  required VoidCallback onSaved,
  required VoidCallback onReorder,
}) => [
  (label: 'Deals', icon: PhosphorIconsDuotone.sealPercent, onTap: onDeals),
  (
    label: 'Branches',
    icon: PhosphorIconsDuotone.storefront,
    onTap: onBranches,
  ),
  (label: 'Saved', icon: PhosphorIconsDuotone.heart, onTap: onSaved),
  (
    label: 'Reorder',
    icon: PhosphorIconsDuotone.clockCounterClockwise,
    onTap: onReorder,
  ),
];

/// Morphs the expanded quick-action circles into a compact row of 4 icon-only
/// actions pinned directly beneath the collapsed Shop search bar.
class _QuickActionsHeaderDelegate extends SliverPersistentHeaderDelegate {
  const _QuickActionsHeaderDelegate({
    required this.dealsActive,
    required this.compactOnly,
    required this.pinQuickActions,
    required this.viewportWidth,
    required this.slab,
    required this.theme,
    required this.textScaler,
    required this.reducedMotion,
    required this.textDirection,
    required this.onDeals,
    required this.onBranches,
    required this.onSaved,
    required this.onReorder,
  });

  static const _pinnedExtent = 56.0;

  final bool dealsActive;
  final bool compactOnly;
  final bool pinQuickActions;
  final double viewportWidth;
  final ({Color fill, Color ink}) slab;
  final ThemeData theme;
  final TextScaler textScaler;
  final bool reducedMotion;
  final TextDirection textDirection;
  final VoidCallback onDeals;
  final VoidCallback onBranches;
  final VoidCallback onSaved;
  final VoidCallback onReorder;

  double get _expandedExtent {
    final stripWidth = (viewportWidth - 32.0).clamp(0.0, double.infinity);
    final labelSize = textScaler.scale(12);
    final columns = (stripWidth - 24) / 4 < labelSize * 5 ? 2 : 4;
    final tileWidth = ((stripWidth - (columns - 1) * 8) / columns).clamp(
      0.0,
      double.infinity,
    );
    final textMaxWidth = (tileWidth - 4.0).clamp(0.0, double.infinity);
    final labelStyle = theme.textTheme.labelMedium?.copyWith(
      fontWeight: FontWeight.w700,
    );

    double tileHeight(String label) {
      final painter = TextPainter(
        text: TextSpan(text: label, style: labelStyle),
        textAlign: TextAlign.center,
        textDirection: textDirection,
        textScaler: textScaler,
      )..layout(maxWidth: textMaxWidth);
      final height = 76.0 + painter.height + 4.0;
      painter.dispose();
      return height;
    }

    final row1A = tileHeight('Deals');
    final row1B = tileHeight('Branches');
    final row2A = tileHeight('Saved');
    final row2B = tileHeight('Reorder');

    if (columns == 4) {
      var tallest = row1A > row1B ? row1A : row1B;
      if (row2A > tallest) {
        tallest = row2A;
      }
      if (row2B > tallest) {
        tallest = row2B;
      }
      return 16.0 + tallest;
    }

    final row1 = row1A > row1B ? row1A : row1B;
    final row2 = row2A > row2B ? row2A : row2B;
    return 16.0 + row1 + 8.0 + row2;
  }

  @override
  double get minExtent =>
      compactOnly || pinQuickActions ? _pinnedExtent : _expandedExtent;

  @override
  double get maxExtent => compactOnly ? _pinnedExtent : _expandedExtent;

  @override
  Widget build(
    BuildContext context,
    double shrinkOffset,
    bool overlapsContent,
  ) {
    final delta = maxExtent - minExtent;
    // Short landscape keeps the strip expanded instead of collapsing at delta == 0.
    final rawT = compactOnly
        ? 1.0
        : (delta <= 0 ? 0.0 : (shrinkOffset / delta).clamp(0.0, 1.0));
    final t = reducedMotion ? (rawT >= 0.5 ? 1.0 : 0.0) : rawT;

    final bgColor = Color.lerp(
      theme.scaffoldBackgroundColor,
      slab.fill,
      ((t - 0.15) / 0.70).clamp(0.0, 1.0),
    )!;
    final bottomRadius = lerpDouble(
      0.0,
      18.0,
      ((t - 0.25) / 0.75).clamp(0.0, 1.0),
    )!;
    final scheme = theme.colorScheme;
    final dark = scheme.brightness == Brightness.dark;
    final shadowAlpha = ((t - 0.65) / 0.35).clamp(0.0, 1.0);

    final expandedOpacity = compactOnly
        ? 0.0
        : (reducedMotion
              ? (t < 0.5 ? 1.0 : 0.0)
              : (1.0 - ((t - 0.15) / 0.50)).clamp(0.0, 1.0));
    final iconsOpacity = compactOnly
        ? 1.0
        : (reducedMotion
              ? (t >= 0.5 ? 1.0 : 0.0)
              : ((t - 0.40) / 0.45).clamp(0.0, 1.0));
    final iconsInteractive = compactOnly || t >= 0.5;
    final items = _buildQuickActionItems(
      onDeals: onDeals,
      onBranches: onBranches,
      onSaved: onSaved,
      onReorder: onReorder,
    );

    return DecoratedBox(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.vertical(
          bottom: Radius.circular(bottomRadius),
        ),
        boxShadow: shadowAlpha > 0
            ? [
                BoxShadow(
                  color: (dark ? Colors.black : const Color(0xFF0A163A))
                      .withValues(alpha: (dark ? 0.30 : 0.14) * shadowAlpha),
                  blurRadius: 12,
                  offset: const Offset(0, 4),
                ),
              ]
            : const [],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.vertical(
          bottom: Radius.circular(bottomRadius),
        ),
        child: Material(
          color: bgColor,
          child: Semantics(
            container: true,
            label: 'Quick actions',
            child: Stack(
              fit: StackFit.expand,
              children: [
                if (expandedOpacity > 0.01)
                  Positioned.fill(
                    child: IgnorePointer(
                      ignoring: iconsInteractive,
                      child: ExcludeSemantics(
                        excluding: iconsInteractive,
                        child: ClipRect(
                          child: OverflowBox(
                            alignment: Alignment.topCenter,
                            minHeight: _expandedExtent,
                            maxHeight: _expandedExtent,
                            child: Opacity(
                              opacity: expandedOpacity,
                              child: Transform.translate(
                                offset: Offset(0, reducedMotion ? 0 : -12.0 * t),
                                child: Padding(
                                  padding: const EdgeInsets.fromLTRB(
                                    16,
                                    12,
                                    16,
                                    4,
                                  ),
                                  child: _QuickActionsStrip(items: items),
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                if (iconsOpacity > 0.01)
                  Positioned.fill(
                    child: IgnorePointer(
                      ignoring: !iconsInteractive,
                      child: ExcludeSemantics(
                        excluding: !iconsInteractive,
                        child: Align(
                          alignment: Alignment.center,
                          child: Opacity(
                            opacity: iconsOpacity,
                            child: Transform.translate(
                              offset: Offset(
                                0,
                                reducedMotion ? 0 : (1.0 - iconsOpacity) * 8.0,
                              ),
                              child: _CollapsedQuickActionsBar(
                                items: items,
                                dealsActive: dealsActive,
                                slab: slab,
                                reducedMotion: reducedMotion,
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  @override
  bool shouldRebuild(covariant _QuickActionsHeaderDelegate oldDelegate) =>
      dealsActive != oldDelegate.dealsActive ||
      compactOnly != oldDelegate.compactOnly ||
      pinQuickActions != oldDelegate.pinQuickActions ||
      viewportWidth != oldDelegate.viewportWidth ||
      slab != oldDelegate.slab ||
      theme != oldDelegate.theme ||
      textScaler != oldDelegate.textScaler ||
      reducedMotion != oldDelegate.reducedMotion ||
      textDirection != oldDelegate.textDirection;
}

class _CollapsedQuickActionsBar extends StatelessWidget {
  const _CollapsedQuickActionsBar({
    required this.items,
    required this.dealsActive,
    required this.slab,
    required this.reducedMotion,
  });

  final List<_QuickActionItem> items;
  final bool dealsActive;
  final ({Color fill, Color ink}) slab;
  final bool reducedMotion;

  @override
  Widget build(BuildContext context) {
    return Padding(
      key: const Key('shop-collapsed-quick-actions'),
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
      child: Row(
        children: [
          for (final item in items)
            Expanded(
              child: _CollapsedQuickActionButton(
                item: item,
                active: dealsActive && item.label == 'Deals',
                slab: slab,
                reducedMotion: reducedMotion,
              ),
            ),
        ],
      ),
    );
  }
}

class _CollapsedQuickActionButton extends StatelessWidget {
  const _CollapsedQuickActionButton({
    required this.item,
    required this.active,
    required this.slab,
    required this.reducedMotion,
  });

  final _QuickActionItem item;
  final bool active;
  final ({Color fill, Color ink}) slab;
  final bool reducedMotion;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final dark = scheme.brightness == Brightness.dark;
    final brand = AppBrandColors.of(context);

    return Center(
      child: Semantics(
        button: true,
        selected: active,
        label: item.label,
        onTap: item.onTap,
        excludeSemantics: true,
        child: Tooltip(
          message: item.label,
          child: Material(
            color: Colors.transparent,
            child: InkWell(
              customBorder: const CircleBorder(),
              onTap: item.onTap,
              child: SizedBox.square(
                dimension: 48,
                child: Center(
                  child: AnimatedContainer(
                    duration: reducedMotion
                        ? Duration.zero
                        : const Duration(milliseconds: 180),
                    curve: Curves.easeOutCubic,
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      color: active
                          ? brand.sunGold
                          : Colors.white.withValues(alpha: dark ? 0.08 : 0.14),
                      shape: BoxShape.circle,
                      border: Border.all(
                        color: active
                            ? Colors.transparent
                            : Colors.white.withValues(
                                alpha: dark ? 0.14 : 0.20,
                              ),
                      ),
                    ),
                    child: Center(
                      child: PhosphorIcon(
                        item.icon,
                        size: 22,
                        color: active ? brand.onSunGold : slab.ink,
                        duotoneSecondaryColor: active
                            ? scheme.primary
                            : brand.sunGold,
                        duotoneSecondaryOpacity: active ? 0.22 : 0.90,
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Active filter summary above filtered product results with an `All` reset pill.
class _ActiveFilterBar extends StatelessWidget {
  const _ActiveFilterBar({
    required this.filter,
    required this.categories,
    required this.onClear,
  });

  final CatalogFilter filter;
  final List<Category> categories;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final dark = scheme.brightness == Brightness.dark;
    final allInk = dark ? scheme.onSecondaryContainer : scheme.primary;
    final activeLabel = _activeLabel();

    return Semantics(
      container: true,
      label: 'Active filter',
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: [
          Semantics(
            button: true,
            label: 'All',
            onTap: onClear,
            excludeSemantics: true,
            child: Material(
              color: dark
                  ? scheme.surfaceContainerLow
                  : scheme.surfaceContainerLowest,
              shape: StadiumBorder(
                side: BorderSide(color: scheme.outlineVariant),
              ),
              clipBehavior: Clip.antiAlias,
              child: InkWell(
                onTap: onClear,
                child: ConstrainedBox(
                  constraints: const BoxConstraints(minHeight: 48),
                  child: Padding(
                    padding: const EdgeInsetsDirectional.fromSTEB(
                      12,
                      0,
                      14,
                      0,
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          PhosphorIconsRegular.squaresFour,
                          size: 16,
                          color: allInk,
                        ),
                        const SizedBox(width: 6),
                        Text(
                          'All',
                          style: theme.textTheme.labelMedium?.copyWith(
                            color: scheme.onSurface,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
          if (activeLabel != null)
            Semantics(
              button: true,
              label: 'Clear $activeLabel filter',
              onTap: onClear,
              excludeSemantics: true,
              child: Material(
                color: scheme.primaryContainer,
                shape: StadiumBorder(
                  side: BorderSide(
                    color: scheme.primary.withValues(alpha: dark ? 0.45 : 0.25),
                  ),
                ),
                clipBehavior: Clip.antiAlias,
                child: InkWell(
                  onTap: onClear,
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(minHeight: 48),
                    child: Padding(
                      padding: const EdgeInsetsDirectional.fromSTEB(
                        14,
                        0,
                        10,
                        0,
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Flexible(
                            child: Text(
                              activeLabel,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: theme.textTheme.labelMedium?.copyWith(
                                color: scheme.onPrimaryContainer,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                          const SizedBox(width: 6),
                          Icon(
                            PhosphorIconsBold.x,
                            size: 14,
                            color: scheme.onPrimaryContainer,
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  String? _activeLabel() {
    if (filter.category != null) {
      return categories
              .where((category) => category.slug == filter.category)
              .firstOrNull
              ?.name ??
          _formatSlug(filter.category!);
    }
    if (filter.onSale) {
      return 'On sale';
    }
    if (filter.query.trim().isNotEmpty) {
      return '“${filter.query.trim()}”';
    }
    return null;
  }

  static String _formatSlug(String slug) {
    final words = slug
        .split(RegExp(r'[-_]+'))
        .where((word) => word.isNotEmpty)
        .map((word) => '${word[0].toUpperCase()}${word.substring(1)}');
    final joined = words.join(' ');
    return joined.isEmpty ? slug : joined;
  }
}

/// Pharmacy shortcuts keep their labels readable at larger text sizes.
class _QuickActionsStrip extends StatelessWidget {
  const _QuickActionsStrip({required this.items});

  final List<_QuickActionItem> items;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final labelSize = MediaQuery.textScalerOf(context).scale(12);
        final columns = (constraints.maxWidth - 24) / 4 < labelSize * 5 ? 2 : 4;
        final width = (constraints.maxWidth - (columns - 1) * 8) / columns;

        return Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final item in items)
              SizedBox(
                width: width,
                child: _QuickActionTile(
                  label: item.label,
                  icon: item.icon,
                  onTap: item.onTap,
                ),
              ),
          ],
        );
      },
    );
  }
}

class _QuickActionTile extends StatelessWidget {
  const _QuickActionTile({
    required this.label,
    required this.icon,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final dark = scheme.brightness == Brightness.dark;
    final brand = AppBrandColors.of(context);

    return Semantics(
      button: true,
      label: label,
      onTap: onTap,
      excludeSemantics: true,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(14),
          child: ConstrainedBox(
            constraints: const BoxConstraints(minHeight: 48),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 2),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Ink(
                    decoration: ShapeDecoration(
                      color: scheme.primaryContainer,
                      shape: const CircleBorder(),
                    ),
                    child: SizedBox.square(
                      dimension: 56,
                      child: Center(
                        child: PhosphorIcon(
                          icon,
                          size: 30,
                          color: dark
                              ? scheme.onPrimaryContainer
                              : scheme.primary,
                          duotoneSecondaryColor: brand.sunGold,
                          duotoneSecondaryOpacity: dark ? 0.85 : 1,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    label,
                    textAlign: TextAlign.center,
                    style: theme.textTheme.labelMedium?.copyWith(
                      color: scheme.onSurface,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Wide enough for the price and cart button on one line at normal text size.
const _railCardWidth = 172.0;

/// A titled sideways row of product cards, such as "Order again"; [ranked] numbers them 1, 2, 3….
class _ProductRail extends StatelessWidget {
  const _ProductRail({
    super.key,
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
                        width: _railCardWidth,
                        // Passthrough keeps the rail's equal heights reaching the card.
                        child: Stack(
                          fit: StackFit.passthrough,
                          children: [
                            ProductCard(
                              product: product,
                              discovery: true,
                              showAddToCart: true,
                              fillHeight: true,
                              // Same rule as the Popular products grid: cart beside the price.
                              stackCartControls: ProductCard.stacksCartAt(
                                context,
                                _railCardWidth,
                              ),
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

List<({String name, String slug})> _sortedCategoryOptions(
  List<Category> categories,
) {
  const categoryOrder = [
    'prescription-medicines',
    'pain-fever',
    'cough-colds',
    'vitamins-supplements',
    'first-aid',
  ];
  final sortedCategories = [...categories]
    ..sort((a, b) {
      final aIndex = categoryOrder.indexOf(a.slug);
      final bIndex = categoryOrder.indexOf(b.slug);
      final byPosition = (aIndex < 0 ? categoryOrder.length : aIndex).compareTo(
        bIndex < 0 ? categoryOrder.length : bIndex,
      );
      if (byPosition != 0) {
        return byPosition;
      }
      final byCount = b.productCount.compareTo(a.productCount);
      return byCount != 0 ? byCount : a.name.compareTo(b.name);
    });

  return <({String name, String slug})>[
    for (final category in sortedCategories)
      (name: category.name, slug: category.slug),
  ];
}

class _CategoryCirclesStrip extends StatelessWidget {
  const _CategoryCirclesStrip({required this.options, required this.onSelected});

  final List<({String name, String slug})> options;
  final ValueChanged<String?> onSelected;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final scaler = MediaQuery.textScalerOf(context);
    final categoryLabelStyle = theme.textTheme.labelMedium;
    final categoryLabelHeight =
        scaler.scale(categoryLabelStyle?.fontSize ?? 12) *
        (categoryLabelStyle?.height ?? 1.5);
    final brandColors = AppBrandColors.of(context);

    return SizedBox(
      height: 72 + categoryLabelHeight * 2 + 16,
      child: ListView.separated(
        key: const PageStorageKey('shop-category-circles'),
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        itemCount: options.length,
        separatorBuilder: (context, index) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final option = options[index];
          final circleFill = brandColors.categoryTint(
            option.slug,
            theme.brightness,
          );

          return SizedBox(
            // Wide enough at 2× text that one-word names like "Electronics" never split mid-word.
            width: scaler.scale(92).clamp(92, 184),
            child: Semantics(
              button: true,
              label: option.name,
              onTap: () => onSelected(option.slug),
              excludeSemantics: true,
              child: Material(
                color: Colors.transparent,
                child: InkWell(
                  borderRadius: BorderRadius.circular(appSurfaceRadius),
                  onTap: () => onSelected(option.slug),
                  child: Column(
                    children: [
                      Container(
                        width: 72,
                        height: 72,
                        decoration: BoxDecoration(
                          color: circleFill,
                          shape: BoxShape.circle,
                        ),
                        clipBehavior: Clip.antiAlias,
                        child: Padding(
                          padding: const EdgeInsets.all(8),
                          child: Image.asset(
                            'assets/categories/${option.slug}.webp',
                            fit: BoxFit.contain,
                            cacheWidth: 192,
                            excludeFromSemantics: true,
                            errorBuilder: (context, error, stackTrace) => Icon(
                              categoryIcon(option.name),
                              size: 26,
                              color: scheme.onSecondaryContainer,
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
                          color: scheme.onSurface,
                          fontWeight: FontWeight.w600,
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
    );
  }
}
