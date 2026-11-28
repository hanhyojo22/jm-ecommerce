import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:phosphor_icons/phosphor_icons.dart';
import 'package:uuid/uuid.dart';

import '../core/api_exception.dart';
import '../core/medication_alarms.dart';
import '../models/medication.dart';
import '../providers/auth_providers.dart';
import '../providers/cart_providers.dart';
import '../providers/medication_providers.dart';
import '../theme.dart';
import '../widgets/common.dart';
import '../widgets/grouped_list_card.dart';
import '../widgets/medicine_ui.dart';
import '../widgets/status_notice.dart';

const directionsDisclaimer = medicineDirectionsDisclaimer;

String _manilaSlotIso(DateTime slot) {
  String two(int value) => value.toString().padLeft(2, '0');
  return '${slot.year}-${two(slot.month)}-${two(slot.day)}'
      'T${two(slot.hour)}:${two(slot.minute)}:00+08:00';
}

/// Logs one dose now and refreshes everything that depends on the supply.
Future<String?> logDose(
  WidgetRef ref,
  MedicationPlan plan, {
  required String status,
  String? scheduledFor,
}) async {
  final result = await ref.read(apiClientProvider).logDoses(plan.id, [
    {
      'client_uuid': const Uuid().v4(),
      'status': status,
      'logged_at': DateTime.now().toUtc().toIso8601String(),
      'scheduled_for': ?scheduledFor,
    },
  ]);
  ref.invalidate(medicationPlansProvider);
  return result.warning;
}

String warningText(String warning, MedicationPlan plan) => switch (warning) {
  'too_soon' =>
    "It's been less than ${plan.minHoursBetween} hours since your last dose.",
  'daily_max' =>
    "That's more than ${plan.maxDosesPerDay} doses in 24 hours, the most the label allows.",
  _ => '',
};

/// Account → Medicine reminders: opt-in first, then each person's medicines.
class MedicinesScreen extends ConsumerWidget {
  const MedicinesScreen({super.key, this.countPlanId});

  /// Opens the "how many are left?" question for this plan straight away.
  final String? countPlanId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final account = ref.watch(meProvider);
    final optedIn = account.value?.medicationReminders ?? false;
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Medicine reminders'),
        actions: [
          if (optedIn) ...[
            IconButton(
              tooltip: 'Dose history',
              onPressed: () => context.push('/medicines/history'),
              icon: const Icon(
                PhosphorIconsRegular.clockCounterClockwise,
                size: 22,
              ),
            ),
            IconButton(
              tooltip: 'Family and caregivers',
              onPressed: () => context.push('/medicines/people'),
              icon: const Icon(PhosphorIconsRegular.users, size: 22),
            ),
            PopupMenuButton<String>(
              tooltip: 'More options',
              icon: const Icon(PhosphorIconsRegular.dotsThreeVertical, size: 22),
              onSelected: (choice) => switch (choice) {
                'history' => context.push('/medicines/history'),
                'people' => context.push('/medicines/people'),
                _ => _turnOff(context, ref),
              },
              itemBuilder: (context) => [
                const PopupMenuItem(
                  value: 'history',
                  child: _MenuRow(
                    icon: PhosphorIconsRegular.clockCounterClockwise,
                    label: 'Dose history',
                  ),
                ),
                const PopupMenuItem(
                  value: 'people',
                  child: _MenuRow(
                    icon: PhosphorIconsRegular.users,
                    label: 'Family and caregivers',
                  ),
                ),
                const PopupMenuDivider(),
                PopupMenuItem(
                  value: 'off',
                  child: _MenuRow(
                    icon: PhosphorIconsRegular.trash,
                    label: 'Turn off reminders',
                    color: scheme.error,
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
      body: AsyncView(
        value: account,
        onRetry: () => ref.invalidate(meProvider),
        builder: (account) => account.medicationReminders
            ? _MedicineList(countPlanId: countPlanId)
            : const MedicineConsent(),
      ),
    );
  }

  Future<void> _turnOff(BuildContext context, WidgetRef ref) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) {
        final scheme = Theme.of(dialogContext).colorScheme;
        return AlertDialog(
          title: const Text('Turn off medicine reminders?'),
          content: const Text(
            'This deletes your reminders, dose history, family profiles, and caregivers.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Keep'),
            ),
            FilledButton(
              style: FilledButton.styleFrom(
                minimumSize: const Size(0, 44),
                backgroundColor: scheme.error,
                foregroundColor: scheme.onError,
              ),
              onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Turn off and delete'),
            ),
          ],
        );
      },
    );
    if (confirmed != true || !context.mounted) {
      return;
    }
    final notice = AppNotice.of(context);
    try {
      await ref.read(apiClientProvider).setMedicationConsent(false);
      await MedicationAlarms.cancelMedicineAlarms();
      ref.invalidate(meProvider);
      notice.show('Medicine reminders are off.', kind: NoticeKind.info);
    } on ApiException catch (error) {
      notice.show(error.friendly);
    }
  }
}

class _MenuRow extends StatelessWidget {
  const _MenuRow({required this.icon, required this.label, this.color});

  final IconData icon;
  final String label;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final effective = color ?? Theme.of(context).colorScheme.onSurface;
    return Row(
      children: [
        Icon(icon, size: 18, color: effective),
        const SizedBox(width: 12),
        Text(label, style: TextStyle(color: effective)),
      ],
    );
  }
}

/// What is stored, who sees it and how to delete it, before anything is saved.
class MedicineConsent extends ConsumerStatefulWidget {
  const MedicineConsent({super.key});

  @override
  ConsumerState<MedicineConsent> createState() => _MedicineConsentState();
}

class _MedicineConsentState extends ConsumerState<MedicineConsent> {
  bool _saving = false;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final brand = AppBrandColors.of(context);
    final invitations =
        ref.watch(caregiversProvider).value?.invitations ?? const [];

    return Column(
      children: [
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
            children: [
              if (invitations.isNotEmpty) ...[
                GroupedCard(
                  children: [
                    GroupedRowTile(
                      icon: PhosphorIconsRegular.heart,
                      tone: GroupedIconTone.teal,
                      title: 'Someone asked you to be their caregiver',
                      subtitle: 'Review and respond to the invitation',
                      onTap: () => context.push('/medicines/people'),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
              ],
              MedicineCardShell(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 52,
                      height: 52,
                      decoration: BoxDecoration(
                        color: brand.careTealSoft,
                        borderRadius: BorderRadius.circular(14),
                      ),
                      alignment: Alignment.center,
                      child: Icon(
                        PhosphorIconsFill.alarm,
                        size: 26,
                        color: brand.careTeal,
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      'Never miss a dose',
                      style: theme.textTheme.headlineSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Get on-time reminders for your medicines, a heads-up before you run out, and expiry alerts. You can also manage schedules for family.',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: scheme.onSurfaceVariant,
                        height: 1.5,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),
              const MedicineSectionHeader(title: 'Your privacy & control'),
              const GroupedCard(
                dividerIndent: 62,
                children: [
                  _ConsentPointRow(
                    icon: PhosphorIconsRegular.lockSimple,
                    tone: GroupedIconTone.primary,
                    title: 'Stored only for your reminders',
                    subtitle:
                        'We store the medicines, doses, and times you set, plus the doses you mark. Medicine details are sensitive personal information under the Data Privacy Act.',
                  ),
                  _ConsentPointRow(
                    icon: PhosphorIconsRegular.eyeSlash,
                    tone: GroupedIconTone.teal,
                    title: 'Private by default',
                    subtitle:
                        "Only you see your plans. The pharmacy doesn't, and a caregiver only gets an alert when a dose isn't marked.",
                  ),
                  _ConsentPointRow(
                    icon: PhosphorIconsRegular.trash,
                    tone: GroupedIconTone.neutral,
                    title: 'Delete everything anytime',
                    subtitle:
                        'Turn reminders off at any time to permanently erase all plans, logs, profiles, and caregiver links.',
                  ),
                ],
              ),
              const SizedBox(height: 16),
              const MedicineDisclaimerNote(),
            ],
          ),
        ),
        MedicineBottomBar(
          child: FilledButton(
            onPressed: _saving ? null : _accept,
            style: FilledButton.styleFrom(
              minimumSize: const Size.fromHeight(52),
            ),
            child: _saving
                ? SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2.2,
                      color: scheme.onPrimary,
                    ),
                  )
                : const Text('I agree, turn on reminders'),
          ),
        ),
      ],
    );
  }

  Future<void> _accept() async {
    setState(() => _saving = true);
    final notice = AppNotice.of(context);
    try {
      await ref.read(apiClientProvider).setMedicationConsent(true);
      await MedicationAlarms.requestExactTiming();
      ref.invalidate(meProvider);
    } on ApiException catch (error) {
      notice.show(error.friendly);
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }
}

class _ConsentPointRow extends StatelessWidget {
  const _ConsentPointRow({
    required this.icon,
    required this.tone,
    required this.title,
    required this.subtitle,
  });

  final IconData icon;
  final GroupedIconTone tone;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Padding(
      padding: const EdgeInsets.all(14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          GroupedIconBadge(icon: icon, tone: tone),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: scheme.onSurface,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  subtitle,
                  style: theme.textTheme.bodySmall?.copyWith(
                    fontSize: 12.5,
                    height: 1.45,
                    color: scheme.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _MedicineList extends ConsumerStatefulWidget {
  const _MedicineList({this.countPlanId});

  final String? countPlanId;

  @override
  ConsumerState<_MedicineList> createState() => _MedicineListState();
}

class _MedicineListState extends ConsumerState<_MedicineList> {
  bool _askedCount = false;

  @override
  void initState() {
    super.initState();
    // The shell's sync is paused while this screen covers it, so changes made here (set-up,
    // delete, pause, a logged dose) reach the phone's alarms straight away.
    ref.listenManual(medicationPlansProvider, (_, next) {
      final plans = next.value;
      if (plans != null) {
        unawaited(MedicationAlarms.sync(plans));
      }
    }, fireImmediately: true);
  }

  @override
  Widget build(BuildContext context) {
    final plans = ref.watch(medicationPlansProvider);
    final suggestions = ref.watch(medicationSuggestionsProvider).value ?? [];
    final pendingInvitations =
        (ref.watch(caregiversProvider).value?.invitations ?? const [])
            .where((link) => !link.isAccepted)
            .length;

    return AsyncView(
      value: plans,
      onRetry: () => ref.invalidate(medicationPlansProvider),
      builder: (plans) {
        _maybeAskCount(plans);
        final today = DateUtils.dateOnly(DateTime.now());
        final runningLow = plans
            .where(
              (plan) =>
                  plan.productId != null &&
                  plan.reorderOn != null &&
                  !plan.reorderOn!.isAfter(today),
            )
            .toList();
        final groups = <String, List<MedicationPlan>>{};
        for (final plan in plans) {
          groups.putIfAbsent(plan.profileName ?? 'Me', () => []).add(plan);
        }

        final activePlans = plans.where((p) => p.isActive).toList();
        var todayTotal = 0;
        var todayAnswered = 0;
        for (final plan in activePlans) {
          if (plan.isAsNeeded) {
            continue;
          }
          if (today.isBefore(plan.startsOn) ||
              (plan.endsOn != null && today.isAfter(plan.endsOn!))) {
            continue;
          }
          for (final hhmm in plan.doseTimes) {
            final slot = _todaySlot(today, hhmm);
            todayTotal++;
            if (plan.answeredSlots.contains(slot)) {
              todayAnswered++;
            }
          }
        }

        return RefreshIndicator(
          onRefresh: () async {
            ref.invalidate(medicationPlansProvider);
            ref.invalidate(medicationSuggestionsProvider);
            await ref.read(medicationPlansProvider.future);
          },
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
            children: [
              if (plans.isNotEmpty) ...[
                _TodayOverviewCard(
                  today: today,
                  activeCount: activePlans.length,
                  todayAnswered: todayAnswered,
                  todayTotal: todayTotal,
                ),
                const SizedBox(height: 12),
                _QuickNavRow(pendingInvitations: pendingInvitations),
              ],
              if (pendingInvitations > 0 && plans.isEmpty) ...[
                GroupedCard(
                  children: [
                    GroupedRowTile(
                      icon: PhosphorIconsRegular.heart,
                      tone: GroupedIconTone.teal,
                      title: 'Caregiver invitation waiting',
                      subtitle: 'Tap to review and accept',
                      trailing: GroupedStatusPill(
                        label: '$pendingInvitations',
                        tone: GroupedPillTone.unread,
                      ),
                      onTap: () => context.push('/medicines/people'),
                    ),
                  ],
                ),
              ],
              if (runningLow.isNotEmpty) ...[
                const SizedBox(height: 12),
                _RunningLowBanner(
                  text: runningLow.length == 1
                      ? '${runningLow.single.productName} is running low.'
                      : '${runningLow.length} medicines are running low.',
                  onTap: () => context.push(
                    '/medicines/reorder?plans=${runningLow.map((plan) => plan.id).join(',')}',
                  ),
                ),
              ],
              if (suggestions.isNotEmpty) ...[
                const SizedBox(height: 20),
                MedicineSectionHeader(
                  title: 'From your orders',
                  count: suggestions.length,
                ),
                GroupedCard(
                  dividerIndent: 62,
                  children: [
                    for (final suggestion in suggestions.take(5))
                      GroupedRowTile(
                        icon: PhosphorIconsRegular.pill,
                        tone: suggestion.directions != null
                            ? GroupedIconTone.teal
                            : GroupedIconTone.primary,
                        title: suggestion.productName,
                        subtitle: suggestion.directions == null
                            ? 'Enter the dose from the box or your doctor'
                            : 'Directions from the pharmacy are ready',
                        trailing: const GroupedStatusPill(
                          label: 'Set up',
                          tone: GroupedPillTone.primary,
                        ),
                        onTap: () =>
                            context.push('/medicines/setup', extra: suggestion),
                      ),
                  ],
                ),
              ],
              if (plans.isEmpty && suggestions.isEmpty) ...[
                const SizedBox(height: 28),
                const EmptyState(
                  icon: PhosphorIconsRegular.pill,
                  title: 'No reminders yet',
                  message:
                      'When medicine from your orders is delivered, it shows up here to set up.',
                ),
                const SizedBox(height: 16),
                _QuickNavRow(pendingInvitations: pendingInvitations),
              ],
              for (final entry in groups.entries) ...[
                const SizedBox(height: 20),
                MedicineSectionHeader(
                  title: entry.key,
                  count: entry.value.length,
                ),
                for (var i = 0; i < entry.value.length; i++) ...[
                  if (i > 0) const SizedBox(height: 10),
                  _PlanCard(
                    key: ValueKey(entry.value[i].id),
                    plan: entry.value[i],
                    today: today,
                  ),
                ],
              ],
              const SizedBox(height: 20),
              const MedicineDisclaimerNote(),
            ],
          ),
        );
      },
    );
  }

  void _maybeAskCount(List<MedicationPlan> plans) {
    final id = widget.countPlanId;
    if (_askedCount || id == null) {
      return;
    }
    _askedCount = true;
    final plan = plans.where((plan) => plan.id == id).firstOrNull;
    if (plan != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) {
          askHowManyLeft(context, ref, plan);
        }
      });
    }
  }
}

DateTime _todaySlot(DateTime today, String hhmm) {
  final parts = hhmm.split(':');
  return DateTime(
    today.year,
    today.month,
    today.day,
    int.parse(parts[0]),
    int.parse(parts[1]),
  );
}

class _TodayOverviewCard extends StatelessWidget {
  const _TodayOverviewCard({
    required this.today,
    required this.activeCount,
    required this.todayAnswered,
    required this.todayTotal,
  });

  final DateTime today;
  final int activeCount;
  final int todayAnswered;
  final int todayTotal;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final brand = AppBrandColors.of(context);
    final allDone = todayTotal > 0 && todayAnswered >= todayTotal;
    final progress = todayTotal > 0 ? (todayAnswered / todayTotal).clamp(0.0, 1.0) : 0.0;

    return MedicineCardShell(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: allDone ? brand.careTealSoft : scheme.primaryContainer,
                  borderRadius: BorderRadius.circular(10),
                ),
                alignment: Alignment.center,
                child: Icon(
                  allDone
                      ? PhosphorIconsFill.checkCircle
                      : PhosphorIconsFill.pill,
                  size: 20,
                  color: allDone ? brand.careTeal : scheme.primary,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      formatMedicineDay(today),
                      style: theme.textTheme.bodySmall?.copyWith(
                        fontWeight: FontWeight.w600,
                        color: scheme.onSurfaceVariant,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      todayTotal == 0
                          ? '$activeCount active ${activeCount == 1 ? 'medicine' : 'medicines'}'
                          : allDone
                          ? 'All scheduled doses marked today'
                          : '$todayAnswered of $todayTotal doses marked today',
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontSize: 15.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (todayTotal > 0) ...[
            const SizedBox(height: 12),
            ClipRRect(
              borderRadius: BorderRadius.circular(999),
              child: LinearProgressIndicator(
                value: progress,
                minHeight: 6,
                backgroundColor: scheme.surfaceContainerHighest,
                valueColor: AlwaysStoppedAnimation(
                  allDone ? brand.careTeal : scheme.primary,
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _QuickNavRow extends StatelessWidget {
  const _QuickNavRow({required this.pendingInvitations});

  final int pendingInvitations;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: _QuickNavTile(
            icon: PhosphorIconsRegular.clockCounterClockwise,
            tone: GroupedIconTone.primary,
            title: 'Dose history',
            subtitle: 'For your doctor',
            onTap: () => context.push('/medicines/history'),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: _QuickNavTile(
            icon: PhosphorIconsRegular.users,
            tone: GroupedIconTone.teal,
            title: 'Family & care',
            subtitle: pendingInvitations > 0
                ? '$pendingInvitations pending invite'
                : 'Profiles & alerts',
            badge: pendingInvitations > 0 ? '$pendingInvitations' : null,
            onTap: () => context.push('/medicines/people'),
          ),
        ),
      ],
    );
  }
}

class _QuickNavTile extends StatelessWidget {
  const _QuickNavTile({
    required this.icon,
    required this.tone,
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.badge,
  });

  final IconData icon;
  final GroupedIconTone tone;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  final String? badge;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return MedicineCardShell(
      onTap: onTap,
      padding: const EdgeInsets.all(12),
      child: Row(
        children: [
          GroupedIconBadge(icon: icon, tone: tone),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
                  children: [
                    Flexible(
                      child: Text(
                        title,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          fontSize: 13.5,
                          fontWeight: FontWeight.w700,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    if (badge != null) ...[
                      const SizedBox(width: 6),
                      GroupedStatusPill(
                        label: badge!,
                        tone: GroupedPillTone.unread,
                      ),
                    ],
                  ],
                ),
                const SizedBox(height: 1),
                Text(
                  subtitle,
                  style: theme.textTheme.bodySmall?.copyWith(
                    fontSize: 11.5,
                    color: scheme.onSurfaceVariant,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

Future<void> askHowManyLeft(
  BuildContext context,
  WidgetRef ref,
  MedicationPlan plan,
) async {
  final controller = TextEditingController(
    text: formatMedicineNumber(plan.unitsLeft),
  );
  final value = await showDialog<double>(
    context: context,
    builder: (context) => AlertDialog(
      title: Text('How many ${plan.doseUnit}s are left?'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Updating your count keeps run-out and reorder reminders accurate.',
            style: Theme.of(context).textTheme.bodySmall,
          ),
          const SizedBox(height: 16),
          TextField(
            controller: controller,
            autofocus: true,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: InputDecoration(
              labelText: plan.productName,
              suffixText: plan.doseUnit,
            ),
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Cancel'),
        ),
        FilledButton(
          style: FilledButton.styleFrom(minimumSize: const Size(0, 44)),
          onPressed: () =>
              Navigator.pop(context, double.tryParse(controller.text.trim())),
          child: const Text('Save'),
        ),
      ],
    ),
  );
  if (value == null || !context.mounted) {
    return;
  }
  final notice = AppNotice.of(context);
  try {
    await ref.read(apiClientProvider).countMedication(plan.id, value);
    ref.invalidate(medicationPlansProvider);
    notice.show('Supply updated.', kind: NoticeKind.success);
  } on ApiException catch (error) {
    notice.show(error.friendly);
  }
}

class _PlanCard extends ConsumerWidget {
  const _PlanCard({super.key, required this.plan, required this.today});

  final MedicationPlan plan;
  final DateTime today;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final brand = AppBrandColors.of(context);
    final isLow =
        plan.reorderOn != null && !plan.reorderOn!.isAfter(today);

    final supplyText = plan.medicineKind == 'course' && plan.endsOn != null
        ? 'Course ends ${formatMedicineDay(plan.endsOn!)}'
        : plan.runOutOn != null
        ? 'About ${plan.unitsLeftLabel} left · runs out ${formatMedicineDay(plan.runOutOn!)}'
        : 'About ${plan.unitsLeftLabel} left';

    return Opacity(
      opacity: plan.isActive ? 1.0 : 0.72,
      child: MedicineCardShell(
        onTap: () => _showActions(context, ref),
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
                        plan.productName,
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontSize: 15.5,
                          fontWeight: FontWeight.w700,
                          height: 1.25,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Wrap(
                        spacing: 8,
                        runSpacing: 6,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        children: [
                          MedicineKindBadge(
                            medicineKind: plan.medicineKind,
                            isActive: plan.isActive,
                          ),
                          Text(
                            plan.isAsNeeded
                                ? '${plan.doseLabel} when needed'
                                : plan.doseLabel,
                            style: theme.textTheme.bodySmall?.copyWith(
                              fontSize: 13,
                              fontWeight: FontWeight.w600,
                              color: scheme.onSurfaceVariant,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 4),
                IconButton(
                  tooltip: 'Reminder options',
                  visualDensity: VisualDensity.compact,
                  constraints: const BoxConstraints(
                    minWidth: 36,
                    minHeight: 36,
                  ),
                  padding: EdgeInsets.zero,
                  onPressed: () => _showActions(context, ref),
                  icon: Icon(
                    PhosphorIconsRegular.dotsThree,
                    size: 20,
                    color: scheme.onSurfaceVariant,
                  ),
                ),
              ],
            ),
            if (plan.directionsNote != null &&
                plan.directionsNote!.trim().isNotEmpty) ...[
              const SizedBox(height: 8),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Padding(
                    padding: const EdgeInsets.only(top: 1),
                    child: Icon(
                      PhosphorIconsRegular.prescription,
                      size: 14,
                      color: brand.careTeal,
                    ),
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      plan.directionsNote!,
                      style: theme.textTheme.bodySmall?.copyWith(
                        fontSize: 12.5,
                        color: scheme.onSurfaceVariant,
                      ),
                    ),
                  ),
                ],
              ),
            ],
            if (!plan.isAsNeeded && plan.doseTimes.isNotEmpty) ...[
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final hhmm in plan.doseTimes)
                    _DoseTimePill(
                      timeLabel: formatMedicineTime(hhmm),
                      answered: plan.answeredSlots.contains(
                        _todaySlot(today, hhmm),
                      ),
                      enabled: plan.isActive,
                      onTap: plan.isActive
                          ? () => _showSlotSheet(
                                context,
                                ref,
                                _todaySlot(today, hhmm),
                              )
                          : null,
                    ),
                ],
              ),
            ],
            if (plan.isAsNeeded && plan.isActive) ...[
              const SizedBox(height: 12),
              SizedBox(
                height: 40,
                child: FilledButton.tonalIcon(
                  style: FilledButton.styleFrom(
                    minimumSize: const Size(0, 40),
                    padding: const EdgeInsets.symmetric(horizontal: 14),
                    backgroundColor: brand.careTealSoft,
                    foregroundColor: brand.careTeal,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                  onPressed: () => _tookOne(context, ref),
                  icon: const Icon(PhosphorIconsRegular.checkCircle, size: 18),
                  label: const Text('I took one'),
                ),
              ),
            ],
            const SizedBox(height: 12),
            Divider(height: 1, thickness: 1, color: scheme.outlineVariant),
            const SizedBox(height: 10),
            Row(
              children: [
                Icon(
                  plan.medicineKind == 'course'
                      ? PhosphorIconsRegular.calendarCheck
                      : PhosphorIconsRegular.package,
                  size: 15,
                  color: isLow ? statusColour(context, 'pending') : scheme.onSurfaceVariant,
                ),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    supplyText,
                    style: theme.textTheme.bodySmall?.copyWith(
                      fontSize: 12.5,
                      color: isLow
                          ? statusColour(context, 'pending')
                          : scheme.onSurfaceVariant,
                      fontWeight: isLow ? FontWeight.w600 : FontWeight.w400,
                    ),
                  ),
                ),
                if (isLow)
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 7,
                      vertical: 2,
                    ),
                    decoration: BoxDecoration(
                      color: theme.brightness == Brightness.dark
                          ? const Color(0xFF2B2210)
                          : sunWash,
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      'Low',
                      style: theme.textTheme.labelSmall?.copyWith(
                        color: statusColour(context, 'pending'),
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
              ],
            ),
            if (plan.expiresBeforeRunOut && plan.expiresOn != null) ...[
              const SizedBox(height: 10),
              MedicineInlineAlert(
                icon: PhosphorIconsRegular.warningCircle,
                tone: MedicineAlertTone.danger,
                text:
                    'Part of this supply expires ${formatMedicineDay(plan.expiresOn!)}, before you finish it.',
              ),
            ],
            if ((plan.recentDoseDays ?? 0) >= 3) ...[
              const SizedBox(height: 10),
              const MedicineInlineAlert(
                icon: PhosphorIconsRegular.stethoscope,
                tone: MedicineAlertTone.danger,
                text:
                    "You've needed this 3 days in a row. Still feeling unwell? Talk to a pharmacist or doctor.",
              ),
            ],
          ],
        ),
      ),
    );
  }

  Future<void> _showSlotSheet(
    BuildContext context,
    WidgetRef ref,
    DateTime slot,
  ) async {
    final alreadyAnswered = plan.answeredSlots.contains(slot);
    if (alreadyAnswered) {
      AppNotice.of(context).show(
        '${formatMedicineClock(slot)} dose is already marked today.',
        kind: NoticeKind.info,
      );
      return;
    }
    final choice = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) {
        final theme = Theme.of(sheetContext);
        final scheme = theme.colorScheme;
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  plan.productName,
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Take ${plan.doseLabel} · ${formatMedicineClock(slot)}',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
                ),
                const SizedBox(height: 16),
                FilledButton.icon(
                  onPressed: () => Navigator.pop(sheetContext, 'taken'),
                  icon: const Icon(PhosphorIconsRegular.check, size: 18),
                  label: const Text('Mark as taken'),
                ),
                const SizedBox(height: 8),
                OutlinedButton.icon(
                  onPressed: () => Navigator.pop(sheetContext, 'skipped'),
                  icon: const Icon(PhosphorIconsRegular.minusCircle, size: 18),
                  label: const Text('Skip this dose'),
                ),
              ],
            ),
          ),
        );
      },
    );
    if (choice == null || !context.mounted) {
      return;
    }
    final notice = AppNotice.of(context);
    final slotIso = _manilaSlotIso(slot);
    try {
      await logDose(ref, plan, status: choice, scheduledFor: slotIso);
      unawaited(
        MedicationAlarms.cancelNudge(
          '/medicines/dose?plan=${plan.id}&slot=${Uri.encodeQueryComponent(slotIso)}',
        ),
      );
      notice.show(
        choice == 'taken' ? 'Dose recorded.' : 'Dose skipped.',
        kind: NoticeKind.success,
      );
    } on ApiException catch (error) {
      notice.show(error.friendly);
    }
  }

  Future<void> _tookOne(BuildContext context, WidgetRef ref) async {
    final notice = AppNotice.of(context);
    try {
      final warning = await logDose(ref, plan, status: 'taken');
      if (warning != null) {
        notice.show(warningText(warning, plan), kind: NoticeKind.info);
      } else {
        notice.show('Dose recorded.', kind: NoticeKind.success);
      }
    } on ApiException catch (error) {
      notice.show(error.friendly);
    }
  }

  Future<void> _showActions(BuildContext context, WidgetRef ref) async {
    final choice = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) {
        final theme = Theme.of(sheetContext);
        final scheme = theme.colorScheme;

        Widget option(
          IconData icon,
          String label,
          String value, {
          bool danger = false,
        }) => ListTile(
          contentPadding: const EdgeInsets.symmetric(horizontal: 20),
          leading: GroupedIconBadge(
            icon: icon,
            tone: danger ? GroupedIconTone.danger : GroupedIconTone.neutral,
          ),
          title: Text(
            label,
            style: theme.textTheme.bodyMedium?.copyWith(
              fontSize: 15,
              fontWeight: FontWeight.w600,
              color: danger ? scheme.error : scheme.onSurface,
            ),
          ),
          onTap: () => Navigator.pop(sheetContext, value),
        );

        return SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 0, 20, 12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      plan.productName,
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '${plan.doseLabel} · About ${plan.unitsLeftLabel} left',
                      style: theme.textTheme.bodySmall,
                    ),
                  ],
                ),
              ),
              Divider(height: 1, thickness: 1, color: scheme.outlineVariant),
              const SizedBox(height: 4),
              if (!plan.isAsNeeded)
                option(
                  PhosphorIconsRegular.checkCircle,
                  'I took a dose now',
                  'taken',
                ),
              option(
                PhosphorIconsRegular.hash,
                'Update how many are left',
                'count',
              ),
              option(
                plan.isActive
                    ? PhosphorIconsRegular.pauseCircle
                    : PhosphorIconsRegular.playCircle,
                plan.isActive ? 'Pause reminders' : 'Resume reminders',
                'pause',
              ),
              const Padding(
                padding: EdgeInsets.symmetric(horizontal: 20, vertical: 4),
                child: Divider(height: 1),
              ),
              option(
                PhosphorIconsRegular.trash,
                'Delete reminder',
                'delete',
                danger: true,
              ),
              const SizedBox(height: 8),
            ],
          ),
        );
      },
    );
    if (choice == null || !context.mounted) {
      return;
    }
    final notice = AppNotice.of(context);
    final api = ref.read(apiClientProvider);
    try {
      switch (choice) {
        case 'taken':
          await logDose(ref, plan, status: 'taken');
          notice.show('Dose recorded.', kind: NoticeKind.success);
        case 'count':
          await askHowManyLeft(context, ref, plan);
        case 'pause':
          await api.updateMedicationPlan(plan.id, {
            'status': plan.isActive ? 'paused' : 'active',
          });
          ref.invalidate(medicationPlansProvider);
          notice.show(
            plan.isActive ? 'Reminders paused.' : 'Reminders resumed.',
            kind: NoticeKind.info,
          );
        case 'delete':
          final confirmed = await showDialog<bool>(
            context: context,
            builder: (dialogContext) {
              final scheme = Theme.of(dialogContext).colorScheme;
              return AlertDialog(
                title: const Text('Delete this reminder?'),
                content: Text(
                  '${plan.productName} and its scheduled alarms will be removed.',
                ),
                actions: [
                  TextButton(
                    onPressed: () => Navigator.pop(dialogContext, false),
                    child: const Text('Cancel'),
                  ),
                  FilledButton(
                    style: FilledButton.styleFrom(
                      minimumSize: const Size(0, 44),
                      backgroundColor: scheme.error,
                      foregroundColor: scheme.onError,
                    ),
                    onPressed: () => Navigator.pop(dialogContext, true),
                    child: const Text('Delete'),
                  ),
                ],
              );
            },
          );
          if (confirmed != true || !context.mounted) {
            return;
          }
          await api.deleteMedicationPlan(plan.id);
          ref.invalidate(medicationPlansProvider);
          ref.invalidate(medicationSuggestionsProvider);
          notice.show('Reminder deleted.', kind: NoticeKind.info);
      }
    } on ApiException catch (error) {
      notice.show(error.friendly);
    }
  }
}

/// Signature pill-schedule chip showing a scheduled dose time and today's taken state.
class _DoseTimePill extends StatelessWidget {
  const _DoseTimePill({
    required this.timeLabel,
    required this.answered,
    required this.enabled,
    this.onTap,
  });

  final String timeLabel;
  final bool answered;
  final bool enabled;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final brand = AppBrandColors.of(context);

    final bg = answered
        ? brand.careTealSoft
        : scheme.surfaceContainer.withValues(alpha: 0.75);
    final border = answered
        ? brand.careTeal.withValues(alpha: 0.35)
        : scheme.outlineVariant;
    final fg = answered ? brand.careTeal : scheme.onSurface;

    return Semantics(
      button: onTap != null,
      label: answered ? '$timeLabel marked today' : '$timeLabel scheduled',
      child: Material(
        color: bg,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(10),
          side: BorderSide(color: border),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: enabled ? onTap : null,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  answered
                      ? PhosphorIconsFill.checkCircle
                      : PhosphorIconsRegular.clock,
                  size: 15,
                  color: answered ? brand.careTeal : scheme.onSurfaceVariant,
                ),
                const SizedBox(width: 6),
                Text(
                  timeLabel,
                  style: theme.textTheme.labelMedium?.copyWith(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w700,
                    color: fg,
                    fontFeatures: const [FontFeature.tabularFigures()],
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

class _RunningLowBanner extends StatelessWidget {
  const _RunningLowBanner({required this.text, required this.onTap});

  final String text;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final dark = theme.brightness == Brightness.dark;
    final accent = dark ? const Color(0xFFFFC94A) : const Color(0xFF945E00);

    return MedicineInlineAlert(
      icon: PhosphorIconsRegular.package,
      tone: MedicineAlertTone.warning,
      title: 'Refill heads-up',
      text: text,
      trailing: FilledButton(
        style: FilledButton.styleFrom(
          minimumSize: const Size(0, 38),
          padding: const EdgeInsets.symmetric(horizontal: 14),
          backgroundColor: accent,
          foregroundColor: dark ? const Color(0xFF1F1600) : Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(10),
          ),
          textStyle: theme.textTheme.labelMedium?.copyWith(
            fontWeight: FontWeight.w700,
          ),
        ),
        onPressed: onTap,
        child: const Text('Reorder'),
      ),
    );
  }
}

/// Opened from a dose reminder. With Taken or Skip already chosen on the
/// notification it records that at once; otherwise it asks.
class MedicineDoseScreen extends ConsumerStatefulWidget {
  const MedicineDoseScreen({
    super.key,
    required this.planId,
    required this.slot,
    this.action,
  });

  final String planId;
  final String slot;
  final String? action;

  @override
  ConsumerState<MedicineDoseScreen> createState() => _MedicineDoseScreenState();
}

class _MedicineDoseScreenState extends ConsumerState<MedicineDoseScreen> {
  bool _saving = false;
  bool _autoLogged = false;

  @override
  Widget build(BuildContext context) {
    final plans = ref.watch(medicationPlansProvider);
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final brand = AppBrandColors.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Medicine reminder')),
      body: AsyncView(
        value: plans,
        onRetry: () => ref.invalidate(medicationPlansProvider),
        builder: (plans) {
          final plan = plans
              .where((plan) => plan.id == widget.planId)
              .firstOrNull;
          if (plan == null) {
            return const EmptyState(
              icon: PhosphorIconsRegular.pill,
              title: 'This reminder was removed',
            );
          }
          final action = widget.action;
          if (action != null && !_autoLogged) {
            _autoLogged = true;
            WidgetsBinding.instance.addPostFrameCallback(
              (_) => _record(plan, action == 'skip' ? 'skipped' : 'taken'),
            );
          }
          final slot = DateTime.tryParse(widget.slot)?.toLocal();

          return Column(
            children: [
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
                  children: [
                    MedicineCardShell(
                      padding: const EdgeInsets.all(20),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Container(
                                width: 44,
                                height: 44,
                                decoration: BoxDecoration(
                                  color: brand.careTealSoft,
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                alignment: Alignment.center,
                                child: Icon(
                                  PhosphorIconsFill.alarm,
                                  size: 22,
                                  color: brand.careTeal,
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Wrap(
                                  spacing: 8,
                                  runSpacing: 6,
                                  children: [
                                    if (slot != null)
                                      GroupedStatusPill(
                                        label: formatMedicineClock(slot),
                                        tone: GroupedPillTone.teal,
                                      ),
                                    if (plan.profileName != null)
                                      GroupedStatusPill(
                                        label: 'For ${plan.profileName}',
                                        tone: GroupedPillTone.primary,
                                      ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 16),
                          Text(
                            plan.productName,
                            style: theme.textTheme.headlineSmall?.copyWith(
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            'Take ${plan.doseLabel}${slot == null ? '' : ' · ${formatMedicineClock(slot)}'}',
                            style: theme.textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.w600,
                              color: scheme.onSurfaceVariant,
                            ),
                          ),
                          if (plan.directionsNote != null &&
                              plan.directionsNote!.trim().isNotEmpty) ...[
                            const SizedBox(height: 14),
                            MedicineInlineAlert(
                              icon: PhosphorIconsRegular.prescription,
                              tone: MedicineAlertTone.teal,
                              title: 'Directions',
                              text: plan.directionsNote!,
                            ),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    const MedicineDisclaimerNote(),
                  ],
                ),
              ),
              MedicineBottomBar(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    FilledButton.icon(
                      onPressed: _saving ? null : () => _record(plan, 'taken'),
                      style: FilledButton.styleFrom(
                        minimumSize: const Size.fromHeight(52),
                      ),
                      icon: _saving
                          ? SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(
                                strokeWidth: 2.2,
                                color: scheme.onPrimary,
                              ),
                            )
                          : const Icon(PhosphorIconsRegular.check, size: 20),
                      label: const Text('Taken'),
                    ),
                    const SizedBox(height: 8),
                    OutlinedButton.icon(
                      onPressed:
                          _saving ? null : () => _record(plan, 'skipped'),
                      style: OutlinedButton.styleFrom(
                        minimumSize: const Size.fromHeight(48),
                      ),
                      icon: const Icon(
                        PhosphorIconsRegular.minusCircle,
                        size: 20,
                      ),
                      label: const Text('Skip this dose'),
                    ),
                  ],
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Future<void> _record(MedicationPlan plan, String status) async {
    if (_saving) {
      return;
    }
    setState(() => _saving = true);
    final notice = AppNotice.of(context);
    final router = GoRouter.of(context);
    try {
      await logDose(ref, plan, status: status, scheduledFor: widget.slot);
      unawaited(
        MedicationAlarms.cancelNudge(
          '/medicines/dose?plan=${plan.id}&slot=${Uri.encodeQueryComponent(widget.slot)}',
        ),
      );
      notice.show(
        status == 'taken' ? 'Dose recorded.' : 'Dose skipped.',
        kind: NoticeKind.success,
      );
      router.go('/medicines');
    } on ApiException catch (error) {
      notice.show(error.friendly);
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }
}

/// Opened from a "running low" reminder: puts those medicines in the cart.
class MedicineReorderScreen extends ConsumerStatefulWidget {
  const MedicineReorderScreen({super.key, required this.planIds});

  final List<String> planIds;

  @override
  ConsumerState<MedicineReorderScreen> createState() =>
      _MedicineReorderScreenState();
}

class _MedicineReorderScreenState extends ConsumerState<MedicineReorderScreen> {
  bool _adding = false;

  @override
  Widget build(BuildContext context) {
    final plans = ref.watch(medicationPlansProvider);
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(title: const Text('Reorder medicine')),
      body: AsyncView(
        value: plans,
        onRetry: () => ref.invalidate(medicationPlansProvider),
        builder: (plans) {
          final chosen = plans
              .where(
                (plan) =>
                    widget.planIds.contains(plan.id) && plan.productId != null,
              )
              .toList();
          if (chosen.isEmpty) {
            return const EmptyState(
              icon: PhosphorIconsRegular.package,
              title: 'Nothing to reorder',
            );
          }

          return Column(
            children: [
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
                  children: [
                    MedicineSectionHeader(
                      title: chosen.length == 1
                          ? 'Running low'
                          : 'Running low together',
                      count: chosen.length,
                    ),
                    GroupedCard(
                      dividerIndent: 62,
                      children: [
                        for (final plan in chosen)
                          GroupedRowTile(
                            icon: PhosphorIconsRegular.pill,
                            tone: GroupedIconTone.teal,
                            title: plan.productName,
                            subtitle: plan.runOutOn == null
                                ? 'About ${plan.unitsLeftLabel} left'
                                : 'About ${plan.unitsLeftLabel} left · runs out ${formatMedicineDay(plan.runOutOn!)}',
                            showChevron: false,
                          ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    const MedicineDisclaimerNote(
                      text:
                          'Prescription medicines still need a valid prescription at checkout.',
                    ),
                  ],
                ),
              ),
              MedicineBottomBar(
                child: FilledButton.icon(
                  onPressed: _adding ? null : () => _addAll(chosen),
                  style: FilledButton.styleFrom(
                    minimumSize: const Size.fromHeight(52),
                  ),
                  icon: _adding
                      ? SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2.2,
                            color: scheme.onPrimary,
                          ),
                        )
                      : const Icon(
                          PhosphorIconsRegular.shoppingCartSimple,
                          size: 20,
                        ),
                  label: Text(
                    chosen.length == 1 ? 'Add to cart' : 'Add all to cart',
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Future<void> _addAll(List<MedicationPlan> plans) async {
    setState(() => _adding = true);
    final notice = AppNotice.of(context);
    final router = GoRouter.of(context);
    final cart = ref.read(cartProvider.notifier);
    final failed = <String>[];
    for (final plan in plans) {
      try {
        await cart.add(
          productId: plan.productId!,
          variantId: plan.productVariantId,
        );
      } on ApiException {
        failed.add(plan.productName);
      }
    }
    if (failed.isNotEmpty) {
      notice.show(
        "Couldn't add ${failed.join(', ')}. It may be out of stock at your branch.",
      );
    }
    router.go('/cart');
  }
}
