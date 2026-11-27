<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MedicationDoseLog;
use App\Models\MedicationPlan;
use App\Support\MedicationSupply;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MedicationHistoryController extends Controller
{
    /**
     * Each past dose of one person's medicines (the buyer, or one care profile), for showing a doctor.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'care_profile_id' => ['nullable', 'string', Rule::exists('care_profiles', 'id')->where('user_id', $request->user()->id)],
            'days' => ['nullable', 'integer', Rule::in([30, 90])],
        ]);
        $now = CarbonImmutable::now();
        $from = $now->subDays($data['days'] ?? 30);

        $plans = $request->user()->medicationPlans()
            ->where('care_profile_id', $data['care_profile_id'] ?? null)
            ->with(['doseLogs' => fn ($logs) => $logs->where('logged_at', '>=', $from)])
            ->oldest()
            ->get()
            ->map(function (MedicationPlan $plan) use ($from, $now): array {
                $statuses = $plan->doseLogs->whereNotNull('scheduled_for')
                    ->mapWithKeys(fn (MedicationDoseLog $log): array => [$log->scheduled_for->getTimestamp() => $log->status]);
                $slots = array_map(fn (CarbonImmutable $slot): array => [
                    'scheduled_for' => $slot->toIso8601String(),
                    'status' => $statuses[$slot->getTimestamp()] ?? 'not_marked',
                ], MedicationSupply::slots($plan, $from, $now));
                $taken = count(array_filter($slots, fn (array $slot): bool => $slot['status'] === 'taken'));

                return [
                    'plan_id' => $plan->id,
                    'product_name' => $plan->product_name,
                    'medicine_kind' => $plan->medicine_kind,
                    'slots' => $slots,
                    'taken_count' => $plan->medicine_kind === 'as_needed' ? $plan->doseLogs->where('status', 'taken')->count() : $taken,
                    'adherence' => $slots === [] ? null : round($taken / count($slots), 2),
                ];
            });

        return response()->json(['data' => $plans]);
    }
}
