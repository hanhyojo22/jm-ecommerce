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
import 'src/core/clerk_http_service.dart';
import 'src/core/push_notifications.dart';
import 'src/models/models.dart';
import 'src/providers/auth_providers.dart';
import 'src/providers/catalog_providers.dart';
import 'src/theme.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  // Started now and awaited before runApp: it runs alongside Clerk's startup
  // instead of in front of it, and push registration still finds it ready.
  final firebaseReady = _initFirebase();
  final historyFile = File(
    '${Directory.systemTemp.path}/jmmart_search_history.json',
  );
  final initialHistory = await _loadSearchHistory(historyFile);

  if (!AppConfig.hasClerk) {
    await firebaseReady;
    runApp(
      ProviderScope(
        overrides: [
          searchHistoryFileProvider.overrideWithValue(historyFile),
          initialSearchHistoryProvider.overrideWithValue(initialHistory),
        ],
        child: const JmMartApp(),
      ),
    );
    return;
  }

  try {
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

    runApp(
      ProviderScope(
        overrides: [
          clerkAuthProvider.overrideWithValue(authState),
          searchHistoryFileProvider.overrideWithValue(historyFile),
          initialSearchHistoryProvider.overrideWithValue(initialHistory),
        ],
        child: JmMartApp(authState: authState),
      ),
    );
  } catch (error) {
    await firebaseReady;
    runApp(_StartupErrorApp(error: error));
  }
}

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

class _StartupErrorApp extends StatelessWidget {
  const _StartupErrorApp({required this.error});

  final Object error;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'JMMart',
      theme: buildAppTheme(),
      darkTheme: buildAppTheme(brightness: Brightness.dark),
      themeMode: ThemeMode.system,
      debugShowCheckedModeBanner: false,
      home: Scaffold(
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(
                  Icons.cloud_off_outlined,
                  size: 56,
                  color: Theme.of(context).colorScheme.error,
                ),
                const SizedBox(height: 20),
                Text(
                  'Could not start JMMart',
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
                const SizedBox(height: 12),
                const Text('Check your connection and restart the app.'),
                const SizedBox(height: 12),
                Text('$error', style: Theme.of(context).textTheme.bodySmall),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
