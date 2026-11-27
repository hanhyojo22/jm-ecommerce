<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\StartMedicationPlan;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MedicationPlanResource;
use App\Models\MedicationPlan;
use App\Models\OrderItem;
use App\Support\MedicationSupply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class MedicationPlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $plans = $request->user()->medicationPlans()
            ->whereIn('status', ['active', 'paused'])
            ->with(['doseLogs', 'careProfile'])
            ->oldest()
            ->get();

        return response()->json(['data' => MedicationPlanResource::collection($plans)->resolve($request)]);
    }

    /**
     * Delivered purchases from the last 120 days not yet in a plan, medicines with label
     * directions first. Items without directions are offered too: the buyer enters the dose.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $items = OrderItem::query()
            ->whereHas('sellerOrder', fn ($sellerOrder) => $sellerOrder->where('status', 'delivered')
                ->where('delivered_at', '>=', now()->subDays(120))
                ->whereHas('order', fn ($order) => $order->where('user_id', $request->user()->id)))
            ->whereDoesntHave('medicationPlans')
            ->with('product')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (OrderItem $item): array => [
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'directions' => $item->medicationDirections(),
            ])
            ->sortBy(fn (array $suggestion): int => $suggestion['directions'] === null ? 1 : 0)
            ->values();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request, StartMedicationPlan $start): JsonResponse
    {
        $data = $request->validate([
            'order_item_id' => ['required', 'string', 'ulid'],
            'care_profile_id' => ['nullable', 'string', Rule::exists('care_profiles', 'id')->where('user_id', $request->user()->id)],
            'medicine_kind' => ['required', Rule::in(MedicationPlan::KINDS)],
            'course_days' => ['required_if:medicine_kind,course', 'nullable', 'integer', 'min:1', 'max:365'],
            'units_on_hand' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'starts_on' => ['required', 'date'],
            ...$this->doseRules(),
        ]);

        [$plan, $created] = $start->handle($request->user(), $data);

        return MedicationPlanResource::make($plan->load(['doseLogs', 'careProfile']))->response()->setStatusCode($created ? 201 : 200);
    }

    /**
     * A new dose or schedule only applies from now: the supply is re-anchored first so past
     * doses are counted with the schedule they were taken on.
     */
    public function update(Request $request, string $plan): MedicationPlanResource
    {
        $plan = $this->ownPlan($request, $plan);
        $data = $request->validate([
            'care_profile_id' => ['sometimes', 'nullable', 'string', Rule::exists('care_profiles', 'id')->where('user_id', $request->user()->id)],
            'status' => ['sometimes', Rule::in(['active', 'paused'])],
            ...collect($this->doseRules())->map(fn (array $rules): array => ['sometimes', ...array_diff($rules, ['required'])])->all(),
        ]);

        if (array_key_exists('dose_times', $data) || array_key_exists('dose_quantity', $data)) {
            $data['units_anchor'] = MedicationSupply::summary($plan, now())['units_left'];
            $data['anchored_at'] = now();
        }
        $plan->update($data);

        return MedicationPlanResource::make($plan->fresh(['doseLogs', 'careProfile']));
    }

    public function destroy(Request $request, string $plan): Response
    {
        $this->ownPlan($request, $plan)->delete();

        return response()->noContent();
    }

    /**
     * The buyer's answer to "about how many are left?" becomes the new starting point.
     */
    public function count(Request $request, string $plan): MedicationPlanResource
    {
        $plan = $this->ownPlan($request, $plan);
        $data = $request->validate(['units_left' => ['required', 'numeric', 'min:0', 'max:99999']]);
        $plan->update(['units_anchor' => $data['units_left'], 'anchored_at' => now(), 'last_count_prompt_at' => now()]);

        return MedicationPlanResource::make($plan->fresh(['doseLogs', 'careProfile']));
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function doseRules(): array
    {
        return [
            'dose_quantity' => ['required', 'numeric', 'min:0.01', 'max:999'],
            'dose_unit' => ['required', 'string', 'max:20'],
            'dose_times' => ['required_unless:medicine_kind,as_needed', 'array', 'max:12'],
            'dose_times.*' => ['date_format:H:i', 'distinct'],
            'min_hours_between' => ['nullable', 'integer', 'min:1', 'max:48'],
            'max_doses_per_day' => ['nullable', 'integer', 'min:1', 'max:24'],
            'directions_note' => ['nullable', 'string', 'max:200'],
            'show_name_on_lock_screen' => ['boolean'],
        ];
    }

    private function ownPlan(Request $request, string $plan): MedicationPlan
    {
        return $request->user()->medicationPlans()->with('doseLogs')->findOrFail($plan);
    }
}
