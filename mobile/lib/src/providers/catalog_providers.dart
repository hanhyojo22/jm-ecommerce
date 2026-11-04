import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/models.dart';
import 'auth_providers.dart';

class CatalogFilter {
  const CatalogFilter({this.query = '', this.category});

  final String query;
  final String? category;

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
  });

  final CatalogFilter filter;
  final List<Product> products;
  final int page;
  final int lastPage;
  final int total;
  final bool loadingMore;

  bool get hasMore => page < lastPage;

  ProductFeed copyWith({
    List<Product>? products,
    int? page,
    int? lastPage,
    int? total,
    bool? loadingMore,
  }) => ProductFeed(
    filter: filter,
    products: products ?? this.products,
    page: page ?? this.page,
    lastPage: lastPage ?? this.lastPage,
    total: total ?? this.total,
    loadingMore: loadingMore ?? this.loadingMore,
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

  Future<void> loadMore() async {
    final current = state.value;

    if (current == null ||
        state.isLoading ||
        current.loadingMore ||
        !current.hasMore) {
      return;
    }

    state = AsyncData(current.copyWith(loadingMore: true));

    try {
      final next = await ref
          .read(apiClientProvider)
          .products(
            query: current.filter.query,
            category: current.filter.category,
            page: current.page + 1,
            perPage: catalogPageSize,
          );

      if (ref.read(catalogFilterProvider) != current.filter) {
        return;
      }

      state = AsyncData(
        ProductFeed(
          filter: current.filter,
          products: [...current.products, ...next.products],
          page: next.page,
          lastPage: next.lastPage,
          total: next.total,
        ),
      );
    } catch (_) {
      if (ref.read(catalogFilterProvider) == current.filter) {
        state = AsyncData(current.copyWith(loadingMore: false));
      }
    }
  }
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

final storeProductsProvider =
    FutureProvider.autoDispose.family<ProductPage, String>(
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
