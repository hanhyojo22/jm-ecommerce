import 'package:flutter/material.dart';
import 'package:phosphor_icons/phosphor_icons.dart';

import '../theme.dart';

enum GroupedIconTone { primary, teal, neutral, danger }

enum GroupedPillTone { teal, primary, unread }

/// Labeled section container with a Material 3 elevated card and inset hairlines.
class GroupedSection extends StatelessWidget {
  const GroupedSection({
    super.key,
    this.label,
    required this.children,
    this.dividerIndent = 62,
  });

  final String? label;
  final List<Widget> children;
  final double dividerIndent;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (label != null) ...[
          Padding(
            padding: const EdgeInsets.only(left: 4, bottom: 8),
            child: Text(
              label!,
              style: theme.textTheme.labelMedium?.copyWith(
                fontSize: 12.5,
                fontWeight: FontWeight.w700,
                color: scheme.onSurfaceVariant,
                letterSpacing: 0.1,
              ),
            ),
          ),
        ],
        GroupedCard(dividerIndent: dividerIndent, children: children),
      ],
    );
  }
}

/// Surface card with a crisp 1px hairline border and inset dividers.
class GroupedCard extends StatelessWidget {
  const GroupedCard({
    super.key,
    required this.children,
    this.showDividers = true,
    this.dividerIndent = 62,
  });

  final List<Widget> children;
  final bool showDividers;
  final double dividerIndent;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final dark = theme.brightness == Brightness.dark;

    return Container(
      decoration: BoxDecoration(
        color: scheme.surfaceContainerLowest,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: scheme.outlineVariant),
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
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            for (var i = 0; i < children.length; i++) ...[
              if (i > 0 && showDividers)
                Divider(
                  height: 1,
                  thickness: 1,
                  indent: dividerIndent,
                  color: scheme.outlineVariant,
                ),
              children[i],
            ],
          ],
        ),
      ),
    );
  }
}

/// 36x36 tinted rounded square icon container matching Everyday Pharmacy tokens.
class GroupedIconBadge extends StatelessWidget {
  const GroupedIconBadge({
    super.key,
    required this.icon,
    this.tone = GroupedIconTone.neutral,
  });

  final IconData icon;
  final GroupedIconTone tone;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final brand = AppBrandColors.of(context);

    final (Color bg, Color fg) = switch (tone) {
      GroupedIconTone.primary => (
        scheme.primaryContainer,
        scheme.onPrimaryContainer,
      ),
      GroupedIconTone.teal => (brand.careTealSoft, brand.careTeal),
      GroupedIconTone.neutral => (scheme.surfaceContainer, scheme.onSurface),
      GroupedIconTone.danger => (
        scheme.error.withValues(alpha: 0.12),
        scheme.error,
      ),
    };

    return Container(
      width: 36,
      height: 36,
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(10),
      ),
      alignment: Alignment.center,
      child: Icon(icon, size: 19, color: fg),
    );
  }
}

/// Compact pill chip for statuses ("On", "2 active") or unread counts.
class GroupedStatusPill extends StatelessWidget {
  const GroupedStatusPill({
    super.key,
    required this.label,
    this.tone = GroupedPillTone.teal,
  });

  final String label;
  final GroupedPillTone tone;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final brand = AppBrandColors.of(context);

    final (Color bg, Color fg) = switch (tone) {
      GroupedPillTone.teal => (brand.careTealSoft, brand.careTeal),
      GroupedPillTone.primary => (
        scheme.primaryContainer,
        scheme.onPrimaryContainer,
      ),
      GroupedPillTone.unread => (scheme.error, Colors.white),
    };

    return Container(
      constraints: tone == GroupedPillTone.unread
          ? const BoxConstraints(minWidth: 20)
          : null,
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(999),
      ),
      alignment: Alignment.center,
      child: Text(
        label,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: fg,
          fontFeatures: const [FontFeature.tabularFigures()],
        ),
      ),
    );
  }
}

/// Interactive (or static) grouped list row with >= 52dp height.
class GroupedRowTile extends StatelessWidget {
  const GroupedRowTile({
    super.key,
    required this.icon,
    required this.title,
    this.subtitle,
    this.tone = GroupedIconTone.neutral,
    this.boxedIcon = true,
    this.dangerTitle = false,
    this.trailing,
    this.showChevron = true,
    this.onTap,
  });

  final IconData icon;
  final String title;
  final String? subtitle;
  final GroupedIconTone tone;
  final bool boxedIcon;
  final bool dangerTitle;
  final Widget? trailing;
  final bool showChevron;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final hasSubtitle = subtitle != null && subtitle!.isNotEmpty;

    final Widget leading = boxedIcon
        ? GroupedIconBadge(icon: icon, tone: tone)
        : SizedBox(
            width: 24,
            height: 24,
            child: Center(
              child: Icon(
                icon,
                size: 20,
                color: dangerTitle
                    ? scheme.error
                    : tone == GroupedIconTone.teal
                    ? AppBrandColors.of(context).careTeal
                    : tone == GroupedIconTone.primary
                    ? scheme.primary
                    : scheme.onSurfaceVariant,
              ),
            ),
          );

    final content = ConstrainedBox(
      constraints: BoxConstraints(minHeight: hasSubtitle ? 58 : 52),
      child: Padding(
        padding: EdgeInsets.fromLTRB(
          boxedIcon ? 14 : 16,
          hasSubtitle ? 11 : 12,
          12,
          hasSubtitle ? 11 : 12,
        ),
        child: Row(
          children: [
            leading,
            SizedBox(width: boxedIcon ? 12 : 14),
            Expanded(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontSize: 14.5,
                      fontWeight: FontWeight.w600,
                      height: 1.3,
                      color: dangerTitle ? scheme.error : scheme.onSurface,
                    ),
                  ),
                  if (hasSubtitle) ...[
                    const SizedBox(height: 2),
                    Text(
                      subtitle!,
                      style: theme.textTheme.bodySmall?.copyWith(
                        fontSize: 12.5,
                        height: 1.35,
                        color: scheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            if (trailing != null) ...[const SizedBox(width: 8), trailing!],
            if (showChevron && onTap != null) ...[
              const SizedBox(width: 4),
              Icon(
                PhosphorIconsRegular.caretRight,
                size: 17,
                color: scheme.onSurfaceVariant,
              ),
            ],
          ],
        ),
      ),
    );

    if (onTap == null) {
      return content;
    }

    return InkWell(onTap: onTap, child: content);
  }
}

/// Grouped row with a Material 3 Switch control, tappable across the full row.
class GroupedSwitchTile extends StatelessWidget {
  const GroupedSwitchTile({
    super.key,
    required this.icon,
    required this.title,
    this.subtitle,
    this.tone = GroupedIconTone.primary,
    this.boxedIcon = true,
    required this.value,
    required this.onChanged,
  });

  final IconData icon;
  final String title;
  final String? subtitle;
  final GroupedIconTone tone;
  final bool boxedIcon;
  final bool value;
  final ValueChanged<bool>? onChanged;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final hasSubtitle = subtitle != null && subtitle!.isNotEmpty;

    final Widget leading = boxedIcon
        ? GroupedIconBadge(icon: icon, tone: tone)
        : SizedBox(
            width: 24,
            height: 24,
            child: Center(
              child: Icon(
                icon,
                size: 20,
                color: tone == GroupedIconTone.primary
                    ? scheme.primary
                    : scheme.onSurfaceVariant,
              ),
            ),
          );

    return Semantics(
      toggled: value,
      child: InkWell(
        onTap: onChanged == null ? null : () => onChanged!(!value),
        child: ConstrainedBox(
          constraints: BoxConstraints(minHeight: hasSubtitle ? 62 : 52),
          child: Padding(
            padding: EdgeInsets.fromLTRB(
              boxedIcon ? 14 : 16,
              hasSubtitle ? 10 : 8,
              12,
              hasSubtitle ? 10 : 8,
            ),
            child: Row(
              children: [
                leading,
                SizedBox(width: boxedIcon ? 12 : 14),
                Expanded(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          fontSize: 14.5,
                          fontWeight: FontWeight.w600,
                          height: 1.3,
                          color: scheme.onSurface,
                        ),
                      ),
                      if (hasSubtitle) ...[
                        const SizedBox(height: 2),
                        Text(
                          subtitle!,
                          style: theme.textTheme.bodySmall?.copyWith(
                            fontSize: 12.5,
                            height: 1.35,
                            color: scheme.onSurfaceVariant,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Switch(value: value, onChanged: onChanged),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
