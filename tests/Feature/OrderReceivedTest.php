<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderIssue;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderReceivedTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_buyer_confirms_a_shipped_order_arrived_and_the_store_is_charged_its_fee(): void
    {
        [$store, $owner] = $this->storeWithOwner();
        $sellerOrder = $this->sellerOrder($store, 'processing', '200.00');
        $this->actingAs($owner)->patch(route('seller.orders.update', $sellerOrder), ['status' => 'shipped'])->assertSessionHasNoErrors();
        $this->assertNotNull($sellerOrder->fresh()->shipped_at);
        $buyer = $sellerOrder->order->user;

        $this->actingAs($buyer)->get(route('orders.show', $sellerOrder->order_id))->assertOk()->assertSee('Order received');
        $this->actingAs($buyer)->post(route('orders.received', $sellerOrder))->assertSessionHasNoErrors();

        $this->assertSame('delivered', $sellerOrder->fresh()->status);
        $this->assertDatabaseHas('seller_earnings', ['seller_order_id' => $sellerOrder->id, 'commission_amount' => '2.00']);
    }

    public function test_only_the_buyer_confirms_and_only_a_shipped_order(): void
    {
        [$store] = $this->storeWithOwner();
        $shipped = $this->sellerOrder($store, 'shipped', '200.00');
        $processing = $this->sellerOrder($store, 'processing', '200.00');

        $this->actingAs(User::factory()->create())->post(route('orders.received', $shipped))->assertNotFound();
        // The buyer endpoint rethrows any invalid transition as one guide message.
        $this->actingAs($processing->order->user)->post(route('orders.received', $processing))->assertSessionHasErrors('received');

        $this->assertSame('shipped', $shipped->fresh()->status);
        $this->assertSame('processing', $processing->fresh()->status);
    }

    public function test_orders_shipped_too_long_ago_are_marked_delivered_unless_the_buyer_asked_for_help(): void
    {
        [$store] = $this->storeWithOwner();
        $stale = $this->sellerOrder($store, 'shipped', '300.00', shippedDaysAgo: 15);
        $recent = $this->sellerOrder($store, 'shipped', '300.00', shippedDaysAgo: 13);
        $disputed = $this->sellerOrder($store, 'shipped', '300.00', shippedDaysAgo: 15);
        OrderIssue::create(['seller_order_id' => $disputed->id, 'buyer_id' => $disputed->order->user_id, 'type' => 'delivery', 'details' => 'The parcel never arrived.', 'status' => 'open']);

        $this->artisan('orders:auto-deliver')->assertSuccessful();

        $this->assertSame('delivered', $stale->fresh()->status);
        $this->assertDatabaseHas('seller_earnings', ['seller_order_id' => $stale->id, 'commission_amount' => '3.00']);
        $this->assertDatabaseHas('audit_logs', ['entity_id' => $stale->id, 'action' => 'seller_order.status_updated', 'actor_user_id' => null]);
        $this->assertSame('shipped', $recent->fresh()->status);
        $this->assertSame('shipped', $disputed->fresh()->status);
    }

    public function test_a_store_reports_a_failed_delivery_and_support_cancels_it_without_a_fee(): void
    {
        [$store, $owner] = $this->storeWithOwner();
        $sellerOrder = $this->sellerOrder($store, 'shipped', '300.00', shippedDaysAgo: 2);
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($owner)->get(route('seller.orders.show', $sellerOrder))->assertOk()->assertSee('Report failed delivery');
        $this->actingAs($owner)->post(route('seller.orders.failed-delivery', $sellerOrder), ['details' => 'The buyer refused the parcel at the door.'])->assertSessionHasNoErrors();
        $issue = OrderIssue::query()->where('seller_order_id', $sellerOrder->id)->sole();
        $this->assertSame('failed_delivery', $issue->type);

        $this->travel(14)->days();
        $this->artisan('orders:auto-deliver')->assertSuccessful();
        $this->assertSame('shipped', $sellerOrder->fresh()->status, 'An open report stops the order completing on its own.');

        $this->actingAs($administrator)->patch(route('admin.order-issues.update', $issue), ['decision' => 'cancel_order', 'resolution_note' => 'Courier confirmed the parcel was returned.'])->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $sellerOrder->fresh()->status);
        $this->assertSame('resolved', $issue->fresh()->status);
        $this->assertDatabaseMissing('seller_earnings', ['seller_order_id' => $sellerOrder->id]);
    }

    public function test_a_store_cannot_cancel_a_shipped_order_itself_even_after_reporting_it(): void
    {
        [$store, $owner] = $this->storeWithOwner();
        $sellerOrder = $this->sellerOrder($store, 'shipped', '300.00', shippedDaysAgo: 2);
        $delivered = $this->sellerOrder($store, 'delivered', '300.00');

        $this->actingAs($owner)->post(route('seller.orders.failed-delivery', $sellerOrder), ['details' => 'The buyer refused the parcel at the door.'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->patch(route('seller.orders.update', $sellerOrder), ['status' => 'cancelled'])->assertSessionHasErrors('status');
        $this->actingAs($owner)->post(route('seller.orders.failed-delivery', $delivered), ['details' => 'The buyer refused the parcel at the door.'])->assertSessionHasErrors('details');

        $this->assertSame('shipped', $sellerOrder->fresh()->status);
    }

    public function test_the_buyer_confirming_receipt_closes_the_stores_failed_delivery_report(): void
    {
        [$store, $owner] = $this->storeWithOwner();
        $sellerOrder = $this->sellerOrder($store, 'shipped', '300.00', shippedDaysAgo: 2);
        $this->actingAs($owner)->post(route('seller.orders.failed-delivery', $sellerOrder), ['details' => 'The courier says the buyer was not home.'])->assertSessionHasNoErrors();

        $this->actingAs($sellerOrder->order->user)->post(route('orders.received', $sellerOrder))->assertSessionHasNoErrors();

        $this->assertSame('delivered', $sellerOrder->fresh()->status);
        $this->assertDatabaseHas('order_issues', ['seller_order_id' => $sellerOrder->id, 'type' => 'failed_delivery', 'status' => 'resolved']);
    }

    /**
     * @return array{0: Store, 1: User}
     */
    private function storeWithOwner(): array
    {
        $suffix = str()->lower(str()->random(8));
        $tenant = Tenant::create(['name' => "Tenant {$suffix}", 'slug' => "tenant-{$suffix}", 'status' => 'active', 'platform_fee_accepted_at' => now()->subYear(), 'platform_fee_policy_version' => '2.0', 'platform_fee_rate' => '0.01']);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => "Store {$suffix}", 'slug' => "store-{$suffix}", 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);
        $owner = User::factory()->seller()->create();
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'role' => 'owner']);

        return [$store, $owner];
    }

    private function sellerOrder(Store $store, string $status, string $total, ?int $shippedDaysAgo = null): SellerOrder
    {
        $buyer = User::factory()->create();
        $order = Order::create(['user_id' => $buyer->id, 'number' => 'JM-'.str()->upper(str()->random(8)), 'status' => $status, 'recipient_name' => $buyer->name, 'phone' => '+639171234567', 'address_line' => '100 Market Street', 'city' => 'Manila', 'subtotal' => $total, 'shipping_amount' => '0.00', 'total' => $total]);
        $order->payment()->create(['method' => 'cod', 'amount' => $total]);

        return $order->sellerOrders()->create(['tenant_id' => $store->tenant_id, 'store_id' => $store->id, 'status' => $status, 'subtotal' => $total, 'shipping_amount' => '0.00', 'total' => $total, 'shipped_at' => $shippedDaysAgo !== null ? now()->subDays($shippedDaysAgo) : null]);
    }
}
