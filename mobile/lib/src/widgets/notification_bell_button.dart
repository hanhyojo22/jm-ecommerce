import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../providers/notification_providers.dart';

class NotificationBellButton extends ConsumerWidget {
  const NotificationBellButton({super.key, this.onPrimary = false});

  final bool onPrimary;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final count = ref.watch(notificationsProvider).value?.unreadCount ?? 0;

    return IconButton(
      tooltip: count > 0 ? 'Notifications, $count unread' : 'Notifications',
      onPressed: () => context.push('/notifications'),
      style: IconButton.styleFrom(
        minimumSize: const Size(48, 48),
        foregroundColor: onPrimary
            ? Theme.of(context).colorScheme.onPrimary
            : null,
      ),
      icon: Badge(
        isLabelVisible: count > 0,
        label: Text(count > 99 ? '99+' : '$count'),
        child: const Icon(Icons.notifications_outlined),
      ),
    );
  }
}
