import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
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

/// The Shop header's branch line: which branch stock and prices come from.
/// Tapping it opens the branch picker. Drawn in [ink] on the brand band.
class BranchHeader extends ConsumerWidget {
  const BranchHeader({super.key, required this.ink});

  final Color ink;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final locator = ref.watch(nearestStoreProvider);
    final state = locator.value;
    if (state != null && state.branches.isEmpty) {
      return _Wordmark(ink: ink);
    }

    final active = state?.activeBranch;
    final String label;
    final String title;
    VoidCallback? onTap = () => showBranchSelectorSheet(context);
    if (state != null && active != null) {
      final distanceKm = active.distanceKm;
      final opens = active.branch.isOpen ? null : active.branch.nextOpeningAt;
      // Most important first, since a narrow screen cuts the end off. The
      // branch name already carries the brand, so the line skips it.
      label = [
        if (opens != null) 'Opens ${opensLabel(opens)}',
        if (active.isOutOfDeliveryRange) 'Pickup only',
        if (distanceKm != null) '${distanceKm.toStringAsFixed(1)} km',
        state.selectedBranchId != null ? 'Your branch' : 'Nearest branch',
      ].join(' · ');
      title = active.branch.name;
    } else if (state != null) {
      label = 'Everyday Pharmacy';
      title = 'Choose a branch';
    } else if (locator.hasError) {
      label = 'Could not load branches';
      title = 'Tap to retry';
      onTap = () => ref.invalidate(storeBranchesProvider);
    } else {
      label = 'Everyday Pharmacy';
      title = 'Finding your nearest branch…';
      onTap = null;
    }
    final scheme = theme.colorScheme;
    final dark = scheme.brightness == Brightness.dark;
    // Forecast tiles are white cards on the sky, so they take navy ink.
    final tileInk = dark ? scheme.onSurface : scheme.primary;
    final tiles = active == null
        ? null
        : _forecast(context, active, state!.selectedBranchId != null);

    return Semantics(
      button: onTap != null,
      label: [
        title,
        if (tiles != null)
          for (final tile in tiles) '${tile.title} ${tile.detail}'
        else
          label,
      ].join(', '),
      hint: onTap == null ? null : 'Change branch',
      excludeSemantics: true,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        splashColor: ink.withValues(alpha: 0.12),
        highlightColor: ink.withValues(alpha: 0.08),
        child: Padding(
          padding: const EdgeInsetsDirectional.fromSTEB(2, 2, 2, 4),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                children: [
                  Expanded(
                    // The caret is a font glyph after a no-break space, so it
                    // wraps together with the last word of the name.
                    child: Text.rich(
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
                                  dimension: 16,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                    color: ink,
                                  ),
                                ),
                              ),
                            )
                          else if (!locator.hasError)
                            TextSpan(
                              text:
                                  '\u00A0${String.fromCharCode(PhosphorIconsBold.caretDown.codePoint)}',
                              style: TextStyle(
                                fontFamily:
                                    PhosphorIconsBold.caretDown.fontFamily,
                                package:
                                    PhosphorIconsBold.caretDown.fontPackage,
                                fontSize: 16,
                                letterSpacing: 0,
                              ),
                            ),
                        ],
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleLarge?.copyWith(
                        color: ink,
                        fontWeight: FontWeight.w800,
                        letterSpacing: -0.5,
                        height: 1.15,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  // The logo's sun and cloud: today's forecast for this branch.
                  ExcludeSemantics(
                    child: Image.asset(
                      'assets/branding/everyday-emblem.webp',
                      height: 34,
                    ),
                  ),
                ],
              ),
              if (tiles == null)
                Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Text(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.labelMedium?.copyWith(
                      color: ink.withValues(alpha: 0.78),
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                )
              else
                Padding(
                  padding: const EdgeInsets.only(top: 12),
                  child: Row(
                    children: [
                      for (final (index, tile) in tiles.indexed) ...[
                        if (index > 0) const SizedBox(width: 8),
                        Expanded(
                          child: _ForecastTile(tile: tile, ink: tileInk),
                        ),
                      ],
                    ],
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }

  /// Open status, distance and delivery for the active branch, most
  /// important first, each a glyph with a short title and detail.
  List<_Forecast> _forecast(
    BuildContext context,
    BranchDistance active,
    bool picked,
  ) {
    final scheme = Theme.of(context).colorScheme;
    final branch = active.branch;
    final km = active.distanceKm;
    final opens = branch.nextOpeningAt;
    final fee = double.tryParse(branch.deliveryFee ?? '') ?? 0;
    final minimum = branch.freeDeliveryMinimum;
    final which = picked ? 'Your branch' : 'Nearest branch';

    return [
      branch.isOpen
          ? _Forecast(
              icon: PhosphorIconsFill.sun,
              glyph: scheme.secondary,
              title: 'Open',
              titleColour: statusColour(context, 'completed'),
              detail: branch.open24Hours ? '24 hours' : 'now',
            )
          : _Forecast(
              icon: PhosphorIconsFill.moon,
              glyph: scheme.error,
              title: 'Closed',
              titleColour: scheme.error,
              detail: opens == null
                  ? 'for today'
                  : 'Opens ${opensLabel(opens)}',
            ),
      _Forecast(
        icon: PhosphorIconsFill.cloud,
        glyph: const Color(0xFF8DB6F3),
        title: km == null ? which : '${km.toStringAsFixed(1)} km',
        detail: active.isOutOfDeliveryRange
            ? 'Pickup only'
            : km == null
            ? 'Distance unknown'
            : which,
      ),
      active.isOutOfDeliveryRange
          ? const _Forecast(
              icon: PhosphorIconsFill.storefront,
              title: 'Pickup',
              detail: 'Too far to deliver',
            )
          : _Forecast(
              icon: PhosphorIconsFill.truck,
              title: fee > 0 ? money(branch.deliveryFee) : 'Free',
              detail: fee > 0
                  ? (minimum == null
                        ? 'Delivery fee'
                        : 'Free over ${money(minimum)}')
                  : 'Delivery',
            ),
    ];
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

/// One white forecast tile on the sky: glyph, big title, small detail.
class _ForecastTile extends StatelessWidget {
  const _ForecastTile({required this.tile, required this.ink});

  final _Forecast tile;
  final Color ink;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return DecoratedBox(
      decoration: BoxDecoration(
        color: theme.colorScheme.surfaceContainerLowest,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(10, 10, 8, 10),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(tile.icon, size: 26, color: tile.glyph ?? ink),
            const SizedBox(height: 6),
            Text(
              tile.title,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.titleSmall?.copyWith(
                fontSize: 15,
                fontWeight: FontWeight.w800,
                color: tile.titleColour ?? ink,
              ),
            ),
            Text(
              tile.detail,
              // Room for "Free over ₱500.00" or large text without cutting it.
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.labelSmall?.copyWith(
                fontWeight: FontWeight.w600,
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// "Everyday Pharmacy", for when no branch is pinned yet.
class _Wordmark extends StatelessWidget {
  const _Wordmark({required this.ink});

  final Color ink;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsetsDirectional.only(start: 4, top: 6, bottom: 6),
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
                  fontWeight: FontWeight.w500,
                  color: ink.withValues(alpha: 0.72),
                ),
              ),
            ],
          ),
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
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
          child: Text(
            'Choose a branch',
            style: Theme.of(context).textTheme.titleMedium,
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
