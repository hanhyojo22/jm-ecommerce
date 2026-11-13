import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/core/api_exception.dart';
import 'package:jmmart/src/core/attachment_picker.dart';
import 'package:jmmart/src/core/formatters.dart';
import 'package:jmmart/src/core/reverb_client.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/providers/chat_providers.dart';
import 'package:jmmart/src/screens/chat_screen.dart';
import 'package:jmmart/src/widgets/common.dart';
import 'package:jmmart/src/widgets/image_viewer.dart';

void main() {
  testWidgets('pending product context is sent with the first message', (
    tester,
  ) async {
    final api = _FakeApiClient();
    await _pumpChat(tester, api, productId: 'product-1');

    expect(find.text('Product inquiry'), findsOneWidget);
    await tester.enterText(find.byType(TextField), 'Is this available?');
    await tester.tap(find.byIcon(Icons.send_rounded));
    await tester.pump();

    expect(api.lastProductId, 'product-1');
    await _disposeChat(tester);
  });

  testWidgets('each inquiry shows its own preserved context card', (
    tester,
  ) async {
    final api = _FakeApiClient();
    api.serverMessages = [
      ChatMessage.fromJson({
        'id': 'one',
        'sender_id': 'buyer-1',
        'is_mine': true,
        'type': 'text',
        'body': 'Does this have 16 GB RAM?',
        'created_at': DateTime.now().toUtc().toIso8601String(),
        'context_snapshot': {
          'type': 'product',
          'title': 'Laptop Pro',
          'price': '42000.00',
        },
      }),
      ChatMessage.fromJson({
        'id': 'two',
        'sender_id': 'buyer-1',
        'is_mine': true,
        'type': 'text',
        'body': 'Will it work?',
        'created_at': DateTime.now().toUtc().toIso8601String(),
        'context_snapshot': {
          'type': 'product',
          'title': 'Wireless Mouse',
          'price': '850.00',
        },
      }),
    ];

    await _pumpChat(tester, api);

    expect(find.text('Laptop Pro'), findsOneWidget);
    expect(find.text('Wireless Mouse'), findsOneWidget);
    expect(find.text('₱42000.00'), findsOneWidget);
    expect(find.text('₱850.00'), findsOneWidget);
    await _disposeChat(tester);
  });

  testWidgets('an order card shows its items and opens the photo', (
    tester,
  ) async {
    final api = _FakeApiClient();
    api.serverMessages = [
      ChatMessage.fromJson({
        'id': 'order-msg',
        'sender_id': 'buyer-1',
        'is_mine': true,
        'type': 'text',
        'body': 'When will this ship?',
        'created_at': DateTime.now().toUtc().toIso8601String(),
        'context_snapshot': {
          'type': 'order',
          'number': 'JM-1042',
          'total': '199.00',
          'status': 'shipped',
          'order_id': 'order-1',
          'item_count': 5,
          'payment_method': 'cod',
          'city': 'Cebu',
          'image_url': 'http://localhost/media/rice-cooker.jpg',
          'items': [
            {
              'name': 'Rice cooker',
              'variant': 'White',
              'quantity': 1,
              'rating': 4.5,
              'rating_count': 12,
              'sold': 19,
              'free_shipping': true,
              'image_url': 'http://localhost/media/rice-cooker.jpg',
            },
            {'name': 'Spare lid', 'quantity': 2, 'image_url': null},
            {'name': 'Measuring cup', 'quantity': 1, 'image_url': null},
            {'name': 'Steamer tray', 'quantity': 1, 'image_url': null},
          ],
        },
      }),
    ];

    await _pumpChat(tester, api);

    expect(find.textContaining('JM-1042'), findsNothing);
    expect(find.text('Rice cooker'), findsOneWidget);
    expect(find.text('On the way'), findsOneWidget);
    expect(find.text('COD'), findsOneWidget);
    expect(find.text('FREE SHIPPING'), findsOneWidget);
    expect(find.text('4.5 (12) | 19 Sold'), findsOneWidget);
    expect(find.text('Cebu'), findsOneWidget);
    expect(find.text('+3'), findsOneWidget);
    expect(find.text('₱199.00'), findsOneWidget);

    await tester.tap(
      find.byKey(const ValueKey('open-order-item-image-order-msg')),
    );
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
    expect(find.byType(ImageViewer), findsOneWidget);
    await _disposeChat(tester);
  });

  testWidgets('long-press replies to a message and the reply shows a quote', (
    tester,
  ) async {
    final api = _FakeApiClient();
    api.serverMessages = [
      ChatMessage.fromJson({
        'id': 'seller-1',
        'sender_id': 'seller',
        'is_mine': false,
        'type': 'text',
        'body': 'Yes, two colors left.',
        'created_at': DateTime.now().toUtc().toIso8601String(),
      }),
      ChatMessage.fromJson({
        'id': 'buyer-2',
        'sender_id': 'buyer-1',
        'is_mine': true,
        'type': 'text',
        'body': 'Which colors?',
        'created_at': DateTime.now().toUtc().toIso8601String(),
        'reply_to': {
          'id': 'seller-0',
          'body': 'It ships tomorrow.',
          'context_label': 'Laptop Pro',
          'is_mine': false,
        },
      }),
    ];

    await _pumpChat(tester, api);

    expect(find.byKey(const ValueKey('reply-quote-buyer-2')), findsOneWidget);
    expect(find.text('Laptop Pro · It ships tomorrow.'), findsOneWidget);

    await tester.longPress(find.text('Yes, two colors left.'));
    await tester.pumpAndSettle();
    expect(find.text('Copy'), findsOneWidget);
    expect(find.byKey(const ValueKey('replying-to')), findsNothing);
    await tester.tap(find.text('Reply'));
    await tester.pumpAndSettle();
    expect(find.byKey(const ValueKey('replying-to')), findsOneWidget);

    await tester.enterText(find.byType(TextField), 'Black please');
    await tester.tap(find.byIcon(Icons.send_rounded));
    await tester.pump();

    expect(api.lastReplyToId, 'seller-1');
    expect(find.byKey(const ValueKey('replying-to')), findsNothing);
    await _disposeChat(tester);
  });

  testWidgets('long-press menu saves a photo and reports a store message', (
    tester,
  ) async {
    final api = _FakeApiClient();
    final saved = <String>[];
    api.serverMessages = [
      ChatMessage.fromJson({
        'id': 'store-photo',
        'sender_id': 'seller',
        'is_mine': false,
        'type': 'file',
        'body': '',
        'created_at': DateTime.now().toUtc().toIso8601String(),
        'attachments': [
          {
            'id': 'photo-1',
            'name': 'black.png',
            'mime_type': 'image/png',
            'url': 'http://localhost/media/photo-1',
            'is_image': true,
          },
        ],
      }),
      ChatMessage.fromJson({
        'id': 'store-text',
        'sender_id': 'seller',
        'is_mine': false,
        'type': 'text',
        'body': 'Pay me outside the app',
        'created_at': DateTime.now().toUtc().toIso8601String(),
      }),
      _message(id: 'mine', clientMessageId: null, body: 'Ok'),
    ];

    await _pumpChat(
      tester,
      api,
      imageSaver: (url, name) async => saved.add('$url|$name'),
    );

    await tester.longPress(find.byType(NetworkThumb));
    await tester.pumpAndSettle();
    expect(find.text('Copy'), findsNothing);
    await tester.tap(find.text('Save image'));
    await tester.pumpAndSettle();
    expect(saved, ['http://localhost/media/photo-1|black.png']);
    expect(find.text('Saved to gallery'), findsOneWidget);

    await tester.longPress(find.text('Pay me outside the app'));
    await tester.pumpAndSettle();
    expect(find.text('Save image'), findsNothing);
    await tester.tap(find.text('Report'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Spam'));
    await tester.pumpAndSettle();
    expect(api.reports, ['store-text:Spam']);

    // You can't report your own message.
    await tester.longPress(find.text('Ok'));
    await tester.pumpAndSettle();
    expect(find.text('Report'), findsNothing);
    await _disposeChat(tester);
  });

  testWidgets('tapping a chat photo opens a zoomable viewer that can save', (
    tester,
  ) async {
    final saved = <String>[];
    final api = _FakeApiClient();
    api.serverMessages = [
      ChatMessage.fromJson({
        'id': 'store-photo',
        'sender_id': 'seller',
        'is_mine': false,
        'type': 'file',
        'body': '',
        'created_at': DateTime.now().toUtc().toIso8601String(),
        'attachments': [
          {
            'id': 'photo-1',
            'name': 'receipt.png',
            'mime_type': 'image/png',
            'url': 'http://localhost/media/photo-1',
            'is_image': true,
          },
        ],
      }),
    ];

    await _pumpChat(
      tester,
      api,
      imageSaver: (url, name) async => saved.add('$url|$name'),
    );

    await tester.tap(find.byKey(const ValueKey('open-image-photo-1')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));

    final viewer = tester.widget<InteractiveViewer>(
      find.byKey(const ValueKey('image-viewer-zoom')),
    );
    expect(viewer.minScale, 1);
    expect(viewer.maxScale, greaterThan(1));
    expect(find.bySemanticsLabel('receipt.png'), findsOneWidget);

    await tester.tap(find.byTooltip('Save image'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
    expect(saved, ['http://localhost/media/photo-1|receipt.png']);

    await tester.tap(find.byTooltip('Close'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
    expect(find.byKey(const ValueKey('image-viewer-zoom')), findsNothing);
    expect(find.byKey(const ValueKey('open-image-photo-1')), findsOneWidget);

    await _disposeChat(tester);
  });

  testWidgets('swiping a message starts a reply, a short swipe does not', (
    tester,
  ) async {
    final api = _FakeApiClient();
    api.serverMessages = [
      ChatMessage.fromJson({
        'id': 'seller-1',
        'sender_id': 'seller',
        'is_mine': false,
        'type': 'text',
        'body': 'Yes, two colors left.',
        'created_at': DateTime.now().toUtc().toIso8601String(),
      }),
      ChatMessage.fromJson({
        'id': 'buyer-1',
        'sender_id': 'buyer-1',
        'is_mine': true,
        'type': 'text',
        'body': 'Which colors?',
        'created_at': DateTime.now().toUtc().toIso8601String(),
      }),
    ];

    await _pumpChat(tester, api);

    await tester.drag(find.text('Yes, two colors left.'), const Offset(20, 0));
    await tester.pumpAndSettle();
    expect(find.byKey(const ValueKey('replying-to')), findsNothing);

    await tester.drag(find.text('Yes, two colors left.'), const Offset(120, 0));
    await tester.pumpAndSettle();
    expect(find.byKey(const ValueKey('replying-to')), findsOneWidget);

    // Your own messages swipe the other way.
    await tester.drag(find.text('Which colors?'), const Offset(-120, 0));
    await tester.pumpAndSettle();
    expect(find.text('Replying to yourself'), findsOneWidget);
    await _disposeChat(tester);
  });

  testWidgets(
    'a message appears immediately and is replaced by the server row',
    (tester) async {
      final api = _FakeApiClient();

      await _pumpChat(tester, api);

      await tester.enterText(
        find.byType(TextField),
        'Is this still available?',
      );
      await tester.tap(find.byIcon(Icons.send_rounded));
      await tester.pump();

      expect(find.text('Is this still available?'), findsOneWidget);
      expect(find.text('Sending…'), findsOneWidget);
      expect(
        find.descendant(
          of: find.byKey(ValueKey('pending-bubble-${api.lastClientMessageId}')),
          matching: find.text('Sending…'),
        ),
        findsNothing,
      );
      expect(api.sendCount, 1);
      expect(_composerText(tester), isEmpty);

      final confirmed = _message(
        id: 'message-1',
        clientMessageId: api.lastClientMessageId,
        body: 'Is this still available?',
      );
      api.serverMessages = [confirmed];
      api.sends.single.complete(confirmed);

      await tester.pumpAndSettle();

      expect(find.text('Is this still available?'), findsOneWidget);
      expect(find.text('Sending…'), findsNothing);
      expect(find.text('Sent'), findsNothing);
      expect(
        find.descendant(
          of: find.byKey(const ValueKey('message-bubble-message-1')),
          matching: find.text(dateTime(confirmed.createdAt)),
        ),
        findsNothing,
      );

      await _disposeChat(tester);
    },
  );

  testWidgets('a failed send keeps the bubble and retries with the same id', (
    tester,
  ) async {
    final api = _FakeApiClient();

    await _pumpChat(tester, api);

    await tester.enterText(find.byType(TextField), 'Can you ship today?');
    await tester.tap(find.byIcon(Icons.send_rounded));
    await tester.pump();

    api.sends.single.completeError(
      const ApiException(code: 'network.error', message: 'No connection.'),
    );
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));

    expect(find.text('Not sent'), findsOneWidget);
    expect(find.text('Retry'), findsOneWidget);
    expect(find.text('Can you ship today?'), findsOneWidget);

    await tester.tap(find.text('Retry'));
    await tester.pump();

    expect(api.sendCount, 2);
    expect(api.clientMessageIds.toSet(), hasLength(1));
    expect(find.text('Sending…'), findsOneWidget);

    await _disposeChat(tester);
  });

  testWidgets(
    'chat images keep a stable cache key when the signed URL changes',
    (tester) async {
      final api = _FakeApiClient();
      api.serverMessages = [
        _message(
          id: 'message-1',
          clientMessageId: null,
          body: '',
          attachments: [
            ChatAttachment(
              id: 'attachment-1',
              name: 'maya.png',
              mimeType: 'image/png',
              url: 'http://localhost/media/attachment-1?signature=first',
              isImage: true,
            ),
          ],
        ),
      ];

      await _pumpChat(tester, api);

      final first = tester.widget<NetworkThumb>(find.byType(NetworkThumb));
      expect(first.cacheKey, 'chat-attachment-attachment-1');

      api.serverMessages = [
        _message(
          id: 'message-1',
          clientMessageId: null,
          body: '',
          attachments: [
            ChatAttachment(
              id: 'attachment-1',
              name: 'maya.png',
              mimeType: 'image/png',
              url: 'http://localhost/media/attachment-1?signature=second',
              isImage: true,
            ),
          ],
        ),
      ];

      await tester.pump(const Duration(seconds: 15));
      await tester.pump();

      final second = tester.widget<NetworkThumb>(find.byType(NetworkThumb));
      expect(second.url, contains('signature=second'));
      expect(second.cacheKey, 'chat-attachment-attachment-1');

      await _disposeChat(tester);
    },
  );

  testWidgets('a bubble keeps its image when a newer message shifts the list', (
    tester,
  ) async {
    final api = _FakeApiClient();
    api.serverMessages = [
      _imageMessage('message-1', 'attachment-1', 'gcash.png'),
    ];

    await _pumpChat(tester, api);

    final first = find.byWidgetPredicate(
      (widget) =>
          widget is NetworkThumb &&
          widget.url == 'http://localhost/media/attachment-1',
    );
    expect(first, findsOneWidget);
    final firstElement = tester.element(first);

    api.serverMessages = [
      ...api.serverMessages,
      _imageMessage('message-2', 'attachment-2', 'maya.png'),
    ];

    await tester.pump(const Duration(seconds: 15));
    await tester.pump();

    final second = find.byWidgetPredicate(
      (widget) =>
          widget is NetworkThumb &&
          widget.url == 'http://localhost/media/attachment-2',
    );

    expect(first, findsOneWidget);
    expect(second, findsOneWidget);
    // The element that paints the first image must stay with the first
    // message; the new bubble gets a fresh element and cannot reuse its state.
    expect(tester.element(first), same(firstElement));
    expect(tester.element(second), isNot(same(firstElement)));

    await _disposeChat(tester);
  });

  testWidgets('attachment-only messages use edge-to-edge bubble layouts', (
    tester,
  ) async {
    final api = _FakeApiClient();
    api.serverMessages = [
      _imageMessage('message-image', 'attachment-image', 'receipt.png'),
      _message(
        id: 'message-document',
        clientMessageId: null,
        body: '',
        attachments: const [
          ChatAttachment(
            id: 'attachment-document',
            name: 'invoice.pdf',
            mimeType: 'application/pdf',
            url: 'http://localhost/media/attachment-document',
            isImage: false,
          ),
        ],
      ),
    ];

    await _pumpChat(tester, api);

    final imageBubble = tester.widget<Container>(
      find.byKey(const ValueKey('attachment-bubble-message-image')),
    );
    final image = tester.widget<NetworkThumb>(find.byType(NetworkThumb));
    final documentBubble = tester.widget<Container>(
      find.byKey(const ValueKey('attachment-bubble-message-document')),
    );

    // Edge to edge: no frame padding, the bubble clips the photo, and the photo
    // keeps its own aspect ratio rather than a fixed letterboxed width.
    expect(imageBubble.padding, EdgeInsets.zero);
    expect(imageBubble.clipBehavior, Clip.antiAlias);
    expect(imageBubble.decoration, isA<BoxDecoration>());
    expect((imageBubble.decoration! as BoxDecoration).border, isNotNull);
    expect(image.radius, 0);
    expect(image.fit, BoxFit.contain);
    expect(image.width, isNull);
    expect(image.height, isNull);
    expect(documentBubble.padding, EdgeInsets.zero);
    final timestamp = find.text(dateTime(api.serverMessages.first.createdAt));
    expect(
      find.descendant(
        of: find.byKey(const ValueKey('attachment-bubble-message-image')),
        matching: timestamp,
      ),
      findsNothing,
    );
    expect(
      find.descendant(
        of: find.byKey(const ValueKey('attachment-bubble-message-document')),
        matching: timestamp,
      ),
      findsNothing,
    );
    expect(
      find.byKey(const ValueKey('document-attachment-attachment-document')),
      findsOneWidget,
    );
    expect(find.text('PDF document'), findsOneWidget);

    await _disposeChat(tester);
  });

  testWidgets('incoming photo shows an avatar without a sender name', (
    tester,
  ) async {
    final api = _FakeApiClient();
    api.serverMessages = [
      ChatMessage(
        id: 'incoming-photo',
        senderId: 'seller-1',
        isMine: false,
        type: 'file',
        body: '',
        createdAt: DateTime.now().toUtc().toIso8601String(),
        attachments: const [
          ChatAttachment(
            id: 'portrait',
            name: 'portrait.png',
            mimeType: 'image/png',
            url: 'http://localhost/media/portrait',
            isImage: true,
          ),
        ],
      ),
    ];

    await _pumpChat(tester, api);

    final bubble = find.byKey(
      const ValueKey('attachment-bubble-incoming-photo'),
    );
    final avatar = find.byKey(const ValueKey('sender-avatar-incoming-photo'));

    expect(find.text('James'), findsNothing);
    expect(avatar, findsOneWidget);
    expect(
      find.descendant(of: bubble, matching: find.byType(NetworkThumb)),
      findsOneWidget,
    );
    expect(
      tester.getTopLeft(avatar).dx,
      lessThan(tester.getTopLeft(bubble).dx),
    );
    expect(
      tester.getBottomLeft(avatar).dy,
      closeTo(tester.getBottomLeft(bubble).dy, 8),
    );

    await _disposeChat(tester);
  });

  testWidgets('incoming messages show an avatar on the last message only', (
    tester,
  ) async {
    final api = _FakeApiClient();
    final createdAt = DateTime.now().toUtc().toIso8601String();
    api.serverMessages = [
      for (final type in ['text', 'image', 'document'])
        ChatMessage(
          id: 'incoming-$type',
          senderId: 'seller-1',
          isMine: false,
          type: type == 'text' ? 'text' : 'file',
          body: type == 'text' ? 'Hello' : '',
          createdAt: createdAt,
          attachments: type == 'text'
              ? const []
              : [
                  ChatAttachment(
                    id: type,
                    name: type == 'image' ? 'photo.png' : 'file.pdf',
                    mimeType: type == 'image' ? 'image/png' : 'application/pdf',
                    url: 'http://localhost/media/$type',
                    isImage: type == 'image',
                  ),
                ],
        ),
    ];

    await _pumpChat(tester, api, width: 320, height: 700);

    final bubbles = [
      for (final type in ['text', 'image', 'document'])
        find.byKey(
          ValueKey(
            type == 'text'
                ? 'message-bubble-incoming-$type'
                : 'attachment-bubble-incoming-$type',
          ),
        ),
    ];

    for (final type in ['text', 'image']) {
      final avatar = find.byKey(ValueKey('sender-avatar-incoming-$type'));
      expect(avatar, findsNothing);
    }

    final avatar = find.byKey(
      const ValueKey('sender-avatar-incoming-document'),
    );
    expect(avatar, findsOneWidget);
    for (final bubble in bubbles) {
      expect(bubble, findsOneWidget);
      expect(tester.getTopLeft(bubble).dx, tester.getTopLeft(bubbles.first).dx);
    }
    expect(
      tester.getTopLeft(avatar).dx,
      lessThan(tester.getTopLeft(bubbles.last).dx),
    );
    expect(
      tester.getBottomLeft(avatar).dy,
      closeTo(tester.getBottomLeft(bubbles.last).dy, 8),
    );
    expect(find.text('James'), findsNothing);
    expect(tester.takeException(), isNull);

    await _disposeChat(tester);
  });

  testWidgets('a new sender group shows its own avatar', (tester) async {
    final api = _FakeApiClient();
    final createdAt = DateTime.now().toUtc().toIso8601String();
    api.serverMessages = [
      ChatMessage(
        id: 'seller-first',
        senderId: 'seller-1',
        isMine: false,
        type: 'text',
        body: 'Hello',
        createdAt: createdAt,
      ),
      ChatMessage(
        id: 'seller-second',
        senderId: 'seller-1',
        isMine: false,
        type: 'text',
        body: 'Still there?',
        createdAt: createdAt,
      ),
      ChatMessage(
        id: 'buyer-reply',
        senderId: 'buyer-1',
        isMine: true,
        type: 'text',
        body: 'Yes',
        createdAt: createdAt,
      ),
      ChatMessage(
        id: 'seller-again',
        senderId: 'seller-1',
        isMine: false,
        type: 'text',
        body: 'Great',
        createdAt: createdAt,
      ),
    ];

    await _pumpChat(tester, api);

    expect(
      find.byKey(const ValueKey('sender-avatar-seller-first')),
      findsNothing,
    );
    expect(
      find.byKey(const ValueKey('sender-avatar-seller-second')),
      findsOneWidget,
    );
    expect(
      find.byKey(const ValueKey('sender-avatar-seller-again')),
      findsOneWidget,
    );

    await _disposeChat(tester);
  });

  testWidgets('outgoing last message shows a blue read checkmark', (
    tester,
  ) async {
    final api = _FakeApiClient();
    api.serverMessages = [
      ChatMessage(
        id: 'outgoing-read',
        senderId: 'buyer-1',
        isMine: true,
        type: 'text',
        body: 'Hello',
        createdAt: DateTime.now().toUtc().toIso8601String(),
        receipt: 'Read',
      ),
    ];

    await _pumpChat(tester, api);

    final bubble = find.byKey(const ValueKey('message-outgoing-read'));
    expect(bubble, findsOneWidget);
    expect(
      find.descendant(
        of: bubble,
        matching: find.byKey(const ValueKey('receipt-indicator')),
      ),
      findsOneWidget,
    );
    await _disposeChat(tester);
  });

  testWidgets('outgoing last message shows a gray sent checkmark', (
    tester,
  ) async {
    final api = _FakeApiClient();
    api.serverMessages = [
      ChatMessage(
        id: 'outgoing-sent',
        senderId: 'buyer-1',
        isMine: true,
        type: 'text',
        body: 'Hello',
        createdAt: DateTime.now().toUtc().toIso8601String(),
        receipt: 'Sent',
      ),
    ];

    await _pumpChat(tester, api);

    final bubble = find.byKey(const ValueKey('message-outgoing-sent'));
    expect(
      find.descendant(
        of: bubble,
        matching: find.byKey(const ValueKey('receipt-indicator')),
      ),
      findsOneWidget,
    );

    await _disposeChat(tester);
  });

  testWidgets('incoming messages render without crashing', (tester) async {
    final api = _FakeApiClient();
    api.serverMessages = [
      ChatMessage(
        id: 'incoming-no-receipt',
        senderId: 'seller-1',
        isMine: false,
        type: 'text',
        body: 'Hello',
        createdAt: DateTime.now().toUtc().toIso8601String(),
        receipt: 'Read',
      ),
    ];

    await _pumpChat(tester, api);

    final bubble = find.byKey(const ValueKey('message-incoming-no-receipt'));
    expect(bubble, findsOneWidget);

    await _disposeChat(tester);
  });

  testWidgets('non-last outgoing message does not show a receipt checkmark', (
    tester,
  ) async {
    final api = _FakeApiClient();
    final createdAt = DateTime.now().toUtc().toIso8601String();
    api.serverMessages = [
      ChatMessage(
        id: 'outgoing-first',
        senderId: 'buyer-1',
        isMine: true,
        type: 'text',
        body: 'First',
        createdAt: createdAt,
        receipt: 'Read',
      ),
      ChatMessage(
        id: 'outgoing-last',
        senderId: 'buyer-1',
        isMine: true,
        type: 'text',
        body: 'Last',
        createdAt: createdAt,
        receipt: 'Read',
      ),
    ];

    await _pumpChat(tester, api);

    expect(
      find.byKey(const ValueKey('message-outgoing-first')),
      findsOneWidget,
    );
    expect(find.byKey(const ValueKey('message-outgoing-last')), findsOneWidget);

    // Only the last outgoing message should show the checkmark.
    expect(
      find.descendant(
        of: find.byKey(const ValueKey('message-outgoing-last')),
        matching: find.byKey(const ValueKey('receipt-indicator')),
      ),
      findsOneWidget,
    );

    await _disposeChat(tester);
  });

  testWidgets('own typing echo is ignored but the store typing shows', (
    tester,
  ) async {
    final api = _FakeApiClient();
    final events = StreamController<ReverbEvent>.broadcast();
    addTearDown(events.close);

    ReverbEvent typing(String userId) => ReverbEvent('conversation.typing', {
      'event': 'conversation.typing',
      'conversation_id': 'conversation-1',
      'user_id': userId,
      'typing': true,
    });

    await _pumpChat(tester, api, reverbEvents: events.stream);

    events.add(typing('buyer-1'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));
    expect(find.textContaining('is typing'), findsNothing);

    events.add(typing('seller-1'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));
    expect(find.textContaining('is typing'), findsOneWidget);

    await tester.pump(const Duration(seconds: 4));
    await _disposeChat(tester);
  });

  testWidgets('document attachments fit narrow screens with large text', (
    tester,
  ) async {
    final api = _FakeApiClient();
    api.serverMessages = [
      _message(
        id: 'message-document',
        clientMessageId: null,
        body: '',
        attachments: const [
          ChatAttachment(
            id: 'attachment-document',
            name: 'a-very-long-invoice-file-name.pdf',
            mimeType: 'application/pdf',
            url: 'http://localhost/media/attachment-document',
            isImage: false,
          ),
        ],
      ),
    ];

    await _pumpChat(tester, api, width: 320, height: 700, textScale: 2);

    expect(tester.takeException(), isNull);
    expect(find.text('PDF document'), findsOneWidget);

    await _disposeChat(tester);
  });

  testWidgets('a tall photo bubble hugs the photo instead of letterboxing it', (
    tester,
  ) async {
    final bytes = (await tester.runAsync(() => _solidPng(100, 200)))!;
    final api = _FakeApiClient();
    final picker = _FakeAttachmentPicker()
      ..image = PickedAttachment(name: 'screenshot.png', bytes: bytes);

    await _pumpChat(tester, api, picker: picker);
    await _openAttachmentMenu(tester, 'Photo');
    // Let the engine decode the image so it lays out at its real size.
    await tester.runAsync(
      () => Future<void>.delayed(const Duration(milliseconds: 200)),
    );
    await tester.pump();

    final clientId = api.lastAttachmentClientMessageId!;
    final bubble = tester.getSize(
      find.byKey(ValueKey('pending-attachment-bubble-$clientId')),
    );
    final image = tester.getSize(
      find.byKey(ValueKey('pending-image-$clientId')),
    );

    // The 1px border is the only space around the photo: no side bars.
    expect(bubble.width - image.width, lessThanOrEqualTo(2));
    expect(bubble.height - image.height, lessThanOrEqualTo(2));
    expect(image.width / image.height, closeTo(0.5, 0.02));

    await _disposeChat(tester);
  });

  testWidgets('a picked photo is uploaded and replaced by the server row', (
    tester,
  ) async {
    final api = _FakeApiClient();
    final picker = _FakeAttachmentPicker()
      ..image = PickedAttachment(name: 'gcash.png', bytes: _pngBytes());

    await _pumpChat(tester, api, picker: picker);
    await _openAttachmentMenu(tester, 'Photo');

    expect(picker.imagePicks, 1);
    expect(api.attachmentSends, hasLength(1));
    expect(api.lastAttachment?.name, 'gcash.png');

    final clientId = api.lastAttachmentClientMessageId!;
    expect(find.byKey(ValueKey('pending-image-$clientId')), findsOneWidget);
    final pendingBubble = tester.widget<Container>(
      find.byKey(ValueKey('pending-attachment-bubble-$clientId')),
    );
    expect(pendingBubble.padding, EdgeInsets.zero);
    expect((pendingBubble.decoration! as BoxDecoration).border, isNotNull);
    expect(find.text('Sending…'), findsOneWidget);

    final confirmed = _message(
      id: 'message-photo',
      clientMessageId: clientId,
      body: '',
      attachments: [
        ChatAttachment(
          id: 'attachment-photo',
          name: 'gcash.png',
          mimeType: 'image/png',
          url: 'http://localhost/media/attachment-photo',
          isImage: true,
        ),
      ],
    );

    api.serverMessages = [confirmed];
    api.attachmentSends.single.complete(confirmed);
    await tester.pumpAndSettle();

    expect(find.byKey(ValueKey('pending-image-$clientId')), findsNothing);
    expect(find.byType(NetworkThumb), findsOneWidget);
    expect(find.text('Sending…'), findsNothing);

    await _disposeChat(tester);
  });

  testWidgets('a picked PDF is uploaded as a document', (tester) async {
    final api = _FakeApiClient();
    final picker = _FakeAttachmentPicker()
      ..document = PickedAttachment(
        name: 'invoice.pdf',
        bytes: Uint8List.fromList(utf8.encode('%PDF-1.4 test %%EOF')),
      );

    await _pumpChat(tester, api, picker: picker);
    await _openAttachmentMenu(tester, 'Document');

    expect(picker.documentPicks, 1);
    expect(api.attachmentSends, hasLength(1));
    expect(api.lastAttachment?.mimeType, 'application/pdf');
    expect(find.text('invoice.pdf'), findsOneWidget);

    final clientId = api.lastAttachmentClientMessageId!;
    final confirmed = _message(
      id: 'message-pdf',
      clientMessageId: clientId,
      body: '',
      attachments: [
        ChatAttachment(
          id: 'attachment-pdf',
          name: 'invoice.pdf',
          mimeType: 'application/pdf',
          url: 'http://localhost/media/attachment-pdf',
          isImage: false,
        ),
      ],
    );

    api.serverMessages = [confirmed];
    api.attachmentSends.single.complete(confirmed);
    await tester.pumpAndSettle();

    expect(find.text('invoice.pdf'), findsOneWidget);
    expect(find.byType(NetworkThumb), findsNothing);

    await _disposeChat(tester);
  });

  testWidgets('an unsupported file is rejected before upload', (tester) async {
    final api = _FakeApiClient();
    final picker = _FakeAttachmentPicker()
      ..image = PickedAttachment(
        name: 'animation.gif',
        bytes: Uint8List.fromList([1, 2, 3]),
      );

    await _pumpChat(tester, api, picker: picker);
    await _openAttachmentMenu(tester, 'Photo');
    await tester.pumpAndSettle();

    expect(find.text('Choose a JPG, PNG, WebP, or PDF file.'), findsOneWidget);
    expect(api.attachmentSends, isEmpty);
    expect(find.byType(Image), findsNothing);

    await _disposeChat(tester);
  });

  testWidgets('a failed attachment upload retries with the same id', (
    tester,
  ) async {
    final api = _FakeApiClient();
    final picker = _FakeAttachmentPicker()
      ..image = PickedAttachment(name: 'gcash.png', bytes: _pngBytes());

    await _pumpChat(tester, api, picker: picker);
    await _openAttachmentMenu(tester, 'Photo');

    final clientId = api.lastAttachmentClientMessageId!;

    api.attachmentSends.single.completeError(
      const ApiException(code: 'network.error', message: 'No connection.'),
    );
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));

    expect(find.text('Not sent'), findsOneWidget);
    expect(find.text('Retry'), findsOneWidget);
    expect(find.byKey(ValueKey('pending-image-$clientId')), findsOneWidget);

    await tester.tap(find.text('Retry'));
    await tester.pump();

    expect(api.attachmentSends, hasLength(2));
    expect(api.lastAttachmentClientMessageId, clientId);
    expect(api.lastAttachment?.bytes, same(picker.image!.bytes));
    expect(find.text('Sending…'), findsOneWidget);

    await _disposeChat(tester);
  });
}

Future<void> _openAttachmentMenu(WidgetTester tester, String option) async {
  await tester.tap(find.byTooltip('Attach photo or document'));
  await tester.pumpAndSettle();
  await tester.tap(find.text(option));
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 300));
}

Future<Uint8List> _solidPng(int width, int height) async {
  final recorder = ui.PictureRecorder();
  Canvas(recorder).drawRect(
    Rect.fromLTWH(0, 0, width.toDouble(), height.toDouble()),
    Paint()..color = const Color(0xFF205BF4),
  );
  final image = await recorder.endRecording().toImage(width, height);
  final data = await image.toByteData(format: ui.ImageByteFormat.png);

  return data!.buffer.asUint8List();
}

Uint8List _pngBytes() => base64Decode(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUB'
  'AScY42YAAAAASUVORK5CYII=',
);

Future<void> _pumpChat(
  WidgetTester tester,
  _FakeApiClient api, {
  AttachmentPicker? picker,
  Future<void> Function(String url, String name)? imageSaver,
  Stream<ReverbEvent> reverbEvents = const Stream.empty(),
  double? width,
  double height = 844,
  double textScale = 1,
  String? productId,
}) async {
  if (width != null) {
    tester.view.physicalSize = Size(width, height);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
  }

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(api),
        reverbProvider.overrideWithValue(_FakeReverbClient(reverbEvents)),
        meProvider.overrideWith(
          (ref) async => const Account(
            id: 'buyer-1',
            name: 'Buyer',
            email: 'buyer@example.com',
            accountType: 'buyer',
            emailVerified: true,
            isPlatformAdministrator: false,
          ),
        ),
        if (picker != null) attachmentPickerProvider.overrideWithValue(picker),
        if (imageSaver != null)
          imageSaverProvider.overrideWithValue(imageSaver),
      ],
      child: MaterialApp(
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(context)
              .copyWith(textScaler: TextScaler.linear(textScale)),
          child: child!,
        ),
        home: ChatScreen(
          conversationId: 'conversation-1',
          productId: productId,
        ),
      ),
    ),
  );

  await tester.pumpAndSettle();
}

Future<void> _disposeChat(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await tester.pump();
}

String _composerText(WidgetTester tester) {
  final field = tester.widget<TextField>(find.byType(TextField));

  return field.controller?.text ?? '';
}

ChatMessage _message({
  required String id,
  required String? clientMessageId,
  required String body,
  List<ChatAttachment> attachments = const [],
}) {
  return ChatMessage(
    id: id,
    senderId: 'buyer-1',
    isMine: true,
    type: 'text',
    body: body,
    clientMessageId: clientMessageId,
    createdAt: DateTime.now().toUtc().toIso8601String(),
    attachments: attachments,
  );
}

ChatMessage _imageMessage(String id, String attachmentId, String name) {
  return _message(
    id: id,
    clientMessageId: null,
    body: '',
    attachments: [
      ChatAttachment(
        id: attachmentId,
        name: name,
        mimeType: 'image/png',
        url: 'http://localhost/media/$attachmentId',
        isImage: true,
      ),
    ],
  );
}

class _FakeApiClient extends ApiClient {
  _FakeApiClient()
    : super(baseUrl: 'http://localhost', tokenProvider: () async => null);

  final List<Completer<ChatMessage>> sends = [];
  final List<String> clientMessageIds = [];
  final List<String> reports = [];

  @override
  Future<void> reportMessage({
    required String conversationId,
    required String messageId,
    required String reason,
  }) async => reports.add('$messageId:$reason');
  final List<Completer<ChatMessage>> attachmentSends = [];
  final List<String> attachmentClientMessageIds = [];
  List<ChatMessage> serverMessages = const [];
  String? lastClientMessageId;
  String? lastProductId;
  String? lastReplyToId;
  String? lastAttachmentClientMessageId;
  PickedAttachment? lastAttachment;

  int get sendCount => sends.length;

  @override
  Future<List<Conversation>> conversations({String? search}) async => const [];

  @override
  Future<Conversation> conversation(String id) async =>
      Conversation(id: id, unreadCount: 0);

  @override
  Future<List<ChatMessage>> messages(String conversationId) async =>
      serverMessages;

  @override
  Future<void> markRead(String conversationId) async {}

  @override
  Future<void> typing(String conversationId) async {}

  @override
  Future<ChatMessage> sendMessage({
    required String conversationId,
    required String body,
    required String clientMessageId,
    String? productId,
    String? sellerOrderId,
    String? replyToId,
  }) {
    lastClientMessageId = clientMessageId;
    lastProductId = productId;
    lastReplyToId = replyToId;
    clientMessageIds.add(clientMessageId);

    final completer = Completer<ChatMessage>();
    sends.add(completer);

    return completer.future;
  }

  @override
  Future<ChatMessage> sendAttachment({
    required String conversationId,
    required PickedAttachment attachment,
    required String clientMessageId,
    String body = '',
    String? productId,
    String? sellerOrderId,
  }) {
    lastAttachment = attachment;
    lastAttachmentClientMessageId = clientMessageId;
    attachmentClientMessageIds.add(clientMessageId);

    final completer = Completer<ChatMessage>();
    attachmentSends.add(completer);

    return completer.future;
  }
}

class _FakeAttachmentPicker implements AttachmentPicker {
  PickedAttachment? image;
  PickedAttachment? document;
  int imagePicks = 0;
  int documentPicks = 0;

  @override
  Future<PickedAttachment?> pickImage() async {
    imagePicks++;

    return image;
  }

  @override
  Future<PickedAttachment?> pickDocument() async {
    documentPicks++;

    return document;
  }
}

class _FakeReverbClient extends ReverbClient {
  _FakeReverbClient([this.eventStream = const Stream.empty()])
    : super(
        key: 'test-key',
        host: 'localhost',
        port: 1,
        useTls: false,
        origin: 'http://127.0.0.1:8000',
        authorize: (socketId, channelName) async => '',
      );

  final Stream<ReverbEvent> eventStream;

  @override
  Stream<ReverbEvent> get events => eventStream;

  @override
  Stream<ReverbState> get states => const Stream.empty();

  @override
  Future<void> connectAndSubscribe(String channelName) async {}

  @override
  Future<void> dispose() async {}
}
