import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:jmmart/src/core/push_notifications.dart';

void main() {
  test(
    'notification images pass through without Flutter image conversion',
    () async {
      final bytes = Uint8List.fromList([137, 80, 78, 71, 13, 10, 26, 10]);
      final client = MockClient(
        (_) async => http.Response.bytes(
          bytes,
          200,
          headers: {'content-type': 'image/png'},
        ),
      );
      addTearDown(client.close);
      expect(
        await PushNotifications.downloadImage(
          'https://example.com/avatar.png',
          client: client,
        ),
        bytes,
      );
    },
  );

  testWidgets(
    'chat cards use the conversation layout with the store as a round avatar',
    (tester) async {
      List<Object?>? shortcut;
      final details = await tester.runAsync(
        () => PushNotifications.detailsFor(
          const RemoteMessage(
            data: {
              'type': 'chat',
              'title': 'JM',
              'body': 'The navy option is available.',
              'conversation_id': 'c1',
              'avatar_url': 'https://example.com/store.png',
            },
          ),
          imageLoader: (_) async => Uint8List.fromList([1, 2, 3]),
          earlierMessages: (_) async => const [],
          publishShortcut: (id, name, avatar) async =>
              shortcut = [id, name, avatar?.toList()],
        ),
      );
      final style = details!.styleInformation as MessagingStyleInformation;
      final latest = style.messages!.single;
      expect(latest.text, 'The navy option is available.');
      expect(latest.person!.name, 'JM');
      expect((latest.person!.icon! as ByteArrayAndroidIcon).data, [1, 2, 3]);
      expect(details.largeIcon, isNull);
      expect(details.icon, '@drawable/ic_stat_notification');
      expect(details.actions!.map((action) => action.title), [
        'Reply',
        'Open chat',
      ]);
      expect(details.tag, 'conversation-c1');
      expect(details.shortcutId, 'conversation-c1');
      expect(shortcut, [
        'conversation-c1',
        'JM',
        [1, 2, 3],
      ]);
    },
  );

  testWidgets(
    'a new chat message stacks under the latest earlier ones, and no photo leaves the initial',
    (tester) async {
      const store = Person(name: 'JM');
      final earlier = [
        for (var i = 1; i <= 6; i++)
          Message('Earlier $i', DateTime(2026), store),
      ];
      String? askedTag;
      final details = await tester.runAsync(
        () => PushNotifications.detailsFor(
          const RemoteMessage(
            data: {
              'type': 'chat',
              'title': 'JM',
              'body': 'Newest',
              'conversation_id': 'c1',
            },
          ),
          imageLoader: (_) async => null,
          publishShortcut: (_, _, _) async {},
          earlierMessages: (tag) async {
            askedTag = tag;
            return earlier;
          },
        ),
      );
      final messages =
          (details!.styleInformation as MessagingStyleInformation).messages!;
      expect(askedTag, 'conversation-c1');
      expect(messages.map((m) => m.text), [
        'Earlier 3',
        'Earlier 4',
        'Earlier 5',
        'Earlier 6',
        'Newest',
      ]);
      expect(messages.last.person!.icon, isNull);
    },
  );

  testWidgets('order photos stay compact and updates share an order tag', (
    tester,
  ) async {
    final details = await tester.runAsync(
      () => PushNotifications.detailsFor(
        const RemoteMessage(
          data: {
            'type': 'order',
            'order_id': 'o1',
            'title': 'On its way',
            'image_url': 'https://example.com/product.png',
          },
        ),
        imageLoader: (_) async => Uint8List.fromList([1, 2, 3]),
      ),
    );
    expect(details!.largeIcon, isA<ByteArrayAndroidBitmap>());
    expect(details.styleInformation, isA<BigTextStyleInformation>());
    expect(details.actions!.single.title, 'View order');
    expect(details.tag, 'order-o1');
  });

  for (final type in ['cart', 'announcement']) {
    testWidgets(
      '$type photos expand and failed downloads retain text and action',
      (tester) async {
        final message = RemoteMessage(
          data: {
            'type': type,
            'title': 'Saved for you',
            'body': 'Explore your finds.',
            'image_url': 'https://example.com/product.png',
          },
        );
        final photo = await tester.runAsync(
          () => PushNotifications.detailsFor(
            message,
            imageLoader: (_) async => Uint8List.fromList([1]),
          ),
        );
        expect(photo!.styleInformation, isA<BigPictureStyleInformation>());
        final failed = await tester.runAsync(
          () => PushNotifications.detailsFor(
            message,
            imageLoader: (_) async => throw Exception('Offline'),
          ),
        );
        expect(failed!.styleInformation, isA<BigTextStyleInformation>());
        expect(failed.largeIcon, isNotNull);
        expect(
          failed.actions!.single.title,
          type == 'cart' ? 'View cart' : 'Explore',
        );
      },
    );
  }

  test(
    'image download rejects insecure URLs, redirects, oversize and non-images',
    () async {
      var requests = 0;
      final client = MockClient((request) async {
        requests++;
        return http.Response(
          '<html>Not an image</html>',
          200,
          headers: {'content-type': 'text/html'},
        );
      });
      addTearDown(client.close);
      expect(
        await PushNotifications.downloadImage(
          'http://example.com/photo.jpg',
          client: client,
        ),
        isNull,
      );
      expect(requests, 0);
      expect(
        await PushNotifications.downloadImage(
          'https://example.com/photo.jpg',
          client: client,
        ),
        isNull,
      );
      final redirect = MockClient(
        (_) async =>
            http.Response('', 302, headers: {'location': 'http://example.com'}),
      );
      addTearDown(redirect.close);
      expect(
        await PushNotifications.downloadImage(
          'https://example.com/photo.jpg',
          client: redirect,
        ),
        isNull,
      );
      final oversized = MockClient.streaming(
        (request, stream) async => http.StreamedResponse(
          const Stream.empty(),
          200,
          contentLength: 1024 * 1024 + 1,
        ),
      );
      addTearDown(oversized.close);
      expect(
        await PushNotifications.downloadImage(
          'https://example.com/photo.jpg',
          client: oversized,
        ),
        isNull,
      );
    },
  );

  test('offers accept only HTTPS links on the configured marketplace', () {
    expect(
      PushNotifications.announcementDestination('javascript:alert(1)'),
      '/',
    );
    expect(
      PushNotifications.announcementDestination('https://other-site.com'),
      '/',
    );
    expect(
      PushNotifications.announcementDestination(
        'https://jmmart-marketplace.onrender.com/products',
      ),
      'https://jmmart-marketplace.onrender.com/products',
    );
    expect(
      PushNotifications.announcementDestination(
        'https://jmmart-marketplace.onrender.com/stores/northwind',
      ),
      '/store/northwind',
    );
    expect(
      PushNotifications.announcementDestination(
        'https://jmmart-marketplace.onrender.com/stores/northwind/products/tote',
      ),
      '/product/northwind/tote',
    );
    expect(
      PushNotifications.announcementDestination(
        'https://jmmart-marketplace.onrender.com/cart',
      ),
      '/cart',
    );
    expect(
      PushNotifications.routeFor({'type': 'return_case', 'order_id': 'o1'}),
      '/orders/o1',
    );
  });

  testWidgets('store announcements offer a direct store action', (
    tester,
  ) async {
    final details = await tester.runAsync(
      () => PushNotifications.detailsFor(
        const RemoteMessage(
          data: {
            'type': 'announcement',
            'title': 'Fresh finds',
            'body': 'New arrivals are in.',
            'url': 'https://jmmart-marketplace.onrender.com/stores/northwind',
          },
        ),
        imageLoader: (_) async => null,
      ),
    );

    expect(details!.actions!.single.title, 'Open store');
  });

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
    expect(
      PushNotifications.routeFor({
        'type': 'announcement',
        'store_slug': 'northwind',
        'url': 'http://127.0.0.1:4173/stores/northwind',
      }),
      '/store/northwind',
    );
    expect(
      PushNotifications.routeFor({
        'type': 'announcement',
        'app_path': '/stores/northwind/products/canvas-tote',
        'url': 'http://127.0.0.1:4173/stores/northwind/products/canvas-tote',
      }),
      '/product/northwind/canvas-tote',
    );
    expect(
      PushNotifications.routeFor({
        'type': 'announcement',
        'app_path': '/cart',
        'url': 'http://127.0.0.1:4173/cart',
      }),
      '/cart',
    );
    expect(
      PushNotifications.routeFor({
        'type': 'announcement',
        'app_path': '/',
        'url': 'http://127.0.0.1:4173/',
      }),
      '/',
    );
    expect(PushNotifications.routeFor({'type': 'cart'}), '/cart');
    expect(PushNotifications.routeFor({'type': 'chat'}), isNull);
  });

  testWidgets('store metadata opens the store action without a trusted URL', (
    tester,
  ) async {
    final details = await tester.runAsync(
      () => PushNotifications.detailsFor(
        const RemoteMessage(
          data: {
            'type': 'announcement',
            'title': 'Fresh finds',
            'body': 'New arrivals are in.',
            'store_slug': 'northwind',
            'url': 'http://127.0.0.1:4173/stores/northwind',
          },
        ),
        imageLoader: (_) async => null,
      ),
    );

    expect(details!.actions!.single.title, 'Open store');
  });

  testWidgets('product announcements offer a direct product action', (
    tester,
  ) async {
    final details = await tester.runAsync(
      () => PushNotifications.detailsFor(
        const RemoteMessage(
          data: {
            'type': 'announcement',
            'title': 'Flash deal',
            'body': 'Canvas tote is 30% off.',
            'app_path': '/stores/northwind/products/canvas-tote',
            'url':
                'http://127.0.0.1:4173/stores/northwind/products/canvas-tote',
          },
        ),
        imageLoader: (_) async => null,
      ),
    );

    expect(details!.actions!.single.title, 'View product');
  });

  testWidgets('square photos are padded to 2:1 and wide banners kept', (
    tester,
  ) async {
    Future<Uint8List> png(int width, int height) async {
      final recorder = ui.PictureRecorder();
      ui.Canvas(recorder).drawRect(
        ui.Rect.fromLTWH(0, 0, width.toDouble(), height.toDouble()),
        ui.Paint()..color = const ui.Color(0xFF205BF4),
      );
      final image = await recorder.endRecording().toImage(width, height);
      final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
      return bytes!.buffer.asUint8List();
    }

    Future<List<int>> size(Uint8List bytes) async {
      final frame = await (await ui.instantiateImageCodec(bytes))
          .getNextFrame();
      return [frame.image.width, frame.image.height];
    }

    await tester.runAsync(() async {
      final square = await png(40, 40);
      expect(await size(await PushNotifications.padToWide(square)), [80, 40]);

      final banner = await png(120, 40);
      expect(await PushNotifications.padToWide(banner), same(banner));

      final junk = Uint8List.fromList([1, 2, 3]);
      expect(await PushNotifications.padToWide(junk), same(junk));
    });
  });

  testWidgets(
    'chat avatars are centre-cropped into a transparent-cornered circle',
    (tester) async {
      await tester.runAsync(() async {
        final recorder = ui.PictureRecorder();
        ui.Canvas(recorder).drawRect(
          const ui.Rect.fromLTWH(0, 0, 60, 40),
          ui.Paint()..color = const ui.Color(0xFF14665A),
        );
        final source = await (await recorder.endRecording().toImage(
          60,
          40,
        )).toByteData(format: ui.ImageByteFormat.png);

        final round = await PushNotifications.cropToCircle(
          source!.buffer.asUint8List(),
        );
        final image = (await (await ui.instantiateImageCodec(
          round,
        )).getNextFrame()).image;
        final pixels = (await image.toByteData())!;
        int alphaAt(int x, int y) =>
            pixels.getUint8((y * image.width + x) * 4 + 3);

        expect([image.width, image.height], [40, 40]);
        expect(alphaAt(0, 0), 0);
        expect(alphaAt(20, 20), 255);

        final junk = Uint8List.fromList([1, 2, 3]);
        expect(await PushNotifications.cropToCircle(junk), same(junk));
      });
    },
  );

  test('all push types retain the same JMMart brand icon', () {
    expect(
      PushNotifications.iconFor('ic_stat_shipped'),
      '@drawable/ic_stat_notification',
    );
    expect(PushNotifications.iconFor(null), '@drawable/ic_stat_notification');
    expect(
      PushNotifications.iconFor('../evil'),
      '@drawable/ic_stat_notification',
    );
  });
}
