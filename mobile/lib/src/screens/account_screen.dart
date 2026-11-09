import 'package:clerk_flutter/clerk_flutter.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/formatters.dart';
import '../core/push_notifications.dart';
import '../providers/auth_providers.dart';
import '../widgets/common.dart';
import '../widgets/shell.dart';

class AccountScreen extends ConsumerWidget {
  const AccountScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final me = ref.watch(meProvider);

    return AsyncView(
      value: me,
      onRetry: () => ref.invalidate(meProvider),
      builder: (account) {
        final theme = Theme.of(context);

        return RefreshIndicator(
          onRefresh: () async {
            ref.invalidate(meProvider);
            await ref.read(meProvider.future);
          },
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
            children: [
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(18),
                  child: Row(
                    children: [
                      CircleAvatar(
                        radius: 32,
                        backgroundColor: theme.colorScheme.primaryContainer,
                        foregroundColor: theme.colorScheme.onPrimaryContainer,
                        child: Text(
                          initials(account.name),
                          style: const TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              account.name,
                              style: theme.textTheme.titleMedium?.copyWith(
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            Text(
                              account.email,
                              style: theme.textTheme.bodySmall,
                            ),
                            const SizedBox(height: 6),
                            Wrap(
                              spacing: 6,
                              runSpacing: 4,
                              crossAxisAlignment: WrapCrossAlignment.center,
                              children: [
                                Chip(
                                  visualDensity: VisualDensity.compact,
                                  label: Text(
                                    account.accountType.toUpperCase(),
                                  ),
                                ),
                                if (account.emailVerified) ...[
                                  Icon(
                                    Icons.verified_rounded,
                                    size: 17,
                                    color: theme.colorScheme.primary,
                                  ),
                                  Text(
                                    'Verified',
                                    style: theme.textTheme.bodySmall,
                                  ),
                                ],
                              ],
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 14),
              Card(
                child: Column(
                  children: [
                    ListTile(
                      leading: const Icon(Icons.location_on_outlined),
                      title: const Text('Delivery addresses'),
                      subtitle: const Text('Save the places you order to'),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () => context.push('/addresses'),
                    ),
                    const Divider(height: 1),
                    ListTile(
                      leading: const Icon(Icons.chat_bubble_outline_rounded),
                      title: const Text('Messages'),
                      subtitle: const Text('Chats with stores'),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () =>
                          ref.read(shellTabProvider.notifier).select(2),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              OutlinedButton.icon(
                onPressed: () async {
                  final auth = ClerkAuth.of(context);
                  await PushNotifications.unregister(
                    ref.read(apiClientProvider),
                  );
                  await auth.signOut();
                },
                icon: const Icon(Icons.logout_rounded),
                label: const Text('Sign out'),
              ),
            ],
          ),
        );
      },
    );
  }
}
