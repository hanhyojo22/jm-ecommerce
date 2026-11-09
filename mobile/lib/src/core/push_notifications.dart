import 'dart:async';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/painting.dart' show Color;
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
/// to the relevant conversation or order.
///
/// shortcut: uses the deprecated `getToken()`/`onTokenRefresh` instead of
/// the newer `register()`/`onRegistered`, because the latter sends a
/// Firebase Installation ID rather than a classic FCM registration token,
/// and the backend (`kreait/firebase-php`'s `sendMulticast`) expects the
/// classic token. Revisit if `getToken` is ever removed, not just deprecated.
class PushNotifications {
  PushNotifications._();

  // IDs must match PushNotifier::buildMessage() on the backend.
  static const _chatChannel = AndroidNotificationChannel(
    'chat_messages',
    'Messages',
    description: 'New messages from buyers and sellers.',
    importance: Importance.high,
  );
  static const _orderChannel = AndroidNotificationChannel(
    'order_updates',
    'Orders',
    description: 'Order status, delivery and return updates.',
    importance: Importance.high,
  );
  static const _updatesChannel = AndroidNotificationChannel(
    'marketplace_updates',
    'Updates and offers',
    description: 'JMMart announcements and cart reminders.',
    importance: Importance.defaultImportance,
  );
  static const _icon = '@drawable/ic_stat_notification';
  static const _accent = Color(0xFF14665A);

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
        final android = _local
            .resolvePlatformSpecificImplementation<
              AndroidFlutterLocalNotificationsPlugin
            >();
        await android?.createNotificationChannel(_chatChannel);
        await android?.createNotificationChannel(_orderChannel);
        await android?.createNotificationChannel(_updatesChannel);

        await _local.initialize(
          const InitializationSettings(
            android: AndroidInitializationSettings(_icon),
          ),
          onDidReceiveNotificationResponse: (response) =>
              _open(response.payload),
        );

        FirebaseMessaging.onMessage.listen((message) {
          onMessage?.call();
          _showForeground(message);
        });
        FirebaseMessaging.onMessageOpenedApp.listen(
          (message) => _open(routeFor(message.data)),
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
        _open(routeFor(initialMessage.data));
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

  /// The in-app screen a push should open, or null when the mobile app has
  /// no screen for it (seller-side pushes such as new seller orders).
  @visibleForTesting
  static String? routeFor(Map<String, dynamic> data) {
    String? id(String key) {
      final value = data[key]?.toString();
      return value == null || value.isEmpty ? null : value;
    }

    return switch (data['type']) {
      'chat' when id('conversation_id') != null =>
        '/conversations/${id('conversation_id')}',
      'order' when id('order_id') != null => '/orders/${id('order_id')}',
      'cart' => '/cart',
      _ => null,
    };
  }

  static Future<void> _showForeground(RemoteMessage message) async {
    final notification = message.notification;
    if (notification == null) {
      return;
    }

    final route = routeFor(message.data);
    // Already reading this chat: the new message is on screen, don't ping.
    if (route != null && appRouter.state.uri.path == route) {
      return;
    }

    final isChat = message.data['type'] == 'chat';
    final channel = switch (message.data['type']) {
      'chat' => _chatChannel,
      'announcement' || 'cart' => _updatesChannel,
      _ => _orderChannel,
    };
    // One notification per conversation, replaced by its newest message,
    // matching the `tag` the backend sets for background pushes.
    final tag = isChat
        ? 'conversation-${message.data['conversation_id']}'
        : null;

    await _local.show(
      (tag ?? message.messageId ?? notification).hashCode,
      notification.title,
      notification.body,
      NotificationDetails(
        android: AndroidNotificationDetails(
          channel.id,
          channel.name,
          channelDescription: channel.description,
          importance: channel.importance,
          priority: channel == _updatesChannel
              ? Priority.defaultPriority
              : Priority.high,
          icon: _icon,
          color: _accent,
          tag: tag,
          category: isChat
              ? AndroidNotificationCategory.message
              : AndroidNotificationCategory.status,
          styleInformation: BigTextStyleInformation(notification.body ?? ''),
        ),
      ),
      payload: route,
    );
  }

  static void _open(String? route) {
    if (route != null) {
      appRouter.push(route);
    }
  }
}
