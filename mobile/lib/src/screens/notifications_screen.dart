import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/formatters.dart';
import '../models/models.dart';
import '../providers/auth_providers.dart';
import '../providers/notification_providers.dart';
import '../widgets/common.dart';

class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  Future<void> _open(
    BuildContext context,
    WidgetRef ref,
    AppNotification item,
  ) async {
    if (item.readAt == null) {
      await ref.read(apiClientProvider).markNotificationRead(item.id);
      ref.invalidate(notificationsProvider);
    }

    if (context.mounted && item.type == 'order' && item.orderId != null) {
      context.push('/orders/${item.orderId}');
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final inbox = ref.watch(notificationsProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: [
          if ((inbox.value?.unreadCount ?? 0) > 0)
            TextButton(
              onPressed: () async {
                try {
                  await ref.read(apiClientProvider).markAllNotificationsRead();
                  ref.invalidate(notificationsProvider);
                } catch (_) {
                  if (context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text(
                          'Could not mark notifications as read. Try again.',
                        ),
                      ),
                    );
                  }
                }
              },
              child: const Text('Mark all read'),
            ),
        ],
      ),
      body: AsyncView(
        value: inbox,
        onRetry: () => ref.invalidate(notificationsProvider),
        builder: (result) => result.items.isEmpty
            ? const EmptyState(
                icon: Icons.notifications_none_rounded,
                title: 'No notifications yet',
                message: 'Order updates will appear here.',
              )
            : RefreshIndicator(
                onRefresh: () async {
                  ref.invalidate(notificationsProvider);
                  await ref.read(notificationsProvider.future);
                },
                child: ListView.separated(
                  physics: const AlwaysScrollableScrollPhysics(),
                  itemCount: result.items.length,
                  separatorBuilder: (_, _) => const Divider(height: 1),
                  itemBuilder: (context, index) {
                    final item = result.items[index];
                    return ListTile(
                      leading: Icon(
                        item.readAt == null
                            ? Icons.notifications_active_rounded
                            : Icons.notifications_none_rounded,
                      ),
                      title: Text(item.title),
                      subtitle: Text(
                        '${item.body}\n${timeAgo(item.createdAt)}',
                      ),
                      isThreeLine: true,
                      onTap: () async {
                        try {
                          await _open(context, ref, item);
                        } catch (_) {
                          if (context.mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(
                                content: Text(
                                  'Could not open notification. Try again.',
                                ),
                              ),
                            );
                          }
                        }
                      },
                    );
                  },
                ),
              ),
      ),
    );
  }
}
