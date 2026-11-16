import 'package:clerk_flutter/clerk_flutter.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/formatters.dart';
import '../core/push_notifications.dart';
import '../models/models.dart';
import '../providers/auth_providers.dart';
import '../providers/order_providers.dart';
import '../theme.dart';
import '../widgets/common.dart';
import '../widgets/shell.dart';
import '../widgets/store_locator.dart';

class AccountScreen extends ConsumerWidget {
  const AccountScreen({super.key});

  void _openOrdersTab(WidgetRef ref, String tab) {
    ref.read(ordersTabProvider.notifier).select(tab);
    ref.read(shellTabProvider.notifier).select(1);
  }

  Future<void> _showHelpSheet(BuildContext context) async {
    await showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      useSafeArea: true,
      builder: (context) {
        final theme = Theme.of(context);
        return Padding(
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Help',
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 10),
              Text(
                'Questions about an order or a medicine? Message your branch from the order details or the Messages tab.',
                style: theme.textTheme.bodyMedium,
              ),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: () => Navigator.of(context).pop(),
                child: const Text('Got it'),
              ),
            ],
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final me = ref.watch(meProvider);

    return AsyncView(
      value: me,
      onRetry: () => ref.invalidate(meProvider),
      builder: (account) {
        final theme = Theme.of(context);
        final scheme = theme.colorScheme;

        return RefreshIndicator(
          onRefresh: () async {
            ref.invalidate(meProvider);
            await ref.read(meProvider.future);
          },
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.only(bottom: 28),
            children: [
              _LazadaAccountHeader(account: account),
              const SizedBox(height: 8),
              _AccountOrdersSection(
                onOpenTab: (tab) => _openOrdersTab(ref, tab),
              ),
              const SizedBox(height: 8),
              Material(
                color: scheme.surfaceContainerLowest,
                child: SwitchListTile(
                  secondary: Icon(
                    Icons.notifications_active_outlined,
                    color: scheme.primary,
                  ),
                  title: const Text(
                    'Cart reminders',
                    style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
                  ),
                  subtitle: const Text(
                    'Remind me when items sit in my cart for a day',
                    style: TextStyle(fontSize: 12),
                  ),
                  value: account.cartReminders,
                  onChanged: (enabled) async {
                    final messenger = ScaffoldMessenger.of(context);
                    try {
                      await ref
                          .read(apiClientProvider)
                          .updateCartReminders(enabled);
                      ref.invalidate(meProvider);
                    } catch (_) {
                      messenger.showSnackBar(
                        const SnackBar(
                          content: Text('Could not save. Try again.'),
                        ),
                      );
                    }
                  },
                ),
              ),
              const SizedBox(height: 8),
              _LazadaToolsGridSection(
                onAddressesTap: () => context.push('/addresses'),
                onReviewsTap: () => _openOrdersTab(ref, 'to_review'),
                onMessagesTap: () =>
                    ref.read(shellTabProvider.notifier).select(2),
                onBranchesTap: () => showBranchSelectorSheet(context),
                onHelpTap: () => _showHelpSheet(context),
              ),
              const SizedBox(height: 16),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: OutlinedButton.icon(
                  onPressed: () async {
                    final auth = ClerkAuth.of(context);
                    await PushNotifications.unregister(
                      ref.read(apiClientProvider),
                    );
                    await auth.signOut();
                  },
                  icon: const Icon(Icons.logout_rounded),
                  label: const Text('Sign out'),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

/// Profile banner: avatar, name, email and whether the email is verified.
class _LazadaAccountHeader extends StatelessWidget {
  const _LazadaAccountHeader({required this.account});

  final Account account;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [
            scheme.primary.withValues(alpha: 0.14),
            scheme.surfaceContainerLowest,
          ],
        ),
      ),
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 20, 12, 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.center,
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
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          account.name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w800,
                            fontSize: 18,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          account.email,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: scheme.onSurfaceVariant,
                          ),
                        ),
                        const SizedBox(height: 5),
                        Wrap(
                          spacing: 6,
                          runSpacing: 4,
                          crossAxisAlignment: WrapCrossAlignment.center,
                          children: [
                            if (account.emailVerified)
                              Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(
                                    Icons.verified_rounded,
                                    size: 15,
                                    color: scheme.primary,
                                  ),
                                  const SizedBox(width: 3),
                                  Text(
                                    'Verified',
                                    style: theme.textTheme.bodySmall?.copyWith(
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                ],
                              ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Lazada-style "My Orders" section with "View All Orders >" and the 5 status
/// icon shortcuts.
class _AccountOrdersSection extends StatelessWidget {
  const _AccountOrdersSection({required this.onOpenTab});

  final ValueChanged<String> onOpenTab;

  static const _shortcuts = [
    (
      key: 'to_pay',
      label: 'To Pay',
      icon: Icons.account_balance_wallet_outlined,
    ),
    (key: 'to_dispatch', label: 'Preparing', icon: Icons.inventory_2_outlined),
    (
      key: 'to_receive',
      label: 'On the way',
      icon: Icons.local_shipping_outlined,
    ),
    (key: 'to_review', label: 'To Review', icon: Icons.rate_review_outlined),
    (
      key: 'returns',
      label: 'Returns &\nCancellations',
      icon: Icons.assignment_return_outlined,
    ),
  ];

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Material(
      color: scheme.surfaceContainerLowest,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 12, 12, 14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'My Orders',
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                    fontSize: 16,
                  ),
                ),
                InkWell(
                  borderRadius: BorderRadius.circular(appSurfaceRadius),
                  onTap: () => onOpenTab('all'),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 4,
                      vertical: 2,
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          'View All Orders',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: scheme.onSurfaceVariant,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        Icon(
                          Icons.chevron_right_rounded,
                          size: 18,
                          color: scheme.onSurfaceVariant,
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final item in _shortcuts)
                  Expanded(
                    child: InkWell(
                      borderRadius: BorderRadius.circular(appSurfaceRadius),
                      onTap: () => onOpenTab(item.key),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 2,
                          vertical: 4,
                        ),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(item.icon, size: 25, color: scheme.onSurface),
                            const SizedBox(height: 6),
                            Text(
                              item.label,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              textAlign: TextAlign.center,
                              style: theme.textTheme.labelSmall?.copyWith(
                                fontSize: 11,
                                height: 1.15,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

/// Account shortcuts: addresses, reviews, messages, branches and help.
class _LazadaToolsGridSection extends StatelessWidget {
  const _LazadaToolsGridSection({
    required this.onAddressesTap,
    required this.onReviewsTap,
    required this.onMessagesTap,
    required this.onBranchesTap,
    required this.onHelpTap,
  });

  final VoidCallback onAddressesTap;
  final VoidCallback onReviewsTap;
  final VoidCallback onMessagesTap;
  final VoidCallback onBranchesTap;
  final VoidCallback onHelpTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    final row1 = [
      (
        icon: Icons.location_on_outlined,
        label: 'Delivery addresses',
        onTap: onAddressesTap,
      ),
      (
        icon: Icons.rate_review_outlined,
        label: 'My Reviews',
        onTap: onReviewsTap,
      ),
      (
        icon: Icons.chat_bubble_outline_rounded,
        label: 'Messages',
        onTap: onMessagesTap,
      ),
      (
        icon: Icons.storefront_outlined,
        label: 'Branches',
        onTap: onBranchesTap,
      ),
      (icon: Icons.help_outline_rounded, label: 'Help', onTap: onHelpTap),
    ];

    Widget buildRow(
      List<({IconData icon, String label, VoidCallback onTap})> items,
    ) {
      return Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          for (final item in items)
            Expanded(
              child: InkWell(
                borderRadius: BorderRadius.circular(appSurfaceRadius),
                onTap: item.onTap,
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 4,
                    vertical: 6,
                  ),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(item.icon, size: 25, color: scheme.onSurface),
                      const SizedBox(height: 6),
                      Text(
                        item.label,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        textAlign: TextAlign.center,
                        style: theme.textTheme.labelSmall?.copyWith(
                          fontSize: 11.5,
                          height: 1.15,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
        ],
      );
    }

    return Material(
      color: scheme.surfaceContainerLowest,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 14, 12, 16),
        child: buildRow(row1),
      ),
    );
  }
}
