import 'package:clerk_auth/clerk_auth.dart' as clerk;
import 'package:clerk_flutter/clerk_flutter.dart';
// ignore: implementation_imports
import 'package:clerk_flutter/src/utils/localization_extensions.dart';
import 'package:flutter/material.dart';

import 'config.dart';
import 'router.dart';
import 'theme.dart';

class JmMartApp extends StatelessWidget {
  const JmMartApp({super.key, this.authState});

  final ClerkAuthState? authState;

  @override
  Widget build(BuildContext context) {
    if (!AppConfig.hasClerk || authState == null) {
      return MaterialApp(
        title: 'JMMart',
        theme: buildAppTheme(),
        darkTheme: buildAppTheme(brightness: Brightness.dark),
        themeMode: ThemeMode.system,
        debugShowCheckedModeBanner: false,
        home: const MissingConfigScreen(),
      );
    }

    return ClerkAuth(
      authState: authState,
      child: MaterialApp.router(
        title: 'JMMart',
        theme: buildAppTheme(),
        darkTheme: buildAppTheme(brightness: Brightness.dark),
        themeMode: ThemeMode.system,
        debugShowCheckedModeBanner: false,
        routerConfig: appRouter,
        // ClerkErrorListener needs MaterialApp's ScaffoldMessenger.
        builder: (context, child) => ClerkErrorListener(
          handler: _showClerkError,
          child: child ?? const SizedBox.shrink(),
        ),
      ),
    );
  }
}

/// Same snackbar as Clerk's default, minus WebView sub-resource failures:
/// the SDK reports every failed request inside the Facebook login page
/// (e.g. net::ERR_CONNECTION_REFUSED) even though sign-in still works.
void _showClerkError(BuildContext context, clerk.ClerkError error) {
  if (error.code == clerk.ClerkErrorCode.clientAppError &&
      error.message.contains('net::ERR_')) {
    return;
  }
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(
      content: Text(error.localizedMessage(ClerkAuth.localizationsOf(context))),
    ),
  );
}

class MissingConfigScreen extends StatelessWidget {
  const MissingConfigScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                Icons.settings_suggest_outlined,
                size: 56,
                color: Theme.of(context).colorScheme.primary,
              ),
              const SizedBox(height: 20),
              Text(
                'Clerk is not configured',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 12),
              const Text(
                'Build or run the app with your Clerk publishable key:\n\n'
                'flutter run --dart-define=CLERK_PUBLISHABLE_KEY=pk_test_... '
                '--dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1',
              ),
              const SizedBox(height: 24),
              Text(
                'Current API: ${AppConfig.apiBaseUrl}',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
