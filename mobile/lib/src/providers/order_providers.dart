import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/models.dart';
import 'auth_providers.dart';

final ordersProvider = FutureProvider.autoDispose.family<OrdersResult, String>(
  (ref, tab) => ref.watch(apiClientProvider).orders(tab: tab),
);

final orderProvider = FutureProvider.autoDispose.family<OrderDetail, String>(
  (ref, id) => ref.watch(apiClientProvider).order(id),
);
