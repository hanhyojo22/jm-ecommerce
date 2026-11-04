import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:uuid/uuid.dart';

import '../core/api_exception.dart';
import '../core/attachment_picker.dart';
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
  final _scrollController = ScrollController();
  final List<_PendingMessage> _pending = [];

  late final ReverbClient _reverb;
  StreamSubscription<ReverbEvent>? _events;
  StreamSubscription<ReverbState>? _states;
  Timer? _poll;
  Timer? _typingTimer;
  Timer? _reconnectTimer;
  bool _typing = false;
  bool _markingRead = false;
  bool _pickingAttachment = false;
  bool _live = false;
  DateTime? _lastTypingSent;

  @override
  void initState() {
    super.initState();
    _reverb = ref.read(reverbProvider);
    _events = _reverb.events.listen(_onReverbEvent);
    _states = _reverb.states.listen(_onReverbState);

    unawaited(_connectReverb());
    _poll = Timer.periodic(const Duration(seconds: 15), (_) => _refresh());

    WidgetsBinding.instance.addPostFrameCallback((_) => _markRead());
  }

  @override
  void dispose() {
    _poll?.cancel();
    _typingTimer?.cancel();
    _reconnectTimer?.cancel();
    _events?.cancel();
    _states?.cancel();
    _composer.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  Future<void> _connectReverb() async {
    try {
      await _reverb.connectAndSubscribe(
        'conversation.${widget.conversationId}',
      );
    } catch (_) {
      // REST polling keeps the thread usable when the socket is unavailable.
      _scheduleReconnect();
    }
  }

  void _onReverbState(ReverbState state) {
    if (!mounted) {
      return;
    }

    setState(() => _live = state == ReverbState.connected);

    if (state == ReverbState.connected) {
      _reconnectTimer?.cancel();
      return;
    }

    if (state == ReverbState.disconnected || state == ReverbState.failed) {
      _scheduleReconnect();
    }
  }

  void _scheduleReconnect() {
    if (!mounted || _reconnectTimer?.isActive == true) {
      return;
    }

    _reconnectTimer = Timer(const Duration(seconds: 5), () {
      if (mounted) {
        unawaited(_connectReverb());
      }
    });
  }

  void _onReverbEvent(ReverbEvent event) {
    if (!mounted) {
      return;
    }

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
    if (!mounted) {
      return;
    }

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

      if (mounted) {
        ref.invalidate(conversationsProvider);
      }
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

    if (body.isEmpty) {
      return;
    }

    final item = _PendingMessage(
      clientId: const Uuid().v4(),
      body: body,
      createdAt: DateTime.now(),
    );

    setState(() => _pending.add(item));
    _composer.clear();
    _scrollToLatest();
    await _sendPending(item);
  }

  Future<void> _showAttachmentSheet() async {
    if (_pickingAttachment) {
      return;
    }

    final kind = await showModalBottomSheet<_AttachmentKind>(
      context: context,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Photo'),
              subtitle: const Text('JPG, PNG, or WebP up to 5 MB'),
              onTap: () => Navigator.pop(context, _AttachmentKind.photo),
            ),
            ListTile(
              leading: const Icon(Icons.picture_as_pdf_outlined),
              title: const Text('Document'),
              subtitle: const Text('PDF up to 5 MB'),
              onTap: () => Navigator.pop(context, _AttachmentKind.document),
            ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );

    if (kind == null || !mounted) {
      return;
    }

    await _pickAttachment(kind);
  }

  Future<void> _pickAttachment(_AttachmentKind kind) async {
    setState(() => _pickingAttachment = true);

    PickedAttachment? attachment;

    try {
      final picker = ref.read(attachmentPickerProvider);
      attachment = kind == _AttachmentKind.photo
          ? await picker.pickImage()
          : await picker.pickDocument();
    } on AttachmentTooLargeException {
      _showAttachmentError('Choose a file smaller than 5 MB.');
      return;
    } catch (_) {
      _showAttachmentError('Could not open the file picker. Please try again.');
      return;
    } finally {
      if (mounted) {
        setState(() => _pickingAttachment = false);
      }
    }

    if (attachment == null || !mounted) {
      return;
    }

    final problem = _attachmentProblem(attachment);

    if (problem != null) {
      _showAttachmentError(problem);
      return;
    }

    final item = _PendingMessage(
      clientId: const Uuid().v4(),
      body: '',
      createdAt: DateTime.now(),
      attachment: attachment,
    );

    setState(() => _pending.add(item));
    _scrollToLatest();
    await _sendPending(item);
  }

  String? _attachmentProblem(PickedAttachment attachment) {
    const allowedExtensions = {'jpg', 'jpeg', 'png', 'webp', 'pdf'};

    if (!allowedExtensions.contains(attachment.extension)) {
      return 'Choose a JPG, PNG, WebP, or PDF file.';
    }

    if (attachment.bytes.length > maxChatAttachmentBytes) {
      return 'Choose a file smaller than 5 MB.';
    }

    return null;
  }

  void _showAttachmentError(String message) {
    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _sendPending(_PendingMessage item) async {
    if (!mounted ||
        item.status == _PendingStatus.sending ||
        item.status == _PendingStatus.sent) {
      return;
    }

    setState(() => item.status = _PendingStatus.sending);

    try {
      final api = ref.read(apiClientProvider);
      final attachment = item.attachment;

      if (attachment == null) {
        await api.sendMessage(
          conversationId: widget.conversationId,
          body: item.body,
          clientMessageId: item.clientId,
        );
      } else {
        await api.sendAttachment(
          conversationId: widget.conversationId,
          attachment: attachment,
          clientMessageId: item.clientId,
        );
      }

      if (!mounted) {
        return;
      }

      setState(() => item.status = _PendingStatus.sent);
      ref.invalidate(conversationsProvider);

      try {
        final rows = await ref.refresh(
          messagesProvider(widget.conversationId).future,
        );

        if (mounted) {
          _reconcilePending(rows);
        }
      } catch (_) {
        // The sent bubble stays until the next refresh succeeds.
      }

      await _markRead();
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }

      setState(() => item.status = _PendingStatus.failed);
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(error.friendly)));
    } catch (_) {
      if (mounted) {
        setState(() => item.status = _PendingStatus.failed);
      }
    }
  }

  void _reconcilePending(List<ChatMessage> messages) {
    final confirmed = messages
        .map((message) => message.clientMessageId)
        .whereType<String>()
        .toSet();

    if (!_pending.any((item) => confirmed.contains(item.clientId))) {
      return;
    }

    setState(
      () => _pending.removeWhere((item) => confirmed.contains(item.clientId)),
    );
  }

  void _scrollToLatest() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted || !_scrollController.hasClients) {
        return;
      }

      _scrollController.animateTo(
        0,
        duration: const Duration(milliseconds: 220),
        curve: Curves.easeOut,
      );
    });
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
        if (next.hasValue) {
          _reconcilePending(next.value!);

          if (next.value!.isNotEmpty) {
            WidgetsBinding.instance.addPostFrameCallback((_) => _markRead());
          }
        }
      },
    );

    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            if (conversation?.store != null) ...[
              StoreAvatar(
                name: conversation!.store!.name,
                logoUrl: conversation.store!.logoUrl,
                radius: 18,
              ),
              const SizedBox(width: 10),
            ],
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(conversation?.store?.name ?? 'Chat'),
                  if (conversation?.productName != null ||
                      conversation?.orderNumber != null)
                    Text(
                      conversation?.productName ??
                          'Order ${conversation?.orderNumber}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                ],
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
              padding: const EdgeInsets.fromLTRB(4, 8, 8, 8),
              decoration: BoxDecoration(
                color: Colors.white,
                border: Border(
                  top: BorderSide(color: theme.colorScheme.outlineVariant),
                ),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  IconButton(
                    onPressed: _pickingAttachment ? null : _showAttachmentSheet,
                    tooltip: 'Attach photo or document',
                    icon: _pickingAttachment
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.attach_file_rounded),
                  ),
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
                    onPressed: _send,
                    icon: const Icon(Icons.send_rounded),
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
    final loaded = messages.value;
    final pending = _pending.reversed.toList(growable: false);

    if (loaded == null && pending.isEmpty) {
      if (messages.hasError) {
        return ErrorView(
          error: messages.error!,
          onRetry: () =>
              ref.invalidate(messagesProvider(widget.conversationId)),
        );
      }

      return const Center(child: CircularProgressIndicator());
    }

    final data = loaded ?? const <ChatMessage>[];

    if (data.isEmpty && pending.isEmpty) {
      return const EmptyState(
        icon: Icons.waving_hand_outlined,
        title: 'Say hello',
        message: 'Ask about the product, the delivery, or anything else.',
      );
    }

    return ListView.builder(
      controller: _scrollController,
      reverse: true,
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 10),
      itemCount: data.length + pending.length,
      // The list is reversed and new messages are inserted at index zero, so
      // every existing child changes index on refresh. Without this callback
      // Flutter reuses each child element for the message that moved into its
      // old index, and the image widget keeps painting the previous image.
      findChildIndexCallback: (key) {
        if (key is! ValueKey<String>) {
          return null;
        }

        final value = key.value;

        if (value.startsWith('pending-')) {
          final clientId = value.substring('pending-'.length);
          final index = pending.indexWhere((item) => item.clientId == clientId);

          return index == -1 ? null : index;
        }

        if (value.startsWith('message-')) {
          final messageId = value.substring('message-'.length);
          final index = data.indexWhere((message) => message.id == messageId);

          return index == -1 ? null : pending.length + data.length - 1 - index;
        }

        return null;
      },
      itemBuilder: (context, index) {
        if (index < pending.length) {
          final item = pending[index];

          return _PendingBubble(
            key: ValueKey('pending-${item.clientId}'),
            pending: item,
            onRetry: item.status == _PendingStatus.failed
                ? () => _sendPending(item)
                : null,
          );
        }

        final message = data[data.length - 1 - (index - pending.length)];

        return _Bubble(
          key: ValueKey('message-${message.id}'),
          message: message,
        );
      },
    );
  }
}

enum _PendingStatus { queued, sending, sent, failed }

enum _AttachmentKind { photo, document }

class _PendingMessage {
  _PendingMessage({
    required this.clientId,
    required this.body,
    required this.createdAt,
    this.attachment,
  });

  final String clientId;
  final String body;
  final DateTime createdAt;
  final PickedAttachment? attachment;
  _PendingStatus status = _PendingStatus.queued;
}

class _PendingBubble extends StatelessWidget {
  const _PendingBubble({super.key, required this.pending, this.onRetry});

  final _PendingMessage pending;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final failed = pending.status == _PendingStatus.failed;
    final foreground = failed
        ? theme.colorScheme.onErrorContainer
        : theme.colorScheme.onPrimary;
    final statusColor = foreground.withValues(alpha: 0.7);
    final compactImage =
        pending.attachment?.isImage == true && pending.body.trim().isEmpty;

    return Align(
      alignment: Alignment.centerRight,
      child: Container(
        constraints: BoxConstraints(
          maxWidth: MediaQuery.of(context).size.width * 0.78,
        ),
        margin: const EdgeInsets.only(bottom: 8),
        padding: compactImage
            ? const EdgeInsets.all(3)
            : const EdgeInsets.symmetric(horizontal: 13, vertical: 9),
        decoration: BoxDecoration(
          color: failed
              ? theme.colorScheme.errorContainer
              : theme.colorScheme.primary,
          borderRadius: const BorderRadius.only(
            topLeft: Radius.circular(16),
            topRight: Radius.circular(16),
            bottomLeft: Radius.circular(16),
            bottomRight: Radius.circular(4),
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (pending.attachment != null) ...[
              _PendingAttachmentPreview(
                attachment: pending.attachment!,
                clientId: pending.clientId,
                foreground: foreground,
              ),
              if (pending.body.trim().isNotEmpty) const SizedBox(height: 6),
            ],
            if (pending.body.trim().isNotEmpty)
              Text(
                pending.body,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: foreground,
                  height: 1.4,
                ),
              ),
            Padding(
              padding: compactImage
                  ? const EdgeInsets.fromLTRB(7, 5, 7, 4)
                  : const EdgeInsets.only(top: 4),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (pending.status == _PendingStatus.failed)
                    Icon(
                      Icons.error_outline_rounded,
                      size: 12,
                      color: foreground,
                    )
                  else if (pending.status == _PendingStatus.sent)
                    Icon(Icons.check_rounded, size: 12, color: statusColor)
                  else
                    SizedBox(
                      width: 9,
                      height: 9,
                      child: CircularProgressIndicator(
                        strokeWidth: 1.4,
                        color: statusColor,
                      ),
                    ),
                  const SizedBox(width: 4),
                  Text(
                    switch (pending.status) {
                      _PendingStatus.sent => 'Sent',
                      _PendingStatus.failed => 'Not sent',
                      _PendingStatus.queued ||
                      _PendingStatus.sending => 'Sending…',
                    },
                    style: theme.textTheme.labelSmall?.copyWith(
                      color: statusColor,
                      fontSize: 10,
                    ),
                  ),
                  if (failed) ...[
                    const SizedBox(width: 10),
                    InkWell(
                      onTap: onRetry,
                      child: Text(
                        'Retry',
                        style: theme.textTheme.labelSmall?.copyWith(
                          color: foreground,
                          fontSize: 10,
                          fontWeight: FontWeight.w800,
                          decoration: TextDecoration.underline,
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PendingAttachmentPreview extends StatelessWidget {
  const _PendingAttachmentPreview({
    required this.attachment,
    required this.clientId,
    required this.foreground,
  });

  final PickedAttachment attachment;
  final String clientId;
  final Color foreground;

  @override
  Widget build(BuildContext context) {
    if (!attachment.isImage) {
      return _document(context);
    }

    return ClipRRect(
      borderRadius: BorderRadius.circular(10),
      child: Image.memory(
        attachment.bytes,
        key: ValueKey('pending-image-$clientId'),
        width: 190,
        height: 150,
        fit: BoxFit.cover,
        gaplessPlayback: true,
        errorBuilder: (context, error, stackTrace) => _document(context),
      ),
    );
  }

  Widget _document(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(Icons.picture_as_pdf_outlined, size: 20, color: foreground),
        const SizedBox(width: 6),
        Flexible(
          child: Text(
            attachment.name,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.bodySmall
                ?.copyWith(color: foreground),
          ),
        ),
      ],
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble({super.key, required this.message});

  final ChatMessage message;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final mine = message.isMine;
    final background = mine ? theme.colorScheme.primary : Colors.white;
    final foreground = mine
        ? theme.colorScheme.onPrimary
        : theme.colorScheme.onSurface;
    final compactImage =
        message.body.trim().isEmpty &&
        message.attachments.length == 1 &&
        message.attachments.single.isImage;

    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        constraints: BoxConstraints(
          maxWidth: MediaQuery.of(context).size.width * 0.78,
        ),
        margin: const EdgeInsets.only(bottom: 8),
        padding: compactImage
            ? const EdgeInsets.all(3)
            : const EdgeInsets.symmetric(horizontal: 13, vertical: 9),
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
                padding: compactImage
                    ? const EdgeInsets.fromLTRB(7, 5, 7, 5)
                    : const EdgeInsets.only(bottom: 4),
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
                padding: EdgeInsets.only(top: compactImage ? 0 : 6),
                child: attachment.isImage && attachment.url != null
                    ? NetworkThumb(
                        url: attachment.url,
                        cacheKey: 'chat-attachment-${attachment.id}',
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
            SizedBox(height: compactImage ? 5 : 3),
            Padding(
              padding: compactImage
                  ? const EdgeInsets.fromLTRB(7, 0, 7, 4)
                  : EdgeInsets.zero,
              child: Text(
                dateTime(message.createdAt),
                style: theme.textTheme.labelSmall?.copyWith(
                  color: foreground.withValues(alpha: 0.65),
                  fontSize: 10,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
