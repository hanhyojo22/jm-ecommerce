import 'package:cached_network_image/cached_network_image.dart';
import 'package:clerk_auth/clerk_auth.dart' as clerk;
import 'package:clerk_flutter/clerk_flutter.dart';
// ignore: implementation_imports
import 'package:clerk_flutter/src/widgets/authentication/clerk_sign_in_panel.dart';
// ignore: implementation_imports
import 'package:clerk_flutter/src/widgets/authentication/clerk_sign_up_panel.dart';
import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:phosphor_icons/phosphor_icons.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:uuid/uuid.dart';

import '../config.dart';
import '../providers/auth_providers.dart';
import '../theme.dart';
import '../widgets/common.dart';

/// True when signed in; a guest is sent to sign in first, so the caller can
/// carry on with the action (add to cart, chat) once they come back signed in.
Future<bool> requireSignIn(BuildContext context, WidgetRef ref) async {
  if (ref.read(isSignedInProvider)) {
    return true;
  }
  await context.push('/sign-in');
  return context.mounted && ref.read(isSignedInProvider);
}

/// Shown in place of a signed-in-only tab while browsing as a guest.
class SignInPrompt extends StatelessWidget {
  const SignInPrompt({
    super.key,
    required this.icon,
    required this.title,
    required this.message,
  });

  final IconData icon;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) {
    return EmptyState(
      icon: icon,
      title: title,
      message: message,
      action: FilledButton(
        onPressed: () => context.push('/sign-in'),
        child: const Text('Sign in or create account'),
      ),
    );
  }
}

/// The pushed sign-in route; it closes itself once the guest is signed in.
class SignInPage extends ConsumerStatefulWidget {
  const SignInPage({super.key});

  @override
  ConsumerState<SignInPage> createState() => _SignInPageState();
}

class _SignInPageState extends ConsumerState<SignInPage> {
  bool _closing = false;

  @override
  Widget build(BuildContext context) {
    final signedIn = ref.watch(isSignedInProvider);
    // Wait until Clerk's own sign-in screens above this one have closed, so
    // the pop removes this page and not one of theirs.
    final isCurrent = ModalRoute.of(context)?.isCurrent ?? true;
    if (signedIn && isCurrent && !_closing) {
      _closing = true;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) {
          context.canPop() ? context.pop() : context.go('/');
        }
      });
    }
    return const SignInScreen();
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
  bool _showEmailForm = false;
  bool _signUp = false;
  clerk.SocialConnection? _pendingConnection;

  Future<void> _signInWith(clerk.SocialConnection connection) async {
    setState(() => _pendingConnection = connection);
    final auth = ClerkAuth.of(context, listen: false);
    try {
      if (connection.strategy.provider == clerk.Strategy.oauthGoogle.provider &&
          AppConfig.googleWebClientId.isNotEmpty) {
        await _signInWithNativeGoogle(auth);
      } else {
        await auth.ssoSignIn(context, connection.strategy);
      }
    } finally {
      if (mounted) {
        setState(() => _pendingConnection = null);
      }
    }
  }

  /// Shows Android's Google account picker, then hands its ID token to Clerk.
  Future<void> _signInWithNativeGoogle(ClerkAuthState auth) async {
    final String? idToken;
    try {
      // Clerk accepts each Google ID token nonce once, so every attempt needs a
      // new one; google_sign_in only takes the nonce in initialize, which on
      // Android (google_sign_in_android 7.2.x) just replaces the stored values.
      await GoogleSignIn.instance.initialize(
        serverClientId: AppConfig.googleWebClientId,
        nonce: const Uuid().v4(),
      );
      final account = await GoogleSignIn.instance.authenticate();
      idToken = account.authentication.idToken;
    } on GoogleSignInException catch (error) {
      if (error.code != GoogleSignInExceptionCode.canceled) {
        auth.handleError(clerk.ClerkError.external(error));
      }
      return;
    }
    if (!mounted) {
      return;
    }
    await auth.safelyCall(
      context,
      () => auth.idTokenSignIn(
        provider: clerk.IdTokenProvider.google,
        token: idToken,
      ),
    );
  }

  void _openEmailForm({required bool signUp}) {
    final auth = ClerkAuth.of(context, listen: false);
    // A cancelled Facebook or Google attempt stays open in Clerk and would
    // leave the email form showing only a Back button; start it clean.
    if (auth.isSigningIn || auth.isSigningUp) {
      auth.safelyCall(context, auth.resetClient);
    }
    setState(() {
      _showEmailForm = true;
      _signUp = signUp;
    });
  }

  @override
  Widget build(BuildContext context) {
    return _showEmailForm ? _buildEmailForm(context) : _buildLanding(context);
  }

  /// Clerk's own sign-in and sign-up panels without its card, whose header
  /// shows the dashboard's app name and a "Development mode" label.
  Widget _buildEmailForm(BuildContext context) {
    void close() => setState(() => _showEmailForm = false);
    final authState = ClerkAuth.of(context);
    // Follow Clerk when it moves an attempt between sign-in and sign-up, as its card does.
    final signUp = authState.isSigningUp || (_signUp && !authState.isSigningIn);

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) {
          close();
        }
      },
      child: _AuthFrame(
        leading: BackButton(onPressed: close),
        title: signUp ? 'Create your account' : 'Sign in with email',
        subtitle: signUp
            ? 'It takes a minute. Then add medicines and check out.'
            : 'Welcome back to Everyday Pharmacy.',
        sheet: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (authState.isNotAvailable)
              const SizedBox.shrink()
            else
              _ClerkTextScale(
                child: signUp
                    ? const ClerkSignUpPanel()
                    : const ClerkSignInPanel(),
              ),
            const SizedBox(height: 16),
            // Mid-attempt Clerk keeps the current mode, so switching would do nothing.
            if (!authState.isSigningIn && !authState.isSigningUp)
              _SwitchLine(
                prompt: signUp
                    ? 'Already have an account?'
                    : 'New to Everyday Pharmacy?',
                action: signUp ? 'Sign in' : 'Create an account',
                onPressed: () => setState(() => _signUp = !signUp),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildLanding(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final authState = ClerkAuth.of(context);
    final connections = authState.isNotAvailable
        ? const <clerk.SocialConnection>[]
        : authState.env.socialConnections;
    final idle = _pendingConnection == null;

    return _AuthFrame(
      trailing: IconButton(
        tooltip: 'Keep browsing',
        onPressed: () => Navigator.of(context).maybePop(),
        icon: const Icon(PhosphorIconsRegular.x),
      ),
      title: 'Sign in to Everyday Pharmacy',
      subtitle: 'Sign in or create an account to add medicines to your cart, order and chat with a pharmacist.',
      large: true,
      sheet: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (final connection in connections) ...[
            _SocialButton(
              connection: connection,
              isPending: _pendingConnection == connection,
              onPressed: idle ? () => _signInWith(connection) : null,
            ),
            const SizedBox(height: 12),
          ],
          if (connections.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 8, bottom: 20),
              child: Row(
                children: [
                  Expanded(child: Divider(color: scheme.outlineVariant)),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 14),
                    child: Text(
                      'or',
                      style: theme.textTheme.labelMedium?.copyWith(
                        color: scheme.onSurfaceVariant,
                      ),
                    ),
                  ),
                  Expanded(child: Divider(color: scheme.outlineVariant)),
                ],
              ),
            ),
          FilledButton.icon(
            onPressed: idle ? () => _openEmailForm(signUp: false) : null,
            icon: const Icon(PhosphorIconsRegular.envelopeSimple, size: 20),
            label: const Text('Continue with email'),
          ),
          const Spacer(),
          const SizedBox(height: 28),
          _SwitchLine(
            prompt: 'New to Everyday Pharmacy?',
            action: 'Create an account',
            onPressed: idle ? () => _openEmailForm(signUp: true) : null,
          ),
          const SizedBox(height: 4),
          Text.rich(
            TextSpan(
              style: theme.textTheme.bodySmall?.copyWith(height: 1.5),
              children: [
                const TextSpan(text: 'By continuing you agree to the '),
                _linkSpan('Terms', '/terms', scheme.primary),
                const TextSpan(text: ' and '),
                _linkSpan('Privacy Policy', '/privacy-policy', scheme.primary),
                const TextSpan(text: '.'),
              ],
            ),
            textAlign: TextAlign.center,
          ),
        ],
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
    final scheme = Theme.of(context).colorScheme;

    return OutlinedButton.icon(
      onPressed: onPressed,
      style: OutlinedButton.styleFrom(
        minimumSize: const Size.fromHeight(52),
        backgroundColor: scheme.surfaceContainerLowest,
        foregroundColor: scheme.onSurface,
        side: BorderSide(color: scheme.outlineVariant, width: 1.2),
        textStyle: Theme.of(context).textTheme.labelLarge
            ?.copyWith(fontSize: 15.5),
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

/// The sign-in screens' frame: the Shop header's brand slab, carrying the
/// wordmark, the pharmacy's capsule mark and the heading, with a sheet rising
/// out of it that holds the actions.
class _AuthFrame extends StatelessWidget {
  const _AuthFrame({
    required this.title,
    required this.subtitle,
    required this.sheet,
    this.leading,
    this.trailing,
    this.large = false,
  });

  final String title;
  final String subtitle;
  final Widget sheet;
  final Widget? leading;
  final Widget? trailing;

  /// The landing gets the tall slab and display heading; the email steps stay compact.
  final bool large;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final slab = brandSlab(scheme);
    final topInset = MediaQuery.paddingOf(context).top;
    final reduceMotion = MediaQuery.disableAnimationsOf(context);
    final bottomPadding = large ? 40.0 : 30.0;
    // The landing slab fills ~42% of the screen so the heading meets the sheet
    // and the actions sit low, within thumb reach.
    final minContentHeight = large
        ? MediaQuery.sizeOf(context).height * 0.42 -
              topInset -
              6 -
              bottomPadding
        : 0.0;

    final header = Stack(
      children: [
        Positioned(
          right: large ? -64 : -40,
          top: large ? topInset + 34 : topInset - 6,
          child: ExcludeSemantics(
            child: Transform.rotate(
              angle: -0.62,
              child: Icon(
                PhosphorIconsFill.pill,
                size: large ? 250 : 170,
                color: slab.ink.withValues(alpha: 0.10),
              ),
            ),
          ),
        ),
        Padding(
          padding: EdgeInsets.fromLTRB(
            leading == null ? 24 : 8,
            topInset + 6,
            8,
            bottomPadding,
          ),
          child: IconTheme.merge(
            data: IconThemeData(color: slab.ink),
            child: ConstrainedBox(
              constraints: BoxConstraints(minHeight: minContentHeight),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(
                    height: 48,
                    child: Row(
                      children: [
                        ?leading,
                        // Same wordmark as the Shop header.
                        Text.rich(
                          TextSpan(
                            children: [
                              // Two-tone like the logo: sun-yellow "Everyday".
                              TextSpan(
                                text: 'Everyday',
                                style: TextStyle(
                                  color: Theme.of(context)
                                      .colorScheme
                                      .secondary,
                                ),
                              ),
                              TextSpan(
                                text: '  Pharmacy',
                                style: TextStyle(
                                  fontWeight: FontWeight.w500,
                                  color: slab.ink.withValues(alpha: 0.72),
                                ),
                              ),
                            ],
                          ),
                          style: theme.textTheme.titleLarge?.copyWith(
                            color: slab.ink,
                            fontWeight: FontWeight.w800,
                            letterSpacing: -0.5,
                          ),
                        ),
                        const Spacer(),
                        ?trailing,
                      ],
                    ),
                  ),
                  SizedBox(height: large ? 24 : 18),
                  Padding(
                    padding: EdgeInsets.only(
                      left: leading == null ? 0 : 16,
                      right: 40,
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Semantics(
                          header: true,
                          child: Text(
                            title,
                            style:
                                (large
                                        ? theme.textTheme.displaySmall
                                        : theme.textTheme.headlineMedium)
                                    ?.copyWith(color: slab.ink),
                          ),
                        ),
                        const SizedBox(height: 10),
                        Text(
                          subtitle,
                          style: theme.textTheme.bodyLarge?.copyWith(
                            color: slab.ink.withValues(alpha: 0.80),
                            height: 1.5,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );

    return Scaffold(
      // The slab colour sits behind the sheet's rounded corners.
      backgroundColor: slab.fill,
      body: AnnotatedRegion<SystemUiOverlayStyle>(
        value: SystemUiOverlayStyle.light.copyWith(
          statusBarColor: Colors.transparent,
        ),
        child: LayoutBuilder(
          builder: (context, constraints) => SingleChildScrollView(
            child: ConstrainedBox(
              constraints: BoxConstraints(minHeight: constraints.maxHeight),
              child: IntrinsicHeight(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    header,
                    Expanded(
                      // The one authored moment: the sheet settles up into place.
                      child: TweenAnimationBuilder<double>(
                        tween: Tween(begin: 1, end: 0),
                        duration: reduceMotion
                            ? Duration.zero
                            : const Duration(milliseconds: 560),
                        curve: Curves.easeOutExpo,
                        builder: (context, t, child) => Transform.translate(
                          offset: Offset(0, 36 * t),
                          child: child,
                        ),
                        child: DecoratedBox(
                          decoration: BoxDecoration(
                            color: scheme.surfaceContainerLowest,
                            borderRadius: const BorderRadius.vertical(
                              top: Radius.circular(appModalRadius + 4),
                            ),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: 0.12),
                                offset: const Offset(0, -6),
                                blurRadius: 24,
                              ),
                            ],
                          ),
                          child: SafeArea(
                            top: false,
                            child: Padding(
                              padding: const EdgeInsets.fromLTRB(
                                24,
                                28,
                                24,
                                20,
                              ),
                              child: sheet,
                            ),
                          ),
                        ),
                      ),
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
}

/// "New to JMMart? Create an account" and its reverse, under the actions.
class _SwitchLine extends StatelessWidget {
  const _SwitchLine({
    required this.prompt,
    required this.action,
    required this.onPressed,
  });

  final String prompt;
  final String action;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Wrap(
      alignment: WrapAlignment.center,
      crossAxisAlignment: WrapCrossAlignment.center,
      children: [
        Text(
          prompt,
          style: theme.textTheme.bodyMedium?.copyWith(
            color: theme.colorScheme.onSurfaceVariant,
          ),
        ),
        TextButton(onPressed: onPressed, child: Text(action)),
      ],
    );
  }
}

/// Clerk sets its buttons, links and hints in 10–12px type; inside the
/// sign-in screens they follow the app's 14px label scale instead.
class _ClerkTextScale extends StatelessWidget {
  const _ClerkTextScale({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final clerkTheme = theme.extension<ClerkThemeExtension>();
    if (clerkTheme == null) {
      return child;
    }
    return Theme(
      data: theme.copyWith(
        extensions: [clerkTheme.copyWith(stylesBuilder: _ClerkStyles.new)],
      ),
      child: child,
    );
  }
}

class _ClerkStyles extends ClerkThemeStyles {
  _ClerkStyles(ClerkThemeColors colors) : super(colors: colors);

  // Clerk derives its link and button styles from text, so they grow with it.
  @override
  TextStyle get text => super.text.copyWith(fontSize: 14);

  @override
  TextStyle get subtext => super.subtext.copyWith(fontSize: 12);

  @override
  TextStyle get button =>
      super.button.copyWith(fontSize: 14.5, fontWeight: FontWeight.w700);
}
