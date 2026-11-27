<?php

namespace Tests\Feature;

use App\Actions\DeleteCustomerAccount;
use App\Models\CaregiverLink;
use App\Models\InventoryBatch;
use App\Models\MedicationPlan;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MedicationPlanApiTest extends TestCase
{
    use RefreshDatabase;

    private const DIRECTIONS = [
        'medicine_kind' => 'maintenance', 'pack_units' => 30, 'dose_quantity' => 1, 'dose_unit' => 'tablet', 'doses_per_day' => 1,
    ];

    public function test_plans_need_consent(): void
    {
        [$buyer, $item] = $this->deliveredItem();

        $this->actingAs($buyer)->postJson(route('api.v1.medication-plans.store'), $this->planBody($item))->assertForbidden();
        $this->putJson(route('api.v1.medication-consent.store'))->assertNoContent();
        $this->getJson(route('api.v1.me'))->assertJsonPath('data.medication_reminders', true);
        $this->postJson(route('api.v1.medication-plans.store'), $this->planBody($item))->assertCreated();
    }

    public function test_suggestions_list_delivered_medicine_once(): void
    {
        [$buyer, $item] = $this->deliveredItem();
        [, $pending] = $this->deliveredItem(buyer: $buyer, sellerOrderStatus: 'pending');
        $this->consent($buyer);

        $this->getJson(route('api.v1.medication-plans.suggestions'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_item_id', $item->id)
            ->assertJsonPath('data.0.directions.medicine_kind', 'maintenance')
            ->assertJsonMissing(['order_item_id' => $pending->id]);

        $this->postJson(route('api.v1.medication-plans.store'), $this->planBody($item))->assertCreated();

        $this->getJson(route('api.v1.medication-plans.suggestions'))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_items_that_are_not_medicine_are_not_suggested(): void
    {
        [$buyer] = $this->deliveredItem(directions: ['medicine_kind' => 'none']);
        $this->consent($buyer);

        $this->getJson(route('api.v1.medication-plans.suggestions'))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_starting_a_plan_from_an_order_item_uses_pack_units_and_batch_expiry(): void
    {
        [$buyer, $item] = $this->deliveredItem(quantity: 2);
        $batch = InventoryBatch::create([
            'store_id' => $item->sellerOrder->store_id, 'product_id' => $item->product_id, 'variant_key' => 'default', 'lot_number' => 'L1',
            'expires_at' => '2027-01-10', 'received_quantity' => 10, 'remaining_quantity' => 8, 'status' => 'active',
        ]);
        $item->inventoryAllocations()->create(['inventory_batch_id' => $batch->id, 'quantity' => 2]);
        $this->consent($buyer);

        $this->postJson(route('api.v1.medication-plans.store'), $this->planBody($item))
            ->assertCreated()
            ->assertJsonPath('data.units_left', 60)
            ->assertJsonPath('data.expires_on', '2027-01-10')
            ->assertJsonPath('data.product_name', 'Amlodipine 5 mg Tablet');
    }

    public function test_a_repurchase_tops_up_the_active_plan_once(): void
    {
        [$buyer, $first] = $this->deliveredItem();
        [, $second] = $this->deliveredItem(buyer: $buyer, product: $first->product);
        $this->consent($buyer);

        $planId = $this->postJson(route('api.v1.medication-plans.store'), $this->planBody($first))->assertCreated()->json('data.id');

        $this->postJson(route('api.v1.medication-plans.store'), $this->planBody($second))
            ->assertOk()
            ->assertJsonPath('data.id', $planId)
            ->assertJsonPath('data.units_left', 60);

        $this->postJson(route('api.v1.medication-plans.store'), $this->planBody($second))->assertUnprocessable()->assertJsonPath('error.fields.order_item_id.0', 'This purchase is already in your reminders.');
        $this->assertSame(1, MedicationPlan::query()->count());
        $this->assertSame('60.00', MedicationPlan::query()->first()->units_anchor);
    }

    public function test_only_the_buyers_delivered_items_can_start_a_plan(): void
    {
        [$buyer] = $this->deliveredItem();
        [, $someoneElses] = $this->deliveredItem();
        [, $undelivered] = $this->deliveredItem(buyer: $buyer, sellerOrderStatus: 'shipped');
        $this->consent($buyer);

        $this->postJson(route('api.v1.medication-plans.store'), $this->planBody($someoneElses))->assertNotFound();
        $this->postJson(route('api.v1.medication-plans.store'), $this->planBody($undelivered))->assertNotFound();
        $this->assertSame(0, MedicationPlan::query()->count());
    }

    public function test_a_buyer_adds_a_delivered_medicine_without_directions_manually(): void
    {
        [$buyer, $item] = $this->deliveredItem(directions: ['medicine_kind' => 'none', 'dosage_form' => 'Tablet']);
        $this->consent($buyer);

        $this->getJson(route('api.v1.medication-plans.suggestions'))->assertJsonPath('data.0.directions', null);
        $this->postJson(route('api.v1.medication-plans.store'), $this->planBody($item))->assertUnprocessable()->assertJsonPath('error.fields.units_on_hand.0', 'Enter how many you received.');

        $this->postJson(route('api.v1.medication-plans.store'), $this->planBody($item, ['units_on_hand' => 20]))
            ->assertCreated()
            ->assertJsonPath('data.units_left', 20);
    }

    public function test_dose_logs_are_idempotent_and_warn_for_as_needed_limits(): void
    {
        [$buyer, $item] = $this->deliveredItem(directions: ['medicine_kind' => 'as_needed', 'pack_units' => 10, 'dose_quantity' => 1, 'min_hours_between' => 4, 'max_doses_per_day' => 4]);
        $this->consent($buyer);
        $planId = $this->postJson(route('api.v1.medication-plans.store'), $this->planBody($item, ['medicine_kind' => 'as_needed', 'dose_times' => [], 'min_hours_between' => 4, 'max_doses_per_day' => 4]))
            ->assertCreated()->json('data.id');
        $first = ['client_uuid' => (string) Str::uuid(), 'status' => 'taken', 'logged_at' => now()->toIso8601String()];

        $this->postJson(route('api.v1.medication-plans.doses.store', $planId), ['logs' => [$first]])->assertOk()->assertJsonPath('warning', null);
        $this->postJson(route('api.v1.medication-plans.doses.store', $planId), ['logs' => [$first]])->assertOk();
        $this->assertDatabaseCount('medication_dose_logs', 1);

        $this->travel(1)->hours();
        $second = ['client_uuid' => (string) Str::uuid(), 'status' => 'taken', 'logged_at' => now()->toIso8601String()];
        $this->postJson(route('api.v1.medication-plans.doses.store', $planId), ['logs' => [$second]])
            ->assertOk()
            ->assertJsonPath('warning', 'too_soon')
            ->assertJsonPath('plan.units_left', 8);
    }

    public function test_as_needed_plans_report_how_many_recent_days_had_a_dose(): void
    {
        [$buyer, $item] = $this->deliveredItem(directions: ['medicine_kind' => 'as_needed', 'pack_units' => 20, 'dose_quantity' => 1, 'max_doses_per_day' => 4]);
        $this->consent($buyer);
        $planId = $this->postJson(route('api.v1.medication-plans.store'), $this->planBody($item, ['medicine_kind' => 'as_needed', 'dose_times' => []]))->json('data.id');
        foreach ([0, 1, 2] as $day) {
            $this->travel($day === 0 ? 0 : 1)->days();
            $this->postJson(route('api.v1.medication-plans.doses.store', $planId), ['logs' => [
                ['client_uuid' => (string) Str::uuid(), 'status' => 'taken', 'logged_at' => now()->toIso8601String()],
            ]])->assertOk();
        }

        $this->getJson(route('api.v1.medication-plans.index'))->assertJsonPath('data.0.recent_dose_days', 3);
    }

    public function test_count_resets_the_supply(): void
    {
        [$buyer, $item] = $this->deliveredItem();
        $this->consent($buyer);
        $planId = $this->postJson(route('api.v1.medication-plans.store'), $this->planBody($item))->json('data.id');

        $this->postJson(route('api.v1.medication-plans.count', $planId), ['units_left' => 8])
            ->assertOk()
            ->assertJsonPath('data.units_left', 8)
            ->assertJsonPath('data.run_out_on', now('Asia/Manila')->addDays(8)->toDateString());
    }

    public function test_history_marks_unlogged_slots_and_computes_adherence(): void
    {
        $this->travelTo(now('Asia/Manila')->setTime(12, 0));
        [$buyer, $item] = $this->deliveredItem();
        $this->consent($buyer);
        $planId = $this->postJson(route('api.v1.medication-plans.store'), $this->planBody($item, [
            'dose_times' => ['08:00'], 'starts_on' => now('Asia/Manila')->subDay()->toDateString(),
        ]))->json('data.id');
        $this->postJson(route('api.v1.medication-plans.doses.store', $planId), ['logs' => [[
            'client_uuid' => (string) Str::uuid(), 'status' => 'taken', 'logged_at' => now()->toIso8601String(),
            'scheduled_for' => now('Asia/Manila')->setTime(8, 0)->toIso8601String(),
        ]]])->assertOk();

        $this->getJson(route('api.v1.medication-history', ['days' => 30]))
            ->assertOk()
            ->assertJsonPath('data.0.adherence', 0.5)
            ->assertJsonPath('data.0.slots.0.status', 'not_marked')
            ->assertJsonPath('data.0.slots.1.status', 'taken');
    }

    public function test_another_users_plan_is_not_found(): void
    {
        [$owner, $item] = $this->deliveredItem();
        $this->consent($owner);
        $planId = $this->postJson(route('api.v1.medication-plans.store'), $this->planBody($item))->json('data.id');
        $intruder = User::factory()->create(['email_verified_at' => now(), 'medication_reminders_consent_at' => now()]);

        $this->actingAs($intruder)->patchJson(route('api.v1.medication-plans.update', $planId), ['status' => 'paused'])->assertNotFound();
        $this->postJson(route('api.v1.medication-plans.doses.store', $planId), ['logs' => [['client_uuid' => (string) Str::uuid(), 'status' => 'taken', 'logged_at' => now()->toIso8601String()]]])->assertNotFound();
        $this->deleteJson(route('api.v1.medication-plans.destroy', $planId))->assertNotFound();
        $this->assertDatabaseHas('medication_plans', ['id' => $planId, 'status' => 'active']);
    }

    public function test_withdrawing_consent_deletes_everything(): void
    {
        [$buyer, $item] = $this->deliveredItem();
        $this->consent($buyer);
        $this->postJson(route('api.v1.care-profiles.store'), ['name' => 'Lola', 'relation' => 'Grandmother'])->assertCreated();
        $this->postJson(route('api.v1.medication-plans.store'), $this->planBody($item))->assertCreated();

        $this->deleteJson(route('api.v1.medication-consent.destroy'))->assertNoContent();

        $this->assertDatabaseCount('medication_plans', 0);
        $this->assertDatabaseCount('care_profiles', 0);
        $this->assertNull($buyer->fresh()->medication_reminders_consent_at);
        $this->getJson(route('api.v1.medication-plans.index'))->assertForbidden();
    }

    public function test_deleting_the_account_deletes_medication_data(): void
    {
        [$buyer, $item] = $this->deliveredItem();
        $this->consent($buyer);
        $this->postJson(route('api.v1.medication-plans.store'), $this->planBody($item))->assertCreated();
        $caregiverFor = User::factory()->create();
        CaregiverLink::query()->forceCreate(['patient_user_id' => $caregiverFor->id, 'caregiver_user_id' => $buyer->id, 'invited_email' => $buyer->email, 'status' => 'accepted']);

        app(DeleteCustomerAccount::class)->handle($buyer, '127.0.0.1');

        $this->assertDatabaseCount('medication_plans', 0);
        $this->assertDatabaseCount('caregiver_links', 0);
        $this->assertNull($buyer->fresh()->medication_reminders_consent_at);
    }

    private function consent(User $buyer): void
    {
        $this->actingAs($buyer)->putJson(route('api.v1.medication-consent.store'))->assertNoContent();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function planBody(OrderItem $item, array $overrides = []): array
    {
        return [
            'order_item_id' => $item->id, 'medicine_kind' => 'maintenance', 'dose_quantity' => 1, 'dose_unit' => 'tablet',
            'dose_times' => ['08:00'], 'starts_on' => now('Asia/Manila')->toDateString(), ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $directions
     * @return array{User, OrderItem}
     */
    private function deliveredItem(?User $buyer = null, string $sellerOrderStatus = 'delivered', int $quantity = 1, array $directions = self::DIRECTIONS, ?Product $product = null): array
    {
        $buyer ??= User::factory()->create(['email_verified_at' => now()]);
        $suffix = Str::lower(Str::random(8));
        if ($product === null) {
            $tenant = Tenant::create(['name' => "Botika {$suffix}", 'slug' => "botika-{$suffix}", 'status' => 'active']);
            $store = Store::create([
                'tenant_id' => $tenant->id, 'name' => "Botika {$suffix}", 'slug' => "botika-{$suffix}", 'phone' => '+639171234567',
                'address_line' => '10 Rizal Street', 'city' => 'Cebu', 'status' => 'active',
            ]);
            $product = Product::create([
                'tenant_id' => $tenant->id, 'store_id' => $store->id, 'name' => 'Amlodipine 5 mg Tablet', 'slug' => "amlodipine-{$suffix}",
                'sku' => "AML-{$suffix}", 'price' => '12.00', 'stock_quantity' => 50, 'status' => 'active', ...$directions,
            ]);
        }
        $order = Order::create([
            'user_id' => $buyer->id, 'number' => 'JM-'.Str::upper(Str::random(8)), 'status' => $sellerOrderStatus,
            'recipient_name' => $buyer->name, 'phone' => '+639171234567', 'address_line' => '100 Market Street', 'city' => 'Cebu',
            'subtotal' => '12.00', 'shipping_amount' => '0.00', 'total' => '12.00',
        ]);
        $sellerOrder = $order->sellerOrders()->create([
            'tenant_id' => $product->tenant_id, 'store_id' => $product->store_id, 'status' => $sellerOrderStatus,
            'subtotal' => '12.00', 'shipping_amount' => '0.00', 'total' => '12.00',
            'delivered_at' => $sellerOrderStatus === 'delivered' ? now() : null,
        ]);
        $item = $sellerOrder->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku,
            'unit_price' => '12.00', 'quantity' => $quantity, 'line_total' => '12.00',
        ]);

        return [$buyer, $item];
    }
}
