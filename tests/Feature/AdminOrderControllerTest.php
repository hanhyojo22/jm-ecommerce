<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_mark_a_delivered_pending_cod_payment_as_paid_and_records_an_audit_log(): void
    {
        $administrator = User::factory()->administrator()->create();
        $order = $this->order('delivered', 'pending');
        [$store] = $this->storeWithOwner('Northwind Goods', 'Ava Seller');
        $this->sellerOrder($order, $store, 'Delivered Tote', 'TOTE-001', 1);

        $this->actingAs($administrator)
            ->patch(route('admin.orders.payment.mark-paid', $order))
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('status', 'COD payment marked as paid.');

        $this->assertDatabaseHas('payments', ['id' => $order->payment->id, 'status' => 'paid']);
        $this->assertDatabaseHas('seller_earnings', [
            'seller_order_id' => $order->sellerOrders()->sole()->id,
            'gross_amount' => '500.00',
            'commission_amount' => '0.00',
            'net_amount' => '500.00',
            'status' => 'available',
        ]);

        $auditLog = AuditLog::query()->where('action', 'payment.cod_marked_paid')->firstOrFail();
        $this->assertSame($administrator->id, $auditLog->actor_user_id);
        $this->assertSame('pending', $auditLog->old_values['status']);
        $this->assertSame('paid', $auditLog->new_values['status']);
        $this->assertSame($order->id, $auditLog->new_values['order_id']);
    }

    public function test_administrator_cannot_mark_a_cod_payment_as_paid_before_order_delivery(): void
    {
        $administrator = User::factory()->administrator()->create();
        $order = $this->order('shipped', 'pending');

        $this->actingAs($administrator)
            ->patch(route('admin.orders.payment.mark-paid', $order))
            ->assertSessionHasErrors('payment');

        $this->assertDatabaseHas('payments', ['id' => $order->payment->id, 'status' => 'pending']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_administrator_can_mark_a_partially_cancelled_marketplace_order_as_cod_paid_after_the_remaining_delivery(): void
    {
        $administrator = User::factory()->administrator()->create();
        [$deliveredStore] = $this->storeWithOwner('Northwind Goods', 'Ava Seller');
        [$cancelledStore] = $this->storeWithOwner('Harbor Market', 'Ben Seller');
        $order = $this->order('delivered', 'pending');
        $this->sellerOrder($order, $deliveredStore, 'Delivered Tote', 'TOTE-001', 1, 'delivered');
        $this->sellerOrder($order, $cancelledStore, 'Cancelled Scarf', 'SCARF-001', 1, 'cancelled');

        $this->actingAs($administrator)
            ->patch(route('admin.orders.payment.mark-paid', $order))
            ->assertRedirect(route('admin.orders.show', $order));

        $this->assertDatabaseHas('payments', ['id' => $order->payment->id, 'status' => 'paid']);
        $deliveredSellerOrder = $order->sellerOrders()->where('store_id', $deliveredStore->id)->firstOrFail();
        $cancelledSellerOrder = $order->sellerOrders()->where('store_id', $cancelledStore->id)->firstOrFail();
        $this->assertDatabaseHas('seller_earnings', ['seller_order_id' => $deliveredSellerOrder->id, 'net_amount' => '500.00']);
        $this->assertDatabaseMissing('seller_earnings', ['seller_order_id' => $cancelledSellerOrder->id]);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_non_administrators_cannot_mark_a_cod_payment_as_paid(): void
    {
        $seller = User::factory()->seller()->create();
        $order = $this->order('delivered', 'pending');

        $this->actingAs($seller)
            ->patch(route('admin.orders.payment.mark-paid', $order))
            ->assertForbidden();

        $this->assertDatabaseHas('payments', ['id' => $order->payment->id, 'status' => 'pending']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_administrator_can_filter_marketplace_orders_and_see_store_and_seller_associations(): void
    {
        $administrator = User::factory()->administrator()->create();
        [$matchingStore, $matchingSeller] = $this->storeWithOwner('Northwind Goods', 'Ava Seller');
        [$otherStore] = $this->storeWithOwner('Harbor Market', 'Ben Seller');
        $matchingOrder = $this->order('processing', 'pending');
        $otherOrder = $this->order('delivered', 'paid');
        $this->sellerOrder($matchingOrder, $matchingStore, 'Canvas Tote', 'TOTE-001', 2);
        $this->sellerOrder($otherOrder, $otherStore, 'Wool Scarf', 'SCARF-001', 1);

        $this->actingAs($administrator)
            ->get(route('admin.orders.index', [
                'search' => 'ava seller',
                'status' => 'processing',
                'payment_status' => 'pending',
                'store_id' => $matchingStore->id,
                'from' => now()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertSee($matchingOrder->number)
            ->assertSee($matchingStore->name)
            ->assertSee($matchingSeller->name)
            ->assertSee('2')
            ->assertDontSee($otherOrder->number);
    }

    public function test_administrator_can_view_parent_order_with_multiple_seller_sub_orders_and_checkout_snapshots(): void
    {
        $administrator = User::factory()->administrator()->create();
        [$firstStore, $firstSeller] = $this->storeWithOwner('Northwind Goods', 'Ava Seller');
        [$secondStore, $secondSeller] = $this->storeWithOwner('Harbor Market', 'Ben Seller');
        $order = $this->order('processing', 'pending');
        $this->sellerOrder($order, $firstStore, 'Checkout Snapshot Tote', 'TOTE-001', 2);
        $this->sellerOrder($order, $secondStore, 'Checkout Snapshot Scarf', 'SCARF-001', 1);

        $this->actingAs($administrator)
            ->get(route('admin.orders.show', $order))
            ->assertSee($order->number)
            ->assertSee($order->user->name)
            ->assertSee($firstStore->name)
            ->assertSee($firstSeller->name)
            ->assertSee($secondStore->name)
            ->assertSee($secondSeller->name)
            ->assertSee('Checkout Snapshot Tote')
            ->assertSee('Checkout Snapshot Scarf')
            ->assertSee(route('admin.stores.show', $firstStore), false)
            ->assertSee(route('catalog.store', $secondStore), false);
    }

    public function test_order_pagination_keeps_selected_filters(): void
    {
        $administrator = User::factory()->administrator()->create();

        for ($number = 1; $number <= 21; $number++) {
            $this->order('pending', 'pending', sprintf('JM-PAGE-%02d', $number));
        }

        Order::query()->update(['created_at' => now()->startOfSecond()]);

        $this->actingAs($administrator)
            ->get(route('admin.orders.index', ['status' => 'pending', 'page' => 2]))
            ->assertSee('JM-PAGE-01')
            ->assertDontSee('JM-PAGE-21')
            ->assertSee('status=pending', false);
    }

    public function test_non_administrators_cannot_view_marketplace_orders(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->seller()->create();
        $order = $this->order('pending', 'pending');

        $this->actingAs($buyer)->get(route('admin.orders.index'))->assertForbidden();
        $this->actingAs($seller)->get(route('admin.orders.show', $order))->assertForbidden();
    }

    private function order(string $status, string $paymentStatus, ?string $number = null): Order
    {
        $buyer = User::factory()->create();
        $order = Order::create([
            'user_id' => $buyer->id,
            'number' => $number ?? 'JM-'.str()->upper(str()->random(12)),
            'status' => $status,
            'recipient_name' => $buyer->name,
            'phone' => '+639171234567',
            'address_line' => '100 Market Street',
            'city' => 'Manila',
            'subtotal' => '500.00',
            'shipping_amount' => '0.00',
            'total' => '500.00',
        ]);

        $order->payment()->create([
            'method' => 'cod',
            'status' => $paymentStatus,
            'amount' => '500.00',
        ]);

        return $order->load('payment');
    }

    /**
     * @return array{0: Store, 1: User}
     */
    private function storeWithOwner(string $name, string $sellerName): array
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
        $seller = User::factory()->seller()->create(['name' => $sellerName]);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $seller->id, 'role' => 'owner']);

        return [$store, $seller];
    }

    private function sellerOrder(Order $order, Store $store, string $productName, string $sku, int $quantity, ?string $status = null): SellerOrder
    {
        $sellerOrder = $order->sellerOrders()->create([
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->id,
            'status' => $status ?? $order->status,
            'subtotal' => '500.00',
            'shipping_amount' => '0.00',
            'total' => '500.00',
        ]);
        $sellerOrder->items()->create([
            'product_name' => $productName,
            'sku' => $sku,
            'unit_price' => '250.00',
            'quantity' => $quantity,
            'line_total' => bcmul('250.00', (string) $quantity, 2),
        ]);

        return $sellerOrder;
    }
}
