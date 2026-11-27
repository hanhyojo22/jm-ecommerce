import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/medication_alarms.dart';
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

/// Keeps the phone's medicine alarms in step with the server: rebuilt each app
/// session and whenever the plans change; cleared when reminders are off.
final medicationAlarmSyncProvider = FutureProvider<void>((ref) async {
  final account = await ref.watch(meProvider.future);
  if (!account.medicationReminders) {
    try {
      await MedicationAlarms.cancelMedicineAlarms();
    } catch (_) {
      // No plugin (tests, unsupported platform): nothing is scheduled.
    }
    return;
  }
  await MedicationAlarms.sync(await ref.watch(medicationPlansProvider.future));
});
