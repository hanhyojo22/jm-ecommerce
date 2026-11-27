<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MedicationPlanResource;
use App\Models\MedicationDoseLog;
use App\Models\MedicationPlan;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MedicationDoseController extends Controller
{
    /**
     * Records doses marked taken or skipped. The app may resend logs it saved offline; a repeated
     * client_uuid is ignored. For an as-needed medicine the reply warns when the newest dose came
     * sooner than the label's minimum gap or went over its daily maximum.
     */
    public function store(Request $request, string $plan): JsonResponse
    {
        $plan = $request->user()->medicationPlans()->findOrFail($plan);
        $data = $request->validate([
            'logs' => ['required', 'array', 'min:1', 'max:50'],
            'logs.*.client_uuid' => ['required', 'uuid'],
            'logs.*.status' => ['required', 'in:taken,skipped'],
            'logs.*.scheduled_for' => ['nullable', 'date'],
            'logs.*.logged_at' => ['required', 'date', 'before_or_equal:'.now()->addMinutes(5)->toIso8601String()],
            'logs.*.quantity' => ['nullable', 'numeric', 'min:0.01', 'max:999'],
        ]);

        MedicationDoseLog::query()->insertOrIgnore(array_map(fn (array $log): array => [
            'id' => (string) Str::ulid(),
            'medication_plan_id' => $plan->id,
            'client_uuid' => $log['client_uuid'],
            'status' => $log['status'],
            'scheduled_for' => isset($log['scheduled_for']) ? CarbonImmutable::parse($log['scheduled_for'])->setTimezone(config('app.timezone')) : null,
            'logged_at' => CarbonImmutable::parse($log['logged_at'])->setTimezone(config('app.timezone')),
            'quantity' => $log['quantity'] ?? $plan->dose_quantity,
            'created_at' => now(),
            'updated_at' => now(),
        ], $data['logs']));

        $plan->load(['doseLogs', 'careProfile']);

        return response()->json([
            'warning' => $this->limitWarning($plan),
            'plan' => MedicationPlanResource::make($plan)->resolve($request),
        ]);
    }

    private function limitWarning(MedicationPlan $plan): ?string
    {
        if ($plan->medicine_kind !== 'as_needed') {
            return null;
        }

        $taken = $plan->doseLogs->where('status', 'taken')->sortByDesc('logged_at')->values();
        $latest = $taken->first();
        if ($latest === null) {
            return null;
        }

        $previous = $taken->get(1);
        if ($plan->min_hours_between !== null && $previous !== null && $previous->logged_at->diffInMinutes($latest->logged_at) < $plan->min_hours_between * 60) {
            return 'too_soon';
        }

        $lastDay = $taken->filter(fn (MedicationDoseLog $log): bool => $log->logged_at->gt($latest->logged_at->subDay()));
        if ($plan->max_doses_per_day !== null && $lastDay->count() > $plan->max_doses_per_day) {
            return 'daily_max';
        }

        return null;
    }
}
