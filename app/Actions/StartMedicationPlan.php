<?php

namespace App\Actions;

use App\Models\MedicationPlan;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\MedicationSupply;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Starts a reminder plan from a delivered purchase, or tops up the buyer's active plan for the
 * same medicine. Each order item's units count once.
 */
class StartMedicationPlan
{
    /**
     * @param  array<string, mixed>  $data  validated plan fields
     * @return array{MedicationPlan, bool} the plan and whether it was newly created
     */
    public function handle(User $buyer, array $data): array
    {
        return DB::transaction(function () use ($buyer, $data): array {
            $item = $this->deliveredItem($buyer, $data['order_item_id']);
            $packUnits = $item->medicationDirections()['pack_units'] ?? null;
            $units = $packUnits !== null ? (float) $packUnits * $item->quantity : ($data['units_on_hand'] ?? null);
            if ($units === null) {
                throw ValidationException::withMessages(['units_on_hand' => 'Enter how many you received.']);
            }
            $expiresOn = $item->inventoryAllocations()
                ->join('inventory_batches', 'inventory_batches.id', '=', 'inventory_allocations.inventory_batch_id')
                ->min('inventory_batches.expires_at');
            $expiresOn = $expiresOn === null ? null : CarbonImmutable::parse($expiresOn)->toDateString();

            $plan = $buyer->medicationPlans()
                ->where('product_id', $item->product_id)
                ->where('care_profile_id', $data['care_profile_id'] ?? null)
                ->where('status', 'active')
                ->with('doseLogs')
                ->lockForUpdate()
                ->first();

            if ($plan !== null) {
                $plan->update([
                    'units_anchor' => MedicationSupply::summary($plan, now())['units_left'] + $units,
                    'anchored_at' => now(),
                    'expires_on' => collect([$plan->expires_on?->toDateString(), $expiresOn])->filter()->min(),
                ]);
                $plan->orderItems()->attach($item->id, ['units' => $units]);

                return [$plan, false];
            }

            $startsOn = CarbonImmutable::parse($data['starts_on']);
            $plan = $buyer->medicationPlans()->create([
                ...collect($data)->only(['care_profile_id', 'medicine_kind', 'dose_quantity', 'dose_unit', 'min_hours_between', 'max_doses_per_day', 'directions_note', 'show_name_on_lock_screen'])->all(),
                'dose_times' => $data['medicine_kind'] === 'as_needed' ? [] : array_values(array_unique($data['dose_times'] ?? [])),
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'product_name' => $item->product_name,
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $data['medicine_kind'] === 'course' ? $startsOn->addDays($data['course_days'] - 1)->toDateString() : null,
                'units_anchor' => $units,
                'anchored_at' => now(),
                'expires_on' => $expiresOn,
            ]);
            $plan->orderItems()->attach($item->id, ['units' => $units]);

            return [$plan, true];
        });
    }

    private function deliveredItem(User $buyer, string $orderItemId): OrderItem
    {
        $item = OrderItem::query()
            ->whereKey($orderItemId)
            ->whereHas('sellerOrder', fn ($sellerOrder) => $sellerOrder->where('status', 'delivered')
                ->whereHas('order', fn ($order) => $order->where('user_id', $buyer->id)))
            ->with('product')
            ->lockForUpdate()
            ->first();
        abort_if($item === null, 404);

        if ($item->medicationPlans()->exists()) {
            throw ValidationException::withMessages(['order_item_id' => 'This purchase is already in your reminders.']);
        }

        return $item;
    }
}
