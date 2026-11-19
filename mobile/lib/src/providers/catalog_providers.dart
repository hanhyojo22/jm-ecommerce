import 'dart:convert';
import 'dart:io';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/models.dart';
import 'auth_providers.dart';
import 'store_locator_providers.dart';

class SearchHistory {
  const SearchHistory({
    this.recentSearches = const [],
    this.recentlyViewed = const [],
  });

  final List<String> recentSearches;
  final List<Product> recentlyViewed;

  bool get isEmpty => recentSearches.isEmpty && recentlyViewed.isEmpty;

  factory SearchHistory.fromJson(Map<String, dynamic> json) => SearchHistory(
    recentSearches:
        (json['recent_searches'] is List
                ? (json['recent_searches'] as List)
                      .whereType<String>()
                      .map((s) => s.trim())
                      .where((s) => s.length >= 2)
                : const <String>[])
            .take(5)
            .toList(growable: false),
    recentlyViewed: listOf(json['recently_viewed'])
        .map(Product.fromJson)
        .where((p) => p.id.isNotEmpty && p.slug.isNotEmpty)
        .take(8)
        .toList(growable: false),
  );

  Map<String, dynamic> toJson() => {
    'recent_searches': recentSearches,
    'recently_viewed': [
      for (final product in recentlyViewed)
        {
          'id': product.id,
          'name': product.name,
          'slug': product.slug,
          'price': product.price,
          'effective_price': product.effectivePrice,
          'compare_at_price': product.compareAtPrice,
          'thumbnail_url':
              product.thumbnailUrl ?? product.images.firstOrNull?.url,
          'in_stock': product.inStock,
          'stock_quantity': product.stockQuantity,
          if (product.store != null)
            'store': {
              'id': product.store!.id,
              'name': product.store!.name,
              'slug': product.store!.slug,
            },
        },
    ],
  };
}

/// Optional backing file for device persistence; null in widget tests so
/// FakeAsync never waits on disk I/O.
final searchHistoryFileProvider = Provider<File?>((ref) => null);

/// Initial snapshot loaded before runApp when a backing file exists.
final initialSearchHistoryProvider = Provider<SearchHistory>(
  (ref) => const SearchHistory(),
);

class SearchHistoryNotifier extends Notifier<SearchHistory> {
  static final _hasAlphaNum = RegExp(r'[\p{L}\p{N}]', unicode: true);

  @override
  SearchHistory build() => ref.watch(initialSearchHistoryProvider);

  void recordSearch(String query) {
    final trimmed = query.trim();
    if (trimmed.length < 2 || !_hasAlphaNum.hasMatch(trimmed)) {
      return;
    }
    final lower = trimmed.toLowerCase();
    final updated = [
      trimmed,
      for (final existing in state.recentSearches)
        if (existing.toLowerCase() != lower) existing,
    ].take(5).toList(growable: false);
    state = SearchHistory(
      recentSearches: updated,
      recentlyViewed: state.recentlyViewed,
    );
    _persist();
  }

  void removeSearch(String query) {
    final lower = query.trim().toLowerCase();
    final updated = state.recentSearches
        .where((existing) => existing.toLowerCase() != lower)
        .toList(growable: false);
    state = SearchHistory(
      recentSearches: updated,
      recentlyViewed: state.recentlyViewed,
    );
    _persist();
  }

  void recordViewed(Product product) {
    if (product.id.isEmpty || product.slug.isEmpty) {
      return;
    }
    final normalized = Product.fromJson({
      'id': product.id,
      'name': product.name,
      'slug': product.slug,
      'price': product.price,
      'effective_price': product.effectivePrice,
      'compare_at_price': product.compareAtPrice,
      'thumbnail_url': product.thumbnailUrl ?? product.images.firstOrNull?.url,
      'in_stock': product.inStock,
      'stock_quantity': product.stockQuantity,
      if (product.store != null)
        'store': {
          'id': product.store!.id,
          'name': product.store!.name,
          'slug': product.store!.slug,
        },
    });
    final updated = [
      normalized,
      for (final existing in state.recentlyViewed)
        if (existing.id != product.id) existing,
    ].take(8).toList(growable: false);
    state = SearchHistory(
      recentSearches: state.recentSearches,
      recentlyViewed: updated,
    );
    _persist();
  }

  void clear() {
    state = const SearchHistory();
    _persist();
  }

  void _persist() {
    final file = ref.read(searchHistoryFileProvider);
    if (file == null) {
      return;
    }
    final payload = jsonEncode(state.toJson());
    file.writeAsString(payload).ignore();
  }
}

final searchHistoryProvider =
    NotifierProvider<SearchHistoryNotifier, SearchHistory>(
      SearchHistoryNotifier.new,
    );

class CatalogFilter {
  const CatalogFilter({
    this.query = '',
    this.category,
    this.onSale = false,
    this.priceSort,
    this.noPrescription = false,
  });

  final String query;
  final String? category;

  /// The "See all" deals list: only discounted products.
  final bool onSale;

  /// `price_asc` or `price_desc` chosen on the search page; null keeps the default order.
  final String? priceSort;

  /// Search page filter: leave out medicines that need a prescription.
  final bool noPrescription;

  bool get isDefault =>
      query.isEmpty && category == null && !onSale && !noPrescription;

  String get sort =>
      priceSort ?? (query.trim().isEmpty ? 'popular' : 'relevance');

  CatalogFilter copyWith({
    String? query,
    String? category,
    bool clearCategory = false,
  }) => CatalogFilter(
    query: query ?? this.query,
    category: clearCategory ? null : (category ?? this.category),
    // Searching or picking a category leaves the deals list and the search page's sort and filter.
    onSale: false,
  );

  @override
  bool operator ==(Object other) =>
      other is CatalogFilter &&
      other.query == query &&
      other.category == category &&
      other.onSale == onSale &&
      other.priceSort == priceSort &&
      other.noPrescription == noPrescription;

  @override
  int get hashCode =>
      Object.hash(query, category, onSale, priceSort, noPrescription);
}

class CatalogFilterNotifier extends Notifier<CatalogFilter> {
  @override
  CatalogFilter build() => const CatalogFilter();

  void setQuery(String value) => state = state.copyWith(query: value);

  void setCategory(String? slug) =>
      state = state.copyWith(category: slug, clearCategory: slug == null);

  void showDeals() => state = const CatalogFilter(onSale: true);

  /// The search page sets its whole filter at once and puts the Shop's back when it closes.
  void apply(CatalogFilter filter) => state = filter;

  void reset() => state = const CatalogFilter();
}

final catalogFilterProvider =
    NotifierProvider<CatalogFilterNotifier, CatalogFilter>(
      CatalogFilterNotifier.new,
    );

const catalogPageSize = 20;

class ProductFeed {
  const ProductFeed({
    required this.filter,
    required this.products,
    required this.page,
    required this.lastPage,
    required this.total,
    this.loadingMore = false,
    this.loadMoreFailed = false,
    this.searchIngredient,
  });

  final CatalogFilter filter;
  final List<Product> products;
  final int page;
  final int lastPage;
  final int total;
  final bool loadingMore;
  final bool loadMoreFailed;
  final String? searchIngredient;

  bool get hasMore => page < lastPage;

  ProductFeed copyWith({
    List<Product>? products,
    int? page,
    int? lastPage,
    int? total,
    bool? loadingMore,
    bool? loadMoreFailed,
  }) => ProductFeed(
    filter: filter,
    products: products ?? this.products,
    page: page ?? this.page,
    lastPage: lastPage ?? this.lastPage,
    total: total ?? this.total,
    loadingMore: loadingMore ?? this.loadingMore,
    loadMoreFailed: loadMoreFailed ?? this.loadMoreFailed,
    searchIngredient: searchIngredient,
  );
}

class ProductsNotifier extends AsyncNotifier<ProductFeed> {
  @override
  Future<ProductFeed> build() async {
    final filter = ref.watch(catalogFilterProvider);
    final branch = await ref.watch(activeBranchIdProvider.future);
    final page = await ref
        .watch(apiClientProvider)
        .products(
          query: filter.query,
          category: filter.category,
          sort: filter.sort,
          onSale: filter.onSale,
          noPrescription: filter.noPrescription,
          perPage: catalogPageSize,
          branch: branch,
        );

    return ProductFeed(
      filter: filter,
      products: page.products,
      page: page.page,
      lastPage: page.lastPage,
      total: page.total,
      searchIngredient: page.searchIngredient,
    );
  }

  Future<void> loadMore({bool retry = false}) async {
    final current = state.value;

    if (current == null ||
        state.isLoading ||
        state.hasError ||
        current.loadingMore ||
        (current.loadMoreFailed && !retry) ||
        !current.hasMore) {
      return;
    }

    state = AsyncData(
      current.copyWith(loadingMore: true, loadMoreFailed: false),
    );

    try {
      final next = await ref
          .read(apiClientProvider)
          .products(
            query: current.filter.query,
            category: current.filter.category,
            sort: current.filter.sort,
            onSale: current.filter.onSale,
            noPrescription: current.filter.noPrescription,
            page: current.page + 1,
            perPage: catalogPageSize,
            branch: ref.read(activeBranchIdProvider).value,
          );

      if (!_isCurrentFeed(current)) {
        return;
      }

      final seen = current.products.map((product) => product.id).toSet();
      state = AsyncData(
        ProductFeed(
          filter: current.filter,
          products: [
            ...current.products,
            ...next.products.where((product) => seen.add(product.id)),
          ],
          page: next.page,
          lastPage: next.lastPage,
          total: next.total,
          searchIngredient: current.searchIngredient,
        ),
      );
    } catch (_) {
      if (_isCurrentFeed(current)) {
        state = AsyncData(
          current.copyWith(loadingMore: false, loadMoreFailed: true),
        );
      }
    }
  }

  bool _isCurrentFeed(ProductFeed feed) =>
      ref.mounted &&
      !state.isLoading &&
      identical(state.value?.products, feed.products) &&
      ref.read(catalogFilterProvider) == feed.filter;
}

final productsProvider =
    AsyncNotifierProvider.autoDispose<ProductsNotifier, ProductFeed>(
      ProductsNotifier.new,
    );

/// Discounted products for the home deals strip, independent of the feed's first page.
/// Company-wide matches for a search the shopper's branch has nothing for.
final elsewhereSearchProvider = FutureProvider.autoDispose
    .family<List<Product>, String>(
      (ref, query) async =>
          (await ref
                  .watch(apiClientProvider)
                  .products(query: query, perPage: 6))
              .products,
    );

/// Empty when signed out, before a branch is known, or if the request fails.
final boughtBeforeProvider = FutureProvider.autoDispose<List<Product>>((
  ref,
) async {
  if (!ref.watch(isSignedInProvider)) {
    return const [];
  }
  final branch = await ref.watch(activeBranchIdProvider.future);
  if (branch == null) {
    return const [];
  }
  try {
    return await ref.watch(apiClientProvider).boughtBefore(branch);
  } catch (_) {
    return const [];
  }
});

/// The branch's best sellers, for guests too; empty before a branch is known or if the request fails.
final bestSellersProvider = FutureProvider.autoDispose<List<Product>>((
  ref,
) async {
  final branch = await ref.watch(activeBranchIdProvider.future);
  if (branch == null) {
    return const [];
  }
  try {
    return await ref.watch(apiClientProvider).bestSellers(branch);
  } catch (_) {
    return const [];
  }
});

/// The buyer's wishlist; empty when signed out. Stock is the active branch's when one is known.
final savedProductsProvider = FutureProvider.autoDispose<List<Product>>((
  ref,
) async {
  if (!ref.watch(isSignedInProvider)) {
    return const [];
  }
  final branch = await ref.watch(activeBranchIdProvider.future);

  return ref.watch(apiClientProvider).savedProducts(branch: branch);
});

final dealsProvider = FutureProvider.autoDispose<List<Product>>(
  (ref) async =>
      (await ref
              .watch(apiClientProvider)
              .products(
                sort: 'popular',
                onSale: true,
                perPage: 12,
                branch: await ref.watch(activeBranchIdProvider.future),
              ))
          .products,
);

final categoriesProvider = FutureProvider.autoDispose<List<Category>>(
  (ref) => ref.watch(apiClientProvider).categories(),
);

final storeProvider = FutureProvider.autoDispose.family<StoreInfo, String>(
  (ref, slug) => ref.watch(apiClientProvider).store(slug),
);

final storeProductsProvider = FutureProvider.autoDispose
    .family<ProductPage, String>((ref, slug) async {
      // A branch page lists what that branch has on hand, not what it first listed.
      final store = await ref.watch(storeProvider(slug).future);
      return ref
          .watch(apiClientProvider)
          .products(branch: store.id, perPage: 48);
    });

class ProductKey {
  const ProductKey({required this.store, required this.slug});

  final String store;
  final String slug;

  @override
  bool operator ==(Object other) =>
      other is ProductKey && other.store == store && other.slug == slug;

  @override
  int get hashCode => Object.hash(store, slug);

  @override
  String toString() => '$store/$slug';
}

final productProvider = FutureProvider.autoDispose.family<Product, ProductKey>(
  (ref, key) async => ref
      .watch(apiClientProvider)
      .product(
        store: key.store,
        slug: key.slug,
        branch: await ref.watch(activeBranchIdProvider.future),
      ),
);

final productReviewsProvider = FutureProvider.autoDispose
    .family<ReviewPage, ({ProductKey product, int page})>(
      (ref, key) => ref
          .watch(apiClientProvider)
          .productReviews(
            store: key.product.store,
            product: key.product.slug,
            page: key.page,
          ),
    );

final productDiscussionsProvider = FutureProvider.autoDispose
    .family<DiscussionPage, ({ProductKey product, int page})>(
      (ref, key) => ref
          .watch(apiClientProvider)
          .productDiscussions(
            store: key.product.store,
            product: key.product.slug,
            page: key.page,
          ),
    );
