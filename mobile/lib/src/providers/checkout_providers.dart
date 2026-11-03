import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/models.dart';
import 'auth_providers.dart';

final checkoutQuoteProvider = FutureProvider.autoDispose<CheckoutQuote>(
  (ref) => ref.watch(apiClientProvider).checkoutQuote(),
);
