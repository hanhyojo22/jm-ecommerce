import 'package:flutter/material.dart';

import 'theme.dart';
import 'widgets/startup_splash.dart';

/// Keeps initialization concurrent with the one-time brand animation.
class AppStartup extends StatefulWidget {
  const AppStartup({super.key, required this.application});

  final Future<Widget> application;

  @override
  State<AppStartup> createState() => _AppStartupState();
}

class _AppStartupState extends State<AppStartup> {
  bool _animationComplete = false;

  @override
  Widget build(BuildContext context) => FutureBuilder<Widget>(
    future: widget.application,
    builder: (context, snapshot) {
      if (snapshot.hasError) {
        return _StartupErrorApp(error: snapshot.error!);
      }
      if (_animationComplete && snapshot.hasData) return snapshot.data!;
      return MaterialApp(
        title: 'Everyday Pharmacy',
        theme: buildAppTheme(),
        debugShowCheckedModeBanner: false,
        home: StartupSplash(
          onComplete: () => setState(() => _animationComplete = true),
        ),
      );
    },
  );
}

class _StartupErrorApp extends StatelessWidget {
  const _StartupErrorApp({required this.error});

  final Object error;

  @override
  Widget build(BuildContext context) => MaterialApp(
    title: 'Everyday Pharmacy',
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
                'Could not start Everyday Pharmacy',
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
