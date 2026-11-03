class AppConfig {
  const AppConfig._();

  /// Android emulator reaches the host machine at 10.0.2.2. A physical device
  /// needs the LAN address passed with --dart-define.
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1',
  );

  static const String clerkPublishableKey = String.fromEnvironment(
    'CLERK_PUBLISHABLE_KEY',
  );

  static const String reverbKey = String.fromEnvironment(
    'REVERB_APP_KEY',
    defaultValue: 'jmmart-local-key',
  );

  static const String reverbHost = String.fromEnvironment(
    'REVERB_HOST',
    defaultValue: '10.0.2.2',
  );

  static const int reverbPort = int.fromEnvironment(
    'REVERB_PORT',
    defaultValue: 8080,
  );

  static const bool reverbUseTls = bool.fromEnvironment(
    'REVERB_USE_TLS',
    defaultValue: false,
  );

  /// Native WebSocket clients send no Origin, which Reverb rejects unless the
  /// server allows this value. Keep it in sync with REVERB_ALLOWED_ORIGINS.
  static const String reverbOrigin = String.fromEnvironment(
    'REVERB_ORIGIN',
    defaultValue: 'http://127.0.0.1:8000',
  );

  static bool get hasClerk => clerkPublishableKey.isNotEmpty;

  static bool get hasReverb => reverbKey.isNotEmpty;

  /// The API origin without the versioned path, for links that live outside /api/v1.
  static String get apiOrigin =>
      apiBaseUrl.replaceFirst(RegExp(r'/api/v1/?$'), '');
}
