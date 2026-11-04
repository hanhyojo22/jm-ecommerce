import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/core/api_exception.dart';
import 'package:jmmart/src/core/attachment_picker.dart';
import 'package:jmmart/src/core/reverb_client.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/providers/chat_providers.dart';
import 'package:jmmart/src/screens/chat_screen.dart';
import 'package:jmmart/src/widgets/common.dart';

void main() {
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

Uint8List _pngBytes() => base64Decode(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUB'
  'AScY42YAAAAASUVORK5CYII=',
);

Future<void> _pumpChat(
  WidgetTester tester,
  _FakeApiClient api, {
  AttachmentPicker? picker,
}) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(api),
        reverbProvider.overrideWithValue(_FakeReverbClient()),
        if (picker != null) attachmentPickerProvider.overrideWithValue(picker),
      ],
      child: const MaterialApp(
        home: ChatScreen(conversationId: 'conversation-1'),
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
  final List<Completer<ChatMessage>> attachmentSends = [];
  final List<String> attachmentClientMessageIds = [];
  List<ChatMessage> serverMessages = const [];
  String? lastClientMessageId;
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
  }) {
    lastClientMessageId = clientMessageId;
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
  _FakeReverbClient()
    : super(
        key: 'test-key',
        host: 'localhost',
        port: 1,
        useTls: false,
        origin: 'http://127.0.0.1:8000',
        authorize: (socketId, channelName) async => '',
      );

  @override
  Stream<ReverbEvent> get events => const Stream.empty();

  @override
  Stream<ReverbState> get states => const Stream.empty();

  @override
  Future<void> connectAndSubscribe(String channelName) async {}

  @override
  Future<void> dispose() async {}
}
