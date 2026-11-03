import 'dart:async';
import 'dart:convert';

import 'package:web_socket_channel/web_socket_channel.dart';

enum ReverbState { disconnected, connecting, connected, failed }

class ReverbEvent {
  const ReverbEvent(this.event, this.data);

  final String event;
  final Map<String, dynamic> data;

  String? get conversationId => data['conversation_id'] as String?;

  String? get type => data['event'] as String?;
}

/// A tiny Pusher-protocol client for Laravel Reverb.
///
/// `pusher_channels_flutter` cannot point at a custom host on Android, so this
/// speaks the protocol directly and authenticates private channels through the
/// app's `/api/v1/broadcasting/auth` endpoint with the Clerk bearer token.
class ReverbClient {
  ReverbClient({
    required this.key,
    required this.host,
    required this.port,
    required this.useTls,
    required this.authorize,
  });

  final String key;
  final String host;
  final int port;
  final bool useTls;
  final Future<String> Function(String socketId, String channelName) authorize;

  WebSocketChannel? _channel;
  StreamSubscription<dynamic>? _subscription;
  final _events = StreamController<ReverbEvent>.broadcast();
  final _states = StreamController<ReverbState>.broadcast();
  final _connected = Completer<void>();
  final _subscribed = <String>{};
  String? _socketId;
  bool _disposed = false;

  Stream<ReverbEvent> get events => _events.stream;

  Stream<ReverbState> get states => _states.stream;

  bool get isConnected => _socketId != null;

  Future<void> connectAndSubscribe(String channelName) async {
    if (_channel == null) {
      await _connect();
    }

    if (!_connected.isCompleted) {
      await _connected.future.timeout(const Duration(seconds: 12));
    }

    await _subscribe(channelName);
  }

  Future<void> _connect() async {
    final scheme = useTls ? 'wss' : 'ws';
    final uri = Uri.parse(
      '$scheme://$host:$port/app/$key?protocol=7&client=flutter&version=1.0',
    );

    _states.add(ReverbState.connecting);

    final channel = WebSocketChannel.connect(uri);
    _channel = channel;
    _subscription = channel.stream.listen(
      _onMessage,
      onError: _fail,
      onDone: _onDone,
      cancelOnError: false,
    );

    await channel.ready;
  }

  void _onMessage(dynamic raw) {
    final decoded = jsonDecode('$raw');

    if (decoded is! Map) {
      return;
    }

    final event = '${decoded['event'] ?? ''}';
    final data = decoded['data'];

    if (event == 'pusher:connection_established') {
      final payload = data is String ? jsonDecode(data) : data;

      if (payload is Map) {
        _socketId = payload['socket_id'] as String?;
      }

      _states.add(ReverbState.connected);

      if (!_connected.isCompleted) {
        _connected.complete();
      }

      return;
    }

    if (event == 'pusher:ping') {
      _send({'event': 'pusher:pong', 'data': <String, dynamic>{}});

      return;
    }

    if (event == 'pusher:error') {
      _fail(data ?? 'Reverb returned an error.');

      return;
    }

    if (event.startsWith('pusher:') || event.startsWith('pusher_internal:')) {
      return;
    }

    final payload = data is String ? jsonDecode(data) : data;

    if (payload is Map) {
      _events.add(
        ReverbEvent(
          event,
          payload.map((key, value) => MapEntry('$key', value)),
        ),
      );
    }
  }

  Future<void> _subscribe(String channelName) async {
    final channel = 'private-$channelName';

    if (_subscribed.contains(channel)) {
      return;
    }

    final socketId = _socketId;

    if (socketId == null) {
      throw StateError('Reverb socket is not connected.');
    }

    final auth = await authorize(socketId, channel);

    if (auth.isEmpty) {
      throw StateError('Reverb channel authorization failed.');
    }

    _send({
      'event': 'pusher:subscribe',
      'data': {'auth': auth, 'channel': channel},
    });
    _subscribed.add(channel);
  }

  void _send(Map<String, dynamic> payload) {
    _channel?.sink.add(jsonEncode(payload));
  }

  void _fail(Object error) {
    _states.add(ReverbState.failed);

    if (!_connected.isCompleted) {
      _connected.completeError(error);
    }
  }

  void _onDone() {
    _states.add(ReverbState.disconnected);
    _socketId = null;
    _subscribed.clear();
  }

  Future<void> dispose() async {
    if (_disposed) {
      return;
    }

    _disposed = true;
    await _subscription?.cancel();
    await _channel?.sink.close();
    _channel = null;
    _socketId = null;
    _subscribed.clear();
    await _events.close();
    await _states.close();
  }
}
