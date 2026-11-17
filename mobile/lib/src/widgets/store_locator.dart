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

String _feeLabel(StoreBranch branch) {
  final fee = double.tryParse(branch.deliveryFee ?? '') ?? 0;
  final minimum = branch.freeDeliveryMinimum;
  return [
    fee > 0 ? '${money(branch.deliveryFee)} delivery' : 'Free delivery',
    if (fee > 0 && minimum != null) 'free over ${money(minimum)}',
  ].join(', ');
}

/// Recommended branch card, with loading and location-denied fallbacks.
class StoreRecommendationCard extends ConsumerWidget {
  const StoreRecommendationCard({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final locator = ref.watch(nearestStoreProvider);
    if (locator.value?.branches.isEmpty ?? false) {
      return const SizedBox.shrink();
    }

    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 14),
        child: switch (locator) {
          AsyncData(:final value) => switch (value.activeBranch) {
            final BranchDistance active => _Recommendation(
              active: active,
              isManualPick: value.selectedBranchId != null,
            ),
            null => _LocationFallback(issue: value.issue!),
          },
          AsyncError() => _Message(
            text: 'Could not load nearby branches.',
            action: TextButton(
              onPressed: () => ref.invalidate(storeBranchesProvider),
              child: const Text('Retry'),
            ),
          ),
          _ => const _Message(
            text: 'Finding your nearest branch…',
            leading: SizedBox.square(
              dimension: 20,
              child: CircularProgressIndicator(strokeWidth: 2),
            ),
          ),
        },
      ),
    );
  }
}

class _Recommendation extends StatelessWidget {
  const _Recommendation({required this.active, required this.isManualPick});

  final BranchDistance active;
  final bool isManualPick;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final distanceKm = active.distanceKm;
    final open = active.branch.isOpen;
    final hoursColour = open
        ? statusColour(context, 'completed')
        : scheme.error;
    final metaStyle = theme.textTheme.bodySmall?.copyWith(
      color: scheme.onSurfaceVariant,
      fontWeight: FontWeight.w500,
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: scheme.primaryContainer,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(
                PhosphorIconsFill.storefront,
                color: scheme.primary,
                size: 22,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    isManualPick ? 'Your branch' : 'Nearest branch',
                    style: theme.textTheme.labelMedium?.copyWith(
                      color: scheme.onSurfaceVariant,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    active.branch.name,
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                      letterSpacing: -0.3,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            FilledButton.tonal(
              onPressed: () => showBranchSelectorSheet(context),
              style: FilledButton.styleFrom(
                minimumSize: const Size(48, 40),
                padding: const EdgeInsets.symmetric(horizontal: 16),
                tapTargetSize: MaterialTapTargetSize.padded,
                shape: const StadiumBorder(),
              ),
              child: const Text('Change'),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Divider(color: scheme.outlineVariant),
        const SizedBox(height: 10),
        Wrap(
          spacing: 14,
          runSpacing: 6,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 8,
                  height: 8,
                  decoration: BoxDecoration(
                    color: hoursColour,
                    shape: BoxShape.circle,
                  ),
                ),
                const SizedBox(width: 6),
                Flexible(
                  child: Text(
                    _hoursLabel(active),
                    style: theme.textTheme.bodySmall?.copyWith(
                      fontWeight: FontWeight.w700,
                      color: hoursColour,
                    ),
                  ),
                ),
              ],
            ),
            if (distanceKm != null)
              _BranchFact(
                icon: PhosphorIconsRegular.mapPin,
                label: _distanceLabel(distanceKm),
                style: metaStyle,
              ),
            if (active.branch.deliveryFee != null)
              _BranchFact(
                icon: PhosphorIconsRegular.motorcycle,
                label: _feeLabel(active.branch),
                style: metaStyle,
              ),
            if (active.isOutOfDeliveryRange) _OutOfRangeBadge(),
          ],
        ),
      ],
    );
  }
}

class _BranchFact extends StatelessWidget {
  const _BranchFact({required this.icon, required this.label, this.style});

  final IconData icon;
  final String label;
  final TextStyle? style;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 15, color: style?.color),
        const SizedBox(width: 4),
        Flexible(child: Text(label, style: style)),
      ],
    );
  }
}

class _OutOfRangeBadge extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: colors.errorContainer,
        borderRadius: BorderRadius.circular(6),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
        child: Text(
          'Out of delivery range',
          style: Theme.of(context).textTheme.labelSmall
              ?.copyWith(color: colors.onErrorContainer),
        ),
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
      LocationIssue.denied || LocationIssue.deniedForever =>
        'Allow location access to find your nearest branch.',
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
            if (issue != LocationIssue.unavailable)
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

class _Message extends StatelessWidget {
  const _Message({required this.text, this.leading, this.action});

  final String text;
  final Widget? leading;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        if (leading != null) ...[leading!, const SizedBox(width: 12)],
        Expanded(child: Text(text)),
        ?action,
      ],
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
        if (state.nearest != null && state.selectedBranchId != null)
          ListTile(
            leading: const Icon(Icons.my_location),
            title: const Text('Use nearest branch'),
            onTap: () {
              notifier.useNearest();
              Navigator.pop(context);
            },
          ),
        for (final item in state.branches)
          ListTile(
            title: Text(item.branch.name),
            subtitle: Text(
              [
                item.branch.address,
                if (item.distanceKm != null) _distanceLabel(item.distanceKm!),
                _hoursLabel(item),
              ].join(' · '),
            ),
            trailing: item.branch.id == activeId
                ? Icon(
                    Icons.check,
                    color: Theme.of(context).colorScheme.primary,
                  )
                : null,
            selected: item.branch.id == activeId,
            onTap: () {
              notifier.selectBranch(item.branch.id);
              Navigator.pop(context);
            },
          ),
      ],
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
