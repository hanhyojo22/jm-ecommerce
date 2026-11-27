import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_exception.dart';
import '../models/medication.dart';
import '../providers/auth_providers.dart';
import '../providers/medication_providers.dart';
import '../widgets/common.dart';
import '../widgets/status_notice.dart';

/// Family members whose medicine the buyer manages, caregivers who get an
/// alert when a dose isn't marked, and invitations to be someone's caregiver.
class MedicinePeopleScreen extends ConsumerWidget {
  const MedicinePeopleScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final consented = ref.watch(meProvider).value?.medicationReminders ?? false;
    final profiles = consented
        ? ref.watch(careProfilesProvider)
        : const AsyncValue<List<CareProfile>>.data([]);
    final caregivers = ref.watch(caregiversProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Family and caregivers')),
      body: ListView(
        padding: const EdgeInsets.only(bottom: 32),
        children: [
          if (consented) ...[
            SectionTitle(
              title: 'Family',
              trailing: TextButton.icon(
                onPressed: () => _editProfile(context, ref, null),
                icon: const Icon(Icons.add),
                label: const Text('Add'),
              ),
            ),
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 16),
              child: Text(
                "Set reminders for a child or parent on this phone. Deleting someone deletes their reminders.",
              ),
            ),
            ...profiles.when(
              data: (profiles) => [
                for (final profile in profiles)
                  ListTile(
                    leading: const Icon(Icons.person_outline_rounded),
                    title: Text(profile.name),
                    subtitle: profile.relation == null
                        ? null
                        : Text(profile.relation!),
                    onTap: () => _editProfile(context, ref, profile),
                  ),
              ],
              loading: () => const [LinearProgressIndicator()],
              error: (error, _) => [ErrorView(error: error)],
            ),
          ],
          if (consented)
            SectionTitle(
              title: 'Caregivers',
              trailing: TextButton.icon(
                onPressed: () => _invite(context, ref),
                icon: const Icon(Icons.person_add_alt_rounded),
                label: const Text('Invite'),
              ),
            ),
          if (consented)
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 16),
              child: Text(
                "A caregiver gets an alert on their Everyday Pharmacy app when you haven't marked a dose within an hour. They can't see your medicines.",
              ),
            ),
          ...caregivers.when(
            data: (data) => [
              for (final link in data.caregivers)
                ListTile(
                  leading: const Icon(Icons.shield_outlined),
                  title: Text(link.caregiverName ?? link.invitedEmail),
                  subtitle: Text(
                    link.isAccepted ? 'Gets alerts' : 'Invitation sent',
                  ),
                  trailing: IconButton(
                    tooltip: 'Remove',
                    icon: const Icon(Icons.close_rounded),
                    onPressed: () => _remove(context, ref, link),
                  ),
                ),
              if (data.invitations.isNotEmpty) ...[
                const SectionTitle(title: 'You look after'),
                for (final link in data.invitations)
                  ListTile(
                    leading: const Icon(Icons.favorite_outline_rounded),
                    title: Text(link.patientName ?? 'Someone'),
                    subtitle: Text(
                      link.isAccepted
                          ? "You get alerts when they haven't marked a dose"
                          : 'Wants you as their caregiver',
                    ),
                    trailing: link.isAccepted
                        ? IconButton(
                            tooltip: 'Stop alerts',
                            icon: const Icon(Icons.close_rounded),
                            onPressed: () => _remove(context, ref, link),
                          )
                        : FilledButton(
                            onPressed: () => _accept(context, ref, link),
                            child: const Text('Accept'),
                          ),
                  ),
              ],
            ],
            loading: () => const [LinearProgressIndicator()],
            error: (error, _) => [ErrorView(error: error)],
          ),
        ],
      ),
    );
  }

  Future<void> _editProfile(
    BuildContext context,
    WidgetRef ref,
    CareProfile? profile,
  ) async {
    final name = TextEditingController(text: profile?.name);
    final relation = TextEditingController(text: profile?.relation);
    final result = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(profile == null ? 'Add family member' : 'Edit'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: name,
              autofocus: true,
              maxLength: 60,
              decoration: const InputDecoration(labelText: 'Name'),
            ),
            TextField(
              controller: relation,
              maxLength: 30,
              decoration: const InputDecoration(
                labelText: 'Relationship (optional)',
                hintText: 'Lola, son, mother',
              ),
            ),
          ],
        ),
        actions: [
          if (profile != null)
            TextButton(
              onPressed: () => Navigator.pop(context, 'delete'),
              child: const Text('Delete'),
            ),
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, 'save'),
            child: const Text('Save'),
          ),
        ],
      ),
    );
    if (result == null || !context.mounted) {
      return;
    }
    final notice = AppNotice.of(context);
    final api = ref.read(apiClientProvider);
    try {
      if (result == 'delete') {
        await api.deleteCareProfile(profile!.id);
        ref.invalidate(medicationPlansProvider);
      } else if (name.text.trim().isNotEmpty) {
        await api.saveCareProfile(
          id: profile?.id,
          name: name.text.trim(),
          relation: relation.text.trim().isEmpty ? null : relation.text.trim(),
        );
      }
      ref.invalidate(careProfilesProvider);
    } on ApiException catch (error) {
      notice.show(error.friendly);
    }
  }

  Future<void> _invite(BuildContext context, WidgetRef ref) async {
    final email = TextEditingController();
    var shareNames = false;
    final send = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setState) => AlertDialog(
          title: const Text('Invite a caregiver'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: email,
                autofocus: true,
                keyboardType: TextInputType.emailAddress,
                decoration: const InputDecoration(
                  labelText: 'Their Everyday Pharmacy email',
                ),
              ),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('Include medicine names in alerts'),
                value: shareNames,
                onChanged: (value) => setState(() => shareNames = value),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Invite'),
            ),
          ],
        ),
      ),
    );
    if (send != true || email.text.trim().isEmpty || !context.mounted) {
      return;
    }
    final notice = AppNotice.of(context);
    try {
      await ref
          .read(apiClientProvider)
          .inviteCaregiver(
            email: email.text.trim(),
            shareMedicineNames: shareNames,
          );
      ref.invalidate(caregiversProvider);
      notice.show(
        'Invitation saved. They accept it in their app under Medicine reminders.',
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
    } on ApiException catch (error) {
      notice.show(error.friendly);
    }
  }

  Future<void> _remove(
    BuildContext context,
    WidgetRef ref,
    CaregiverLink link,
  ) async {
    final notice = AppNotice.of(context);
    try {
      await ref.read(apiClientProvider).removeCaregiver(link.id);
      ref.invalidate(caregiversProvider);
    } on ApiException catch (error) {
      notice.show(error.friendly);
    }
  }
}
