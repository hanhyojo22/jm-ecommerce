import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:phosphor_icons/phosphor_icons.dart';

import '../models/medication.dart';
import '../providers/medication_providers.dart';
import '../theme.dart';
import '../widgets/common.dart';
import '../widgets/medicine_ui.dart';

/// Each scheduled dose of one person's medicines, marked taken, skipped or not
/// marked, for showing a doctor.
class MedicineHistoryScreen extends ConsumerStatefulWidget {
  const MedicineHistoryScreen({super.key});

  @override
  ConsumerState<MedicineHistoryScreen> createState() =>
      _MedicineHistoryScreenState();
}

class _MedicineHistoryScreenState extends ConsumerState<MedicineHistoryScreen> {
  String? _profileId;
  int _days = 30;

  @override
  Widget build(BuildContext context) {
    final profiles = ref.watch(careProfilesProvider).value ?? const [];
    final history = ref.watch(
      medicationHistoryProvider((profileId: _profileId, days: _days)),
    );

    return Scaffold(
      appBar: AppBar(title: const Text('Dose history')),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (profiles.isNotEmpty) ...[
                  SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: Row(
                      children: [
                        ChoiceChip(
                          label: const Text('Me'),
                          selected: _profileId == null,
                          onSelected: (_) => setState(() => _profileId = null),
                        ),
                        for (final profile in profiles) ...[
                          const SizedBox(width: 8),
                          ChoiceChip(
                            label: Text(profile.name),
                            selected: _profileId == profile.id,
                            onSelected: (_) =>
                                setState(() => _profileId = profile.id),
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: 10),
                ],
                SegmentedButton<int>(
                  showSelectedIcon: false,
                  segments: const [
                    ButtonSegment(value: 30, label: Text('Last 30 days')),
                    ButtonSegment(value: 90, label: Text('Last 90 days')),
                  ],
                  selected: {_days},
                  onSelectionChanged: (value) =>
                      setState(() => _days = value.first),
                ),
              ],
            ),
          ),
          Expanded(
            child: AsyncView(
              value: history,
              onRetry: () => ref.invalidate(medicationHistoryProvider),
              builder: (plans) => plans.isEmpty
                  ? const EmptyState(
                      icon: PhosphorIconsRegular.clockCounterClockwise,
                      title: 'No doses yet',
                      message:
                          'Marked and scheduled doses show up here so you can review them with your doctor.',
                    )
                  : ListView(
                      padding: const EdgeInsets.fromLTRB(16, 4, 16, 32),
                      children: [
                        const _StatusLegend(),
                        const SizedBox(height: 12),
                        for (var i = 0; i < plans.length; i++) ...[
                          if (i > 0) const SizedBox(height: 12),
                          _PlanHistory(
                            key: ValueKey(plans[i].planId),
                            history: plans[i],
                            daysWindow: _days,
                          ),
                        ],
                      ],
                    ),
            ),
          ),
        ],
      ),
    );
  }
}

class _StatusLegend extends StatelessWidget {
  const _StatusLegend();

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final brand = AppBrandColors.of(context);

    return Wrap(
      spacing: 16,
      runSpacing: 6,
      children: [
        _LegendItem(
          icon: PhosphorIconsFill.checkCircle,
          color: brand.careTeal,
          label: 'Taken',
        ),
        _LegendItem(
          icon: PhosphorIconsRegular.minusCircle,
          color: scheme.onSurfaceVariant,
          label: 'Skipped',
        ),
        _LegendItem(
          icon: PhosphorIconsRegular.circle,
          color: scheme.error,
          label: 'Not marked',
        ),
      ],
    );
  }
}

class _LegendItem extends StatelessWidget {
  const _LegendItem({
    required this.icon,
    required this.color,
    required this.label,
  });

  final IconData icon;
  final Color color;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 15, color: color),
        const SizedBox(width: 5),
        Text(
          label,
          style: Theme.of(context).textTheme.bodySmall?.copyWith(
            fontSize: 12,
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }
}

class _PlanHistory extends StatefulWidget {
  const _PlanHistory({
    super.key,
    required this.history,
    required this.daysWindow,
  });

  final DoseHistory history;
  final int daysWindow;

  @override
  State<_PlanHistory> createState() => _PlanHistoryState();
}

class _PlanHistoryState extends State<_PlanHistory> {
  bool _expanded = false;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final brand = AppBrandColors.of(context);
    final history = widget.history;
    final adherence = history.adherence;
    final percent = adherence == null ? null : (adherence * 100).round();

    final Color adherenceColor = adherence == null
        ? scheme.primary
        : adherence >= 0.8
        ? brand.careTeal
        : adherence >= 0.5
        ? statusColour(context, 'pending')
        : scheme.error;

    final days = <DateTime, List<DoseSlot>>{};
    for (final slot in history.slots.reversed) {
      days
          .putIfAbsent(DateUtils.dateOnly(slot.scheduledFor), () => [])
          .add(slot);
    }
    final allDays = days.entries.toList();
    final visibleDays = _expanded ? allDays : allDays.take(14).toList();

    return MedicineCardShell(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      history.productName,
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontSize: 15.5,
                        fontWeight: FontWeight.w700,
                        height: 1.25,
                      ),
                    ),
                    const SizedBox(height: 6),
                    MedicineKindBadge(medicineKind: history.medicineKind),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    percent != null ? '$percent%' : '${history.takenCount}',
                    style: theme.textTheme.headlineSmall?.copyWith(
                      fontSize: 22,
                      fontWeight: FontWeight.w800,
                      color: adherenceColor,
                      fontFeatures: const [FontFeature.tabularFigures()],
                    ),
                  ),
                  Text(
                    percent != null ? 'adherence' : 'doses taken',
                    style: theme.textTheme.bodySmall?.copyWith(
                      fontSize: 11.5,
                      color: scheme.onSurfaceVariant,
                    ),
                  ),
                ],
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (adherence != null) ...[
            ClipRRect(
              borderRadius: BorderRadius.circular(999),
              child: LinearProgressIndicator(
                value: adherence.clamp(0.0, 1.0),
                minHeight: 6,
                backgroundColor: scheme.surfaceContainerHighest,
                valueColor: AlwaysStoppedAnimation(adherenceColor),
              ),
            ),
            const SizedBox(height: 6),
          ],
          Text(
            adherence == null
                ? '${history.takenCount} ${history.takenCount == 1 ? 'dose' : 'doses'} logged in the last ${widget.daysWindow} days'
                : '${history.takenCount} of ${history.slots.length} scheduled doses taken',
            style: theme.textTheme.bodySmall?.copyWith(
              fontSize: 12.5,
              color: scheme.onSurfaceVariant,
            ),
          ),
          if (visibleDays.isNotEmpty) ...[
            const SizedBox(height: 12),
            Divider(height: 1, thickness: 1, color: scheme.outlineVariant),
            const SizedBox(height: 8),
            for (var i = 0; i < visibleDays.length; i++) ...[
              if (i > 0)
                Divider(
                  height: 1,
                  thickness: 1,
                  color: scheme.outlineVariant.withValues(alpha: 0.6),
                ),
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 8),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    SizedBox(
                      width: 92,
                      child: Text(
                        formatMedicineDay(visibleDays[i].key),
                        style: theme.textTheme.bodySmall?.copyWith(
                          fontSize: 12.5,
                          fontWeight: FontWeight.w600,
                          color: scheme.onSurfaceVariant,
                          fontFeatures: const [FontFeature.tabularFigures()],
                        ),
                      ),
                    ),
                    Expanded(
                      child: Wrap(
                        spacing: 6,
                        runSpacing: 6,
                        children: [
                          for (final slot in visibleDays[i].value.reversed)
                            _SlotMark(slot),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ],
          if (allDays.length > 14) ...[
            const SizedBox(height: 4),
            Center(
              child: TextButton(
                onPressed: () => setState(() => _expanded = !_expanded),
                child: Text(
                  _expanded
                      ? 'Show fewer days'
                      : 'Show all ${allDays.length} days',
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _SlotMark extends StatelessWidget {
  const _SlotMark(this.slot);

  final DoseSlot slot;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final brand = AppBrandColors.of(context);
    final dark = theme.brightness == Brightness.dark;

    final (IconData icon, Color fg, Color bg, String label) = switch (slot
        .status) {
      'taken' => (
        PhosphorIconsFill.checkCircle,
        brand.careTeal,
        brand.careTealSoft,
        'taken',
      ),
      'skipped' => (
        PhosphorIconsRegular.minusCircle,
        scheme.onSurfaceVariant,
        scheme.surfaceContainer,
        'skipped',
      ),
      _ => (
        PhosphorIconsRegular.circle,
        scheme.error,
        scheme.error.withValues(alpha: dark ? 0.16 : 0.08),
        'not marked',
      ),
    };
    final time = formatMedicineClock(slot.scheduledFor);

    return Semantics(
      label: '$time $label',
      excludeSemantics: true,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        decoration: BoxDecoration(
          color: bg,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 14, color: fg),
            const SizedBox(width: 4),
            Text(
              time,
              style: theme.textTheme.bodySmall?.copyWith(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: slot.status == 'taken' ? fg : scheme.onSurface,
                fontFeatures: const [FontFeature.tabularFigures()],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
