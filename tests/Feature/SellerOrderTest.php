<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_orders_are_tenant_scoped_and_filterable(): void
    {
        [$seller, $tenant, $store] = $this->seller();
        $visibleOrder = $this->sellerOrder($tenant, $store, 'SELLER-1001', 'pending', 'pending');
        [, $otherTenant, $otherStore] = $this->seller();
        $this->sellerOrder($otherTenant, $otherStore, 'OTHER-1001', 'processing', 'paid');

        $this->actingAs($seller)->get(route('seller.orders.index', [
            'search' => 'seller-1001',
            'status' => 'pending',
            'payment_status' => 'pending',
        ]))
            ->assertSee($visibleOrder->order->number)
            ->assertDontSee('OTHER-1001')
            ->assertSee('Search: seller-1001')
            ->assertSee('Status: Pending')
            ->assertSee('Payment: Pending');
    }

    public function test_seller_cannot_view_another_tenants_order_details(): void
    {
        [$seller] = $this->seller();
        [, $otherTenant, $otherStore] = $this->seller();
        $otherSellerOrder = $this->sellerOrder($otherTenant, $otherStore, 'OTHER-1001');

        $this->actingAs($seller)
            ->get(route('seller.orders.show', $otherSellerOrder))
            ->assertNotFound();
    }

    public function test_owner_can_process_only_the_next_valid_seller_order_status(): void
    {
        [$seller, $tenant, $store] = $this->seller();
        $sellerOrder = $this->sellerOrder($tenant, $store, 'SELLER-1001');

        $this->actingAs($seller)
            ->patch(route('seller.orders.update', $sellerOrder), ['status' => 'confirmed'])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('seller_orders', ['id' => $sellerOrder->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('orders', ['id' => $sellerOrder->order_id, 'status' => 'confirmed']);

        $this->actingAs($seller)
            ->patch(route('seller.orders.update', $sellerOrder), ['status' => 'shipped'])
            ->assertSessionHasErrors(['status']);

        $this->assertDatabaseHas('seller_orders', ['id' => $sellerOrder->id, 'status' => 'confirmed']);
    }

    /**
     * @return array{0: User, 1: Tenant, 2: Store}
     */
    private function seller(): array
    {
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);
        $tenant = Tenant::create(['name' => 'Northwind', 'slug' => 'northwind-'.str()->lower(str()->random(6)), 'status' => 'active']);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => 'Northwind Goods', 'slug' => 'northwind-goods-'.str()->lower(str()->random(6)), 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $seller->id, 'role' => 'owner']);

        return [$seller, $tenant, $store];
    }

    private function sellerOrder(Tenant $tenant, Store $store, string $number, string $status = 'pending', string $paymentStatus = 'pending'): SellerOrder
    {
        $buyer = User::factory()->create();
        $order = Order::create([
            'user_id' => $buyer->id,
            'number' => $number,
            'status' => $status,
            'recipient_name' => 'Casey Buyer',
            'phone' => '+639171234567',
            'address_line' => '100 Buyer Street',
            'city' => 'Manila',
            'subtotal' => '500.00',
            'shipping_amount' => '0.00',
            'total' => '500.00',
        ]);
        $order->payment()->create(['method' => 'cod', 'status' => $paymentStatus, 'amount' => '500.00']);
        $sellerOrder = $order->sellerOrders()->create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'status' => $status, 'subtotal' => '500.00', 'shipping_amount' => '0.00', 'total' => '500.00']);
        $sellerOrder->items()->create(['product_name' => 'Canvas Tote', 'sku' => 'TOTE-001', 'unit_price' => '500.00', 'quantity' => 1, 'line_total' => '500.00']);

        return $sellerOrder->load('order');
    }
}
