<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderIssue;
use App\Models\Product;
use App\Models\ReturnCase;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
            ->assertSee('₱10.00')
            ->assertSee('₱20.00')
            ->assertSee('₱30.00')
            ->assertDontSee('Store total')
            ->assertDontSee('Pay on delivery')
            ->assertDontSee('Checkout total ₱65.00')
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
            ->assertOk()->assertSee('Reviewable')
            ->assertDontSee('Reviewed')->assertDontSee('Own Store')
            ->assertViewHas('tabCounts', fn (array $counts): bool => $counts['all'] === 3
                && $counts['delivered'] === 3
                && $counts['to_review'] === 1
                && $counts['returns'] === 2);
        $this->actingAs($buyer)->get(route('orders.index', ['tab' => 'returns']))
            ->assertOk()->assertSee('Reviewed')->assertSee('Reviewable')
            ->assertDontSee('Own Store');
        $this->actingAs($buyer)->get(route('orders.index', ['tab' => 'delivered']))
            ->assertOk()->assertSee('Reviewed')->assertSee('Reviewable')->assertSee('Own Store');
        $this->actingAs($buyer)->get(route('orders.index'))
            ->assertSee('Reviewable')->assertSee('Reviewed')->assertSee('Own Store');
        $this->assertSame('delivered', $reviewable->fresh()->status);
    }

    public function test_delivered_purchase_links_to_its_order_without_listing_return_or_review_actions(): void
    {
        $buyer = User::factory()->create();
        $order = $this->order($buyer, 'BOTH-001', '10.00');
        $this->sellerOrder($order, 'Both Actions', 'delivered', '10.00');

        $this->actingAs($buyer)->get(route('orders.index', ['tab' => 'delivered']))
            ->assertOk()
            ->assertSee(route('orders.show', $order), false)
            ->assertDontSee('Write review')
            ->assertDontSee('Request return')
            ->assertDontSee('View return');
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
            ->assertOk()->assertSee('No purchases match “Secret”')->assertSee('Clear search')->assertDontSee('Secret Store')->assertDontSee('Secret Delivery');
        $this->actingAs($buyer)->get(route('orders.index', ['tab' => 'delivered']))
            ->assertOk()->assertSee('No delivered orders yet')->assertSee('View all purchases')->assertDontSee('Secret Delivery');
        $this->actingAs($buyer)->get(route('orders.index', ['tab' => 'unknown']))
            ->assertSessionHasErrors('tab');
        $this->actingAs($buyer)->get(route('orders.show', $otherOrder))->assertNotFound();
    }

    public function test_purchases_load_ten_more_at_a_time_and_keep_the_tab(): void
    {
        $buyer = User::factory()->create();
        foreach (range(1, 11) as $index) {
            $this->sellerOrder($this->order($buyer, sprintf('PAGE-%03d', $index), '10.00'), sprintf('Page Store %02d', $index), 'confirmed', '10.00');
        }

        $this->actingAs($buyer)->get(route('orders.index', ['tab' => 'to_dispatch']))
            ->assertOk()->assertSee('Showing 10 of 11 orders')->assertSee('Load more')
            ->assertSee(route('orders.index', ['tab' => 'to_dispatch', 'page' => 2]))
            ->assertViewHas('sellerOrders', fn ($sellerOrders): bool => $sellerOrders->count() === 10);
        // A loaded-more address rebuilds the whole longer list, so back and refresh keep it.
        $this->actingAs($buyer)->get(route('orders.index', ['tab' => 'to_dispatch', 'page' => 2]))
            ->assertOk()->assertSee('Showing 11 of 11 orders')->assertDontSee('Load more')
            ->assertViewHas('sellerOrders', fn ($sellerOrders): bool => $sellerOrders->count() === 11);
    }

    public function test_each_purchase_card_links_to_the_order_and_not_to_a_list_chat_action(): void
    {
        $buyer = User::factory()->create();
        $order = $this->order($buyer, 'CHAT-001', '10.00');
        $sellerOrder = $this->sellerOrder($order, 'Chatty Store', 'delivered', '10.00');

        $this->actingAs($buyer)->get(route('orders.index'))
            ->assertOk()
            ->assertSee(route('orders.show', $order), false)
            // The card itself is the way in; chat now starts from the order, not the list.
            ->assertDontSee('Chat with Chatty Store, order CHAT-001')
            ->assertDontSee(route('messages.seller-orders.start', $sellerOrder), false);
    }

    public function test_purchase_cards_show_every_item_in_the_order(): void
    {
        $buyer = User::factory()->create();
        $order = $this->order($buyer, 'MANY-001', '30.00');
        $sellerOrder = $this->sellerOrder($order, 'Bundle Store', 'delivered', '30.00');

        foreach (['Second Bundle Item', 'Third Bundle Item'] as $index => $name) {
            $sellerOrder->items()->create([
                'product_id' => $sellerOrder->items->first()->product_id,
                'product_name' => $name,
                'sku' => 'BUNDLE-'.($index + 2),
                'unit_price' => '10.00',
                'quantity' => 1,
                'line_total' => '10.00',
            ]);
        }

        $this->actingAs($buyer)->get(route('orders.index'))
            ->assertOk()
            ->assertSee('Bundle Store Product')
            ->assertSee('Second Bundle Item')
            ->assertSee('Third Bundle Item')
            ->assertDontSee('1 more item')
            ->assertSee(route('orders.show', $order), false);
    }

    public function test_purchase_cards_show_the_store_logo_when_one_exists(): void
    {
        Storage::fake('public');
        $buyer = User::factory()->create();
        $order = $this->order($buyer, 'LOGO-001', '10.00');
        $sellerOrder = $this->sellerOrder($order, 'Logo Store', 'delivered', '10.00');
        $sellerOrder->store->update(['logo_path' => 'stores/logo.png']);

        $this->actingAs($buyer)->get(route('orders.index'))
            ->assertOk()
            ->assertSee(Storage::disk('public')->url('stores/logo.png'), false)
            ->assertSee('Logo Store')
            // The hanger is only the fallback; a store with a logo never shows it.
            ->assertDontSee('M4 9.5 5.5 4h13L20 9.5', false);
    }

    public function test_purchase_pills_report_state_while_actions_live_on_the_order_page(): void
    {
        $buyer = User::factory()->create();
        $onlineOrder = $this->order($buyer, 'ONLINE-001', '10.00');
        $onlineOrder->payment()->update(['method' => 'gcash']);
        $unpaid = $this->sellerOrder($onlineOrder, 'Unpaid Store', 'pending', '10.00');
        $paidOrder = $this->order($buyer, 'PAID-001', '15.00');
        $paidOrder->payment()->update(['method' => 'gcash', 'status' => 'paid']);
        $this->sellerOrder($paidOrder, 'Paid Store', 'pending', '15.00')->update(['cod_collected_at' => now()]);
        $shipped = $this->sellerOrder($this->order($buyer, 'SHIP-001', '5.00'), 'Shipping Store', 'shipped', '5.00');
        $closed = $this->sellerOrder($this->order($buyer, 'OLD-001', '5.00'), 'Old Store', 'delivered', '5.00');
        $closed->update(['delivered_at' => now()->subDays(8)]);

        $response = $this->actingAs($buyer)->get(route('orders.index'))->assertOk();

        // The card only carries state; paying, confirming, and cancelling stay on the order page.
        $response->assertSee('To pay')->assertSee('On the way')->assertSee('Awaiting store')
            ->assertDontSee('Pay now')
            ->assertDontSee(route('orders.pay', $unpaid->order_id), false)
            ->assertDontSee('action="'.route('orders.received', $shipped).'"', false)
            ->assertDontSee('Pay with GCash before')
            ->assertDontSee('Ask to cancel')
            ->assertDontSee('The 7-day return window has closed.')
            ->assertDontSee('Contact support');
        $this->assertSame($unpaid->id, $response->viewData('sellerOrders')->first()->id);

        $this->get(route('orders.index', ['tab' => 'to_pay']))->assertOk()
            ->assertSee('Unpaid Store')->assertDontSee('Paid Store')->assertDontSee('Shipping Store');
        $this->get(route('orders.index', ['tab' => 'awaiting_confirmation']))->assertOk()
            ->assertSee('Paid Store')->assertDontSee('Unpaid Store');

        // Paid, then cancelled, and PayMongo's refund has not gone through yet.
        $unpaid->update(['status' => 'cancelled', 'cod_collected_at' => now()]);
        $this->get(route('orders.index'))->assertOk()
            ->assertSee('Cancelled')
            ->assertSee('1 refund to chase')
            ->assertDontSee('Refund due')
            ->assertDontSee('refund through PayMongo');

        $answered = $this->sellerOrder($this->order($buyer, 'RET-001', '5.00'), 'Answered Store', 'delivered', '5.00');
        ReturnCase::create(['seller_order_id' => $answered->id, 'buyer_id' => $buyer->id, 'opened_by' => $buyer->id, 'status' => 'offered', 'reason' => 'damaged', 'requested_remedy' => 'refund', 'remedy' => 'refund', 'details' => 'The item arrived broken.']);
        $this->get(route('orders.index'))->assertOk()
            ->assertSee('1 return needs you')
            ->assertDontSee('Answer return')
            ->assertDontSee('Answered Store answered your return.');

        $sendBack = $this->sellerOrder($this->order($buyer, 'RET-002', '6.00'), 'Return Store', 'delivered', '6.00');
        ReturnCase::create(['seller_order_id' => $sendBack->id, 'buyer_id' => $buyer->id, 'opened_by' => $buyer->id, 'status' => 'awaiting_return', 'reason' => 'damaged', 'requested_remedy' => 'refund', 'remedy' => 'refund', 'details' => 'The item arrived broken.', 'return_by' => now()->addDays(7)]);
        $this->get(route('orders.index'))->assertOk()
            ->assertSee('2 returns need you')
            ->assertDontSee('Send item back')
            ->assertDontSee('Send the item back to Return Store');

        // A store past its response deadline can be escalated; a buyer past theirs no longer counts as waiting on them.
        $lateStore = $this->sellerOrder($this->order($buyer, 'RET-003', '7.00'), 'Late Store', 'delivered', '7.00');
        ReturnCase::create(['seller_order_id' => $lateStore->id, 'buyer_id' => $buyer->id, 'opened_by' => $buyer->id, 'status' => 'awaiting_seller', 'reason' => 'damaged', 'requested_remedy' => 'refund', 'details' => 'The item arrived broken.', 'respond_by' => now()->subHour()]);
        $sendBack->returnCases()->update(['return_by' => now()->subDay()]);
        $this->get(route('orders.index'))->assertOk()
            ->assertSee('3 returns need you')
            ->assertSee('Store missed its deadline')
            ->assertDontSee('Ask JMMart to step in')
            ->assertDontSee('send-back date passed. Record the handoff now');

        // Confirming or disputing an outcome has no buyer cutoff, so it keeps waiting on the buyer past its date.
        $outcome = $this->sellerOrder($this->order($buyer, 'RET-004', '8.00'), 'Outcome Store', 'delivered', '8.00');
        ReturnCase::create(['seller_order_id' => $outcome->id, 'buyer_id' => $buyer->id, 'opened_by' => $buyer->id, 'status' => 'awaiting_confirmation', 'reason' => 'damaged', 'requested_remedy' => 'refund', 'remedy' => 'refund', 'details' => 'The item arrived broken.', 'buyer_respond_by' => now()->subDay()]);
        $this->get(route('orders.index'))->assertOk()
            ->assertSee('4 returns need you')
            ->assertDontSee('Confirm outcome')
            ->assertDontSee('Outcome Store recorded your refund.');
        $outcome->returnCases()->update(['buyer_respond_by' => now()->addDays(2)]);
        $this->get(route('orders.index'))->assertOk()
            ->assertSee('4 returns need you');

        // An offer left past its answer date can no longer be accepted, so it stops asking the buyer to decide.
        $answered->returnCases()->update(['buyer_respond_by' => now()->subDay()]);
        $this->get(route('orders.index'))->assertOk()
            ->assertSee('3 returns need you')
            ->assertSee('Response window passed')
            ->assertDontSee('Your deadline to respond passed');
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
        $sellerOrder = $order->sellerOrders()->create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'status' => $status, 'delivered_at' => $status === 'delivered' ? now() : null, 'subtotal' => $total, 'shipping_amount' => '0.00', 'total' => $total]);
        $sellerOrder->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku, 'unit_price' => $total, 'quantity' => 1, 'line_total' => $total]);

        return $sellerOrder->load('items');
    }
}
