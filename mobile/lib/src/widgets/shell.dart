import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../providers/chat_providers.dart';
import '../providers/push_providers.dart';
import '../screens/account_screen.dart';
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

final shellTabProvider =
    NotifierProvider.autoDispose<ShellTabNotifier, ShellTabState>(
      ShellTabNotifier.new,
    );

class AppShell extends ConsumerStatefulWidget {
  const AppShell({super.key});

  @override
  ConsumerState<AppShell> createState() => _AppShellState();
}

class _AppShellState extends ConsumerState<AppShell> with WidgetsBindingObserver {
  static const _titles = ['Discover', 'Orders', 'Messages', 'Account'];

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

    return Scaffold(
      // The Shop tab draws its own search header with the cart.
      appBar: index == 0
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
            const AccountScreen()
          else
            const SizedBox(),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: index,
        onDestinationSelected: (value) =>
            ref.read(shellTabProvider.notifier).select(value),
        destinations: [
          const NavigationDestination(
            icon: Icon(Icons.storefront_outlined),
            selectedIcon: Icon(Icons.storefront),
            label: 'Shop',
          ),
          const NavigationDestination(
            icon: Icon(Icons.receipt_long_outlined),
            selectedIcon: Icon(Icons.receipt_long),
            label: 'Orders',
          ),
          NavigationDestination(
            icon: Badge(
              isLabelVisible: unread > 0,
              label: Text(unread > 99 ? '99+' : '$unread'),
              child: const Icon(Icons.chat_bubble_outline_rounded),
            ),
            selectedIcon: const Icon(Icons.chat_bubble_rounded),
            label: 'Messages',
          ),
          const NavigationDestination(
            icon: Icon(Icons.person_outline_rounded),
            selectedIcon: Icon(Icons.person_rounded),
            label: 'Account',
          ),
        ],
      ),
    );
  }
}
