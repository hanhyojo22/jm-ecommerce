import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/api_exception.dart';
import '../core/formatters.dart';
import '../models/models.dart';
import '../providers/auth_providers.dart';
import '../providers/cart_providers.dart';
import '../providers/catalog_providers.dart';
import '../widgets/common.dart';
import '../widgets/shell.dart';

class ProductScreen extends ConsumerWidget {
  const ProductScreen({super.key, required this.productKey});

  final ProductKey productKey;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final product = ref.watch(productProvider(productKey));

    return Scaffold(
      appBar: AppBar(
        title: Text(product.value?.name ?? 'Product'),
        actions: [
          IconButton(
            tooltip: 'Cart',
            onPressed: () {
              ref.read(shellTabProvider.notifier).select(1);

              if (context.canPop()) {
                context.pop();
              } else {
                context.go('/');
              }
            },
            icon: const Icon(Icons.shopping_bag_outlined),
          ),
        ],
      ),
      body: AsyncView(
        value: product,
        onRetry: () => ref.invalidate(productProvider(productKey)),
        builder: (value) => _ProductBody(product: value),
      ),
    );
  }
}

class _ProductBody extends ConsumerStatefulWidget {
  const _ProductBody({required this.product});

  final Product product;

  @override
  ConsumerState<_ProductBody> createState() => _ProductBodyState();
}

class _ProductBodyState extends ConsumerState<_ProductBody> {
  String? _variantId;
  int _quantity = 1;
  bool _adding = false;
  bool _chatting = false;
  int _imageIndex = 0;

  Product get product => widget.product;

  ProductVariant? get _variant {
    if (_variantId == null) {
      return null;
    }

    for (final variant in product.variants) {
      if (variant.id == _variantId) {
        return variant;
      }
    }

    return null;
  }

  int get _maxQuantity {
    final stock = _variant?.stockQuantity ?? product.stockQuantity;

    if (stock < 1) {
      return 1;
    }

    return stock > 100 ? 100 : stock;
  }

  bool get _canAdd {
    final variant = _variant;

    return product.inStock &&
        (!product.requiresVariantSelection || _variantId != null) &&
        (variant == null || variant.inStock);
  }

  @override
  void initState() {
    super.initState();

    if (product.variants.length == 1) {
      _variantId = product.variants.first.id;
    }
  }

  Future<void> _addToCart() async {
    if (product.requiresVariantSelection && _variantId == null) {
      _snack('Choose a color and size first.');
      return;
    }

    setState(() => _adding = true);

    try {
      await ref
          .read(cartProvider.notifier)
          .add(
            productId: product.id,
            quantity: _quantity,
            variantId: _variantId,
          );

      if (mounted) {
        _snack('Added to your cart.');
      }
    } on ApiException catch (error) {
      if (mounted) {
        _snack(error.friendly);
      }
    } finally {
      if (mounted) {
        setState(() => _adding = false);
      }
    }
  }

  Future<void> _openChat() async {
    setState(() => _chatting = true);

    try {
      final conversation = await ref
          .read(apiClientProvider)
          .startConversation(productId: product.id);

      if (mounted) {
        context.push('/conversations/${conversation.id}');
      }
    } on ApiException catch (error) {
      if (mounted) {
        _snack(error.friendly);
      }
    } finally {
      if (mounted) {
        setState(() => _chatting = false);
      }
    }
  }

  void _snack(String message) {
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final images = product.images.isEmpty
        ? (product.thumbnailUrl == null
              ? <ProductImage>[]
              : [ProductImage(url: product.thumbnailUrl!)])
        : product.images;

    return ListView(
      padding: const EdgeInsets.only(bottom: 32),
      children: [
        if (images.isEmpty)
          const AspectRatio(
            aspectRatio: 1,
            child: ColoredBox(
              color: Color(0xFFEDEAE2),
              child: Icon(Icons.image_outlined, size: 64),
            ),
          )
        else
          Stack(
            alignment: Alignment.bottomCenter,
            children: [
              AspectRatio(
                aspectRatio: 1,
                child: PageView.builder(
                  itemCount: images.length,
                  onPageChanged: (index) => setState(() => _imageIndex = index),
                  itemBuilder: (context, index) => NetworkThumb(
                    url: images[index].url,
                    width: double.infinity,
                    radius: 0,
                    cacheWidth: 1200,
                  ),
                ),
              ),
              if (images.length > 1)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: List.generate(
                      images.length,
                      (index) => AnimatedContainer(
                        duration: const Duration(milliseconds: 180),
                        margin: const EdgeInsets.symmetric(horizontal: 3),
                        width: index == _imageIndex ? 18 : 7,
                        height: 7,
                        decoration: BoxDecoration(
                          color: index == _imageIndex
                              ? theme.colorScheme.primary
                              : Colors.white70,
                          borderRadius: BorderRadius.circular(99),
                        ),
                      ),
                    ),
                  ),
                ),
            ],
          ),
        Padding(
          padding: const EdgeInsets.fromLTRB(18, 18, 18, 0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  if (product.brand != null && product.brand!.isNotEmpty)
                    Text(
                      product.brand!.toUpperCase(),
                      style: theme.textTheme.labelSmall?.copyWith(
                        letterSpacing: 1.1,
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                  const Spacer(),
                  if (product.freeShipping)
                    const Chip(
                      visualDensity: VisualDensity.compact,
                      label: Text('Free shipping'),
                    ),
                ],
              ),
              const SizedBox(height: 6),
              Text(
                product.name,
                style: theme.textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                  height: 1.2,
                ),
              ),
              const SizedBox(height: 10),
              Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    money(product.effectivePrice),
                    style: theme.textTheme.headlineSmall?.copyWith(
                      color: theme.colorScheme.primary,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  if (product.compareAtPrice != null &&
                      double.tryParse(product.compareAtPrice!) != null &&
                      (double.tryParse(product.compareAtPrice!) ?? 0) >
                          (double.tryParse(product.effectivePrice) ?? 0)) ...[
                    const SizedBox(width: 10),
                    Padding(
                      padding: const EdgeInsets.only(bottom: 3),
                      child: Text(
                        money(product.compareAtPrice),
                        style: theme.textTheme.bodyMedium?.copyWith(
                          decoration: TextDecoration.lineThrough,
                          color: theme.colorScheme.onSurfaceVariant,
                        ),
                      ),
                    ),
                  ],
                ],
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  _Stars(rating: product.ratingAverage),
                  const SizedBox(width: 8),
                  Text(
                    product.ratingCount == 0
                        ? 'No reviews yet'
                        : '${product.ratingAverage.toStringAsFixed(1)} · ${product.ratingCount} reviews',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                  const Spacer(),
                  if (product.soldQuantity > 0)
                    Text(
                      '${product.soldQuantity} sold',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 18),
              if (product.store != null)
                Card(
                  child: ListTile(
                    leading: CircleAvatar(
                      backgroundColor: theme.colorScheme.primaryContainer,
                      foregroundColor: theme.colorScheme.onPrimaryContainer,
                      child: Text(initials(product.store!.name)),
                    ),
                    title: Text(
                      product.store!.name,
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                    subtitle: Text(product.store!.city ?? 'Marketplace store'),
                  ),
                ),
              if (product.variants.isNotEmpty) ...[
                const SizedBox(height: 22),
                Text(
                  'Choose an option',
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: product.variants.map((variant) {
                    final selected = variant.id == _variantId;

                    return ChoiceChip(
                      label: Text(variant.label),
                      selected: selected,
                      onSelected: variant.inStock
                          ? (_) => setState(() {
                              _variantId = variant.id;
                              _quantity = _quantity < 1
                                  ? 1
                                  : (_quantity > _maxQuantity
                                        ? _maxQuantity
                                        : _quantity);
                            })
                          : null,
                    );
                  }).toList(),
                ),
                if (_variant != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Text(
                      _variant!.inStock
                          ? '${_variant!.stockQuantity} in stock'
                          : 'Sold out',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: _variant!.inStock
                            ? theme.colorScheme.onSurfaceVariant
                            : theme.colorScheme.error,
                      ),
                    ),
                  ),
              ],
              const SizedBox(height: 22),
              Row(
                children: [
                  Text(
                    'Quantity',
                    style: theme.textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const Spacer(),
                  QuantityStepper(
                    quantity: _quantity,
                    max: _maxQuantity,
                    onChanged: (value) => setState(() => _quantity = value),
                  ),
                ],
              ),
              const SizedBox(height: 20),
              FilledButton.icon(
                onPressed: _canAdd && !_adding ? _addToCart : null,
                icon: _adding
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.add_shopping_cart),
                label: Text(
                  !product.inStock || (_variant != null && !_variant!.inStock)
                      ? 'Out of stock'
                      : (product.requiresVariantSelection && _variantId == null
                            ? 'Choose an option'
                            : 'Add to cart'),
                ),
              ),
              const SizedBox(height: 10),
              OutlinedButton.icon(
                onPressed: _chatting ? null : _openChat,
                icon: _chatting
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.chat_bubble_outline_rounded),
                label: const Text('Chat with the store'),
              ),
              const SizedBox(height: 26),
              Text(
                'About this item',
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                (product.description == null ||
                        product.description!.trim().isEmpty)
                    ? 'The seller has not added a description yet.'
                    : product.description!,
                style: theme.textTheme.bodyMedium?.copyWith(height: 1.55),
              ),
              if (product.sku != null && product.sku!.isNotEmpty) ...[
                const SizedBox(height: 12),
                Text(
                  'SKU ${product.sku}',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
              ],
              if (product.reviewSummary != null &&
                  product.reviewSummary!.count > 0) ...[
                const SizedBox(height: 26),
                Text(
                  'Rating breakdown',
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 10),
                ...List.generate(5, (index) {
                  final star = 5 - index;
                  final count = product.reviewSummary!.distribution[star] ?? 0;
                  final ratio = product.reviewSummary!.count == 0
                      ? 0.0
                      : count / product.reviewSummary!.count;

                  return Padding(
                    padding: const EdgeInsets.only(bottom: 6),
                    child: Row(
                      children: [
                        SizedBox(width: 18, child: Text('$star')),
                        const Icon(
                          Icons.star_rounded,
                          size: 16,
                          color: Color(0xFFE0A63B),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: ClipRRect(
                            borderRadius: BorderRadius.circular(99),
                            child: LinearProgressIndicator(
                              value: ratio,
                              minHeight: 8,
                              backgroundColor:
                                  theme.colorScheme.surfaceContainerHighest,
                            ),
                          ),
                        ),
                        const SizedBox(width: 10),
                        SizedBox(
                          width: 28,
                          child: Text('$count', textAlign: TextAlign.end),
                        ),
                      ],
                    ),
                  );
                }),
              ],
              if (product.related.isNotEmpty) ...[
                const SizedBox(height: 26),
                Text(
                  'You may also like',
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 12),
                SizedBox(
                  height: 230,
                  child: ListView.separated(
                    scrollDirection: Axis.horizontal,
                    itemCount: product.related.length,
                    separatorBuilder: (context, index) =>
                        const SizedBox(width: 12),
                    itemBuilder: (context, index) {
                      final related = product.related[index];

                      return SizedBox(
                        width: 168,
                        child: ProductCard(
                          product: related,
                          onTap: () => context.push(
                            '/product/${related.store?.slug ?? ''}/${related.slug}',
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
      ],
    );
  }
}

class _Stars extends StatelessWidget {
  const _Stars({required this.rating});

  final double rating;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: List.generate(
        5,
        (index) => Icon(
          rating >= index + 1
              ? Icons.star_rounded
              : (rating >= index + 0.5
                    ? Icons.star_half_rounded
                    : Icons.star_outline_rounded),
          size: 18,
          color: const Color(0xFFE0A63B),
        ),
      ),
    );
  }
}
