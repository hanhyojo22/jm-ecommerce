<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_store_shows_its_own_progress_when_one_store_has_delivered_and_another_has_not(): void
    {
        $buyer = User::factory()->create();
        $order = $this->order($buyer);
        $this->storeOrder($order, 'Franil Store', 'Tp Link - 300Mbps', 'delivered');
        $this->storeOrder($order, 'Trail & Tide', 'Bike Handlebar Bag', 'pending');

        $response = $this->actingAs($buyer)->get(route('orders.show', $order));

        $response->assertOk()
            // Each store carries its own bar, and the page counts them off instead of
            // reporting one store's status as the whole order's.
            ->assertSee('aria-label="Franil Store progress"', false)
            ->assertSee('aria-label="Trail &amp; Tide progress"', false)
            ->assertSee('1 of 2 stores delivered · 1 awaiting confirmation')
            ->assertSee('Order in progress')
            ->assertDontSee('Order received')
            // The card counts the stores rather than repeating the badge each store already shows.
            ->assertDontSee('Awaiting seller confirmation');
    }

    public function test_a_single_store_order_keeps_the_one_progress_bar(): void
    {
        $buyer = User::factory()->create();
        $order = $this->order($buyer);
        $this->storeOrder($order, 'Franil Store', 'Tp Link - 300Mbps', 'pending');

        $this->actingAs($buyer)->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('aria-label="Order progress"', false)
            ->assertSee('Order received')
            ->assertDontSee('aria-label="Progress by store"', false);
    }

    private function order(User $buyer): Order
    {
        $order = Order::create([
            'user_id' => $buyer->id,
            'number' => 'ORD-'.str()->upper(str()->random(10)),
            'status' => 'pending',
            'recipient_name' => 'Ava Buyer',
            'phone' => '+639171234567',
            'address_line' => '1 Market Street',
            'city' => 'Manila',
            'subtotal' => '598.00',
            'shipping_amount' => '0.00',
            'total' => '598.00',
        ]);
        $order->payment()->create(['method' => 'cod', 'status' => 'pending', 'amount' => '598.00']);

        return $order;
    }

    private function storeOrder(Order $order, string $storeName, string $productName, string $status): SellerOrder
    {
        $suffix = str()->lower(str()->random(6));
        $tenant = Tenant::create(['name' => $storeName, 'slug' => 'tenant-'.$suffix, 'status' => 'active']);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => $storeName, 'slug' => 'store-'.$suffix, 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);
        $product = Product::create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'name' => $productName, 'slug' => 'product-'.$suffix, 'sku' => 'SKU-'.$suffix, 'price' => '299.00', 'stock_quantity' => 5, 'status' => 'active']);

        $sellerOrder = $order->sellerOrders()->create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'status' => $status, 'subtotal' => '299.00', 'shipping_amount' => '0.00', 'total' => '299.00']);
        $sellerOrder->items()->create(['product_id' => $product->id, 'product_name' => $productName, 'sku' => $product->sku, 'unit_price' => '299.00', 'quantity' => 1, 'line_total' => '299.00']);

        return $sellerOrder;
    }

    public function test_order_modal_returns_the_compact_ledger_body(): void
    {
        $buyer = User::factory()->create();
        $order = Order::create([
            'user_id' => $buyer->id,
            'number' => 'LEDGER-1',
            'status' => 'delivered',
            'recipient_name' => 'Ava Buyer',
            'phone' => '+639****4567',
            'address_line' => '1 Market Street',
            'city' => 'Manila',
            'subtotal' => '598.00',
            'shipping_amount' => '0.00',
            'total' => '598.00',
        ]);
        $order->payment()->create(['method' => 'cod', 'status' => 'paid', 'amount' => '598.00']);

        $suffix = str()->lower(str()->random(6));
        $tenant = Tenant::create(['name' => 'Ledger Store', 'slug' => 'ledger-'.$suffix, 'status' => 'active']);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => 'Ledger Store', 'slug' => 'store-'.$suffix, 'phone' => '+639****4567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);
        $order->sellerOrders()->create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'status' => 'delivered', 'subtotal' => '598.00', 'shipping_amount' => '0.00', 'total' => '598.00']);
        $order->sellerOrders->first()->items()->create(['product_name' => 'Canvas Tote', 'sku' => 'TOTE-001', 'unit_price' => '598.00', 'quantity' => 1, 'line_total' => '598.00']);

        $response = $this->actingAs($buyer)
            ->withHeader('X-Order-Modal', '1')
            ->get(route('orders.show', $order));

        $response->assertSee('Order #LEDGER-1')
            ->assertSee('Placed')
            ->assertDontSee('aria-label="Order progress"', false)
            ->assertSee('Delivered')
            ->assertSee('Canvas Tote')
            ->assertSee('Ava Buyer')
            ->assertSee('1 Market Street')
            ->assertSee('Total')
            ->assertSee('Cash on delivery · Paid')
            ->assertSee('Open full page')
            ->assertSee('Done')
            // The compact ledger is read-only and carries no shipment action.
            ->assertDontSee('Mark as shipped');
    }

    public function test_paid_cash_orders_do_not_ask_for_payment_again(): void
    {
        $buyer = User::factory()->create();
        $order = $this->order($buyer);
        $order->update(['status' => 'delivered']);
        $order->payment()->update(['status' => 'paid']);
        $this->storeOrder($order, 'Paid Store', 'Paid Item', 'delivered')->update(['cod_collected_at' => now()]);

        $this->actingAs($buyer)->get(route('orders.show', $order))
            ->assertSee('Paid in cash. Your payment has been recorded.')
            ->assertDontSee('Payment is due when the order arrives.')
            ->assertDontSee('Pay each store in cash when its items arrive.');
    }

    public function test_partial_cash_collection_is_explained_without_claiming_the_whole_order_is_paid(): void
    {
        $buyer = User::factory()->create();
        $order = $this->order($buyer);
        $this->storeOrder($order, 'Paid Store', 'Paid Item', 'delivered')->update(['cod_collected_at' => now()]);
        $this->storeOrder($order, 'Unpaid Store', 'Unpaid Item', 'shipped');

        $this->actingAs($buyer)->get(route('orders.show', $order))
            ->assertSee('Cash has been collected for part of this order.')
            ->assertSee('COD collected')
            ->assertSee('COD collection not recorded')
            ->assertDontSee('Paid in cash. Your payment has been recorded.');

        $this->withHeader('X-Order-Modal', '1')->get(route('orders.show', $order))
            ->assertSee('Cash on delivery · Partly collected')
            ->assertDontSee('Cash on delivery · Due on delivery');
    }

    public function test_review_prompts_follow_remaining_unreviewed_items(): void
    {
        $buyer = User::factory()->create();
        $order = $this->order($buyer);
        $order->update(['status' => 'delivered']);
        $sellerOrder = $this->storeOrder($order, 'Review Store', 'Reviewed Item', 'delivered');
        $reviewed = $sellerOrder->items()->firstOrFail();
        $reviewed->review()->create(['product_id' => $reviewed->product_id, 'user_id' => $buyer->id, 'rating' => 5, 'status' => 'published']);
        $remaining = $sellerOrder->items()->create(['product_id' => $reviewed->product_id, 'product_name' => 'Remaining Item', 'sku' => 'REMAINING', 'unit_price' => '0.00', 'quantity' => 1, 'line_total' => '0.00']);

        $this->actingAs($buyer)->withHeader('X-Order-Modal', '1')->get(route('orders.show', $order))
            ->assertSee('Review your items')
            ->assertSee('#review-item-'.$remaining->id, false)
            ->assertDontSee('#review-item-'.$reviewed->id, false);

        $remaining->review()->create(['product_id' => $remaining->product_id, 'user_id' => $buyer->id, 'rating' => 4, 'status' => 'published']);

        $this->get(route('orders.show', $order))
            ->assertSee('View your reviews')
            ->assertDontSee('Review your items')
            ->assertDontSee('Review items')
            ->assertDontSee('Review what you received.');
        $this->flushHeaders()->get(route('orders.index'))
            ->assertSee('Your reviews are saved')
            ->assertDontSee('ready to review');
    }

    public function test_own_store_and_removed_products_are_not_suggested_for_review(): void
    {
        $buyer = User::factory()->seller()->create();
        $order = $this->order($buyer);
        $order->update(['status' => 'delivered']);
        $ownStore = $this->storeOrder($order, 'Own Store', 'Own Item', 'delivered');
        TenantMember::create(['tenant_id' => $ownStore->tenant_id, 'user_id' => $buyer->id, 'role' => 'owner']);
        $removed = $this->storeOrder($order, 'Other Store', 'Removed Item', 'delivered');
        $removed->items()->update(['product_id' => null]);

        $this->actingAs($buyer)->withHeader('X-Order-Modal', '1')->get(route('orders.show', $order))
            ->assertSee('Done')
            ->assertDontSee('Review your items')
            ->assertDontSee('Review items');
        $this->flushHeaders()->get(route('orders.show', $order))
            ->assertDontSee('Write a review')
            ->assertDontSee('Share your experience by reviewing your items.');
    }

    public function test_return_forms_stay_collapsed_until_requested_or_recovering_input(): void
    {
        $buyer = User::factory()->create();
        $order = $this->order($buyer);
        $sellerOrder = $this->storeOrder($order, 'Return Store', 'Return Item', 'delivered');
        $sellerOrder->update(['delivered_at' => now()]);

        $response = $this->actingAs($buyer)->get(route('orders.show', $order));
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//section[@id="returns"]//details[@open]')->length);

        $response = $this->withSession(['_old_input' => ['store_order' => $sellerOrder->id, 'details' => 'My item arrived damaged.']])
            ->get(route('orders.show', $order));
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//section[@id="returns"]//details[@open]')->length);
        $response->assertSee('My item arrived damaged.');
    }

    public function test_help_defaults_to_an_available_request_for_the_selected_store(): void
    {
        $buyer = User::factory()->create();
        $order = $this->order($buyer);
        $delivered = $this->storeOrder($order, 'Delivered Store', 'Delivered Item', 'delivered');
        $pending = $this->storeOrder($order, 'Pending Store', 'Pending Item', 'pending');

        $response = $this->actingAs($buyer)->get(route('orders.show', [$order, 'seller_order_id' => $delivered->id]));
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//select[@name="type"]/option[@value="cancellation"][@disabled]')->length);
        $this->assertSame(1, $xpath->query('//select[@name="type"]/option[@value="other"][@selected]')->length);

        $response = $this->get(route('orders.show', [$order, 'seller_order_id' => $pending->id]));
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//select[@name="type"]/option[@value="cancellation"][@disabled]')->length);
        $this->assertSame(1, $xpath->query('//select[@name="type"]/option[@value="cancellation"][@selected]')->length);
    }
}
