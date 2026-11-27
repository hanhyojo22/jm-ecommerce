import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/models.dart';
import 'auth_providers.dart';

final addressesProvider = FutureProvider.autoDispose<List<Address>>(
  (ref) => ref.watch(apiClientProvider).addresses(),
);

/// Provinces and their cities, loaded once per app run.
final provincesProvider = FutureProvider<Map<String, List<String>>>(
  (ref) => ref.watch(apiClientProvider).provinces(),
);

/// One city's barangays, keyed by (province, city).
final barangaysProvider = FutureProvider.autoDispose
    .family<List<String>, (String, String)>(
      (ref, place) =>
          ref.watch(apiClientProvider).barangays(place.$1, place.$2),
    );
