import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../models/medication.dart';
import '../providers/medication_providers.dart';
import '../widgets/common.dart';

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
        children: [
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
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
                    onSelected: (_) => setState(() => _profileId = profile.id),
                  ),
                ],
                const SizedBox(width: 16),
                SegmentedButton<int>(
                  segments: const [
                    ButtonSegment(value: 30, label: Text('30 days')),
                    ButtonSegment(value: 90, label: Text('90 days')),
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
                      icon: Icons.history_rounded,
                      title: 'No doses yet',
                    )
                  : ListView(
                      padding: const EdgeInsets.only(bottom: 24),
                      children: [for (final plan in plans) _PlanHistory(plan)],
                    ),
            ),
          ),
        ],
      ),
    );
  }
}

class _PlanHistory extends StatelessWidget {
  const _PlanHistory(this.history);

  final DoseHistory history;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final adherence = history.adherence;
    final days = <DateTime, List<DoseSlot>>{};
    for (final slot in history.slots.reversed) {
      days
          .putIfAbsent(DateUtils.dateOnly(slot.scheduledFor), () => [])
          .add(slot);
    }

    return Card(
      margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              history.productName,
              style: theme.textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              adherence == null
                  ? '${history.takenCount} doses taken'
                  : '${(adherence * 100).round()}% of doses taken (${history.takenCount} of ${history.slots.length})',
            ),
            const SizedBox(height: 8),
            for (final entry in days.entries.take(14))
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 3),
                child: Row(
                  children: [
                    SizedBox(
                      width: 96,
                      child: Text(
                        DateFormat('EEE, MMM d').format(entry.key),
                        style: theme.textTheme.bodySmall,
                      ),
                    ),
                    Expanded(
                      child: Wrap(
                        spacing: 10,
                        children: [
                          for (final slot in entry.value.reversed)
                            _SlotMark(slot),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _SlotMark extends StatelessWidget {
  const _SlotMark(this.slot);

  final DoseSlot slot;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final (icon, color, label) = switch (slot.status) {
      'taken' => (Icons.check_circle_rounded, scheme.primary, 'taken'),
      'skipped' => (
        Icons.remove_circle_outline_rounded,
        scheme.outline,
        'skipped',
      ),
      _ => (Icons.radio_button_unchecked_rounded, scheme.error, 'not marked'),
    };
    final time = DateFormat('h:mm a').format(slot.scheduledFor);

    return Semantics(
      label: '$time $label',
      excludeSemantics: true,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 16, color: color),
          const SizedBox(width: 3),
          Text(time, style: Theme.of(context).textTheme.bodySmall),
        ],
      ),
    );
  }
}
