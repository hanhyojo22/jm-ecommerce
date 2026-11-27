<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Label directions that start a buyer's medicine reminder. Each kind needs what its reminders
 * are built from: a daily medicine its doses a day, a course its length, an as-needed medicine
 * its daily maximum.
 */
trait ValidatesMedicineDirections
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function medicineDirectionRules(bool $withPackUnits = true): array
    {
        return [
            'medicine_kind' => ['sometimes', Rule::in(['maintenance', 'course', 'as_needed', 'none'])],
            ...($withPackUnits ? ['pack_units' => ['nullable', 'numeric', 'min:0.01', 'max:9999']] : []),
            'dose_quantity' => ['nullable', 'numeric', 'min:0.01', 'max:999'],
            'dose_unit' => ['nullable', 'string', 'max:20'],
            'doses_per_day' => ['nullable', 'integer', 'min:1', 'max:12'],
            'course_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'min_hours_between' => ['nullable', 'integer', 'min:1', 'max:48'],
            'max_doses_per_day' => ['nullable', 'integer', 'min:1', 'max:24'],
            'directions_note' => ['nullable', 'string', 'max:200'],
        ];
    }

    public static function validateMedicineDirections(Validator $validator): void
    {
        $data = $validator->getData();
        $required = match ($data['medicine_kind'] ?? 'none') {
            'maintenance' => ['dose_quantity', 'dose_unit', 'doses_per_day'],
            'course' => ['dose_quantity', 'dose_unit', 'doses_per_day', 'course_days'],
            'as_needed' => ['dose_quantity', 'dose_unit', 'max_doses_per_day'],
            default => [],
        };

        foreach ($required as $field) {
            if (blank($data[$field] ?? null)) {
                $validator->errors()->add($field, 'Add the '.str_replace('_', ' ', $field).' for this kind of medicine.');
            }
        }
    }

    /**
     * Validates directions outside a form request (the pharmacist's per-item override).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function validatedMedicineDirections(array $input, bool $withPackUnits = true): array
    {
        $validator = ValidatorFactory::make($input, self::medicineDirectionRules($withPackUnits));
        $validator->after(fn (Validator $validator) => self::validateMedicineDirections($validator));

        return $validator->validate();
    }
}
