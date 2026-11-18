import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/models.dart';
import 'auth_providers.dart';

/// The senior citizen / PWD IDs the customer saved at checkout.
final discountCardsProvider = FutureProvider.autoDispose<List<DiscountCard>>(
  (ref) => ref.watch(apiClientProvider).discountCards(),
);
