import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/models.dart';
import 'auth_providers.dart';

final addressesProvider = FutureProvider.autoDispose<List<Address>>(
  (ref) => ref.watch(apiClientProvider).addresses(),
);
