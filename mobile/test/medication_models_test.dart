import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/models/medication.dart';

void main() {
  test('a plan parses whole and decimal numbers and missing dates', () {
    final plan = MedicationPlan.fromJson({
      'id': 'p1',
      'product_id': 'prod1',
      'product_variant_id': null,
      'product_name': 'Paracetamol 120 mg/5 mL Syrup',
      'care_profile': {'id': 'c1', 'name': 'Lola'},
      'medicine_kind': 'maintenance',
      'dose_quantity': '2.50',
      'dose_unit': 'mL',
      'dose_times': ['08:00', '20:00'],
      'min_hours_between': null,
      'max_doses_per_day': null,
      'directions_note': 'After meals',
      'starts_on': '2026-12-01',
      'ends_on': null,
      'status': 'active',
      'show_name_on_lock_screen': false,
      'units_left': 60,
      'run_out_on': '2026-12-13',
      'reorder_on': '2026-12-08',
      'count_prompt_on': null,
      'expires_on': null,
      'expires_before_run_out': false,
      'answered_slots': ['2026-12-10T08:00:00+08:00'],
    });

    expect(plan.doseQuantity, 2.5);
    expect(plan.unitsLeft, 60.0);
    expect(plan.doseTimes, ['08:00', '20:00']);
    expect(plan.profileName, 'Lola');
    expect(plan.runOutOn, DateTime(2026, 12, 13));
    expect(plan.countPromptOn, isNull);
    expect(plan.endsOn, isNull);
    expect(plan.isAsNeeded, isFalse);
    expect(plan.doseLabel, '2.5 mL');
    expect(plan.answeredSlots, [DateTime(2026, 12, 10, 8)]);
  });

  test('supply labels use plural units except for measures like mL', () {
    MedicationPlan withSupply(double left, String unit) =>
        MedicationPlan.fromJson({
          'id': 'p',
          'product_name': 'Medicine',
          'dose_quantity': '1',
          'dose_unit': unit,
          'dose_times': ['08:00'],
          'starts_on': '2026-12-01',
          'units_left': left,
        });

    expect(withSupply(6, 'tablet').unitsLeftLabel, '6 tablets');
    expect(withSupply(1, 'tablet').unitsLeftLabel, '1 tablet');
    expect(withSupply(2.5, 'capsule').unitsLeftLabel, '2.5 capsules');
    expect(withSupply(60, 'mL').unitsLeftLabel, '60 mL');
    expect(withSupply(3, 'drops').unitsLeftLabel, '3 drops');
  });

  test('a suggestion without directions keeps them null', () {
    final suggestion = MedicationSuggestion.fromJson({
      'order_item_id': 'i1',
      'product_id': 'prod1',
      'product_name': 'Biogesic',
      'quantity': 2,
      'directions': null,
    });

    expect(suggestion.directions, isNull);
    expect(suggestion.quantity, 2);
  });
}
