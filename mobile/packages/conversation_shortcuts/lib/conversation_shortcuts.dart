import 'dart:typed_data';

import 'package:flutter/services.dart';

/// Android shows a chat notification in the round-avatar conversation layout
/// only when it names a long-lived shortcut for that conversation (Android 11+).
class ConversationShortcuts {
  const ConversationShortcuts._();

  static const _channel = MethodChannel('jmmart/conversation_shortcuts');

  /// Creates or refreshes the shortcut [id] for the person [name], using
  /// [avatar] (PNG/JPEG bytes) as the round avatar, or the app icon without one.
  static Future<void> publish({
    required String id,
    required String name,
    Uint8List? avatar,
  }) => _channel.invokeMethod<void>('publish', {
    'id': id,
    'name': name,
    'avatar': avatar,
  });
}
