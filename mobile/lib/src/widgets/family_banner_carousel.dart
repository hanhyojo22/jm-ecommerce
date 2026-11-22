import 'package:flutter/material.dart';
import 'package:phosphor_icons/phosphor_icons.dart';

import '../theme.dart';

class FamilyBannerCarousel extends StatefulWidget {
  const FamilyBannerCarousel({super.key, required this.onShop});

  final VoidCallback onShop;

  @override
  State<FamilyBannerCarousel> createState() => _FamilyBannerCarouselState();
}

class _FamilyBannerCarouselState extends State<FamilyBannerCarousel> {
  static const _banners = [
    (
      asset: 'assets/home/family-care-banner-logo.webp',
      name: 'Family care',
      headline: 'Care for the whole family.',
    ),
    (
      asset: 'assets/home/morning-joy-banner-logo.webp',
      name: 'Morning joy',
      headline: 'Little routines. Big love.',
    ),
    (
      asset: 'assets/home/family-moments-banner-logo.webp',
      name: 'Family moments',
      headline: 'More happy moments.',
    ),
    (
      asset: 'assets/home/bedtime-comfort-banner-logo.webp',
      name: 'Bedtime comfort',
      headline: 'Comfort in every moment.',
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

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final slab = (fill: scheme.surfaceContainerLow, ink: scheme.primary);
    final scaler = MediaQuery.textScalerOf(context);
    final largeText = scaler.scale(14) > 17.5;
    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 720),
        child: Material(
          color: slab.fill,
          borderRadius: BorderRadius.circular(20),
          clipBehavior: Clip.antiAlias,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              LayoutBuilder(
                builder: (context, constraints) => ConstrainedBox(
                  constraints: BoxConstraints(
                    minHeight: (constraints.maxWidth / 1.95).clamp(
                      184.0,
                      280.0,
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
                                  '${banner.headline} Shop essentials. '
                                  'Banner ${index + 1} of ${_banners.length}.',
                              excludeSemantics: true,
                              onTap: widget.onShop,
                              child: Stack(
                                fit: StackFit.expand,
                                children: [
                                  Image.asset(
                                    banner.asset,
                                    fit: BoxFit.cover,
                                    alignment: Alignment.centerRight,
                                  ),
                                  // Cover the image's baked-in copy; keep the family photo visible.
                                  DecoratedBox(
                                    decoration: BoxDecoration(
                                      gradient: LinearGradient(
                                        colors: largeText
                                            ? [slab.fill, slab.fill]
                                            : [
                                                slab.fill,
                                                slab.fill,
                                                slab.fill.withValues(
                                                  alpha: 0.3,
                                                ),
                                                slab.fill.withValues(
                                                  alpha: 0.02,
                                                ),
                                              ],
                                        stops: largeText
                                            ? null
                                            : const [0, 0.5, 0.85, 1],
                                      ),
                                    ),
                                  ),
                                  Material(
                                    type: MaterialType.transparency,
                                    child: InkWell(
                                      onTap: widget.onShop,
                                      child: _BannerCopy(
                                        name: banner.name,
                                        headline: banner.headline,
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
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Row(
                  children: [
                    Expanded(
                      child: ExcludeSemantics(
                        child: Text(
                          '${(_active + 1).toString().padLeft(2, '0')} / 04',
                          style: theme.textTheme.labelSmall?.copyWith(
                            color: slab.ink.withValues(alpha: 0.8),
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
                                width: _active == index ? 24 : 8,
                                height: 4,
                                decoration: BoxDecoration(
                                  color: _active == index
                                      ? scheme.primary
                                      : slab.ink.withValues(alpha: 0.3),
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
    );
  }
}

class _BannerCopy extends StatelessWidget {
  const _BannerCopy({
    required this.name,
    required this.headline,
    required this.largeText,
  });
  final String name;
  final String headline;
  final bool largeText;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final slab = (fill: scheme.surfaceContainerLow, ink: scheme.primary);
    final dark = scheme.brightness == Brightness.dark;
    final ctaFill = dark ? scheme.tertiaryContainer : sunWash;
    final ctaInk = dark ? scheme.onTertiaryContainer : scheme.primary;
    return Align(
      alignment: AlignmentDirectional.centerStart,
      child: FractionallySizedBox(
        widthFactor: largeText ? 1 : 0.64,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(PhosphorIconsFill.heart, size: 12, color: slab.ink),
                  const SizedBox(width: 6),
                  Flexible(
                    child: Text(
                      name.toUpperCase(),
                      style: theme.textTheme.labelSmall?.copyWith(
                        color: slab.ink.withValues(alpha: 0.85),
                        letterSpacing: 1,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                headline,
                style: theme.textTheme.headlineSmall?.copyWith(
                  color: slab.ink,
                  height: 1.12,
                ),
              ),
              const SizedBox(height: 16),
              Container(
                constraints: const BoxConstraints(minHeight: 48),
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 10,
                ),
                decoration: BoxDecoration(
                  color: ctaFill,
                  borderRadius: BorderRadius.circular(24),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Flexible(
                      child: Text(
                        'Shop essentials',
                        style: theme.textTheme.labelMedium?.copyWith(
                          color: ctaInk,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                    const SizedBox(width: 6),
                    Icon(PhosphorIconsBold.arrowRight, size: 16, color: ctaInk),
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
