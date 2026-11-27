<?php

namespace App\Http\Resources\Api\V1;

use App\Models\MedicationPlan;
use App\Support\MedicationSupply;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MedicationPlan
 */
class MedicationPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $summary = MedicationSupply::summary($this->resource, now());

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'product_name' => $this->product_name,
            'care_profile' => $this->careProfile === null ? null : ['id' => $this->careProfile->id, 'name' => $this->careProfile->name],
            'medicine_kind' => $this->medicine_kind,
            'dose_quantity' => $this->dose_quantity,
            'dose_unit' => $this->dose_unit,
            'dose_times' => $this->dose_times,
            'min_hours_between' => $this->min_hours_between,
            'max_doses_per_day' => $this->max_doses_per_day,
            'directions_note' => $this->directions_note,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'status' => $this->status,
            'show_name_on_lock_screen' => $this->show_name_on_lock_screen,
            'units_left' => $summary['units_left'],
            'run_out_on' => $summary['run_out_on'],
            'reorder_on' => $summary['reorder_on'],
            'count_prompt_on' => $summary['count_prompt_on'],
            'expires_on' => $this->expires_on?->toDateString(),
            'expires_before_run_out' => $this->expires_on !== null && $summary['run_out_on'] !== null && $this->expires_on->toDateString() < $summary['run_out_on'],
        ];
    }
}
