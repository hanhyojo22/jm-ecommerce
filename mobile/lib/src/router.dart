import 'package:go_router/go_router.dart';

import 'providers/catalog_providers.dart';
import 'screens/auth_gate.dart';
import 'screens/addresses_screen.dart';
import 'screens/chat_screen.dart';
import 'screens/checkout_screen.dart';
import 'screens/order_detail_screen.dart';
import 'screens/product_screen.dart';

final appRouter = GoRouter(
  initialLocation: '/',
  routes: [
    GoRoute(path: '/', builder: (context, state) => const AuthGate()),
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
      path: '/checkout',
      builder: (context, state) => const CheckoutScreen(),
    ),
    GoRoute(
      path: '/orders/:id',
      builder: (context, state) =>
          OrderDetailScreen(orderId: state.pathParameters['id'] ?? ''),
    ),
    GoRoute(
      path: '/conversations/:id',
      builder: (context, state) =>
          ChatScreen(conversationId: state.pathParameters['id'] ?? ''),
    ),
    GoRoute(
      path: '/addresses',
      builder: (context, state) => const AddressesScreen(),
    ),
  ],
);
