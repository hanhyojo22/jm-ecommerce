import 'dart:async';
import 'dart:convert';

import 'package:web_socket_channel/io.dart';
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
    required this.origin,
    required this.authorize,
  });

  final String key;
  final String host;
  final int port;
  final bool useTls;
  final String origin;
  final Future<String> Function(String socketId, String channelName) authorize;

  WebSocketChannel? _channel;
  StreamSubscription<dynamic>? _subscription;
  Completer<void>? _connected;
  final _events = StreamController<ReverbEvent>.broadcast();
  final _states = StreamController<ReverbState>.broadcast();
  final _subscribed = <String>{};
  String? _socketId;
  bool _disposed = false;

  Stream<ReverbEvent> get events => _events.stream;

  Stream<ReverbState> get states => _states.stream;

  bool get isConnected => _socketId != null && _channel != null;

  Future<void> connectAndSubscribe(String channelName) async {
    if (_disposed) {
      throw StateError('Reverb client is disposed.');
    }

    if (_channel == null) {
      await _connect();
    }

    final connected = _connected;

    if (connected != null && !connected.isCompleted) {
      await connected.future.timeout(const Duration(seconds: 12));
    }

    await _subscribe(channelName);
  }

  Future<void> _connect() async {
    final scheme = useTls ? 'wss' : 'ws';
    final uri = Uri.parse(
      '$scheme://$host:$port/app/$key?protocol=7&client=flutter&version=1.0',
    );
    final connected = Completer<void>();
    _connected = connected;

    _states.add(ReverbState.connecting);

    final channel = IOWebSocketChannel.connect(
      uri,
      headers: {'Origin': origin},
      connectTimeout: const Duration(seconds: 10),
    );
    _channel = channel;
    _subscription = channel.stream.listen(
      _onMessage,
      onError: _fail,
      onDone: _onDone,
      cancelOnError: false,
    );

    try {
      await channel.ready;
    } catch (error) {
      _resetConnection(close: true);

      if (!connected.isCompleted) {
        connected.completeError(error);
      }

      _states.add(ReverbState.failed);
      rethrow;
    }
  }

  void _onMessage(dynamic raw) {
    if (_disposed) {
      return;
    }

    final decoded = _decode(raw);

    if (decoded == null) {
      return;
    }

    final event = '${decoded['event'] ?? ''}';
    final payload = _decode(decoded['data']);

    if (event == 'pusher:connection_established') {
      if (payload != null) {
        _socketId = payload['socket_id'] as String?;
      }

      if (_socketId == null) {
        _fail('Reverb did not return a socket id.');

        return;
      }

      _states.add(ReverbState.connected);

      final connected = _connected;

      if (connected != null && !connected.isCompleted) {
        connected.complete();
      }

      return;
    }

    if (event == 'pusher:ping') {
      _send({'event': 'pusher:pong', 'data': <String, dynamic>{}});

      return;
    }

    if (event == 'pusher:error') {
      _fail(payload?['message'] ?? 'Reverb returned an error.');

      return;
    }

    if (event.startsWith('pusher:') || event.startsWith('pusher_internal:')) {
      return;
    }

    if (payload != null) {
      _events.add(ReverbEvent(event, payload));
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
    if (_disposed || _channel == null) {
      return;
    }

    _channel?.sink.add(jsonEncode(payload));
  }

  void _fail(Object error) {
    if (_disposed) {
      return;
    }

    _states.add(ReverbState.failed);

    final connected = _connected;

    if (connected != null && !connected.isCompleted) {
      connected.completeError(error);
    }

    _resetConnection(close: true);
  }

  void _onDone() {
    if (_disposed) {
      return;
    }

    _states.add(ReverbState.disconnected);
    _resetConnection();

    final connected = _connected;

    if (connected != null && !connected.isCompleted) {
      connected.completeError(StateError('Reverb socket closed.'));
    }
  }

  void _resetConnection({bool close = false}) {
    final channel = _channel;
    _channel = null;
    _subscription = null;
    _socketId = null;
    _subscribed.clear();

    if (close) {
      unawaited(channel?.sink.close());
    }
  }

  Map<String, dynamic>? _decode(dynamic raw) {
    try {
      final decoded = raw is String ? jsonDecode(raw) : raw;

      if (decoded is Map) {
        return decoded.map((key, value) => MapEntry('$key', value));
      }
    } catch (_) {
      // A malformed frame should not tear down the socket.
    }

    return null;
  }

  Future<void> dispose() async {
    if (_disposed) {
      return;
    }

    final subscription = _subscription;
    _disposed = true;
    _resetConnection(close: true);
    await subscription?.cancel();
    await _events.close();
    await _states.close();
  }
}
