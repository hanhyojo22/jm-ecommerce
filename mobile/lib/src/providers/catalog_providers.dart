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

final productsProvider = FutureProvider.autoDispose<List<Product>>((ref) {
  final filter = ref.watch(catalogFilterProvider);

  return ref
      .watch(apiClientProvider)
      .products(query: filter.query, category: filter.category);
});

final categoriesProvider = FutureProvider.autoDispose<List<Category>>(
  (ref) => ref.watch(apiClientProvider).categories(),
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
