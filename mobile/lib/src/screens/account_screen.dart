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
                'Help & Buyer Protection',
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 10),
              Text(
                'Orders are protected from checkout until delivery confirmation. You can message any seller directly from your order details or the Messages tab.',
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
              _LazadaAccountHeader(
                account: account,
                onHelpTap: () => _showHelpSheet(context),
                onSettingsTap: () => context.push('/addresses'),
                onMemberTap: () =>
                    ref.read(shellTabProvider.notifier).select(0),
              ),
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
              _LazadaHighlightsSection(
                onOrdersTap: () => _openOrdersTab(ref, 'all'),
                onDealsTap: () => ref.read(shellTabProvider.notifier).select(0),
                onAddressesTap: () => context.push('/addresses'),
              ),
              const SizedBox(height: 8),
              _LazadaChannelsSection(
                onShopTap: () => ref.read(shellTabProvider.notifier).select(0),
              ),
              const SizedBox(height: 8),
              _LazadaToolsGridSection(
                onAddressesTap: () => context.push('/addresses'),
                onReviewsTap: () => _openOrdersTab(ref, 'to_review'),
                onMessagesTap: () =>
                    ref.read(shellTabProvider.notifier).select(2),
                onCartTap: () => ref.read(shellTabProvider.notifier).select(3),
                onShopTap: () => ref.read(shellTabProvider.notifier).select(0),
                onFastDeliveryTap: () => _openOrdersTab(ref, 'to_receive'),
                onProtectionTap: () => _showHelpSheet(context),
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

/// Top Lazada-style profile banner: soft tinted gradient, top-right help/gear
/// icons, avatar + name + membership badge on the left, and the "Member Center"
/// gradient card on the right.
class _LazadaAccountHeader extends StatelessWidget {
  const _LazadaAccountHeader({
    required this.account,
    required this.onHelpTap,
    required this.onSettingsTap,
    required this.onMemberTap,
  });

  final Account account;
  final VoidCallback onHelpTap;
  final VoidCallback onSettingsTap;
  final VoidCallback onMemberTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final largeText = MediaQuery.textScalerOf(context).scale(14) > 18;

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
          padding: const EdgeInsets.fromLTRB(16, 6, 12, 14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  IconButton(
                    tooltip: 'Help',
                    visualDensity: VisualDensity.compact,
                    onPressed: onHelpTap,
                    icon: Icon(
                      Icons.help_outline_rounded,
                      size: 22,
                      color: scheme.onSurface,
                    ),
                  ),
                  IconButton(
                    tooltip: 'Settings',
                    visualDensity: VisualDensity.compact,
                    onPressed: onSettingsTap,
                    icon: Icon(
                      Icons.settings_outlined,
                      size: 22,
                      color: scheme.onSurface,
                    ),
                  ),
                ],
              ),
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
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 7,
                                vertical: 2,
                              ),
                              decoration: BoxDecoration(
                                color: scheme.primaryContainer,
                                borderRadius: BorderRadius.circular(999),
                              ),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(
                                    Icons.diamond_rounded,
                                    size: 12,
                                    color: scheme.primary,
                                  ),
                                  const SizedBox(width: 4),
                                  Text(
                                    account.accountType.toUpperCase(),
                                    style: TextStyle(
                                      fontSize: 10.5,
                                      fontWeight: FontWeight.w800,
                                      color: scheme.onPrimaryContainer,
                                    ),
                                  ),
                                ],
                              ),
                            ),
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
                  if (!largeText) ...[
                    const SizedBox(width: 8),
                    InkWell(
                      borderRadius: BorderRadius.circular(appSurfaceRadius),
                      onTap: onMemberTap,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 10,
                        ),
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            colors: [
                              scheme.primary,
                              Color.lerp(
                                scheme.primary,
                                scheme.secondary,
                                0.45,
                              )!,
                            ],
                          ),
                          borderRadius: BorderRadius.circular(appSurfaceRadius),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Text(
                                  'Member Center',
                                  style: TextStyle(
                                    color: scheme.onPrimary,
                                    fontSize: 12,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                                const SizedBox(width: 3),
                                Icon(
                                  Icons.chevron_right_rounded,
                                  size: 15,
                                  color: scheme.onPrimary,
                                ),
                              ],
                            ),
                            const SizedBox(height: 2),
                            Text(
                              'Free Shipping Perks',
                              style: TextStyle(
                                color: scheme.onPrimary.withValues(alpha: 0.9),
                                fontSize: 10.5,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
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
    (key: 'to_dispatch', label: 'To Ship', icon: Icons.inventory_2_outlined),
    (
      key: 'to_receive',
      label: 'To Receive',
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

/// 3-card Lazada perk strip (Rewards / Vouchers / Addresses).
class _LazadaHighlightsSection extends StatelessWidget {
  const _LazadaHighlightsSection({
    required this.onOrdersTap,
    required this.onDealsTap,
    required this.onAddressesTap,
  });

  final VoidCallback onOrdersTap;
  final VoidCallback onDealsTap;
  final VoidCallback onAddressesTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final largeText = MediaQuery.textScalerOf(context).scale(14) > 18;
    if (largeText) {
      return const SizedBox.shrink();
    }

    return Material(
      color: scheme.surfaceContainerLowest,
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            Expanded(
              child: _HighlightMiniCard(
                badge: 'Rewards',
                headline: 'Free Shipping',
                subtitle: 'Daily perks',
                ctaLabel: 'Collect',
                filledCta: true,
                tint: const Color(0xFFFFF8E5),
                onTap: onDealsTap,
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: _HighlightMiniCard(
                badge: 'Vouchers',
                headline: 'Up to 70%',
                subtitle: 'Store deals',
                ctaLabel: 'Browse >',
                filledCta: false,
                tint: const Color(0xFFFFF2F4),
                onTap: onDealsTap,
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: _HighlightMiniCard(
                badge: 'Addresses',
                headline: 'Fast Checkout',
                subtitle: 'Saved places',
                ctaLabel: 'Manage >',
                filledCta: false,
                tint: const Color(0xFFEEF7FF),
                onTap: onAddressesTap,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _HighlightMiniCard extends StatelessWidget {
  const _HighlightMiniCard({
    required this.badge,
    required this.headline,
    required this.subtitle,
    required this.ctaLabel,
    required this.filledCta,
    required this.tint,
    required this.onTap,
  });

  final String badge;
  final String headline;
  final String subtitle;
  final String ctaLabel;
  final bool filledCta;
  final Color tint;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final dark = scheme.brightness == Brightness.dark;

    return InkWell(
      borderRadius: BorderRadius.circular(appSurfaceRadius),
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.fromLTRB(10, 8, 10, 10),
        decoration: BoxDecoration(
          color: dark ? scheme.surfaceContainerHigh : tint,
          borderRadius: BorderRadius.circular(appSurfaceRadius),
        ),
        child: Column(
          children: [
            Text(
              badge,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.labelSmall?.copyWith(
                fontWeight: FontWeight.w700,
                color: scheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              headline,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w800,
                fontSize: 13.5,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              subtitle,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.bodySmall?.copyWith(
                fontSize: 11,
                color: scheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 8),
            if (filledCta)
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 4,
                ),
                decoration: BoxDecoration(
                  color: scheme.primary,
                  borderRadius: BorderRadius.circular(appSurfaceRadius),
                ),
                child: Text(
                  ctaLabel,
                  style: TextStyle(
                    color: scheme.onPrimary,
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              )
            else
              Text(
                ctaLabel,
                style: TextStyle(
                  color: scheme.onSurface,
                  fontSize: 11.5,
                  fontWeight: FontWeight.w700,
                ),
              ),
          ],
        ),
      ),
    );
  }
}

/// Lazada-style "My Channels" 3-card showcase section.
class _LazadaChannelsSection extends StatelessWidget {
  const _LazadaChannelsSection({required this.onShopTap});

  final VoidCallback onShopTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final largeText = MediaQuery.textScalerOf(context).scale(14) > 18;
    if (largeText) {
      return const SizedBox.shrink();
    }

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
                  'My Channels',
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                    fontSize: 16,
                  ),
                ),
                InkWell(
                  borderRadius: BorderRadius.circular(appSurfaceRadius),
                  onTap: onShopTap,
                  child: Padding(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 4,
                      vertical: 2,
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          'All Channels',
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
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: _ChannelTile(
                    title: 'SHOP NOW!',
                    caption: 'UP TO 70% OFF',
                    icon: Icons.local_fire_department_rounded,
                    accent: const Color(0xFFE53935),
                    onTap: onShopTap,
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: _ChannelTile(
                    title: 'Top Stores',
                    caption: '100% Authentic',
                    icon: Icons.verified_rounded,
                    accent: scheme.primary,
                    onTap: onShopTap,
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: _ChannelTile(
                    title: 'Flash Deals',
                    caption: 'Fast Dispatch',
                    icon: Icons.bolt_rounded,
                    accent: const Color(0xFFD97706),
                    onTap: onShopTap,
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

class _ChannelTile extends StatelessWidget {
  const _ChannelTile({
    required this.title,
    required this.caption,
    required this.icon,
    required this.accent,
    required this.onTap,
  });

  final String title;
  final String caption;
  final IconData icon;
  final Color accent;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return InkWell(
      borderRadius: BorderRadius.circular(appSurfaceRadius),
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: scheme.surfaceContainerHighest.withValues(alpha: 0.38),
          borderRadius: BorderRadius.circular(appSurfaceRadius),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.labelMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                Icon(
                  Icons.arrow_circle_right_rounded,
                  size: 15,
                  color: scheme.onSurface,
                ),
              ],
            ),
            const SizedBox(height: 8),
            Container(
              height: 62,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: accent.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(appDetailRadius),
              ),
              child: Icon(icon, size: 32, color: accent),
            ),
            const SizedBox(height: 6),
            Center(
              child: Text(
                caption,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: theme.textTheme.labelSmall?.copyWith(
                  color: scheme.onSurfaceVariant,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Lazada 2x4 bottom utility icon grid (Delivery addresses, My Reviews,
/// Messages, Cart, Followed Stores, Buyer Protection, Fast Deliveries, Help).
class _LazadaToolsGridSection extends StatelessWidget {
  const _LazadaToolsGridSection({
    required this.onAddressesTap,
    required this.onReviewsTap,
    required this.onMessagesTap,
    required this.onCartTap,
    required this.onShopTap,
    required this.onFastDeliveryTap,
    required this.onProtectionTap,
    required this.onHelpTap,
  });

  final VoidCallback onAddressesTap;
  final VoidCallback onReviewsTap;
  final VoidCallback onMessagesTap;
  final VoidCallback onCartTap;
  final VoidCallback onShopTap;
  final VoidCallback onFastDeliveryTap;
  final VoidCallback onProtectionTap;
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
      (icon: Icons.shopping_bag_outlined, label: 'Your Cart', onTap: onCartTap),
    ];

    final row2 = [
      (icon: Icons.storefront_outlined, label: 'Stores', onTap: onShopTap),
      (
        icon: Icons.verified_user_outlined,
        label: 'Buyer Protection',
        onTap: onProtectionTap,
      ),
      (
        icon: Icons.local_shipping_outlined,
        label: 'Fast Deliveries',
        onTap: onFastDeliveryTap,
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
        child: Column(
          children: [
            buildRow(row1),
            const SizedBox(height: 12),
            buildRow(row2),
          ],
        ),
      ),
    );
  }
}
