import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/push_notifications.dart';

void main() {
  test('push taps open the matching screen', () {
    expect(
      PushNotifications.routeFor({'type': 'chat', 'conversation_id': 'c1'}),
      '/conversations/c1',
    );
    expect(
      PushNotifications.routeFor({'type': 'order', 'order_id': 'o1'}),
      '/orders/o1',
    );
    expect(
      PushNotifications.routeFor({
        'type': 'seller_order',
        'seller_order_id': 's1',
      }),
      isNull,
    );
    expect(PushNotifications.routeFor({'type': 'cart'}), '/cart');
    expect(PushNotifications.routeFor({'type': 'chat'}), isNull);
  });

  test('foreground pushes use the icon the backend chose', () {
    expect(
      PushNotifications.iconFor('ic_stat_shipped'),
      '@drawable/ic_stat_shipped',
    );
    expect(PushNotifications.iconFor(null), '@drawable/ic_stat_notification');
    expect(
      PushNotifications.iconFor('../evil'),
      '@drawable/ic_stat_notification',
    );
  });
}
