import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:uuid/uuid.dart';

import '../core/api_exception.dart';
import '../core/formatters.dart';
import '../core/reverb_client.dart';
import '../models/models.dart';
import '../providers/auth_providers.dart';
import '../providers/chat_providers.dart';
import '../widgets/common.dart';

class ChatScreen extends ConsumerStatefulWidget {
  const ChatScreen({super.key, required this.conversationId});

  final String conversationId;

  @override
  ConsumerState<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends ConsumerState<ChatScreen> {
  final _composer = TextEditingController();

  late final ReverbClient _reverb;
  StreamSubscription<ReverbEvent>? _events;
  StreamSubscription<ReverbState>? _states;
  Timer? _poll;
  Timer? _typingTimer;
  bool _typing = false;
  bool _sending = false;
  bool _markingRead = false;
  bool _live = false;
  DateTime? _lastTypingSent;

  @override
  void initState() {
    super.initState();
    _reverb = ref.read(reverbProvider);
    _events = _reverb.events.listen(_onReverbEvent);
    _states = _reverb.states.listen((state) {
      if (mounted) {
        setState(() => _live = state == ReverbState.connected);
      }
    });

    unawaited(_connectReverb());
    _poll = Timer.periodic(const Duration(seconds: 15), (_) => _refresh());

    WidgetsBinding.instance.addPostFrameCallback((_) => _markRead());
  }

  @override
  void dispose() {
    _poll?.cancel();
    _typingTimer?.cancel();
    _events?.cancel();
    _states?.cancel();
    _composer.dispose();
    super.dispose();
  }

  Future<void> _connectReverb() async {
    try {
      await _reverb.connectAndSubscribe(
        'conversation.${widget.conversationId}',
      );
    } catch (_) {
      // REST polling keeps the thread usable when the socket is unavailable.
    }
  }

  void _onReverbEvent(ReverbEvent event) {
    if (event.type == 'message.created' || event.event == 'message.created') {
      _refresh();
      return;
    }

    if (event.type == 'conversation.typing') {
      final userId = event.data['user_id'];
      final me = ref.read(meProvider).value?.id;

      if (userId != null && userId == me) {
        return;
      }

      _typingTimer?.cancel();
      setState(() => _typing = event.data['typing'] == true);

      if (event.data['typing'] == true) {
        _typingTimer = Timer(const Duration(seconds: 3), () {
          if (mounted) {
            setState(() => _typing = false);
          }
        });
      }
    }
  }

  void _refresh() {
    ref.invalidate(messagesProvider(widget.conversationId));
    ref.invalidate(conversationsProvider);
  }

  Future<void> _markRead() async {
    if (_markingRead) {
      return;
    }

    _markingRead = true;

    try {
      await ref.read(apiClientProvider).markRead(widget.conversationId);
      ref.invalidate(conversationsProvider);
    } catch (_) {
      // A read receipt is not worth blocking the thread for.
    } finally {
      _markingRead = false;
    }
  }

  void _onComposerChanged(String value) {
    if (value.trim().isEmpty) {
      return;
    }

    final now = DateTime.now();

    if (_lastTypingSent != null &&
        now.difference(_lastTypingSent!) < const Duration(seconds: 2)) {
      return;
    }

    _lastTypingSent = now;
    unawaited(
      ref
          .read(apiClientProvider)
          .typing(widget.conversationId)
          .catchError((_) {}),
    );
  }

  Future<void> _send() async {
    final body = _composer.text.trim();

    if (body.isEmpty || _sending) {
      return;
    }

    setState(() => _sending = true);

    try {
      await ref
          .read(apiClientProvider)
          .sendMessage(
            conversationId: widget.conversationId,
            body: body,
            clientMessageId: const Uuid().v4(),
          );
      _composer.clear();
      _refresh();
      await _markRead();
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.friendly)));
      }
    } finally {
      if (mounted) {
        setState(() => _sending = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    // Keep the auto-disposed client alive for the lifetime of this screen.
    ref.watch(reverbProvider);

    final messages = ref.watch(messagesProvider(widget.conversationId));
    final conversation = ref
        .watch(conversationProvider(widget.conversationId))
        .value;
    final theme = Theme.of(context);

    ref.listen<AsyncValue<List<ChatMessage>>>(
      messagesProvider(widget.conversationId),
      (previous, next) {
        if (next.hasValue && next.value!.isNotEmpty) {
          WidgetsBinding.instance.addPostFrameCallback((_) => _markRead());
        }
      },
    );

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(conversation?.store?.name ?? 'Chat'),
            if (conversation?.productName != null ||
                conversation?.orderNumber != null)
              Text(
                conversation?.productName ??
                    'Order ${conversation?.orderNumber}',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
          ],
        ),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 14),
            child: Tooltip(
              message: _live
                  ? 'Live updates connected'
                  : 'Live updates reconnecting (using refresh)',
              child: Icon(
                Icons.circle,
                size: 10,
                color: _live
                    ? const Color(0xFF2F855A)
                    : theme.colorScheme.outline,
              ),
            ),
          ),
        ],
      ),
      body: Column(
        children: [
          Expanded(child: _body(messages, theme)),
          if (_typing)
            Padding(
              padding: const EdgeInsets.fromLTRB(18, 0, 18, 4),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  '${conversation?.store?.name ?? 'Store'} is typing…',
                  style: theme.textTheme.bodySmall?.copyWith(
                    fontStyle: FontStyle.italic,
                  ),
                ),
              ),
            ),
          SafeArea(
            top: false,
            child: Container(
              padding: const EdgeInsets.fromLTRB(12, 8, 8, 8),
              decoration: BoxDecoration(
                color: Colors.white,
                border: Border(
                  top: BorderSide(color: theme.colorScheme.outlineVariant),
                ),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Expanded(
                    child: TextField(
                      controller: _composer,
                      onChanged: _onComposerChanged,
                      minLines: 1,
                      maxLines: 4,
                      textCapitalization: TextCapitalization.sentences,
                      decoration: const InputDecoration(
                        hintText: 'Write a message',
                        contentPadding: EdgeInsets.symmetric(
                          horizontal: 14,
                          vertical: 12,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 6),
                  IconButton.filled(
                    onPressed: _sending ? null : _send,
                    icon: _sending
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.send_rounded),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _body(AsyncValue<List<ChatMessage>> messages, ThemeData theme) {
    final data = messages.value;

    if (data == null) {
      if (messages.hasError) {
        return ErrorView(
          error: messages.error!,
          onRetry: () =>
              ref.invalidate(messagesProvider(widget.conversationId)),
        );
      }

      return const Center(child: CircularProgressIndicator());
    }

    if (data.isEmpty) {
      return const EmptyState(
        icon: Icons.waving_hand_outlined,
        title: 'Say hello',
        message: 'Ask about the product, the delivery, or anything else.',
      );
    }

    return ListView.builder(
      reverse: true,
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 10),
      itemCount: data.length,
      itemBuilder: (context, index) {
        final message = data[data.length - 1 - index];

        return _Bubble(message: message);
      },
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.message});

  final ChatMessage message;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final mine = message.isMine;
    final background = mine ? theme.colorScheme.primary : Colors.white;
    final foreground = mine
        ? theme.colorScheme.onPrimary
        : theme.colorScheme.onSurface;

    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        constraints: BoxConstraints(
          maxWidth: MediaQuery.of(context).size.width * 0.78,
        ),
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 9),
        decoration: BoxDecoration(
          color: background,
          borderRadius: BorderRadius.only(
            topLeft: const Radius.circular(16),
            topRight: const Radius.circular(16),
            bottomLeft: Radius.circular(mine ? 16 : 4),
            bottomRight: Radius.circular(mine ? 4 : 16),
          ),
          border: mine
              ? null
              : Border.all(color: theme.colorScheme.outlineVariant),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (!mine && message.senderName != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 4),
                child: Text(
                  message.senderName!,
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: theme.colorScheme.primary,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            if (message.body.trim().isNotEmpty)
              Text(
                message.body,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: foreground,
                  height: 1.4,
                ),
              ),
            ...message.attachments.map(
              (attachment) => Padding(
                padding: const EdgeInsets.only(top: 6),
                child: attachment.isImage && attachment.url != null
                    ? NetworkThumb(
                        url: attachment.url,
                        width: 190,
                        height: 150,
                        radius: 10,
                      )
                    : InkWell(
                        onTap: attachment.url == null
                            ? null
                            : () => launchUrl(
                                Uri.parse(attachment.url!),
                                mode: LaunchMode.externalApplication,
                              ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(
                              Icons.attach_file_rounded,
                              size: 18,
                              color: foreground,
                            ),
                            const SizedBox(width: 6),
                            Flexible(
                              child: Text(
                                attachment.name,
                                overflow: TextOverflow.ellipsis,
                                style: theme.textTheme.bodySmall?.copyWith(
                                  color: foreground,
                                  decoration: TextDecoration.underline,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
              ),
            ),
            const SizedBox(height: 3),
            Text(
              dateTime(message.createdAt),
              style: theme.textTheme.labelSmall?.copyWith(
                color: foreground.withValues(alpha: 0.65),
                fontSize: 10,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
