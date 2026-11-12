import 'dart:async';
import 'dart:typed_data';

import 'package:firebase_core/firebase_core.dart';
import 'package:http/http.dart' as http;
import 'package:url_launcher/url_launcher.dart';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/painting.dart' show Color;
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import '../router.dart';
import '../config.dart';
import 'api_client.dart';

/// Legacy notification payloads are already displayed by Android.
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  if (message.notification != null ||
      message.data['rich_notification'] != '1') {
    return;
  }
  await Firebase.initializeApp();
  await PushNotifications.showMessage(message);
}

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
  static Future<void>? _localInitialization;
  static StreamSubscription<String>? _refreshSubscription;
  static final _renderVersions = <String, int>{};

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
        await _initializeLocal();

        FirebaseMessaging.onMessage.listen((message) {
          onMessage?.call();
          unawaited(showMessage(message, foreground: true));
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

      final launch = await _local.getNotificationAppLaunchDetails();
      if (launch?.didNotificationLaunchApp == true) {
        final response = launch!.notificationResponse;
        _handleResponse(response);
      }
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
      'return_case' when id('order_id') != null => '/orders/${id('order_id')}',
      'announcement' when id('store_slug') != null =>
        '/store/${Uri.encodeComponent(id('store_slug')!)}',
      // The server sends the link's path so product, cart and order links open
      // natively even when the website host differs from the API host.
      'announcement' when pathDestination(id('app_path')) != null =>
        pathDestination(id('app_path')),
      'announcement' => announcementDestination(id('url')),
      _ => null,
    };
  }

  /// Keep the app's brand in the header; contextual photos use the large icon.
  @visibleForTesting
  static String iconFor(String? smallIcon) => _icon;

  static Future<void> _initializeLocal() => _localInitialization ??= () async {
    final android = _local
        .resolvePlatformSpecificImplementation<
          AndroidFlutterLocalNotificationsPlugin
        >();
    for (final channel in [_chatChannel, _orderChannel, _updatesChannel]) {
      await android?.createNotificationChannel(channel);
    }
    await _local.initialize(
      const InitializationSettings(
        android: AndroidInitializationSettings(_icon),
      ),
      onDidReceiveNotificationResponse: _handleResponse,
    );
  }();

  static void _handleResponse(NotificationResponse? response) {
    var route = response?.payload;
    if (response?.actionId == 'reply' &&
        route?.startsWith('/conversations/') == true) {
      route = '$route?reply=1';
    }
    _open(route);
  }

  /// The in-app destination for an announcement link. Store and product pages
  /// open their native screens; other same-site pages stay full browser links.
  @visibleForTesting
  static String announcementDestination(String? url) {
    final uri = Uri.tryParse(url ?? '');
    final api = Uri.parse(AppConfig.apiBaseUrl);
    if (uri == null ||
        uri.scheme != 'https' ||
        uri.host != api.host ||
        uri.userInfo.isNotEmpty) {
      return '/';
    }

    return pathDestination(uri.path) ?? uri.toString();
  }

  /// The app screen for a JMMart web path, or null when the app has none.
  @visibleForTesting
  static String? pathDestination(String? path) {
    if (path == null || !path.startsWith('/')) return null;
    final segments = Uri(path: path).pathSegments
        .where((segment) => segment.isNotEmpty)
        .toList();

    if (segments.isEmpty) {
      return '/';
    }

    if (segments.length == 2 && segments[0] == 'stores') {
      return '/store/${Uri.encodeComponent(segments[1])}';
    }
    if (segments.length == 4 &&
        segments[0] == 'stores' &&
        segments[2] == 'products') {
      return '/product/${Uri.encodeComponent(segments[1])}/${Uri.encodeComponent(segments[3])}';
    }
    if (segments.length == 1 && segments[0] == 'cart') {
      return '/cart';
    }
    if (segments.length == 2 && segments[0] == 'orders') {
      return '/orders/${Uri.encodeComponent(segments[1])}';
    }

    return null;
  }

  @visibleForTesting
  static String? tagFor(Map<String, dynamic> data) => switch (data['type']) {
    'chat' when data['conversation_id'] != null =>
      'conversation-${data['conversation_id']}',
    'order' when data['order_id'] != null => 'order-${data['order_id']}',
    'return_case' when data['return_case_id'] != null =>
      'return-${data['return_case_id']}',
    'cart' => 'cart-reminder',
    _ => null,
  };

  static int _idFor(String value) {
    // Stable across the foreground and background Dart isolates.
    var hash = 0x811c9dc5;
    for (final unit in value.codeUnits) {
      hash = ((hash ^ unit) * 0x01000193) & 0x7fffffff;
    }
    return hash;
  }

  static String _title(RemoteMessage message) =>
      message.notification?.title ??
      message.data['title']?.toString() ??
      'JMMart';
  static String _body(RemoteMessage message) =>
      message.notification?.body ?? message.data['body']?.toString() ?? '';

  /// Uses the same renderer for foreground messages and upgraded background pushes.
  static Future<void> showMessage(
    RemoteMessage message, {
    bool foreground = false,
  }) async {
    final route = routeFor(message.data);
    if (foreground && route != null && appRouter.state.uri.path == route) {
      return;
    }
    try {
      final tag = tagFor(message.data);
      final version = tag == null ? 0 : (_renderVersions[tag] ?? 0) + 1;
      if (tag != null) {
        _renderVersions[tag] = version;
      }
      await _initializeLocal();
      final details = await detailsFor(message);
      if (tag != null && _renderVersions[tag] != version) {
        return;
      }
      await _local.show(
        _idFor(
          tag ?? message.messageId ?? '${_title(message)}:${message.sentTime}',
        ),
        _title(message),
        _body(message),
        NotificationDetails(android: details),
        payload: route,
      );
    } catch (error) {
      debugPrint('[Push] display failed: $error');
    }
  }

  @visibleForTesting
  static Future<AndroidNotificationDetails> detailsFor(
    RemoteMessage message, {
    Future<Uint8List?> Function(String? url)? imageLoader,
  }) async {
    final data = message.data;
    final isChat = data['type'] == 'chat';
    final channel = switch (data['type']) {
      'chat' => _chatChannel,
      'announcement' || 'cart' => _updatesChannel,
      _ => _orderChannel,
    };
    final load = imageLoader ?? downloadImage;
    final photoUrl = isChat
        ? data['avatar_url']?.toString()
        : data['image_url']?.toString() ??
              message.notification?.android?.imageUrl;
    Uint8List? photo;
    try {
      photo = await load(photoUrl);
    } catch (_) {
      /* Keep the text card when imagery fails. */
    }
    final AndroidBitmap<Object> largeIcon = photo == null
        ? const DrawableResourceAndroidBitmap('jmmart_avatar')
        : ByteArrayAndroidBitmap(photo);
    StyleInformation style = BigTextStyleInformation(_body(message));
    // MessagingStyle relocates the avatar on some Android versions. The standard
    // template keeps the brand header separate from the right-side thumbnail.
    if (photo != null &&
        (data['type'] == 'cart' || data['type'] == 'announcement')) {
      style = BigPictureStyleInformation(
        ByteArrayAndroidBitmap(photo),
        contentTitle: _title(message),
        summaryText: _body(message),
        hideExpandedLargeIcon: true,
      );
    }
    final route = routeFor(data);
    final label = switch (data['type']) {
      'chat' => 'Open chat',
      'order' || 'return_case' => 'View order',
      'cart' => 'View cart',
      'announcement' => switch (route) {
        final r? when r.startsWith('/store/') => 'Open store',
        final r? when r.startsWith('/product/') => 'View product',
        '/cart' => 'View cart',
        final r? when r.startsWith('/orders/') => 'View order',
        _ => 'Explore',
      },
      _ => null,
    };
    return AndroidNotificationDetails(
      channel.id,
      channel.name,
      channelDescription: channel.description,
      importance: channel.importance,
      priority: channel == _updatesChannel
          ? Priority.defaultPriority
          : Priority.high,
      icon: iconFor(
        message.notification?.android?.smallIcon ??
            data['small_icon']?.toString(),
      ),
      color: _accent,
      largeIcon: largeIcon,
      tag: tagFor(data),
      subText: data['subtitle']?.toString(),
      category: isChat
          ? AndroidNotificationCategory.message
          : AndroidNotificationCategory.status,
      visibility: NotificationVisibility.private,
      styleInformation: style,
      actions: route == null || label == null
          ? null
          : [
              if (isChat)
                const AndroidNotificationAction(
                  'reply',
                  'Reply',
                  showsUserInterface: true,
                ),
              AndroidNotificationAction(
                'open',
                label,
                showsUserInterface: true,
              ),
            ],
    );
  }

  /// Bounded HTTPS downloads cannot delay a notification indefinitely.
  @visibleForTesting
  static Future<Uint8List?> downloadImage(
    String? url, {
    http.Client? client,
  }) async {
    final uri = Uri.tryParse(url ?? '');
    if (uri == null ||
        uri.scheme != 'https' ||
        uri.host.isEmpty ||
        uri.userInfo.isNotEmpty) {
      return null;
    }
    final connection = client ?? http.Client();
    try {
      return await (() async {
        final response = await connection.send(
          http.Request('GET', uri)..followRedirects = false,
        );
        if (response.statusCode != 200 ||
            (response.contentLength ?? 0) > 1024 * 1024) {
          return null;
        }
        final type = response.headers['content-type']?.split(';').first;
        if (!const ['image/jpeg', 'image/png', 'image/webp'].contains(type)) {
          return null;
        }
        final bytes = BytesBuilder(copy: false);
        await for (final chunk in response.stream) {
          if (bytes.length + chunk.length > 1024 * 1024) {
            return null;
          }
          bytes.add(chunk);
        }
        return bytes.takeBytes();
      })().timeout(const Duration(seconds: 4));
    } catch (_) {
      return null;
    } finally {
      if (client == null) connection.close();
    }
  }

  static void _open(String? route) {
    if (route == null) return;
    if (route.startsWith('https://')) {
      unawaited(
        launchUrl(Uri.parse(route), mode: LaunchMode.externalApplication),
      );
    } else {
      appRouter.push(route);
    }
  }
}
