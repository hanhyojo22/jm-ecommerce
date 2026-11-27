import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:uuid/uuid.dart';

import '../core/api_exception.dart';
import '../core/medication_alarms.dart';
import '../models/medication.dart';
import '../providers/auth_providers.dart';
import '../providers/cart_providers.dart';
import '../providers/medication_providers.dart';
import '../widgets/common.dart';
import '../widgets/status_notice.dart';

const directionsDisclaimer =
    "Follow your doctor's or pharmacist's directions. Reminders don't replace medical advice.";

final _day = DateFormat('EEE, MMM d');

String _number(double value) =>
    value == value.roundToDouble() ? '${value.toInt()}' : '$value';

String _time(String hhmm) {
  final parts = hhmm.split(':');
  return DateFormat('h:mm a')
      .format(DateTime(2000, 1, 1, int.parse(parts[0]), int.parse(parts[1])));
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

    return Scaffold(
      appBar: AppBar(
        title: const Text('Medicine reminders'),
        actions: [
          if (account.value?.medicationReminders ?? false)
            PopupMenuButton<String>(
              onSelected: (choice) => switch (choice) {
                'history' => context.push('/medicines/history'),
                'people' => context.push('/medicines/people'),
                _ => _turnOff(context, ref),
              },
              itemBuilder: (context) => const [
                PopupMenuItem(value: 'history', child: Text('Dose history')),
                PopupMenuItem(
                  value: 'people',
                  child: Text('Family and caregivers'),
                ),
                PopupMenuItem(value: 'off', child: Text('Turn off reminders')),
              ],
            ),
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
      builder: (context) => AlertDialog(
        title: const Text('Turn off medicine reminders?'),
        content: const Text(
          'This deletes your reminders, dose history, family profiles and caregivers.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Keep'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Turn off and delete'),
          ),
        ],
      ),
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

    return ListView(
      padding: const EdgeInsets.all(24),
      children: [
        Icon(
          Icons.alarm_on_rounded,
          size: 48,
          color: theme.colorScheme.primary,
        ),
        if ((ref.watch(caregiversProvider).value?.invitations ?? const [])
            .isNotEmpty)
          Card(
            child: ListTile(
              leading: const Icon(Icons.favorite_outline_rounded),
              title: const Text('Someone asked you to be their caregiver'),
              trailing: const Icon(Icons.chevron_right_rounded),
              onTap: () => context.push('/medicines/people'),
            ),
          ),
        const SizedBox(height: 16),
        Text(
          'Never miss a dose',
          style: theme.textTheme.headlineSmall?.copyWith(
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 12),
        const Text(
          'Get reminders to take your medicine, a heads-up before it runs out, '
          'and an alert before it expires. You can also set reminders for family.',
        ),
        const SizedBox(height: 20),
        const _ConsentPoint(
          icon: Icons.lock_outline_rounded,
          text:
              'We store the medicines, doses and times you set, and the doses you mark. '
              'Medicine details are sensitive personal information under the Data Privacy Act.',
        ),
        const _ConsentPoint(
          icon: Icons.visibility_off_outlined,
          text:
              "Only you see them. The pharmacy doesn't, and a caregiver only gets an alert "
              "when a dose isn't marked.",
        ),
        const _ConsentPoint(
          icon: Icons.delete_outline_rounded,
          text: 'Turn reminders off at any time to delete all of it.',
        ),
        const SizedBox(height: 8),
        Text(directionsDisclaimer, style: theme.textTheme.bodySmall),
        const SizedBox(height: 24),
        FilledButton(
          onPressed: _saving ? null : _accept,
          style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(52)),
          child: const Text('I agree, turn on reminders'),
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

class _ConsentPoint extends StatelessWidget {
  const _ConsentPoint({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 14),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 20, color: Theme.of(context).colorScheme.primary),
        const SizedBox(width: 12),
        Expanded(child: Text(text)),
      ],
    ),
  );
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
  Widget build(BuildContext context) {
    final plans = ref.watch(medicationPlansProvider);
    final suggestions = ref.watch(medicationSuggestionsProvider).value ?? [];

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

        return RefreshIndicator(
          onRefresh: () async {
            ref.invalidate(medicationPlansProvider);
            ref.invalidate(medicationSuggestionsProvider);
            await ref.read(medicationPlansProvider.future);
          },
          child: ListView(
            padding: const EdgeInsets.only(bottom: 32),
            children: [
              if (runningLow.isNotEmpty)
                _Banner(
                  icon: Icons.inventory_2_outlined,
                  text: runningLow.length == 1
                      ? '${runningLow.single.productName} is running low.'
                      : '${runningLow.length} medicines are running low.',
                  action: 'Reorder',
                  onTap: () => context.push(
                    '/medicines/reorder?plans=${runningLow.map((plan) => plan.id).join(',')}',
                  ),
                ),
              if (suggestions.isNotEmpty) ...[
                const SectionTitle(title: 'From your orders'),
                for (final suggestion in suggestions.take(5))
                  ListTile(
                    leading: const Icon(Icons.medication_outlined),
                    title: Text(suggestion.productName),
                    subtitle: Text(
                      suggestion.directions == null
                          ? 'Enter the dose from the box or your doctor'
                          : 'Directions from the pharmacy are ready',
                    ),
                    trailing: const Text('Set up'),
                    onTap: () =>
                        context.push('/medicines/setup', extra: suggestion),
                  ),
              ],
              if (plans.isEmpty && suggestions.isEmpty)
                const Padding(
                  padding: EdgeInsets.only(top: 48),
                  child: EmptyState(
                    icon: Icons.medication_outlined,
                    title: 'No reminders yet',
                    message: 'When medicine from your orders is delivered, it shows up here to set up.',
                  ),
                ),
              for (final entry in groups.entries) ...[
                SectionTitle(title: entry.key),
                for (final plan in entry.value) _PlanCard(plan: plan),
              ],
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
                child: Text(
                  directionsDisclaimer,
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ),
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

Future<void> askHowManyLeft(
  BuildContext context,
  WidgetRef ref,
  MedicationPlan plan,
) async {
  final controller = TextEditingController(text: _number(plan.unitsLeft));
  final value = await showDialog<double>(
    context: context,
    builder: (context) => AlertDialog(
      title: Text('How many ${plan.doseUnit}s are left?'),
      content: TextField(
        controller: controller,
        autofocus: true,
        keyboardType: const TextInputType.numberWithOptions(decimal: true),
        decoration: InputDecoration(
          labelText: plan.productName,
          suffixText: plan.doseUnit,
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Cancel'),
        ),
        FilledButton(
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
  const _PlanCard({required this.plan});

  final MedicationPlan plan;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final details = <String>[
      plan.isAsNeeded
          ? '${plan.doseLabel} when needed'
          : '${plan.doseLabel} at ${plan.doseTimes.map(_time).join(', ')}',
      if (plan.medicineKind == 'course' && plan.endsOn != null)
        'Course ends ${_day.format(plan.endsOn!)}'
      else if (plan.runOutOn != null)
        'About ${plan.unitsLeftLabel} left · runs out ${_day.format(plan.runOutOn!)}'
      else
        'About ${plan.unitsLeftLabel} left',
      if (!plan.isActive) 'Paused',
    ];

    return Card(
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 10),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () => _showActions(context, ref),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                plan.productName,
                style: theme.textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 4),
              for (final line in details)
                Text(line, style: theme.textTheme.bodySmall),
              if (plan.expiresBeforeRunOut && plan.expiresOn != null)
                Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Text(
                    'Part of this supply expires ${_day.format(plan.expiresOn!)}, before you finish it.',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.error,
                    ),
                  ),
                ),
              if ((plan.recentDoseDays ?? 0) >= 3)
                Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Text(
                    "You've needed this 3 days in a row. Still feeling unwell? Talk to a pharmacist or doctor.",
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.error,
                    ),
                  ),
                ),
              if (plan.isAsNeeded && plan.isActive) ...[
                const SizedBox(height: 10),
                FilledButton.tonalIcon(
                  onPressed: () => _tookOne(context, ref),
                  icon: const Icon(Icons.check_rounded),
                  label: const Text('I took one'),
                ),
              ],
            ],
          ),
        ),
      ),
    );
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
      builder: (context) {
        Widget option(IconData icon, String label, String value) => ListTile(
          leading: Icon(icon),
          title: Text(label),
          onTap: () => Navigator.pop(context, value),
        );

        return SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (!plan.isAsNeeded)
                option(
                  Icons.check_circle_outline_rounded,
                  'I took a dose now',
                  'taken',
                ),
              option(
                Icons.numbers_rounded,
                'Update how many are left',
                'count',
              ),
              option(
                plan.isActive
                    ? Icons.pause_circle_outline_rounded
                    : Icons.play_circle_outline_rounded,
                plan.isActive ? 'Pause reminders' : 'Resume reminders',
                'pause',
              ),
              option(Icons.delete_outline_rounded, 'Delete', 'delete'),
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
        case 'delete':
          await api.deleteMedicationPlan(plan.id);
          ref.invalidate(medicationPlansProvider);
          ref.invalidate(medicationSuggestionsProvider);
      }
    } on ApiException catch (error) {
      notice.show(error.friendly);
    }
  }
}

class _Banner extends StatelessWidget {
  const _Banner({
    required this.icon,
    required this.text,
    required this.action,
    required this.onTap,
  });

  final IconData icon;
  final String text;
  final String action;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      padding: const EdgeInsets.fromLTRB(14, 6, 6, 6),
      decoration: BoxDecoration(
        color: scheme.primaryContainer,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Icon(icon, color: scheme.onPrimaryContainer),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              text,
              style: TextStyle(color: scheme.onPrimaryContainer),
            ),
          ),
          TextButton(onPressed: onTap, child: Text(action)),
        ],
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
              icon: Icons.medication_outlined,
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

          return ListView(
            padding: const EdgeInsets.all(24),
            children: [
              Text(
                plan.productName,
                style: Theme.of(context).textTheme.headlineSmall
                    ?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 8),
              Text(
                'Take ${plan.doseLabel}${slot == null ? '' : ' · ${DateFormat('h:mm a').format(slot)}'}',
              ),
              if (plan.directionsNote != null) Text(plan.directionsNote!),
              const SizedBox(height: 8),
              Text(
                directionsDisclaimer,
                style: Theme.of(context).textTheme.bodySmall,
              ),
              const SizedBox(height: 24),
              FilledButton(
                onPressed: _saving ? null : () => _record(plan, 'taken'),
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(52),
                ),
                child: const Text('Taken'),
              ),
              const SizedBox(height: 10),
              OutlinedButton(
                onPressed: _saving ? null : () => _record(plan, 'skipped'),
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size.fromHeight(52),
                ),
                child: const Text('Skip this dose'),
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
              icon: Icons.inventory_2_outlined,
              title: 'Nothing to reorder',
            );
          }

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              for (final plan in chosen)
                ListTile(
                  leading: const Icon(Icons.medication_outlined),
                  title: Text(plan.productName),
                  subtitle: Text(
                    plan.runOutOn == null
                        ? 'About ${plan.unitsLeftLabel} left'
                        : 'About ${plan.unitsLeftLabel} left · runs out ${_day.format(plan.runOutOn!)}',
                  ),
                ),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: _adding ? null : () => _addAll(chosen),
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(52),
                ),
                child: Text(
                  chosen.length == 1 ? 'Add to cart' : 'Add all to cart',
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'Prescription medicines still need a valid prescription at checkout.',
                style: Theme.of(context).textTheme.bodySmall,
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
