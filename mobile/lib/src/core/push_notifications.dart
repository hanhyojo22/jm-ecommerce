import 'dart:async';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import '../router.dart';
import 'api_client.dart';

/// Android shows the OS notification for a backgrounded/terminated app by
/// itself from the message's `notification` payload, so there is nothing to
/// do here. The handler only has to exist and be a top-level/static
/// function for `FirebaseMessaging.onBackgroundMessage` to accept it.
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {}

/// Registers this device for push, shows a notification while the app is in
/// the foreground (FCM does not do this automatically), and deep-links a tap
/// to the relevant conversation or notification inbox.
///
/// shortcut: uses the deprecated `getToken()`/`onTokenRefresh` instead of
/// the newer `register()`/`onRegistered`, because the latter sends a
/// Firebase Installation ID rather than a classic FCM registration token,
/// and the backend (`kreait/firebase-php`'s `sendMulticast`) expects the
/// classic token. Revisit if `getToken` is ever removed, not just deprecated.
class PushNotifications {
  PushNotifications._();

  static const _channel = AndroidNotificationChannel(
    'chat_messages',
    'Chat messages',
    description: 'New messages from buyers and sellers.',
    importance: Importance.high,
  );

  static final _local = FlutterLocalNotificationsPlugin();
  static bool _initialized = false;
  static StreamSubscription<String>? _refreshSubscription;

  /// Safe to call once per signed-in session; every failure is swallowed so
  /// a push setup problem (denied permission, no Google Play Services) never
  /// blocks sign-in or the rest of the app.
  ///
  /// [onMessage] runs for every foreground push, read or not, so the caller
  /// can refresh its own unread-count state (the badge would otherwise only
  /// catch up the next time something else re-fetches conversations).
  static Future<void> register(
    ApiClient apiClient, {
    VoidCallback? onMessage,
  }) async {
    try {
      final settings = await FirebaseMessaging.instance.requestPermission();
      debugPrint('[Push] permission status: ${settings.authorizationStatus}');

      if (!_initialized) {
        await _local
            .resolvePlatformSpecificImplementation<
              AndroidFlutterLocalNotificationsPlugin
            >()
            ?.createNotificationChannel(_channel);

        await _local.initialize(
          const InitializationSettings(
            android: AndroidInitializationSettings('@mipmap/ic_launcher'),
          ),
          onDidReceiveNotificationResponse: (response) =>
              _openTarget(response.payload),
        );

        FirebaseMessaging.onMessage.listen((message) {
          onMessage?.call();
          _showForeground(message);
        });
        FirebaseMessaging.onMessageOpenedApp.listen(
          (message) => _openTarget(message.data['conversation_id']),
        );

        _initialized = true;
      }

      final token = await FirebaseMessaging.instance.getToken();
      debugPrint(
        '[Push] getToken(): ${token == null ? 'null' : '${token.substring(0, 12)}...'}',
      );
      if (token != null) {
        await apiClient.registerDeviceToken(token);
        debugPrint('[Push] registerDeviceToken sent successfully');
      }

      unawaited(_refreshSubscription?.cancel());
      _refreshSubscription = FirebaseMessaging.instance.onTokenRefresh.listen(
        apiClient.registerDeviceToken,
      );

      final initialMessage = await FirebaseMessaging.instance
          .getInitialMessage();
      if (initialMessage != null) {
        _openTarget(initialMessage.data['conversation_id']);
      }
    } catch (error, stack) {
      // Push is a nice-to-have; never let it block the rest of the app.
      debugPrint('[Push] register() failed: $error\n$stack');
    }
  }

  /// Best-effort: the backend also prunes a token the next time a push to it
  /// fails, so a lost unregister call (app killed mid sign-out) self-heals.
  static Future<void> unregister(ApiClient apiClient) async {
    try {
      final token = await FirebaseMessaging.instance.getToken();
      if (token != null) {
        await apiClient.unregisterDeviceToken(token);
      }
    } catch (_) {
      // See above.
    }
  }

  static Future<void> _showForeground(RemoteMessage message) async {
    final notification = message.notification;
    if (notification == null) {
      return;
    }

    await _local.show(
      notification.hashCode,
      notification.title,
      notification.body,
      NotificationDetails(
        android: AndroidNotificationDetails(_channel.id, _channel.name),
      ),
      payload: message.data['conversation_id'] ?? 'notifications',
    );
  }

  static void _openTarget(String? conversationId) {
    if (conversationId != null &&
        conversationId.isNotEmpty &&
        conversationId != 'notifications') {
      appRouter.push('/conversations/$conversationId');
    } else {
      appRouter.push('/notifications');
    }
  }
}
