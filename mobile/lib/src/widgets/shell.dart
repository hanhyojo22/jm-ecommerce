import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../providers/cart_providers.dart';
import '../providers/chat_providers.dart';
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

class AppShell extends ConsumerWidget {
  const AppShell({super.key});

  static const _titles = ['Discover', 'Orders', 'Messages', 'Account'];

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final tab = ref.watch(shellTabProvider);
    final index = tab.index;
    final cartCount = ref.watch(cartCountProvider);
    final unread =
        ref
            .watch(conversationsProvider)
            .value
            ?.fold<int>(0, (sum, item) => sum + item.unreadCount) ??
        0;

    return Scaffold(
      appBar: AppBar(
        backgroundColor: index == 0
            ? Theme.of(context).colorScheme.surfaceContainerLowest
            : null,
        surfaceTintColor: index == 0 ? Colors.transparent : null,
        title: index == 0
            ? Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 34,
                    height: 34,
                    decoration: BoxDecoration(
                      color: Theme.of(context).colorScheme.primary,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Icon(
                      Icons.storefront_outlined,
                      size: 22,
                      color: Theme.of(context).colorScheme.onPrimary,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Flexible(
                    child: Text(
                      'JMMart.',
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.primary,
                      ),
                    ),
                  ),
                ],
              )
            : Text(_titles[index]),
        titleSpacing: 20,
        actions: [
          if (index == 0)
            Semantics(
              liveRegion: true,
              child: IconButton.filledTonal(
                tooltip:
                    'Cart, $cartCount ${cartCount == 1 ? 'item' : 'items'}',
                style: IconButton.styleFrom(
                  backgroundColor: Theme.of(context).colorScheme.primary
                      .withValues(alpha: 0.07),
                  foregroundColor: Theme.of(context).colorScheme.primary,
                ),
                onPressed: () => context.push('/cart'),
                icon: Badge(
                  isLabelVisible: cartCount > 0,
                  label: ExcludeSemantics(
                    child: AnimatedSwitcher(
                      duration: MediaQuery.disableAnimationsOf(context)
                          ? Duration.zero
                          : const Duration(milliseconds: 180),
                      child: Text(
                        cartCount > 99 ? '99+' : '$cartCount',
                        key: ValueKey(cartCount),
                      ),
                    ),
                  ),
                  child: const Icon(Icons.shopping_bag_outlined),
                ),
              ),
            ),
        ],
      ),
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
