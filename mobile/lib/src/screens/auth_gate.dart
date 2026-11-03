import 'package:clerk_flutter/clerk_flutter.dart';
import 'package:flutter/material.dart';

import '../widgets/shell.dart';

class AuthGate extends StatelessWidget {
  const AuthGate({super.key});

  @override
  Widget build(BuildContext context) {
    return ClerkAuthBuilder(
      signedOutBuilder: (context, auth) => const SignInScreen(),
      signedInBuilder: (context, auth) => const AppShell(),
    );
  }
}

class SignInScreen extends StatelessWidget {
  const SignInScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(body: SafeArea(child: ClerkAuthentication()));
  }
}
