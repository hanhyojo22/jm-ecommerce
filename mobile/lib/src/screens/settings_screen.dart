import 'package:clerk_flutter/clerk_flutter.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:go_router/go_router.dart';
import 'package:phosphor_icons/phosphor_icons.dart';

import '../core/api_exception.dart';
import '../providers/auth_providers.dart';
import '../providers/theme_providers.dart';
import '../theme.dart';
import '../widgets/grouped_list_card.dart';
import '../widgets/shell.dart';
import '../widgets/status_notice.dart';
import 'account_screen.dart';

/// App version shown in Settings and Account footers (matches pubspec.yaml).
const appVersionLabel = '1.0.24';
const appBuildLabel = '26';

class SettingsScreen extends ConsumerStatefulWidget {
  const SettingsScreen({super.key});

  @override
  ConsumerState<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends ConsumerState<SettingsScreen> {
  bool _savingCartReminders = false;
  bool _deletingAccount = false;

  Future<void> _toggleCartReminders(bool enabled) async {
    if (_savingCartReminders) {
      return;
    }
    final feedback = AppNotice.of(context);
    setState(() => _savingCartReminders = true);
    try {
      await ref.read(apiClientProvider).updateCartReminders(enabled);
      ref.invalidate(meProvider);
    } catch (_) {
      feedback.show('Could not save. Try again.');
    } finally {
      if (mounted) {
        setState(() => _savingCartReminders = false);
      }
    }
  }

  Future<void> _openSystemNotificationSettings() async {
    final feedback = AppNotice.of(context);
    try {
      final opened = await Geolocator.openAppSettings();
      if (!opened && mounted) {
        feedback.show(
          'Open your phone Settings to manage Everyday Pharmacy notifications.',
        );
      }
    } on MissingPluginException {
      feedback.show(
        'Open your phone Settings to manage Everyday Pharmacy notifications.',
      );
    } on PlatformException {
      feedback.show(
        'Open your phone Settings to manage Everyday Pharmacy notifications.',
      );
    }
  }

  void _openPharmacistChat() {
    ref.read(shellTabProvider.notifier).select(2);
    if (Navigator.of(context).canPop()) {
      Navigator.of(context).popUntil((route) => route.isFirst);
    }
  }

  Future<void> _showHelpCenterSheet() async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      useSafeArea: true,
      backgroundColor: Theme.of(context).colorScheme.surfaceContainerLowest,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(
          top: Radius.circular(appModalRadius),
        ),
      ),
      builder: (sheetContext) => _HelpCenterSheet(
        onMessagePharmacist: () {
          Navigator.of(sheetContext).pop();
          if (mounted) {
            _openPharmacistChat();
          }
        },
      ),
    );
  }

  Future<void> _deleteAccount() async {
    if (_deletingAccount) {
      return;
    }
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Delete your account?'),
        content: const Text(
          'Your profile, saved addresses, saved discount IDs and cart are deleted, and you are signed out.\n\n'
          'Past orders, prescriptions and receipts are kept as the pharmacy\'s records, and your reviews stay under "Deleted customer".\n\n'
          'This cannot be undone.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Keep account'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: Theme.of(dialogContext).colorScheme.error,
              foregroundColor: Theme.of(dialogContext).colorScheme.onError,
            ),
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Delete account'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) {
      return;
    }

    final feedback = AppNotice.of(context);
    setState(() => _deletingAccount = true);
    try {
      await ref.read(apiClientProvider).deleteAccount();
    } on ApiException catch (error) {
      feedback.show(error.friendly);
      if (mounted) {
        setState(() => _deletingAccount = false);
      }
      return;
    }
    if (!mounted) {
      return;
    }
    final navigator = Navigator.of(context);
    await signOutOfApp(ClerkAuth.of(context), ref);
    navigator.popUntil((route) => route.isFirst);
    feedback.show('Your account was deleted.', kind: NoticeKind.success);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final signedIn = ref.watch(isSignedInProvider);
    final account = signedIn ? ref.watch(meProvider).value : null;
    final themeMode = ref.watch(themeModeProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Settings')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
        children: [
          GroupedSection(
            label: 'Appearance',
            children: [
              _AppearanceSelector(
                mode: themeMode,
                onSelected: (mode) =>
                    ref.read(themeModeProvider.notifier).setMode(mode),
              ),
            ],
          ),
          const SizedBox(height: 20),
          GroupedSection(
            label: 'Notifications',
            dividerIndent: 54,
            children: [
              if (signedIn)
                GroupedSwitchTile(
                  icon: PhosphorIconsRegular.bell,
                  tone: GroupedIconTone.neutral,
                  boxedIcon: false,
                  title: 'Cart reminders',
                  value: account?.cartReminders ?? true,
                  onChanged: account == null || _savingCartReminders
                      ? null
                      : _toggleCartReminders,
                ),
              GroupedRowTile(
                icon: PhosphorIconsRegular.slidersHorizontal,
                tone: GroupedIconTone.neutral,
                boxedIcon: false,
                title: 'Push notifications',
                onTap: _openSystemNotificationSettings,
              ),
              if (signedIn)
                GroupedRowTile(
                  icon: PhosphorIconsRegular.alarm,
                  tone: GroupedIconTone.neutral,
                  boxedIcon: false,
                  title: 'Medicine reminders',
                  trailing: account == null
                      ? null
                      : GroupedStatusPill(
                          label: account.medicationReminders ? 'On' : 'Off',
                          tone: account.medicationReminders
                              ? GroupedPillTone.teal
                              : GroupedPillTone.primary,
                        ),
                  onTap: () => context.push('/medicines'),
                ),
            ],
          ),
          const SizedBox(height: 20),
          GroupedSection(
            label: 'Help',
            dividerIndent: 54,
            children: [
              GroupedRowTile(
                icon: PhosphorIconsRegular.question,
                tone: GroupedIconTone.neutral,
                boxedIcon: false,
                title: 'Help center',
                onTap: _showHelpCenterSheet,
              ),
              GroupedRowTile(
                icon: PhosphorIconsRegular.chatCircleDots,
                tone: GroupedIconTone.neutral,
                boxedIcon: false,
                title: 'Message a pharmacist',
                onTap: _openPharmacistChat,
              ),
            ],
          ),
          if (signedIn) ...[
            const SizedBox(height: 20),
            GroupedSection(
              label: 'Account',
              dividerIndent: 54,
              children: [
                GroupedRowTile(
                  icon: PhosphorIconsRegular.user,
                  tone: GroupedIconTone.neutral,
                  boxedIcon: false,
                  title: 'Personal information',
                  onTap: () => context.push('/profile'),
                ),
                GroupedRowTile(
                  icon: PhosphorIconsRegular.trash,
                  tone: GroupedIconTone.danger,
                  boxedIcon: false,
                  title: _deletingAccount
                      ? 'Deleting account…'
                      : 'Delete account',
                  dangerTitle: true,
                  onTap: _deletingAccount ? null : _deleteAccount,
                ),
              ],
            ),
          ],
          const SizedBox(height: 24),
          Text(
            'Everyday Pharmacy · v$appVersionLabel',
            textAlign: TextAlign.center,
            style: theme.textTheme.bodySmall?.copyWith(
              fontSize: 12,
              fontWeight: FontWeight.w500,
              color: scheme.onSurfaceVariant,
              fontFeatures: const [FontFeature.tabularFigures()],
            ),
          ),
        ],
      ),
    );
  }
}

class _AppearanceSelector extends StatelessWidget {
  const _AppearanceSelector({required this.mode, required this.onSelected});

  final ThemeMode mode;
  final ValueChanged<ThemeMode> onSelected;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    const options = [
      (
        mode: ThemeMode.system,
        label: 'System',
        icon: PhosphorIconsRegular.deviceMobile,
      ),
      (mode: ThemeMode.light, label: 'Light', icon: PhosphorIconsRegular.sun),
      (mode: ThemeMode.dark, label: 'Dark', icon: PhosphorIconsRegular.moon),
    ];

    return Padding(
      padding: const EdgeInsets.all(6),
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: scheme.surface,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Padding(
          padding: const EdgeInsets.all(3),
          child: Row(
            children: [
              for (var i = 0; i < options.length; i++) ...[
                if (i > 0) const SizedBox(width: 4),
                Expanded(
                  child: _ThemeOptionButton(
                    label: options[i].label,
                    icon: options[i].icon,
                    selected: mode == options[i].mode,
                    onTap: () => onSelected(options[i].mode),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _ThemeOptionButton extends StatelessWidget {
  const _ThemeOptionButton({
    required this.label,
    required this.icon,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final dark = theme.brightness == Brightness.dark;

    return Semantics(
      button: true,
      selected: selected,
      child: Material(
        color: selected ? scheme.surfaceContainerLowest : Colors.transparent,
        borderRadius: BorderRadius.circular(8),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(8),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 150),
            constraints: const BoxConstraints(minHeight: 44),
            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 10),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(8),
              border: selected
                  ? Border.all(color: scheme.outlineVariant)
                  : null,
              boxShadow: selected && !dark
                  ? const [
                      BoxShadow(
                        color: Color(0x140F1D40),
                        blurRadius: 3,
                        offset: Offset(0, 1),
                      ),
                    ]
                  : null,
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(
                  icon,
                  size: 16,
                  color: selected ? scheme.primary : scheme.onSurfaceVariant,
                ),
                const SizedBox(width: 6),
                Flexible(
                  child: Text(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: selected
                          ? scheme.primary
                          : scheme.onSurfaceVariant,
                    ),
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

class _HelpCenterSheet extends StatelessWidget {
  const _HelpCenterSheet({required this.onMessagePharmacist});

  final VoidCallback onMessagePharmacist;

  static const _faqs = [
    (
      question: 'Uploading a prescription',
      answer: 'Upload a clear photo of your doctor\'s prescription at checkout. A licensed branch pharmacist reviews it before preparing your order.',
    ),
    (
      question: 'Senior Citizen & PWD discounts',
      answer: 'Attach your valid ID during checkout to save it for future orders. You can remove saved IDs anytime under Account.',
    ),
    (
      question: 'Returns & branch pickup',
      answer: 'Open an order\'s details to check pickup readiness or request a return for eligible sealed items.',
    ),
  ];

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Help center',
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              TextButton(
                onPressed: () => Navigator.of(context).pop(),
                child: const Text('Done'),
              ),
            ],
          ),
          const SizedBox(height: 8),
          GroupedCard(
            showDividers: false,
            children: [
              for (var i = 0; i < _faqs.length; i++) ...[
                if (i > 0)
                  Divider(
                    height: 1,
                    thickness: 1,
                    color: scheme.outlineVariant,
                  ),
                Theme(
                  data: theme.copyWith(dividerColor: Colors.transparent),
                  child: ExpansionTile(
                    initiallyExpanded: i == 0,
                    tilePadding: const EdgeInsets.symmetric(horizontal: 14),
                    childrenPadding: const EdgeInsets.fromLTRB(14, 0, 14, 14),
                    title: Text(
                      _faqs[i].question,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        fontSize: 13.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    children: [
                      Align(
                        alignment: Alignment.centerLeft,
                        child: Text(
                          _faqs[i].answer,
                          style: theme.textTheme.bodySmall?.copyWith(
                            fontSize: 12.5,
                            height: 1.5,
                            color: scheme.onSurfaceVariant,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ],
          ),
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: onMessagePharmacist,
            icon: const Icon(PhosphorIconsRegular.chatCircleDots, size: 19),
            label: const Text('Message a pharmacist'),
          ),
        ],
      ),
    );
  }
}
