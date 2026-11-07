import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/api_exception.dart';
import '../core/formatters.dart';
import '../models/models.dart';
import '../providers/auth_providers.dart';
import '../providers/cart_providers.dart';
import '../providers/catalog_providers.dart';
import '../providers/checkout_providers.dart';
import '../widgets/common.dart';

class ProductScreen extends ConsumerWidget {
  const ProductScreen({super.key, required this.productKey});

  final ProductKey productKey;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final product = ref.watch(productProvider(productKey));

    return product.when(
      data: (value) => _ProductBody(product: value, productKey: productKey),
      loading: () => Scaffold(
        appBar: AppBar(title: const Text('Product')),
        body: const Center(child: CircularProgressIndicator()),
      ),
      error: (error, _) => Scaffold(
        appBar: AppBar(title: const Text('Product')),
        body: ErrorView(
          error: error,
          onRetry: () => ref.invalidate(productProvider(productKey)),
        ),
      ),
    );
  }
}

class _ProductBody extends ConsumerStatefulWidget {
  const _ProductBody({required this.product, required this.productKey});

  final Product product;
  final ProductKey productKey;

  @override
  ConsumerState<_ProductBody> createState() => _ProductBodyState();
}

class _ProductBodyState extends ConsumerState<_ProductBody> {
  String? _variantId;
  int _quantity = 1;
  bool _adding = false;
  bool _buying = false;
  final _optionsKey = GlobalKey();
  bool _chatting = false;
  int _imageIndex = 0;
  Key _galleryKey = UniqueKey();

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

  String get _selectedColor => _variant?.color?.trim().toLowerCase() ?? '';

  List<ProductImage> get _galleryImages {
    final images = product.images.isEmpty
        ? (product.thumbnailUrl == null
              ? <ProductImage>[]
              : [ProductImage(url: product.thumbnailUrl!)])
        : product.images;
    if (_selectedColor.isEmpty) {
      return images;
    }
    final matching = images.where(
      (image) => image.color?.trim().toLowerCase() == _selectedColor,
    );
    return [
      ...matching,
      ...images.where(
        (image) => image.color?.trim().toLowerCase() != _selectedColor,
      ),
    ];
  }

  void _selectVariant(ProductVariant variant) {
    setState(() {
      _variantId = variant.id;
      _quantity = _quantity.clamp(1, _maxQuantity);
      _imageIndex = 0;
      _galleryKey = UniqueKey();
    });
  }

  @override
  void initState() {
    super.initState();

    if (product.variants.length == 1) {
      _variantId = product.variants.first.id;
    }
  }

  Future<void> _addToCart({bool buyNow = false}) async {
    if (_adding ||
        !product.inStock ||
        (_variant != null && !_variant!.inStock)) {
      return;
    }
    if (product.requiresVariantSelection && _variantId == null) {
      final options = _optionsKey.currentContext;
      if (options != null) {
        Scrollable.ensureVisible(
          options,
          alignment: 0,
          duration: MediaQuery.disableAnimationsOf(context)
              ? Duration.zero
              : const Duration(milliseconds: 250),
        );
      }
      _snack('Choose a color and size first.');
      return;
    }

    setState(() {
      _adding = true;
      _buying = buyNow;
    });
    var added = false;

    try {
      await ref
          .read(cartProvider.notifier)
          .add(
            productId: product.id,
            quantity: _quantity,
            variantId: _variantId,
          );

      added = true;
      if (!mounted) {
        return;
      }
      if (buyNow) {
        final lines = ref
            .read(cartProvider)
            .value
            ?.lines
            .where(
              (line) =>
                  line.productId == product.id && line.variantId == _variantId,
            );
        if (lines == null || lines.isEmpty) {
          _snack('Added to your cart. Open Cart to continue checkout.');
          return;
        }
        await ref.read(cartProvider.notifier).selectOnly([lines.first.id]);
        if (!mounted) {
          return;
        }
        ref.invalidate(checkoutQuoteProvider);
        context.push('/checkout');
      } else {
        _snack('Added to your cart.');
      }
    } on ApiException catch (error) {
      if (mounted) {
        _snack(
          added && buyNow
              ? 'Added to cart, but checkout could not open. ${error.friendly}'
              : error.friendly,
        );
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
        context.push(
          '/conversations/${conversation.id}?product_id=${product.id}',
        );
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

  Widget _purchaseBar(BuildContext context) {
    final theme = Theme.of(context);
    final available = product.inStock && (_variant?.inStock ?? true);
    final enabled = available && !_adding;
    final largeText = MediaQuery.textScalerOf(context).scale(14) > 17;

    Widget shortcut(String label, IconData icon, VoidCallback onTap) =>
        SizedBox(
          width: largeText ? 88 : 52,
          child: TextButton(
            style: TextButton.styleFrom(
              minimumSize: const Size(48, 56),
              padding: const EdgeInsets.all(4),
            ),
            onPressed: _adding || (label == 'Chat' && _chatting) ? null : onTap,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(icon, size: 22),
                const SizedBox(height: 4),
                Text(label, style: theme.textTheme.labelSmall),
              ],
            ),
          ),
        );

    final shortcuts = Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        shortcut(
          'Store',
          Icons.storefront_outlined,
          () => context.push(
            '/store/${product.store?.slug ?? widget.productKey.store}',
          ),
        ),
        shortcut('Chat', Icons.chat_bubble_outline_rounded, _openChat),
      ],
    );
    final actions = Row(
      children: [
        IconButton.filledTonal(
          tooltip: 'Add to cart',
          style: IconButton.styleFrom(
            minimumSize: const Size(52, 52),
            backgroundColor: theme.colorScheme.primaryContainer,
            foregroundColor: theme.colorScheme.onPrimaryContainer,
          ),
          onPressed: enabled ? () => _addToCart() : null,
          icon: _adding && !_buying
              ? const SizedBox.square(
                  dimension: 20,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    semanticsLabel: 'Adding to cart',
                  ),
                )
              : const Icon(Icons.add_shopping_cart_rounded),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: FilledButton(
            style: FilledButton.styleFrom(
              minimumSize: const Size(48, 48),
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 12),
              textStyle: theme.textTheme.labelLarge,
              disabledBackgroundColor: _adding
                  ? theme.colorScheme.primary
                  : null,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(10),
              ),
            ),
            onPressed: enabled ? () => _addToCart(buyNow: true) : null,
            child: _adding && _buying
                ? SizedBox.square(
                    dimension: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: theme.colorScheme.onPrimary,
                      semanticsLabel: 'Opening checkout',
                    ),
                  )
                : Text(
                    available ? 'Buy now' : 'Sold out',
                    textAlign: TextAlign.center,
                  ),
          ),
        ),
      ],
    );
    return Material(
      color: theme.colorScheme.surfaceContainerLowest,
      elevation: 4,
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
          child: LayoutBuilder(
            builder: (context, constraints) {
              if (constraints.maxWidth < 336 || largeText) {
                return Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [shortcuts, const SizedBox(height: 8), actions],
                );
              }
              return Row(
                children: [
                  shortcuts,
                  const SizedBox(width: 8),
                  Expanded(child: actions),
                ],
              );
            },
          ),
        ),
      ),
    );
  }

  Widget _selectionFrame(
    BuildContext context,
    BoxConstraints viewport,
    List<ProductImage> images,
  ) {
    final theme = Theme.of(context);
    final largeText = MediaQuery.textScalerOf(context).scale(14) > 21;
    final galleryHeight = math
        .min(viewport.maxWidth, viewport.maxHeight * (largeText ? 0.28 : 0.5))
        .clamp(140.0, 420.0);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (images.isEmpty)
          SizedBox(
            height: galleryHeight,
            child: ColoredBox(
              color: theme.colorScheme.surfaceContainerHighest,
              child: const Icon(Icons.image_outlined, size: 64),
            ),
          )
        else
          Stack(
            children: [
              ColoredBox(
                color: theme.colorScheme.surfaceContainerLowest,
                child: SizedBox(
                  height: galleryHeight,
                  child: PageView.builder(
                    key: _galleryKey,
                    itemCount: images.length,
                    onPageChanged: (index) =>
                        setState(() => _imageIndex = index),
                    itemBuilder: (context, index) => NetworkThumb(
                      url: images[index].url,
                      width: double.infinity,
                      radius: 0,
                      fit: BoxFit.contain,
                      cacheWidth: 1200,
                    ),
                  ),
                ),
              ),
              if (images.length > 1)
                Positioned(
                  right: 16,
                  bottom: 16,
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                      color: theme.colorScheme.inverseSurface.withValues(
                        alpha: 0.82,
                      ),
                      borderRadius: BorderRadius.circular(99),
                    ),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 5,
                      ),
                      child: Text(
                        '${_imageIndex + 1}/${images.length}',
                        style: theme.textTheme.labelSmall?.copyWith(
                          color: theme.colorScheme.onInverseSurface,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ),
                ),
            ],
          ),
      ],
    );
  }

  Widget _variantOptions(BuildContext context) {
    final theme = Theme.of(context);
    final largeText = MediaQuery.textScalerOf(context).scale(14) > 21;
    final choices = Wrap(
      spacing: 8,
      runSpacing: 8,
      children: product.variants.map((variant) {
        final selected = variant.id == _variantId;

        return ChoiceChip(
          label: Text(variant.label),
          selected: selected,
          onSelected: variant.inStock && !_adding
              ? (_) => _selectVariant(variant)
              : null,
        );
      }).toList(),
    );
    return Container(
      key: _optionsKey,
      color: theme.colorScheme.surfaceContainerLowest,
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Select options',
            style: theme.textTheme.titleSmall?.copyWith(
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 10),
          if (largeText)
            choices
          else
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: choices,
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
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final count = ref.watch(cartCountProvider);
    final images = _galleryImages;
    final effectivePrice = _variant == null
        ? product.effectivePrice
        : (_variant!.price ?? product.price);
    final sku = _variant?.sku ?? product.sku;

    return Scaffold(
      appBar: AppBar(
        title: Text(product.name, maxLines: 1, overflow: TextOverflow.ellipsis),
        actions: [
          IconButton(
            tooltip: 'Open cart',
            onPressed: () => context.push('/cart'),
            icon: Semantics(
              liveRegion: true,
              label: '$count items in cart',
              child: Badge(
                isLabelVisible: count > 0,
                label: ExcludeSemantics(
                  child: Text(count > 99 ? '99+' : '$count'),
                ),
                child: const Icon(Icons.shopping_cart_outlined),
              ),
            ),
          ),
        ],
      ),
      bottomNavigationBar: _purchaseBar(context),
      body: LayoutBuilder(
        builder: (context, viewport) => ListView(
          padding: const EdgeInsets.only(bottom: 32),
          children: [
            _selectionFrame(context, viewport, images),
            Padding(
              padding: const EdgeInsets.fromLTRB(18, 16, 18, 0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Wrap(
                    spacing: 8,
                    runSpacing: 4,
                    crossAxisAlignment: WrapCrossAlignment.end,
                    children: [
                      Text(
                        money(effectivePrice),
                        style: theme.textTheme.headlineMedium?.copyWith(
                          color: theme.colorScheme.primary,
                          fontWeight: FontWeight.w800,
                          letterSpacing: -0.8,
                        ),
                      ),
                      if (product.compareAtPrice != null &&
                          double.tryParse(product.compareAtPrice!) != null &&
                          (double.tryParse(product.compareAtPrice!) ?? 0) >
                              (double.tryParse(effectivePrice) ?? 0)) ...[
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
                  Text(
                    product.name,
                    style: theme.textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w700,
                      height: 1.25,
                    ),
                  ),
                  if (product.brand != null && product.brand!.isNotEmpty) ...[
                    const SizedBox(height: 4),
                    Text(
                      product.brand!,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      if (product.ratingCount > 0)
                        Icon(
                          Icons.star_rounded,
                          color: theme.colorScheme.secondary,
                          size: 18,
                        ),
                      Text(
                        product.ratingCount == 0
                            ? 'No reviews yet'
                            : '${product.ratingAverage.toStringAsFixed(1)} (${product.ratingCount} reviews)',
                        style: theme.textTheme.bodySmall?.copyWith(
                          fontWeight: product.ratingCount > 0
                              ? FontWeight.w700
                              : FontWeight.w400,
                        ),
                      ),
                      if (product.soldQuantity > 0)
                        Text(
                          '  ·  ${product.soldQuantity} sold',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant,
                          ),
                        ),
                    ],
                  ),
                  if (product.freeShipping) ...[
                    const SizedBox(height: 14),
                    Row(
                      children: [
                        Icon(
                          Icons.local_shipping_outlined,
                          size: 20,
                          color: theme.colorScheme.primary,
                        ),
                        const SizedBox(width: 8),
                        Text(
                          'Free shipping',
                          style: theme.textTheme.bodyMedium?.copyWith(
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ],
                  const SizedBox(height: 18),
                  if (product.variants.isNotEmpty) ...[
                    _variantOptions(context),
                    const SizedBox(height: 16),
                  ],
                  if (product.store != null)
                    Card(
                      child: ListTile(
                        leading: StoreAvatar(
                          name: product.store!.name,
                          logoUrl: product.store!.logoUrl,
                        ),
                        title: Text(
                          product.store!.name,
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                        subtitle: Text(
                          product.store!.city ?? 'Marketplace store',
                        ),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: () =>
                            context.push('/store/${product.store!.slug}'),
                      ),
                    ),
                  const SizedBox(height: 22),
                  Wrap(
                    alignment: WrapAlignment.spaceBetween,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    spacing: 12,
                    runSpacing: 8,
                    children: [
                      Text(
                        'Quantity',
                        style: theme.textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      QuantityStepper(
                        quantity: _quantity,
                        max: _maxQuantity,
                        onChanged: (value) => setState(() => _quantity = value),
                      ),
                    ],
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
                  if (sku != null && sku.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    Text(
                      'SKU $sku',
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
                      final count =
                          product.reviewSummary!.distribution[star] ?? 0;
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
                  const SizedBox(height: 26),
                  _ProductReviews(productKey: widget.productKey),
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
                              onStoreTap: related.store == null
                                  ? null
                                  : () => context.push(
                                      '/store/${related.store!.slug}',
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
        ),
      ),
    );
  }
}

class _ProductReviews extends ConsumerStatefulWidget {
  const _ProductReviews({required this.productKey});

  final ProductKey productKey;

  @override
  ConsumerState<_ProductReviews> createState() => _ProductReviewsState();
}

class _ProductReviewsState extends ConsumerState<_ProductReviews> {
  int _page = 1;
  final _headingKey = GlobalKey();

  void _changePage(int page) {
    setState(() => _page = page);
    Scrollable.ensureVisible(
      _headingKey.currentContext!,
      duration: MediaQuery.disableAnimationsOf(context)
          ? Duration.zero
          : const Duration(milliseconds: 200),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final provider = productReviewsProvider((
      product: widget.productKey,
      page: _page,
    ));
    final reviews = ref.watch(provider);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Customer reviews',
          key: _headingKey,
          style: theme.textTheme.titleMedium?.copyWith(
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 12),
        reviews.when(
          loading: () => const Padding(
            padding: EdgeInsets.all(20),
            child: Center(
              child: CircularProgressIndicator(
                semanticsLabel: 'Loading reviews',
              ),
            ),
          ),
          error: (_, _) => Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Could not load customer reviews.'),
              TextButton(
                onPressed: () => ref.invalidate(provider),
                child: const Text('Retry reviews'),
              ),
            ],
          ),
          data: (value) => Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (value.reviews.isEmpty) const Text('No reviews yet.'),
              for (final review in value.reviews) ...[
                Text(
                  review.author.trim().isEmpty ? 'Customer' : review.author,
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 4),
                Semantics(
                  label: '${review.rating} out of 5 stars',
                  child: ExcludeSemantics(
                    child: _Stars(rating: review.rating.toDouble()),
                  ),
                ),
                const SizedBox(height: 4),
                Wrap(
                  spacing: 12,
                  runSpacing: 4,
                  children: [
                    if (review.verifiedPurchase)
                      Text(
                        'Verified purchase',
                        style: theme.textTheme.labelMedium?.copyWith(
                          color: theme.colorScheme.primary,
                        ),
                      ),
                    if (shortDate(review.createdAt).isNotEmpty)
                      Text(
                        shortDate(review.createdAt),
                        style: theme.textTheme.bodySmall,
                      ),
                  ],
                ),
                if (review.title.trim().isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Text(
                    review.title,
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                ],
                if (review.body.trim().isNotEmpty) ...[
                  const SizedBox(height: 6),
                  Text(
                    review.body,
                    style: theme.textTheme.bodyMedium?.copyWith(height: 1.5),
                  ),
                ],
                const Divider(height: 32),
              ],
              if (value.lastPage > 1)
                Wrap(
                  spacing: 8,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    TextButton(
                      onPressed: value.page > 1
                          ? () => _changePage(value.page - 1)
                          : null,
                      child: const Text('Previous reviews'),
                    ),
                    Text('Page ${value.page} of ${value.lastPage}'),
                    TextButton(
                      onPressed: value.page < value.lastPage
                          ? () => _changePage(value.page + 1)
                          : null,
                      child: const Text('Next reviews'),
                    ),
                  ],
                ),
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
