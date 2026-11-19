import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:phosphor_icons/phosphor_icons.dart';

import '../core/formatters.dart';
import '../providers/auth_providers.dart';
import '../providers/cart_providers.dart';
import '../providers/chat_providers.dart';
import '../providers/push_providers.dart';
import '../providers/store_locator_providers.dart';
import '../screens/account_screen.dart';
import '../screens/auth_gate.dart';
import '../screens/cart_screen.dart';
import '../screens/catalog_screen.dart';
import '../screens/messages_screen.dart';
import '../screens/orders_screen.dart';
import '../theme.dart';
import 'store_locator.dart';

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
    // Like other shopping apps: explain, then ask for location on first open.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        askForLocation(context, ref, automatic: true);
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
      // Back from Settings, where location may have just been allowed.
      if (ref.read(nearestStoreProvider).value?.issue != null) {
        ref.invalidate(nearestStoreProvider);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final signedIn = ref.watch(isSignedInProvider);
    // Cached by Riverpod: runs once per signed-in session, not on every tab switch.
    if (signedIn) {
      ref.watch(pushRegistrationProvider);
    }

    final tab = ref.watch(shellTabProvider);
    final index = tab.index;
    // Guests see a sign-in prompt, with a title bar, on every tab but Shop.
    final guestTab = !signedIn && index != 0;
    final unread =
        ref
            .watch(conversationsProvider)
            .value
            ?.fold<int>(0, (sum, item) => sum + item.unreadCount) ??
        0;
    final cartCount = ref.watch(cartCountProvider);
    final scheme = Theme.of(context).colorScheme;

    // Wide windows (tablets, unfolded phones) get a side rail instead of a stretched bottom bar.
    final wide = MediaQuery.sizeOf(context).width >= 600;
    final Widget page = AnnotatedRegion<SystemUiOverlayStyle>(
      value:
          (Theme.of(context).brightness == Brightness.dark
                  ? SystemUiOverlayStyle.light
                  : SystemUiOverlayStyle.dark)
              .copyWith(statusBarColor: Colors.transparent),
      child: IndexedStack(
        index: index,
        children: [
          const CatalogScreen(),
          if (!signedIn) ...[
            for (var i = 1; i <= 4; i++) _guestPrompt(i),
          ] else ...[
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
        ],
      ),
    );

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
        appBar:
            !guestTab && (index == 0 || index == 1 || index == 3 || index == 4)
            ? null
            : AppBar(title: Text(_titles[index]), titleSpacing: 20),
        // Tabs with light headers need dark status-bar icons; the Shop tab's band sets its own.
        body: wide
            ? Row(
                children: [
                  _rail(index, unread, cartCount),
                  const VerticalDivider(width: 1),
                  Expanded(child: page),
                ],
              )
            : page,
        bottomNavigationBar: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Like a food app: while shopping, the cart rides along above the tabs.
            if (index == 0 && cartCount > 0)
              _ViewCartBar(
                count: cartCount,
                subtotal: ref.watch(cartProvider).value?.availableTotal,
                onTap: () => ref.read(shellTabProvider.notifier).select(3),
              ),
            if (!wide) _navigation(context, index, unread, cartCount, scheme),
          ],
        ),
      ),
    );
  }

  static Widget _guestPrompt(int index) => switch (index) {
    1 => const SignInPrompt(
      icon: PhosphorIconsRegular.receipt,
      title: 'Sign in to see your orders',
      message: 'Track deliveries, prescriptions and past orders.',
    ),
    2 => const SignInPrompt(
      icon: PhosphorIconsRegular.chatCircleDots,
      title: 'Sign in to message the pharmacy',
      message: 'Ask a pharmacist about a medicine or an order.',
    ),
    3 => const SignInPrompt(
      icon: PhosphorIconsRegular.tote,
      title: 'Sign in to start your cart',
      message: 'Browse freely, then sign in to add medicines and check out.',
    ),
    _ => const SignInPrompt(
      icon: PhosphorIconsRegular.user,
      title: 'Sign in to Everyday Pharmacy',
      message: 'Create an account or sign in to order medicines.',
    ),
  };

  void _select(int value) {
    if (value == 2) {
      ref.invalidate(conversationsProvider);
    }
    ref.read(shellTabProvider.notifier).select(value);
  }

  Widget _rail(int index, int unread, int cartCount) => SafeArea(
    right: false,
    child: NavigationRail(
      selectedIndex: index,
      labelType: NavigationRailLabelType.all,
      onDestinationSelected: _select,
      destinations: [
        const NavigationRailDestination(
          icon: Icon(PhosphorIconsRegular.house),
          selectedIcon: Icon(PhosphorIconsFill.house),
          label: Text('Shop'),
        ),
        const NavigationRailDestination(
          icon: Icon(PhosphorIconsRegular.receipt),
          selectedIcon: Icon(PhosphorIconsFill.receipt),
          label: Text('Orders'),
        ),
        NavigationRailDestination(
          icon: _NavBadge(
            count: unread,
            child: const Icon(PhosphorIconsRegular.chatCircleDots),
          ),
          selectedIcon: _NavBadge(
            count: unread,
            child: const Icon(PhosphorIconsFill.chatCircleDots),
          ),
          label: const Text('Messages'),
        ),
        NavigationRailDestination(
          icon: _NavBadge(
            count: cartCount,
            child: const Icon(PhosphorIconsRegular.tote),
          ),
          selectedIcon: _NavBadge(
            count: cartCount,
            child: const Icon(PhosphorIconsFill.tote),
          ),
          label: const Text('Cart'),
        ),
        const NavigationRailDestination(
          icon: Icon(PhosphorIconsRegular.user),
          selectedIcon: Icon(PhosphorIconsFill.user),
          label: Text('Account'),
        ),
      ],
    ),
  );

  Widget _navigation(
    BuildContext context,
    int index,
    int unread,
    int cartCount,
    ColorScheme scheme,
  ) => DecoratedBox(
    decoration: BoxDecoration(
      color: scheme.surfaceContainerLowest,
      border: Border(top: BorderSide(color: scheme.outlineVariant)),
    ),
    // Outline glyphs at rest, the filled glyph when selected: one icon family.
    child: NavigationBar(
      selectedIndex: index,
      labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
      onDestinationSelected: _select,
      destinations: [
        const NavigationDestination(
          icon: Icon(PhosphorIconsRegular.house),
          selectedIcon: Icon(PhosphorIconsFill.house),
          label: 'Shop',
        ),
        const NavigationDestination(
          icon: Icon(PhosphorIconsRegular.receipt),
          selectedIcon: Icon(PhosphorIconsFill.receipt),
          label: 'Orders',
        ),
        NavigationDestination(
          icon: _NavBadge(
            count: unread,
            child: const Icon(PhosphorIconsRegular.chatCircleDots),
          ),
          selectedIcon: _NavBadge(
            count: unread,
            child: const Icon(PhosphorIconsFill.chatCircleDots),
          ),
          label: 'Messages',
        ),
        NavigationDestination(
          icon: _NavBadge(
            count: cartCount,
            child: const Icon(PhosphorIconsRegular.tote),
          ),
          selectedIcon: _NavBadge(
            count: cartCount,
            child: const Icon(PhosphorIconsFill.tote),
          ),
          label: 'Cart',
        ),
        const NavigationDestination(
          icon: Icon(PhosphorIconsRegular.user),
          selectedIcon: Icon(PhosphorIconsFill.user),
          label: 'Account',
        ),
      ],
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
    final slab = brandSlab(theme.colorScheme);
    final label = '$count ${count == 1 ? 'item' : 'items'}';

    return MediaQuery.withClampedTextScaling(
      maxScaleFactor: 1.3,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 0, 12, 10),
        child: Material(
          color: slab.fill,
          elevation: 6,
          shadowColor: slab.fill.withValues(alpha: 0.45),
          borderRadius: BorderRadius.circular(16),
          child: InkWell(
            borderRadius: BorderRadius.circular(16),
            onTap: onTap,
            child: ConstrainedBox(
              constraints: const BoxConstraints(minHeight: 56),
              child: Padding(
                padding: const EdgeInsets.fromLTRB(8, 8, 18, 8),
                child: Row(
                  children: [
                    Container(
                      width: 40,
                      height: 40,
                      decoration: BoxDecoration(
                        color: theme.colorScheme.secondary,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Icon(
                        PhosphorIconsFill.tote,
                        color: theme.colorScheme.onSecondary,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        'View cart · $label',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleSmall?.copyWith(
                          color: slab.ink,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                    if (subtotal != null)
                      Text(
                        money(subtotal),
                        style: theme.textTheme.titleSmall?.copyWith(
                          color: theme.colorScheme.secondary,
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
