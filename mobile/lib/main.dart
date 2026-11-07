import 'package:clerk_flutter/clerk_flutter.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'src/app.dart';
import 'src/config.dart';
import 'src/core/clerk_http_service.dart';
import 'src/core/push_notifications.dart';
import 'src/providers/auth_providers.dart';
import 'src/theme.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  try {
    await Firebase.initializeApp();
    FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);
    debugPrint('[Push] Firebase.initializeApp() succeeded');
  } catch (error) {
    // No google-services.json in this build, or no Google Play Services on
    // the device: the app still works, just without push notifications.
    debugPrint('[Push] Firebase.initializeApp() failed: $error');
  }

  if (!AppConfig.hasClerk) {
    runApp(const ProviderScope(child: JmMartApp()));
    return;
  }

  try {
    final authState = await ClerkAuthState.create(
      config: ClerkAuthConfig(
        publishableKey: AppConfig.clerkPublishableKey,
        httpService: const NativeClerkHttpService(),
      ),
    );

    runApp(
      ProviderScope(
        overrides: [clerkAuthProvider.overrideWithValue(authState)],
        child: JmMartApp(authState: authState),
      ),
    );
  } catch (error) {
    runApp(_StartupErrorApp(error: error));
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
