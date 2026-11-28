import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:phosphor_icons/phosphor_icons.dart';

import '../core/api_exception.dart';
import '../models/medication.dart';
import '../providers/auth_providers.dart';
import '../providers/medication_providers.dart';
import '../theme.dart';
import '../widgets/grouped_list_card.dart';
import '../widgets/medicine_ui.dart';
import '../widgets/status_notice.dart';

/// Spreads the label's doses a day over waking hours; the buyer can change them.
List<String> defaultDoseTimes(int? dosesPerDay) => switch (dosesPerDay) {
  null || 1 => ['08:00'],
  2 => ['08:00', '20:00'],
  3 => ['08:00', '14:00', '20:00'],
  4 => ['06:00', '12:00', '18:00', '22:00'],
  final count => [
    for (var index = 0; index < count; index++)
      '${(6 + index * 16 ~/ (count - 1)).toString().padLeft(2, '0')}:00',
  ],
};

String _two(int value) => value.toString().padLeft(2, '0');

/// Sets up reminders for one delivered purchase, prefilled from the
/// pharmacy's label directions when it has them.
class MedicineSetupScreen extends ConsumerStatefulWidget {
  const MedicineSetupScreen({super.key, required this.suggestion});

  final MedicationSuggestion suggestion;

  @override
  ConsumerState<MedicineSetupScreen> createState() =>
      _MedicineSetupScreenState();
}

class _MedicineSetupScreenState extends ConsumerState<MedicineSetupScreen> {
  final _form = GlobalKey<FormState>();
  late String _kind;
  late final TextEditingController _dose;
  late final TextEditingController _unit;
  late final TextEditingController _courseDays;
  late final TextEditingController _onHand;
  late List<String> _times;
  String? _profileId;
  bool _showName = false;
  bool _saving = false;

  MedicationDirections? get _directions => widget.suggestion.directions;

  @override
  void initState() {
    super.initState();
    final directions = _directions;
    _kind = directions?.medicineKind ?? 'maintenance';
    String number(double? value) =>
        value == null ? '' : formatMedicineNumber(value);
    _dose = TextEditingController(text: number(directions?.doseQuantity));
    _unit = TextEditingController(text: directions?.doseUnit ?? 'tablet');
    _courseDays = TextEditingController(
      text: directions?.courseDays?.toString() ?? '',
    );
    _onHand = TextEditingController();
    _times = defaultDoseTimes(directions?.dosesPerDay);
  }

  @override
  void dispose() {
    _dose.dispose();
    _unit.dispose();
    _courseDays.dispose();
    _onHand.dispose();
    super.dispose();
  }

  String get _kindHint => switch (_kind) {
    'course' => 'Fixed reminders for the length of the course, then finished.',
    'as_needed' =>
      'No fixed alarms — log doses as you take them, with label safety checks.',
    _ => 'Daily reminders plus run-out and refill heads-up.',
  };

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final profiles = ref.watch(careProfilesProvider).value ?? const [];
    final directions = _directions;
    final needsCount = directions?.packUnits == null;

    return Scaffold(
      appBar: AppBar(title: const Text('Set up reminders')),
      body: Form(
        key: _form,
        child: Column(
          children: [
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
                children: [
                  MedicineCardShell(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            GroupedIconBadge(
                              icon: PhosphorIconsRegular.pill,
                              tone: directions != null
                                  ? GroupedIconTone.teal
                                  : GroupedIconTone.primary,
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    widget.suggestion.productName,
                                    style: theme.textTheme.titleMedium
                                        ?.copyWith(
                                          fontSize: 16.5,
                                          fontWeight: FontWeight.w800,
                                          height: 1.25,
                                        ),
                                  ),
                                  const SizedBox(height: 6),
                                  GroupedStatusPill(
                                    label: directions != null
                                        ? 'Prefilled from pharmacy label'
                                        : 'Enter from box or doctor',
                                    tone: directions != null
                                        ? GroupedPillTone.teal
                                        : GroupedPillTone.primary,
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        Text(
                          directions == null
                              ? "The pharmacy hasn't added directions for this product. Enter the dose from the box or your doctor."
                              : 'Filled in from the pharmacy label. Change anything your doctor told you differently.',
                          style: theme.textTheme.bodySmall?.copyWith(
                            fontSize: 12.5,
                            height: 1.45,
                            color: scheme.onSurfaceVariant,
                          ),
                        ),
                        if (directions?.directionsNote != null &&
                            directions!.directionsNote!.trim().isNotEmpty) ...[
                          const SizedBox(height: 12),
                          MedicineInlineAlert(
                            icon: PhosphorIconsRegular.prescription,
                            tone: MedicineAlertTone.teal,
                            title: 'Pharmacy note',
                            text: directions.directionsNote!,
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),
                  const MedicineSectionHeader(title: 'Person & schedule'),
                  MedicineCardShell(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        DropdownButtonFormField<String?>(
                          initialValue: _profileId,
                          decoration: const InputDecoration(
                            labelText: 'Who is it for?',
                          ),
                          items: [
                            const DropdownMenuItem(
                              value: null,
                              child: Text('Me'),
                            ),
                            for (final profile in profiles)
                              DropdownMenuItem(
                                value: profile.id,
                                child: Text(profile.name),
                              ),
                          ],
                          onChanged: (value) =>
                              setState(() => _profileId = value),
                        ),
                        const SizedBox(height: 16),
                        Text(
                          'Schedule type',
                          style: theme.textTheme.labelMedium?.copyWith(
                            color: scheme.onSurfaceVariant,
                          ),
                        ),
                        const SizedBox(height: 8),
                        SegmentedButton<String>(
                          showSelectedIcon: false,
                          segments: const [
                            ButtonSegment(
                              value: 'maintenance',
                              label: Text('Every day'),
                            ),
                            ButtonSegment(
                              value: 'course',
                              label: Text('Course'),
                            ),
                            ButtonSegment(
                              value: 'as_needed',
                              label: Text('As needed'),
                            ),
                          ],
                          selected: {_kind},
                          onSelectionChanged: (value) =>
                              setState(() => _kind = value.first),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          _kindHint,
                          style: theme.textTheme.bodySmall?.copyWith(
                            fontSize: 12,
                            color: scheme.onSurfaceVariant,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),
                  const MedicineSectionHeader(title: 'Dose & timing'),
                  MedicineCardShell(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Expanded(
                              child: TextFormField(
                                controller: _dose,
                                keyboardType:
                                    const TextInputType.numberWithOptions(
                                      decimal: true,
                                    ),
                                decoration: const InputDecoration(
                                  labelText: 'Dose',
                                ),
                                validator: (value) =>
                                    (double.tryParse(value?.trim() ?? '') ??
                                            0) <=
                                        0
                                    ? 'Enter the dose'
                                    : null,
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: TextFormField(
                                controller: _unit,
                                decoration: const InputDecoration(
                                  labelText: 'Unit',
                                  hintText: 'tablet, mL, capsule',
                                ),
                                validator: (value) =>
                                    (value?.trim().isEmpty ?? true)
                                    ? 'Enter a unit'
                                    : null,
                              ),
                            ),
                          ],
                        ),
                        if (_kind != 'as_needed') ...[
                          const SizedBox(height: 16),
                          Text(
                            'Reminder times',
                            style: theme.textTheme.labelMedium?.copyWith(
                              color: scheme.onSurfaceVariant,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: [
                              for (final time in _times)
                                InputChip(
                                  avatar: Icon(
                                    PhosphorIconsRegular.clock,
                                    size: 16,
                                    color: scheme.primary,
                                  ),
                                  label: Text(
                                    formatMedicineTime(time),
                                    style: const TextStyle(
                                      fontFamily: appFontFamily,
                                      fontFeatures: [
                                        FontFeature.tabularFigures(),
                                      ],
                                    ),
                                  ),
                                  onPressed: () => _pickTime(time),
                                  onDeleted: _times.length == 1
                                      ? null
                                      : () =>
                                            setState(() => _times.remove(time)),
                                ),
                              ActionChip(
                                avatar: const Icon(
                                  PhosphorIconsRegular.plus,
                                  size: 16,
                                ),
                                label: const Text('Add time'),
                                onPressed: () => _pickTime(null),
                              ),
                            ],
                          ),
                        ],
                        if (_kind == 'course') ...[
                          const SizedBox(height: 16),
                          TextFormField(
                            controller: _courseDays,
                            keyboardType: TextInputType.number,
                            decoration: const InputDecoration(
                              labelText: 'How many days?',
                            ),
                            validator: (value) =>
                                (int.tryParse(value ?? '') ?? 0) < 1
                                ? 'Enter the number of days'
                                : null,
                          ),
                        ],
                        if (needsCount) ...[
                          const SizedBox(height: 16),
                          TextFormField(
                            controller: _onHand,
                            keyboardType: const TextInputType.numberWithOptions(
                              decimal: true,
                            ),
                            decoration: const InputDecoration(
                              labelText: 'How many did you receive?',
                              helperText: 'For example 30 tablets or 60 mL',
                            ),
                            validator: (value) =>
                                double.tryParse(value?.trim() ?? '') == null
                                ? 'Enter how many you received'
                                : null,
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),
                  GroupedSection(
                    label: 'Lock screen privacy',
                    children: [
                      GroupedSwitchTile(
                        icon: PhosphorIconsRegular.eyeSlash,
                        tone: GroupedIconTone.neutral,
                        title: 'Show the medicine name on the lock screen',
                        subtitle:
                            'Off: reminders just say "Time for your medicine".',
                        value: _showName,
                        onChanged: (value) => setState(() => _showName = value),
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
                onPressed: _saving ? null : _save,
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
                    : const Text('Save reminders'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _pickTime(String? current) async {
    final parts = (current ?? '12:00').split(':');
    final picked = await showTimePicker(
      context: context,
      initialTime: TimeOfDay(
        hour: int.parse(parts[0]),
        minute: int.parse(parts[1]),
      ),
    );
    if (picked == null) {
      return;
    }
    final value = '${_two(picked.hour)}:${_two(picked.minute)}';
    setState(() {
      if (current != null) {
        _times.remove(current);
      }
      if (!_times.contains(value)) {
        _times = [..._times, value]..sort();
      }
    });
  }

  Future<void> _save() async {
    if (!(_form.currentState?.validate() ?? false)) {
      return;
    }
    setState(() => _saving = true);
    final notice = AppNotice.of(context);
    final router = GoRouter.of(context);
    final directions = _directions;
    try {
      await ref.read(apiClientProvider).startMedicationPlan({
        'order_item_id': widget.suggestion.orderItemId,
        'care_profile_id': _profileId,
        'medicine_kind': _kind,
        'dose_quantity': double.parse(_dose.text.trim()),
        'dose_unit': _unit.text.trim(),
        'dose_times': _kind == 'as_needed' ? <String>[] : _times,
        'starts_on': DateFormat('yyyy-MM-dd').format(DateTime.now()),
        if (_kind == 'course') 'course_days': int.parse(_courseDays.text),
        if (_onHand.text.trim().isNotEmpty)
          'units_on_hand': double.parse(_onHand.text.trim()),
        'min_hours_between': directions?.minHoursBetween,
        'max_doses_per_day': directions?.maxDosesPerDay,
        'directions_note': directions?.directionsNote,
        'show_name_on_lock_screen': _showName,
      });
      ref.invalidate(medicationPlansProvider);
      ref.invalidate(medicationSuggestionsProvider);
      notice.show('Reminders are set.', kind: NoticeKind.success);
      router.pop();
    } on ApiException catch (error) {
      notice.show(error.friendly);
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }
}
