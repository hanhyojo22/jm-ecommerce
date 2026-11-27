import 'package:flutter/material.dart';
import 'package:phosphor_icons/phosphor_icons.dart';

import '../theme.dart';

enum _BannerAction { category, deals, branches }

class _BannerItem {
  const _BannerItem({
    required this.asset,
    required this.name,
    required this.headline,
    required this.cta,
    required this.action,
    required this.photoAlignment,
    this.categorySlug,
  });

  final String asset;
  final String name;
  final String headline;
  final String cta;
  final _BannerAction action;
  final Alignment photoAlignment;
  final String? categorySlug;
}

class FamilyBannerCarousel extends StatefulWidget {
  const FamilyBannerCarousel({
    super.key,
    required this.onShop,
    this.onBrowseCategory,
    this.onShowDeals,
    this.onOpenBranches,
  });

  final VoidCallback onShop;
  final ValueChanged<String>? onBrowseCategory;
  final VoidCallback? onShowDeals;
  final VoidCallback? onOpenBranches;

  @override
  State<FamilyBannerCarousel> createState() => _FamilyBannerCarouselState();
}

class _FamilyBannerCarouselState extends State<FamilyBannerCarousel> {
  static const _banners = [
    _BannerItem(
      asset: 'assets/home/family-everyday-care.webp',
      name: 'Everyday care',
      headline: 'Care for the whole family.',
      cta: 'Browse vitamins',
      action: _BannerAction.category,
      categorySlug: 'vitamins-supplements',
      photoAlignment: Alignment(0.14, -0.55),
    ),
    _BannerItem(
      asset: 'assets/home/family-morning-joy.webp',
      name: 'Free delivery',
      headline: 'Free delivery from your branch.',
      cta: 'See top deals',
      action: _BannerAction.deals,
      photoAlignment: Alignment(0.0, -0.58),
    ),
    _BannerItem(
      asset: 'assets/home/family-garden-moments.webp',
      name: '24/7 branches',
      headline: 'Your nearest pharmacy, open 24h.',
      cta: 'Find a branch',
      action: _BannerAction.branches,
      photoAlignment: Alignment(0.04, -0.52),
    ),
    _BannerItem(
      asset: 'assets/home/family-bedtime-comfort.webp',
      name: 'Prescription care',
      headline: 'Refill regular medicines easily.',
      cta: 'Browse Rx meds',
      action: _BannerAction.category,
      categorySlug: 'prescription-medicines',
      photoAlignment: Alignment(0.04, -0.58),
    ),
  ];

  final _controller = PageController(keepPage: false);
  int _active = 0;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _select(int index) {
    if (MediaQuery.disableAnimationsOf(context)) {
      _controller.jumpToPage(index);
    } else {
      _controller.animateToPage(
        index,
        duration: const Duration(milliseconds: 250),
        curve: Curves.easeOutCubic,
      );
    }
  }

  void _activateBanner(_BannerItem banner) {
    switch (banner.action) {
      case _BannerAction.category:
        if (widget.onBrowseCategory != null && banner.categorySlug != null) {
          widget.onBrowseCategory!(banner.categorySlug!);
        } else {
          widget.onShop();
        }
      case _BannerAction.deals:
        (widget.onShowDeals ?? widget.onShop)();
      case _BannerAction.branches:
        (widget.onOpenBranches ?? widget.onShop)();
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final dark = scheme.brightness == Brightness.dark;
    final slab = brandSlab(scheme);
    final scaler = MediaQuery.textScalerOf(context);
    final largeText = scaler.scale(14) > 17.5;

    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 720),
        child: DecoratedBox(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            boxShadow: [
              BoxShadow(
                color: dark
                    ? Colors.black.withValues(alpha: 0.32)
                    : const Color(0x280B183D),
                blurRadius: 18,
                offset: const Offset(0, 6),
              ),
            ],
          ),
          child: Material(
            color: slab.fill,
            borderRadius: BorderRadius.circular(16),
            clipBehavior: Clip.antiAlias,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                LayoutBuilder(
                  builder: (context, constraints) => ConstrainedBox(
                    constraints: BoxConstraints(
                      minHeight: (constraints.maxWidth / 2.25).clamp(
                        152.0,
                        220.0,
                      ),
                    ),
                    child: Stack(
                      children: [
                        // Measure all headlines so swiping never clips text or changes the card height.
                        for (final banner in _banners)
                          ExcludeSemantics(
                            child: IgnorePointer(
                              child: Opacity(
                                opacity: 0,
                                child: _BannerCopy(
                                  name: banner.name,
                                  headline: banner.headline,
                                  cta: banner.cta,
                                  largeText: largeText,
                                ),
                              ),
                            ),
                          ),
                        Positioned.fill(
                          child: PageView.builder(
                            controller: _controller,
                            allowImplicitScrolling: true,
                            itemCount: _banners.length,
                            onPageChanged: (index) =>
                                setState(() => _active = index),
                            itemBuilder: (context, index) {
                              final banner = _banners[index];
                              return Semantics(
                                button: true,
                                label:
                                    '${banner.headline} ${banner.cta}. '
                                    'Banner ${index + 1} of ${_banners.length}.',
                                excludeSemantics: true,
                                onTap: () => _activateBanner(banner),
                                child: Stack(
                                  fit: StackFit.expand,
                                  children: [
                                    if (!largeText)
                                      Align(
                                        alignment:
                                            AlignmentDirectional.centerEnd,
                                        child: FractionallySizedBox(
                                          widthFactor: 0.46,
                                          heightFactor: 1,
                                          child: Stack(
                                            fit: StackFit.expand,
                                            children: [
                                              Image.asset(
                                                banner.asset,
                                                fit: BoxFit.cover,
                                                alignment:
                                                    banner.photoAlignment,
                                              ),
                                              // Narrow left-edge feather into the solid brand slab; faces stay clear.
                                              DecoratedBox(
                                                decoration: BoxDecoration(
                                                  gradient: LinearGradient(
                                                    colors: [
                                                      slab.fill,
                                                      slab.fill.withValues(
                                                        alpha: 0,
                                                      ),
                                                    ],
                                                    stops: const [0.0, 0.24],
                                                  ),
                                                ),
                                              ),
                                            ],
                                          ),
                                        ),
                                      ),
                                    Material(
                                      type: MaterialType.transparency,
                                      child: InkWell(
                                        onTap: () => _activateBanner(banner),
                                        splashColor: Colors.white.withValues(
                                          alpha: 0.12,
                                        ),
                                        child: _BannerCopy(
                                          name: banner.name,
                                          headline: banner.headline,
                                          cta: banner.cta,
                                          largeText: largeText,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              );
                            },
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 0, 8, 2),
                  child: Row(
                    children: [
                      Expanded(
                        child: ExcludeSemantics(
                          child: MediaQuery.withClampedTextScaling(
                            maxScaleFactor: 1.5,
                            child: Text(
                              '${(_active + 1).toString().padLeft(2, '0')} / 04',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: theme.textTheme.labelSmall?.copyWith(
                                color: slab.ink.withValues(alpha: 0.78),
                              ),
                            ),
                          ),
                        ),
                      ),
                      for (var index = 0; index < _banners.length; index++)
                        Semantics(
                          button: true,
                          selected: _active == index,
                          label:
                              'Show banner ${index + 1}: ${_banners[index].name}',
                          excludeSemantics: true,
                          onTap: () => _select(index),
                          child: InkWell(
                            onTap: () => _select(index),
                            borderRadius: BorderRadius.circular(24),
                            child: SizedBox.square(
                              dimension: 48,
                              child: Center(
                                child: Container(
                                  width: _active == index ? 22 : 8,
                                  height: 4,
                                  decoration: BoxDecoration(
                                    color: _active == index
                                        ? scheme.secondary
                                        : Colors.white.withValues(alpha: 0.36),
                                    borderRadius: BorderRadius.circular(2),
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _BannerCopy extends StatelessWidget {
  const _BannerCopy({
    required this.name,
    required this.headline,
    required this.cta,
    required this.largeText,
  });

  final String name;
  final String headline;
  final String cta;
  final bool largeText;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final slab = brandSlab(scheme);

    return Align(
      alignment: AlignmentDirectional.centerStart,
      child: FractionallySizedBox(
        widthFactor: largeText ? 1 : 0.58,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 14, 8, 8),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              DecoratedBox(
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.16),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 3,
                  ),
                  child: Text(
                    name.toUpperCase(),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.labelSmall?.copyWith(
                      fontSize: 10,
                      color: slab.ink,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 0.8,
                      height: 1.2,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 8),
              Text(
                headline,
                style: theme.textTheme.headlineSmall?.copyWith(
                  fontSize: 18,
                  color: slab.ink,
                  fontWeight: FontWeight.w800,
                  height: 1.15,
                  letterSpacing: -0.35,
                ),
              ),
              const SizedBox(height: 12),
              Container(
                constraints: const BoxConstraints(minHeight: 44),
                padding: const EdgeInsets.symmetric(
                  horizontal: 14,
                  vertical: 9,
                ),
                decoration: BoxDecoration(
                  color: scheme.secondary,
                  borderRadius: BorderRadius.circular(24),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Flexible(
                      child: Text(
                        cta,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.labelMedium?.copyWith(
                          color: scheme.onSecondary,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                    const SizedBox(width: 6),
                    Icon(
                      PhosphorIconsBold.arrowRight,
                      size: 16,
                      color: scheme.onSecondary,
                    ),
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
