<?php

namespace Tests\Feature;

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

class PurchaseIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_orders_use_their_own_status_and_total_without_changing_shared_cod_payment(): void
    {
        $buyer = User::factory()->create();
        $order = $this->order($buyer, 'MIXED-001', '65.00');
        $pending = $this->sellerOrder($order, 'North', 'pending', '10.00');
        $shipped = $this->sellerOrder($order, 'East', 'shipped', '20.00');
        $delivered = $this->sellerOrder($order, 'West', 'delivered', '30.00');
        $processing = $this->sellerOrder($order, 'South', 'processing', '5.00');

        $response = $this->actingAs($buyer)->get(route('orders.index'));

        $response->assertOk()
            ->assertSee('North')
            ->assertSee('East')
            ->assertSee('West')
            ->assertSee('South')
            ->assertSee('Store total')
            ->assertSee('₱10.00')
            ->assertSee('₱20.00')
            ->assertSee('₱30.00')
            ->assertSee('Checkout total ₱65.00')
            ->assertSee('Cash on delivery')
            ->assertDontSee('Pay Now')
            ->assertViewHas('tabCounts', fn (array $counts): bool => $counts['all'] === 4
                && $counts['awaiting_confirmation'] === 1
                && $counts['to_dispatch'] === 1
                && $counts['to_receive'] === 1
                && $counts['delivered'] === 1
                && $counts['to_review'] === 1
                && $counts['returns'] === 0);

        $this->actingAs($buyer)->get(route('orders.index', ['tab' => 'awaiting_confirmation']))
            ->assertSee('North')->assertDontSee('East')->assertDontSee('West');
        $this->actingAs($buyer)->get(route('orders.index', ['tab' => 'to_receive']))
            ->assertSee('East')->assertDontSee('North')->assertDontSee('West');
        $this->actingAs($buyer)->get(route('orders.index', ['tab' => 'to_dispatch']))
            ->assertSee('South')->assertDontSee('North')->assertDontSee('West');
        $this->actingAs($buyer)->get(route('orders.index', ['tab' => 'delivered']))
            ->assertSee('West')->assertDontSee('North')->assertDontSee('East')->assertDontSee('South');
        $this->assertSame('pending', $order->payment->fresh()->status);
        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertSame('shipped', $shipped->fresh()->status);
        $this->assertSame('delivered', $delivered->fresh()->status);
        $this->assertSame('processing', $processing->fresh()->status);
    }

    public function test_review_and_return_tabs_follow_existing_eligibility_and_persisted_requests(): void
    {
        $buyer = User::factory()->create();
        $order = $this->order($buyer, 'REVIEW-001', '30.00');
        $reviewable = $this->sellerOrder($order, 'Reviewable', 'delivered', '10.00');
        $reviewed = $this->sellerOrder($order, 'Reviewed', 'delivered', '10.00');
        $reviewed->items->first()->review()->create([
            'product_id' => $reviewed->items->first()->product_id,
            'user_id' => $buyer->id,
            'rating' => 5,
            'status' => 'published',
        ]);
        $ownStore = $this->sellerOrder($order, 'Own Store', 'delivered', '10.00');
        TenantMember::create(['tenant_id' => $ownStore->tenant_id, 'user_id' => $buyer->id, 'role' => 'owner']);
        OrderIssue::create([
            'seller_order_id' => $reviewed->id,
            'buyer_id' => $buyer->id,
            'type' => 'return_refund',
            'details' => 'The item was damaged on arrival.',
            'status' => 'open',
        ]);
        OrderIssue::create([
            'seller_order_id' => $reviewable->id,
            'buyer_id' => $buyer->id,
            'type' => 'return_refund',
            'details' => 'The item arrived damaged.',
            'status' => 'open',
        ]);

        $this->actingAs($buyer)->get(route('orders.index', ['tab' => 'to_review']))
            ->assertOk()->assertSee('Reviewable')->assertSee('Write review')
            ->assertDontSee('Reviewed')->assertDontSee('Own Store')
            ->assertViewHas('tabCounts', fn (array $counts): bool => $counts['all'] === 3
                && $counts['delivered'] === 3
                && $counts['to_review'] === 1
                && $counts['returns'] === 2);
        $this->actingAs($buyer)->get(route('orders.index', ['tab' => 'returns']))
            ->assertOk()->assertSee('Reviewed')->assertSee('Reviewable')->assertSee('Return request open')
            ->assertSee('View return')->assertDontSee('Own Store');
        $this->actingAs($buyer)->get(route('orders.index', ['tab' => 'delivered']))
            ->assertOk()->assertSee('Reviewed')->assertSee('Reviewable')->assertSee('Own Store');
        $this->actingAs($buyer)->get(route('orders.index'))
            ->assertSee('Reviewable')->assertSee('Reviewed')->assertSee('Own Store');
        $this->assertSame('delivered', $reviewable->fresh()->status);
    }

    public function test_delivered_purchase_shows_review_and_return_actions_together(): void
    {
        $buyer = User::factory()->create();
        $order = $this->order($buyer, 'BOTH-001', '10.00');
        $sellerOrder = $this->sellerOrder($order, 'Both Actions', 'delivered', '10.00');

        $this->actingAs($buyer)->get(route('orders.index', ['tab' => 'delivered']))
            ->assertSee('Write review')->assertSee('Request return')->assertDontSee('View return');

        $issue = OrderIssue::create([
            'seller_order_id' => $sellerOrder->id,
            'buyer_id' => $buyer->id,
            'type' => 'return_refund',
            'details' => 'The item arrived damaged.',
            'status' => 'open',
        ]);

        $this->actingAs($buyer)->get(route('orders.index', ['tab' => 'delivered']))
            ->assertSee('Write review')->assertSee('View return')->assertDontSee('Request return');

        $issue->update(['status' => 'resolved']);

        $this->actingAs($buyer)->get(route('orders.index', ['tab' => 'delivered']))
            ->assertSee('Write review')->assertSee('Request return');
    }

    public function test_search_and_counts_exclude_another_buyers_purchases(): void
    {
        $buyer = User::factory()->create();
        $other = User::factory()->create();
        $ownOrder = $this->order($buyer, 'OWN-001', '10.00');
        $otherOrder = $this->order($other, 'OTHER-001', '10.00');
        $this->sellerOrder($ownOrder, 'Oak Store', 'confirmed', '10.00');
        $this->sellerOrder($otherOrder, 'Secret Store', 'confirmed', '10.00');
        $this->sellerOrder($otherOrder, 'Secret Delivery', 'delivered', '10.00');

        $this->actingAs($buyer)->get(route('orders.index', ['tab' => 'to_dispatch', 'search' => 'Oak']))
            ->assertOk()->assertSee('Oak Store')->assertDontSee('Secret Store')
            ->assertViewHas('tabCounts', fn (array $counts): bool => $counts['all'] === 1
                && $counts['delivered'] === 0
                && $counts['to_dispatch'] === 1);
        $this->actingAs($buyer)->get(route('orders.index', ['search' => 'Secret']))
            ->assertOk()->assertSee('No purchases match those filters')->assertDontSee('Secret Store')->assertDontSee('Secret Delivery');
        $this->actingAs($buyer)->get(route('orders.index', ['tab' => 'delivered']))
            ->assertOk()->assertSee('Nothing here yet')->assertDontSee('Secret Delivery');
        $this->actingAs($buyer)->get(route('orders.index', ['tab' => 'unknown']))
            ->assertSessionHasErrors('tab');
        $this->actingAs($buyer)->get(route('orders.show', $otherOrder))->assertNotFound();
    }

    private function order(User $buyer, string $number, string $total): Order
    {
        $order = Order::create([
            'user_id' => $buyer->id,
            'number' => $number,
            'status' => 'pending',
            'recipient_name' => 'Buyer',
            'phone' => '+639171234567',
            'address_line' => '1 Market Street',
            'city' => 'Manila',
            'subtotal' => $total,
            'shipping_amount' => '0.00',
            'total' => $total,
        ]);
        $order->payment()->create(['method' => 'cod', 'status' => 'pending', 'amount' => $total]);

        return $order;
    }

    private function sellerOrder(Order $order, string $name, string $status, string $total): SellerOrder
    {
        $slug = str()->slug($name).'-'.str()->lower(str()->random(6));
        $tenant = Tenant::create(['name' => $name, 'slug' => $slug, 'status' => 'active']);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => $name, 'slug' => $slug, 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);
        $product = Product::create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'name' => $name.' Product', 'slug' => $slug, 'sku' => $slug, 'price' => $total, 'stock_quantity' => 5, 'status' => 'active']);
        $sellerOrder = $order->sellerOrders()->create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'status' => $status, 'subtotal' => $total, 'shipping_amount' => '0.00', 'total' => $total]);
        $sellerOrder->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku, 'unit_price' => $total, 'quantity' => 1, 'line_total' => $total]);

        return $sellerOrder->load('items');
    }
}
