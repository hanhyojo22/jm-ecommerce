import 'models.dart';

/// Calendar dates ("2026-12-13") parsed as local dates, so they never shift a
/// day across time zones.
DateTime? _date(dynamic value) {
  final parts = str(value).split('-');
  if (parts.length != 3) {
    return null;
  }
  final year = int.tryParse(parts[0]);
  final month = int.tryParse(parts[1]);
  final day = int.tryParse(parts[2]);

  return year == null || month == null || day == null
      ? null
      : DateTime(year, month, day);
}

String _number(double value) =>
    value == value.roundToDouble() ? '${value.toInt()}' : '$value';

/// The pharmacy's label directions for a purchase, or the buyer's own.
class MedicationDirections {
  const MedicationDirections({
    required this.medicineKind,
    this.packUnits,
    this.doseQuantity,
    this.doseUnit,
    this.dosesPerDay,
    this.courseDays,
    this.minHoursBetween,
    this.maxDosesPerDay,
    this.directionsNote,
  });

  final String medicineKind;
  final double? packUnits;
  final double? doseQuantity;
  final String? doseUnit;
  final int? dosesPerDay;
  final int? courseDays;
  final int? minHoursBetween;
  final int? maxDosesPerDay;
  final String? directionsNote;

  static MedicationDirections? fromJson(dynamic value) {
    if (value is! Map) {
      return null;
    }
    final json = mapOf(value);
    double? optionalDecimal(String key) =>
        json[key] == null ? null : decimal(json[key]);
    int? optionalInteger(String key) =>
        json[key] == null ? null : integer(json[key]);

    return MedicationDirections(
      medicineKind: str(json['medicine_kind'], 'maintenance'),
      packUnits: optionalDecimal('pack_units'),
      doseQuantity: optionalDecimal('dose_quantity'),
      doseUnit: json['dose_unit'] as String?,
      dosesPerDay: optionalInteger('doses_per_day'),
      courseDays: optionalInteger('course_days'),
      minHoursBetween: optionalInteger('min_hours_between'),
      maxDosesPerDay: optionalInteger('max_doses_per_day'),
      directionsNote: json['directions_note'] as String?,
    );
  }
}

class MedicationSuggestion {
  const MedicationSuggestion({
    required this.orderItemId,
    required this.productId,
    required this.productName,
    required this.quantity,
    this.directions,
  });

  final String orderItemId;
  final String? productId;
  final String productName;
  final int quantity;
  final MedicationDirections? directions;

  factory MedicationSuggestion.fromJson(Map<String, dynamic> json) =>
      MedicationSuggestion(
        orderItemId: str(json['order_item_id']),
        productId: json['product_id'] as String?,
        productName: str(json['product_name']),
        quantity: integer(json['quantity'], 1),
        directions: MedicationDirections.fromJson(json['directions']),
      );
}

class MedicationPlan {
  const MedicationPlan({
    required this.id,
    required this.productName,
    required this.medicineKind,
    required this.doseQuantity,
    required this.doseUnit,
    required this.doseTimes,
    required this.startsOn,
    required this.status,
    required this.unitsLeft,
    this.productId,
    this.productVariantId,
    this.profileId,
    this.profileName,
    this.minHoursBetween,
    this.maxDosesPerDay,
    this.directionsNote,
    this.endsOn,
    this.showNameOnLockScreen = false,
    this.runOutOn,
    this.reorderOn,
    this.countPromptOn,
    this.expiresOn,
    this.expiresBeforeRunOut = false,
    this.recentDoseDays,
  });

  final String id;
  final String? productId;
  final String? productVariantId;
  final String productName;
  final String? profileId;
  final String? profileName;
  final String medicineKind;
  final double doseQuantity;
  final String doseUnit;

  /// "HH:MM" in Philippine time.
  final List<String> doseTimes;
  final int? minHoursBetween;
  final int? maxDosesPerDay;
  final String? directionsNote;
  final DateTime startsOn;
  final DateTime? endsOn;
  final String status;
  final bool showNameOnLockScreen;
  final double unitsLeft;
  final DateTime? runOutOn;
  final DateTime? reorderOn;
  final DateTime? countPromptOn;
  final DateTime? expiresOn;
  final bool expiresBeforeRunOut;

  /// As-needed only: days in the last four with a dose.
  final int? recentDoseDays;

  bool get isAsNeeded => medicineKind == 'as_needed';
  bool get isActive => status == 'active';
  String get doseLabel => '${_number(doseQuantity)} $doseUnit';
  String get unitsLeftLabel => '${_number(unitsLeft)} ${_unitFor(unitsLeft)}';

  /// "6 tablets", "1 tablet"; measures (mL, mg) and units already plural stay as entered.
  String _unitFor(double amount) {
    final countable =
        RegExp(r'^[a-z]{3,}$').hasMatch(doseUnit) && !doseUnit.endsWith('s');
    return amount != 1 && countable ? '${doseUnit}s' : doseUnit;
  }

  factory MedicationPlan.fromJson(Map<String, dynamic> json) {
    final profile = mapOf(json['care_profile']);

    return MedicationPlan(
      id: str(json['id']),
      productId: json['product_id'] as String?,
      productVariantId: json['product_variant_id'] as String?,
      productName: str(json['product_name']),
      profileId: profile['id'] as String?,
      profileName: profile['name'] as String?,
      medicineKind: str(json['medicine_kind'], 'maintenance'),
      doseQuantity: decimal(json['dose_quantity'], 1),
      doseUnit: str(json['dose_unit']),
      doseTimes: (json['dose_times'] is List)
          ? (json['dose_times'] as List).map((time) => '$time').toList()
          : const [],
      minHoursBetween: json['min_hours_between'] == null
          ? null
          : integer(json['min_hours_between']),
      maxDosesPerDay: json['max_doses_per_day'] == null
          ? null
          : integer(json['max_doses_per_day']),
      directionsNote: json['directions_note'] as String?,
      startsOn: _date(json['starts_on']) ?? DateTime.now(),
      endsOn: _date(json['ends_on']),
      status: str(json['status'], 'active'),
      showNameOnLockScreen: boolean(json['show_name_on_lock_screen']),
      unitsLeft: decimal(json['units_left']),
      runOutOn: _date(json['run_out_on']),
      reorderOn: _date(json['reorder_on']),
      countPromptOn: _date(json['count_prompt_on']),
      expiresOn: _date(json['expires_on']),
      expiresBeforeRunOut: boolean(json['expires_before_run_out']),
      recentDoseDays: json['recent_dose_days'] == null
          ? null
          : integer(json['recent_dose_days']),
    );
  }
}

class CareProfile {
  const CareProfile({required this.id, required this.name, this.relation});

  final String id;
  final String name;
  final String? relation;

  factory CareProfile.fromJson(Map<String, dynamic> json) => CareProfile(
    id: str(json['id']),
    name: str(json['name']),
    relation: json['relation'] as String?,
  );
}

class CaregiverLink {
  const CaregiverLink({
    required this.id,
    required this.status,
    required this.invitedEmail,
    this.caregiverName,
    this.patientName,
    this.shareMedicineNames = false,
  });

  final String id;
  final String status;
  final String invitedEmail;
  final String? caregiverName;
  final String? patientName;
  final bool shareMedicineNames;

  bool get isAccepted => status == 'accepted';

  factory CaregiverLink.fromJson(Map<String, dynamic> json) => CaregiverLink(
    id: str(json['id']),
    status: str(json['status'], 'pending'),
    invitedEmail: str(json['invited_email']),
    caregiverName: json['caregiver_name'] as String?,
    patientName: json['patient_name'] as String?,
    shareMedicineNames: boolean(json['share_medicine_names']),
  );
}

class Caregivers {
  const Caregivers({required this.caregivers, required this.invitations});

  /// People this buyer asked to watch their doses.
  final List<CaregiverLink> caregivers;

  /// Invitations for this buyer to watch someone else's doses.
  final List<CaregiverLink> invitations;

  factory Caregivers.fromJson(Map<String, dynamic> json) => Caregivers(
    caregivers: listOf(json['caregivers']).map(CaregiverLink.fromJson).toList(),
    invitations: listOf(json['invitations'])
        .map(CaregiverLink.fromJson)
        .toList(),
  );
}

class DoseSlot {
  const DoseSlot({required this.scheduledFor, required this.status});

  final DateTime scheduledFor;

  /// 'taken', 'skipped' or 'not_marked'.
  final String status;
}

class DoseHistory {
  const DoseHistory({
    required this.planId,
    required this.productName,
    required this.medicineKind,
    required this.slots,
    required this.takenCount,
    this.adherence,
  });

  final String planId;
  final String productName;
  final String medicineKind;
  final List<DoseSlot> slots;
  final int takenCount;

  /// Taken ÷ scheduled doses, or null when nothing was scheduled.
  final double? adherence;

  factory DoseHistory.fromJson(Map<String, dynamic> json) => DoseHistory(
    planId: str(json['plan_id']),
    productName: str(json['product_name']),
    medicineKind: str(json['medicine_kind']),
    slots: listOf(json['slots'])
        .map(
          (slot) => DoseSlot(
            scheduledFor:
                DateTime.tryParse(str(slot['scheduled_for']))?.toLocal() ??
                DateTime.now(),
            status: str(slot['status'], 'not_marked'),
          ),
        )
        .toList(),
    takenCount: integer(json['taken_count']),
    adherence: json['adherence'] == null ? null : decimal(json['adherence']),
  );
}
