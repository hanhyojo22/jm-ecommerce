import 'dart:convert';
import 'dart:io';

import 'package:app_links/app_links.dart';
import 'package:clerk_auth/clerk_auth.dart' as clerk;
import 'package:clerk_flutter/clerk_flutter.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'src/app.dart';
import 'src/config.dart';
import 'src/core/api_exception.dart';
import 'src/core/clerk_http_service.dart';
import 'src/core/push_notifications.dart';
import 'src/models/models.dart';
import 'src/providers/auth_providers.dart';
import 'src/providers/catalog_providers.dart';
import 'src/providers/connectivity_providers.dart';
import 'src/startup.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(AppStartup(application: _createApplication()));
}

Future<Widget> _createApplication() async {
  final firebaseReady = _initFirebase();
  final historyFile = File(
    '${Directory.systemTemp.path}/jmmart_search_history.json',
  );
  final initialHistory = await _loadSearchHistory(historyFile);

  if (!AppConfig.hasClerk) {
    await firebaseReady;
    return ProviderScope(
      retry: _retry,
      observers: [failedProviders],
      overrides: [
        searchHistoryFileProvider.overrideWithValue(historyFile),
        initialSearchHistoryProvider.overrideWithValue(initialHistory),
      ],
      child: const JmMartApp(),
    );
  }

  final authState = await ClerkAuthState.create(
    config: ClerkAuthConfig(
      publishableKey: AppConfig.clerkPublishableKey,
      httpService: const NativeClerkHttpService(),
      // Facebook signs in through the browser, which can hand off to the
      // Facebook app; other providers keep Clerk's in-app flow.
      redirectionGenerator: (context, strategy) =>
          strategy.provider == clerk.Strategy.oauthFacebook.provider
          ? AppConfig.oauthRedirectUri
          : null,
      deepLinkStream: AppLinks().uriLinkStream,
    ),
  );
  await firebaseReady;

  return ProviderScope(
    retry: _retry,
    observers: [failedProviders],
    overrides: [
      clerkAuthProvider.overrideWithValue(authState),
      searchHistoryFileProvider.overrideWithValue(historyFile),
      initialSearchHistoryProvider.overrideWithValue(initialHistory),
    ],
    child: JmMartApp(authState: authState),
  );
}

/// Request errors show at once instead of after Riverpod's ~40 seconds of silent retries;
/// failed screens reload when the phone is back online (see [failedProviders]).
Duration? _retry(int retryCount, Object error) => error is ApiException
    ? null
    : ProviderContainer.defaultRetry(retryCount, error);

Future<SearchHistory> _loadSearchHistory(File file) async {
  try {
    if (!await file.exists()) {
      return const SearchHistory();
    }
    return SearchHistory.fromJson(mapOf(jsonDecode(await file.readAsString())));
  } catch (_) {
    return const SearchHistory();
  }
}

/// Never throws: without google-services.json or Play Services the app still
/// works, just without push notifications.
Future<void> _initFirebase() async {
  try {
    await Firebase.initializeApp();
    FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);
    debugPrint('[Push] Firebase.initializeApp() succeeded');
  } catch (error) {
    debugPrint('[Push] Firebase.initializeApp() failed: $error');
  }
}
