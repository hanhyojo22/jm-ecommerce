import 'package:flutter/material.dart';

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
    final scheme = Theme.of(context).colorScheme;
    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 720),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            AspectRatio(
              aspectRatio: 2,
              child: ClipRRect(
                borderRadius: BorderRadius.circular(appSurfaceRadius),
                child: PageView.builder(
                  controller: _controller,
                  allowImplicitScrolling: true,
                  itemCount: _banners.length,
                  onPageChanged: (index) => setState(() => _active = index),
                  itemBuilder: (context, index) {
                    final banner = _banners[index];
                    return Semantics(
                      button: true,
                      label:
                          '${banner.headline} Shop essentials. '
                          'Banner ${index + 1} of ${_banners.length}.',
                      excludeSemantics: true,
                      onTap: widget.onShop,
                      child: Material(
                        color: scheme.surfaceContainerLow,
                        child: Ink.image(
                          image: AssetImage(banner.asset),
                          fit: BoxFit.contain,
                          child: InkWell(onTap: widget.onShop),
                        ),
                      ),
                    );
                  },
                ),
              ),
            ),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                for (var index = 0; index < _banners.length; index++)
                  Semantics(
                    button: true,
                    selected: _active == index,
                    label: 'Show banner ${index + 1}: ${_banners[index].name}',
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
                                  : scheme.outlineVariant,
                              borderRadius: BorderRadius.circular(2),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
