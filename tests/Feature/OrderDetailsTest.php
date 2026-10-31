<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
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

    private function storeOrder(Order $order, string $storeName, string $productName, string $status): void
    {
        $suffix = str()->lower(str()->random(6));
        $tenant = Tenant::create(['name' => $storeName, 'slug' => 'tenant-'.$suffix, 'status' => 'active']);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => $storeName, 'slug' => 'store-'.$suffix, 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);
        $product = Product::create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'name' => $productName, 'slug' => 'product-'.$suffix, 'sku' => 'SKU-'.$suffix, 'price' => '299.00', 'stock_quantity' => 5, 'status' => 'active']);

        $sellerOrder = $order->sellerOrders()->create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'status' => $status, 'subtotal' => '299.00', 'shipping_amount' => '0.00', 'total' => '299.00']);
        $sellerOrder->items()->create(['product_id' => $product->id, 'product_name' => $productName, 'sku' => $product->sku, 'unit_price' => '299.00', 'quantity' => 1, 'line_total' => '299.00']);
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
            ->assertSee('Confirmed')
            ->assertSee('Shipped')
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
}
