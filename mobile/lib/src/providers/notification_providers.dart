import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/models.dart';
import 'auth_providers.dart';

final notificationsProvider = FutureProvider.autoDispose<NotificationInbox>(
  (ref) => ref.watch(apiClientProvider).notifications(),
);
