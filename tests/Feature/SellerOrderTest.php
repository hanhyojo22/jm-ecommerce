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

    public function test_buyer_account_with_a_tenant_membership_cannot_open_seller_orders(): void
    {
        [$user] = $this->seller();
        $user->update(['account_type' => 'buyer']);

        $this->actingAs($user)
            ->get(route('seller.orders.index'))
            ->assertForbidden();
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

    public function test_seller_cannot_update_another_tenants_order(): void
    {
        [$seller] = $this->seller();
        [, $otherTenant, $otherStore] = $this->seller();
        $otherSellerOrder = $this->sellerOrder($otherTenant, $otherStore, 'OTHER-1001');

        $this->actingAs($seller)
            ->patch(route('seller.orders.update', $otherSellerOrder), ['status' => 'confirmed'])
            ->assertNotFound();

        $this->assertDatabaseHas('seller_orders', [
            'id' => $otherSellerOrder->id,
            'status' => 'pending',
        ]);
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

    public function test_owner_can_confirm_selected_pending_orders_from_their_store(): void
    {
        [$seller, $tenant, $store] = $this->seller();
        $orders = collect([
            $this->sellerOrder($tenant, $store, 'SELLER-1001'),
            $this->sellerOrder($tenant, $store, 'SELLER-1002'),
        ]);

        $this->actingAs($seller)->get(route('seller.orders.index'))
            ->assertSee('Confirm pending orders')
            ->assertSee('name="seller_order_ids[]"', false);

        $this->actingAs($seller)->post(route('seller.orders.bulk-confirm'), [
            'seller_order_ids' => [...$orders->pluck('id')->all(), ...$orders->pluck('id')->all()],
        ])->assertRedirect(route('seller.orders.index'))
            ->assertSessionHas('status', '2 pending orders were confirmed.');

        foreach ($orders as $sellerOrder) {
            $this->assertDatabaseHas('seller_orders', ['id' => $sellerOrder->id, 'status' => 'confirmed']);
            $this->assertDatabaseHas('orders', ['id' => $sellerOrder->order_id, 'status' => 'confirmed']);
        }

        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_bulk_order_confirmation_rejects_foreign_or_non_pending_orders_without_partial_updates(): void
    {
        [$seller, $tenant, $store] = $this->seller();
        $pendingOrder = $this->sellerOrder($tenant, $store, 'SELLER-1001');
        $processingOrder = $this->sellerOrder($tenant, $store, 'SELLER-1002', 'processing');
        [, $otherTenant, $otherStore] = $this->seller();
        $foreignOrder = $this->sellerOrder($otherTenant, $otherStore, 'OTHER-1001');

        $this->actingAs($seller)->post(route('seller.orders.bulk-confirm'), [
            'seller_order_ids' => [$pendingOrder->id, $foreignOrder->id],
        ])->assertSessionHasErrors('seller_order_ids');

        $this->actingAs($seller)->post(route('seller.orders.bulk-confirm'), [
            'seller_order_ids' => [$pendingOrder->id, $processingOrder->id],
        ])->assertSessionHasErrors('seller_order_ids');

        $this->assertDatabaseHas('seller_orders', ['id' => $pendingOrder->id, 'status' => 'pending']);
        $this->assertDatabaseHas('seller_orders', ['id' => $processingOrder->id, 'status' => 'processing']);
        $this->assertDatabaseHas('seller_orders', ['id' => $foreignOrder->id, 'status' => 'pending']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_staff_cannot_confirm_orders_in_bulk(): void
    {
        [, $tenant, $store] = $this->seller();
        $staff = User::factory()->seller()->create(['email_verified_at' => now()]);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $staff->id, 'role' => 'staff']);
        $sellerOrder = $this->sellerOrder($tenant, $store, 'SELLER-1001');

        $this->actingAs($staff)->post(route('seller.orders.bulk-confirm'), [
            'seller_order_ids' => [$sellerOrder->id],
        ])->assertForbidden();

        $this->assertDatabaseHas('seller_orders', ['id' => $sellerOrder->id, 'status' => 'pending']);
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
