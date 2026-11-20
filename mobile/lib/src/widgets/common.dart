import 'package:cached_network_image/cached_network_image.dart';
import 'package:clerk_flutter/clerk_flutter.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:phosphor_icons/phosphor_icons.dart';

import '../core/api_exception.dart';
import '../core/formatters.dart';
import '../core/push_notifications.dart';
import '../models/models.dart';
import '../providers/auth_providers.dart';
import '../providers/cart_providers.dart';
import '../providers/catalog_providers.dart';
import '../providers/store_locator_providers.dart';
import '../screens/auth_gate.dart';
import '../theme.dart';
import 'store_locator.dart';

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
            _IconMedallion(
              icon: PhosphorIconsRegular.cloudSlash,
              background: Theme.of(context).colorScheme.errorContainer,
              foreground: Theme.of(context).colorScheme.error,
            ),
            const SizedBox(height: 18),
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
              Consumer(
                builder: (context, ref, _) => TextButton.icon(
                  onPressed: () async {
                    final router = GoRouter.of(context);
                    final auth = ClerkAuth.of(context);
                    // Best-effort, as on the account screen: this phone stops getting the account's pushes.
                    await PushNotifications.unregister(
                      ref.read(apiClientProvider),
                    );
                    await auth.signOut();
                    router.go('/');
                  },
                  icon: const Icon(Icons.logout_rounded),
                  label: const Text('Sign out'),
                ),
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
            _IconMedallion(
              icon: icon,
              background: Theme.of(context).colorScheme.primaryContainer,
              foreground: Theme.of(context).colorScheme.primary,
            ),
            const SizedBox(height: 20),
            Text(
              title,
              style: Theme.of(context).textTheme.titleLarge,
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

/// A soft round field holding the icon of an empty or error state.
class _IconMedallion extends StatelessWidget {
  const _IconMedallion({
    required this.icon,
    required this.background,
    required this.foreground,
  });

  final IconData icon;
  final Color background;
  final Color foreground;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 88,
      height: 88,
      decoration: BoxDecoration(color: background, shape: BoxShape.circle),
      child: Icon(icon, size: 38, color: foreground),
    );
  }
}

/// A neutral placeholder block used while content is loading.
class SkeletonBox extends StatelessWidget {
  const SkeletonBox({
    super.key,
    this.width,
    this.height,
    this.radius = appDetailRadius,
  });

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
    this.radius = appDetailRadius,
    this.fit = BoxFit.cover,
    this.cacheWidth,
    this.cacheKey,
    this.placeholderSize,
    this.placeholderIcon = PhosphorIconsRegular.image,
    this.errorLabel,
  });

  /// Drawn on the soft stage while the photo loads or when there is none.
  final IconData placeholderIcon;

  /// Said under a broken-image icon when the photo fails to load, so a lost
  /// file reads as lost instead of as a photo that is still loading.
  final String? errorLabel;

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
    final scheme = Theme.of(context).colorScheme;
    final boxWidth = width ?? placeholderSize?.width;
    final boxHeight = height ?? placeholderSize?.height;
    final knownSides = [
      boxWidth,
      boxHeight,
    ].whereType<double>().where((side) => side.isFinite);
    final iconSize = knownSides.isEmpty
        ? 36.0
        : (knownSides.reduce((a, b) => a < b ? a : b) * 0.3).clamp(16.0, 44.0);
    final placeholder = Container(
      width: boxWidth,
      height: boxHeight,
      color: scheme.surfaceContainer,
      child: Icon(
        placeholderIcon,
        size: iconSize,
        color: scheme.primary.withValues(alpha: 0.34),
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
        errorWidget: (context, value, error) => errorLabel == null
            ? placeholder
            : Container(
                width: boxWidth,
                height: boxHeight,
                color: scheme.surfaceContainer,
                padding: const EdgeInsets.all(12),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(
                      Icons.broken_image_outlined,
                      size: iconSize,
                      color: scheme.onSurfaceVariant,
                    ),
                    const SizedBox(height: 6),
                    Text(
                      errorLabel!,
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.bodySmall
                          ?.copyWith(color: scheme.onSurfaceVariant),
                    ),
                  ],
                ),
              ),
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
            borderRadius: BorderRadius.circular(appDetailRadius),
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
    this.discovery = false,
    this.showAddToCart = false,
    this.fillHeight = false,
  });

  final Product product;
  final VoidCallback onTap;
  final bool discovery;
  final bool showAddToCart;

  /// Stretch to the row's height with the cart button pinned to the bottom,
  /// so cards side by side line up.
  final bool fillHeight;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final titleStyle = theme.textTheme.bodyMedium?.copyWith(
      fontSize: discovery ? 13.5 : null,
      fontWeight: FontWeight.w600,
      height: 1.3,
    );
    final discount = discountPercent(
      product.effectivePrice,
      product.compareAtPrice,
    );
    final onSale = discount > 0;
    // Regular prices sit in ink like a printed label; only a sale earns colour.
    final priceColour = onSale
        ? theme.colorScheme.error
        : theme.colorScheme.onSurface;
    final salePriceStyle = discovery
        ? theme.textTheme.titleMedium?.copyWith(
            fontSize: 17,
            fontWeight: FontWeight.w800,
            color: priceColour,
            letterSpacing: -0.4,
            height: 1.15,
          )
        : theme.textTheme.titleSmall?.copyWith(
            fontWeight: FontWeight.w800,
            color: priceColour,
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

    final photo = ColoredBox(
      // Product photos are shot on white; one white plate keeps them even in dark mode.
      color: Colors.white,
      child: Stack(
        fit: StackFit.expand,
        children: [
          NetworkThumb(
            url: product.thumbnailUrl,
            width: double.infinity,
            radius: 0,
            cacheWidth: 600,
            placeholderIcon: PhosphorIconsRegular.pill,
          ),
          if (onSale)
            Positioned(
              right: 8,
              top: 8,
              child: _FeedDiscountBadge(percent: discount),
            ),
          if (discovery && product.requiresPrescription)
            const Positioned(left: 8, bottom: 8, child: _RxBadge()),
          if (discovery && !product.inStock)
            Positioned(
              left: 8,
              top: 8,
              child: _FeedBadge(
                label: 'Sold out',
                background: theme.colorScheme.inverseSurface,
                foreground: theme.colorScheme.onInverseSurface,
              ),
            ),
        ],
      ),
    );

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Column(
          // Feed cards hug their details; other cards fill their grid cell.
          mainAxisSize: discovery && !fillHeight
              ? MainAxisSize.min
              : MainAxisSize.max,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Feed photos are a fixed square so every photo in a row lines up; spare height sits under the details.
            if (discovery)
              AspectRatio(aspectRatio: 1, child: photo)
            else
              Expanded(child: photo),
            _expandedIf(
              fillHeight,
              Padding(
                padding: discovery
                    ? const EdgeInsets.fromLTRB(10, 10, 10, 10)
                    : const EdgeInsets.fromLTRB(12, 10, 12, 12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (product.genericNameLabel != null)
                      Text(
                        product.genericNameLabel!.toUpperCase(),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.labelSmall?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 0.4,
                        ),
                      ),
                    Text(
                      product.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: titleStyle,
                    ),
                    if (discovery) ...[
                      const SizedBox(height: 4),
                      _StarRating(product: product),
                      const SizedBox(height: 4),
                      price,
                      if (fillHeight) const Spacer(),
                      if (product.soldQuantity > 0 ||
                          (showAddToCart && product.inStock))
                        // Sold count and a plain cart icon share the last line, like the nav bar's Cart.
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                product.soldQuantity > 0
                                    ? '${_compactCount(product.soldQuantity)} sold'
                                    : '',
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: theme.textTheme.bodySmall?.copyWith(
                                  color: theme.colorScheme.onSurfaceVariant,
                                  fontFeatures: const [
                                    FontFeature.tabularFigures(),
                                  ],
                                ),
                              ),
                            ),
                            if (showAddToCart && product.inStock)
                              _ProductCartButton(
                                product: product,
                                onChooseOptions: onTap,
                              ),
                          ],
                        ),
                    ] else ...[
                      const SizedBox(height: 12),
                      price,
                      if (showAddToCart) ...[
                        if (fillHeight) const Spacer(),
                        const SizedBox(height: 10),
                        _ProductCartButton(
                          product: product,
                          onChooseOptions: onTap,
                        ),
                      ],
                    ],
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

Widget _expandedIf(bool expand, Widget child) =>
    expand ? Expanded(child: child) : child;

/// Five stars from verified-purchase reviews; grey outlines say "No reviews" rather than a low score.
class _StarRating extends StatelessWidget {
  const _StarRating({required this.product});

  final Product product;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final rated = product.ratingCount > 0;
    final average = product.ratingAverage;

    return Semantics(
      label: rated
          ? 'Rated ${average.toStringAsFixed(1)} out of 5, ${product.ratingCount} reviews'
          : 'No reviews yet',
      excludeSemantics: true,
      child: Row(
        children: [
          for (var star = 1; star <= 5; star++)
            Icon(
              !rated
                  ? Icons.star_outline_rounded
                  : average >= star
                  ? Icons.star_rounded
                  : average >= star - 0.5
                  ? Icons.star_half_rounded
                  : Icons.star_rounded,
              size: 14,
              color: rated && average >= star - 0.5
                  ? scheme.secondary
                  : scheme.outline.withValues(alpha: rated ? 0.45 : 0.8),
            ),
          const SizedBox(width: 4),
          Flexible(
            child: Text.rich(
              rated
                  ? TextSpan(
                      children: [
                        TextSpan(
                          text: average.toStringAsFixed(1),
                          style: TextStyle(
                            color: scheme.onSurface,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        TextSpan(text: ' (${product.ratingCount})'),
                      ],
                    )
                  // Short enough for a 160 dp card at large text; TalkBack hears "No reviews yet".
                  : const TextSpan(text: 'No reviews'),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.bodySmall?.copyWith(
                color: scheme.onSurfaceVariant,
                fontFeatures: const [FontFeature.tabularFigures()],
              ),
            ),
          ),
        ],
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

    return _FeedBadge(
      label: '-$percent%',
      background: theme.colorScheme.error,
      foreground: theme.colorScheme.onError,
    );
  }
}

/// Marks a medicine that needs a prescription at the foot of its photo.
class _RxBadge extends StatelessWidget {
  const _RxBadge();

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return _FeedBadge(
      label: 'Rx',
      background: scheme.tertiary,
      foreground: scheme.onTertiary,
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
    return MediaQuery.withClampedTextScaling(
      maxScaleFactor: 1.3,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: background,
          borderRadius: BorderRadius.circular(appDetailRadius),
        ),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
          child: Text(
            label,
            maxLines: 1,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: foreground,
              fontWeight: FontWeight.w800,
              height: 1.2,
            ),
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
    if (_adding || !await requireSignIn(context, ref) || !mounted) {
      return;
    }
    // The cart buys from one branch, so one has to be chosen first.
    if (await ref.read(activeBranchIdProvider.future) == null) {
      if (mounted) {
        await showBranchSelectorSheet(context);
      }
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

  /// The number changes on tap; the cart sends the last of quick taps and puts the real
  /// quantity back if the server refuses, so the stepper never locks while it waits.
  void _setQuantity(String lineId, int quantity) {
    ref.read(cartProvider.notifier).setQuantity(lineId, quantity).catchError((
      Object error,
    ) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              error is ApiException
                  ? error.friendly
                  : 'Could not update your cart. Try again.',
            ),
          ),
        );
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final product = widget.product;
    final chooseOptions =
        product.hasVariants || product.requiresVariantSelection;
    // Watch only this product's line, as a record that compares by value, so a change to
    // another product (or a refetch of the same cart) does not redraw every card on screen.
    final line = chooseOptions
        ? null
        : ref.watch(
            cartProvider.select((cart) {
              final match = cart.value?.lines
                  .where(
                    (l) => l.productId == product.id && l.variantId == null,
                  )
                  .firstOrNull;
              return match == null
                  ? null
                  : (id: match.id, quantity: match.quantity);
            }),
          );
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    // Small glyphs with a 40 dp touch area, no fill or outline.
    const tap = BoxConstraints.tightFor(width: 36, height: 40);

    if (chooseOptions) {
      return TextButton(
        style: TextButton.styleFrom(
          foregroundColor: scheme.primary,
          minimumSize: const Size(0, 40),
          padding: const EdgeInsets.symmetric(horizontal: 4),
          textStyle: theme.textTheme.labelMedium?.copyWith(
            fontWeight: FontWeight.w800,
          ),
        ),
        onPressed: widget.onChooseOptions,
        child: Text(
          'Options',
          semanticsLabel: 'Choose options for ${product.name}',
        ),
      );
    }

    if (line != null) {
      final atStock =
          product.stockQuantity > 0 && line.quantity >= product.stockQuantity;
      return Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            tooltip: line.quantity > 1 ? 'Remove one' : 'Remove from cart',
            color: scheme.primary,
            constraints: tap,
            padding: EdgeInsets.zero,
            onPressed: () => _setQuantity(line.id, line.quantity - 1),
            icon: Icon(
              line.quantity > 1
                  ? PhosphorIconsBold.minus
                  : PhosphorIconsRegular.trash,
              size: 18,
            ),
          ),
          Text(
            '${line.quantity}',
            semanticsLabel: '${line.quantity} in cart',
            style: theme.textTheme.titleSmall?.copyWith(
              fontWeight: FontWeight.w800,
              color: scheme.onSurface,
              fontFeatures: const [FontFeature.tabularFigures()],
            ),
          ),
          IconButton(
            tooltip: 'Add one',
            color: scheme.primary,
            constraints: tap,
            padding: EdgeInsets.zero,
            onPressed: atStock
                ? null
                : () => _setQuantity(line.id, line.quantity + 1),
            icon: const Icon(PhosphorIconsBold.plus, size: 18),
          ),
        ],
      );
    }

    return IconButton(
      tooltip: 'Add to cart',
      color: scheme.primary,
      constraints: const BoxConstraints.tightFor(width: 40, height: 40),
      padding: EdgeInsets.zero,
      onPressed: _adding ? null : _add,
      // The Cart tab's tote, so the icon says where the item goes.
      icon: _adding
          ? SizedBox.square(
              dimension: 18,
              child: CircularProgressIndicator(
                strokeWidth: 2,
                color: scheme.primary,
                semanticsLabel: 'Adding ${product.name} to cart',
              ),
            )
          : const Icon(PhosphorIconsRegular.tote, size: 24),
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
      padding: const EdgeInsets.fromLTRB(8, 4, 10, 4),
      decoration: ShapeDecoration(
        color: color.withValues(
          alpha: Theme.of(context).brightness == Brightness.dark ? 0.2 : 0.1,
        ),
        shape: const StadiumBorder(),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 6,
            height: 6,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
          ),
          const SizedBox(width: 6),
          Flexible(
            child: Text(
              statusLabel(status),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.labelMedium
                  ?.copyWith(color: color, fontWeight: FontWeight.w700),
            ),
          ),
        ],
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
      padding: const EdgeInsets.fromLTRB(16, 28, 16, 10),
      child: Wrap(
        alignment: WrapAlignment.spaceBetween,
        crossAxisAlignment: WrapCrossAlignment.center,
        spacing: 8,
        runSpacing: 4,
        children: [
          Semantics(
            header: true,
            child: Text(
              title,
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                fontSize: 19,
                fontWeight: FontWeight.w800,
                letterSpacing: -0.4,
              ),
            ),
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
      decoration: ShapeDecoration(
        shape: StadiumBorder(
          side: BorderSide(color: Theme.of(context).colorScheme.outlineVariant),
        ),
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

/// Marks a medicine that needs a doctor's prescription before a branch can fill it.
class PrescriptionBadge extends StatelessWidget {
  const PrescriptionBadge({super.key});

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Align(
      alignment: AlignmentDirectional.centerStart,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: scheme.tertiaryContainer,
          borderRadius: BorderRadius.circular(6),
        ),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
          child: Text(
            'Rx · Prescription required',
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: scheme.onTertiaryContainer,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ),
    );
  }
}
