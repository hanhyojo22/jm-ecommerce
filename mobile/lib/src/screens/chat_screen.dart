import 'dart:async';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:uuid/uuid.dart';

import '../core/api_exception.dart';
import '../core/attachment_picker.dart';
import '../core/formatters.dart';
import '../core/reverb_client.dart';
import '../models/models.dart';
import '../providers/auth_providers.dart';
import '../providers/chat_providers.dart';
import '../theme.dart';
import '../widgets/common.dart';
import '../widgets/image_viewer.dart';

class ChatScreen extends ConsumerStatefulWidget {
  const ChatScreen({
    super.key,
    required this.conversationId,
    this.productId,
    this.sellerOrderId,
    this.focusComposer = false,
  });

  final String conversationId;
  final String? productId;
  final String? sellerOrderId;
  final bool focusComposer;

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
  String? _pendingProductId;
  String? _pendingSellerOrderId;
  ChatMessage? _replyTo;
  final _composerFocus = FocusNode();

  @override
  void initState() {
    super.initState();
    _pendingProductId = widget.productId;
    _pendingSellerOrderId = widget.sellerOrderId;
    _reverb = ref.read(reverbProvider);
    _events = _reverb.events.listen(_onReverbEvent);
    _states = _reverb.states.listen(_onReverbState);

    unawaited(_connectReverb());
    _startPolling();

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
    _composerFocus.dispose();
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
      // The socket delivers new messages, so REST polling is redundant.
      _stopPolling();
      return;
    }

    _startPolling();

    if (state == ReverbState.disconnected || state == ReverbState.failed) {
      _scheduleReconnect();
    }
  }

  void _startPolling() {
    if (_poll?.isActive ?? false) {
      return;
    }

    _poll = Timer.periodic(const Duration(seconds: 15), (_) => _refresh());
  }

  void _stopPolling() {
    _poll?.cancel();
    _poll = null;
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

      // Until we know who we are, any typing event could be our own echo.
      if (me == null || userId == me) {
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
      replyToId: _replyTo?.id,
    );

    setState(() {
      _pending.add(item);
      _replyTo = null;
    });
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
          productId: _pendingProductId,
          sellerOrderId: _pendingSellerOrderId,
          replyToId: item.replyToId,
        );
      } else {
        await api.sendAttachment(
          conversationId: widget.conversationId,
          attachment: attachment,
          clientMessageId: item.clientId,
          productId: _pendingProductId,
          sellerOrderId: _pendingSellerOrderId,
        );
      }

      if (!mounted) {
        return;
      }

      setState(() => item.status = _PendingStatus.sent);
      _pendingProductId = null;
      _pendingSellerOrderId = null;
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
    // The typing handler reads this to drop our own echoed events.
    ref.watch(meProvider);

    final messages = ref.watch(messagesProvider(widget.conversationId));
    final conversation = ref
        .watch(conversationProvider(widget.conversationId))
        .value;
    if (conversation != null && conversation.id != widget.conversationId) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        // Keep the unsent product/order context across the alias redirect.
        if (mounted) {
          final pendingContext = {
            'product_id': ?_pendingProductId,
            'seller_order_id': ?_pendingSellerOrderId,
          };
          context.go(
            Uri(
              path: '/conversations/${conversation.id}',
              queryParameters: pendingContext.isEmpty ? null : pendingContext,
            ).toString(),
          );
        }
      });
    }
    final theme = Theme.of(context);

    // Reflects the most recently loaded context-bearing message, not the not-yet-sent
    // pending context (that already has its own "Product/Order inquiry" chip below).
    String? headerContextType;
    for (final m in (messages.value ?? const <ChatMessage>[]).reversed) {
      if (m.contextSnapshot != null) {
        headerContextType = m.contextSnapshot!['type'] as String?;
        break;
      }
    }
    final headerContextLabel = switch (headerContextType) {
      'product' => 'Product inquiry',
      'order' => 'Order conversation',
      _ => 'Marketplace chat',
    };

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
                  Text(
                    conversation?.store?.name ?? 'Chat',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  Text(
                    headerContextLabel,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodySmall,
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
                    ? theme.colorScheme.primary
                    : theme.colorScheme.outline,
              ),
            ),
          ),
        ],
      ),
      body: Column(
        children: [
          Expanded(child: _body(messages, theme, conversation?.store)),
          if (_pendingProductId != null || _pendingSellerOrderId != null)
            ListTile(
              dense: true,
              title: Text(
                _pendingProductId != null ? 'Product inquiry' : 'Order inquiry',
              ),
              trailing: IconButton(
                icon: const Icon(Icons.close),
                tooltip: 'Remove context',
                onPressed: () => setState(() {
                  _pendingProductId = null;
                  _pendingSellerOrderId = null;
                }),
              ),
            ),
          if (_replyTo case final replyTo?)
            Container(
              key: const ValueKey('replying-to'),
              margin: const EdgeInsets.fromLTRB(12, 0, 12, 6),
              padding: const EdgeInsets.only(left: 10),
              decoration: BoxDecoration(
                color: theme.colorScheme.primaryContainer.withValues(
                  alpha: 0.35,
                ),
                border: Border(
                  left: BorderSide(color: theme.colorScheme.primary, width: 3),
                ),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          replyTo.isMine
                              ? 'Replying to yourself'
                              : 'Replying to ${conversation?.store?.name ?? 'store'}',
                          style: theme.textTheme.labelMedium?.copyWith(
                            color: theme.colorScheme.primary,
                          ),
                        ),
                        Text(
                          _quoteText(
                            replyTo.contextSnapshot?['type'] == 'product'
                                ? replyTo.contextSnapshot!['title'] as String?
                                : replyTo.contextSnapshot?['type'] == 'order'
                                ? 'Order ${replyTo.contextSnapshot!['number']}'
                                : null,
                            replyTo.body.trim().isEmpty &&
                                    replyTo.attachments.isNotEmpty
                                ? 'Attachment'
                                : replyTo.body,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close),
                    tooltip: 'Cancel reply',
                    onPressed: () => setState(() => _replyTo = null),
                  ),
                ],
              ),
            ),
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
                color: theme.colorScheme.surfaceContainerLowest,
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
                      focusNode: _composerFocus,
                      autofocus: widget.focusComposer,
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

  Future<void> _showMessageMenu(ChatMessage message, VoidCallback reply) async {
    HapticFeedback.mediumImpact();
    final text = message.body.trim();
    final image = message.attachments
        .where((attachment) => attachment.isImage && attachment.url != null)
        .firstOrNull;

    await showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.reply_rounded),
              title: const Text('Reply'),
              onTap: () {
                Navigator.pop(sheetContext);
                reply();
              },
            ),
            if (text.isNotEmpty)
              ListTile(
                leading: const Icon(Icons.copy_rounded),
                title: const Text('Copy'),
                onTap: () {
                  Navigator.pop(sheetContext);
                  Clipboard.setData(ClipboardData(text: text));
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Message copied')),
                  );
                },
              ),
            if (image != null)
              ListTile(
                leading: const Icon(Icons.download_rounded),
                title: const Text('Save image'),
                onTap: () {
                  Navigator.pop(sheetContext);
                  _saveImage(image);
                },
              ),
            if (!message.isMine)
              ListTile(
                leading: Icon(
                  Icons.flag_outlined,
                  color: Theme.of(sheetContext).colorScheme.error,
                ),
                title: Text(
                  'Report',
                  style: TextStyle(
                    color: Theme.of(sheetContext).colorScheme.error,
                  ),
                ),
                onTap: () {
                  Navigator.pop(sheetContext);
                  _reportMessage(message);
                },
              ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
  }

  Future<void> _saveImage(ChatAttachment image) async {
    final messenger = ScaffoldMessenger.of(context);

    try {
      await ref.read(imageSaverProvider)(image.url!, image.name);
      messenger.showSnackBar(const SnackBar(content: Text('Saved to gallery')));
    } catch (_) {
      messenger.showSnackBar(
        const SnackBar(content: Text('Could not save the image. Try again.')),
      );
    }
  }

  Future<void> _reportMessage(ChatMessage message) async {
    const reasons = {
      'Spam': 'Spam',
      'Abuse': 'Abusive content',
      'Other': 'Something else',
    };

    final reason = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              child: Text(
                'Why are you reporting this message?',
                style: Theme.of(sheetContext).textTheme.titleMedium,
              ),
            ),
            for (final entry in reasons.entries)
              ListTile(
                title: Text(entry.value),
                onTap: () => Navigator.pop(sheetContext, entry.key),
              ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );

    if (reason == null || !mounted) {
      return;
    }

    final messenger = ScaffoldMessenger.of(context);

    try {
      await ref
          .read(apiClientProvider)
          .reportMessage(
            conversationId: widget.conversationId,
            messageId: message.id,
            reason: reason,
          );
      messenger.showSnackBar(
        const SnackBar(
          content: Text('Report submitted. Our team will review it.'),
        ),
      );
    } on ApiException catch (error) {
      messenger.showSnackBar(SnackBar(content: Text(error.friendly)));
    }
  }

  Widget _body(
    AsyncValue<List<ChatMessage>> messages,
    ThemeData theme,
    StoreInfo? store,
  ) {
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

        final dataIndex = data.length - 1 - (index - pending.length);
        final message = data[dataIndex];
        final nextMessage = dataIndex + 1 < data.length
            ? data[dataIndex + 1]
            : null;

        void reply() {
          setState(() => _replyTo = message);
          _composerFocus.requestFocus();
        }

        return _SwipeToReply(
          key: ValueKey('message-${message.id}'),
          fromRight: message.isMine,
          onReply: reply,
          child: _Bubble(
            message: message,
            store: store,
            showAvatar:
                nextMessage == null ||
                nextMessage.senderId != message.senderId ||
                !_isSameChatMinute(nextMessage.createdAt, message.createdAt),
            showReceipt:
                message.isMine &&
                nextMessage == null &&
                pending.isEmpty &&
                message.receipt != null,
            onLongPress: () => _showMessageMenu(message, reply),
            onSaveImage: _saveImage,
          ),
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
    this.replyToId,
  });

  final String clientId;
  final String body;
  final DateTime createdAt;
  final PickedAttachment? attachment;
  final String? replyToId;
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
    final compactAttachment =
        pending.attachment != null && pending.body.trim().isEmpty;
    final photoOnly = compactAttachment && pending.attachment!.isImage;
    final foreground = failed
        ? theme.colorScheme.onErrorContainer
        : photoOnly
        ? theme.colorScheme.onSurface
        : theme.colorScheme.onPrimary;
    final statusColor = failed
        ? theme.colorScheme.error
        : theme.colorScheme.onSurfaceVariant;

    return Align(
      alignment: Alignment.centerRight,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Container(
            key: compactAttachment
                ? ValueKey('pending-attachment-bubble-${pending.clientId}')
                : ValueKey('pending-bubble-${pending.clientId}'),
            constraints: BoxConstraints(
              maxWidth: MediaQuery.of(context).size.width * 0.78,
            ),
            padding: compactAttachment
                ? EdgeInsets.zero
                : const EdgeInsets.symmetric(horizontal: 13, vertical: 9),
            clipBehavior: compactAttachment ? Clip.antiAlias : Clip.none,
            decoration: BoxDecoration(
              color: failed
                  ? theme.colorScheme.errorContainer
                  : photoOnly
                  ? theme.colorScheme.surfaceContainerLowest
                  : theme.colorScheme.primary,
              border: photoOnly
                  ? Border.all(color: theme.colorScheme.outlineVariant)
                  : null,
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
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(4, 4, 4, 8),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (pending.status == _PendingStatus.failed)
                  Icon(
                    Icons.error_outline_rounded,
                    size: 12,
                    color: statusColor,
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
                        color: statusColor,
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

    // The pending bubble clips to its own shape; the image keeps its aspect ratio.
    return ConstrainedBox(
      constraints: const BoxConstraints(maxWidth: 220, maxHeight: 320),
      child: Image.memory(
        attachment.bytes,
        key: ValueKey('pending-image-$clientId'),
        cacheWidth: (220 * MediaQuery.devicePixelRatioOf(context)).round(),
        fit: BoxFit.contain,
        gaplessPlayback: true,
        errorBuilder: (context, error, stackTrace) => _document(context),
      ),
    );
  }

  Widget _document(BuildContext context) {
    return _DocumentAttachment(
      key: ValueKey('pending-document-$clientId'),
      name: attachment.name,
      foreground: foreground,
      compact: true,
    );
  }
}

class _DocumentAttachment extends StatelessWidget {
  const _DocumentAttachment({
    super.key,
    required this.name,
    required this.foreground,
    required this.compact,
    this.onTap,
  });

  final String name;
  final Color foreground;
  final bool compact;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final radius = compact
        ? BorderRadius.zero
        : BorderRadius.circular(appSurfaceRadius);
    final content = Padding(
      padding: const EdgeInsets.all(12),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: foreground.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(appSurfaceRadius),
            ),
            child: Icon(
              Icons.picture_as_pdf_outlined,
              size: 22,
              color: foreground,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: foreground,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  'PDF document',
                  style: Theme.of(context).textTheme.labelSmall
                      ?.copyWith(color: foreground.withValues(alpha: 0.72)),
                ),
              ],
            ),
          ),
          if (onTap != null) ...[
            const SizedBox(width: 8),
            Icon(Icons.open_in_new_rounded, size: 18, color: foreground),
          ],
        ],
      ),
    );

    return Semantics(
      button: onTap != null,
      label: onTap == null ? 'Document $name' : 'Open document $name',
      child: ConstrainedBox(
        constraints: const BoxConstraints(minWidth: 220),
        child: Material(
          color: compact
              ? Colors.transparent
              : foreground.withValues(alpha: 0.08),
          borderRadius: radius,
          clipBehavior: Clip.antiAlias,
          child: InkWell(onTap: onTap, borderRadius: radius, child: content),
        ),
      ),
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble({
    required this.message,
    required this.showAvatar,
    this.store,
    this.showReceipt = false,
    this.onLongPress,
    this.onSaveImage,
  });

  final ChatMessage message;
  final bool showAvatar;
  final StoreInfo? store;
  final bool showReceipt;
  final VoidCallback? onLongPress;
  final Future<void> Function(ChatAttachment image)? onSaveImage;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final mine = message.isMine;
    final background = mine
        ? theme.colorScheme.primary
        : theme.colorScheme.surfaceContainerLowest;
    final foreground = mine
        ? theme.colorScheme.onPrimary
        : theme.colorScheme.onSurface;
    final compactAttachment =
        message.body.trim().isEmpty && message.attachments.length == 1;
    final photoOnly = compactAttachment && message.attachments.single.isImage;
    final bubbleBackground = photoOnly
        ? theme.colorScheme.surfaceContainerLowest
        : background;
    final snapshot = message.contextSnapshot;
    final reply = message.replyTo;

    return GestureDetector(
      onLongPress: onLongPress,
      child: Semantics(
        onLongPressHint: onLongPress == null ? null : 'Message options',
        child: Align(
          alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              if (!mine) ...[
                if (showAvatar)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 24),
                    // Buyers talk to the store, never to an individual staff member.
                    child: StoreAvatar(
                      key: ValueKey('sender-avatar-${message.id}'),
                      name: store?.name ?? 'Store',
                      logoUrl: store?.logoUrl,
                      radius: 14,
                    ),
                  )
                else
                  const SizedBox(width: 28),
                const SizedBox(width: 8),
              ],
              Flexible(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: mine
                      ? CrossAxisAlignment.end
                      : CrossAxisAlignment.start,
                  children: [
                    if (snapshot != null && snapshot['type'] == 'order')
                      _OrderInquiryCard(
                        messageId: message.id,
                        snapshot: snapshot,
                      )
                    else if (snapshot != null)
                      Builder(
                        builder: (context) {
                          final isProduct = snapshot['type'] == 'product';
                          final storeSlug = snapshot['store_slug'] as String?;
                          final productSlug = snapshot['slug'] as String?;
                          final orderId = snapshot['order_id'] as String?;
                          final destination = isProduct
                              ? (storeSlug != null && productSlug != null
                                    ? '/product/$storeSlug/$productSlug'
                                    : null)
                              : (orderId != null ? '/orders/$orderId' : null);
                          final imageUrl = snapshot['image_url'] as String?;
                          final title = isProduct
                              ? '${snapshot['title']}'
                              : 'Order ${snapshot['number']}';

                          return Card(
                            margin: const EdgeInsets.only(bottom: 6),
                            color: isProduct
                                ? theme.colorScheme.primaryContainer.withValues(
                                    alpha: 0.35,
                                  )
                                : theme.colorScheme.surfaceContainerHighest,
                            child: InkWell(
                              onTap: destination == null
                                  ? null
                                  : () => context.push(destination),
                              borderRadius: BorderRadius.circular(
                                appSurfaceRadius,
                              ),
                              child: Padding(
                                padding: const EdgeInsets.all(10),
                                child: Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    if (imageUrl != null)
                                      Semantics(
                                        button: true,
                                        label: 'View photo',
                                        child: GestureDetector(
                                          key: ValueKey(
                                            'open-context-image-${message.id}',
                                          ),
                                          onTap: () => ImageViewer.open(
                                            context,
                                            url: imageUrl,
                                            title: title,
                                          ),
                                          behavior: HitTestBehavior.opaque,
                                          child: SizedBox.square(
                                            dimension: 64,
                                            child: ClipRRect(
                                              borderRadius:
                                                  BorderRadius.circular(
                                                    appSurfaceRadius,
                                                  ),
                                              child: CachedNetworkImage(
                                                imageUrl: imageUrl,
                                                fit: BoxFit.cover,
                                                errorWidget: (_, _, _) =>
                                                    const Icon(
                                                      Icons
                                                          .image_not_supported_outlined,
                                                    ),
                                              ),
                                            ),
                                          ),
                                        ),
                                      )
                                    else
                                      CircleAvatar(
                                        radius: 24,
                                        backgroundColor: isProduct
                                            ? theme.colorScheme.primaryContainer
                                            : theme
                                                  .colorScheme
                                                  .surfaceContainerHigh,
                                        child: Icon(
                                          isProduct
                                              ? Icons.shopping_bag_outlined
                                              : Icons.receipt_long_outlined,
                                          size: 20,
                                          color: theme
                                              .colorScheme
                                              .onSurfaceVariant,
                                        ),
                                      ),
                                    const SizedBox(width: 10),
                                    Flexible(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            title,
                                            maxLines: 2,
                                            overflow: TextOverflow.ellipsis,
                                            style: theme.textTheme.titleSmall,
                                          ),
                                          Text(
                                            '₱${snapshot['price']}',
                                            style: theme.textTheme.bodySmall
                                                ?.copyWith(
                                                  color: theme
                                                      .colorScheme
                                                      .onSurfaceVariant,
                                                ),
                                          ),
                                        ],
                                      ),
                                    ),
                                    if (destination != null) ...[
                                      const SizedBox(width: 4),
                                      Icon(
                                        Icons.chevron_right_rounded,
                                        size: 20,
                                        color:
                                            theme.colorScheme.onSurfaceVariant,
                                      ),
                                    ],
                                  ],
                                ),
                              ),
                            ),
                          );
                        },
                      ),
                    // Messenger-style: "replied to" label over a faded quote tucked under the bubble.
                    if (reply != null) ...[
                      Padding(
                        padding: const EdgeInsets.fromLTRB(4, 0, 4, 4),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(
                              Icons.reply_rounded,
                              size: 14,
                              color: theme.colorScheme.onSurfaceVariant,
                            ),
                            const SizedBox(width: 4),
                            Text(
                              '${mine ? 'You' : 'Store'} replied to '
                              '${reply.isMine ? (mine ? 'yourself' : 'you') : (mine ? 'Store' : 'themselves')}',
                              style: theme.textTheme.labelSmall?.copyWith(
                                color: theme.colorScheme.onSurfaceVariant,
                              ),
                            ),
                          ],
                        ),
                      ),
                      Transform.translate(
                        offset: const Offset(0, 8),
                        child: Container(
                          key: ValueKey('reply-quote-${message.id}'),
                          constraints: BoxConstraints(
                            maxWidth:
                                MediaQuery.of(context).size.width * 0.78 -
                                (mine ? 0 : 36),
                          ),
                          padding: const EdgeInsets.fromLTRB(13, 8, 13, 16),
                          decoration: BoxDecoration(
                            color: theme.colorScheme.surfaceContainerHigh,
                            borderRadius: BorderRadius.circular(16),
                          ),
                          child: Text(
                            _quoteText(reply.contextLabel, reply.body),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: theme.colorScheme.onSurfaceVariant,
                            ),
                          ),
                        ),
                      ),
                    ],
                    Container(
                      key: compactAttachment
                          ? ValueKey('attachment-bubble-${message.id}')
                          : ValueKey('message-bubble-${message.id}'),
                      constraints: BoxConstraints(
                        maxWidth:
                            MediaQuery.of(context).size.width * 0.78 -
                            (mine ? 0 : 36),
                      ),
                      // A photo fills its bubble edge to edge; the bubble shape clips it.
                      padding: compactAttachment
                          ? EdgeInsets.zero
                          : const EdgeInsets.symmetric(
                              horizontal: 13,
                              vertical: 9,
                            ),
                      clipBehavior: compactAttachment
                          ? Clip.antiAlias
                          : Clip.none,
                      decoration: BoxDecoration(
                        color: bubbleBackground,
                        borderRadius: BorderRadius.only(
                          topLeft: const Radius.circular(16),
                          topRight: const Radius.circular(16),
                          bottomLeft: Radius.circular(mine ? 16 : 4),
                          bottomRight: Radius.circular(mine ? 4 : 16),
                        ),
                        border: photoOnly || !mine
                            ? Border.all(
                                color: theme.colorScheme.outlineVariant,
                              )
                            : null,
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
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
                              padding: EdgeInsets.only(
                                top: compactAttachment ? 0 : 6,
                              ),
                              child:
                                  attachment.isImage && attachment.url != null
                                  ? ConstrainedBox(
                                      constraints: const BoxConstraints(
                                        maxWidth: 220,
                                        maxHeight: 320,
                                      ),
                                      // No fixed width: the photo keeps its own aspect
                                      // ratio inside 220x320 instead of letterboxing.
                                      child: Semantics(
                                        button: true,
                                        label: 'Open photo',
                                        child: GestureDetector(
                                          key: ValueKey(
                                            'open-image-${attachment.id}',
                                          ),
                                          onTap: () => ImageViewer.open(
                                            context,
                                            url: attachment.url!,
                                            cacheKey:
                                                'chat-attachment-${attachment.id}',
                                            title: attachment.name,
                                            onSave: onSaveImage == null
                                                ? null
                                                : () =>
                                                      onSaveImage!(attachment),
                                          ),
                                          child: NetworkThumb(
                                            url: attachment.url,
                                            cacheKey:
                                                'chat-attachment-${attachment.id}',
                                            cacheWidth:
                                                (220 *
                                                        MediaQuery.devicePixelRatioOf(
                                                          context,
                                                        ))
                                                    .round(),
                                            placeholderSize: const Size(
                                              220,
                                              220,
                                            ),
                                            fit: BoxFit.contain,
                                            radius: photoOnly ? 0 : 10,
                                          ),
                                        ),
                                      ),
                                    )
                                  : _DocumentAttachment(
                                      key: ValueKey(
                                        'document-attachment-${attachment.id}',
                                      ),
                                      name: attachment.name,
                                      foreground: foreground,
                                      compact: compactAttachment,
                                      onTap: attachment.url == null
                                          ? null
                                          : () => launchUrl(
                                              Uri.parse(attachment.url!),
                                              mode: LaunchMode
                                                  .externalApplication,
                                            ),
                                    ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.fromLTRB(4, 4, 4, 8),
                      child: showReceipt
                          ? Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Text(
                                  dateTime(message.createdAt),
                                  style: theme.textTheme.labelSmall?.copyWith(
                                    color: theme.colorScheme.onSurfaceVariant,
                                    fontSize: 11,
                                  ),
                                ),
                                const SizedBox(width: 4),
                                Icon(
                                  key: const ValueKey('receipt-indicator'),
                                  Icons.done,
                                  size: 14,
                                  color: message.receipt == 'Read'
                                      ? theme.colorScheme.primary
                                      : theme.colorScheme.onSurfaceVariant,
                                ),
                              ],
                            )
                          : Text(
                              dateTime(message.createdAt),
                              style: theme.textTheme.labelSmall?.copyWith(
                                color: theme.colorScheme.onSurfaceVariant,
                                fontSize: 11,
                              ),
                            ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Messenger-style swipe: drag a message toward the middle of the screen to reply.
/// Incoming messages swipe right, your own swipe left.
class _SwipeToReply extends StatefulWidget {
  const _SwipeToReply({
    super.key,
    required this.fromRight,
    required this.onReply,
    required this.child,
  });

  final bool fromRight;
  final VoidCallback onReply;
  final Widget child;

  @override
  State<_SwipeToReply> createState() => _SwipeToReplyState();
}

class _SwipeToReplyState extends State<_SwipeToReply> {
  static const _trigger = 56.0;
  static const _maxDrag = 80.0;

  double _distance = 0;
  bool _dragging = false;
  bool _armed = false;

  void _update(DragUpdateDetails details) {
    final delta = widget.fromRight ? -details.delta.dx : details.delta.dx;

    setState(() {
      _dragging = true;
      _distance = (_distance + delta).clamp(0.0, _maxDrag);

      if (!_armed && _distance >= _trigger) {
        _armed = true;
        HapticFeedback.selectionClick();
      } else if (_armed && _distance < _trigger) {
        _armed = false;
      }
    });
  }

  void _release() {
    if (_armed) {
      widget.onReply();
    }

    setState(() {
      _dragging = false;
      _distance = 0;
      _armed = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final progress = (_distance / _trigger).clamp(0.0, 1.0);
    final offset = widget.fromRight ? -_distance : _distance;

    return GestureDetector(
      behavior: HitTestBehavior.translucent,
      onHorizontalDragUpdate: _update,
      onHorizontalDragEnd: (_) => _release(),
      onHorizontalDragCancel: _release,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Positioned(
            top: 0,
            bottom: 0,
            left: widget.fromRight ? null : 4,
            right: widget.fromRight ? 4 : null,
            child: Center(
              child: Opacity(
                opacity: progress,
                child: Transform.scale(
                  scale: 0.6 + 0.4 * progress,
                  child: CircleAvatar(
                    radius: 15,
                    backgroundColor: Theme.of(context)
                        .colorScheme
                        .surfaceContainerHigh,
                    child: Icon(
                      Icons.reply_rounded,
                      size: 18,
                      color: Theme.of(context).colorScheme.onSurfaceVariant,
                    ),
                  ),
                ),
              ),
            ),
          ),
          AnimatedContainer(
            duration: _dragging
                ? Duration.zero
                : const Duration(milliseconds: 180),
            curve: Curves.easeOut,
            transform: Matrix4.translationValues(offset, 0, 0),
            child: widget.child,
          ),
        ],
      ),
    );
  }
}

String _quoteText(String? contextLabel, String body) => [
  contextLabel,
  body.trim(),
].where((part) => part != null && part.isNotEmpty).join(' · ');

bool _isSameChatMinute(String first, String second) {
  final firstTime = DateTime.tryParse(first);
  final secondTime = DateTime.tryParse(second);

  return firstTime != null &&
      secondTime != null &&
      firstTime.year == secondTime.year &&
      firstTime.month == secondTime.month &&
      firstTime.day == secondTime.day &&
      firstTime.hour == secondTime.hour &&
      firstTime.minute == secondTime.minute;
}

/// Compact chat card for an order inquiry: order number and status, a strip of
/// product photos (each opens full screen), and the item names with the total.
class _OrderInquiryCard extends StatelessWidget {
  const _OrderInquiryCard({required this.messageId, required this.snapshot});

  static const _visiblePhotos = 4;
  static const _photoSize = 40.0;

  final String messageId;
  final Map<String, dynamic> snapshot;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final orderId = snapshot['order_id'] as String?;
    final status = snapshot['status'] as String?;
    final items = [
      for (final item in snapshot['items'] as List? ?? const [])
        if (item is Map) Map<String, dynamic>.from(item),
    ];
    // Inquiries sent before item lists were stored only carry one photo.
    if (items.isEmpty && snapshot['image_url'] is String) {
      items.add({
        'name': snapshot['item_name'] ?? 'Ordered product',
        'image_url': snapshot['image_url'],
      });
    }
    final hiddenItems = items.length - _visiblePhotos;
    final names = items.map((item) => '${item['name']}').toList();
    final summary = names.length > 2
        ? '${names.take(2).join(', ')} +${names.length - 2} more'
        : names.join(', ');

    return Container(
      key: ValueKey('order-card-$messageId'),
      width: MediaQuery.sizeOf(context).width * 0.72,
      margin: const EdgeInsets.only(bottom: 6),
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: scheme.surfaceContainerLowest,
        borderRadius: BorderRadius.circular(appSurfaceRadius + 2),
        border: Border.all(color: scheme.outlineVariant),
      ),
      child: Material(
        type: MaterialType.transparency,
        child: InkWell(
          onTap: orderId == null
              ? null
              : () => context.push('/orders/$orderId'),
          child: Padding(
            padding: const EdgeInsets.all(10),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        'Order #${snapshot['number']}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleSmall,
                      ),
                    ),
                    if (status != null) StatusPill(status: status),
                  ],
                ),
                if (items.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      for (final (index, item)
                          in items.take(_visiblePhotos).indexed)
                        Padding(
                          padding: const EdgeInsetsDirectional.only(end: 6),
                          child: _OrderInquiryPhoto(
                            key: ValueKey(
                              'open-order-item-image-$messageId-$index',
                            ),
                            item: item,
                            size: _photoSize,
                          ),
                        ),
                      if (hiddenItems > 0)
                        Container(
                          width: _photoSize,
                          height: _photoSize,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color: scheme.surfaceContainerHigh,
                            borderRadius: BorderRadius.circular(
                              appSurfaceRadius,
                            ),
                          ),
                          child: Text(
                            '+$hiddenItems',
                            style: theme.textTheme.labelMedium?.copyWith(
                              color: scheme.onSurfaceVariant,
                            ),
                          ),
                        ),
                    ],
                  ),
                ],
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        summary,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: scheme.onSurfaceVariant,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      '₱${snapshot['total']}',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    if (orderId != null)
                      Icon(
                        Icons.chevron_right_rounded,
                        size: 18,
                        color: scheme.onSurfaceVariant,
                      ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _OrderInquiryPhoto extends StatelessWidget {
  const _OrderInquiryPhoto({super.key, required this.item, required this.size});

  final Map<String, dynamic> item;
  final double size;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final name = '${item['name'] ?? 'Product'}';
    final imageUrl = item['image_url'] as String?;
    final placeholder = ColoredBox(
      color: scheme.surfaceContainerHigh,
      child: Icon(
        Icons.inventory_2_outlined,
        size: 18,
        color: scheme.onSurfaceVariant,
      ),
    );

    return Semantics(
      button: imageUrl != null,
      label: imageUrl == null ? name : 'View photo of $name',
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: imageUrl == null
            ? null
            : () => ImageViewer.open(context, url: imageUrl, title: name),
        child: SizedBox.square(
          dimension: size,
          child: ClipRRect(
            borderRadius: BorderRadius.circular(appSurfaceRadius),
            child: imageUrl == null
                ? placeholder
                : CachedNetworkImage(
                    imageUrl: imageUrl,
                    fit: BoxFit.cover,
                    errorWidget: (_, _, _) => placeholder,
                  ),
          ),
        ),
      ),
    );
  }
}
