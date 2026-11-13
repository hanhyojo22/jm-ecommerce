import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/formatters.dart';
import '../models/models.dart';
import '../providers/auth_providers.dart';
import '../providers/catalog_providers.dart';
import '../theme.dart';
import '../widgets/common.dart';
import '../widgets/shell.dart';

class CatalogScreen extends ConsumerStatefulWidget {
  const CatalogScreen({super.key});

  @override
  ConsumerState<CatalogScreen> createState() => _CatalogScreenState();
}

class _CatalogScreenState extends ConsumerState<CatalogScreen> {
  final _searchController = TextEditingController();
  final _searchFocus = FocusNode();
  final _scrollController = ScrollController();
  final _productsHeadingKey = GlobalKey();
  Timer? _suggestDebounce;
  List<Product> _suggestions = const [];
  String _suggestionsQuery = '';
  bool _suggestionsLoading = false;
  int _suggestionRequest = 0;

  static const _suggestionMinLength = 2;

  @override
  void initState() {
    super.initState();
    _searchFocus.addListener(_onSearchFocusChanged);
  }

  void _onSearchFocusChanged() {
    if (!_searchFocus.hasFocus) {
      _hideSuggestions();
    }
  }

  void _submitSearch(String value) {
    _searchFocus.unfocus();
    _hideSuggestions();
    ref.read(catalogFilterProvider.notifier).setQuery(value.trim());
  }

  void _clearSearch() {
    _searchController.clear();
    _hideSuggestions();
    ref.read(catalogFilterProvider.notifier).setQuery('');
    _searchFocus.requestFocus();
  }

  void _clearFilters() {
    _searchController.clear();
    _searchFocus.unfocus();
    _hideSuggestions();
    ref.read(catalogFilterProvider.notifier)
      ..setQuery('')
      ..setCategory(null);
  }

  void _showDeals() {
    _searchController.clear();
    _searchFocus.unfocus();
    _hideSuggestions();
    ref.read(catalogFilterProvider.notifier).showDeals();
  }

  void _selectCategory(String? slug) {
    _searchFocus.unfocus();
    _hideSuggestions();
    ref.read(catalogFilterProvider.notifier).setCategory(slug);
  }

  void _browseCategory(String slug) {
    _searchController.clear();
    _searchFocus.unfocus();
    _hideSuggestions();
    ref.read(catalogFilterProvider.notifier)
      ..setQuery('')
      ..setCategory(slug);
  }

  void _exploreProducts() {
    final target = _productsHeadingKey.currentContext;
    if (target != null) {
      Scrollable.ensureVisible(
        target,
        alignment: 0.2,
        duration: MediaQuery.disableAnimationsOf(context)
            ? Duration.zero
            : const Duration(milliseconds: 300),
        curve: Curves.easeOutCubic,
      );
    }
  }

  @override
  void dispose() {
    _suggestDebounce?.cancel();
    _searchFocus
      ..removeListener(_onSearchFocusChanged)
      ..dispose();
    _searchController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _onSearchChanged(String value) {
    _suggestDebounce?.cancel();
    final query = value.trim();
    if (query.length < _suggestionMinLength) {
      _hideSuggestions();
      return;
    }

    _suggestionRequest++;
    setState(() {
      _suggestionsQuery = query;
      _suggestions = const [];
      _suggestionsLoading = true;
    });
    _scrollToTop();

    _suggestDebounce = Timer(const Duration(milliseconds: 220), () {
      _loadSuggestions(query);
    });
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

  Future<void> _loadSuggestions(String query) async {
    final request = ++_suggestionRequest;

    try {
      final items = await ref.read(apiClientProvider).suggestedProducts(query);

      if (!mounted || request != _suggestionRequest) {
        return;
      }

      setState(() {
        _suggestions = items.take(5).toList(growable: false);
        _suggestionsLoading = false;
      });
    } catch (_) {
      if (!mounted || request != _suggestionRequest) {
        return;
      }

      setState(() {
        _suggestions = const [];
        _suggestionsLoading = false;
      });
    }
  }

  void _hideSuggestions() {
    _suggestDebounce?.cancel();
    _suggestionRequest++;
    ref.read(apiClientProvider).cancelProductSuggestions();

    if (_suggestions.isEmpty &&
        !_suggestionsLoading &&
        _suggestionsQuery.isEmpty) {
      return;
    }

    if (!mounted) {
      return;
    }

    setState(() {
      _suggestions = const [];
      _suggestionsQuery = '';
      _suggestionsLoading = false;
    });
  }

  void _openSuggestion(Product product) {
    _hideSuggestions();
    _searchFocus.unfocus();
    context.push('/product/${product.store?.slug ?? ''}/${product.slug}');
  }

  void _autocompleteSuggestion(Product product) {
    _searchController.value = TextEditingValue(
      text: product.name,
      selection: TextSelection.collapsed(offset: product.name.length),
    );
    _searchFocus.requestFocus();
    _onSearchChanged(product.name);
  }

  Future<void> _refreshProducts() async {
    ref.invalidate(categoriesProvider);
    ref.invalidate(dealsProvider);
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
    // Back on the Shop tab clears a search, category or deals list before leaving the app.
    final shopTabActive = ref.watch(shellTabProvider).index == 0;
    final previousFeed = products.value;
    final feed = previousFeed?.filter == filter ? previousFeed : null;
    final refreshing = products.isLoading && feed != null;

    final scheme = theme.colorScheme;
    // One height for the field and its button so the header reads as a single row;
    // it grows a little with large text instead of clipping it.
    final headerScaler = MediaQuery.textScalerOf(context)
        .clamp(maxScaleFactor: 1.6);
    final barHeight = headerScaler.scale(48).clamp(48.0, 56.0);
    // Pad the single text line evenly so the field fills the pill (one 48dp tap target)
    // with hint and typed text centred; textAlignVertical alone did not centre it here.
    final inputLine = TextPainter(
      text: TextSpan(text: 'Ag', style: theme.textTheme.bodyLarge),
      textDirection: TextDirection.ltr,
      textScaler: headerScaler,
    )..layout();
    final inputPadding = ((barHeight - inputLine.height) / 2).clamp(0.0, 24.0);
    inputLine.dispose();
    final trailingSize = barHeight - 12;
    final reduceMotion = MediaQuery.disableAnimationsOf(context);
    final searchListenables = Listenable.merge([
      _searchController,
      _searchFocus,
    ]);
    // Pill search: soft white field on the brand band, a focus ring instead of a
    // permanent border, and a round inset submit button.
    final searchBar = ListenableBuilder(
      listenable: searchListenables,
      builder: (context, child) {
        final hasFocus = _searchFocus.hasFocus;
        final hasText = _searchController.text.isNotEmpty;
        final active = hasFocus || hasText || filter.query.isNotEmpty;

        return AnimatedContainer(
          duration: reduceMotion
              ? Duration.zero
              : const Duration(milliseconds: 180),
          curve: Curves.easeOutCubic,
          height: barHeight,
          clipBehavior: Clip.antiAlias,
          decoration: BoxDecoration(
            color: scheme.surfaceContainerLowest,
            borderRadius: BorderRadius.circular(barHeight / 2),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: hasFocus ? 0.16 : 0.08),
                blurRadius: hasFocus ? 16 : 8,
                offset: const Offset(0, 3),
              ),
            ],
          ),
          // Paint the ring over the field to preserve 48dp button touch areas.
          foregroundDecoration: BoxDecoration(
            borderRadius: BorderRadius.circular(barHeight / 2),
            border: Border.all(
              color: hasFocus ? scheme.secondary : Colors.transparent,
              width: 2,
            ),
          ),
          child: Row(
            children: [
              Padding(
                padding: const EdgeInsetsDirectional.only(start: 16, end: 8),
                child: Icon(
                  Icons.search_rounded,
                  size: 20,
                  color: active ? scheme.primary : scheme.onSurfaceVariant,
                ),
              ),
              Expanded(
                child: Semantics(
                  label: 'Search products',
                  child: TextField(
                    controller: _searchController,
                    focusNode: _searchFocus,
                    onChanged: _onSearchChanged,
                    onSubmitted: _submitSearch,
                    onTapOutside: (_) => _searchFocus.unfocus(),
                    autocorrect: false,
                    enableSuggestions: false,
                    maxLines: 1,
                    style: theme.textTheme.bodyLarge?.copyWith(
                      fontWeight: FontWeight.w600,
                    ),
                    textInputAction: TextInputAction.search,
                    decoration: InputDecoration(
                      hint: DefaultTextStyle.merge(
                        style: theme.textTheme.bodyLarge?.copyWith(
                          color: scheme.onSurfaceVariant,
                          fontWeight: FontWeight.w500,
                        ),
                        child: ExcludeSemantics(
                          child: !hasText && !hasFocus
                              ? const _ScrollingSearchPlaceholder()
                              : const Text('Search products'),
                        ),
                      ),
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
              // Clear appears once typing; the Search button sits cleanly inset on the right,
              // collapsing to an icon only when typing on a narrow/large-text screen.
              Padding(
                padding: const EdgeInsetsDirectional.only(end: 2),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (hasText)
                      IconButton(
                        tooltip: 'Clear search',
                        onPressed: _clearSearch,
                        color: scheme.onSurfaceVariant,
                        style: IconButton.styleFrom(
                          fixedSize: Size.square(trailingSize),
                          minimumSize: Size.square(trailingSize),
                          tapTargetSize: MaterialTapTargetSize.padded,
                          padding: EdgeInsets.zero,
                        ),
                        icon: const Icon(Icons.close_rounded, size: 20),
                      ),
                    IconButton.filled(
                      tooltip: 'Search',
                      onPressed: () => _submitSearch(_searchController.text),
                      style: IconButton.styleFrom(
                        backgroundColor: scheme.primary,
                        foregroundColor: scheme.onPrimary,
                        fixedSize: Size.square(trailingSize),
                        minimumSize: Size.square(trailingSize),
                        tapTargetSize: MaterialTapTargetSize.padded,
                        padding: EdgeInsets.zero,
                        shape: const CircleBorder(),
                      ),
                      icon: Icon(
                        hasText
                            ? Icons.arrow_forward_rounded
                            : Icons.search_rounded,
                        size: 22,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );

    // The shell drops its app bar on this tab, so this band sits behind the status bar.
    final search = AnnotatedRegion<SystemUiOverlayStyle>(
      value:
          (ThemeData.estimateBrightnessForColor(scheme.primary) ==
                      Brightness.dark
                  ? SystemUiOverlayStyle.light
                  : SystemUiOverlayStyle.dark)
              .copyWith(statusBarColor: Colors.transparent),
      child: Material(
        color: scheme.primary,
        child: MediaQuery.withClampedTextScaling(
          maxScaleFactor: 1.6,
          child: Padding(
            padding: EdgeInsetsDirectional.fromSTEB(
              16,
              MediaQuery.paddingOf(context).top + 8,
              16,
              12,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [searchBar],
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
                final scaler = MediaQuery.textScalerOf(context);
                final width = constraints.maxWidth - 32;
                final columns = scaler.scale(14) > 21 && width < 600
                    ? 1
                    : (width / 190).floor().clamp(2, 4);
                final cardWidth = (width - (columns - 1) * 10) / columns;
                final gridDelegate = SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: columns,
                  crossAxisSpacing: 10,
                  mainAxisSpacing: 10,
                  // Loading skeleton only; loaded cards hug their own height.
                  mainAxisExtent: cardWidth + scaler.scale(157),
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
                        if (_suggestionsQuery.length >= _suggestionMinLength)
                          SliverPadding(
                            padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
                            sliver: SliverToBoxAdapter(
                              child: _SearchSuggestionsPanel(
                                query: _suggestionsQuery,
                                suggestions: _suggestions,
                                loading: _suggestionsLoading,
                                onSelected: _openSuggestion,
                                onAutocomplete: _autocompleteSuggestion,
                                onSeeAll: () {
                                  _searchController.text = _suggestionsQuery;
                                  _submitSearch(_suggestionsQuery);
                                },
                              ),
                            ),
                          ),
                        SliverToBoxAdapter(
                          child: DecoratedBox(
                            decoration: BoxDecoration(
                              gradient: LinearGradient(
                                begin: Alignment.topCenter,
                                end: Alignment.bottomCenter,
                                colors: [
                                  scheme.primary.withValues(alpha: 0.16),
                                  scheme.surface,
                                ],
                              ),
                            ),
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
                        if (browsing && deals != null)
                          SliverToBoxAdapter(
                            child: _TopDeals(
                              products: deals,
                              onSeeAll: _showDeals,
                            ),
                          ),
                        if (browsing)
                          SliverPadding(
                            padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
                            sliver: SliverToBoxAdapter(
                              child: _DiscoveryBanner(
                                products: feed?.products ?? const [],
                                onExplore: feed?.products.isNotEmpty == true
                                    ? _exploreProducts
                                    : null,
                              ),
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
                          ..._productSlivers(
                            feed,
                            theme,
                            categories.value ?? const [],
                            gridDelegate,
                          )
                        else
                          ..._productSkeletonSlivers(gridDelegate, cardWidth),
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

  List<Widget> _productSlivers(
    ProductFeed feed,
    ThemeData theme,
    List<Category> categories,
    SliverGridDelegateWithFixedCrossAxisCount gridDelegate,
  ) {
    final products = feed.products;

    if (products.isEmpty) {
      final filtered = !feed.filter.isDefault;
      final suggestions = categories
          .where((category) => category.productCount > 0)
          .take(4)
          .toList(growable: false);

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
                        onPressed: _clearFilters,
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
                                onPressed: () => _browseCategory(category.slug),
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
      SliverPadding(
        padding: const EdgeInsets.fromLTRB(16, 28, 16, 12),
        sliver: SliverToBoxAdapter(
          // Same row shape as the loading skeleton, so nothing jumps when products arrive.
          child: Row(
            key: _productsHeadingKey,
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
                      fontSize: 20,
                      fontWeight: FontWeight.w700,
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
      // Masonry like marketplace feeds: each column stacks on its own, so cards
      // hug their details instead of sharing a row height.
      // shortcut: products alternate columns rather than filling the shorter one; balance by height if the column ends drift noticeably.
      SliverPadding(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
        sliver: SliverCrossAxisGroup(
          slivers: [
            for (
              var column = 0;
              column < gridDelegate.crossAxisCount;
              column++
            ) ...[
              if (column > 0)
                SliverConstrainedCrossAxis(
                  maxExtent: gridDelegate.crossAxisSpacing,
                  sliver: const SliverToBoxAdapter(child: SizedBox.shrink()),
                ),
              SliverCrossAxisExpanded(
                flex: 1,
                sliver: SliverList.builder(
                  itemCount:
                      (products.length -
                          column +
                          gridDelegate.crossAxisCount -
                          1) ~/
                      gridDelegate.crossAxisCount,
                  itemBuilder: (context, row) {
                    final product =
                        products[row * gridDelegate.crossAxisCount + column];

                    return Padding(
                      key: ValueKey(product.id),
                      padding: EdgeInsets.only(
                        bottom: gridDelegate.mainAxisSpacing,
                      ),
                      child: ProductCard(
                        product: product,
                        discovery: true,
                        onTap: () => context.push(
                          '/product/${product.store?.slug ?? ''}/${product.slug}',
                        ),
                      ),
                    );
                  },
                ),
              ),
            ],
          ],
        ),
      ),
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
                  onPressed: _scrollToTop,
                  icon: const Icon(Icons.arrow_upward_rounded, size: 18),
                  label: const Text('Back to top'),
                ),
              ],
            ),
          ),
        ),
    ];
  }

  List<Widget> _productSkeletonSlivers(
    SliverGridDelegateWithFixedCrossAxisCount gridDelegate,
    double cardWidth,
  ) {
    return [
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

class _ScrollingSearchPlaceholder extends StatefulWidget {
  const _ScrollingSearchPlaceholder();

  @override
  State<_ScrollingSearchPlaceholder> createState() =>
      _ScrollingSearchPlaceholderState();
}

class _ScrollingSearchPlaceholderState
    extends State<_ScrollingSearchPlaceholder> {
  static const _hints = [
    'Search products',
    'Search electronics',
    'Search fashion',
    'Search home essentials',
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

class _SearchSuggestionsPanel extends StatelessWidget {
  const _SearchSuggestionsPanel({
    required this.query,
    required this.suggestions,
    required this.loading,
    required this.onSelected,
    required this.onAutocomplete,
    required this.onSeeAll,
  });

  final String query;
  final List<Product> suggestions;
  final bool loading;
  final ValueChanged<Product> onSelected;
  final ValueChanged<Product> onAutocomplete;
  final VoidCallback onSeeAll;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return TextFieldTapRegion(
      child: DecoratedBox(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(appSurfaceRadius),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.08),
              blurRadius: 12,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Material(
          color: theme.colorScheme.surfaceContainerLowest,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(appSurfaceRadius),
            side: BorderSide(color: theme.colorScheme.outlineVariant),
          ),
          clipBehavior: Clip.antiAlias,
          child: Semantics(
            container: true,
            label: 'Search suggestions',
            child: loading && suggestions.isEmpty
                ? Padding(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 16,
                      vertical: 16,
                    ),
                    child: Row(
                      children: [
                        const SizedBox.square(
                          dimension: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            'Looking for products…',
                            style: theme.textTheme.bodyMedium,
                          ),
                        ),
                      ],
                    ),
                  )
                : Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      for (
                        var index = 0;
                        index < suggestions.length;
                        index++
                      ) ...[
                        if (index > 0) const Divider(height: 1),
                        _SuggestionTile(
                          product: suggestions[index],
                          onTap: () => onSelected(suggestions[index]),
                          onAutocomplete: () =>
                              onAutocomplete(suggestions[index]),
                        ),
                      ],
                      if (suggestions.isNotEmpty) const Divider(height: 1),
                      ListTile(
                        leading: Icon(
                          Icons.search_rounded,
                          color: theme.colorScheme.primary,
                        ),
                        title: Text(
                          'See all results for "$query"',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: theme.colorScheme.primary,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        trailing: Icon(
                          Icons.arrow_forward_rounded,
                          size: 18,
                          color: theme.colorScheme.primary,
                        ),
                        onTap: onSeeAll,
                      ),
                    ],
                  ),
          ),
        ),
      ),
    );
  }
}

class _SuggestionTile extends StatelessWidget {
  const _SuggestionTile({
    required this.product,
    required this.onTap,
    required this.onAutocomplete,
  });

  final Product product;
  final VoidCallback onTap;
  final VoidCallback onAutocomplete;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final largeText = MediaQuery.textScalerOf(context).scale(14) > 21;

    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        child: Row(
          children: [
            NetworkThumb(
              url: product.thumbnailUrl,
              width: 44,
              height: 44,
              radius: appDetailRadius,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    product.name,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontWeight: FontWeight.w600,
                      height: 1.2,
                    ),
                  ),
                  if (product.store != null) ...[
                    const SizedBox(height: 2),
                    Row(
                      children: [
                        StoreAvatar(
                          name: product.store!.name,
                          logoUrl: product.store!.logoUrl,
                          storeSlug: product.store!.slug,
                          radius: 9,
                        ),
                        const SizedBox(width: 6),
                        Expanded(
                          child: Text(
                            product.store!.name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: theme.colorScheme.onSurfaceVariant,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                  if (largeText) ...[
                    const SizedBox(height: 4),
                    Text(
                      money(product.effectivePrice),
                      style: theme.textTheme.labelLarge?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            if (!largeText) ...[
              const SizedBox(width: 8),
              Text(
                money(product.effectivePrice),
                style: theme.textTheme.labelLarge?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
            IconButton(
              tooltip: 'Complete with ${product.name}',
              onPressed: onAutocomplete,
              icon: Icon(
                Icons.north_west_rounded,
                size: 18,
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ),
      ),
    );
  }
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
    final scaler = MediaQuery.textScalerOf(context);
    final priceStyle = theme.textTheme.titleSmall?.copyWith(
      color: scheme.error,
      fontWeight: FontWeight.w800,
      fontSize: 15,
      height: 1.3,
      letterSpacing: -0.3,
    );
    // Name line plus sale and original price lines.
    final priceHeight = scaler.scale(54);

    return Padding(
      padding: const EdgeInsets.only(top: 16),
      child: LayoutBuilder(
        builder: (context, constraints) {
          // Three tiles and a peek of the fourth, so the row reads as scrollable.
          final tileWidth = ((constraints.maxWidth - 32 - 30) / 3.35).clamp(
            96.0,
            168.0,
          );

          return Column(
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
              SizedBox(
                height: tileWidth + priceHeight + 16,
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  itemCount: deals.length.clamp(0, 10),
                  separatorBuilder: (context, index) =>
                      const SizedBox(width: 10),
                  itemBuilder: (context, index) {
                    final deal = deals[index];
                    final product = deal.product;

                    return SizedBox(
                      width: tileWidth,
                      child: Semantics(
                        button: true,
                        label:
                            '${product.name}, ${money(product.effectivePrice)}, ${deal.discount}% off',
                        excludeSemantics: true,
                        child: Material(
                          color: scheme.surfaceContainerLowest,
                          borderRadius: BorderRadius.circular(appSurfaceRadius),
                          clipBehavior: Clip.antiAlias,
                          child: InkWell(
                            onTap: () => context.push(
                              '/product/${product.store?.slug ?? ''}/${product.slug}',
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                SizedBox.square(
                                  dimension: tileWidth,
                                  child: Stack(
                                    fit: StackFit.expand,
                                    children: [
                                      NetworkThumb(
                                        url: product.thumbnailUrl,
                                        width: tileWidth,
                                        height: tileWidth,
                                        radius: 0,
                                      ),
                                      Positioned(
                                        right: 6,
                                        top: 6,
                                        child: DecoratedBox(
                                          decoration: BoxDecoration(
                                            color: scheme.errorContainer,
                                            borderRadius: BorderRadius.circular(
                                              appDetailRadius,
                                            ),
                                          ),
                                          child: Padding(
                                            padding: const EdgeInsets.symmetric(
                                              horizontal: 5,
                                              vertical: 2,
                                            ),
                                            child: Text(
                                              '-${deal.discount}%',
                                              style: theme.textTheme.labelSmall
                                                  ?.copyWith(
                                                    color:
                                                        scheme.onErrorContainer,
                                                    fontSize: 12,
                                                    fontWeight: FontWeight.w800,
                                                  ),
                                            ),
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                Expanded(
                                  child: Padding(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 8,
                                    ),
                                    child: Align(
                                      alignment: Alignment.centerLeft,
                                      child: Column(
                                        mainAxisSize: MainAxisSize.min,
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            product.name,
                                            maxLines: 1,
                                            overflow: TextOverflow.ellipsis,
                                            style: theme.textTheme.bodySmall
                                                ?.copyWith(
                                                  color: scheme.onSurface,
                                                ),
                                          ),
                                          const SizedBox(height: 1),
                                          FittedBox(
                                            fit: BoxFit.scaleDown,
                                            alignment: Alignment.centerLeft,
                                            child: Text(
                                              money(product.effectivePrice),
                                              maxLines: 1,
                                              style: priceStyle,
                                            ),
                                          ),
                                          const SizedBox(height: 1),
                                          FittedBox(
                                            fit: BoxFit.scaleDown,
                                            alignment: Alignment.centerLeft,
                                            child: Text(
                                              money(product.compareAtPrice),
                                              maxLines: 1,
                                              style: theme.textTheme.bodySmall
                                                  ?.copyWith(
                                                    fontSize: 11,
                                                    color:
                                                        scheme.onSurfaceVariant,
                                                    decoration: TextDecoration
                                                        .lineThrough,
                                                    decorationColor:
                                                        scheme.onSurfaceVariant,
                                                    decorationThickness: 1.5,
                                                  ),
                                            ),
                                          ),
                                        ],
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
                  },
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

/// Discovery campaign with store previews that adapt to the available width.
class _DiscoveryBanner extends StatelessWidget {
  const _DiscoveryBanner({required this.products, this.onExplore});

  final List<Product> products;
  final VoidCallback? onExplore;

  @override
  Widget build(BuildContext context) {
    final scaler = MediaQuery.textScalerOf(context);
    // Keep store previews tied to the live feed.
    final stores = <({StoreInfo store, Product product})>[];
    final seen = <String>{};
    for (final product in products) {
      final store = product.store;
      if (store != null && seen.add(store.id)) {
        stores.add((store: store, product: product));
      }
      if (stores.length == 2) {
        break;
      }
    }
    final showPromos = stores.isNotEmpty && scaler.scale(14) <= 18;

    return LayoutBuilder(
      builder: (context, constraints) {
        final height = scaler.scale(200).clamp(200.0, 320.0);
        final campaign = _CampaignCard(onExplore: onExplore);

        if (!showPromos) {
          return SizedBox(height: height, child: campaign);
        }

        if (constraints.maxWidth < 600) {
          return Column(
            children: [
              SizedBox(height: height, child: campaign),
              const SizedBox(height: 10),
              SizedBox(
                height: 96,
                child: Row(
                  children: [
                    Expanded(child: _StorePromo(featured: stores[0])),
                    if (stores.length > 1) ...[
                      const SizedBox(width: 10),
                      Expanded(child: _StorePromo(featured: stores[1])),
                    ],
                  ],
                ),
              ),
            ],
          );
        }

        return SizedBox(
          height: height,
          child: Row(
            children: [
              Expanded(flex: 3, child: campaign),
              const SizedBox(width: 10),
              Expanded(
                flex: 2,
                child: Column(
                  children: [
                    Expanded(child: _StorePromo(featured: stores[0])),
                    if (stores.length > 1) ...[
                      const SizedBox(height: 10),
                      Expanded(child: _StorePromo(featured: stores[1])),
                    ],
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _CampaignCard extends StatelessWidget {
  const _CampaignCard({this.onExplore});

  final VoidCallback? onExplore;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Material(
      color: scheme.primary,
      borderRadius: BorderRadius.circular(appSurfaceRadius),
      clipBehavior: Clip.antiAlias,
      child: Stack(
        fit: StackFit.expand,
        children: [
          Image.asset(
            'assets/home/collection.jpg',
            fit: BoxFit.cover,
            alignment: Alignment.centerRight,
            excludeFromSemantics: true,
          ),
          DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.centerLeft,
                end: Alignment.centerRight,
                colors: [
                  scheme.primary.withValues(alpha: 0.98),
                  scheme.primary.withValues(alpha: 0.82),
                  scheme.primary.withValues(alpha: 0.16),
                ],
                stops: const [0, 0.56, 1],
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Semantics(
                  header: true,
                  child: Text(
                    'Good finds.\nCloser to home.',
                    maxLines: 3,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.titleLarge?.copyWith(
                      color: scheme.onPrimary,
                      fontSize: 26,
                      fontWeight: FontWeight.w800,
                      height: 1.05,
                      letterSpacing: -0.6,
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                FilledButton.icon(
                  onPressed: onExplore,
                  iconAlignment: IconAlignment.end,
                  icon: const Icon(Icons.arrow_forward_rounded, size: 16),
                  label: const Text(
                    'Explore products',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  style: FilledButton.styleFrom(
                    backgroundColor: scheme.onPrimary,
                    foregroundColor: scheme.primary,
                    disabledBackgroundColor: scheme.onPrimary.withValues(
                      alpha: 0.75,
                    ),
                    disabledForegroundColor: scheme.primary.withValues(
                      alpha: 0.85,
                    ),
                    minimumSize: const Size(48, 48),
                    padding: const EdgeInsets.symmetric(horizontal: 14),
                    tapTargetSize: MaterialTapTargetSize.padded,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(appSurfaceRadius),
                    ),
                    textStyle: theme.textTheme.labelLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _StorePromo extends StatelessWidget {
  const _StorePromo({required this.featured});

  final ({StoreInfo store, Product product}) featured;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final store = featured.store;

    return Semantics(
      button: true,
      label: 'Visit ${store.name}',
      excludeSemantics: true,
      child: Material(
        color: scheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(appSurfaceRadius),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: () => context.push('/store/${store.slug}'),
          child: Stack(
            fit: StackFit.expand,
            children: [
              NetworkThumb(
                url: featured.product.thumbnailUrl ?? store.logoUrl,
                width: double.infinity,
                height: double.infinity,
                radius: 0,
              ),
              const DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [Colors.transparent, Color(0xB3000000)],
                    stops: [0.35, 1],
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.all(12),
                child: Align(
                  alignment: Alignment.bottomLeft,
                  child: MediaQuery.withClampedTextScaling(
                    maxScaleFactor: 1.3,
                    child: Row(
                      children: [
                        StoreAvatar(
                          name: store.name,
                          logoUrl: store.logoUrl,
                          radius: 14,
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            store.name,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.labelLarge?.copyWith(
                              color: Colors.white,
                              fontWeight: FontWeight.w800,
                              height: 1.1,
                            ),
                          ),
                        ),
                      ],
                    ),
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

String? _categoryImage(String? slug) => switch (slug) {
  'electronics' ||
  'fashion' ||
  'health-beauty' ||
  'home-living' ||
  'food-grocery' ||
  'babies-kids' ||
  'sports-outdoors' ||
  'pet-supplies' ||
  'automotive' ||
  'toys-collectibles' ||
  'office-school' ||
  'appliances' ||
  'bags-accessories' ||
  'hobbies-crafts' ||
  'books-media' ||
  'jewelry-watches' ||
  'garden-diy' ||
  'travel-luggage' => 'assets/categories/$slug.webp',
  _ => null,
};

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
        height: 58 + categoryLabelHeight * 2 + 16,
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
            final image = _categoryImage(option.slug);
            final fallback = Center(
              child: Text(
                option.name.isEmpty ? '?' : option.name.characters.first,
              ),
            );

            return SizedBox(
              // Wide enough at 2× text that one-word names like "Electronics" never split mid-word.
              width: scaler.scale(72).clamp(68, 168),
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
                        Container(
                          width: 58,
                          height: 58,
                          decoration: BoxDecoration(
                            color: active && option.slug == null
                                ? theme.colorScheme.primary
                                : theme.colorScheme.surfaceContainerLowest,
                            borderRadius: BorderRadius.circular(29),
                            border: Border.all(
                              color: active
                                  ? theme.colorScheme.primary
                                  : Colors.transparent,
                              width: 2,
                            ),
                          ),
                          child: Stack(
                            clipBehavior: Clip.none,
                            children: [
                              Positioned.fill(
                                child: Padding(
                                  padding: const EdgeInsets.all(2),
                                  child: option.slug == null
                                      ? Icon(
                                          active
                                              ? Icons.check_rounded
                                              : Icons.grid_view_rounded,
                                          color: active
                                              ? theme.colorScheme.onPrimary
                                              : theme
                                                    .colorScheme
                                                    .onSurfaceVariant,
                                        )
                                      : image == null
                                      ? fallback
                                      : Image.asset(
                                          image,
                                          fit: BoxFit.contain,
                                          cacheWidth: 192,
                                          excludeFromSemantics: true,
                                          errorBuilder: (
                                            context,
                                            error,
                                            stack,
                                          ) => fallback,
                                        ),
                                ),
                              ),
                              if (active && option.slug != null)
                                Positioned(
                                  right: -2,
                                  top: -2,
                                  child: CircleAvatar(
                                    radius: 9,
                                    backgroundColor: theme.colorScheme.primary,
                                    child: Icon(
                                      Icons.check_rounded,
                                      size: 12,
                                      color: theme.colorScheme.onPrimary,
                                    ),
                                  ),
                                ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          option.name,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          textAlign: TextAlign.center,
                          style: theme.textTheme.labelMedium?.copyWith(
                            color: active
                                ? theme.colorScheme.primary
                                : theme.colorScheme.onSurfaceVariant,
                            fontWeight: active
                                ? FontWeight.w700
                                : FontWeight.w500,
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
