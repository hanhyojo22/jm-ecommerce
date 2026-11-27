import 'package:clerk_flutter/clerk_flutter.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:phosphor_icons/phosphor_icons.dart';

import '../core/api_exception.dart';
import '../core/formatters.dart';
import '../core/push_notifications.dart';
import '../models/models.dart';
import '../providers/auth_providers.dart';
import '../providers/catalog_providers.dart';
import '../providers/chat_providers.dart';
import '../providers/discount_card_providers.dart';
import '../providers/order_providers.dart';
import '../theme.dart';
import '../widgets/common.dart';
import '../widgets/grouped_list_card.dart';
import '../widgets/shell.dart';
import '../widgets/status_notice.dart';
import 'settings_screen.dart';

/// Signs out and leaves the app running as a guest on Shop. Local state is
/// cleared even if a step fails, such as Clerk's sign-out after the account
/// was deleted and its session is already gone.
Future<void> signOutOfApp(ClerkAuthState auth, WidgetRef ref) async {
  try {
    await PushNotifications.unregister(ref.read(apiClientProvider));
  } catch (_) {
    // The server forgets a deleted account's devices anyway.
  }
  // Medicine searches are personal: the next person on this phone starts clean.
  ref.read(searchHistoryProvider.notifier).clear();
  ref.read(shellTabProvider.notifier).select(0);
  try {
    await auth.signOut();
  } catch (_) {
    // Nothing to sign out of when the account was just deleted.
  }
}

class AccountScreen extends ConsumerWidget {
  const AccountScreen({super.key});

  void _openOrdersTab(WidgetRef ref, String tab) {
    ref.read(ordersTabProvider.notifier).select(tab);
    ref.read(shellTabProvider.notifier).select(1);
  }

  Future<void> _confirmSignOut(BuildContext context, WidgetRef ref) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Sign out of Everyday Pharmacy?'),
        content: const Text(
          'You can still browse medicines as a guest. Your cart, orders, and reminders stay safely linked to your account.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: Theme.of(dialogContext).colorScheme.error,
              foregroundColor: Theme.of(dialogContext).colorScheme.onError,
            ),
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Sign out'),
          ),
        ],
      ),
    );
    if (confirmed != true || !context.mounted) {
      return;
    }
    await signOutOfApp(ClerkAuth.of(context), ref);
  }

  Future<void> _deleteDiscountCard(
    BuildContext context,
    WidgetRef ref,
    DiscountCard card,
  ) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Remove saved ID?'),
        content: const Text(
          'You will need to enter this ID and upload its photo again to use it on a future order.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Keep ID'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: Theme.of(dialogContext).colorScheme.error,
              foregroundColor: Theme.of(dialogContext).colorScheme.onError,
            ),
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Remove ID'),
          ),
        ],
      ),
    );
    if (confirmed != true || !context.mounted) {
      return;
    }
    try {
      await ref.read(apiClientProvider).deleteDiscountCard(card.id);
      ref.invalidate(discountCardsProvider);

      if (context.mounted) {
        AppNotice.of(context)
            .show('Saved ID removed.', kind: NoticeKind.success);
      }
    } on ApiException catch (error) {
      if (context.mounted) {
        AppNotice.of(context).show(error.friendly);
      }
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final me = ref.watch(meProvider);
    final cards =
        ref.watch(discountCardsProvider).value ?? const <DiscountCard>[];
    final unreadMessages =
        ref
            .watch(conversationsProvider)
            .value
            ?.fold<int>(0, (sum, item) => sum + item.unreadCount) ??
        0;

    return AsyncView(
      value: me,
      onRetry: () => ref.invalidate(meProvider),
      builder: (account) {
        final theme = Theme.of(context);
        final scheme = theme.colorScheme;

        return RefreshIndicator(
          onRefresh: () async {
            ref.invalidate(meProvider);
            ref.invalidate(discountCardsProvider);
            await ref.read(meProvider.future);
          },
          child: CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              SliverToBoxAdapter(
                child: SafeArea(
                  bottom: false,
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(20, 10, 8, 8),
                    child: Row(
                      children: [
                        Expanded(
                          child: Text(
                            'Account',
                            style: theme.textTheme.titleLarge?.copyWith(
                              fontSize: 22,
                              fontWeight: FontWeight.w800,
                              letterSpacing: -0.5,
                            ),
                          ),
                        ),
                        IconButton(
                          tooltip: 'Settings',
                          constraints: const BoxConstraints(
                            minWidth: 48,
                            minHeight: 48,
                          ),
                          onPressed: () => context.push('/settings'),
                          icon: const Icon(
                            PhosphorIconsRegular.gearSix,
                            size: 22,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 4, 16, 28),
                sliver: SliverList.list(
                  children: [
                    _ProfileCard(account: account),
                    const SizedBox(height: 16),
                    _AccountOrdersCard(
                      onOpenTab: (tab) => _openOrdersTab(ref, tab),
                    ),
                    const SizedBox(height: 18),
                    GroupedSection(
                      label: 'Pharmacy & care',
                      children: [
                        GroupedRowTile(
                          icon: PhosphorIconsRegular.pill,
                          tone: GroupedIconTone.teal,
                          title: 'Medicine reminders',
                          trailing: account.medicationReminders
                              ? const GroupedStatusPill(label: 'On')
                              : null,
                          onTap: () => context.push('/medicines'),
                        ),
                        for (final card in cards)
                          _SavedDiscountCardRow(
                            card: card,
                            onDelete: () =>
                                _deleteDiscountCard(context, ref, card),
                          ),
                        GroupedRowTile(
                          icon: PhosphorIconsRegular.storefront,
                          tone: GroupedIconTone.neutral,
                          title: 'Branch locator',
                          onTap: () => context.push('/branches'),
                        ),
                        GroupedRowTile(
                          icon: PhosphorIconsRegular.chatCircleDots,
                          tone: GroupedIconTone.neutral,
                          title: 'Pharmacist messages',
                          trailing: unreadMessages > 0
                              ? GroupedStatusPill(
                                  label: '$unreadMessages',
                                  tone: GroupedPillTone.unread,
                                )
                              : null,
                          onTap: () =>
                              ref.read(shellTabProvider.notifier).select(2),
                        ),
                      ],
                    ),
                    const SizedBox(height: 18),
                    GroupedSection(
                      label: 'Shopping',
                      children: [
                        GroupedRowTile(
                          icon: PhosphorIconsRegular.mapPin,
                          tone: GroupedIconTone.neutral,
                          title: 'Delivery addresses',
                          onTap: () => context.push('/addresses'),
                        ),
                        GroupedRowTile(
                          icon: PhosphorIconsRegular.heart,
                          tone: GroupedIconTone.neutral,
                          title: 'Saved items',
                          onTap: () => context.push('/saved'),
                        ),
                        GroupedRowTile(
                          icon: PhosphorIconsRegular.star,
                          tone: GroupedIconTone.neutral,
                          title: 'My reviews',
                          onTap: () => _openOrdersTab(ref, 'to_review'),
                        ),
                      ],
                    ),
                    const SizedBox(height: 18),
                    GroupedSection(
                      label: 'App',
                      children: [
                        GroupedRowTile(
                          icon: PhosphorIconsRegular.gearSix,
                          tone: GroupedIconTone.primary,
                          title: 'Settings',
                          onTap: () => context.push('/settings'),
                        ),
                      ],
                    ),
                    const SizedBox(height: 18),
                    OutlinedButton.icon(
                      style: OutlinedButton.styleFrom(
                        minimumSize: const Size.fromHeight(50),
                        backgroundColor: scheme.surfaceContainerLowest,
                        foregroundColor: scheme.error,
                        side: BorderSide(color: scheme.outlineVariant),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(appSurfaceRadius),
                        ),
                      ),
                      onPressed: () => _confirmSignOut(context, ref),
                      icon: const Icon(PhosphorIconsRegular.signOut, size: 19),
                      label: const Text(
                        'Sign out',
                        style: TextStyle(
                          fontSize: 14.5,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    const SizedBox(height: 14),
                    Text(
                      'Everyday Pharmacy · v$appVersionLabel',
                      textAlign: TextAlign.center,
                      style: theme.textTheme.bodySmall?.copyWith(
                        fontSize: 11.5,
                        fontWeight: FontWeight.w600,
                        color: scheme.onSurfaceVariant,
                        fontFeatures: const [FontFeature.tabularFigures()],
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

/// Elevated identity card with verified status, edit profile shortcut, and
/// incomplete-profile prompt banner when required details are missing.
class _ProfileCard extends StatelessWidget {
  const _ProfileCard({required this.account});

  final Account account;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final brand = AppBrandColors.of(context);
    final dark = theme.brightness == Brightness.dark;

    return GroupedCard(
      showDividers: false,
      children: [
        InkWell(
          onTap: () => context.push('/profile'),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 14, 14),
            child: Row(
              children: [
                Stack(
                  clipBehavior: Clip.none,
                  children: [
                    CircleAvatar(
                      radius: 26,
                      backgroundColor: scheme.primary,
                      foregroundColor: scheme.onPrimary,
                      child: Text(
                        initials(account.name),
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                    if (account.emailVerified)
                      Positioned(
                        right: -1,
                        bottom: -1,
                        child: Container(
                          width: 18,
                          height: 18,
                          decoration: BoxDecoration(
                            color: brand.careTeal,
                            shape: BoxShape.circle,
                            border: Border.all(
                              color: scheme.surfaceContainerLowest,
                              width: 2,
                            ),
                          ),
                          alignment: Alignment.center,
                          child: Icon(
                            PhosphorIconsRegular.check,
                            size: 10,
                            color: dark
                                ? const Color(0xFF0B1020)
                                : Colors.white,
                          ),
                        ),
                      ),
                  ],
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Flexible(
                            child: Text(
                              account.name,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: theme.textTheme.titleMedium?.copyWith(
                                fontSize: 16.5,
                                fontWeight: FontWeight.w800,
                                letterSpacing: -0.2,
                              ),
                            ),
                          ),
                          if (account.emailVerified) ...[
                            const SizedBox(width: 6),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 7,
                                vertical: 2,
                              ),
                              decoration: BoxDecoration(
                                color: brand.careTealSoft,
                                borderRadius: BorderRadius.circular(999),
                              ),
                              child: Text(
                                'Verified',
                                style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w700,
                                  color: brand.careTeal,
                                ),
                              ),
                            ),
                          ],
                        ],
                      ),
                      const SizedBox(height: 2),
                      Text(
                        account.email,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodySmall?.copyWith(
                          fontSize: 13,
                          color: scheme.onSurfaceVariant,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Icon(
                  PhosphorIconsRegular.pencilSimple,
                  size: 18,
                  color: scheme.onSurfaceVariant,
                ),
              ],
            ),
          ),
        ),
        if (!account.profileComplete) ...[
          Divider(height: 1, thickness: 1, color: scheme.outlineVariant),
          InkWell(
            onTap: () => context.push('/profile?complete=1'),
            child: Ink(
              color: dark ? const Color(0xFF2E2412) : const Color(0xFFFFF4D1),
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 10, 12, 10),
                child: Row(
                  children: [
                    Icon(
                      PhosphorIconsRegular.warningCircle,
                      size: 17,
                      color: dark
                          ? const Color(0xFFFCD34D)
                          : const Color(0xFF7A4F00),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        'Complete your profile for Rx & discounts',
                        style: TextStyle(
                          fontSize: 12.5,
                          fontWeight: FontWeight.w700,
                          color: dark
                              ? const Color(0xFFFCD34D)
                              : const Color(0xFF7A4F00),
                        ),
                      ),
                    ),
                    Icon(
                      PhosphorIconsRegular.caretRight,
                      size: 16,
                      color: dark
                          ? const Color(0xFFFCD34D)
                          : const Color(0xFF7A4F00),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ],
    );
  }
}

/// Elevated "My Orders" card with "View all" link and the 5 status shortcuts.
class _AccountOrdersCard extends StatelessWidget {
  const _AccountOrdersCard({required this.onOpenTab});

  final ValueChanged<String> onOpenTab;

  static const _shortcuts = [
    (key: 'to_pay', label: 'To pay', icon: PhosphorIconsRegular.wallet),
    (
      key: 'to_dispatch',
      label: 'Preparing',
      icon: PhosphorIconsRegular.package,
    ),
    (key: 'to_receive', label: 'On the way', icon: PhosphorIconsRegular.truck),
    (key: 'to_review', label: 'To review', icon: PhosphorIconsRegular.star),
    (
      key: 'returns',
      label: 'Returns',
      icon: PhosphorIconsRegular.arrowCounterClockwise,
    ),
  ];

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return GroupedCard(
      showDividers: false,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 10, 8, 4),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  'My orders',
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontSize: 15.5,
                    fontWeight: FontWeight.w800,
                    letterSpacing: -0.2,
                  ),
                ),
              ),
              TextButton(
                style: TextButton.styleFrom(
                  minimumSize: const Size(48, 40),
                  padding: const EdgeInsets.symmetric(horizontal: 10),
                  foregroundColor: scheme.primary,
                ),
                onPressed: () => onOpenTab('all'),
                child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      'View all',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    SizedBox(width: 2),
                    Icon(PhosphorIconsRegular.caretRight, size: 16),
                  ],
                ),
              ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(6, 2, 6, 12),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              for (final item in _shortcuts)
                Expanded(
                  child: InkWell(
                    borderRadius: BorderRadius.circular(10),
                    onTap: () => onOpenTab(item.key),
                    child: ConstrainedBox(
                      constraints: const BoxConstraints(minHeight: 68),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 2,
                          vertical: 6,
                        ),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Container(
                              width: 36,
                              height: 36,
                              decoration: BoxDecoration(
                                color: scheme.surface,
                                borderRadius: BorderRadius.circular(10),
                              ),
                              alignment: Alignment.center,
                              child: Icon(
                                item.icon,
                                size: 20,
                                color: scheme.onSurface,
                              ),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              item.label,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              textAlign: TextAlign.center,
                              style: theme.textTheme.labelSmall?.copyWith(
                                fontSize: 11,
                                height: 1.2,
                                fontWeight: FontWeight.w600,
                                color: scheme.onSurfaceVariant,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ],
    );
  }
}

/// Saved Senior Citizen / PWD discount ID row inside Pharmacy & care.
class _SavedDiscountCardRow extends StatelessWidget {
  const _SavedDiscountCardRow({required this.card, required this.onDelete});

  final DiscountCard card;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return ConstrainedBox(
      constraints: const BoxConstraints(minHeight: 58),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 9, 8, 9),
        child: Row(
          children: [
            const GroupedIconBadge(
              icon: PhosphorIconsRegular.identificationCard,
              tone: GroupedIconTone.primary,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${card.typeLabel} · ${card.idNumberMasked}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontSize: 14.5,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    card.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodySmall?.copyWith(
                      fontSize: 12.5,
                      color: scheme.onSurfaceVariant,
                    ),
                  ),
                ],
              ),
            ),
            IconButton(
              tooltip: 'Delete',
              constraints: const BoxConstraints(minWidth: 44, minHeight: 44),
              onPressed: onDelete,
              icon: Icon(
                PhosphorIconsRegular.trash,
                size: 19,
                color: scheme.onSurfaceVariant,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
