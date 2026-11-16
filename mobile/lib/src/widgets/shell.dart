import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:phosphor_icons/phosphor_icons.dart';

import '../core/formatters.dart';
import '../providers/cart_providers.dart';
import '../providers/chat_providers.dart';
import '../providers/push_providers.dart';
import '../screens/account_screen.dart';
import '../screens/cart_screen.dart';
import '../screens/catalog_screen.dart';
import '../screens/messages_screen.dart';
import '../screens/orders_screen.dart';

class ShellTabState {
  const ShellTabState({required this.index, required this.loaded});

  final int index;
  final Set<int> loaded;
}

class ShellTabNotifier extends Notifier<ShellTabState> {
  @override
  ShellTabState build() => const ShellTabState(index: 0, loaded: {0});

  void select(int index) =>
      state = ShellTabState(index: index, loaded: {...state.loaded, index});
}

final shellTabProvider = NotifierProvider<ShellTabNotifier, ShellTabState>(
  ShellTabNotifier.new,
);

class AppShell extends ConsumerStatefulWidget {
  const AppShell({super.key});

  @override
  ConsumerState<AppShell> createState() => _AppShellState();
}

class _AppShellState extends ConsumerState<AppShell>
    with WidgetsBindingObserver {
  static const _titles = [
    'Discover',
    'Orders',
    'Messages',
    'Your cart',
    'Account',
  ];

  /// Keeps the Messages badge current when no push arrives (push off, or not yet delivered).
  static const _unreadRefreshInterval = Duration(seconds: 30);
  Timer? _unreadRefresh;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _unreadRefresh = Timer.periodic(_unreadRefreshInterval, (_) {
      // Only while the app is on screen; a backgrounded app catches up on resume.
      if (WidgetsBinding.instance.lifecycleState == AppLifecycleState.resumed) {
        ref.invalidate(conversationsProvider);
      }
    });
  }

  @override
  void dispose() {
    _unreadRefresh?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  /// A push received while backgrounded/killed never reaches [onMessage] in
  /// push_notifications.dart, so the unread badge would otherwise stay stale
  /// until something else happened to re-fetch conversations.
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      ref.invalidate(conversationsProvider);
    }
  }

  @override
  Widget build(BuildContext context) {
    // Cached by Riverpod: runs once per signed-in session, not on every tab switch.
    ref.watch(pushRegistrationProvider);

    final tab = ref.watch(shellTabProvider);
    final index = tab.index;
    final unread =
        ref
            .watch(conversationsProvider)
            .value
            ?.fold<int>(0, (sum, item) => sum + item.unreadCount) ??
        0;
    final cartCount = ref.watch(cartCountProvider);
    final scheme = Theme.of(context).colorScheme;

    // System Back returns to the Shop tab before it leaves the app.
    return PopScope(
      canPop: index == 0,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop && index != 0) {
          ref.read(shellTabProvider.notifier).select(0);
        }
      },
      child: Scaffold(
        // Shopping tabs draw their own marketplace headers.
        appBar: index == 0 || index == 1 || index == 3 || index == 4
            ? null
            : AppBar(title: Text(_titles[index]), titleSpacing: 20),
        // Tabs with light headers need dark status-bar icons; the Shop tab's band sets its own.
        body: AnnotatedRegion<SystemUiOverlayStyle>(
          value:
              (Theme.of(context).brightness == Brightness.dark
                      ? SystemUiOverlayStyle.light
                      : SystemUiOverlayStyle.dark)
                  .copyWith(statusBarColor: Colors.transparent),
          child: IndexedStack(
            index: index,
            children: [
              const CatalogScreen(),
              if (tab.loaded.contains(1))
                const OrdersScreen()
              else
                const SizedBox(),
              if (tab.loaded.contains(2))
                const MessagesScreen()
              else
                const SizedBox(),
              if (tab.loaded.contains(3))
                CartScreen(
                  embedded: true,
                  onBrowseProducts: () =>
                      ref.read(shellTabProvider.notifier).select(0),
                )
              else
                const SizedBox(),
              if (tab.loaded.contains(4))
                const AccountScreen()
              else
                const SizedBox(),
            ],
          ),
        ),
        bottomNavigationBar: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Like a food app: while shopping, the cart rides along above the tabs.
            if (index == 0 && cartCount > 0)
              _ViewCartBar(
                count: cartCount,
                subtotal: ref.watch(cartProvider).value?.subtotal,
                onTap: () => ref.read(shellTabProvider.notifier).select(3),
              ),
            _navigation(context, index, unread, cartCount, scheme),
          ],
        ),
      ),
    );
  }

  Widget _navigation(
    BuildContext context,
    int index,
    int unread,
    int cartCount,
    ColorScheme scheme,
  ) => DecoratedBox(
    decoration: BoxDecoration(
      color: scheme.surfaceContainerLowest,
      border: Border(
        top: BorderSide(
          color: scheme.primary.withValues(alpha: 0.14),
          width: 1,
        ),
      ),
      boxShadow: [
        BoxShadow(
          color: scheme.shadow.withValues(alpha: 0.05),
          blurRadius: 12,
          offset: const Offset(0, -2),
        ),
      ],
    ),
    child: PhosphorDuotoneTheme(
      data: PhosphorDuotoneThemeData(
        secondaryColor: scheme.primary,
        secondaryOpacity: 0.22,
      ),
      child: NavigationBar(
        selectedIndex: index,
        labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
        onDestinationSelected: (value) {
          if (value == 2) {
            ref.invalidate(conversationsProvider);
          }
          ref.read(shellTabProvider.notifier).select(value);
        },
        destinations: [
          NavigationDestination(
            icon: const Icon(PhosphorIconsBold.storefront),
            selectedIcon: PhosphorIcon(
              PhosphorIconsDuotone.storefront,
              color: scheme.primary,
            ),
            label: 'Shop',
          ),
          NavigationDestination(
            icon: const Icon(PhosphorIconsBold.receipt),
            selectedIcon: PhosphorIcon(
              PhosphorIconsDuotone.receipt,
              color: scheme.primary,
            ),
            label: 'Orders',
          ),
          NavigationDestination(
            icon: _NavBadge(
              count: unread,
              child: const Icon(PhosphorIconsBold.chatCircleDots),
            ),
            selectedIcon: _NavBadge(
              count: unread,
              child: PhosphorIcon(
                PhosphorIconsDuotone.chatCircleDots,
                color: scheme.primary,
              ),
            ),
            label: 'Messages',
          ),
          NavigationDestination(
            icon: _NavBadge(
              count: cartCount,
              child: const Icon(PhosphorIconsBold.shoppingCart),
            ),
            selectedIcon: _NavBadge(
              count: cartCount,
              child: PhosphorIcon(
                PhosphorIconsDuotone.shoppingCart,
                color: scheme.primary,
              ),
            ),
            label: 'Cart',
          ),
          NavigationDestination(
            icon: const Icon(PhosphorIconsBold.user),
            selectedIcon: PhosphorIcon(
              PhosphorIconsDuotone.user,
              color: scheme.primary,
            ),
            label: 'Account',
          ),
        ],
      ),
    ),
  );
}

class _ViewCartBar extends StatelessWidget {
  const _ViewCartBar({
    required this.count,
    required this.subtotal,
    required this.onTap,
  });

  final int count;
  final String? subtotal;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final label = '$count ${count == 1 ? 'item' : 'items'}';

    return MediaQuery.withClampedTextScaling(
      maxScaleFactor: 1.3,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 0, 12, 8),
        child: Material(
          color: scheme.primary,
          borderRadius: BorderRadius.circular(14),
          child: InkWell(
            borderRadius: BorderRadius.circular(14),
            onTap: onTap,
            child: ConstrainedBox(
              constraints: const BoxConstraints(minHeight: 52),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Row(
                  children: [
                    Icon(Icons.shopping_bag_rounded, color: scheme.onPrimary),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        'View cart · $label',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleSmall?.copyWith(
                          color: scheme.onPrimary,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                    if (subtotal != null)
                      Text(
                        money(subtotal),
                        style: theme.textTheme.titleSmall?.copyWith(
                          color: scheme.onPrimary,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _NavBadge extends StatelessWidget {
  const _NavBadge({required this.count, required this.child});

  final int count;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Badge(
      isLabelVisible: count > 0,
      backgroundColor: scheme.primary,
      textColor: scheme.onPrimary,
      // Nudge the bubble off the glyph so it reads as a separate shape.
      offset: const Offset(8, -6),
      label: Text(count > 99 ? '99+' : '$count'),
      child: child,
    );
  }
}
