import 'package:cached_network_image/cached_network_image.dart';
import 'package:clerk_auth/clerk_auth.dart' as clerk;
import 'package:clerk_flutter/clerk_flutter.dart';
import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config.dart';
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

/// Our own sign-in landing; Clerk's stock form only handles the email,
/// code, sign-up and second-factor steps, whose rules we don't duplicate.
class SignInScreen extends StatefulWidget {
  const SignInScreen({super.key});

  @override
  State<SignInScreen> createState() => _SignInScreenState();
}

class _SignInScreenState extends State<SignInScreen> {
  static const _ink = Color(0xFF1E2422);
  static const _muted = Color(0xFF545A57);
  static const _hairline = Color(0xFFE2DFD7);

  bool _showEmailForm = false;
  clerk.SocialConnection? _pendingConnection;

  Future<void> _signInWith(clerk.SocialConnection connection) async {
    setState(() => _pendingConnection = connection);
    try {
      await ClerkAuth.of(
        context,
        listen: false,
      ).ssoSignIn(context, connection.strategy);
    } finally {
      if (mounted) {
        setState(() => _pendingConnection = null);
      }
    }
  }

  void _openEmailForm() => setState(() => _showEmailForm = true);

  @override
  Widget build(BuildContext context) {
    return _showEmailForm ? _buildEmailForm(context) : _buildLanding(context);
  }

  Widget _buildEmailForm(BuildContext context) {
    void close() => setState(() => _showEmailForm = false);

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) {
          close();
        }
      },
      child: Scaffold(
        appBar: AppBar(
          leading: BackButton(onPressed: close),
          title: const Text('Continue with email'),
        ),
        body: const SafeArea(
          child: Padding(
            padding: EdgeInsets.fromLTRB(16, 8, 16, 16),
            child: ClerkAuthentication(),
          ),
        ),
      ),
    );
  }

  Widget _buildLanding(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final authState = ClerkAuth.of(context);
    final connections = authState.isNotAvailable
        ? const <clerk.SocialConnection>[]
        : authState.env.socialConnections;

    return Scaffold(
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) => SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(24, 28, 24, 24),
            child: ConstrainedBox(
              constraints: BoxConstraints(
                minHeight: constraints.maxHeight - 52,
              ),
              child: IntrinsicHeight(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        Container(
                          width: 30,
                          height: 30,
                          decoration: BoxDecoration(
                            color: scheme.primary,
                            borderRadius: BorderRadius.circular(9),
                          ),
                          child: const Icon(
                            Icons.shopping_bag_outlined,
                            size: 17,
                            color: Color(0xFFF7F6F2),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          'JMMart',
                          style: TextStyle(
                            color: scheme.primary,
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                            letterSpacing: -0.3,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 64),
                    Semantics(
                      header: true,
                      child: const Text(
                        'Sign in to JMMart',
                        style: TextStyle(
                          color: _ink,
                          fontSize: 32,
                          height: 38 / 32,
                          fontWeight: FontWeight.w700,
                          letterSpacing: -0.8,
                        ),
                      ),
                    ),
                    const SizedBox(height: 10),
                    const Text(
                      'Your orders, saved stores and seller chats, on every device.',
                      style: TextStyle(
                        color: _muted,
                        fontSize: 16,
                        height: 1.5,
                      ),
                    ),
                    const SizedBox(height: 36),
                    for (final connection in connections) ...[
                      _SocialButton(
                        connection: connection,
                        isPending: _pendingConnection == connection,
                        onPressed: _pendingConnection == null
                            ? () => _signInWith(connection)
                            : null,
                      ),
                      const SizedBox(height: 12),
                    ],
                    if (connections.isNotEmpty)
                      const Padding(
                        padding: EdgeInsets.only(top: 12, bottom: 24),
                        child: Row(
                          children: [
                            Expanded(child: Divider(color: _hairline)),
                            Padding(
                              padding: EdgeInsets.symmetric(horizontal: 12),
                              child: Text(
                                'or',
                                style: TextStyle(color: _muted, fontSize: 13),
                              ),
                            ),
                            Expanded(child: Divider(color: _hairline)),
                          ],
                        ),
                      ),
                    FilledButton.icon(
                      onPressed: _pendingConnection == null
                          ? _openEmailForm
                          : null,
                      icon: const Icon(Icons.mail_outline_rounded, size: 20),
                      label: const Text('Continue with email'),
                    ),
                    const Spacer(),
                    const SizedBox(height: 32),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Text(
                          'New to JMMart?',
                          style: TextStyle(color: _muted, fontSize: 15),
                        ),
                        TextButton(
                          onPressed: _pendingConnection == null
                              ? _openEmailForm
                              : null,
                          child: const Text(
                            'Create an account',
                            style: TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text.rich(
                      TextSpan(
                        style: const TextStyle(
                          fontSize: 12,
                          height: 1.5,
                          color: Color(0xFF6B706D),
                        ),
                        children: [
                          const TextSpan(
                            text: 'By continuing you agree to the ',
                          ),
                          _linkSpan('Terms', '/terms', scheme.primary),
                          const TextSpan(text: ' and '),
                          _linkSpan(
                            'Privacy Policy',
                            '/privacy-policy',
                            scheme.primary,
                          ),
                          const TextSpan(text: '.'),
                        ],
                      ),
                      textAlign: TextAlign.center,
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  TextSpan _linkSpan(String text, String path, Color color) {
    return TextSpan(
      text: text,
      style: TextStyle(
        color: color,
        fontWeight: FontWeight.w600,
        decoration: TextDecoration.underline,
        decorationColor: color,
      ),
      recognizer: TapGestureRecognizer()
        ..onTap = () => launchUrl(
          Uri.parse(AppConfig.apiBaseUrl).resolve(path),
          mode: LaunchMode.externalApplication,
        ),
    );
  }
}

class _SocialButton extends StatelessWidget {
  const _SocialButton({
    required this.connection,
    required this.isPending,
    required this.onPressed,
  });

  final clerk.SocialConnection connection;
  final bool isPending;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return OutlinedButton.icon(
      onPressed: onPressed,
      style: OutlinedButton.styleFrom(
        minimumSize: const Size.fromHeight(52),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF1E2422),
        side: const BorderSide(color: Color(0xFFD9D6CE)),
        textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w500),
      ),
      icon: SizedBox.square(
        dimension: 20,
        child: isPending
            ? const CircularProgressIndicator(strokeWidth: 2)
            : CachedNetworkImage(
                imageUrl: connection.logoUrl,
                errorWidget: (context, url, error) =>
                    const Icon(Icons.login, size: 20),
              ),
      ),
      label: Text('Continue with ${connection.name}'),
    );
  }
}
