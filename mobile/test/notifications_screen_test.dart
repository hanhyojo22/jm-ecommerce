import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/screens/notifications_screen.dart';
import 'package:jmmart/src/widgets/notification_bell_button.dart';

void main() {
  testWidgets(
    'bell counts order updates and opens a list without chat messages',
    (tester) async {
      final api = _NotificationApi();
      await tester.pumpWidget(
        ProviderScope(
          overrides: [apiClientProvider.overrideWithValue(api)],
          child: const MaterialApp(
            home: Scaffold(body: NotificationBellButton()),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.byTooltip('Notifications, 1 unread'), findsOneWidget);

      await tester.pumpWidget(
        ProviderScope(
          overrides: [apiClientProvider.overrideWithValue(api)],
          child: const MaterialApp(home: NotificationsScreen()),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Order confirmed'), findsOneWidget);
      expect(find.text('Chat message'), findsNothing);
      await tester.tap(find.text('Mark all read'));
      await tester.pumpAndSettle();
      expect(api.markedAllRead, isTrue);
    },
  );
}

class _NotificationApi extends ApiClient {
  _NotificationApi()
    : super(baseUrl: 'http://localhost', tokenProvider: () async => null);

  bool markedAllRead = false;

  @override
  Future<NotificationInbox> notifications() async => NotificationInbox(
    items: const [
      AppNotification(
        id: 'order-1',
        type: 'order',
        title: 'Order confirmed',
        body: 'Your order is confirmed.',
        createdAt: '2026-11-07T00:00:00Z',
      ),
    ],
    unreadCount: markedAllRead ? 0 : 1,
  );

  @override
  Future<void> markAllNotificationsRead() async {
    markedAllRead = true;
  }
}
