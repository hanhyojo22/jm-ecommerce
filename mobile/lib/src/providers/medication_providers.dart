import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/medication.dart';
import 'auth_providers.dart';

final medicationPlansProvider =
    FutureProvider.autoDispose<List<MedicationPlan>>(
      (ref) => ref.watch(apiClientProvider).medicationPlans(),
    );

final medicationSuggestionsProvider =
    FutureProvider.autoDispose<List<MedicationSuggestion>>(
      (ref) => ref.watch(apiClientProvider).medicationSuggestions(),
    );

final careProfilesProvider = FutureProvider.autoDispose<List<CareProfile>>(
  (ref) => ref.watch(apiClientProvider).careProfiles(),
);

final caregiversProvider = FutureProvider.autoDispose<Caregivers>(
  (ref) => ref.watch(apiClientProvider).caregivers(),
);

final medicationHistoryProvider = FutureProvider.autoDispose
    .family<List<DoseHistory>, ({String? profileId, int days})>(
      (ref, query) => ref
          .watch(apiClientProvider)
          .medicationHistory(profileId: query.profileId, days: query.days),
    );
