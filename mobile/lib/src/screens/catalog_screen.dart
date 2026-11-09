import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/formatters.dart';
import '../models/models.dart';
import '../providers/auth_providers.dart';
import '../providers/catalog_providers.dart';
import '../widgets/cart_button.dart';
import '../widgets/common.dart';

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
    final browsing = filter.query.isEmpty && filter.category == null;
    final previousFeed = products.value;
    final feed = previousFeed?.filter == filter ? previousFeed : null;
    final refreshing = products.isLoading && feed != null;

    final scheme = theme.colorScheme;
    // One height for the field and the cart so the header reads as a single row;
    // it grows a little with large text instead of clipping it.
    final headerScaler = MediaQuery.textScalerOf(context)
        .clamp(maxScaleFactor: 1.6);
    final barHeight = headerScaler.scale(48).clamp(48.0, 60.0);
    // Pad the single text line evenly so the field fills the pill (one 48dp tap target)
    // with hint and typed text centred; textAlignVertical alone did not centre it here.
    final inputLine = TextPainter(
      text: TextSpan(text: 'Ag', style: theme.textTheme.bodyLarge),
      textDirection: TextDirection.ltr,
      textScaler: headerScaler,
    )..layout();
    final inputPadding = ((barHeight - inputLine.height) / 2).clamp(0.0, 24.0);
    inputLine.dispose();
    final trailingSize = barHeight - 8;
    final searchPill = Container(
      height: barHeight,
      padding: const EdgeInsetsDirectional.only(start: 14),
      decoration: BoxDecoration(
        color: scheme.surfaceContainerLowest,
        borderRadius: BorderRadius.circular(barHeight / 2),
      ),
      child: Row(
        children: [
          Icon(Icons.search_rounded, size: 20, color: scheme.onSurfaceVariant),
          const SizedBox(width: 10),
          Expanded(
            child: TextField(
              controller: _searchController,
              focusNode: _searchFocus,
              onChanged: _onSearchChanged,
              onSubmitted: _submitSearch,
              onTapOutside: (_) => _searchFocus.unfocus(),
              autocorrect: false,
              enableSuggestions: false,
              maxLines: 1,
              style: theme.textTheme.bodyLarge,
              textInputAction: TextInputAction.search,
              decoration: InputDecoration(
                // The hint doubles as the TalkBack label; a hidden labelText pushed typed text off-centre.
                hintText: 'Search products and stores',
                filled: false,
                isDense: true,
                contentPadding: EdgeInsets.symmetric(vertical: inputPadding),
                border: InputBorder.none,
                enabledBorder: InputBorder.none,
                focusedBorder: InputBorder.none,
              ),
            ),
          ),
          // Empty, the hint gets the whole pill; once typing, clear and submit appear.
          ValueListenableBuilder<TextEditingValue>(
            valueListenable: _searchController,
            builder: (context, value, child) => value.text.isEmpty
                ? const SizedBox(width: 12)
                : Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
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
                      Padding(
                        padding: const EdgeInsetsDirectional.only(end: 4),
                        child: IconButton.filled(
                          tooltip: 'Search',
                          onPressed: () =>
                              _submitSearch(_searchController.text),
                          style: IconButton.styleFrom(
                            fixedSize: Size.square(trailingSize),
                            minimumSize: Size.square(trailingSize),
                            padding: EdgeInsets.zero,
                          ),
                          icon: const Icon(
                            Icons.arrow_forward_rounded,
                            size: 20,
                          ),
                        ),
                      ),
                    ],
                  ),
          ),
        ],
      ),
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
            child: Row(
              children: [
                Expanded(child: searchPill),
                const SizedBox(width: 12),
                CartButton(size: barHeight),
              ],
            ),
          ),
        ),
      ),
    );

    return Column(
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
              final cardWidth = (width - (columns - 1) * 12) / columns;
              final gridDelegate = SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: columns,
                crossAxisSpacing: 12,
                mainAxisSpacing: 16,
                mainAxisExtent: cardWidth + scaler.scale(58) + 64,
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
                          padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
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
                        child: categories.when(
                          data: (items) => items.isEmpty
                              ? const SizedBox.shrink()
                              : Padding(
                                  padding: const EdgeInsets.only(top: 16),
                                  child: _CategoryStrip(
                                    categories: items,
                                    selected: filter.category,
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
                            padding: const EdgeInsets.symmetric(horizontal: 16),
                            child: TextButton.icon(
                              onPressed: () =>
                                  ref.invalidate(categoriesProvider),
                              icon: const Icon(Icons.refresh_rounded),
                              label: const Text('Reload categories'),
                            ),
                          ),
                        ),
                      ),
                      if (browsing)
                        SliverPadding(
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                          sliver: SliverToBoxAdapter(
                            child: _DiscoveryBanner(
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
                        ..._productSkeletonSlivers(
                          theme,
                          gridDelegate,
                          cardWidth,
                        ),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }

  List<Widget> _productSlivers(
    ProductFeed feed,
    ThemeData theme,
    List<Category> categories,
    SliverGridDelegate gridDelegate,
  ) {
    final products = feed.products;

    if (products.isEmpty) {
      final filtered =
          feed.filter.query.isNotEmpty || feed.filter.category != null;
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
        padding: const EdgeInsets.fromLTRB(16, 24, 16, 12),
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
      SliverPadding(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
        sliver: SliverGrid(
          gridDelegate: gridDelegate,
          delegate: SliverChildBuilderDelegate((context, index) {
            final product = products[index];

            return ProductCard(
              key: ValueKey(product.id),
              product: product,
              discovery: true,
              onTap: () => context.push(
                '/product/${product.store?.slug ?? ''}/${product.slug}',
              ),
              onStoreTap: product.store == null
                  ? null
                  : () => context.push('/store/${product.store!.slug}'),
            );
          }, childCount: products.length),
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
    ThemeData theme,
    SliverGridDelegateWithFixedCrossAxisCount gridDelegate,
    double cardWidth,
  ) {
    return [
      SliverPadding(
        padding: const EdgeInsets.fromLTRB(16, 24, 16, 12),
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
                color: theme.colorScheme.surfaceContainerLowest,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                  side: BorderSide(
                    color: theme.colorScheme.outlineVariant.withValues(
                      alpha: 0.5,
                    ),
                  ),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(8),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Expanded(
                        child: SkeletonBox(width: double.infinity, radius: 10),
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
      child: Material(
        color: theme.colorScheme.surfaceContainerLowest,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
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
                      leading: const Icon(Icons.search_rounded),
                      title: Text(
                        'See all results for "$query"',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      onTap: onSeeAll,
                    ),
                  ],
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
              radius: 10,
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
                    Text(
                      product.store!.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
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

class _DiscoveryBanner extends StatelessWidget {
  const _DiscoveryBanner({this.onExplore});

  final VoidCallback? onExplore;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return LayoutBuilder(
      builder: (context, constraints) {
        final stacked = MediaQuery.textScalerOf(context).scale(14) > 21;
        final copy = Padding(
          padding: EdgeInsets.fromLTRB(
            16,
            12,
            stacked ? 16 : constraints.maxWidth * 0.42 + 12,
            4,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Semantics(
                header: true,
                child: Text(
                  'Good finds.\nCloser to home.',
                  style: theme.textTheme.titleLarge?.copyWith(
                    color: theme.colorScheme.primary,
                    fontWeight: FontWeight.w700,
                    height: 1.1,
                    letterSpacing: -0.6,
                  ),
                ),
              ),
              TextButton.icon(
                onPressed: onExplore,
                iconAlignment: IconAlignment.end,
                icon: const Icon(Icons.arrow_forward_rounded, size: 16),
                label: const Text('Explore products'),
                style: TextButton.styleFrom(
                  padding: EdgeInsets.zero,
                  minimumSize: const Size(48, 48),
                  alignment: Alignment.centerLeft,
                ),
              ),
            ],
          ),
        );
        final photo = Image.asset(
          'assets/home/collection.jpg',
          fit: BoxFit.cover,
          alignment: const Alignment(-0.1, 0),
          excludeFromSemantics: true,
        );

        return ClipRRect(
          borderRadius: BorderRadius.circular(16),
          child: ColoredBox(
            color: Color.alphaBlend(
              theme.colorScheme.primary.withValues(alpha: 0.06),
              theme.colorScheme.surface,
            ),
            child: stacked
                ? Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      copy,
                      SizedBox(
                        height: 120,
                        width: double.infinity,
                        child: photo,
                      ),
                    ],
                  )
                : Stack(
                    children: [
                      Positioned.fill(
                        child: Align(
                          alignment: Alignment.centerRight,
                          child: FractionallySizedBox(
                            widthFactor: 0.42,
                            heightFactor: 1,
                            child: photo,
                          ),
                        ),
                      ),
                      copy,
                    ],
                  ),
          ),
        );
      },
    );
  }
}

class _CategoryStrip extends StatelessWidget {
  const _CategoryStrip({
    required this.categories,
    required this.selected,
    required this.onSelected,
  });

  final List<Category> categories;
  final String? selected;
  final ValueChanged<String?> onSelected;

  String? _image(String? slug) => switch (slug) {
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
    'travel-luggage' => 'assets/categories/$slug.png',
    _ => null,
  };

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
      if (selected != null) (name: 'All', slug: null),
      for (final category in sortedCategories)
        (name: category.name, slug: category.slug),
    ];

    return Semantics(
      container: true,
      label: 'Shop by category',
      child: SizedBox(
        height: 64 + categoryLabelHeight * 2 + 12,
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 16),
          itemCount: options.length,
          separatorBuilder: (context, index) => const SizedBox(width: 12),
          itemBuilder: (context, index) {
            final option = options[index];
            final active = option.slug == selected;
            final image = _image(option.slug);
            final fallback = Center(
              child: Text(
                option.name.isEmpty ? '?' : option.name.characters.first,
              ),
            );

            return SizedBox(
              // Wide enough at 2× text that one-word names like "Electronics" never split mid-word.
              width: scaler.scale(76).clamp(72, 168),
              child: Semantics(
                button: true,
                selected: active,
                label: option.name,
                onTap: () => onSelected(active ? null : option.slug),
                excludeSemantics: true,
                child: Material(
                  color: Colors.transparent,
                  child: InkWell(
                    borderRadius: BorderRadius.circular(12),
                    onTap: () => onSelected(active ? null : option.slug),
                    child: Column(
                      children: [
                        Container(
                          width: 64,
                          height: 64,
                          decoration: BoxDecoration(
                            color: active && option.slug == null
                                ? theme.colorScheme.primary
                                : theme.colorScheme.surfaceContainerLowest,
                            borderRadius: BorderRadius.circular(32),
                            border: Border.all(
                              color: active
                                  ? theme.colorScheme.primary
                                  : Colors.transparent,
                              width: 2,
                            ),
                          ),
                          child: Stack(
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
