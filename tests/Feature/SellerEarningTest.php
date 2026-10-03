<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\SellerEarning;
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
}
