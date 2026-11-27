import 'dart:ui' show lerpDouble;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:go_router/go_router.dart';
import 'package:phosphor_icons/phosphor_icons.dart';

import '../core/formatters.dart';
import '../core/location_service.dart';
import '../models/store_branch.dart';
import '../providers/store_locator_providers.dart';
import '../theme.dart';

String _distanceLabel(double km) => '${km.toStringAsFixed(1)} km away';

/// "Open 24 hours", "Open", or "Closed · opens 8:00 AM".
String _hoursLabel(BranchDistance item) {
  final branch = item.branch;
  if (!branch.isOpen) {
    final opens = branch.nextOpeningAt;
    return opens == null ? 'Closed' : 'Closed · opens ${opensLabel(opens)}';
  }
  return branch.open24Hours ? 'Open 24 hours' : 'Open';
}

/// "Everyday Pharmacy Cebu Capitol" -> "Cebu Capitol" for the header, where
/// the logo beside it already says Everyday Pharmacy. Semantics keep the full name.
// shortcut: the brand prefix is fixed text; read the company name from the API if branches ever carry another brand.
String _withoutBrand(String name) {
  const brand = 'Everyday Pharmacy ';
  return name.startsWith(brand) && name.length > brand.length
      ? name.substring(brand.length)
      : name;
}

String _feeLabel(StoreBranch branch) {
  final fee = double.tryParse(branch.deliveryFee ?? '') ?? 0;
  final minimum = branch.freeDeliveryMinimum;
  return [
    fee > 0 ? '${money(branch.deliveryFee)} delivery' : 'Free delivery',
    if (fee > 0 && minimum != null) 'free over ${money(minimum)}',
  ].join(', ');
}

/// Collapsible Option B Sky Horizon header for the Shop tab: shows the active
/// branch and inline fulfillment summary at the top of the feed, then smoothly
/// collapses to a pinned search bar on scroll.
class SliverShopHeaderDelegate extends SliverPersistentHeaderDelegate {
  const SliverShopHeaderDelegate({
    required this.topPadding,
    required this.viewportWidth,
    required this.textScaler,
    required this.barHeight,
    required this.slab,
    required this.theme,
    required this.locator,
    required this.searchBar,
    required this.reducedMotion,
    required this.refreshing,
    required this.dockBottomRail,
    required this.forceFlatBottom,
    this.compactOnly = false,
    required this.onTapBranch,
    required this.onRetryBranches,
  });

  final double topPadding;
  final double viewportWidth;
  final TextScaler textScaler;
  final double barHeight;
  final ({Color fill, Color ink}) slab;
  final ThemeData theme;
  final AsyncValue<StoreLocatorState> locator;
  final Widget searchBar;
  final bool reducedMotion;
  final bool refreshing;

  /// True when a pinned quick-actions bar sits below and will fuse with this band.
  final bool dockBottomRail;

  /// True when the docked quick-actions bar is already compact directly below at offset 0.
  final bool forceFlatBottom;
  final bool compactOnly;
  final VoidCallback onTapBranch;
  final VoidCallback onRetryBranches;

  TextScaler get _clampedScaler => textScaler.clamp(maxScaleFactor: 1.6);

  bool get _noBranches {
    final state = locator.value;
    return state != null && state.branches.isEmpty;
  }

  bool get _hasActiveBranch => locator.value?.activeBranch != null;

  double get _branchRowHeight => _clampedScaler.scale(48).clamp(48.0, 72.0);

  double get _stripHeight => _clampedScaler.scale(48).clamp(48.0, 84.0);

  double get _wordmarkHeight => _clampedScaler.scale(36).clamp(36.0, 52.0);

  double get _topSectionHeight {
    if (_noBranches) {
      return _wordmarkHeight;
    }
    if (_hasActiveBranch) {
      return _branchRowHeight + 8.0 + _stripHeight;
    }
    return _branchRowHeight;
  }

  double get _collapsedBottomInset => dockBottomRail ? 8.0 : 10.0;

  double get collapseRange =>
      (maxExtent - minExtent).clamp(0.0, double.infinity);

  @override
  double get minExtent => topPadding + 8.0 + barHeight + _collapsedBottomInset;

  @override
  double get maxExtent => compactOnly
      ? minExtent
      : topPadding + 10.0 + _topSectionHeight + 10.0 + barHeight + 12.0;

  @override
  Widget build(
    BuildContext context,
    double shrinkOffset,
    bool overlapsContent,
  ) {
    final delta = maxExtent - minExtent;
    final rawT = delta <= 0 ? 1.0 : (shrinkOffset / delta).clamp(0.0, 1.0);

    final state = locator.value;
    final active = state?.activeBranch;
    final String label;
    final String title;
    VoidCallback? branchAction = onTapBranch;
    if (state != null && active != null) {
      label = state.selectedBranchId != null ? 'Your branch' : 'Nearest branch';
      title = active.branch.name;
    } else if (state != null) {
      label = 'Everyday Pharmacy';
      title = 'Choose a branch';
    } else if (locator.hasError) {
      // Calm: the page below carries the error and its Try again; a tap here still retries.
      label = 'Branches not loaded';
      title = 'Everyday Pharmacy';
      branchAction = onRetryBranches;
    } else {
      label = 'Everyday Pharmacy';
      title = 'Finding your nearest branch…';
      branchAction = null;
    }

    final scheme = theme.colorScheme;
    final dark = scheme.brightness == Brightness.dark;
    final onDarkSlab =
        ThemeData.estimateBrightnessForColor(slab.fill) == Brightness.dark;
    final tileInk = onDarkSlab
        ? slab.ink
        : (dark ? scheme.onSurface : scheme.primary);
    final tiles = active == null
        ? null
        : _forecast(
            context,
            theme,
            active,
            state!.selectedBranchId != null,
            onDarkSlab: onDarkSlab,
          );

    final bottomPad = lerpDouble(12.0, _collapsedBottomInset, rawT)!;
    final double bottomRadius;
    if (forceFlatBottom) {
      bottomRadius = 0;
    } else if (dockBottomRail) {
      bottomRadius = lerpDouble(
        22.0,
        0.0,
        ((rawT - 0.50) / 0.50).clamp(0.0, 1.0),
      )!;
    } else {
      bottomRadius = lerpDouble(22.0, 14.0, rawT)!;
    }

    final topOpacity = reducedMotion
        ? (rawT >= 0.5 ? 0.0 : 1.0)
        : (1.0 - rawT * 1.75).clamp(0.0, 1.0);
    final topSlide = reducedMotion ? 0.0 : -14.0 * rawT;
    final topScale = reducedMotion ? 1.0 : lerpDouble(1.0, 0.97, rawT)!;

    final double shadowT = (dockBottomRail || forceFlatBottom)
        ? 0.0
        : (overlapsContent ? 1.0 : ((rawT - 0.75) / 0.25).clamp(0.0, 1.0));

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value:
          (onDarkSlab ? SystemUiOverlayStyle.light : SystemUiOverlayStyle.dark)
              .copyWith(statusBarColor: Colors.transparent),
      child: DecoratedBox(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.vertical(
            bottom: Radius.circular(bottomRadius),
          ),
          boxShadow: shadowT > 0
              ? [
                  BoxShadow(
                    color: (dark || onDarkSlab ? Colors.black : scheme.primary)
                        .withValues(
                          alpha: (dark ? 0.28 : 0.14) * shadowT,
                        ),
                    blurRadius: 12,
                    offset: const Offset(0, 4),
                  ),
                ]
              : const [],
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.vertical(
            bottom: Radius.circular(bottomRadius),
          ),
          child: Material(
            color: slab.fill,
            child: MediaQuery(
              data: MediaQuery.of(context).copyWith(textScaler: _clampedScaler),
              child: Stack(
                fit: StackFit.expand,
                children: [
                  if (topOpacity > 0.02)
                    Positioned(
                      left: 16,
                      right: 16,
                      top: topPadding + 10,
                      bottom: bottomPad + barHeight + 6,
                      child: IgnorePointer(
                        ignoring: rawT > 0.45,
                        child: ExcludeSemantics(
                          excluding: rawT > 0.45,
                          child: ClipRect(
                            child: OverflowBox(
                              alignment: Alignment.topCenter,
                              minHeight: 0,
                              maxHeight: _topSectionHeight,
                              child: Opacity(
                                opacity: topOpacity,
                                child: Transform.translate(
                                  offset: Offset(0, topSlide),
                                  child: Transform.scale(
                                    scale: topScale,
                                    alignment: Alignment.topCenter,
                                    child: _noBranches
                                        ? Align(
                                            alignment: AlignmentDirectional
                                                .centerStart,
                                            child: _Wordmark(ink: slab.ink),
                                          )
                                        : _ExpandedBranchSection(
                                            ink: slab.ink,
                                            tileInk: tileInk,
                                            onDarkSlab: onDarkSlab,
                                            title: title,
                                            label: label,
                                            hasError: locator.hasError,
                                            onTap: branchAction,
                                            tiles: tiles,
                                            stripHeight: _stripHeight,
                                            compactCols:
                                                viewportWidth < 360 ||
                                                _clampedScaler.scale(12) > 15,
                                          ),
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  Positioned(
                    left: 16,
                    right: 16,
                    bottom: bottomPad,
                    height: barHeight,
                    child: MediaQuery(
                      data: MediaQuery.of(context)
                          .copyWith(textScaler: textScaler),
                      child: searchBar,
                    ),
                  ),
                  if (refreshing)
                    Positioned(
                      left: 0,
                      right: 0,
                      bottom: 0,
                      child: LinearProgressIndicator(
                        minHeight: 2,
                        semanticsLabel: 'Updating products',
                        color: onDarkSlab ? scheme.secondary : scheme.primary,
                        backgroundColor: onDarkSlab
                            ? Colors.white.withValues(alpha: 0.22)
                            : scheme.surfaceContainerHighest,
                      ),
                    ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  /// Open status, distance and delivery for the active branch, most
  /// important first, each a glyph with a short title and detail.
  static List<_Forecast> _forecast(
    BuildContext context,
    ThemeData theme,
    BranchDistance active,
    bool picked, {
    required bool onDarkSlab,
  }) {
    final scheme = theme.colorScheme;
    final brandColors = AppBrandColors.of(context);
    final openColour = onDarkSlab
        ? brandColors.openOnHeader
        : brandColors.careTeal;
    final closedColour = onDarkSlab ? brandColors.closedOnHeader : scheme.error;
    final branch = active.branch;
    final km = active.distanceKm;
    final opens = branch.nextOpeningAt;
    final fee = double.tryParse(branch.deliveryFee ?? '') ?? 0;
    final minimum = branch.freeDeliveryMinimum;
    final which = picked ? 'Your branch' : 'Nearest branch';

    return [
      branch.isOpen
          ? _Forecast(
              icon: PhosphorIconsRegular.sun,
              glyph: onDarkSlab ? brandColors.sunGold : null,
              title: 'Open',
              titleColour: openColour,
              detail: branch.open24Hours ? '24 hours' : 'now',
            )
          : _Forecast(
              icon: PhosphorIconsRegular.moon,
              glyph: closedColour,
              title: 'Closed',
              titleColour: closedColour,
              detail: opens == null
                  ? 'for today'
                  : 'Opens ${opensLabel(opens)}',
            ),
      _Forecast(
        icon: PhosphorIconsRegular.navigationArrow,
        title: km == null ? which : '${km.toStringAsFixed(1)} km',
        detail: active.isOutOfDeliveryRange
            ? 'Pickup only'
            : km == null
            ? 'Distance unknown'
            : 'From you',
      ),
      active.isOutOfDeliveryRange
          ? const _Forecast(
              icon: PhosphorIconsRegular.storefront,
              title: 'Pickup',
              detail: 'Too far to deliver',
            )
          : _Forecast(
              icon: PhosphorIconsRegular.truck,
              title: fee > 0 ? money(branch.deliveryFee) : 'Free',
              detail: fee > 0
                  ? (minimum == null
                        ? 'Delivery fee'
                        : 'Free over ${money(minimum)}')
                  : 'Delivery',
            ),
    ];
  }

  @override
  bool shouldRebuild(covariant SliverShopHeaderDelegate oldDelegate) =>
      topPadding != oldDelegate.topPadding ||
      viewportWidth != oldDelegate.viewportWidth ||
      textScaler != oldDelegate.textScaler ||
      barHeight != oldDelegate.barHeight ||
      slab != oldDelegate.slab ||
      theme != oldDelegate.theme ||
      locator != oldDelegate.locator ||
      searchBar != oldDelegate.searchBar ||
      reducedMotion != oldDelegate.reducedMotion ||
      refreshing != oldDelegate.refreshing ||
      dockBottomRail != oldDelegate.dockBottomRail ||
      forceFlatBottom != oldDelegate.forceFlatBottom ||
      compactOnly != oldDelegate.compactOnly;
}

class _ExpandedBranchSection extends StatelessWidget {
  const _ExpandedBranchSection({
    required this.ink,
    required this.tileInk,
    required this.onDarkSlab,
    required this.title,
    required this.label,
    required this.hasError,
    required this.onTap,
    required this.tiles,
    required this.stripHeight,
    required this.compactCols,
  });

  final Color ink;
  final Color tileInk;
  final bool onDarkSlab;
  final String title;
  final String label;
  final bool hasError;
  final VoidCallback? onTap;
  final List<_Forecast>? tiles;
  final double stripHeight;
  final bool compactCols;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final dark = scheme.brightness == Brightness.dark;
    final pinColor = onDarkSlab ? scheme.secondary : ink;
    final stripFill = onDarkSlab
        ? Colors.white.withValues(alpha: dark ? 0.10 : 0.14)
        : scheme.surfaceContainerLowest.withValues(alpha: dark ? 0.78 : 0.92);
    final stripBorder = onDarkSlab
        ? Colors.white.withValues(alpha: 0.22)
        : (dark
              ? scheme.outlineVariant.withValues(alpha: 0.75)
              : scheme.primary.withValues(alpha: 0.10));
    final dividerColor = onDarkSlab
        ? Colors.white.withValues(alpha: 0.22)
        : scheme.outlineVariant;
    final detailColor = onDarkSlab
        ? ink.withValues(alpha: 0.84)
        : scheme.onSurfaceVariant;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Semantics(
          button: onTap != null,
          label: 'Store location, $title, $label',
          hint: onTap == null ? null : 'Change branch',
          excludeSemantics: true,
          child: InkWell(
            onTap: onTap,
            borderRadius: BorderRadius.circular(14),
            splashColor: ink.withValues(alpha: 0.14),
            highlightColor: ink.withValues(alpha: 0.08),
            child: ConstrainedBox(
              constraints: const BoxConstraints(minHeight: 48),
              child: Padding(
                padding: const EdgeInsetsDirectional.fromSTEB(2, 3, 2, 3),
                child: Row(
                  children: [
                    Icon(PhosphorIconsFill.mapPin, size: 19, color: pinColor),
                    const SizedBox(width: 9),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text.rich(
                            TextSpan(
                              text: _withoutBrand(title),
                              children: [
                                if (onTap == null)
                                  WidgetSpan(
                                    alignment: PlaceholderAlignment.middle,
                                    child: Padding(
                                      padding: const EdgeInsetsDirectional.only(
                                        start: 6,
                                      ),
                                      child: SizedBox.square(
                                        dimension: 15,
                                        child: CircularProgressIndicator(
                                          strokeWidth: 2,
                                          color: ink,
                                        ),
                                      ),
                                    ),
                                  )
                                else if (!hasError)
                                  TextSpan(
                                    text:
                                        '\u00A0${String.fromCharCode(PhosphorIconsBold.caretDown.codePoint)}',
                                    style: TextStyle(
                                      fontFamily: PhosphorIconsBold
                                          .caretDown
                                          .fontFamily,
                                      package: PhosphorIconsBold
                                          .caretDown
                                          .fontPackage,
                                      fontSize: 12,
                                      letterSpacing: 0,
                                    ),
                                  ),
                              ],
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.titleLarge?.copyWith(
                              fontSize: 18.5,
                              color: ink,
                              fontWeight: FontWeight.w800,
                              letterSpacing: -0.35,
                              height: 1.15,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            label,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.labelSmall?.copyWith(
                              fontSize: 11.5,
                              color: ink.withValues(alpha: 0.84),
                              fontWeight: FontWeight.w600,
                              letterSpacing: 0,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    ExcludeSemantics(
                      child: _UnifiedEmblemBadge(
                        ink: ink,
                        onDarkSlab: onDarkSlab,
                        compact: compactCols,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
        if (tiles != null) ...[
          const SizedBox(height: 8),
          Material(
            color: stripFill,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
              side: BorderSide(color: stripBorder),
            ),
            clipBehavior: Clip.antiAlias,
            child: SizedBox(
              height: stripHeight,
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  for (final (index, tile) in tiles!.indexed) ...[
                    if (index > 0)
                      VerticalDivider(
                        width: 1,
                        thickness: 1,
                        indent: 9,
                        endIndent: 9,
                        color: dividerColor,
                      ),
                    Expanded(
                      child: Semantics(
                        button: true,
                        label: '${tile.title}, ${tile.detail}',
                        hint: 'Change branch',
                        excludeSemantics: true,
                        child: InkWell(
                          onTap: onTap,
                          child: Padding(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 4,
                            ),
                            child: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              crossAxisAlignment: CrossAxisAlignment.start,
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Row(
                                  children: [
                                    if (!compactCols) ...[
                                      Icon(
                                        tile.icon,
                                        size: 14,
                                        color: tile.glyph ?? tileInk,
                                      ),
                                      const SizedBox(width: 4),
                                    ],
                                    Expanded(
                                      child: Text(
                                        tile.title,
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                        style: theme.textTheme.titleSmall
                                            ?.copyWith(
                                              fontSize: 12.5,
                                              fontWeight: FontWeight.w800,
                                              height: 1.12,
                                              color:
                                                  tile.titleColour ?? tileInk,
                                            ),
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 1),
                                Text(
                                  tile.detail,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: theme.textTheme.labelSmall?.copyWith(
                                    fontSize: 11,
                                    fontWeight: FontWeight.w500,
                                    height: 1.12,
                                    letterSpacing: 0,
                                    color: detailColor,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ],
    );
  }
}

/// Compact overlapping sun-and-cloud pill so the 2:1 sprite reads as one emblem.
class _UnifiedEmblemBadge extends StatelessWidget {
  const _UnifiedEmblemBadge({
    required this.ink,
    required this.onDarkSlab,
    required this.compact,
  });

  final Color ink;
  final bool onDarkSlab;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: onDarkSlab
            ? Colors.white.withValues(alpha: 0.14)
            : scheme.surfaceContainerLowest.withValues(alpha: 0.90),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(
          color: onDarkSlab
              ? Colors.white.withValues(alpha: 0.22)
              : scheme.primary.withValues(alpha: 0.14),
        ),
      ),
      child: Padding(
        padding: EdgeInsets.symmetric(horizontal: compact ? 6 : 9, vertical: 4),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            SizedBox(
              width: 28,
              height: 22,
              child: Stack(
                clipBehavior: Clip.none,
                children: [
                  Positioned(
                    left: 0,
                    top: 0,
                    child: SizedBox(
                      width: 18,
                      height: 18,
                      child: ClipRect(
                        child: OverflowBox(
                          alignment: Alignment.centerLeft,
                          minWidth: 36,
                          maxWidth: 36,
                          minHeight: 18,
                          maxHeight: 18,
                          child: Image.asset(
                            'assets/branding/everyday-emblem.webp',
                            width: 36,
                            height: 18,
                            fit: BoxFit.fill,
                          ),
                        ),
                      ),
                    ),
                  ),
                  Positioned(
                    right: 0,
                    bottom: 0,
                    child: SizedBox(
                      width: 20,
                      height: 16,
                      child: ClipRect(
                        child: OverflowBox(
                          alignment: Alignment.centerRight,
                          minWidth: 38,
                          maxWidth: 38,
                          minHeight: 19,
                          maxHeight: 19,
                          child: Image.asset(
                            'assets/branding/everyday-emblem.webp',
                            width: 38,
                            height: 19,
                            fit: BoxFit.fill,
                          ),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            if (!compact) ...[
              const SizedBox(width: 5),
              Text(
                'EVERYDAY',
                maxLines: 1,
                style: TextStyle(
                  fontFamily: appFontFamily,
                  fontSize: 10,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 0.25,
                  height: 1.05,
                  color: ink,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _Forecast {
  const _Forecast({
    required this.icon,
    required this.title,
    required this.detail,
    this.glyph,
    this.titleColour,
  });

  final IconData icon;
  final String title;
  final String detail;

  /// Null draws the glyph in the tile's ink.
  final Color? glyph;
  final Color? titleColour;
}

/// "Everyday Pharmacy", for when no branch is pinned yet.
class _Wordmark extends StatelessWidget {
  const _Wordmark({required this.ink});

  final Color ink;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsetsDirectional.only(start: 4, top: 4, bottom: 4),
      child: Semantics(
        header: true,
        child: Text.rich(
          TextSpan(
            children: [
              // Two-tone like the logo: sun-yellow "Everyday".
              TextSpan(
                text: 'Everyday',
                style: TextStyle(
                  color: Theme.of(context).colorScheme.secondary,
                ),
              ),
              TextSpan(
                text: '  Pharmacy',
                style: TextStyle(
                  fontWeight: FontWeight.w600,
                  color: ink.withValues(alpha: 0.92),
                ),
              ),
            ],
          ),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: Theme.of(context).textTheme.titleLarge?.copyWith(
            color: ink,
            fontWeight: FontWeight.w800,
            letterSpacing: -0.5,
          ),
        ),
      ),
    );
  }
}

/// Shown in the Shop when no branch is known: why, and how to fix it.
class LocationFallbackCard extends ConsumerWidget {
  const LocationFallbackCard({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final issue = ref.watch(nearestStoreProvider).value?.issue;
    if (issue == null) {
      return const SizedBox.shrink();
    }

    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 14),
        child: _LocationFallback(issue: issue),
      ),
    );
  }
}

class _LocationFallback extends ConsumerWidget {
  const _LocationFallback({required this.issue});

  final LocationIssue issue;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final message = switch (issue) {
      LocationIssue.serviceDisabled =>
        'Turn on location to find your nearest branch.',
      LocationIssue.denied =>
        'Allow location access to find your nearest branch.',
      // Android no longer shows its dialog after a second "Don't allow".
      LocationIssue.deniedForever => 'Location is off for Everyday Pharmacy. In Settings, tap Permissions, then Location, then Allow.',
      LocationIssue.unavailable => 'We could not get your location.',
    };

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(
              Icons.location_off_outlined,
              color: theme.colorScheme.onSurfaceVariant,
            ),
            const SizedBox(width: 12),
            Expanded(child: Text(message, style: theme.textTheme.bodyMedium)),
            IconButton(
              tooltip: 'Try again',
              onPressed: () => ref.invalidate(nearestStoreProvider),
              icon: const Icon(Icons.refresh),
            ),
          ],
        ),
        const SizedBox(height: 8),
        Wrap(
          spacing: 8,
          children: [
            if (issue == LocationIssue.denied)
              FilledButton.tonal(
                onPressed: () => askForLocation(context, ref),
                child: const Text('Allow location'),
              )
            else if (issue != LocationIssue.unavailable)
              FilledButton.tonal(
                onPressed: issue == LocationIssue.serviceDisabled
                    ? Geolocator.openLocationSettings
                    : Geolocator.openAppSettings,
                child: const Text('Open settings'),
              ),
            OutlinedButton(
              onPressed: () => showBranchSelectorSheet(context),
              child: const Text('Pick a branch'),
            ),
          ],
        ),
      ],
    );
  }
}

bool _askedThisLaunch = false;

/// Explains why JMMart wants location, then shows Android's dialog, where the
/// customer picks Precise or Approximate. True when location is allowed.
///
/// [automatic] prompts (opening the Shop or checkout) show at most once per
/// launch; a tap on "Allow location" always asks. After a second "Don't allow"
/// Android stops showing its dialog, so this only offers Settings then.
// shortcut: "once per launch" is kept in memory; persist it if customers find it repetitive.
Future<bool> askForLocation(
  BuildContext context,
  WidgetRef ref, {
  bool automatic = false,
}) async {
  final service = ref.read(locationServiceProvider);
  final permission = await service.permission();
  if (permission == LocationPermission.whileInUse ||
      permission == LocationPermission.always) {
    return true;
  }
  // Android shows no dialog any more: a tap goes to the app's settings page.
  if (permission == LocationPermission.deniedForever && !automatic) {
    await service.openAppSettings();
    return false;
  }
  // Only "denied" can still be asked: not "deniedForever", nor a platform
  // that cannot tell.
  if (permission != LocationPermission.denied ||
      (automatic && _askedThisLaunch) ||
      !context.mounted) {
    return false;
  }
  _askedThisLaunch = true;
  final allow = await showModalBottomSheet<bool>(
    context: context,
    showDragHandle: true,
    useSafeArea: true,
    // Taller than the default half-screen cap with large text or in landscape.
    isScrollControlled: true,
    builder: (context) => const SingleChildScrollView(child: _LocationPrimer()),
  );
  if (allow != true) {
    return false;
  }
  final asked = DateTime.now();
  final answer = await service.requestPermission();
  // Either answer changes the branch card: a distance, or "Allow location in Settings".
  ref.invalidate(nearestStoreProvider);
  // An instant "blocked" means Android showed no dialog (blocked before this
  // launch), so Settings is the only way left; a real "Don't allow" tap is slower.
  // shortcut: timing is the only signal without storing state; persist the block to drop it.
  if (answer == LocationPermission.deniedForever &&
      DateTime.now().difference(asked) < const Duration(milliseconds: 600)) {
    await service.openAppSettings();
  }
  return answer == LocationPermission.whileInUse ||
      answer == LocationPermission.always;
}

/// The "why" screen shown before Android's location dialog.
class _LocationPrimer extends StatelessWidget {
  const _LocationPrimer();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 0, 24, 20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Center(
            child: Container(
              width: 72,
              height: 72,
              decoration: BoxDecoration(
                color: scheme.primaryContainer,
                shape: BoxShape.circle,
              ),
              child: Icon(
                PhosphorIconsFill.mapPin,
                size: 34,
                color: scheme.onPrimaryContainer,
              ),
            ),
          ),
          const SizedBox(height: 18),
          Semantics(
            header: true,
            child: Text(
              'Find your nearest branch',
              textAlign: TextAlign.center,
              style: theme.textTheme.headlineSmall,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Everyday Pharmacy uses your location to show the closest branch, what it has '
            'in stock and its delivery fee, and to pin your delivery address.',
            textAlign: TextAlign.center,
            style: theme.textTheme.bodyMedium?.copyWith(
              color: scheme.onSurfaceVariant,
            ),
          ),
          const SizedBox(height: 16),
          for (final (icon, text) in const [
            (
              PhosphorIconsRegular.crosshair,
              'Precise puts the rider at your door.',
            ),
            (
              PhosphorIconsRegular.mapTrifold,
              'Approximate is enough to pick a branch.',
            ),
          ])
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Row(
                children: [
                  Icon(icon, size: 20, color: scheme.primary),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(text, style: theme.textTheme.bodyMedium),
                  ),
                ],
              ),
            ),
          const SizedBox(height: 12),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Allow location'),
          ),
          const SizedBox(height: 4),
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Not now'),
          ),
        ],
      ),
    );
  }
}

Future<void> showBranchSelectorSheet(BuildContext context) {
  return showModalBottomSheet<void>(
    context: context,
    showDragHandle: true,
    useSafeArea: true,
    builder: (_) => const _BranchSelectorSheet(),
  );
}

class _BranchSelectorSheet extends ConsumerWidget {
  const _BranchSelectorSheet();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(nearestStoreProvider).value;
    if (state == null) {
      return const SizedBox.shrink();
    }
    final notifier = ref.read(nearestStoreProvider.notifier);
    final activeId = state.activeBranch?.branch.id;
    final issue = state.issue;

    return ListView(
      shrinkWrap: true,
      // useSafeArea only insets the top, so clear the system navigation bar.
      padding: EdgeInsets.only(
        bottom: 16 + MediaQuery.viewPaddingOf(context).bottom,
      ),
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 8, 8),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  'Choose a branch',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
              if (GoRouter.maybeOf(context) != null)
                TextButton.icon(
                  onPressed: () {
                    final router = GoRouter.of(context);
                    Navigator.pop(context);
                    router.push('/branches');
                  },
                  icon: const Icon(PhosphorIconsRegular.mapTrifold, size: 17),
                  label: const Text('View map'),
                ),
            ],
          ),
        ),
        // Distances need location; the sheet stays open so they appear once allowed.
        if (issue != null && issue != LocationIssue.unavailable)
          ListTile(
            leading: const Icon(PhosphorIconsRegular.crosshair),
            title: Text(switch (issue) {
              LocationIssue.serviceDisabled => 'Turn on location',
              LocationIssue.deniedForever => 'Allow location in Settings',
              _ => 'Use my location',
            }),
            onTap: switch (issue) {
              LocationIssue.serviceDisabled => Geolocator.openLocationSettings,
              LocationIssue.deniedForever => Geolocator.openAppSettings,
              _ => () => askForLocation(context, ref),
            },
          ),
        if (state.nearest != null && state.selectedBranchId != null)
          ListTile(
            leading: const Icon(PhosphorIconsRegular.navigationArrow),
            title: const Text('Use nearest branch'),
            onTap: () {
              notifier.useNearest();
              Navigator.pop(context);
            },
          ),
        for (final item in state.branches)
          _BranchOption(
            item: item,
            isActive: item.branch.id == activeId,
            onTap: () {
              notifier.selectBranch(item.branch.id);
              Navigator.pop(context);
            },
          ),
      ],
    );
  }
}

/// One branch in the picker: where it is, then whether it is open and what delivery costs.
class _BranchOption extends StatelessWidget {
  const _BranchOption({
    required this.item,
    required this.isActive,
    required this.onTap,
  });

  final BranchDistance item;
  final bool isActive;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final branch = item.branch;
    final hoursColour = branch.isOpen
        ? statusColour(context, 'completed')
        : scheme.error;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      child: ListTile(
        selected: isActive,
        // The check and tint mark the pick; the details stay readable ink.
        selectedColor: scheme.onSurface,
        selectedTileColor: scheme.secondaryContainer,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        contentPadding: const EdgeInsetsDirectional.only(start: 12, end: 12),
        isThreeLine: true,
        title: Text(
          branch.name,
          style: theme.textTheme.titleSmall?.copyWith(
            fontWeight: FontWeight.w700,
          ),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              [
                branch.address,
                if (item.distanceKm != null) _distanceLabel(item.distanceKm!),
              ].join(' · '),
              style: TextStyle(color: scheme.onSurfaceVariant),
            ),
            const SizedBox(height: 2),
            Text.rich(
              TextSpan(
                children: [
                  TextSpan(
                    text: _hoursLabel(item),
                    style: TextStyle(
                      color: hoursColour,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  // A branch that cannot deliver here has no fee worth showing.
                  if (branch.deliveryFee != null && !item.isOutOfDeliveryRange)
                    TextSpan(text: ' · ${_feeLabel(branch)}'),
                  if (item.isOutOfDeliveryRange)
                    TextSpan(
                      text: ' · Pickup only',
                      style: TextStyle(color: scheme.error),
                    ),
                ],
              ),
              style: TextStyle(color: scheme.onSurfaceVariant),
            ),
          ],
        ),
        trailing: isActive
            ? Icon(PhosphorIconsFill.checkCircle, color: scheme.primary)
            : null,
        onTap: onTap,
      ),
    );
  }
}

/// Shown on a product the active branch has run out of: the nearest branch that
/// has it, with a one-tap switch.
class StockedElsewhereNotice extends ConsumerWidget {
  const StockedElsewhereNotice({super.key, required this.productId});

  final String productId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final elsewhere = ref.watch(stockedElsewhereProvider(productId));

    return switch (elsewhere) {
      AsyncData(value: final BranchDistance other) => Row(
        children: [
          Icon(Icons.storefront_outlined, color: theme.colorScheme.primary),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              [
                'Available at ${other.branch.name}',
                if (other.distanceKm != null) _distanceLabel(other.distanceKm!),
                if (other.isOutOfDeliveryRange) 'Pickup only',
              ].join(' · '),
              style: theme.textTheme.bodyMedium,
            ),
          ),
          TextButton(
            onPressed: () => ref
                .read(nearestStoreProvider.notifier)
                .selectBranch(other.branch.id),
            child: const Text('Switch'),
          ),
        ],
      ),
      AsyncData() => Text(
        'Out of stock at nearby branches.',
        style: theme.textTheme.bodyMedium?.copyWith(
          color: theme.colorScheme.onSurfaceVariant,
        ),
      ),
      _ => const SizedBox.shrink(),
    };
  }
}
