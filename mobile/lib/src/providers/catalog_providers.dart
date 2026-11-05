import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/models.dart';
import 'auth_providers.dart';

class CatalogFilter {
  const CatalogFilter({this.query = '', this.category});

  final String query;
  final String? category;

  String get sort => query.trim().isEmpty ? 'popular' : 'relevance';

  CatalogFilter copyWith({
    String? query,
    String? category,
    bool clearCategory = false,
  }) => CatalogFilter(
    query: query ?? this.query,
    category: clearCategory ? null : (category ?? this.category),
  );

  @override
  bool operator ==(Object other) =>
      other is CatalogFilter &&
      other.query == query &&
      other.category == category;

  @override
  int get hashCode => Object.hash(query, category);
}

class CatalogFilterNotifier extends Notifier<CatalogFilter> {
  @override
  CatalogFilter build() => const CatalogFilter();

  void setQuery(String value) => state = state.copyWith(query: value);

  void setCategory(String? slug) =>
      state = state.copyWith(category: slug, clearCategory: slug == null);
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
  });

  final CatalogFilter filter;
  final List<Product> products;
  final int page;
  final int lastPage;
  final int total;
  final bool loadingMore;
  final bool loadMoreFailed;

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
  );
}

class ProductsNotifier extends AsyncNotifier<ProductFeed> {
  @override
  Future<ProductFeed> build() async {
    final filter = ref.watch(catalogFilterProvider);
    final page = await ref
        .watch(apiClientProvider)
        .products(
          query: filter.query,
          category: filter.category,
          sort: filter.sort,
          perPage: catalogPageSize,
        );

    return ProductFeed(
      filter: filter,
      products: page.products,
      page: page.page,
      lastPage: page.lastPage,
      total: page.total,
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
            page: current.page + 1,
            perPage: catalogPageSize,
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

final categoriesProvider = FutureProvider.autoDispose<List<Category>>(
  (ref) => ref.watch(apiClientProvider).categories(),
);

final storeProvider = FutureProvider.autoDispose.family<StoreInfo, String>(
  (ref, slug) => ref.watch(apiClientProvider).store(slug),
);

final storeProductsProvider = FutureProvider.autoDispose
    .family<ProductPage, String>(
      (ref, slug) =>
          ref.watch(apiClientProvider).products(seller: slug, perPage: 48),
    );

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
  (ref, key) =>
      ref.watch(apiClientProvider).product(store: key.store, slug: key.slug),
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
