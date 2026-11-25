import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:phosphor_icons/phosphor_icons.dart';

import '../core/api_exception.dart';
import '../core/formatters.dart';
import '../models/models.dart';
import '../providers/auth_providers.dart';
import '../providers/cart_providers.dart';
import '../providers/catalog_providers.dart';
import '../providers/checkout_providers.dart';
import '../providers/store_locator_providers.dart';
import '../theme.dart';
import '../widgets/common.dart';
import '../widgets/feedback_popup.dart';
import '../widgets/fly_to_cart.dart';
import '../widgets/image_viewer.dart';
import '../widgets/store_locator.dart';
import 'auth_gate.dart';
import 'order_detail_screen.dart';
import 'product_discussions.dart';

class ProductScreen extends ConsumerWidget {
  const ProductScreen({super.key, required this.productKey});

  final ProductKey productKey;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final product = ref.watch(productProvider(productKey));

    return product.when(
      skipLoadingOnReload: true,
      data: (value) => _ProductBody(product: value, productKey: productKey),
      loading: () => Scaffold(
        appBar: AppBar(title: const Text('Product')),
        body: const _ProductSkeleton(),
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

class _ProductSkeleton extends StatelessWidget {
  const _ProductSkeleton();

  @override
  Widget build(BuildContext context) {
    return Semantics(
      container: true,
      liveRegion: true,
      label: 'Loading product',
      child: ExcludeSemantics(
        child: ListView(
          padding: const EdgeInsets.only(bottom: 32),
          children: const [
            SkeletonBox(width: double.infinity, height: 300, radius: 0),
            Padding(
              padding: EdgeInsets.fromLTRB(18, 18, 18, 0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SkeletonBox(width: 132, height: 30, radius: appDetailRadius),
                  SizedBox(height: 12),
                  SkeletonBox(
                    width: double.infinity,
                    height: 22,
                    radius: appDetailRadius,
                  ),
                  SizedBox(height: 8),
                  SkeletonBox(width: 210, height: 22, radius: appDetailRadius),
                  SizedBox(height: 14),
                  SkeletonBox(width: 160, height: 16, radius: appDetailRadius),
                  SizedBox(height: 22),
                  SkeletonBox(
                    width: double.infinity,
                    height: 72,
                    radius: appSurfaceRadius,
                  ),
                  SizedBox(height: 26),
                  SkeletonBox(width: 140, height: 20, radius: appDetailRadius),
                  SizedBox(height: 12),
                  SkeletonBox(
                    width: double.infinity,
                    height: 14,
                    radius: appDetailRadius,
                  ),
                  SizedBox(height: 8),
                  SkeletonBox(
                    width: double.infinity,
                    height: 14,
                    radius: appDetailRadius,
                  ),
                  SizedBox(height: 8),
                  SkeletonBox(width: 240, height: 14, radius: appDetailRadius),
                ],
              ),
            ),
          ],
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

  /// null while idle; otherwise whether the request in flight is Buy now.
  /// A notifier, not setState, so a tap repaints only the purchase bar.
  final _purchase = ValueNotifier<bool?>(null);
  bool _startingPurchase = false;
  final _cartIconKey = GlobalKey<CartBadgeIconState>();
  final _addButtonKey = GlobalKey();
  bool _chatting = false;
  bool _showDiscussions = false;
  int _imageIndex = 0;
  final _galleryController = PageController();

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

  /// The option values buyers can pick, e.g. "Blue · Yellow | Small · Large".
  String get _optionSummary {
    final groups = [
      _distinct(product.variants.map((variant) => variant.color)),
      _distinct(product.variants.map((variant) => variant.size)),
    ].where((group) => group.isNotEmpty);
    return groups.isEmpty
        ? _distinct(product.variants.map((variant) => variant.label))
              .join(' · ')
        : groups.map((group) => group.join(' · ')).join('  |  ');
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

  void _applySheetChoice(String? variantId, int quantity) {
    final variantChanged = variantId != _variantId;
    setState(() {
      _variantId = variantId;
      _quantity = quantity.clamp(1, _maxQuantity);
      if (variantChanged) {
        _imageIndex = 0;
      }
    });
    if (variantChanged && _galleryController.hasClients) {
      _galleryController.jumpToPage(0);
    }
  }

  Future<void> _openVariationSheet({required bool buyNow}) async {
    if (_startingPurchase || _purchase.value != null) {
      return;
    }
    final choice = await _chooseVariation(buyNow: buyNow);
    if (choice != null && mounted) {
      await _addToCart(buyNow: choice, optionsConfirmed: true);
    }
  }

  Future<bool?> _chooseVariation({required bool buyNow}) {
    return showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      backgroundColor: Theme.of(context).colorScheme.surfaceContainerLowest,
      builder: (_) => _VariationSheet(
        product: product,
        variantId: _variantId,
        quantity: _quantity,
        buyNow: buyNow,
        onChanged: _applySheetChoice,
      ),
    );
  }

  @override
  void initState() {
    super.initState();

    if (product.variants.length == 1) {
      _variantId = product.variants.first.id;
    }
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        ref.read(searchHistoryProvider.notifier).recordViewed(product);
      }
    });
  }

  @override
  void dispose() {
    _galleryController.dispose();
    _purchase.dispose();
    super.dispose();
  }

  Future<void> _addToCart({
    bool buyNow = false,
    bool optionsConfirmed = false,
  }) async {
    if (_startingPurchase ||
        _purchase.value != null ||
        !product.inStock ||
        (_variant != null && !_variant!.inStock)) {
      return;
    }
    _startingPurchase = true;
    var added = false;

    try {
      if (!await requireSignIn(context, ref) || !mounted) {
        return;
      }
      if (product.variants.isNotEmpty &&
          !optionsConfirmed &&
          (!buyNow || _variant == null)) {
        if (await _chooseVariation(buyNow: buyNow) == null || !mounted) {
          return;
        }
      }
      // Continue the same purchase once a branch is chosen; dismissal cancels it.
      if (await ref.read(activeBranchIdProvider.future) == null) {
        if (!mounted) {
          return;
        }
        await showBranchSelectorSheet(context);
        if (!mounted || await ref.read(activeBranchIdProvider.future) == null) {
          return;
        }
      }
      if (!mounted) {
        return;
      }
      _purchase.value = buyNow;
      final variantId = _variantId;
      final quantity = _quantity;
      Future<void>? landed;
      if (!buyNow) {
        final images = _galleryImages;
        landed = flyToCart(
          context: context,
          source: _addButtonKey,
          target: _cartIconKey,
          imageUrl: images.isEmpty
              ? product.thumbnailUrl
              : images[_imageIndex.clamp(0, images.length - 1)].url,
        );
      }
      // Buy now is one request: the server adds the line and makes it the only
      // selected item, so checkout opens after a single round trip.
      final cart = await ref
          .read(cartProvider.notifier)
          .add(
            productId: product.id,
            quantity: quantity,
            variantId: variantId,
            buyNow: buyNow,
          );

      added = true;
      if (!mounted) {
        return;
      }
      // Confirm only once the server agreed and the thumbnail has landed.
      landed?.then((_) {
        if (mounted) {
          AppFeedback.of(context)
              .show('Added to cart', kind: FeedbackKind.success);
        }
      });
      if (buyNow) {
        final line = cart.lines
            .where(
              (line) =>
                  line.productId == product.id && line.variantId == variantId,
            )
            .firstOrNull;
        if (line == null) {
          _showFeedback(
            'Added to your cart. Open Cart to continue checkout.',
            kind: FeedbackKind.info,
          );
          return;
        }
        // An API that predates buy_now leaves other lines ticked; only then
        // pay for the separate selection request.
        final onlyThisSelected = cart.lines.every(
          (other) => other.selected == (other.id == line.id),
        );
        if (!onlyThisSelected) {
          await ref.read(cartProvider.notifier).selectOnly([line.id]);
          if (!mounted) {
            return;
          }
        }
        ref.invalidate(checkoutQuoteProvider);
        await context.push('/checkout');
      }
    } on ApiException catch (error) {
      if (mounted) {
        _showFeedback(
          added && buyNow
              ? 'Added to cart, but checkout could not open. ${error.friendly}'
              : error.friendly,
        );
      }
    } finally {
      _startingPurchase = false;
      if (mounted) {
        _purchase.value = null;
      }
    }
  }

  Future<void> _openChat() async {
    if (!await requireSignIn(context, ref) || !mounted) {
      return;
    }
    setState(() => _chatting = true);

    try {
      final branch = await ref.read(activeBranchIdProvider.future);
      final conversation = await ref
          .read(apiClientProvider)
          .startConversation(productId: product.id, branch: branch);

      if (mounted) {
        context.push(
          '/conversations/${conversation.id}?product_id=${product.id}',
        );
      }
    } on ApiException catch (error) {
      if (mounted) {
        _showFeedback(error.friendly);
      }
    } finally {
      if (mounted) {
        setState(() => _chatting = false);
      }
    }
  }

  void _showFeedback(String message, {FeedbackKind kind = FeedbackKind.error}) {
    AppFeedback.of(context).show(message, kind: kind);
  }

  Future<void> _refreshProduct() async {
    ref.invalidate(productProvider(widget.productKey));
    ref.invalidate(
      productReviewsProvider((product: widget.productKey, page: 1)),
    );
    ref.invalidate(
      productDiscussionsProvider((product: widget.productKey, page: 1)),
    );
    try {
      await ref.read(productProvider(widget.productKey).future);
    } catch (_) {
      // Keep the current product view mounted if a background refresh fails.
    }
  }

  Widget _purchaseBar(BuildContext context) => ValueListenableBuilder(
    valueListenable: _purchase,
    builder: (context, inFlight, _) => _purchaseBarContents(context, inFlight),
  );

  Widget _purchaseBarContents(BuildContext context, bool? inFlight) {
    final theme = Theme.of(context);
    final available = product.inStock && (_variant?.inStock ?? true);
    final enabled = available && inFlight == null;
    final largeText = MediaQuery.textScalerOf(context).scale(14) > 17;

    Widget shortcut(
      String label,
      Widget icon,
      VoidCallback onTap, {
      bool busy = false,
    }) => SizedBox(
      width: largeText ? 88 : 54,
      child: TextButton(
        style: TextButton.styleFrom(
          minimumSize: const Size(48, 52),
          padding: const EdgeInsets.all(4),
          foregroundColor: theme.colorScheme.onSurface,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(appSurfaceRadius),
          ),
        ),
        onPressed: inFlight == true || busy ? null : onTap,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            busy
                ? SizedBox.square(
                    dimension: 18,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: theme.colorScheme.primary,
                      semanticsLabel: 'Opening chat',
                    ),
                  )
                : SizedBox.square(dimension: 22, child: Center(child: icon)),
            const SizedBox(height: 4),
            FittedBox(
              fit: BoxFit.scaleDown,
              child: Text(
                label,
                maxLines: 1,
                style: theme.textTheme.labelSmall?.copyWith(
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ],
        ),
      ),
    );

    final shortcuts = Wrap(
      spacing: 8,
      children: [
        shortcut(
          'Pharmacy',
          product.store == null
              ? const Icon(Icons.storefront_outlined, size: 22)
              : StoreAvatar(
                  name: product.store!.name,
                  logoUrl: product.store!.logoUrl,
                  radius: 11,
                ),
          () => context.push(
            '/store/${product.branchSlug ?? product.store?.slug ?? widget.productKey.store}',
          ),
        ),
        shortcut(
          'Chat',
          const Icon(PhosphorIconsRegular.chatCircleDots, size: 22),
          _openChat,
          busy: _chatting,
        ),
      ],
    );
    Widget purchaseButton({required bool buyNow}) {
      // Buy now is the one solid action; Add to cart sits beside it as its tonal partner.
      final background = buyNow
          ? theme.colorScheme.primary
          : theme.colorScheme.secondaryContainer;
      final foreground = buyNow
          ? theme.colorScheme.onPrimary
          : theme.colorScheme.onSecondaryContainer;
      // Only checkout shows a spinner; adding is confirmed by the flight.
      final busy = buyNow && inFlight == true;
      return FilledButton(
        key: buyNow ? null : _addButtonKey,
        style: FilledButton.styleFrom(
          backgroundColor: background,
          foregroundColor: foreground,
          minimumSize: const Size(48, 52),
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
          textStyle: theme.textTheme.labelLarge?.copyWith(
            fontWeight: FontWeight.w700,
          ),
          // Keep colour while a request runs so the bar doesn't flash grey.
          disabledBackgroundColor: inFlight != null ? background : null,
          disabledForegroundColor: inFlight != null ? foreground : null,
          shape: const StadiumBorder(),
        ),
        // Add stays live (taps are ignored while busy) so it never greys out.
        onPressed: enabled || (!buyNow && available && inFlight == false)
            ? () => _addToCart(buyNow: buyNow)
            : null,
        child: busy
            ? SizedBox.square(
                dimension: 20,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  color: foreground,
                  semanticsLabel: buyNow
                      ? 'Opening checkout'
                      : 'Adding to cart',
                ),
              )
            : Text(
                buyNow ? (available ? 'Buy now' : 'Sold out') : 'Add to cart',
                textAlign: TextAlign.center,
              ),
      );
    }

    final actions = Row(
      children: [
        Expanded(child: purchaseButton(buyNow: false)),
        const SizedBox(width: 8),
        Expanded(child: purchaseButton(buyNow: true)),
      ],
    );
    return DecoratedBox(
      decoration: BoxDecoration(
        color: theme.colorScheme.surfaceContainerLowest,
        border: Border(
          top: BorderSide(color: theme.colorScheme.outlineVariant),
        ),
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
          child: LayoutBuilder(
            builder: (context, constraints) {
              if (constraints.maxWidth < 336 || largeText) {
                return Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.center,
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
              color: theme.colorScheme.surfaceContainer,
              child: Icon(
                PhosphorIconsRegular.pill,
                size: 72,
                color: theme.colorScheme.primary.withValues(alpha: 0.3),
              ),
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
                    controller: _galleryController,
                    itemCount: images.length,
                    onPageChanged: (index) =>
                        setState(() => _imageIndex = index),
                    itemBuilder: (context, index) => Semantics(
                      container: true,
                      button: true,
                      label: 'Photo ${index + 1} of ${images.length}',
                      hint: 'Open full screen',
                      child: GestureDetector(
                        behavior: HitTestBehavior.opaque,
                        onTap: () => ImageViewer.open(
                          context,
                          url: images[index].url,
                          title: product.name,
                        ),
                        child: NetworkThumb(
                          url: images[index].url,
                          width: double.infinity,
                          height: double.infinity,
                          radius: 0,
                          fit: BoxFit.contain,
                          cacheWidth: 1200,
                        ),
                      ),
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
                          fontFeatures: const [FontFeature.tabularFigures()],
                        ),
                      ),
                    ),
                  ),
                ),
            ],
          ),
        if (images.length > 1 && !largeText)
          ColoredBox(
            color: theme.colorScheme.surfaceContainerHigh,
            child: SizedBox(
              height: 76,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 8,
                ),
                itemCount: images.length,
                separatorBuilder: (_, _) => const SizedBox(width: 8),
                itemBuilder: (context, index) {
                  final selected = index == _imageIndex;
                  return Semantics(
                    button: true,
                    selected: selected,
                    label: 'Photo ${index + 1} of ${images.length}',
                    excludeSemantics: true,
                    child: GestureDetector(
                      onTap: () => MediaQuery.disableAnimationsOf(context)
                          ? _galleryController.jumpToPage(index)
                          : _galleryController.animateToPage(
                              index,
                              duration: const Duration(milliseconds: 260),
                              curve: Curves.easeOutCubic,
                            ),
                      child: Container(
                        width: 60,
                        padding: const EdgeInsets.all(2),
                        decoration: BoxDecoration(
                          color: theme.colorScheme.surfaceContainerLowest,
                          borderRadius: BorderRadius.circular(appDetailRadius),
                          border: Border.all(
                            width: 2,
                            color: selected
                                ? theme.colorScheme.primary
                                : Colors.transparent,
                          ),
                        ),
                        child: NetworkThumb(
                          url: images[index].url,
                          radius: 2,
                          cacheWidth: 160,
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
          ),
      ],
    );
  }

  Widget _block(
    BuildContext context,
    Widget child, {
    EdgeInsets padding = const EdgeInsets.all(16),
  }) => Padding(
    padding: const EdgeInsets.fromLTRB(12, 12, 12, 0),
    child: Material(
      color: Theme.of(context).colorScheme.surfaceContainerLowest,
      borderRadius: BorderRadius.circular(16),
      clipBehavior: Clip.antiAlias,
      child: Padding(padding: padding, child: child),
    ),
  );

  Widget _infoRow(
    BuildContext context, {
    required IconData icon,
    required String title,
    String? subtitle,
    bool highlight = false,
    VoidCallback? onTap,
  }) {
    final theme = Theme.of(context);
    return ListTile(
      dense: true,
      minLeadingWidth: 20,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16),
      leading: Icon(
        icon,
        size: 20,
        color: highlight
            ? theme.colorScheme.primary
            : theme.colorScheme.onSurfaceVariant,
      ),
      title: Text(
        title,
        style: theme.textTheme.bodyMedium?.copyWith(
          color: highlight ? theme.colorScheme.primary : null,
          fontWeight: highlight ? FontWeight.w700 : null,
        ),
      ),
      subtitle: subtitle == null
          ? null
          : Text(
              subtitle,
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
      trailing: onTap == null
          ? null
          : Icon(
              Icons.chevron_right_rounded,
              color: theme.colorScheme.onSurfaceVariant,
            ),
      onTap: onTap,
    );
  }

  Widget _productStrip(
    BuildContext context,
    List<Product> products, {
    EdgeInsets padding = const EdgeInsets.symmetric(horizontal: 16),
  }) => SizedBox(
    // Same sizing rule as the home grid: square photo plus scaled copy.
    height: 150 + MediaQuery.textScalerOf(context).scale(157),
    child: ListView.separated(
      scrollDirection: Axis.horizontal,
      padding: padding,
      itemCount: products.length,
      separatorBuilder: (context, index) => const SizedBox(width: 8),
      itemBuilder: (context, index) {
        final item = products[index];
        return SizedBox(
          width: 150,
          child: ProductCard(
            product: item,
            discovery: true,
            onTap: () =>
                context.push('/product/${item.store?.slug ?? ''}/${item.slug}'),
          ),
        );
      },
    ),
  );

  Widget _ratingBreakdown(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Rating breakdown',
          style: theme.textTheme.titleMedium?.copyWith(
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: theme.colorScheme.surfaceContainerLowest,
            borderRadius: BorderRadius.circular(appSurfaceRadius),
            border: Border.all(
              color: theme.colorScheme.outlineVariant.withValues(alpha: 0.6),
            ),
          ),
          child: Column(
            children: List.generate(5, (index) {
              final star = 5 - index;
              final count = product.reviewSummary!.distribution[star] ?? 0;
              final ratio = product.reviewSummary!.count == 0
                  ? 0.0
                  : count / product.reviewSummary!.count;

              return Padding(
                padding: EdgeInsets.only(bottom: index == 4 ? 0 : 8),
                child: Row(
                  children: [
                    SizedBox(
                      width: 18,
                      child: Text(
                        '$star',
                        style: theme.textTheme.labelMedium?.copyWith(
                          fontWeight: FontWeight.w700,
                          fontFeatures: const [FontFeature.tabularFigures()],
                        ),
                      ),
                    ),
                    Icon(
                      Icons.star_rounded,
                      size: 16,
                      color: theme.colorScheme.secondary,
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(99),
                        child: LinearProgressIndicator(
                          value: ratio,
                          minHeight: 8,
                          color: theme.colorScheme.secondary,
                          backgroundColor:
                              theme.colorScheme.surfaceContainerHighest,
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    SizedBox(
                      width: 28,
                      child: Text(
                        '$count',
                        textAlign: TextAlign.end,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant,
                          fontFeatures: const [FontFeature.tabularFigures()],
                        ),
                      ),
                    ),
                  ],
                ),
              );
            }),
          ),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final largeText = MediaQuery.textScalerOf(context).scale(14) > 21;
    final images = _galleryImages;
    final effectivePrice = _variant == null
        ? product.effectivePrice
        : (_variant!.price ?? product.price);
    final sku = _variant?.sku ?? product.sku;
    final priceLabel = money(effectivePrice);
    final onSale =
        (double.tryParse(product.compareAtPrice ?? '') ?? 0) >
        (double.tryParse(effectivePrice) ?? 0);

    return Scaffold(
      backgroundColor: theme.colorScheme.surfaceContainer,
      appBar: AppBar(
        title: Text(product.name, maxLines: 1, overflow: TextOverflow.ellipsis),
        actions: [
          if (ref.watch(isSignedInProvider))
            SaveProductButton(productId: product.id),
          IconButton(
            tooltip: 'Open cart',
            onPressed: () => context.push('/cart'),
            icon: CartBadgeIcon(key: _cartIconKey),
          ),
        ],
      ),
      bottomNavigationBar: _purchaseBar(context),
      body: LayoutBuilder(
        builder: (context, viewport) => RefreshIndicator(
          onRefresh: _refreshProduct,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.only(bottom: 16),
            children: [
              _selectionFrame(context, viewport, images),
              _block(
                context,
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Medicine first, then strength/brand, then price: what you
                    // are buying matters more than what it costs.
                    if (product.genericNameLabel != null) ...[
                      Text(
                        product.genericNameLabel!.toUpperCase(),
                        style: theme.textTheme.labelLarge?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 0.5,
                        ),
                      ),
                      const SizedBox(height: 2),
                    ],
                    Text(
                      product.name,
                      style: theme.textTheme.headlineSmall?.copyWith(
                        height: 1.2,
                      ),
                    ),
                    if (product.brand != null && product.brand!.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        product.brand!,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                    if ((product.strength?.trim().isNotEmpty ?? false) ||
                        (product.dosageForm?.trim().isNotEmpty ?? false)) ...[
                      const SizedBox(height: 12),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          if (product.strength?.trim().isNotEmpty ?? false)
                            _MedicineFact(
                              label: 'Strength',
                              value: product.strength!.trim(),
                            ),
                          if (product.dosageForm?.trim().isNotEmpty ?? false)
                            _MedicineFact(
                              label: 'Form',
                              value: product.dosageForm!.trim(),
                            ),
                        ],
                      ),
                    ],
                    if (product.requiresPrescription) ...[
                      const SizedBox(height: 12),
                      const PrescriptionBadge(),
                    ],
                    const SizedBox(height: 14),
                    Wrap(
                      spacing: 8,
                      runSpacing: 4,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        Text(
                          priceLabel,
                          style: theme.textTheme.headlineMedium?.copyWith(
                            color: onSale
                                ? theme.colorScheme.error
                                : theme.colorScheme.onSurface,
                            fontWeight: FontWeight.w800,
                            letterSpacing: -0.8,
                            fontFeatures: const [FontFeature.tabularFigures()],
                          ),
                        ),
                        if (onSale) ...[
                          Text(
                            money(product.compareAtPrice),
                            style: theme.textTheme.bodyMedium?.copyWith(
                              decoration: TextDecoration.lineThrough,
                              color: theme.colorScheme.onSurfaceVariant,
                              fontFeatures: const [
                                FontFeature.tabularFigures(),
                              ],
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 3,
                            ),
                            decoration: BoxDecoration(
                              color: theme.colorScheme.primaryContainer,
                              borderRadius: BorderRadius.circular(
                                appDetailRadius,
                              ),
                            ),
                            child: Text(
                              '${money(addMoney(product.compareAtPrice!, '-$effectivePrice'))} off',
                              style: theme.textTheme.labelSmall?.copyWith(
                                color: theme.colorScheme.onPrimaryContainer,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                    if (!product.inStock) ...[
                      const SizedBox(height: 10),
                      StockedElsewhereNotice(productId: product.id),
                    ] else ...[
                      const SizedBox(height: 10),
                      Wrap(
                        spacing: 6,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        children: [
                          Icon(
                            Icons.check_circle_rounded,
                            size: 18,
                            color: theme.colorScheme.primary,
                          ),
                          const SizedBox(width: 6),
                          Text(
                            product.branchName == null
                                ? 'Available now'
                                : 'Available at ${product.branchName}',
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: theme.colorScheme.primary,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ],
                      ),
                    ],
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        Icon(
                          product.ratingCount > 0
                              ? Icons.star_rounded
                              : Icons.star_outline_rounded,
                          color: product.ratingCount > 0
                              ? theme.colorScheme.secondary
                              : theme.colorScheme.onSurfaceVariant,
                          size: 18,
                        ),
                        Text(
                          product.ratingCount == 0
                              ? 'No reviews yet'
                              : '${product.ratingAverage.toStringAsFixed(1)} (${product.ratingCount})',
                          style: theme.textTheme.bodySmall?.copyWith(
                            fontWeight: product.ratingCount > 0
                                ? FontWeight.w700
                                : FontWeight.w400,
                            color: product.ratingCount > 0
                                ? theme.colorScheme.onSurface
                                : theme.colorScheme.onSurfaceVariant,
                          ),
                        ),
                        if (product.soldQuantity > 0)
                          Text(
                            '· ${product.soldQuantity} sold',
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: theme.colorScheme.onSurfaceVariant,
                            ),
                          ),
                      ],
                    ),
                  ],
                ),
              ),
              _block(
                context,
                padding: const EdgeInsets.symmetric(vertical: 4),
                Column(
                  children: [
                    if (largeText) ...[
                      _infoRow(
                        context,
                        icon: PhosphorIconsRegular.motorcycle,
                        title: 'Delivery from your branch',
                        subtitle: 'Fee shown at checkout',
                        highlight: false,
                      ),
                      _infoRow(
                        context,
                        icon: PhosphorIconsRegular.money,
                        title: 'Cash on delivery',
                      ),
                    ] else
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: _infoRow(
                              context,
                              icon: PhosphorIconsRegular.motorcycle,
                              title: 'Delivery from your branch',
                              subtitle: 'Fee shown at checkout',
                              highlight: false,
                            ),
                          ),
                          Expanded(
                            child: _infoRow(
                              context,
                              icon: PhosphorIconsRegular.money,
                              title: 'Cash on delivery',
                            ),
                          ),
                        ],
                      ),
                    // A single variant is auto-selected, so there is nothing to choose.
                    if (product.variants.length > 1)
                      _infoRow(
                        context,
                        icon: Icons.grid_view_rounded,
                        title: _optionSummary,
                        subtitle: _variant == null
                            ? 'Choose an option'
                            : 'Selected: ${_variant!.label}',
                        onTap: () => _openVariationSheet(buyNow: false),
                      ),
                  ],
                ),
              ),
              if (product.store != null)
                _block(
                  context,
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          StoreAvatar(
                            name: product.store!.name,
                            logoUrl: product.store!.logoUrl,
                            square: true,
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                // Stock on this page is the chosen branch's, so the card names it.
                                Text(
                                  product.branchName ?? product.store!.name,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: theme.textTheme.titleMedium?.copyWith(
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                                Text(
                                  product.branchName != null
                                      ? 'Your branch'
                                      : product.store!.city ??
                                            'Pharmacy branch',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: theme.textTheme.bodySmall?.copyWith(
                                    color: theme.colorScheme.onSurfaceVariant,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 8),
                          FilledButton.tonal(
                            style: FilledButton.styleFrom(
                              // The theme's full-width minimum breaks inside a Row.
                              minimumSize: const Size(0, 40),
                              padding: const EdgeInsets.symmetric(
                                horizontal: 16,
                              ),
                              tapTargetSize: MaterialTapTargetSize.padded,
                              shape: const StadiumBorder(),
                              textStyle: theme.textTheme.labelLarge,
                            ),
                            onPressed: () => context.push(
                              '/store/${product.branchSlug ?? product.store!.slug}',
                            ),
                            child: const Text('Visit pharmacy'),
                          ),
                        ],
                      ),
                      if (product.moreFromStore.isNotEmpty) ...[
                        const SizedBox(height: 16),
                        Text(
                          'More from this pharmacy',
                          style: theme.textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 10),
                        _productStrip(
                          context,
                          product.moreFromStore,
                          padding: EdgeInsets.zero,
                        ),
                      ],
                    ],
                  ),
                ),
              if (product.sameMedicine.isNotEmpty)
                _block(
                  context,
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Other brands of this medicine',
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Same ingredient, strength and form, by price. Inactive ingredients and appearance may differ. Ask our pharmacist before switching.',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant,
                        ),
                      ),
                      if (product.switchWithCare) ...[
                        const SizedBox(height: 8),
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.symmetric(
                            horizontal: 12,
                            vertical: 8,
                          ),
                          decoration: BoxDecoration(
                            color: theme.colorScheme.tertiaryContainer,
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            'Switching brands of this medicine needs care. Talk to your pharmacist or doctor before changing brands.',
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: theme.colorScheme.onTertiaryContainer,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ],
                      const SizedBox(height: 10),
                      _productStrip(
                        context,
                        product.sameMedicine,
                        padding: EdgeInsets.zero,
                      ),
                    ],
                  ),
                ),
              _block(
                context,
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Product details',
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      (product.description == null ||
                              product.description!.trim().isEmpty)
                          ? 'No description yet.'
                          : product.description!,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        height: 1.55,
                        color:
                            (product.description == null ||
                                product.description!.trim().isEmpty)
                            ? theme.colorScheme.onSurfaceVariant
                            : theme.colorScheme.onSurface,
                      ),
                    ),
                    if (sku != null && sku.isNotEmpty) ...[
                      const SizedBox(height: 12),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: theme.colorScheme.surfaceContainerLow,
                          borderRadius: BorderRadius.circular(appDetailRadius),
                        ),
                        child: Text(
                          'SKU $sku',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant,
                            fontFeatures: const [FontFeature.tabularFigures()],
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              _block(
                context,
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (product.reviewSummary != null &&
                        product.reviewSummary!.count > 0) ...[
                      _ratingBreakdown(context),
                      const SizedBox(height: 16),
                    ],
                    _ProductReviews(productKey: widget.productKey),
                  ],
                ),
              ),
              _block(
                context,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                Theme(
                  data: theme.copyWith(dividerColor: Colors.transparent),
                  child: ExpansionTile(
                    tilePadding: EdgeInsets.zero,
                    childrenPadding: EdgeInsets.zero,
                    title: Text(
                      'Product discussions',
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    subtitle: Text(
                      'Questions and answers with the pharmacy',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                    onExpansionChanged: (expanded) =>
                        setState(() => _showDiscussions = expanded),
                    children: [
                      if (_showDiscussions)
                        ProductDiscussions(productKey: widget.productKey),
                    ],
                  ),
                ),
              ),
              if (product.related.isNotEmpty)
                _block(
                  context,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        child: Text(
                          'You may also like',
                          style: theme.textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                      const SizedBox(height: 12),
                      _productStrip(context, product.related),
                    ],
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MedicineFact extends StatelessWidget {
  const _MedicineFact({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      constraints: const BoxConstraints(minWidth: 96),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: theme.colorScheme.surfaceContainerHigh,
        borderRadius: BorderRadius.circular(appSurfaceRadius),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            label,
            style: theme.textTheme.labelSmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            value,
            style: theme.textTheme.labelLarge?.copyWith(
              color: theme.colorScheme.onSurface,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

/// Heart in the product app bar: saves the product to the wishlist or removes it.
class SaveProductButton extends ConsumerStatefulWidget {
  const SaveProductButton({super.key, required this.productId});

  final String productId;

  @override
  ConsumerState<SaveProductButton> createState() => _SaveProductButtonState();
}

class _SaveProductButtonState extends ConsumerState<SaveProductButton> {
  bool _busy = false;

  Future<void> _toggle(bool saved) async {
    setState(() => _busy = true);
    final api = ref.read(apiClientProvider);
    try {
      saved
          ? await api.unsaveProduct(widget.productId)
          : await api.saveProduct(widget.productId);
      ref.invalidate(savedProductsProvider);
      await ref.read(savedProductsProvider.future);
    } on ApiException catch (error) {
      if (mounted) {
        AppFeedback.of(context).show(error.friendly);
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final savedList = ref.watch(savedProductsProvider).value;
    final saved = savedList?.any((p) => p.id == widget.productId) ?? false;

    return IconButton(
      tooltip: saved ? 'Remove from saved items' : 'Save for later',
      onPressed: savedList == null || _busy ? null : () => _toggle(saved),
      icon: Icon(
        saved ? Icons.favorite_rounded : Icons.favorite_border_rounded,
        color: saved ? Theme.of(context).colorScheme.error : null,
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
  ProductReview? _pendingReview;
  bool _ratingBusy = false;
  String? _ratingError;

  Future<void> _rateProduct() async {
    if (_ratingBusy || !await requireSignIn(context, ref) || !mounted) return;
    setState(() => _ratingBusy = true);
    try {
      final product = ref.read(productProvider(widget.productKey)).value;
      if (product == null) return;
      final api = ref.read(apiClientProvider);
      final orderItemId = await api.eligibleReviewOrderItem(product.id);
      if (!mounted) return;
      if (orderItemId == null) {
        AppFeedback.of(context).show(
          'Ratings are available after a delivered purchase of this product.',
          kind: FeedbackKind.info,
        );
        return;
      }
      final input = await showProductReviewSheet(context, product.name);
      if (input == null || !mounted) return;
      setState(() {
        _page = 1;
        _ratingError = null;
        _pendingReview = ProductReview(
          rating: input.rating,
          author: 'You',
          body: input.body,
          verifiedPurchase: true,
        );
      });
      final saved = await api.publishProductReview(
        orderItemId: orderItemId,
        rating: input.rating,
        body: input.body,
      );
      if (!mounted) return;
      setState(() => _pendingReview = saved);
      AppFeedback.of(context)
          .show('Your review was published.', kind: FeedbackKind.success);
      ref.invalidate(
        productReviewsProvider((product: widget.productKey, page: 1)),
      );
      ref.invalidate(productProvider(widget.productKey));
    } on ApiException catch (error) {
      if (mounted) {
        setState(
          () => _ratingError = error.code == 'network.error'
              ? 'Could not confirm your rating. Refresh before trying again.'
              : error.friendly,
        );
        AppFeedback.of(context).show(_ratingError!);
      }
    } catch (_) {
      if (mounted) {
        setState(
          () => _ratingError =
              'Could not confirm your rating. Refresh before trying again.',
        );
        AppFeedback.of(context).show(_ratingError!);
      }
    } finally {
      if (mounted) setState(() => _ratingBusy = false);
    }
  }

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
        Wrap(
          alignment: WrapAlignment.spaceBetween,
          crossAxisAlignment: WrapCrossAlignment.center,
          spacing: 8,
          runSpacing: 2,
          children: [
            Text(
              'Customer reviews',
              key: _headingKey,
              style: theme.textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w800,
              ),
            ),
            TextButton.icon(
              style: TextButton.styleFrom(
                visualDensity: VisualDensity.compact,
                padding: const EdgeInsets.symmetric(horizontal: 8),
              ),
              onPressed: _ratingBusy ? null : _rateProduct,
              icon: const Icon(Icons.star_outline, size: 20),
              label: Text(
                _ratingBusy ? 'Posting rating...' : 'Rate this product',
              ),
            ),
          ],
        ),
        if (_ratingError != null) ...[
          const SizedBox(height: 8),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            decoration: BoxDecoration(
              color: theme.colorScheme.errorContainer,
              borderRadius: BorderRadius.circular(appSurfaceRadius),
            ),
            child: Row(
              children: [
                Icon(
                  Icons.error_outline_rounded,
                  size: 18,
                  color: theme.colorScheme.onErrorContainer,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    _ratingError!,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onErrorContainer,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
        if (_pendingReview != null &&
            !(reviews.value?.reviews.any(
                  (review) =>
                      review.id.isNotEmpty && review.id == _pendingReview!.id,
                ) ??
                false)) ...[
          const SizedBox(height: 10),
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: theme.colorScheme.surfaceContainerLowest,
              borderRadius: BorderRadius.circular(appSurfaceRadius),
              border: Border.all(
                color: _ratingError != null
                    ? theme.colorScheme.error.withValues(alpha: 0.5)
                    : theme.colorScheme.outlineVariant.withValues(alpha: 0.6),
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        '${_pendingReview!.author} · ${_pendingReview!.rating} out of 5 stars',
                        style: theme.textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    Text(
                      _ratingError != null
                          ? 'Could not confirm'
                          : _ratingBusy
                          ? 'Sending...'
                          : 'Posted',
                      style: theme.textTheme.labelSmall?.copyWith(
                        color: _ratingError != null
                            ? theme.colorScheme.error
                            : theme.colorScheme.onSurfaceVariant,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
                if (_pendingReview!.body.isNotEmpty) ...[
                  const SizedBox(height: 6),
                  Text(
                    _pendingReview!.body,
                    style: theme.textTheme.bodyMedium?.copyWith(height: 1.5),
                  ),
                ],
              ],
            ),
          ),
        ],
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
          error: (_, _) => Container(
            width: double.infinity,
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: theme.colorScheme.surfaceContainerLow,
              borderRadius: BorderRadius.circular(appSurfaceRadius),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Could not load customer reviews.'),
                const SizedBox(height: 4),
                TextButton(
                  style: TextButton.styleFrom(padding: EdgeInsets.zero),
                  onPressed: () => ref.invalidate(provider),
                  child: const Text('Retry reviews'),
                ),
              ],
            ),
          ),
          data: (value) => Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (value.reviews.isEmpty)
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: theme.colorScheme.surfaceContainerLow,
                    borderRadius: BorderRadius.circular(appSurfaceRadius),
                  ),
                  child: Text(
                    'No reviews yet.',
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                ),
              for (var i = 0; i < value.reviews.length; i++) ...[
                Builder(
                  builder: (context) {
                    final review = value.reviews[i];
                    final authorName = review.author.trim().isEmpty
                        ? 'Customer'
                        : review.author;
                    return Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.center,
                          children: [
                            ExcludeSemantics(
                              child: CircleAvatar(
                                radius: 14,
                                backgroundColor:
                                    theme.colorScheme.primaryContainer,
                                foregroundColor:
                                    theme.colorScheme.onPrimaryContainer,
                                child: Text(
                                  initials(authorName),
                                  style: const TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                authorName,
                                style: theme.textTheme.titleSmall?.copyWith(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Semantics(
                              label: '${review.rating} out of 5 stars',
                              child: ExcludeSemantics(
                                child: _Stars(rating: review.rating.toDouble()),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 6),
                        Wrap(
                          spacing: 10,
                          runSpacing: 4,
                          crossAxisAlignment: WrapCrossAlignment.center,
                          children: [
                            if (review.verifiedPurchase)
                              Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(
                                    Icons.verified_outlined,
                                    size: 14,
                                    color: theme.colorScheme.primary,
                                  ),
                                  const SizedBox(width: 4),
                                  Text(
                                    'Verified purchase',
                                    style: theme.textTheme.labelMedium
                                        ?.copyWith(
                                          color: theme.colorScheme.primary,
                                          fontWeight: FontWeight.w700,
                                        ),
                                  ),
                                ],
                              ),
                            if (shortDate(review.createdAt).isNotEmpty)
                              Text(
                                shortDate(review.createdAt),
                                style: theme.textTheme.bodySmall?.copyWith(
                                  color: theme.colorScheme.onSurfaceVariant,
                                ),
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
                            style: theme.textTheme.bodyMedium?.copyWith(
                              height: 1.5,
                            ),
                          ),
                        ],
                        if (i < value.reviews.length - 1 || value.lastPage > 1)
                          const Divider(height: 28),
                      ],
                    );
                  },
                ),
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
                    Text(
                      'Page ${value.page} of ${value.lastPage}',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                        fontFeatures: const [FontFeature.tabularFigures()],
                      ),
                    ),
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

List<String> _distinct(Iterable<String?> values) {
  final distinct = <String>[];
  for (final value in values) {
    if (value != null && value.trim().isNotEmpty && !distinct.contains(value)) {
      distinct.add(value);
    }
  }
  return distinct;
}

String? _imageForColor(Product product, String color) {
  final wanted = color.trim().toLowerCase();
  for (final image in product.images) {
    if (image.color?.trim().toLowerCase() == wanted) {
      return image.url;
    }
  }
  return null;
}

Color? _swatch(String? hex) {
  final digits = (hex ?? '').replaceFirst('#', '');
  final value = digits.length == 6 ? int.tryParse(digits, radix: 16) : null;
  return value == null ? null : Color(0xFF000000 | value);
}

class _VariationSheet extends StatefulWidget {
  const _VariationSheet({
    required this.product,
    required this.variantId,
    required this.quantity,
    required this.onChanged,
    required this.buyNow,
  });

  final Product product;
  final String? variantId;
  final int quantity;
  final void Function(String? variantId, int quantity) onChanged;

  /// The bottom-bar action that Confirm continues.
  final bool buyNow;

  @override
  State<_VariationSheet> createState() => _VariationSheetState();
}

class _VariationSheetState extends State<_VariationSheet> {
  late final List<String> _colors = _distinct(
    _variants.map((variant) => variant.color),
  );
  late final List<String> _sizes = _distinct(
    _variants.map((variant) => variant.size),
  );
  String? _color;
  String? _size;
  String? _labelVariantId;
  late int _quantity = widget.quantity;

  List<ProductVariant> get _variants => widget.product.variants;

  bool get _labelOnly => _colors.isEmpty && _sizes.isEmpty;

  @override
  void initState() {
    super.initState();
    final initial = _variants
        .where((variant) => variant.id == widget.variantId)
        .firstOrNull;
    _color = initial?.color;
    _size = initial?.size;
    _labelVariantId = initial?.id;
  }

  ProductVariant? get _variant {
    if (_labelOnly) {
      return _variants
          .where((variant) => variant.id == _labelVariantId)
          .firstOrNull;
    }
    if ((_colors.isNotEmpty && _color == null) ||
        (_sizes.isNotEmpty && _size == null)) {
      return null;
    }
    return _variants
        .where(
          (variant) =>
              (_colors.isEmpty || variant.color == _color) &&
              (_sizes.isEmpty || variant.size == _size),
        )
        .firstOrNull;
  }

  int get _maxQuantity {
    final stock = _variant?.stockQuantity ?? widget.product.stockQuantity;
    return stock.clamp(1, 100);
  }

  void _update(VoidCallback change) {
    final previousVariantId = _variant?.id;
    setState(() {
      change();
      if (_variant?.id != previousVariantId) {
        _quantity = 1;
      }
      _quantity = _quantity.clamp(1, _maxQuantity);
    });
    widget.onChanged(_variant?.id, _quantity);
  }

  void _pickColor(String color) => _update(() {
    _color = color;
    final sizeStillFits = _variants.any(
      (variant) =>
          variant.color == color && variant.size == _size && variant.inStock,
    );
    if (_size != null && !sizeStillFits) {
      _size = null;
    }
  });

  bool _sizeAvailable(String size) => _variants.any(
    (variant) =>
        variant.size == size &&
        variant.inStock &&
        (_color == null || variant.color == _color),
  );

  Widget _heading(String label, String? value) {
    final theme = Theme.of(context);
    return Text.rich(
      TextSpan(
        text: label,
        children: [
          if (value != null)
            TextSpan(
              text: '  $value',
              style: TextStyle(
                fontWeight: FontWeight.w400,
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
        ],
      ),
      style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
    );
  }

  Widget _colorTile(String color) {
    final theme = Theme.of(context);
    final selected = color == _color;
    final enabled = _variants.any(
      (variant) => variant.color == color && variant.inStock,
    );
    final photo = _imageForColor(widget.product, color);
    final swatch = _swatch(
      _variants.firstWhere((variant) => variant.color == color).colorHex,
    );

    return Semantics(
      button: true,
      selected: selected,
      enabled: enabled,
      label: enabled ? color : '$color, sold out',
      excludeSemantics: true,
      child: Opacity(
        opacity: enabled ? 1 : 0.4,
        child: InkWell(
          borderRadius: BorderRadius.circular(appSurfaceRadius),
          onTap: enabled ? () => _pickColor(color) : null,
          child: SizedBox(
            width: 72,
            child: Column(
              children: [
                Container(
                  height: 72,
                  padding: const EdgeInsets.all(2),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(appSurfaceRadius),
                    border: Border.all(
                      width: 2,
                      color: selected
                          ? theme.colorScheme.primary
                          : theme.colorScheme.outlineVariant,
                    ),
                  ),
                  child: photo != null
                      ? NetworkThumb(
                          url: photo,
                          radius: appDetailRadius,
                          cacheWidth: 220,
                        )
                      : DecoratedBox(
                          decoration: BoxDecoration(
                            color:
                                swatch ??
                                theme.colorScheme.surfaceContainerHighest,
                            borderRadius: BorderRadius.circular(
                              appDetailRadius,
                            ),
                          ),
                        ),
                ),
                const SizedBox(height: 4),
                Text(
                  color,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.center,
                  style: theme.textTheme.labelMedium?.copyWith(
                    color: selected ? theme.colorScheme.primary : null,
                    fontWeight: selected ? FontWeight.w700 : null,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final product = widget.product;
    final variant = _variant;
    final price = variant == null
        ? product.effectivePrice
        : (variant.price ?? product.price);
    final photo =
        (_color == null ? null : _imageForColor(product, _color!)) ??
        (product.images.isEmpty
            ? product.thumbnailUrl
            : product.images.first.url);
    final missing = [
      if (_colors.isNotEmpty && _color == null) 'color',
      if (_sizes.isNotEmpty && _size == null) 'size',
      if (_labelOnly && variant == null) 'an option',
    ];
    final status = variant == null
        ? (missing.isEmpty
              ? 'This combination is not available'
              : 'Select ${missing.join(' and ')}')
        : (variant.inStock ? '${variant.stockQuantity} in stock' : 'Sold out');
    final ready =
        (variant?.inStock ?? false) ||
        (variant == null &&
            !product.requiresVariantSelection &&
            product.inStock);

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              GestureDetector(
                onTap: photo == null || photo.isEmpty
                    ? null
                    : () => ImageViewer.open(
                        context,
                        url: photo,
                        title: product.name,
                      ),
                child: NetworkThumb(
                  url: photo,
                  width: 88,
                  height: 88,
                  radius: appDetailRadius,
                  cacheWidth: 300,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      money(price),
                      style: theme.textTheme.headlineSmall?.copyWith(
                        color: theme.colorScheme.primary,
                        fontWeight: FontWeight.w800,
                        fontFeatures: const [FontFeature.tabularFigures()],
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      status,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: variant != null && !variant.inStock
                            ? theme.colorScheme.error
                            : theme.colorScheme.onSurfaceVariant,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const Divider(height: 28),
        Flexible(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (_colors.isNotEmpty) ...[
                  _heading('Color', _color),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 10,
                    runSpacing: 12,
                    children: [for (final color in _colors) _colorTile(color)],
                  ),
                  const SizedBox(height: 22),
                ],
                if (_sizes.isNotEmpty) ...[
                  _heading('Size', _size),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final size in _sizes)
                        ChoiceChip(
                          label: Text(size),
                          selected: size == _size,
                          onSelected: _sizeAvailable(size)
                              ? (_) => _update(() => _size = size)
                              : null,
                        ),
                    ],
                  ),
                  const SizedBox(height: 22),
                ],
                if (_labelOnly) ...[
                  _heading('Option', variant?.label),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final option in _variants)
                        ChoiceChip(
                          label: Text(option.label),
                          selected: option.id == _labelVariantId,
                          onSelected: option.inStock
                              ? (_) =>
                                    _update(() => _labelVariantId = option.id)
                              : null,
                        ),
                    ],
                  ),
                  const SizedBox(height: 22),
                ],
                Row(
                  children: [
                    Expanded(child: _heading('Quantity', null)),
                    const SizedBox(width: 12),
                    QuantityStepper(
                      quantity: _quantity,
                      max: _maxQuantity,
                      onChanged: (value) => _update(() => _quantity = value),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
              ],
            ),
          ),
        ),
        Padding(
          // useSafeArea leaves the bottom inset to the sheet, so clear the
          // 3-button / gesture navigation bar here.
          padding: EdgeInsets.fromLTRB(
            16,
            8,
            16,
            16 + MediaQuery.viewPaddingOf(context).bottom,
          ),
          child: FilledButton(
            style: FilledButton.styleFrom(
              minimumSize: const Size(48, 52),
              textStyle: theme.textTheme.labelLarge?.copyWith(
                fontWeight: FontWeight.w700,
              ),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(appSurfaceRadius),
              ),
            ),
            onPressed: ready
                ? () => Navigator.pop(context, widget.buyNow)
                : null,
            child: const Text('Confirm'),
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
          color: Theme.of(context).colorScheme.secondary,
        ),
      ),
    );
  }
}
