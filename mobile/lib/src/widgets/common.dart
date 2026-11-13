import 'package:cached_network_image/cached_network_image.dart';
import 'package:clerk_flutter/clerk_flutter.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/api_exception.dart';
import '../core/formatters.dart';
import '../models/models.dart';
import '../providers/cart_providers.dart';
import '../providers/catalog_providers.dart';
import '../theme.dart';

class AsyncView<T> extends StatelessWidget {
  const AsyncView({
    super.key,
    required this.value,
    required this.builder,
    this.loading,
    this.onRetry,
  });

  final AsyncValue<T> value;
  final Widget Function(T value) builder;
  final Widget? loading;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return value.when(
      data: builder,
      loading: () =>
          loading ?? const Center(child: CircularProgressIndicator()),
      error: (error, stack) => ErrorView(error: error, onRetry: onRetry),
    );
  }
}

class ErrorView extends StatelessWidget {
  const ErrorView({super.key, required this.error, this.onRetry});

  final Object error;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    final apiError = error is ApiException ? error as ApiException : null;
    final message = apiError?.friendly ?? 'Something went wrong.';
    // The backend refused this Clerk session, so retrying cannot help: let the user switch accounts.
    final canSignOut =
        apiError != null &&
        (apiError.isUnauthenticated ||
            apiError.code == 'auth.email_unverified');

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.cloud_off_outlined,
              size: 44,
              color: Theme.of(context).colorScheme.error,
            ),
            const SizedBox(height: 14),
            Text(
              message,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            if (onRetry != null) ...[
              const SizedBox(height: 18),
              OutlinedButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh),
                label: const Text('Try again'),
              ),
            ],
            if (canSignOut) ...[
              const SizedBox(height: 10),
              TextButton.icon(
                onPressed: () async {
                  final router = GoRouter.of(context);
                  await ClerkAuth.of(context).signOut();
                  router.go('/');
                },
                icon: const Icon(Icons.logout_rounded),
                label: const Text('Sign out'),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class EmptyState extends StatelessWidget {
  const EmptyState({
    super.key,
    required this.icon,
    required this.title,
    this.message,
    this.action,
  });

  final IconData icon;
  final String title;
  final String? message;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              icon,
              size: 52,
              color: Theme.of(context).colorScheme.primary
                  .withValues(alpha: 0.7),
            ),
            const SizedBox(height: 16),
            Text(
              title,
              style: Theme.of(context).textTheme.titleMedium,
              textAlign: TextAlign.center,
            ),
            if (message != null) ...[
              const SizedBox(height: 6),
              Text(
                message!,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),
                textAlign: TextAlign.center,
              ),
            ],
            if (action != null) ...[const SizedBox(height: 20), action!],
          ],
        ),
      ),
    );
  }
}

/// A neutral placeholder block used while content is loading.
class SkeletonBox extends StatelessWidget {
  const SkeletonBox({super.key, this.width, this.height, this.radius = 6});

  final double? width;
  final double? height;
  final double radius;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: width,
      height: height,
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerHighest
            .withValues(alpha: 0.8),
        borderRadius: BorderRadius.circular(radius),
      ),
    );
  }
}

class NetworkThumb extends StatelessWidget {
  const NetworkThumb({
    super.key,
    required this.url,
    this.width,
    this.height,
    this.radius = appSurfaceRadius,
    this.fit = BoxFit.cover,
    this.cacheWidth,
    this.cacheKey,
    this.placeholderSize,
  });

  final String? url;
  final double? width;
  final double? height;
  final double radius;
  final BoxFit fit;
  final int? cacheWidth;

  /// Loading/error box size when [width]/[height] are left free so the image
  /// can size itself to its own aspect ratio.
  final Size? placeholderSize;

  /// Stable key for immutable media whose signed URL changes between fetches.
  final String? cacheKey;

  @override
  Widget build(BuildContext context) {
    final placeholder = Container(
      width: width ?? placeholderSize?.width,
      height: height ?? placeholderSize?.height,
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
      child: Icon(
        Icons.image_outlined,
        color: Theme.of(context).colorScheme.onSurfaceVariant,
      ),
    );

    if (url == null || url!.isEmpty) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(radius),
        child: placeholder,
      );
    }

    final memCacheWidth =
        cacheWidth ??
        (width != null && width!.isFinite
            ? (width! * MediaQuery.devicePixelRatioOf(context)).round()
            : null);

    return ClipRRect(
      borderRadius: BorderRadius.circular(radius),
      child: CachedNetworkImage(
        imageUrl: url!,
        cacheKey: cacheKey,
        useOldImageOnUrlChange: cacheKey != null,
        width: width,
        height: height,
        fit: fit,
        memCacheWidth: memCacheWidth,
        fadeInDuration: const Duration(milliseconds: 180),
        placeholder: (context, value) => placeholder,
        errorWidget: (context, value, error) => placeholder,
      ),
    );
  }
}

class StoreAvatar extends StatelessWidget {
  const StoreAvatar({
    super.key,
    required this.name,
    this.logoUrl,
    this.storeSlug,
    this.radius = 24,
    this.backgroundColor,
    this.foregroundColor,
    this.square = false,
  });

  final String name;
  final String? logoUrl;

  /// Resolves photos for compact responses that only include a store slug.
  final String? storeSlug;
  final double radius;

  /// Draws a rounded square instead of a circle, as store headers use.
  final bool square;
  final Color? backgroundColor;
  final Color? foregroundColor;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final slug = storeSlug?.trim();
    final imageUrl = logoUrl?.trim();
    if ((imageUrl == null || imageUrl.isEmpty) &&
        slug != null &&
        slug.isNotEmpty) {
      return Consumer(
        builder: (context, ref, child) => StoreAvatar(
          name: name,
          logoUrl: ref.watch(storeProvider(slug)).value?.logoUrl,
          radius: radius,
          square: square,
          backgroundColor: backgroundColor,
          foregroundColor: foregroundColor,
        ),
      );
    }
    final shape = square
        ? RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(radius / 3),
          )
        : const CircleBorder();
    final fallback = Container(
      width: radius * 2,
      height: radius * 2,
      alignment: Alignment.center,
      decoration: ShapeDecoration(
        shape: shape,
        color: backgroundColor ?? scheme.primaryContainer,
      ),
      child: Text(
        initials(name),
        style: TextStyle(
          color: foregroundColor ?? scheme.onPrimaryContainer,
          fontSize: radius * 0.62,
          fontWeight: FontWeight.w800,
        ),
      ),
    );

    if (imageUrl == null || imageUrl.isEmpty) {
      return fallback;
    }

    return ClipPath(
      clipper: ShapeBorderClipper(shape: shape),
      child: CachedNetworkImage(
        imageUrl: imageUrl,
        width: radius * 2,
        height: radius * 2,
        fit: BoxFit.cover,
        memCacheWidth: (radius * 2 * MediaQuery.devicePixelRatioOf(context))
            .round(),
        fadeInDuration: const Duration(milliseconds: 180),
        placeholder: (context, value) => fallback,
        errorWidget: (context, value, error) => fallback,
      ),
    );
  }
}

class ProductCard extends StatelessWidget {
  const ProductCard({
    super.key,
    required this.product,
    required this.onTap,
    this.onStoreTap,
    this.showStore = true,
    this.discovery = false,
    this.showAddToCart = false,
  });

  final Product product;
  final VoidCallback onTap;
  final VoidCallback? onStoreTap;
  final bool showStore;
  final bool discovery;
  final bool showAddToCart;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final titleStyle = theme.textTheme.bodyMedium?.copyWith(
      fontSize: discovery ? 13 : null,
      fontWeight: discovery ? FontWeight.w500 : FontWeight.w700,
      height: 1.3,
    );
    final discount = discountPercent(
      product.effectivePrice,
      product.compareAtPrice,
    );
    final onSale = discount > 0;
    final salePriceStyle = discovery
        ? theme.textTheme.titleMedium?.copyWith(
            fontSize: 17,
            fontWeight: FontWeight.w800,
            color: onSale ? theme.colorScheme.error : theme.colorScheme.primary,
            letterSpacing: -0.4,
            height: 1.15,
          )
        : theme.textTheme.titleSmall?.copyWith(
            fontWeight: FontWeight.w800,
            color: onSale ? theme.colorScheme.error : theme.colorScheme.primary,
            letterSpacing: -0.3,
          );
    // Lazada-style stack: the sale price leads, the old price sits struck below.
    final priceColumn = Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        FittedBox(
          fit: BoxFit.scaleDown,
          alignment: Alignment.centerLeft,
          child: Text(money(product.effectivePrice), style: salePriceStyle),
        ),
        if (onSale) ...[
          const SizedBox(height: 1),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              money(product.compareAtPrice),
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
                decoration: TextDecoration.lineThrough,
                decorationColor: theme.colorScheme.onSurfaceVariant,
                decorationThickness: 1.5,
                fontFeatures: const [FontFeature.tabularFigures()],
              ),
            ),
          ),
        ],
      ],
    );
    final price = discovery
        ? priceColumn
        : Row(
            children: [
              Expanded(child: priceColumn),
              if (product.ratingCount > 0) ...[
                const SizedBox(width: 8),
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      Icons.star_rounded,
                      size: 15,
                      color: theme.colorScheme.secondary,
                    ),
                    const SizedBox(width: 2),
                    Text(
                      product.ratingAverage.toStringAsFixed(1),
                      style: theme.textTheme.bodySmall,
                    ),
                  ],
                ),
              ],
            ],
          );

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Padding(
                padding: EdgeInsets.zero,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    NetworkThumb(
                      url: product.thumbnailUrl,
                      width: double.infinity,
                      radius: 0,
                      cacheWidth: 600,
                    ),
                    if (onSale)
                      Positioned(
                        right: 8,
                        top: 8,
                        child: _FeedDiscountBadge(percent: discount),
                      ),
                    if (discovery)
                      Positioned(
                        left: 0,
                        bottom: 0,
                        child: _FeedBadges(product: product),
                      ),
                    if (discovery && !product.inStock)
                      Positioned(
                        left: 8,
                        top: 8,
                        child: DecoratedBox(
                          decoration: BoxDecoration(
                            color: theme.colorScheme.surfaceContainerLowest,
                            borderRadius: BorderRadius.circular(
                              appSurfaceRadius,
                            ),
                          ),
                          child: Padding(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 4,
                            ),
                            child: Text(
                              'Sold out',
                              style: theme.textTheme.labelSmall?.copyWith(
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ),
            Padding(
              padding: discovery
                  ? const EdgeInsets.fromLTRB(8, 8, 8, 0)
                  : const EdgeInsets.fromLTRB(12, 10, 12, 12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    product.name,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: titleStyle,
                  ),
                  if (discovery) ...[
                    const SizedBox(height: 4),
                    price,
                    const SizedBox(height: 4),
                    Semantics(
                      label: product.ratingCount > 0
                          ? 'Rated ${product.ratingAverage.toStringAsFixed(1)} out of 5, ${product.ratingCount} reviews'
                          : 'No reviews yet',
                      excludeSemantics: true,
                      child: Row(
                        children: [
                          Icon(
                            product.ratingCount > 0
                                ? Icons.star_rounded
                                : Icons.star_outline_rounded,
                            size: 16,
                            color: product.ratingCount > 0
                                ? theme.colorScheme.secondary
                                : theme.colorScheme.onSurfaceVariant,
                          ),
                          const SizedBox(width: 4),
                          Expanded(
                            child: Text(
                              [
                                product.ratingCount > 0
                                    ? '${product.ratingAverage.toStringAsFixed(1)} (${product.ratingCount})'
                                    : 'No reviews yet',
                                if (product.soldQuantity > 0)
                                  '${_compactCount(product.soldQuantity)} sold',
                              ].join(' · '),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: theme.colorScheme.onSurfaceVariant,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ] else
                    const SizedBox(height: 4),
                  if (showStore && product.store != null)
                    InkWell(
                      onTap: onStoreTap,
                      borderRadius: BorderRadius.circular(appSurfaceRadius),
                      child: ConstrainedBox(
                        constraints: const BoxConstraints(minHeight: 48),
                        child: Padding(
                          padding: discovery
                              ? const EdgeInsets.only(top: 4)
                              : EdgeInsets.zero,
                          child: Row(
                            crossAxisAlignment: discovery
                                ? CrossAxisAlignment.start
                                : CrossAxisAlignment.center,
                            children: [
                              StoreAvatar(
                                name: product.store!.name,
                                logoUrl: product.store!.logoUrl,
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
                              if (discovery && onStoreTap != null) ...[
                                const SizedBox(width: 4),
                                Icon(
                                  Icons.chevron_right_rounded,
                                  size: 16,
                                  color: theme.colorScheme.onSurfaceVariant,
                                ),
                              ],
                            ],
                          ),
                        ),
                      ),
                    ),
                  if (!discovery) ...[const SizedBox(height: 8), price],
                  if (showAddToCart) ...[
                    const SizedBox(height: 10),
                    _ProductCartButton(
                      product: product,
                      onChooseOptions: onTap,
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

String _compactCount(int count) {
  if (count < 1000) {
    return '$count';
  }

  final thousands = count / 1000;
  final value = thousands >= 10
      ? thousands.toStringAsFixed(0)
      : thousands.toStringAsFixed(1);

  return '${value.endsWith('.0') ? value.substring(0, value.length - 2) : value}k';
}

/// The discount flag on the top-right of a product photo.
class _FeedDiscountBadge extends StatelessWidget {
  const _FeedDiscountBadge({required this.percent});

  final int percent;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return DecoratedBox(
      decoration: BoxDecoration(
        color: theme.colorScheme.errorContainer,
        borderRadius: BorderRadius.circular(appSurfaceRadius),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
        child: Text(
          '-$percent%',
          style: theme.textTheme.labelSmall?.copyWith(
            color: theme.colorScheme.onErrorContainer,
            fontWeight: FontWeight.w800,
            height: 1.1,
          ),
        ),
      ),
    );
  }
}

/// The tri-colour benefit band marketplace feeds use at the foot of a photo.
class _FeedBadges extends StatelessWidget {
  const _FeedBadges({required this.product});

  final Product product;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return MediaQuery.withClampedTextScaling(
      maxScaleFactor: 1.2,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (product.freeShipping)
            _FeedBadge(
              label: 'FREE SHIPPING',
              background: scheme.primary,
              foreground: scheme.onPrimary,
            ),
          _FeedBadge(
            label: 'COD',
            background: const Color(0xFFB45309),
            foreground: Colors.white,
          ),
        ],
      ),
    );
  }
}

class _FeedBadge extends StatelessWidget {
  const _FeedBadge({
    required this.label,
    required this.background,
    required this.foreground,
  });

  final String label;
  final Color background;
  final Color foreground;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(color: background),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 3),
        child: Text(
          label,
          maxLines: 1,
          style: TextStyle(
            color: foreground,
            fontSize: 8.5,
            height: 1.1,
            fontWeight: FontWeight.w800,
            letterSpacing: 0.1,
          ),
        ),
      ),
    );
  }
}

class _ProductCartButton extends ConsumerStatefulWidget {
  const _ProductCartButton({
    required this.product,
    required this.onChooseOptions,
  });

  final Product product;
  final VoidCallback onChooseOptions;

  @override
  ConsumerState<_ProductCartButton> createState() => _ProductCartButtonState();
}

class _ProductCartButtonState extends ConsumerState<_ProductCartButton> {
  bool _adding = false;

  Future<void> _add() async {
    if (_adding) {
      return;
    }
    setState(() => _adding = true);
    try {
      await ref.read(cartProvider.notifier).add(productId: widget.product.id);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('${widget.product.name} added to cart.')),
        );
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.friendly)));
      }
    } finally {
      if (mounted) {
        setState(() => _adding = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final product = widget.product;
    final chooseOptions =
        product.hasVariants || product.requiresVariantSelection;
    return SizedBox(
      width: double.infinity,
      child: FilledButton(
        style: FilledButton.styleFrom(
          minimumSize: const Size(48, 48),
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 12),
          textStyle: Theme.of(context).textTheme.labelLarge,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(appSurfaceRadius),
          ),
        ),
        onPressed: !product.inStock || _adding
            ? null
            : chooseOptions
            ? widget.onChooseOptions
            : _add,
        child: _adding
            ? SizedBox.square(
                dimension: 20,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  color: Theme.of(context).colorScheme.onPrimary,
                  semanticsLabel: 'Adding ${product.name} to cart',
                ),
              )
            : Text(
                !product.inStock
                    ? 'Sold out'
                    : chooseOptions
                    ? 'Choose options'
                    : 'Add to cart',
                textAlign: TextAlign.center,
              ),
      ),
    );
  }
}

class StatusPill extends StatelessWidget {
  const StatusPill({super.key, required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final color = statusColour(context, status);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(
          alpha: Theme.of(context).brightness == Brightness.dark ? 0.2 : 0.12,
        ),
        borderRadius: BorderRadius.circular(appSurfaceRadius),
      ),
      child: Text(
        statusLabel(status),
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class SectionTitle extends StatelessWidget {
  const SectionTitle({super.key, required this.title, this.trailing});

  final String title;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 20, 16, 10),
      child: Wrap(
        alignment: WrapAlignment.spaceBetween,
        crossAxisAlignment: WrapCrossAlignment.center,
        spacing: 8,
        runSpacing: 4,
        children: [
          Text(
            title,
            style: Theme.of(context).textTheme.titleMedium
                ?.copyWith(fontWeight: FontWeight.w800),
          ),
          ?trailing,
        ],
      ),
    );
  }
}

class QuantityStepper extends StatelessWidget {
  const QuantityStepper({
    super.key,
    required this.quantity,
    required this.onChanged,
    this.max = 100,
  });

  final int quantity;
  final ValueChanged<int> onChanged;
  final int max;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
        borderRadius: BorderRadius.circular(appSurfaceRadius),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            tooltip: 'Decrease quantity',
            visualDensity: VisualDensity.compact,
            onPressed: quantity > 1 ? () => onChanged(quantity - 1) : null,
            icon: const Icon(Icons.remove, size: 18),
          ),
          Semantics(
            liveRegion: true,
            label: 'Quantity $quantity',
            child: ExcludeSemantics(
              child: Text(
                '$quantity',
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
          ),
          IconButton(
            tooltip: 'Increase quantity',
            visualDensity: VisualDensity.compact,
            onPressed: quantity < max ? () => onChanged(quantity + 1) : null,
            icon: const Icon(Icons.add, size: 18),
          ),
        ],
      ),
    );
  }
}
