import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/models.dart';
import 'auth_providers.dart';

class OrdersTabNotifier extends Notifier<String> {
  @override
  String build() => 'all';

  void select(String tab) => state = tab;
}

final ordersTabProvider = NotifierProvider<OrdersTabNotifier, String>(
  OrdersTabNotifier.new,
);

class OrdersSearchNotifier extends Notifier<String> {
  @override
  String build() => '';

  void setQuery(String value) => state = value.trim();
}

final ordersSearchProvider =
    NotifierProvider.autoDispose<OrdersSearchNotifier, String>(
      OrdersSearchNotifier.new,
    );

final ordersProvider = FutureProvider.autoDispose.family<OrdersResult, String>((
  ref,
  tab,
) {
  final search = ref.watch(ordersSearchProvider);
  return ref
      .watch(apiClientProvider)
      .orders(tab: tab, search: search.isEmpty ? null : search);
});

final orderProvider = FutureProvider.autoDispose.family<OrderDetail, String>(
  (ref, id) => ref.watch(apiClientProvider).order(id),
);
