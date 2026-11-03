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

final apiClientProvider = Provider<ApiClient>((ref) {
  final auth = ref.watch(clerkAuthProvider);

  return ApiClient(
    baseUrl: AppConfig.apiBaseUrl,
    tokenProvider: () => _sessionToken(auth),
  );
});

final meProvider = FutureProvider.autoDispose<Account>(
  (ref) => ref.watch(apiClientProvider).me(),
);
