import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/push_notifications.dart';
import 'auth_providers.dart';

/// Registers this device's push token once per signed-in app session.
/// Watched (not read) from [AppShell] so Riverpod keeps the provider alive
/// and never re-runs this on a tab switch or other rebuild.
final pushRegistrationProvider = FutureProvider<void>(
  (ref) => PushNotifications.register(ref.watch(apiClientProvider)),
);
