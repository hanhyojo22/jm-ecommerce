<?php

namespace Tests\Feature;

use App\Actions\RecordSellerEarning;
use App\Models\Order;
use App\Models\ReturnCase;
use App\Models\SellerEarning;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerEarningTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_can_only_view_earnings_for_their_store(): void
    {
        [$firstStore, $firstSeller] = $this->storeWithSeller('Northwind Goods');
        [$secondStore] = $this->storeWithSeller('Harbor Market');
        $firstEarning = $this->earningFor($firstStore, 'JM-EARNING-001', '125.00');
        $secondEarning = $this->earningFor($secondStore, 'JM-EARNING-002', '250.00');

        $this->actingAs($firstSeller)
            ->get(route('seller.earnings.index'))
            ->assertSee($firstEarning->sellerOrder->order->number)
            ->assertDontSee($secondEarning->sellerOrder->order->number)
            ->assertSee('125.00');
    }

    public function test_earnings_page_summarises_delivered_sales_commission_and_cod_collection(): void
    {
        [$store, $seller] = $this->storeWithSeller('Northwind Goods');
        $store->tenant->update(['platform_fee_accepted_at' => now()->subDay()]);

        $collected = $this->deliveredSellerOrderFor($store, 'JM-SUMMARY-001', '125.00', collected: true);
        $uncollected = $this->deliveredSellerOrderFor($store, 'JM-SUMMARY-002', '75.00', collected: false);
        $this->sellerOrderFor($store, 'JM-SUMMARY-PENDING', '999.00', 'pending');

        $action = app(RecordSellerEarning::class);
        $action->handle($collected->fresh());
        $action->handle($uncollected->fresh());

        $this->actingAs($seller)
            ->get(route('seller.earnings.index'))
            ->assertOk()
            ->assertViewHas('deliveredSales', fn ($value) => bccomp((string) $value, '200.00', 2) === 0)
            ->assertViewHas('commissionTotal', fn ($value) => bccomp((string) $value, '2.00', 2) === 0)
            ->assertViewHas('netEarnings', fn ($value) => bccomp((string) $value, '198.00', 2) === 0)
            ->assertViewHas('codCollected', fn ($value) => bccomp((string) $value, '125.00', 2) === 0)
            ->assertViewHas('awaitingCollection', fn ($value) => bccomp((string) $value, '75.00', 2) === 0)
            ->assertSee('Net earnings')
            ->assertSee('Delivered sales')
            ->assertSee('Cash collected')
            ->assertSee('Awaiting cash')
            ->assertDontSee('999.00');
    }

    public function test_earnings_summary_deducts_refunds_and_their_commission_credit(): void
    {
        [$store, $seller] = $this->storeWithSeller('Northwind Goods');
        $store->tenant->update(['platform_fee_accepted_at' => now()->subDay()]);

        $collected = $this->deliveredSellerOrderFor($store, 'JM-REFUND-001', '125.00', collected: true);
        $uncollected = $this->deliveredSellerOrderFor($store, 'JM-REFUND-002', '75.00', collected: false);

        $action = app(RecordSellerEarning::class);
        $action->handle($collected->fresh());
        $action->handle($uncollected->fresh());

        $buyer = $collected->order()->firstOrFail()->user()->firstOrFail();
        $case = ReturnCase::create(['seller_order_id' => $collected->id, 'buyer_id' => $buyer->id, 'opened_by' => $buyer->id, 'status' => 'awaiting_confirmation', 'reason' => 'damaged', 'requested_remedy' => 'refund', 'remedy' => 'refund', 'details' => 'The item arrived broken.']);
        $case->outcome()->create(['recorded_by' => $seller->id, 'kind' => 'refund', 'amount' => '25.00', 'paid_at' => now(), 'created_at' => now()]);

        $this->actingAs($seller)
            ->get(route('seller.earnings.index'))
            ->assertOk()
            ->assertViewHas('deliveredSales', fn ($value) => bccomp((string) $value, '175.00', 2) === 0)
            ->assertViewHas('commissionTotal', fn ($value) => bccomp((string) $value, '1.75', 2) === 0)
            ->assertViewHas('netEarnings', fn ($value) => bccomp((string) $value, '173.25', 2) === 0);
    }

    public function test_backfill_migration_records_missing_earnings_for_delivered_orders(): void
    {
        [$store] = $this->storeWithSeller('Northwind Goods');
        $store->tenant->update(['platform_fee_accepted_at' => now()->subDay()]);

        $collected = $this->deliveredSellerOrderFor($store, 'JM-BACKFILL-001', '125.00', collected: true);
        $uncollected = $this->deliveredSellerOrderFor($store, 'JM-BACKFILL-002', '75.00', collected: false);
        $pending = $this->sellerOrderFor($store, 'JM-BACKFILL-PENDING', '999.00', 'pending');

        $migration = require database_path('migrations/2026_10_22_040426_backfill_delivered_seller_earnings.php');
        $migration->up();
        $migration->up();

        $this->assertSame(2, SellerEarning::query()->count());
        $this->assertDatabaseMissing('seller_earnings', ['seller_order_id' => $pending->id]);

        $collectedEarning = $collected->fresh()->earning()->sole();
        $this->assertSame('0.0100', $collectedEarning->commission_rate);
        $this->assertSame('1.25', number_format((float) $collectedEarning->commission_amount, 2));
        $this->assertSame('123.75', number_format((float) $collectedEarning->net_amount, 2));
        $this->assertNotNull($collectedEarning->voided_at);

        $uncollectedEarning = $uncollected->fresh()->earning()->sole();
        $this->assertSame('0.75', number_format((float) $uncollectedEarning->commission_amount, 2));
        $this->assertSame('74.25', number_format((float) $uncollectedEarning->net_amount, 2));
        $this->assertNotNull($uncollectedEarning->voided_at);
    }

    public function test_administrator_can_record_a_payout_for_available_earnings_from_one_store(): void
    {
        $administrator = User::factory()->administrator()->create();
        [$store] = $this->storeWithSeller('Northwind Goods');
        $firstEarning = $this->earningFor($store, 'JM-PAYOUT-001', '125.00');
        $secondEarning = $this->earningFor($store, 'JM-PAYOUT-002', '75.00');

        $this->actingAs($administrator)
            ->post(route('admin.payouts.store'), [
                'earning_ids' => [$firstEarning->id, $secondEarning->id],
                'note' => 'Cash settlement',
            ])
            ->assertRedirect(route('admin.payouts.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('seller_payouts', [
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->id,
            'recorded_by' => $administrator->id,
            'amount' => '200.00',
            'status' => 'recorded',
            'note' => 'Cash settlement',
        ]);
        $this->assertDatabaseHas('seller_earnings', ['id' => $firstEarning->id, 'status' => 'paid_out']);
        $this->assertDatabaseHas('seller_earnings', ['id' => $secondEarning->id, 'status' => 'paid_out']);
    }

    public function test_administrator_cannot_mix_stores_in_one_payout(): void
    {
        $administrator = User::factory()->administrator()->create();
        [$firstStore] = $this->storeWithSeller('Northwind Goods');
        [$secondStore] = $this->storeWithSeller('Harbor Market');
        $firstEarning = $this->earningFor($firstStore, 'JM-MIXED-001', '125.00');
        $secondEarning = $this->earningFor($secondStore, 'JM-MIXED-002', '75.00');

        $this->actingAs($administrator)
            ->post(route('admin.payouts.store'), ['earning_ids' => [$firstEarning->id, $secondEarning->id]])
            ->assertSessionHasErrors(['payout' => 'A payout can only include earnings from one seller store.']);

        $this->assertDatabaseCount('seller_payouts', 0);
        $this->assertDatabaseHas('seller_earnings', ['id' => $firstEarning->id, 'status' => 'available']);
        $this->assertDatabaseHas('seller_earnings', ['id' => $secondEarning->id, 'status' => 'available']);
    }

    public function test_administrator_cannot_record_a_payout_larger_than_the_storage_limit(): void
    {
        $administrator = User::factory()->administrator()->create();
        [$store] = $this->storeWithSeller('Northwind Goods');
        $firstEarning = $this->earningFor($store, 'JM-PAYOUT-LIMIT-001', '6000000000.00');
        $secondEarning = $this->earningFor($store, 'JM-PAYOUT-LIMIT-002', '6000000000.00');

        $this->actingAs($administrator)
            ->post(route('admin.payouts.store'), ['earning_ids' => [$firstEarning->id, $secondEarning->id]])
            ->assertSessionHasErrors(['payout' => 'The payout amount exceeds the maximum supported amount.']);

        $this->assertDatabaseCount('seller_payouts', 0);
        $this->assertDatabaseHas('seller_earnings', ['id' => $firstEarning->id, 'status' => 'available']);
        $this->assertDatabaseHas('seller_earnings', ['id' => $secondEarning->id, 'status' => 'available']);
    }

    public function test_seller_order_store_must_belong_to_its_tenant(): void
    {
        [$firstStore] = $this->storeWithSeller('Northwind Goods');
        [$secondStore] = $this->storeWithSeller('Harbor Market');
        $buyer = User::factory()->create();
        $order = Order::create([
            'user_id' => $buyer->id,
            'number' => 'JM-CROSS-TENANT-ORDER',
            'status' => 'pending',
            'recipient_name' => $buyer->name,
            'phone' => '+639171234567',
            'address_line' => '100 Market Street',
            'city' => 'Manila',
            'subtotal' => '100.00',
            'shipping_amount' => '0.00',
            'total' => '100.00',
        ]);

        $this->expectException(QueryException::class);

        $order->sellerOrders()->create([
            'tenant_id' => $firstStore->tenant_id,
            'store_id' => $secondStore->id,
            'subtotal' => '100.00',
            'shipping_amount' => '0.00',
            'total' => '100.00',
        ]);
    }

    public function test_seller_cannot_record_a_payout(): void
    {
        [$store, $seller] = $this->storeWithSeller('Northwind Goods');
        $earning = $this->earningFor($store, 'JM-SELLER-PAYOUT-001', '125.00');

        $this->actingAs($seller)
            ->post(route('admin.payouts.store'), ['earning_ids' => [$earning->id]])
            ->assertForbidden();

        $this->assertDatabaseCount('seller_payouts', 0);
        $this->assertDatabaseHas('seller_earnings', ['id' => $earning->id, 'status' => 'available']);
    }

    public function test_cod_earning_cannot_be_paid_out_even_if_legacy_row_is_available(): void
    {
        $administrator = User::factory()->administrator()->create();
        [$store] = $this->storeWithSeller('Legacy Store');
        $earning = $this->earningFor($store, 'JM-LEGACY-COD', '125.00');
        $earning->sellerOrder->order->payment()->create(['method' => 'cod', 'status' => 'paid', 'amount' => '125.00']);

        $this->actingAs($administrator)->post(route('admin.payouts.store'), ['earning_ids' => [$earning->id]])
            ->assertSessionHasErrors('payout');
        $this->assertDatabaseCount('seller_payouts', 0);
        $this->assertDatabaseHas('seller_earnings', ['id' => $earning->id, 'status' => 'available']);
    }

    /**
     * @return array{0: Store, 1: User}
     */
    private function storeWithSeller(string $name): array
    {
        $suffix = str()->lower(str()->random(6));
        $tenant = Tenant::create(['name' => $name, 'slug' => "tenant-{$suffix}", 'status' => 'active']);
        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'slug' => str($name)->slug()."-{$suffix}",
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => 'active',
        ]);
        $seller = User::factory()->seller()->create();
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $seller->id, 'role' => 'owner']);

        return [$store, $seller];
    }

    private function earningFor(Store $store, string $number, string $amount): SellerEarning
    {
        $buyer = User::factory()->create();
        $order = Order::create([
            'user_id' => $buyer->id,
            'number' => $number,
            'status' => 'delivered',
            'recipient_name' => $buyer->name,
            'phone' => '+639171234567',
            'address_line' => '100 Market Street',
            'city' => 'Manila',
            'subtotal' => $amount,
            'shipping_amount' => '0.00',
            'total' => $amount,
        ]);
        $sellerOrder = $order->sellerOrders()->create([
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->id,
            'status' => 'delivered',
            'subtotal' => $amount,
            'shipping_amount' => '0.00',
            'total' => $amount,
        ]);

        return SellerEarning::create([
            'seller_order_id' => $sellerOrder->id,
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->id,
            'gross_amount' => $amount,
            'commission_amount' => '0.00',
            'net_amount' => $amount,
            'status' => 'available',
            'available_at' => now(),
        ])->load('sellerOrder.order');
    }

    private function deliveredSellerOrderFor(Store $store, string $number, string $amount, bool $collected): SellerOrder
    {
        $sellerOrder = $this->sellerOrderFor($store, $number, $amount, 'delivered');

        if ($collected) {
            $sellerOrder->update(['cod_collected_at' => now()]);
        }

        return $sellerOrder;
    }

    private function sellerOrderFor(Store $store, string $number, string $amount, string $status): SellerOrder
    {
        $buyer = User::factory()->create();
        $order = Order::create([
            'user_id' => $buyer->id,
            'number' => $number,
            'status' => $status,
            'recipient_name' => $buyer->name,
            'phone' => '+639171234567',
            'address_line' => '100 Market Street',
            'city' => 'Manila',
            'subtotal' => $amount,
            'shipping_amount' => '0.00',
            'total' => $amount,
        ]);
        $order->payment()->create(['method' => 'cod', 'status' => 'pending', 'amount' => $amount]);

        return $order->sellerOrders()->create([
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->id,
            'status' => $status,
            'subtotal' => $amount,
            'shipping_amount' => '0.00',
            'total' => $amount,
            'delivered_at' => $status === 'delivered' ? now()->subHour() : null,
        ]);
    }
}
