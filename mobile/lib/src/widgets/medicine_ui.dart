import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:phosphor_icons/phosphor_icons.dart';

import '../theme.dart';

const medicineDirectionsDisclaimer =
    "Follow your doctor's or pharmacist's directions. Reminders don't replace medical advice.";

final DateFormat _medicineDayFormat = DateFormat('EEE, MMM d');
final DateFormat _medicineTimeFormat = DateFormat('h:mm a');

String formatMedicineNumber(double value) =>
    value == value.roundToDouble() ? '${value.toInt()}' : '$value';

String formatMedicineDay(DateTime date) => _medicineDayFormat.format(date);

String formatMedicineClock(DateTime dateTime) =>
    _medicineTimeFormat.format(dateTime);

String formatMedicineTime(String hhmm) {
  final parts = hhmm.split(':');
  return _medicineTimeFormat.format(
    DateTime(2000, 1, 1, int.parse(parts[0]), int.parse(parts[1])),
  );
}

enum MedicineAlertTone { teal, warning, danger, neutral }

/// Surface card matching [GroupedCard] tokens: 14px radius, 1px hairline, subtle daylight lift.
class MedicineCardShell extends StatelessWidget {
  const MedicineCardShell({
    super.key,
    required this.child,
    this.padding,
    this.borderColor,
    this.backgroundColor,
    this.onTap,
  });

  final Widget child;
  final EdgeInsetsGeometry? padding;
  final Color? borderColor;
  final Color? backgroundColor;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final dark = theme.brightness == Brightness.dark;

    final inner = padding == null
        ? child
        : Padding(padding: padding!, child: child);

    return Container(
      decoration: BoxDecoration(
        color: backgroundColor ?? scheme.surfaceContainerLowest,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: borderColor ?? scheme.outlineVariant),
        boxShadow: dark
            ? null
            : const [
                BoxShadow(
                  color: Color(0x0F0F1D40),
                  blurRadius: 2,
                  offset: Offset(0, 1),
                ),
              ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Material(
        type: MaterialType.transparency,
        child: onTap == null
            ? inner
            : InkWell(onTap: onTap, child: inner),
      ),
    );
  }
}

/// Consistent section header matching Everyday Pharmacy grouped lists.
class MedicineSectionHeader extends StatelessWidget {
  const MedicineSectionHeader({
    super.key,
    required this.title,
    this.count,
    this.trailing,
    this.padding = const EdgeInsets.fromLTRB(4, 0, 0, 8),
  });

  final String title;
  final int? count;
  final Widget? trailing;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Padding(
      padding: padding,
      child: Row(
        children: [
          Expanded(
            child: Row(
              children: [
                Flexible(
                  child: Text(
                    title,
                    style: theme.textTheme.labelMedium?.copyWith(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: scheme.onSurfaceVariant,
                      letterSpacing: 0.05,
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                if (count != null) ...[
                  const SizedBox(width: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 7,
                      vertical: 2,
                    ),
                    decoration: BoxDecoration(
                      color: scheme.surfaceContainer,
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      '$count',
                      style: theme.textTheme.labelSmall?.copyWith(
                        color: scheme.onSurfaceVariant,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
          ?trailing,
        ],
      ),
    );
  }
}

/// Compact badge showing the medicine schedule type or paused status.
class MedicineKindBadge extends StatelessWidget {
  const MedicineKindBadge({
    super.key,
    required this.medicineKind,
    this.isActive = true,
  });

  final String medicineKind;
  final bool isActive;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final brand = AppBrandColors.of(context);

    final (String label, Color bg, Color fg, Color dot) = !isActive
        ? (
            'Paused',
            scheme.surfaceContainer,
            scheme.onSurfaceVariant,
            scheme.outline,
          )
        : switch (medicineKind) {
            'course' => (
              'Course',
              scheme.primaryContainer,
              scheme.onPrimaryContainer,
              scheme.primary,
            ),
            'as_needed' => (
              'As needed',
              scheme.surfaceContainer,
              scheme.onSurfaceVariant,
              scheme.onSurfaceVariant,
            ),
            _ => (
              'Every day',
              brand.careTealSoft,
              brand.careTeal,
              brand.careTeal,
            ),
          };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 6,
            height: 6,
            decoration: BoxDecoration(color: dot, shape: BoxShape.circle),
          ),
          const SizedBox(width: 6),
          Text(
            label,
            style: TextStyle(
              fontFamily: appFontFamily,
              fontSize: 11.5,
              fontWeight: FontWeight.w700,
              height: 1.1,
              color: fg,
            ),
          ),
        ],
      ),
    );
  }
}

/// Tinted inline callout for pharmacy label notes, warnings, and clinical notices.
class MedicineInlineAlert extends StatelessWidget {
  const MedicineInlineAlert({
    super.key,
    required this.icon,
    required this.text,
    this.title,
    this.tone = MedicineAlertTone.neutral,
    this.trailing,
  });

  final IconData icon;
  final String? title;
  final String text;
  final MedicineAlertTone tone;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final brand = AppBrandColors.of(context);
    final dark = theme.brightness == Brightness.dark;

    final (Color bg, Color border, Color iconFg, Color textFg) = switch (tone) {
      MedicineAlertTone.teal => (
        brand.careTealSoft,
        brand.careTeal.withValues(alpha: dark ? 0.35 : 0.22),
        brand.careTeal,
        scheme.onSurface,
      ),
      MedicineAlertTone.warning => (
        dark ? const Color(0xFF2B2210) : sunWash,
        dark ? const Color(0xFF5A4519) : sunWashEdge,
        dark ? const Color(0xFFFFC94A) : const Color(0xFF945E00),
        dark ? const Color(0xFFFDF3D6) : const Color(0xFF472D00),
      ),
      MedicineAlertTone.danger => (
        scheme.error.withValues(alpha: dark ? 0.16 : 0.08),
        scheme.error.withValues(alpha: dark ? 0.38 : 0.24),
        scheme.error,
        dark ? const Color(0xFFFECACA) : const Color(0xFF7F1D1D),
      ),
      MedicineAlertTone.neutral => (
        scheme.surfaceContainer,
        scheme.outlineVariant,
        scheme.onSurfaceVariant,
        scheme.onSurfaceVariant,
      ),
    };

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: border),
      ),
      child: Row(
        crossAxisAlignment: title != null
            ? CrossAxisAlignment.start
            : CrossAxisAlignment.center,
        children: [
          Padding(
            padding: EdgeInsets.only(top: title != null ? 1 : 0),
            child: Icon(icon, size: 18, color: iconFg),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                if (title != null) ...[
                  Text(
                    title!,
                    style: theme.textTheme.labelMedium?.copyWith(
                      fontSize: 12.5,
                      fontWeight: FontWeight.w700,
                      color: textFg,
                    ),
                  ),
                  const SizedBox(height: 2),
                ],
                Text(
                  text,
                  style: theme.textTheme.bodySmall?.copyWith(
                    fontSize: 12.5,
                    height: 1.4,
                    color: textFg,
                  ),
                ),
              ],
            ),
          ),
          if (trailing != null) ...[const SizedBox(width: 8), trailing!],
        ],
      ),
    );
  }
}

/// Calm clinical disclaimer shown across medicine reminder screens.
class MedicineDisclaimerNote extends StatelessWidget {
  const MedicineDisclaimerNote({
    super.key,
    this.text = medicineDirectionsDisclaimer,
  });

  final String text;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: scheme.surfaceContainer.withValues(alpha: 0.7),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: scheme.outlineVariant),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 1),
            child: Icon(
              PhosphorIconsRegular.info,
              size: 16,
              color: scheme.onSurfaceVariant,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              text,
              style: theme.textTheme.bodySmall?.copyWith(
                fontSize: 12,
                height: 1.45,
                color: scheme.onSurfaceVariant,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Pinned bottom action area with a quiet top hairline and safe-area padding.
class MedicineBottomBar extends StatelessWidget {
  const MedicineBottomBar({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return DecoratedBox(
      decoration: BoxDecoration(
        color: scheme.surfaceContainerLowest,
        border: Border(top: BorderSide(color: scheme.outlineVariant)),
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 14),
          child: child,
        ),
      ),
    );
  }
}
