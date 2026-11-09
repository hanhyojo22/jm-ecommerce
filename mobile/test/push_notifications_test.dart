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
}
