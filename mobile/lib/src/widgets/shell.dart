import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:phosphor_icons/phosphor_icons.dart';

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

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
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
        body: IndexedStack(
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
        bottomNavigationBar: DecoratedBox(
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
              onDestinationSelected: (value) =>
                  ref.read(shellTabProvider.notifier).select(value),
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
