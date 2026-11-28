import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:phosphor_icons/phosphor_icons.dart';

import '../core/api_exception.dart';
import '../models/medication.dart';
import '../providers/auth_providers.dart';
import '../providers/medication_providers.dart';
import '../theme.dart';
import '../widgets/common.dart';
import '../widgets/grouped_list_card.dart';
import '../widgets/medicine_ui.dart';
import '../widgets/status_notice.dart';

const _relationPresets = [
  'Mother',
  'Father',
  'Lola',
  'Lolo',
  'Child',
  'Spouse',
];

String _initials(String name) {
  final parts = name
      .trim()
      .split(RegExp(r'\s+'))
      .where((part) => part.isNotEmpty)
      .toList();
  if (parts.isEmpty) {
    return '?';
  }
  if (parts.length == 1) {
    return parts.first.substring(0, 1).toUpperCase();
  }
  return '${parts.first[0]}${parts.last[0]}'.toUpperCase();
}

/// Family members whose medicine the buyer manages, caregivers who get an
/// alert when a dose isn't marked, and invitations to be someone's caregiver.
class MedicinePeopleScreen extends ConsumerWidget {
  const MedicinePeopleScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final consented = ref.watch(meProvider).value?.medicationReminders ?? false;
    final profiles = consented
        ? ref.watch(careProfilesProvider)
        : const AsyncValue<List<CareProfile>>.data([]);
    final caregivers = ref.watch(caregiversProvider);
    final plans = consented
        ? (ref.watch(medicationPlansProvider).value ?? const <MedicationPlan>[])
        : const <MedicationPlan>[];

    final planCounts = <String, int>{};
    for (final plan in plans) {
      if (plan.profileId != null) {
        planCounts[plan.profileId!] = (planCounts[plan.profileId!] ?? 0) + 1;
      }
    }

    final profileList = profiles.value ?? const <CareProfile>[];
    final caregiverData = caregivers.value;
    final pendingInvites =
        caregiverData?.invitations.where((link) => !link.isAccepted).toList() ??
        const <CaregiverLink>[];
    final activeLookAfter =
        caregiverData?.invitations.where((link) => link.isAccepted).toList() ??
        const <CaregiverLink>[];
    final allCaregivers = caregiverData?.caregivers ?? const <CaregiverLink>[];

    return Scaffold(
      appBar: AppBar(title: const Text('Family and caregivers')),
      body: RefreshIndicator(
        onRefresh: () async {
          if (consented) {
            ref.invalidate(careProfilesProvider);
            ref.invalidate(medicationPlansProvider);
          }
          ref.invalidate(caregiversProvider);
          await ref.read(caregiversProvider.future);
        },
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
          children: [
            if (pendingInvites.isNotEmpty) ...[
              MedicineSectionHeader(
                title: pendingInvites.length == 1
                    ? 'Caregiver invitation'
                    : 'Caregiver invitations',
                count: pendingInvites.length,
              ),
              for (var i = 0; i < pendingInvites.length; i++) ...[
                if (i > 0) const SizedBox(height: 10),
                _PendingInvitationCard(
                  key: ValueKey(pendingInvites[i].id),
                  link: pendingInvites[i],
                  onAccept: () => _accept(context, ref, pendingInvites[i]),
                  onDecline: () => _confirmRemove(
                    context,
                    ref,
                    pendingInvites[i],
                    isPatientSide: false,
                    isPendingInvite: true,
                  ),
                ),
              ],
              const SizedBox(height: 20),
            ],
            if (consented) ...[
              _CareCircleSummaryCard(
                familyCount: profileList.length,
                caregiverCount: allCaregivers.length,
                lookingAfterCount: activeLookAfter.length,
              ),
              const SizedBox(height: 20),
              MedicineSectionHeader(
                title: 'Family profiles',
                count: profileList.isEmpty ? null : profileList.length,
                padding: const EdgeInsets.fromLTRB(4, 0, 0, 4),
                trailing: profileList.isEmpty
                    ? null
                    : TextButton.icon(
                        style: TextButton.styleFrom(
                          visualDensity: VisualDensity.compact,
                        ),
                        onPressed: () => _openProfileSheet(context, ref, null),
                        icon: const Icon(PhosphorIconsRegular.plus, size: 16),
                        label: const Text('Add'),
                      ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(4, 0, 4, 10),
                child: Text(
                  'Group reminders for a child, parent, or dependent on this phone.',
                  style: theme.textTheme.bodySmall?.copyWith(
                    fontSize: 12.5,
                    height: 1.45,
                    color: scheme.onSurfaceVariant,
                  ),
                ),
              ),
              profiles.when(
                data: (items) => items.isEmpty
                    ? _SectionEmptyCard(
                        icon: PhosphorIconsRegular.users,
                        tone: GroupedIconTone.primary,
                        title: 'No family profiles yet',
                        subtitle:
                            'Add a child, parent, or lola to keep their medicines separate from yours.',
                        actionLabel: 'Add family member',
                        actionIcon: PhosphorIconsRegular.plus,
                        onTap: () => _openProfileSheet(context, ref, null),
                      )
                    : GroupedCard(
                        dividerIndent: 66,
                        children: [
                          for (final profile in items)
                            _FamilyProfileRow(
                              key: ValueKey(profile.id),
                              profile: profile,
                              reminderCount: planCounts[profile.id] ?? 0,
                              onTap: () => _openProfileSheet(
                                context,
                                ref,
                                profile,
                                reminderCount: planCounts[profile.id] ?? 0,
                              ),
                            ),
                        ],
                      ),
                loading: () => const _SectionLoadingCard(),
                error: (error, _) => ErrorView(
                  error: error,
                  onRetry: () => ref.invalidate(careProfilesProvider),
                ),
              ),
              const SizedBox(height: 24),
              MedicineSectionHeader(
                title: 'Your caregivers',
                count: allCaregivers.isEmpty ? null : allCaregivers.length,
                padding: const EdgeInsets.fromLTRB(4, 0, 0, 4),
                trailing: allCaregivers.isEmpty
                    ? null
                    : TextButton.icon(
                        style: TextButton.styleFrom(
                          visualDensity: VisualDensity.compact,
                        ),
                        onPressed: () => _openInviteSheet(context, ref),
                        icon: const Icon(
                          PhosphorIconsRegular.userPlus,
                          size: 16,
                        ),
                        label: const Text('Invite'),
                      ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(4, 0, 4, 10),
                child: Text(
                  "Gets an alert in their Everyday Pharmacy app if you haven't marked a dose within an hour. They can't view your medicine list.",
                  style: theme.textTheme.bodySmall?.copyWith(
                    fontSize: 12.5,
                    height: 1.45,
                    color: scheme.onSurfaceVariant,
                  ),
                ),
              ),
            ],
            caregivers.when(
              data: (data) {
                final acceptedInvites = data.invitations
                    .where((link) => link.isAccepted)
                    .toList();
                final hasAnyVisible =
                    consented || data.invitations.isNotEmpty;

                if (!hasAnyVisible) {
                  return const Padding(
                    padding: EdgeInsets.only(top: 40),
                    child: EmptyState(
                      icon: PhosphorIconsRegular.users,
                      title: 'No invitations yet',
                      message:
                          'When someone invites you to be their caregiver, it appears here.',
                    ),
                  );
                }

                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (consented) ...[
                      if (data.caregivers.isEmpty)
                        _SectionEmptyCard(
                          icon: PhosphorIconsRegular.shieldCheck,
                          tone: GroupedIconTone.teal,
                          title: 'No caregivers yet',
                          subtitle:
                              'Invite a trusted family member with an Everyday Pharmacy account for missed-dose alerts.',
                          actionLabel: 'Invite a caregiver',
                          actionIcon: PhosphorIconsRegular.userPlus,
                          onTap: () => _openInviteSheet(context, ref),
                        )
                      else
                        GroupedCard(
                          dividerIndent: 66,
                          children: [
                            for (final link in data.caregivers)
                              _CaregiverRow(
                                key: ValueKey(link.id),
                                link: link,
                                onRemove: () =>
                                    _confirmRemove(context, ref, link),
                              ),
                          ],
                        ),
                    ],
                    if (acceptedInvites.isNotEmpty) ...[
                      if (consented) const SizedBox(height: 24),
                      MedicineSectionHeader(
                        title: 'You look after',
                        count: acceptedInvites.length,
                      ),
                      GroupedCard(
                        dividerIndent: 66,
                        children: [
                          for (final link in acceptedInvites)
                            _LookingAfterRow(
                              key: ValueKey(link.id),
                              link: link,
                              onStop: () => _confirmRemove(
                                context,
                                ref,
                                link,
                                isPatientSide: false,
                              ),
                            ),
                        ],
                      ),
                    ],
                  ],
                );
              },
              loading: () => const _SectionLoadingCard(),
              error: (error, _) => ErrorView(
                error: error,
                onRetry: () => ref.invalidate(caregiversProvider),
              ),
            ),
            if (consented) ...[
              const SizedBox(height: 20),
              const MedicineInlineAlert(
                icon: PhosphorIconsRegular.lockSimple,
                tone: MedicineAlertTone.neutral,
                text:
                    'Caregivers only receive push alerts for unmarked doses. Your medicine names stay hidden unless you choose to include them.',
              ),
            ],
          ],
        ),
      ),
    );
  }

  Future<void> _openProfileSheet(
    BuildContext context,
    WidgetRef ref,
    CareProfile? profile, {
    int reminderCount = 0,
  }) async {
    final result = await showModalBottomSheet<({String action, String name, String? relation})>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (sheetContext) => _ProfileFormSheet(
        profile: profile,
        reminderCount: reminderCount,
      ),
    );
    if (result == null || !context.mounted) {
      return;
    }
    final notice = AppNotice.of(context);
    final api = ref.read(apiClientProvider);
    try {
      if (result.action == 'delete' && profile != null) {
        final confirmed = await showDialog<bool>(
          context: context,
          builder: (dialogContext) {
            final scheme = Theme.of(dialogContext).colorScheme;
            return AlertDialog(
              title: Text('Remove ${profile.name}?'),
              content: Text(
                reminderCount > 0
                    ? 'This also deletes ${profile.name}\'s $reminderCount active ${reminderCount == 1 ? 'reminder' : 'reminders'} and dose history.'
                    : 'This removes ${profile.name}\'s profile and any reminders linked to them.',
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
                  child: const Text('Remove'),
                ),
              ],
            );
          },
        );
        if (confirmed != true || !context.mounted) {
          return;
        }
        await api.deleteCareProfile(profile.id);
        ref.invalidate(medicationPlansProvider);
        ref.invalidate(careProfilesProvider);
        notice.show('Family member removed.', kind: NoticeKind.info);
      } else if (result.action == 'save' && result.name.isNotEmpty) {
        await api.saveCareProfile(
          id: profile?.id,
          name: result.name,
          relation: result.relation,
        );
        ref.invalidate(careProfilesProvider);
        ref.invalidate(medicationPlansProvider);
        notice.show(
          profile == null ? 'Family member added.' : 'Family member updated.',
          kind: NoticeKind.success,
        );
      }
    } on ApiException catch (error) {
      notice.show(error.friendly);
    }
  }

  Future<void> _openInviteSheet(BuildContext context, WidgetRef ref) async {
    final result = await showModalBottomSheet<({String email, bool shareNames})>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (sheetContext) => const _InviteCaregiverSheet(),
    );
    if (result == null || !context.mounted) {
      return;
    }
    final notice = AppNotice.of(context);
    try {
      await ref
          .read(apiClientProvider)
          .inviteCaregiver(
            email: result.email,
            shareMedicineNames: result.shareNames,
          );
      ref.invalidate(caregiversProvider);
      notice.show(
        'Invitation sent. They accept it in their app under Medicine reminders.',
        kind: NoticeKind.success,
      );
    } on ApiException catch (error) {
      notice.show(error.friendly);
    }
  }

  Future<void> _accept(
    BuildContext context,
    WidgetRef ref,
    CaregiverLink link,
  ) async {
    final notice = AppNotice.of(context);
    try {
      await ref.read(apiClientProvider).acceptCaregiver(link.id);
      ref.invalidate(caregiversProvider);
      notice.show(
        'You will now get alerts for ${link.patientName ?? 'this person'}.',
        kind: NoticeKind.success,
      );
    } on ApiException catch (error) {
      notice.show(error.friendly);
    }
  }

  Future<void> _confirmRemove(
    BuildContext context,
    WidgetRef ref,
    CaregiverLink link, {
    bool isPatientSide = true,
    bool isPendingInvite = false,
  }) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) {
        final scheme = Theme.of(dialogContext).colorScheme;
        final title = isPendingInvite
            ? 'Decline caregiver invitation?'
            : isPatientSide
            ? (link.isAccepted ? 'Remove caregiver?' : 'Cancel invitation?')
            : 'Stop caregiver alerts?';
        final body = isPendingInvite
            ? 'You will not receive missed-dose alerts for ${link.patientName ?? 'this person'}.'
            : isPatientSide
            ? (link.isAccepted
                  ? '${link.caregiverName ?? link.invitedEmail} will no longer receive missed-dose alerts.'
                  : 'The pending invitation to ${link.invitedEmail} will be cancelled.')
            : 'You will stop receiving missed-dose alerts for ${link.patientName ?? 'this person'}.';
        final actionLabel = isPendingInvite
            ? 'Decline'
            : isPatientSide
            ? (link.isAccepted ? 'Remove' : 'Cancel invite')
            : 'Stop alerts';

        return AlertDialog(
          title: Text(title),
          content: Text(body),
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
              child: Text(actionLabel),
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
      await ref.read(apiClientProvider).removeCaregiver(link.id);
      ref.invalidate(caregiversProvider);
      notice.show(
        isPendingInvite
            ? 'Invitation declined.'
            : isPatientSide
            ? (link.isAccepted ? 'Caregiver removed.' : 'Invitation cancelled.')
            : 'Alerts stopped.',
        kind: NoticeKind.info,
      );
    } on ApiException catch (error) {
      notice.show(error.friendly);
    }
  }
}

class _CareCircleSummaryCard extends StatelessWidget {
  const _CareCircleSummaryCard({
    required this.familyCount,
    required this.caregiverCount,
    required this.lookingAfterCount,
  });

  final int familyCount;
  final int caregiverCount;
  final int lookingAfterCount;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return MedicineCardShell(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
      child: IntrinsicHeight(
        child: Row(
          children: [
            Expanded(
              child: _SummaryStat(
                value: '$familyCount',
                label: 'Family',
                icon: PhosphorIconsRegular.users,
              ),
            ),
            VerticalDivider(
              width: 1,
              thickness: 1,
              color: scheme.outlineVariant,
            ),
            Expanded(
              child: _SummaryStat(
                value: '$caregiverCount',
                label: 'Caregivers',
                icon: PhosphorIconsRegular.shieldCheck,
              ),
            ),
            VerticalDivider(
              width: 1,
              thickness: 1,
              color: scheme.outlineVariant,
            ),
            Expanded(
              child: _SummaryStat(
                value: '$lookingAfterCount',
                label: 'Looking after',
                icon: PhosphorIconsRegular.heart,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SummaryStat extends StatelessWidget {
  const _SummaryStat({
    required this.value,
    required this.label,
    required this.icon,
  });

  final String value;
  final String label;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          value,
          style: theme.textTheme.titleLarge?.copyWith(
            fontSize: 20,
            fontWeight: FontWeight.w800,
            fontFeatures: const [FontFeature.tabularFigures()],
          ),
        ),
        const SizedBox(height: 2),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 13, color: scheme.onSurfaceVariant),
            const SizedBox(width: 4),
            Flexible(
              child: Text(
                label,
                style: theme.textTheme.bodySmall?.copyWith(
                  fontSize: 11.5,
                  fontWeight: FontWeight.w600,
                  color: scheme.onSurfaceVariant,
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _InitialsAvatar extends StatelessWidget {
  const _InitialsAvatar({required this.name, this.tone = GroupedIconTone.primary});

  final String name;
  final GroupedIconTone tone;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final brand = AppBrandColors.of(context);

    final (Color bg, Color fg) = switch (tone) {
      GroupedIconTone.teal => (brand.careTealSoft, brand.careTeal),
      GroupedIconTone.primary => (
        scheme.primaryContainer,
        scheme.onPrimaryContainer,
      ),
      GroupedIconTone.danger => (
        scheme.error.withValues(alpha: 0.12),
        scheme.error,
      ),
      GroupedIconTone.neutral => (scheme.surfaceContainer, scheme.onSurface),
    };

    return Container(
      width: 40,
      height: 40,
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(12),
      ),
      alignment: Alignment.center,
      child: Text(
        _initials(name),
        style: TextStyle(
          fontFamily: appFontFamily,
          fontSize: 14,
          fontWeight: FontWeight.w800,
          color: fg,
        ),
      ),
    );
  }
}

class _FamilyProfileRow extends StatelessWidget {
  const _FamilyProfileRow({
    super.key,
    required this.profile,
    required this.reminderCount,
    required this.onTap,
  });

  final CareProfile profile;
  final int reminderCount;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final countText = reminderCount == 0
        ? 'No reminders yet'
        : '$reminderCount ${reminderCount == 1 ? 'reminder' : 'reminders'}';
    final subtitle = profile.relation == null || profile.relation!.trim().isEmpty
        ? countText
        : '${profile.relation} · $countText';

    return InkWell(
      onTap: onTap,
      child: ConstrainedBox(
        constraints: const BoxConstraints(minHeight: 62),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(14, 11, 12, 11),
          child: Row(
            children: [
              _InitialsAvatar(
                name: profile.name,
                tone: GroupedIconTone.primary,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      profile.name,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        fontSize: 14.5,
                        fontWeight: FontWeight.w700,
                        color: scheme.onSurface,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      style: theme.textTheme.bodySmall?.copyWith(
                        fontSize: 12.5,
                        color: scheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Icon(
                PhosphorIconsRegular.pencilSimple,
                size: 17,
                color: scheme.onSurfaceVariant,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CaregiverRow extends StatelessWidget {
  const _CaregiverRow({
    super.key,
    required this.link,
    required this.onRemove,
  });

  final CaregiverLink link;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final displayName = link.caregiverName ?? link.invitedEmail;
    final showEmailLine =
        link.caregiverName != null && link.invitedEmail.isNotEmpty;

    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 12, 8, 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _InitialsAvatar(
            name: displayName,
            tone: link.isAccepted
                ? GroupedIconTone.teal
                : GroupedIconTone.neutral,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        displayName,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          fontSize: 14.5,
                          fontWeight: FontWeight.w700,
                          color: scheme.onSurface,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
                if (showEmailLine) ...[
                  const SizedBox(height: 1),
                  Text(
                    link.invitedEmail,
                    style: theme.textTheme.bodySmall?.copyWith(
                      fontSize: 12,
                      color: scheme.onSurfaceVariant,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
                const SizedBox(height: 6),
                Wrap(
                  spacing: 6,
                  runSpacing: 4,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    GroupedStatusPill(
                      label: link.isAccepted ? 'Active' : 'Invite sent',
                      tone: link.isAccepted
                          ? GroupedPillTone.teal
                          : GroupedPillTone.primary,
                    ),
                    Text(
                      link.shareMedicineNames
                          ? 'Medicine names included'
                          : 'Medicine names hidden',
                      style: theme.textTheme.bodySmall?.copyWith(
                        fontSize: 11.5,
                        color: scheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          IconButton(
            tooltip: link.isAccepted ? 'Remove caregiver' : 'Cancel invitation',
            visualDensity: VisualDensity.compact,
            constraints: const BoxConstraints(minWidth: 40, minHeight: 40),
            icon: Icon(
              PhosphorIconsRegular.x,
              size: 18,
              color: scheme.onSurfaceVariant,
            ),
            onPressed: onRemove,
          ),
        ],
      ),
    );
  }
}

class _PendingInvitationCard extends StatelessWidget {
  const _PendingInvitationCard({
    super.key,
    required this.link,
    required this.onAccept,
    required this.onDecline,
  });

  final CaregiverLink link;
  final VoidCallback onAccept;
  final VoidCallback onDecline;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final brand = AppBrandColors.of(context);
    final name = link.patientName ?? 'Someone';

    return MedicineCardShell(
      borderColor: brand.careTeal.withValues(alpha: 0.4),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              _InitialsAvatar(name: name, tone: GroupedIconTone.teal),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name,
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontSize: 15.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Wants you as their caregiver',
                      style: theme.textTheme.bodySmall?.copyWith(
                        fontSize: 12.5,
                        color: scheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              const GroupedStatusPill(
                label: 'New',
                tone: GroupedPillTone.teal,
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            "You'll receive a notification if they haven't marked a scheduled dose within an hour.",
            style: theme.textTheme.bodySmall?.copyWith(
              fontSize: 12.5,
              height: 1.45,
              color: scheme.onSurfaceVariant,
            ),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              FilledButton(
                style: FilledButton.styleFrom(
                  minimumSize: const Size(96, 40),
                  padding: const EdgeInsets.symmetric(horizontal: 18),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
                onPressed: onAccept,
                child: const Text('Accept'),
              ),
              const SizedBox(width: 8),
              OutlinedButton(
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size(88, 40),
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
                onPressed: onDecline,
                child: const Text('Decline'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _LookingAfterRow extends StatelessWidget {
  const _LookingAfterRow({
    super.key,
    required this.link,
    required this.onStop,
  });

  final CaregiverLink link;
  final VoidCallback onStop;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final name = link.patientName ?? 'Someone';

    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 12, 8, 12),
      child: Row(
        children: [
          _InitialsAvatar(name: name, tone: GroupedIconTone.teal),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontSize: 14.5,
                    fontWeight: FontWeight.w700,
                    color: scheme.onSurface,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  "You get alerts when they haven't marked a dose",
                  style: theme.textTheme.bodySmall?.copyWith(
                    fontSize: 12.5,
                    color: scheme.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            tooltip: 'Stop alerts',
            visualDensity: VisualDensity.compact,
            constraints: const BoxConstraints(minWidth: 40, minHeight: 40),
            icon: Icon(
              PhosphorIconsRegular.x,
              size: 18,
              color: scheme.onSurfaceVariant,
            ),
            onPressed: onStop,
          ),
        ],
      ),
    );
  }
}

class _SectionEmptyCard extends StatelessWidget {
  const _SectionEmptyCard({
    required this.icon,
    required this.tone,
    required this.title,
    required this.subtitle,
    required this.actionLabel,
    required this.actionIcon,
    required this.onTap,
  });

  final IconData icon;
  final GroupedIconTone tone;
  final String title;
  final String subtitle;
  final String actionLabel;
  final IconData actionIcon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return MedicineCardShell(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
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
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      style: theme.textTheme.bodySmall?.copyWith(
                        fontSize: 12.5,
                        height: 1.4,
                        color: scheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          SizedBox(
            height: 40,
            child: OutlinedButton.icon(
              style: OutlinedButton.styleFrom(
                minimumSize: const Size(0, 40),
                padding: const EdgeInsets.symmetric(horizontal: 14),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                ),
              ),
              onPressed: onTap,
              icon: Icon(actionIcon, size: 17),
              label: Text(actionLabel),
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionLoadingCard extends StatelessWidget {
  const _SectionLoadingCard();

  @override
  Widget build(BuildContext context) {
    return const MedicineCardShell(
      padding: EdgeInsets.all(16),
      child: Row(
        children: [
          SkeletonBox(width: 40, height: 40, radius: 12),
          SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SkeletonBox(width: 140, height: 14),
                SizedBox(height: 8),
                SkeletonBox(width: 200, height: 12),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ProfileFormSheet extends StatefulWidget {
  const _ProfileFormSheet({this.profile, this.reminderCount = 0});

  final CareProfile? profile;
  final int reminderCount;

  @override
  State<_ProfileFormSheet> createState() => _ProfileFormSheetState();
}

class _ProfileFormSheetState extends State<_ProfileFormSheet> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _relation;

  @override
  void initState() {
    super.initState();
    _name = TextEditingController(text: widget.profile?.name);
    _relation = TextEditingController(text: widget.profile?.relation);
  }

  @override
  void dispose() {
    _name.dispose();
    _relation.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final isEdit = widget.profile != null;
    final selectedRelation = _relation.text.trim().toLowerCase();

    return Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.viewInsetsOf(context).bottom,
      ),
      child: SafeArea(
        top: false,
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
          child: Form(
            key: _formKey,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    const GroupedIconBadge(
                      icon: PhosphorIconsRegular.user,
                      tone: GroupedIconTone.primary,
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            isEdit ? 'Edit family member' : 'Add family member',
                            style: theme.textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          Text(
                            'Manage their reminders right from this phone.',
                            style: theme.textTheme.bodySmall,
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 20),
                TextFormField(
                  controller: _name,
                  autofocus: !isEdit,
                  maxLength: 60,
                  textCapitalization: TextCapitalization.words,
                  decoration: const InputDecoration(
                    labelText: 'Name',
                    hintText: 'e.g. Lola Rosa',
                    counterText: '',
                  ),
                  validator: (value) => (value?.trim().isEmpty ?? true)
                      ? 'Enter a name'
                      : null,
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: _relation,
                  maxLength: 30,
                  textCapitalization: TextCapitalization.sentences,
                  onChanged: (_) => setState(() {}),
                  decoration: const InputDecoration(
                    labelText: 'Relationship (optional)',
                    hintText: 'Lola, son, mother',
                    counterText: '',
                  ),
                ),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  children: [
                    for (final preset in _relationPresets)
                      ChoiceChip(
                        label: Text(preset),
                        selected: selectedRelation == preset.toLowerCase(),
                        onSelected: (selected) {
                          setState(() {
                            _relation.text = selected ? preset : '';
                          });
                        },
                      ),
                  ],
                ),
                const SizedBox(height: 20),
                FilledButton(
                  onPressed: () {
                    if (!(_formKey.currentState?.validate() ?? false)) {
                      return;
                    }
                    final rel = _relation.text.trim();
                    Navigator.pop(context, (
                      action: 'save',
                      name: _name.text.trim(),
                      relation: rel.isEmpty ? null : rel,
                    ));
                  },
                  child: Text(isEdit ? 'Save changes' : 'Add family member'),
                ),
                if (isEdit) ...[
                  const SizedBox(height: 8),
                  TextButton.icon(
                    style: TextButton.styleFrom(
                      foregroundColor: scheme.error,
                      minimumSize: const Size.fromHeight(44),
                    ),
                    onPressed: () => Navigator.pop(context, (
                      action: 'delete',
                      name: widget.profile!.name,
                      relation: widget.profile!.relation,
                    )),
                    icon: const Icon(PhosphorIconsRegular.trash, size: 18),
                    label: const Text('Remove family member'),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _InviteCaregiverSheet extends StatefulWidget {
  const _InviteCaregiverSheet();

  @override
  State<_InviteCaregiverSheet> createState() => _InviteCaregiverSheetState();
}

class _InviteCaregiverSheetState extends State<_InviteCaregiverSheet> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();
  bool _shareNames = false;

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.viewInsetsOf(context).bottom,
      ),
      child: SafeArea(
        top: false,
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
          child: Form(
            key: _formKey,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    const GroupedIconBadge(
                      icon: PhosphorIconsRegular.shieldCheck,
                      tone: GroupedIconTone.teal,
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Invite a caregiver',
                            style: theme.textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          Text(
                            'They need an Everyday Pharmacy account to accept.',
                            style: theme.textTheme.bodySmall,
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 20),
                TextFormField(
                  controller: _email,
                  autofocus: true,
                  keyboardType: TextInputType.emailAddress,
                  autocorrect: false,
                  decoration: const InputDecoration(
                    labelText: 'Their Everyday Pharmacy email',
                    hintText: 'name@example.com',
                  ),
                  validator: (value) {
                    final text = value?.trim() ?? '';
                    if (text.isEmpty || !text.contains('@')) {
                      return 'Enter a valid email address';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: 16),
                GroupedCard(
                  children: [
                    GroupedSwitchTile(
                      icon: PhosphorIconsRegular.eyeSlash,
                      tone: GroupedIconTone.neutral,
                      boxedIcon: false,
                      title: 'Include medicine names in alerts',
                      subtitle: _shareNames
                          ? 'Alerts include the medicine name.'
                          : 'Alerts only say a dose has not been marked.',
                      value: _shareNames,
                      onChanged: (value) => setState(() => _shareNames = value),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: scheme.surfaceContainer.withValues(alpha: 0.7),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: scheme.outlineVariant),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Alert preview',
                        style: theme.textTheme.labelSmall?.copyWith(
                          color: scheme.onSurfaceVariant,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        _shareNames
                            ? '"You haven\'t marked your 8:00 AM Amlodipine as taken."'
                            : '"You haven\'t marked your 8:00 AM medicine as taken."',
                        style: theme.textTheme.bodySmall?.copyWith(
                          fontSize: 12.5,
                          color: scheme.onSurface,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 20),
                FilledButton(
                  onPressed: () {
                    if (!(_formKey.currentState?.validate() ?? false)) {
                      return;
                    }
                    Navigator.pop(context, (
                      email: _email.text.trim(),
                      shareNames: _shareNames,
                    ));
                  },
                  child: const Text('Send invitation'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
