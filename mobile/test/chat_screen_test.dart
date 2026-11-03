import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/core/api_exception.dart';
import 'package:jmmart/src/core/reverb_client.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/providers/chat_providers.dart';
import 'package:jmmart/src/screens/chat_screen.dart';

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
}

Future<void> _pumpChat(WidgetTester tester, _FakeApiClient api) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(api),
        reverbProvider.overrideWithValue(_FakeReverbClient()),
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
}) {
  return ChatMessage(
    id: id,
    senderId: 'buyer-1',
    isMine: true,
    type: 'text',
    body: body,
    clientMessageId: clientMessageId,
    createdAt: DateTime.now().toUtc().toIso8601String(),
  );
}

class _FakeApiClient extends ApiClient {
  _FakeApiClient()
    : super(baseUrl: 'http://localhost', tokenProvider: () async => null);

  final List<Completer<ChatMessage>> sends = [];
  final List<String> clientMessageIds = [];
  List<ChatMessage> serverMessages = const [];
  String? lastClientMessageId;

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
