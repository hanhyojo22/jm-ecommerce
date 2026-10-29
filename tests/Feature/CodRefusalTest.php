<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderIssue;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodRefusalTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_returned_parcel_counts_against_the_buyer_unless_support_blames_the_courier(): void
    {
        $administrator = User::factory()->administrator()->create();
        [$store] = $this->storeWithOwner();
        $buyer = User::factory()->create();
        $refused = $this->failedDelivery($store, $buyer);
        $courierLost = $this->failedDelivery($store, $buyer);

        $this->actingAs($administrator)->get(route('admin.order-issues.index'))->assertOk()->assertSee('no confirmed COD refusals');
        $this->actingAs($administrator)->patch(route('admin.order-issues.update', $refused), ['decision' => 'cancel_order', 'resolution_note' => 'The buyer refused the parcel at the door.'])->assertSessionHasNoErrors();
        $this->actingAs($administrator)->patch(route('admin.order-issues.update', $courierLost), ['decision' => 'cancel_order_courier', 'resolution_note' => 'The courier lost the parcel in transit.'])->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $refused->sellerOrder->fresh()->status);
        $this->assertSame('cancelled', $courierLost->sellerOrder->fresh()->status);
        $this->assertSame(1, $buyer->codRefusalCount());
        $this->assertFalse($buyer->isCodLimited());
    }

    public function test_a_buyer_with_repeated_refusals_can_have_only_one_order_in_progress(): void
    {
        [$store, $owner] = $this->storeWithOwner();
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $this->confirmedRefusal($store, $buyer);
        $this->confirmedRefusal($store, $buyer);
        $inProgress = $this->sellerOrder($store, $buyer, 'pending');
        $product = Product::create(['tenant_id' => $store->tenant_id, 'store_id' => $store->id, 'name' => 'Canvas Tote', 'slug' => 'canvas-tote', 'sku' => 'TOTE-1', 'price' => '100.00', 'stock_quantity' => 5, 'status' => 'active']);
        Cart::create(['user_id' => $buyer->id])->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($owner)->get(route('seller.orders.show', $inProgress))->assertOk()->assertSee('This buyer refused 2 cash-on-delivery parcels');
        $this->actingAs($buyer)->get(route('checkout.create'))->assertOk()->assertSee('You can place this order once your current one is delivered.');
        $this->actingAs($buyer)->post(route('checkout.store'), $this->delivery())->assertSessionHasErrors('cart');
        $this->assertSame(1, $buyer->orders()->where('status', '!=', 'cancelled')->count());

        $inProgress->order->update(['status' => 'delivered']);
        $this->actingAs($buyer)->post(route('checkout.store'), $this->delivery())->assertRedirectContains('/placed');
        $this->assertSame(2, $buyer->orders()->where('status', '!=', 'cancelled')->count());
    }

    public function test_support_lifts_the_limit_and_older_refusals_stop_counting(): void
    {
        $administrator = User::factory()->administrator()->create();
        [$store] = $this->storeWithOwner();
        $buyer = User::factory()->create();
        $this->confirmedRefusal($store, $buyer);
        $this->confirmedRefusal($store, $buyer);

        $this->actingAs($administrator)->get(route('admin.customers.show', $buyer))->assertOk()->assertSee('Limited to one order in progress');
        $this->actingAs($administrator)->post(route('admin.customers.lift-cod-limit', $buyer))->assertSessionHasNoErrors();

        $this->assertFalse($buyer->fresh()->isCodLimited());
        $this->assertDatabaseHas('audit_logs', ['entity_id' => $buyer->id, 'action' => 'customer.cod_limit_lifted']);
        $this->travel(1)->minute();
        $this->confirmedRefusal($store, $buyer);
        $this->assertSame(1, $buyer->fresh()->codRefusalCount());
    }

    public function test_refusals_older_than_the_window_no_longer_count(): void
    {
        [$store] = $this->storeWithOwner();
        $buyer = User::factory()->create();
        $this->travelTo(now()->subDays(181));
        $this->confirmedRefusal($store, $buyer);
        $this->confirmedRefusal($store, $buyer);
        $this->travelBack();

        $this->assertSame(0, $buyer->codRefusalCount());
    }

    /**
     * @return array{0: Store, 1: User}
     */
    private function storeWithOwner(): array
    {
        $suffix = str()->lower(str()->random(8));
        $tenant = Tenant::create(['name' => "Tenant {$suffix}", 'slug' => "tenant-{$suffix}", 'status' => 'active', 'platform_fee_accepted_at' => now()->subYears(2), 'platform_fee_policy_version' => '2.0', 'platform_fee_rate' => '0.01']);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => "Store {$suffix}", 'slug' => "store-{$suffix}", 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);
        $owner = User::factory()->seller()->create();
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'role' => 'owner']);

        return [$store, $owner];
    }

    private function sellerOrder(Store $store, User $buyer, string $status): SellerOrder
    {
        $order = Order::create(['user_id' => $buyer->id, 'number' => 'JM-'.str()->upper(str()->random(8)), 'status' => $status, 'recipient_name' => $buyer->name, 'phone' => '+639171234567', 'address_line' => '100 Market Street', 'city' => 'Manila', 'subtotal' => '300.00', 'shipping_amount' => '0.00', 'total' => '300.00']);
        $order->payment()->create(['method' => 'cod', 'amount' => '300.00']);

        return $order->sellerOrders()->create(['tenant_id' => $store->tenant_id, 'store_id' => $store->id, 'status' => $status, 'subtotal' => '300.00', 'shipping_amount' => '0.00', 'total' => '300.00', 'shipped_at' => $status === 'shipped' ? now()->subDays(3) : null]);
    }

    private function failedDelivery(Store $store, User $buyer): OrderIssue
    {
        $sellerOrder = $this->sellerOrder($store, $buyer, 'shipped');

        return OrderIssue::create(['seller_order_id' => $sellerOrder->id, 'buyer_id' => $buyer->id, 'type' => 'failed_delivery', 'details' => 'The buyer refused the parcel at the door.', 'status' => 'open']);
    }

    private function confirmedRefusal(Store $store, User $buyer): void
    {
        $issue = $this->failedDelivery($store, $buyer);
        $issue->sellerOrder->update(['status' => 'cancelled']);
        $issue->sellerOrder->order->update(['status' => 'cancelled']);
        $issue->update(['status' => 'resolved', 'counts_as_refusal' => true]);
    }

    /**
     * @return array{recipient_name: string, phone: string, address_line: string, city: string, province: string, postal_code: string}
     */
    private function delivery(): array
    {
        return ['recipient_name' => 'Ava Buyer', 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'province' => 'Metro Manila', 'postal_code' => '1000'];
    }
}
