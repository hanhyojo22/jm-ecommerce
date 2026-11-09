import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../models/models.dart';
import '../providers/catalog_providers.dart';
import '../widgets/common.dart';

class StoreScreen extends ConsumerWidget {
  const StoreScreen({super.key, required this.slug});

  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final store = ref.watch(storeProvider(slug));
    final products = ref.watch(storeProductsProvider(slug));

    return Scaffold(
      appBar: AppBar(title: Text(store.value?.name ?? 'Store')),
      body: AsyncView(
        value: store,
        onRetry: () => ref.invalidate(storeProvider(slug)),
        builder: (value) => RefreshIndicator(
          onRefresh: () async {
            ref.invalidate(storeProvider(slug));
            ref.invalidate(storeProductsProvider(slug));
            await ref.read(storeProvider(slug).future);
          },
          child: CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              SliverToBoxAdapter(child: _StoreHeader(store: value)),
              SliverToBoxAdapter(
                child: SectionTitle(
                  title: 'Products',
                  trailing: Text(
                    '${products.value?.total ?? value.productsCount ?? 0} items',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: Theme.of(context).colorScheme.onSurfaceVariant,
                    ),
                  ),
                ),
              ),
              ...products.when(
                data: (page) => page.products.isEmpty
                    ? [
                        const SliverToBoxAdapter(
                          child: SizedBox(
                            height: 260,
                            child: EmptyState(
                              icon: Icons.storefront_outlined,
                              title: 'No products yet',
                              message:
                                  'This store has not published any products.',
                            ),
                          ),
                        ),
                      ]
                    : [
                        SliverLayoutBuilder(
                          builder: (context, constraints) {
                            final scaler = MediaQuery.textScalerOf(context);
                            final width = constraints.crossAxisExtent - 32;
                            final columns = scaler.scale(14) > 21 && width < 600
                                ? 1
                                : (width / 190).floor().clamp(2, 4);
                            final cardWidth =
                                (width - (columns - 1) * 12) / columns;

                            return SliverPadding(
                              padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                              sliver: SliverGrid(
                                gridDelegate:
                                    SliverGridDelegateWithFixedCrossAxisCount(
                                      crossAxisCount: columns,
                                      crossAxisSpacing: 12,
                                      mainAxisSpacing: 12,
                                      mainAxisExtent:
                                          cardWidth + scaler.scale(58) + 56,
                                    ),
                                delegate: SliverChildBuilderDelegate((
                                  context,
                                  index,
                                ) {
                                  final product = page.products[index];

                                  return ProductCard(
                                    product: product,
                                    showStore: false,
                                    onTap: () => context.push(
                                      '/product/${product.store?.slug ?? slug}/${product.slug}',
                                    ),
                                  );
                                }, childCount: page.products.length),
                              ),
                            );
                          },
                        ),
                      ],
                loading: () => [
                  SliverLayoutBuilder(
                    builder: (context, constraints) {
                      final width = constraints.crossAxisExtent - 32;
                      final columns = (width / 190).floor().clamp(2, 4);

                      return SliverPadding(
                        padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                        sliver: SliverGrid(
                          gridDelegate:
                              SliverGridDelegateWithFixedCrossAxisCount(
                                crossAxisCount: columns,
                                crossAxisSpacing: 12,
                                mainAxisSpacing: 12,
                                mainAxisExtent:
                                    ((width - (columns - 1) * 12) / columns) *
                                        0.9 +
                                    70,
                              ),
                          delegate: SliverChildBuilderDelegate(
                            (context, index) => Semantics(
                              container: index == 0,
                              liveRegion: index == 0,
                              label: index == 0 ? 'Loading products' : null,
                              child: ExcludeSemantics(
                                child: Card(
                                  clipBehavior: Clip.antiAlias,
                                  child: Padding(
                                    padding: const EdgeInsets.all(8),
                                    child: SkeletonBox(
                                      width: double.infinity,
                                      radius: 12,
                                    ),
                                  ),
                                ),
                              ),
                            ),
                            childCount: columns * 2,
                          ),
                        ),
                      );
                    },
                  ),
                ],
                error: (error, stack) => [
                  SliverToBoxAdapter(
                    child: SizedBox(
                      height: 280,
                      child: ErrorView(
                        error: error,
                        onRetry: () =>
                            ref.invalidate(storeProductsProvider(slug)),
                      ),
                    ),
                  ),
                ],
              ),
              const SliverToBoxAdapter(child: SizedBox(height: 12)),
            ],
          ),
        ),
      ),
    );
  }
}

class _StoreHeader extends StatelessWidget {
  const _StoreHeader({required this.store});

  final StoreInfo store;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (store.bannerUrl != null)
          NetworkThumb(
            url: store.bannerUrl,
            width: double.infinity,
            height: 140,
            radius: 0,
          )
        else
          Container(
            height: 110,
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: [
                  theme.colorScheme.primary,
                  theme.colorScheme.primaryContainer,
                ],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
            ),
          ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              StoreAvatar(name: store.name, logoUrl: store.logoUrl, radius: 30),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      store.name,
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    if (store.city != null && store.city!.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Row(
                        children: [
                          Icon(
                            Icons.location_on_outlined,
                            size: 15,
                            color: theme.colorScheme.onSurfaceVariant,
                          ),
                          const SizedBox(width: 3),
                          Expanded(
                            child: Text(
                              store.city!,
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: theme.colorScheme.onSurfaceVariant,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                    if (store.description != null &&
                        store.description!.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Text(
                        store.description!,
                        maxLines: 3,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodyMedium,
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
