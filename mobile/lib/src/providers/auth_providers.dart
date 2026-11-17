import 'package:clerk_flutter/clerk_flutter.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config.dart';
import '../core/api_client.dart';
import '../models/models.dart';

/// Overridden by the signed-in subtree with the live Clerk auth state.
final clerkAuthProvider = Provider<ClerkAuthState>(
  (ref) => throw UnimplementedError(
    'clerkAuthProvider must be overridden after sign-in',
  ),
);

Future<String?> _sessionToken(ClerkAuthState auth) async {
  try {
    final token = await auth.sessionToken();

    return token.jwt;
  } catch (_) {
    return null;
  }
}

/// The signed-in Clerk user's id, null for a guest. It changes only on sign-in,
/// sign-out or an account switch, not on Clerk's frequent token refreshes.
class SignedInUserNotifier extends Notifier<String?> {
  @override
  String? build() {
    final auth = ref.watch(clerkAuthProvider);
    void sync() {
      final id = auth.user?.id;
      if (state != id) {
        state = id;
      }
    }

    auth.addListener(sync);
    ref.onDispose(() => auth.removeListener(sync));

    return auth.user?.id;
  }
}

final signedInUserProvider = NotifierProvider<SignedInUserNotifier, String?>(
  SignedInUserNotifier.new,
);

/// Guests browse the shop; carts, orders, chats and the account need sign-in.
final isSignedInProvider = Provider<bool>(
  (ref) => ref.watch(signedInUserProvider) != null,
);

final apiClientProvider = Provider<ApiClient>((ref) {
  final auth = ref.watch(clerkAuthProvider);
  // A new client per user refetches everything, so the last user's data never shows.
  ref.watch(signedInUserProvider);

  return ApiClient(
    baseUrl: AppConfig.apiBaseUrl,
    tokenProvider: () => _sessionToken(auth),
  );
});

final meProvider = FutureProvider.autoDispose<Account>(
  (ref) => ref.watch(apiClientProvider).me(),
);
