import 'package:go_router/go_router.dart';

import 'models/medication.dart';
import 'providers/catalog_providers.dart';
import 'screens/auth_gate.dart';
import 'screens/addresses_screen.dart';
import 'screens/branch_locator_screen.dart';
import 'screens/cart_screen.dart';
import 'screens/chat_screen.dart';
import 'screens/checkout_screen.dart';
import 'screens/checkout_success_screen.dart';
import 'screens/medicine_history_screen.dart';
import 'screens/medicine_people_screen.dart';
import 'screens/medicine_setup_screen.dart';
import 'screens/medicines_screen.dart';
import 'screens/order_detail_screen.dart';
import 'screens/product_screen.dart';
import 'screens/profile_screen.dart';
import 'screens/saved_screen.dart';
import 'screens/search_screen.dart';
import 'screens/settings_screen.dart';
import 'screens/store_screen.dart';
import 'widgets/shell.dart';

final appRouter = GoRouter(
  initialLocation: '/',
  routes: [
    GoRoute(path: '/', builder: (context, state) => const AppShell()),
    GoRoute(path: '/sign-in', builder: (context, state) => const SignInPage()),
    GoRoute(
      path: '/product/:store/:slug',
      builder: (context, state) => ProductScreen(
        productKey: ProductKey(
          store: state.pathParameters['store'] ?? '',
          slug: state.pathParameters['slug'] ?? '',
        ),
      ),
    ),
    GoRoute(
      path: '/store/:slug',
      builder: (context, state) =>
          StoreScreen(slug: state.pathParameters['slug'] ?? ''),
    ),
    GoRoute(
      path: '/branches',
      builder: (context, state) => const BranchLocatorScreen(),
    ),
    GoRoute(path: '/search', builder: (context, state) => const SearchScreen()),
    GoRoute(path: '/cart', builder: (context, state) => const CartScreen()),
    GoRoute(
      path: '/checkout',
      builder: (context, state) => const CheckoutScreen(),
    ),
    GoRoute(
      path: '/checkout/success',
      redirect: (context, state) =>
          state.extra is CheckoutSuccessDetails ? null : '/',
      builder: (context, state) => CheckoutSuccessScreen(
        details: state.extra! as CheckoutSuccessDetails,
      ),
    ),
    GoRoute(
      path: '/orders/:id',
      builder: (context, state) => OrderDetailScreen(
        orderId: state.pathParameters['id'] ?? '',
        returnFor: state.uri.queryParameters['return'],
      ),
    ),
    GoRoute(
      path: '/conversations/:id',
      builder: (context, state) => ChatScreen(
        conversationId: state.pathParameters['id'] ?? '',
        productId: state.uri.queryParameters['product_id'],
        sellerOrderId: state.uri.queryParameters['seller_order_id'],
        focusComposer: state.uri.queryParameters['reply'] == '1',
      ),
    ),
    GoRoute(
      path: '/addresses',
      builder: (context, state) => const AddressesScreen(),
    ),
    GoRoute(path: '/saved', builder: (context, state) => const SavedScreen()),
    GoRoute(
      path: '/medicines',
      builder: (context, state) =>
          MedicinesScreen(countPlanId: state.uri.queryParameters['count']),
    ),
    GoRoute(
      path: '/medicines/setup',
      builder: (context, state) => state.extra is MedicationSuggestion
          ? MedicineSetupScreen(
              suggestion: state.extra! as MedicationSuggestion,
            )
          : const MedicinesScreen(),
    ),
    GoRoute(
      path: '/medicines/history',
      builder: (context, state) => const MedicineHistoryScreen(),
    ),
    GoRoute(
      path: '/medicines/people',
      builder: (context, state) => const MedicinePeopleScreen(),
    ),
    GoRoute(
      path: '/medicines/dose',
      builder: (context, state) => MedicineDoseScreen(
        planId: state.uri.queryParameters['plan'] ?? '',
        slot: state.uri.queryParameters['slot'] ?? '',
        action: state.uri.queryParameters['action'],
      ),
    ),
    GoRoute(
      path: '/medicines/reorder',
      builder: (context, state) => MedicineReorderScreen(
        planIds: (state.uri.queryParameters['plans'] ?? '')
            .split(',')
            .where((id) => id.isNotEmpty)
            .toList(),
      ),
    ),
    GoRoute(
      path: '/profile',
      builder: (context, state) => ProfileScreen(
        completing: state.uri.queryParameters['complete'] == '1',
      ),
    ),
    GoRoute(
      path: '/settings',
      builder: (context, state) => const SettingsScreen(),
    ),
  ],
);
