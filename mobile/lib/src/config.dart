class AppConfig {
  const AppConfig._();

  /// Use Render by default so the app does not depend on a running local PC.
  /// Local development can override these values with --dart-define.
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://jmmart-marketplace.onrender.com/api/v1',
  );

  static const String clerkPublishableKey = String.fromEnvironment(
    'CLERK_PUBLISHABLE_KEY',
  );

  /// Web OAuth client ID of the Google Cloud project behind Clerk's Google
  /// connection; native Google sign-in issues its ID token for this audience.
  /// Without it, Google falls back to Clerk's web sign-in.
  static const String googleWebClientId = String.fromEnvironment(
    'GOOGLE_WEB_CLIENT_ID',
  );

  /// Where the browser hands Facebook sign-in back to the app. Must match the
  /// intent filter in AndroidManifest.xml and Clerk's allowlisted redirect URLs.
  static final Uri oauthRedirectUri = Uri.parse('jmmart://oauth-callback');

  static const String reverbKey = String.fromEnvironment(
    'REVERB_APP_KEY',
    defaultValue: 'd4258d9d1bdd4fbd09df58dc182cba0e84359788001b076d',
  );

  static const String reverbHost = String.fromEnvironment(
    'REVERB_HOST',
    defaultValue: 'jmmart-chat-reverb.onrender.com',
  );

  static const int reverbPort = int.fromEnvironment(
    'REVERB_PORT',
    defaultValue: 443,
  );

  static const bool reverbUseTls = bool.fromEnvironment(
    'REVERB_USE_TLS',
    defaultValue: true,
  );

  /// Native WebSocket clients send no Origin, which Reverb rejects unless the
  /// server allows this value. Keep it in sync with REVERB_ALLOWED_ORIGINS.
  static const String reverbOrigin = String.fromEnvironment(
    'REVERB_ORIGIN',
    defaultValue: 'https://jmmart-marketplace.onrender.com',
  );

  static bool get hasClerk => clerkPublishableKey.isNotEmpty;

  static bool get hasReverb => reverbKey.isNotEmpty;

  /// The API origin without the versioned path, for links that live outside /api/v1.
  static String get apiOrigin =>
      apiBaseUrl.replaceFirst(RegExp(r'/api/v1/?$'), '');
}
