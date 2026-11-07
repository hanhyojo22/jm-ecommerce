<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderIssue;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\OrderIssueOpened;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderIssueTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_request_cancellation_and_only_see_their_own_order(): void
    {
        [$buyer, $sellerOrder] = $this->order();
        $otherBuyer = User::factory()->create(['email_verified_at' => now()]);
        $payload = ['type' => 'cancellation', 'details' => 'Please cancel this order.'];

        $this->actingAs($otherBuyer)->post(route('order-issues.store', $sellerOrder), $payload)->assertNotFound();
        $this->actingAs($otherBuyer)->post(route('order-issues.store', $sellerOrder), [])->assertNotFound();
        $this->actingAs($buyer)->post(route('order-issues.store', $sellerOrder), $payload)->assertRedirect(route('orders.show', $sellerOrder->order));
        $this->assertDatabaseHas('order_issues', ['seller_order_id' => $sellerOrder->id, 'buyer_id' => $buyer->id, 'status' => 'open']);
        $this->actingAs($buyer)->get(route('orders.show', $sellerOrder->order))->assertOk()->assertSee('Please cancel this order.');
        $this->actingAs($buyer)->post(route('order-issues.store', $sellerOrder), $payload)->assertSessionHasErrors('type');
    }

    public function test_opening_a_request_notifies_the_stores_active_staff_but_not_the_buyer(): void
    {
        Notification::fake();

        [$buyer, $sellerOrder] = $this->order();
        $owner = User::factory()->seller()->create(['email_verified_at' => now()]);
        $sellerOrder->store->tenant->members()->create(['user_id' => $owner->id, 'role' => 'owner']);

        $this->actingAs($buyer)
            ->post(route('order-issues.store', $sellerOrder), ['type' => 'cancellation', 'details' => 'Please cancel this order.'])
            ->assertRedirect(route('orders.show', $sellerOrder->order));

        Notification::assertSentTo(
            $owner,
            OrderIssueOpened::class,
            fn (OrderIssueOpened $notification): bool => $notification->toArray($owner)['seller_order_id'] === $sellerOrder->id,
        );
        Notification::assertNotSentTo($buyer, OrderIssueOpened::class);
    }

    public function test_request_types_follow_order_state(): void
    {
        [$buyer, $sellerOrder] = $this->order();
        $this->actingAs($buyer)->post(route('order-issues.store', $sellerOrder), ['type' => 'return_refund', 'details' => 'The item is damaged.'])->assertSessionHasErrors('type');
        $sellerOrder->update(['status' => 'delivered']);
        $this->actingAs($buyer)->post(route('order-issues.store', $sellerOrder), ['type' => 'cancellation', 'details' => 'Please cancel this order.'])->assertSessionHasErrors('type');
        $this->actingAs($buyer)->post(route('order-issues.store', $sellerOrder), ['type' => 'return_refund', 'details' => 'The item is damaged.'])->assertSessionHasErrors('type');
    }

    public function test_open_cancellation_request_pauses_seller_fulfilment(): void
    {
        [$buyer, $sellerOrder] = $this->order();
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);
        $sellerOrder->store->tenant->members()->create(['user_id' => $seller->id, 'role' => 'owner']);
        OrderIssue::query()->create(['seller_order_id' => $sellerOrder->id, 'buyer_id' => $buyer->id, 'type' => 'cancellation', 'details' => 'Please cancel this order.']);

        $this->actingAs($seller)->patch(route('seller.orders.update', $sellerOrder), ['status' => 'confirmed'])->assertSessionHasErrors('status');
        $this->assertDatabaseHas('seller_orders', ['id' => $sellerOrder->id, 'status' => 'pending']);
    }

    public function test_only_admin_can_review_a_request_and_cancel_a_pending_order(): void
    {
        [$buyer, $sellerOrder] = $this->order();
        $issue = OrderIssue::query()->create(['seller_order_id' => $sellerOrder->id, 'buyer_id' => $buyer->id, 'type' => 'cancellation', 'details' => 'Please cancel this order.']);
        $payload = ['decision' => 'cancel_order', 'resolution_note' => 'Cancelled as requested.'];

        $this->actingAs($buyer)->patch(route('admin.order-issues.update', $issue), $payload)->assertForbidden();
        $admin = User::factory()->administrator()->create(['email_verified_at' => now()]);
        $this->actingAs($admin)->get(route('admin.order-issues.index'))->assertOk()->assertSee('Please cancel this order.');
        $this->actingAs($admin)->patch(route('admin.order-issues.update', $issue), $payload)->assertSessionHas('status');
        $this->assertDatabaseHas('seller_orders', ['id' => $sellerOrder->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('order_issues', ['id' => $issue->id, 'status' => 'resolved', 'resolved_by' => $admin->id]);
        $this->actingAs($admin)->patch(route('admin.order-issues.update', $issue), $payload)->assertSessionHasErrors('decision');
    }

    private function order(): array
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $tenant = Tenant::create(['name' => 'Test Seller', 'slug' => 'test-seller', 'status' => 'active']);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => 'Test Store', 'slug' => 'test-store', 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);
        $order = Order::create(['user_id' => $buyer->id, 'number' => 'ISSUE-1001', 'status' => 'pending', 'recipient_name' => 'Test Buyer', 'phone' => '+639171234567', 'address_line' => '100 Buyer Street', 'city' => 'Manila', 'subtotal' => '500.00', 'shipping_amount' => '0.00', 'total' => '500.00']);
        $order->payment()->create(['method' => 'cod', 'status' => 'pending', 'amount' => '500.00']);
        $sellerOrder = $order->sellerOrders()->create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'status' => 'pending', 'subtotal' => '500.00', 'shipping_amount' => '0.00', 'total' => '500.00']);

        return [$buyer, $sellerOrder->load('order')];
    }
}
