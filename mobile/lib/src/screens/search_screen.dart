import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/api_exception.dart';
import '../core/formatters.dart';
import '../models/models.dart';
import '../providers/auth_providers.dart';
import '../providers/cart_providers.dart';
import '../providers/catalog_providers.dart';
import '../providers/store_locator_providers.dart';
import '../theme.dart';
import '../widgets/common.dart';
import '../widgets/product_feed.dart';
import '../widgets/store_locator.dart';
import 'auth_gate.dart';
import 'catalog_screen.dart' show categoryIcon;

/// Opens the buyer's chat with their branch, e.g. to send a prescription photo. A guest signs
/// in first, and someone without a branch is asked to pick one.
Future<void> openBranchChat(BuildContext context, WidgetRef ref) async {
  if (!await requireSignIn(context, ref) || !context.mounted) {
    return;
  }
  final branch = await ref.read(activeBranchIdProvider.future);
  if (!context.mounted) {
    return;
  }
  if (branch == null) {
    await showBranchSelectorSheet(context);
    return;
  }

  try {
    final conversation = await ref
        .read(apiClientProvider)
        .startConversation(branch: branch);
    if (context.mounted) {
      context.push('/conversations/${conversation.id}');
    }
  } on ApiException catch (error) {
    if (context.mounted) {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(error.friendly)));
    }
  }
}

/// Full-screen search, opened from the Shop's search box. Before typing it shows recent
/// searches, recently viewed medicines, categories and a prescription shortcut; while typing,
/// matching names, categories and medicines; after a search, the results with sort and filter.
///
/// Results reuse the Shop's product feed, so the Shop's own filter is put back on leaving.
class SearchScreen extends ConsumerStatefulWidget {
  const SearchScreen({super.key});

  @override
  ConsumerState<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends ConsumerState<SearchScreen> {
  static const _suggestionMinLength = 2;

  final _controller = TextEditingController();
  final _focus = FocusNode();
  final _scroll = ScrollController();
  late final CatalogFilter _shopFilter;
  CatalogFilter? _leaveWith;
  Timer? _debounce;
  SearchSuggestions _suggestions = const SearchSuggestions();
  String _suggestionsQuery = '';
  bool _suggestionsLoading = false;
  int _suggestionRequest = 0;
  bool _openingChat = false;

  @override
  void initState() {
    super.initState();
    _shopFilter = ref.read(catalogFilterProvider);
    _focus.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _controller.dispose();
    _focus.dispose();
    _scroll.dispose();
    super.dispose();
  }

  String get _typed => _controller.text.trim();

  void _onChanged(String value) {
    _debounce?.cancel();
    final query = value.trim();
    if (query.length < _suggestionMinLength) {
      _stopSuggestions();
      setState(() {});
      return;
    }

    _suggestionRequest++;
    setState(() {
      _suggestionsQuery = query;
      _suggestionsLoading = true;
    });
    _debounce = Timer(const Duration(milliseconds: 220), () {
      _loadSuggestions(query);
    });
  }

  Future<void> _loadSuggestions(String query) async {
    final request = ++_suggestionRequest;

    try {
      final branch = ref.read(activeBranchIdProvider).value;
      final suggestions = await ref
          .read(apiClientProvider)
          .searchSuggestions(query, branch: branch);
      if (!mounted || request != _suggestionRequest) {
        return;
      }
      setState(() {
        _suggestions = suggestions;
        _suggestionsLoading = false;
      });
    } catch (_) {
      if (!mounted || request != _suggestionRequest) {
        return;
      }
      setState(() {
        _suggestions = const SearchSuggestions();
        _suggestionsLoading = false;
      });
    }
  }

  void _stopSuggestions() {
    _debounce?.cancel();
    _suggestionRequest++;
    ref.read(apiClientProvider).cancelProductSuggestions();
    _suggestions = const SearchSuggestions();
    _suggestionsQuery = '';
    _suggestionsLoading = false;
  }

  void _submit(String value, {String? category}) {
    final query = value.trim();
    if (query.isEmpty) {
      return;
    }
    _controller.value = TextEditingValue(
      text: query,
      selection: TextSelection.collapsed(offset: query.length),
    );
    _focus.unfocus();
    _stopSuggestions();
    ref.read(searchHistoryProvider.notifier).recordSearch(query);
    ref
        .read(catalogFilterProvider.notifier)
        .apply(CatalogFilter(query: query, category: category));
    if (_scroll.hasClients) {
      _scroll.jumpTo(0);
    }
    setState(() {});
  }

  void _fill(String text) {
    _controller.value = TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: text.length),
    );
    _focus.requestFocus();
    _onChanged(text);
  }

  void _clear() {
    _controller.clear();
    _stopSuggestions();
    ref.read(catalogFilterProvider.notifier).apply(const CatalogFilter());
    _focus.requestFocus();
    setState(() {});
  }

  void _openProduct(Product product) {
    _focus.unfocus();
    ref.read(searchHistoryProvider.notifier).recordViewed(product);
    context.push('/product/${product.store?.slug ?? ''}/${product.slug}');
  }

  /// Leaves for the Shop, which then lists [filter], or what it showed before when null.
  void _leave([CatalogFilter? filter]) {
    _leaveWith = filter;
    Navigator.of(context).maybePop();
  }

  Future<void> _sendPrescription() async {
    if (_openingChat) {
      return;
    }
    setState(() => _openingChat = true);
    try {
      await openBranchChat(context, ref);
    } finally {
      if (mounted) {
        setState(() => _openingChat = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final filter = ref.watch(catalogFilterProvider);
    final typing =
        _typed.length >= _suggestionMinLength &&
        (_focus.hasFocus || _typed != filter.query);
    final showingResults = !typing && filter.query.isNotEmpty;

    return PopScope(
      onPopInvokedWithResult: (didPop, _) {
        if (didPop) {
          ref
              .read(catalogFilterProvider.notifier)
              .apply(_leaveWith ?? _shopFilter);
        }
      },
      // Light page, so dark status bar icons (the Shop behind sets light ones for its green band).
      child: AnnotatedRegion<SystemUiOverlayStyle>(
        value: SystemUiOverlayStyle.dark.copyWith(
          statusBarColor: Colors.transparent,
        ),
        child: Scaffold(
          backgroundColor: Theme.of(context).colorScheme.surface,
          body: SafeArea(
            bottom: false,
            child: Column(
              children: [
                _SearchHeader(
                  controller: _controller,
                  focusNode: _focus,
                  onBack: _leave,
                  onChanged: _onChanged,
                  onSubmitted: _submit,
                  onClear: _clear,
                ),
                Expanded(
                  child: typing
                      ? _SuggestionsView(
                          query: _suggestionsQuery.isEmpty
                              ? _typed
                              : _suggestionsQuery,
                          suggestions: _suggestions,
                          loading: _suggestionsLoading,
                          onName: (name) => _submit(name),
                          onFillName: _fill,
                          onCategory: (slug) => _submit(_typed, category: slug),
                          onProduct: _openProduct,
                          onSeeAll: () => _submit(_typed),
                        )
                      : showingResults
                      ? _ResultsView(
                          scroll: _scroll,
                          filter: filter,
                          onClearFilters: _clear,
                          onBrowseCategory: (slug) =>
                              _leave(CatalogFilter(category: slug)),
                        )
                      : _LandingView(
                          onSearch: _submit,
                          onFill: _fill,
                          onProduct: _openProduct,
                          onCategory: (slug) =>
                              _leave(CatalogFilter(category: slug)),
                          onPrescription: _sendPrescription,
                          openingChat: _openingChat,
                        ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _SearchHeader extends ConsumerWidget {
  const _SearchHeader({
    required this.controller,
    required this.focusNode,
    required this.onBack,
    required this.onChanged,
    required this.onSubmitted,
    required this.onClear,
  });

  final TextEditingController controller;
  final FocusNode focusNode;
  final VoidCallback onBack;
  final ValueChanged<String> onChanged;
  final ValueChanged<String> onSubmitted;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final cartCount = ref.watch(cartCountProvider);
    final reduceMotion = MediaQuery.disableAnimationsOf(context);

    return Material(
      color: scheme.surfaceContainerLowest,
      child: DecoratedBox(
        decoration: BoxDecoration(
          border: Border(bottom: BorderSide(color: scheme.outlineVariant)),
        ),
        child: Padding(
          padding: const EdgeInsetsDirectional.fromSTEB(4, 8, 4, 10),
          child: MediaQuery.withClampedTextScaling(
            maxScaleFactor: 1.6,
            child: Row(
              children: [
                IconButton(
                  tooltip: 'Back',
                  onPressed: onBack,
                  icon: const Icon(Icons.arrow_back_rounded),
                ),
                Expanded(
                  child: ListenableBuilder(
                    listenable: Listenable.merge([controller, focusNode]),
                    builder: (context, _) {
                      final focused = focusNode.hasFocus;
                      final hasText = controller.text.isNotEmpty;

                      return AnimatedContainer(
                        duration: reduceMotion
                            ? Duration.zero
                            : const Duration(milliseconds: 160),
                        curve: Curves.easeOutCubic,
                        constraints: const BoxConstraints(minHeight: 48),
                        decoration: BoxDecoration(
                          color: focused
                              ? scheme.surfaceContainerLow
                              : scheme.surfaceContainer,
                          borderRadius: BorderRadius.circular(24),
                          border: Border.all(
                            color: focused
                                ? scheme.primary
                                : Colors.transparent,
                            width: 1.5,
                          ),
                          boxShadow: focused
                              ? [
                                  BoxShadow(
                                    color: scheme.primary.withValues(
                                      alpha: 0.12,
                                    ),
                                    blurRadius: 10,
                                    offset: const Offset(0, 2),
                                  ),
                                ]
                              : null,
                        ),
                        child: Row(
                          children: [
                            Padding(
                              padding: const EdgeInsetsDirectional.only(
                                start: 14,
                                end: 8,
                              ),
                              child: Icon(
                                Icons.search_rounded,
                                size: 20,
                                color: scheme.primary,
                              ),
                            ),
                            Expanded(
                              child: TextField(
                                controller: controller,
                                focusNode: focusNode,
                                autofocus: true,
                                onChanged: onChanged,
                                onSubmitted: onSubmitted,
                                autocorrect: false,
                                enableSuggestions: false,
                                maxLines: 1,
                                textInputAction: TextInputAction.search,
                                style: theme.textTheme.bodyLarge?.copyWith(
                                  fontWeight: FontWeight.w600,
                                ),
                                decoration: InputDecoration(
                                  hintText: 'Medicine, brand or symptom',
                                  hintStyle: theme.textTheme.bodyLarge
                                      ?.copyWith(
                                        color: scheme.onSurfaceVariant,
                                        fontWeight: FontWeight.w500,
                                      ),
                                  filled: false,
                                  isDense: true,
                                  contentPadding: const EdgeInsets.symmetric(
                                    vertical: 13,
                                  ),
                                  border: InputBorder.none,
                                  enabledBorder: InputBorder.none,
                                  focusedBorder: InputBorder.none,
                                ),
                              ),
                            ),
                            if (hasText)
                              IconButton(
                                tooltip: 'Clear search',
                                onPressed: onClear,
                                icon: Container(
                                  width: 26,
                                  height: 26,
                                  decoration: BoxDecoration(
                                    color: scheme.surfaceContainerHighest,
                                    shape: BoxShape.circle,
                                  ),
                                  child: Icon(
                                    Icons.close_rounded,
                                    size: 15,
                                    color: scheme.onSurfaceVariant,
                                  ),
                                ),
                              ),
                          ],
                        ),
                      );
                    },
                  ),
                ),
                IconButton(
                  tooltip: cartCount > 0 ? 'Cart, $cartCount items' : 'Cart',
                  onPressed: () => context.push('/cart'),
                  icon: Badge(
                    isLabelVisible: cartCount > 0,
                    backgroundColor: scheme.primary,
                    textColor: scheme.onPrimary,
                    label: Text(cartCount > 99 ? '99+' : '$cartCount'),
                    child: const Icon(Icons.shopping_bag_outlined),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Before typing: recent searches, recently viewed, categories, and the prescription shortcut.
class _LandingView extends ConsumerWidget {
  const _LandingView({
    required this.onSearch,
    required this.onFill,
    required this.onProduct,
    required this.onCategory,
    required this.onPrescription,
    required this.openingChat,
  });

  final ValueChanged<String> onSearch;
  final ValueChanged<String> onFill;
  final ValueChanged<Product> onProduct;
  final ValueChanged<String> onCategory;
  final VoidCallback onPrescription;
  final bool openingChat;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final history = ref.watch(searchHistoryProvider);
    final viewed = history.recentlyViewed.take(8).toList(growable: false);
    final needs = (ref.watch(categoriesProvider).value ?? const <Category>[])
        .where((category) => category.productCount > 0)
        .take(4)
        .toList(growable: false);

    return ListView(
      keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
      padding: const EdgeInsets.only(bottom: 32),
      children: [
        if (history.recentSearches.isNotEmpty) ...[
          _SectionHeading(
            title: 'Recent searches',
            action: TextButton(
              onPressed: () => ref.read(searchHistoryProvider.notifier).clear(),
              child: const Text(
                'Clear history',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final term in history.recentSearches)
                  InputChip(
                    avatar: Icon(
                      Icons.history_rounded,
                      size: 16,
                      color: scheme.onSurfaceVariant,
                    ),
                    label: Text(term),
                    onPressed: () => onSearch(term),
                    onDeleted: () => ref
                        .read(searchHistoryProvider.notifier)
                        .removeSearch(term),
                    deleteButtonTooltipMessage: 'Remove $term',
                    backgroundColor: scheme.surfaceContainerLowest,
                    side: BorderSide(color: scheme.outlineVariant),
                    shape: const StadiumBorder(),
                    materialTapTargetSize: MaterialTapTargetSize.padded,
                  ),
              ],
            ),
          ),
        ],
        if (viewed.isNotEmpty) ...[
          _SectionHeading(
            title: 'Recently viewed',
            action: history.recentSearches.isEmpty
                ? TextButton(
                    onPressed: () =>
                        ref.read(searchHistoryProvider.notifier).clear(),
                    child: const Text(
                      'Clear history',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  )
                : null,
          ),
          // At most 8 tiles, so a plain Row sizes the strip to its tallest tile instead of a guessed height.
          MediaQuery.withClampedTextScaling(
            maxScaleFactor: 1.4,
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  for (final (index, product) in viewed.indexed) ...[
                    if (index > 0) const SizedBox(width: 10),
                    _ViewedTile(
                      product: product,
                      onTap: () => onProduct(product),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
        if (needs.isNotEmpty) ...[
          const _SectionHeading(title: 'Shop by need'),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: LayoutBuilder(
              builder: (context, constraints) {
                final width = (constraints.maxWidth - 10) / 2;

                return Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: [
                    for (final category in needs)
                      SizedBox(
                        width: width,
                        child: _NeedTile(
                          category: category,
                          onTap: () => onCategory(category.slug),
                        ),
                      ),
                  ],
                );
              },
            ),
          ),
        ],
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 24, 16, 0),
          child: _PrescriptionCard(onTap: onPrescription, busy: openingChat),
        ),
      ],
    );
  }
}

class _SectionHeading extends StatelessWidget {
  const _SectionHeading({required this.title, this.action});

  final String title;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    // Same height with or without the 48px action button, so every section sits the same distance below.
    return Padding(
      padding: EdgeInsetsDirectional.fromSTEB(
        16,
        12,
        action == null ? 16 : 4,
        4,
      ),
      child: ConstrainedBox(
        constraints: const BoxConstraints(minHeight: 48),
        child: Row(
          children: [
            Expanded(
              child: Semantics(
                header: true,
                child: Text(
                  title,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                    letterSpacing: -0.2,
                  ),
                ),
              ),
            ),
            if (action != null)
              Flexible(
                child: Align(
                  alignment: AlignmentDirectional.centerEnd,
                  child: action,
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _ViewedTile extends StatelessWidget {
  const _ViewedTile({required this.product, required this.onTap});

  final Product product;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return SizedBox(
      width: 96,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(appSurfaceRadius),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 96,
              height: 96,
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: theme.colorScheme.surfaceContainerLowest,
                borderRadius: BorderRadius.circular(appSurfaceRadius),
                border: Border.all(color: theme.colorScheme.outlineVariant),
              ),
              child: NetworkThumb(
                url: product.thumbnailUrl,
                fit: BoxFit.contain,
                cacheWidth: 240,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              product.name,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.bodySmall?.copyWith(
                fontWeight: FontWeight.w600,
                height: 1.25,
              ),
            ),
            Text(
              money(product.effectivePrice),
              style: theme.textTheme.labelLarge?.copyWith(
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _NeedTile extends StatelessWidget {
  const _NeedTile({required this.category, required this.onTap});

  final Category category;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Material(
      color: scheme.surfaceContainerLowest,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(appSurfaceRadius),
        side: BorderSide(color: scheme.outlineVariant),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 64),
          child: Padding(
            padding: const EdgeInsetsDirectional.fromSTEB(8, 8, 12, 8),
            child: Row(
              children: [
                Container(
                  width: 48,
                  height: 48,
                  decoration: BoxDecoration(
                    color: scheme.surfaceContainerLow,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  padding: const EdgeInsets.all(3),
                  child: Image.asset(
                    'assets/categories/${category.slug}.webp',
                    fit: BoxFit.contain,
                    cacheWidth: 144,
                    excludeFromSemantics: true,
                    errorBuilder: (context, error, stackTrace) => Icon(
                      categoryIcon(category.name),
                      size: 24,
                      color: scheme.primary,
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    category.name,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                      height: 1.2,
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
}

class _PrescriptionCard extends StatelessWidget {
  const _PrescriptionCard({required this.onTap, required this.busy});

  final VoidCallback onTap;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final slab = brandSlab(theme.colorScheme);

    return Semantics(
      button: true,
      label: 'Have a prescription? Send a photo to a pharmacist at your branch',
      excludeSemantics: true,
      child: Material(
        color: slab.fill,
        borderRadius: BorderRadius.circular(16),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: busy ? null : onTap,
          child: Padding(
            padding: const EdgeInsetsDirectional.fromSTEB(16, 16, 14, 16),
            child: Row(
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: slab.ink.withValues(alpha: 0.14),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(Icons.description_outlined, color: slab.ink),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Have a prescription?',
                        style: theme.textTheme.titleSmall?.copyWith(
                          color: slab.ink,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Send a photo to a pharmacist at your branch.',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: slab.ink.withValues(alpha: 0.82),
                          height: 1.35,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 10),
                Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(
                    color: slab.ink,
                    shape: BoxShape.circle,
                  ),
                  child: busy
                      ? Padding(
                          padding: const EdgeInsets.all(10),
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: slab.fill,
                          ),
                        )
                      : Icon(
                          Icons.arrow_forward_rounded,
                          size: 18,
                          color: slab.fill,
                        ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// While typing: medicine names, categories and up to four medicines, then "See all".
class _SuggestionsView extends StatelessWidget {
  const _SuggestionsView({
    required this.query,
    required this.suggestions,
    required this.loading,
    required this.onName,
    required this.onFillName,
    required this.onCategory,
    required this.onProduct,
    required this.onSeeAll,
  });

  final String query;
  final SearchSuggestions suggestions;
  final bool loading;
  final ValueChanged<String> onName;
  final ValueChanged<String> onFillName;
  final ValueChanged<String> onCategory;
  final ValueChanged<Product> onProduct;
  final VoidCallback onSeeAll;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final products = suggestions.products.take(4).toList(growable: false);
    final total = suggestions.total;
    final seeAllLabel = total != null && total > 0
        ? 'See all $total ${total == 1 ? 'result' : 'results'} for “$query”'
        : 'Search for “$query”';

    return Stack(
      children: [
        ListView(
          keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
          padding: const EdgeInsets.only(bottom: 96),
          children: [
            if (loading && suggestions.isEmpty)
              Semantics(
                liveRegion: true,
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Row(
                    children: [
                      const SizedBox.square(
                        dimension: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      ),
                      const SizedBox(width: 12),
                      Text(
                        'Looking for medicines…',
                        style: theme.textTheme.bodyMedium,
                      ),
                    ],
                  ),
                ),
              ),
            if (suggestions.names.isNotEmpty)
              ColoredBox(
                color: scheme.surfaceContainerLowest,
                child: Column(
                  children: [
                    for (final (index, name) in suggestions.names.indexed) ...[
                      if (index > 0)
                        Divider(
                          height: 1,
                          indent: 52,
                          color: scheme.surfaceContainer,
                        ),
                      InkWell(
                        onTap: () => onName(name.name),
                        child: ConstrainedBox(
                          constraints: const BoxConstraints(minHeight: 52),
                          child: Row(
                            children: [
                              Padding(
                                padding: const EdgeInsetsDirectional.only(
                                  start: 18,
                                  end: 16,
                                ),
                                child: Icon(
                                  Icons.search_rounded,
                                  size: 18,
                                  color: scheme.onSurfaceVariant,
                                ),
                              ),
                              Expanded(
                                child: _Highlighted(
                                  text: name.name,
                                  query: query,
                                  style: theme.textTheme.bodyLarge,
                                ),
                              ),
                              if (name.count > 0)
                                Text(
                                  '${name.count}',
                                  style: theme.textTheme.labelMedium?.copyWith(
                                    color: scheme.onSurfaceVariant,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              IconButton(
                                tooltip: 'Use ${name.name}',
                                onPressed: () => onFillName(name.name),
                                icon: Icon(
                                  Icons.north_west_rounded,
                                  size: 18,
                                  color: scheme.onSurfaceVariant,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            if (suggestions.categories.isNotEmpty)
              Container(
                color: scheme.surfaceContainerLowest,
                padding: const EdgeInsets.fromLTRB(16, 4, 16, 14),
                child: Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final category in suggestions.categories)
                      ActionChip(
                        label: Text.rich(
                          TextSpan(
                            text: category.name,
                            children: [
                              TextSpan(
                                text: '  ${category.count}',
                                style: TextStyle(
                                  fontWeight: FontWeight.w500,
                                  color: scheme.onSecondaryContainer.withValues(
                                    alpha: 0.7,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                        onPressed: () => onCategory(category.slug),
                        backgroundColor: scheme.secondaryContainer,
                        labelStyle: theme.textTheme.labelLarge?.copyWith(
                          color: scheme.onSecondaryContainer,
                          fontWeight: FontWeight.w700,
                        ),
                        side: BorderSide.none,
                        shape: const StadiumBorder(),
                      ),
                  ],
                ),
              ),
            if (suggestions.names.isNotEmpty ||
                suggestions.categories.isNotEmpty)
              Divider(height: 1, color: scheme.outlineVariant),
            if (products.isNotEmpty) ...[
              Padding(
                padding: const EdgeInsets.fromLTRB(18, 18, 18, 6),
                child: Text(
                  'MEDICINES',
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: scheme.onSurfaceVariant,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 0.6,
                  ),
                ),
              ),
              for (final item in products)
                _SuggestedProductRow(
                  product: item.product,
                  atBranch: item.atBranch,
                  query: query,
                  onTap: () => onProduct(item.product),
                ),
            ],
            if (!loading && suggestions.isEmpty)
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 24, 20, 0),
                child: Text(
                  'No quick matches. Search to see everything, including other branches.',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
                ),
              ),
          ],
        ),
        Positioned(
          left: 16,
          right: 16,
          bottom: 16 + MediaQuery.paddingOf(context).bottom,
          child: FilledButton.icon(
            onPressed: onSeeAll,
            style: FilledButton.styleFrom(
              backgroundColor: scheme.onSurface,
              foregroundColor: scheme.surface,
              minimumSize: const Size.fromHeight(54),
              shape: const StadiumBorder(),
              elevation: 4,
              shadowColor: scheme.onSurface.withValues(alpha: 0.3),
            ),
            icon: const Icon(Icons.search_rounded, size: 18),
            label: Text(
              seeAllLabel,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ),
      ],
    );
  }
}

class _SuggestedProductRow extends StatelessWidget {
  const _SuggestedProductRow({
    required this.product,
    required this.atBranch,
    required this.query,
    required this.onTap,
  });

  final Product product;
  final bool? atBranch;
  final String query;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final generic = product.genericNameLabel;
    final largeText = MediaQuery.textScalerOf(context).scale(14) > 21;
    final price = Text(
      money(product.effectivePrice),
      style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
    );

    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 9),
        child: Row(
          children: [
            Container(
              width: 56,
              height: 56,
              padding: const EdgeInsets.all(5),
              decoration: BoxDecoration(
                color: scheme.surfaceContainerLowest,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: scheme.outlineVariant),
              ),
              child: NetworkThumb(
                url: product.thumbnailUrl,
                fit: BoxFit.contain,
                cacheWidth: 168,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (generic != null)
                    Text(
                      generic.toUpperCase(),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.labelSmall?.copyWith(
                        color: scheme.primary,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 0.5,
                      ),
                    ),
                  _Highlighted(
                    text: product.name,
                    query: query,
                    maxLines: 2,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontWeight: FontWeight.w600,
                      height: 1.25,
                    ),
                  ),
                  if (product.requiresPrescription || atBranch != null) ...[
                    const SizedBox(height: 4),
                    Wrap(
                      spacing: 6,
                      runSpacing: 4,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        if (product.requiresPrescription)
                          _Pill(
                            label: 'Rx',
                            background: scheme.tertiaryContainer,
                            foreground: scheme.onTertiaryContainer,
                          ),
                        if (atBranch == true)
                          Text(
                            'In stock here',
                            style: theme.textTheme.labelMedium?.copyWith(
                              color: scheme.onSurfaceVariant,
                              fontWeight: FontWeight.w600,
                            ),
                          )
                        else if (atBranch == false)
                          _Pill(
                            label: 'Other branches',
                            background: const Color(0xFFFBF0DD),
                            foreground: const Color(0xFF8A4C06),
                          ),
                      ],
                    ),
                  ],
                  if (largeText) ...[const SizedBox(height: 4), price],
                ],
              ),
            ),
            if (!largeText) ...[const SizedBox(width: 10), price],
          ],
        ),
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill({
    required this.label,
    required this.background,
    required this.foreground,
  });

  final String label;
  final Color background;
  final Color foreground;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(appDetailRadius),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelSmall
            ?.copyWith(color: foreground, fontWeight: FontWeight.w800),
      ),
    );
  }
}

/// [text] with the first case-insensitive match of [query] in bold.
class _Highlighted extends StatelessWidget {
  const _Highlighted({
    required this.text,
    required this.query,
    this.style,
    this.maxLines = 1,
  });

  final String text;
  final String query;
  final TextStyle? style;
  final int maxLines;

  @override
  Widget build(BuildContext context) {
    final start = query.isEmpty
        ? -1
        : text.toLowerCase().indexOf(query.toLowerCase());
    final bold = (style ?? const TextStyle()).copyWith(
      fontWeight: FontWeight.w800,
    );

    return Text.rich(
      start < 0
          ? TextSpan(text: text)
          : TextSpan(
              children: [
                TextSpan(text: text.substring(0, start)),
                TextSpan(
                  text: text.substring(start, start + query.length),
                  style: bold,
                ),
                TextSpan(text: text.substring(start + query.length)),
              ],
            ),
      maxLines: maxLines,
      overflow: TextOverflow.ellipsis,
      style: style,
    );
  }
}

/// After a search: the count at this branch, sort, the prescription filter, then the results.
class _ResultsView extends ConsumerWidget {
  const _ResultsView({
    required this.scroll,
    required this.filter,
    required this.onClearFilters,
    required this.onBrowseCategory,
  });

  final ScrollController scroll;
  final CatalogFilter filter;
  final VoidCallback onClearFilters;
  final ValueChanged<String> onBrowseCategory;

  static const _sorts = {
    null: 'Best match',
    'price_asc': 'Price: low to high',
    'price_desc': 'Price: high to low',
  };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final products = ref.watch(productsProvider);
    final categories =
        ref.watch(categoriesProvider).value ?? const <Category>[];
    final previous = products.value;
    final feed = previous?.filter == filter ? previous : null;
    final branchName = ref
        .watch(nearestStoreProvider)
        .value
        ?.activeBranch
        ?.branch
        .name;
    final notifier = ref.read(catalogFilterProvider.notifier);
    final categoryName = categories
        .where((category) => category.slug == filter.category)
        .firstOrNull
        ?.name;
    final count = feed?.total ?? 0;

    void apply({
      required String? priceSort,
      required bool noPrescription,
      required String? category,
    }) => notifier.apply(
      CatalogFilter(
        query: filter.query,
        category: category,
        priceSort: priceSort,
        noPrescription: noPrescription,
      ),
    );

    return LayoutBuilder(
      builder: (context, constraints) {
        final grid = productGrid(context, constraints);
        final slivers = ProductFeedSlivers(
          context: context,
          ref: ref,
          gridDelegate: grid.delegate,
          onClearFilters: onClearFilters,
          onBrowseCategory: onBrowseCategory,
          onBackToTop: () => scroll.hasClients ? scroll.jumpTo(0) : null,
          showHeading: false,
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
          child: CustomScrollView(
            controller: scroll,
            keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
            slivers: [
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 14, 8, 4),
                sliver: SliverToBoxAdapter(
                  child: Row(
                    children: [
                      Expanded(
                        child: Semantics(
                          liveRegion: true,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                feed == null
                                    ? 'Searching…'
                                    : '$count ${count == 1 ? 'medicine' : 'medicines'}',
                                style: theme.textTheme.titleMedium?.copyWith(
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              if (branchName != null)
                                Text(
                                  'at $branchName',
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: theme.textTheme.bodySmall?.copyWith(
                                    color: scheme.onSurfaceVariant,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Flexible(
                        child: PopupMenuButton<String?>(
                          tooltip: 'Sort results',
                          initialValue: filter.priceSort,
                          onSelected: (value) => apply(
                            priceSort: value,
                            noPrescription: filter.noPrescription,
                            category: filter.category,
                          ),
                          itemBuilder: (context) => [
                            for (final entry in _sorts.entries)
                              CheckedPopupMenuItem<String?>(
                                value: entry.key,
                                checked: filter.priceSort == entry.key,
                                child: Text(entry.value),
                              ),
                          ],
                          child: Container(
                            constraints: const BoxConstraints(minHeight: 48),
                            padding: const EdgeInsetsDirectional.only(end: 8),
                            alignment: AlignmentDirectional.centerEnd,
                            child: Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 12,
                                vertical: 7,
                              ),
                              decoration: BoxDecoration(
                                color: scheme.surfaceContainerLowest,
                                border: Border.all(
                                  color: scheme.outlineVariant,
                                ),
                                borderRadius: BorderRadius.circular(20),
                              ),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  const Icon(Icons.swap_vert_rounded, size: 16),
                                  const SizedBox(width: 4),
                                  Flexible(
                                    child: Text(
                                      _sorts[filter.priceSort] ?? 'Best match',
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: theme.textTheme.labelLarge
                                          ?.copyWith(
                                            fontWeight: FontWeight.w700,
                                          ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              SliverToBoxAdapter(
                child: SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                  child: Row(
                    children: [
                      if (categoryName != null) ...[
                        InputChip(
                          label: Text(categoryName),
                          selected: true,
                          onDeleted: () => apply(
                            priceSort: filter.priceSort,
                            noPrescription: filter.noPrescription,
                            category: null,
                          ),
                          deleteButtonTooltipMessage: 'Show every category',
                          showCheckmark: false,
                          shape: const StadiumBorder(),
                        ),
                        const SizedBox(width: 8),
                      ],
                      FilterChip(
                        label: const Text('No prescription'),
                        selected: filter.noPrescription,
                        onSelected: (selected) => apply(
                          priceSort: filter.priceSort,
                          noPrescription: selected,
                          category: filter.category,
                        ),
                        showCheckmark: true,
                        shape: const StadiumBorder(),
                      ),
                    ],
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
                ...slivers.products(feed, categories)
              else
                ...slivers.skeleton(grid.cardWidth),
              SliverToBoxAdapter(
                child: SizedBox(
                  height: MediaQuery.paddingOf(context).bottom + 16,
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}
