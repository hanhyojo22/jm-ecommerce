import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/formatters.dart';
import '../models/models.dart';
import '../providers/chat_providers.dart';
import '../widgets/common.dart';
import '../widgets/shell.dart';

class MessagesScreen extends ConsumerWidget {
  const MessagesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final conversations = ref.watch(conversationsProvider);

    return AsyncView(
      value: conversations,
      onRetry: () => ref.invalidate(conversationsProvider),
      builder: (items) {
        if (items.isEmpty) {
          return EmptyState(
            icon: Icons.chat_bubble_outline_rounded,
            title: 'No conversations yet',
            message: 'Ask a store about a product or an order and the chat will appear here.',
            action: FilledButton(
              onPressed: () => ref.read(shellTabProvider.notifier).select(0),
              child: const Text('Browse products'),
            ),
          );
        }

        return RefreshIndicator(
          onRefresh: () async {
            ref.invalidate(conversationsProvider);
            await ref.read(conversationsProvider.future);
          },
          child: ListView.separated(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
            itemCount: items.length,
            separatorBuilder: (context, index) => const SizedBox(height: 10),
            itemBuilder: (context, index) {
              final conversation = items[index];
              final isUnread = conversation.unreadCount > 0;
              final theme = Theme.of(context);

              return Card(
                clipBehavior: Clip.antiAlias,
                color: isUnread
                    ? theme.colorScheme.primary.withValues(alpha: 0.06)
                    : null,
                child: InkWell(
                  onTap: () =>
                      context.push('/conversations/${conversation.id}'),
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        StoreAvatar(
                          name: conversation.store?.name ?? 'Store',
                          logoUrl: conversation.store?.logoUrl,
                          radius: 24,
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      conversation.store?.name ?? 'Store',
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                  ),
                                  Text(
                                    timeAgo(conversation.latestMessageAt),
                                    style: theme.textTheme.bodySmall?.copyWith(
                                      color: isUnread
                                          ? theme.colorScheme.primary
                                          : theme.colorScheme.onSurfaceVariant,
                                      fontWeight: isUnread
                                          ? FontWeight.w700
                                          : FontWeight.w400,
                                    ),
                                  ),
                                ],
                              ),
                              if (_contextLine(conversation) != null)
                                Text(
                                  _contextLine(conversation)!,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: Theme.of(context).textTheme.bodySmall
                                      ?.copyWith(
                                        color: Theme.of(context)
                                            .colorScheme
                                            .primary,
                                        fontWeight: FontWeight.w600,
                                      ),
                                ),
                              const SizedBox(height: 4),
                              Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      _preview(conversation),
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: theme.textTheme.bodyMedium
                                          ?.copyWith(
                                            color: isUnread
                                                ? theme.colorScheme.onSurface
                                                : theme
                                                      .colorScheme
                                                      .onSurfaceVariant,
                                            fontWeight: isUnread
                                                ? FontWeight.w600
                                                : FontWeight.w400,
                                          ),
                                    ),
                                  ),
                                  if (isUnread)
                                    Container(
                                      margin: const EdgeInsets.only(left: 8),
                                      padding: const EdgeInsets.symmetric(
                                        horizontal: 7,
                                        vertical: 2,
                                      ),
                                      decoration: BoxDecoration(
                                        color: Theme.of(context)
                                            .colorScheme
                                            .primary,
                                        borderRadius: BorderRadius.circular(99),
                                      ),
                                      child: Text(
                                        '${conversation.unreadCount}',
                                        style: TextStyle(
                                          color: Theme.of(context)
                                              .colorScheme
                                              .onPrimary,
                                          fontSize: 11,
                                          fontWeight: FontWeight.w800,
                                        ),
                                      ),
                                    ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            },
          ),
        );
      },
    );
  }

  String? _contextLine(Conversation conversation) {
    if (conversation.productName != null) {
      return conversation.productName;
    }

    if (conversation.orderNumber != null) {
      return 'Order ${conversation.orderNumber}';
    }

    return null;
  }

  String _preview(Conversation conversation) {
    final latest = conversation.latestMessage;

    if (latest == null) {
      return 'Start the conversation';
    }

    if (latest.type == 'file') {
      return latest.body.trim().isEmpty ? 'Attachment' : latest.body;
    }

    return latest.body;
  }
}
