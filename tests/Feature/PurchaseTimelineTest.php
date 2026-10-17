<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_timeline_ticks_off_the_steps_a_store_has_passed(): void
    {
        $buyer = User::factory()->create();
        $order = $this->order($buyer, 'shipped');
        $this->storeOrder($order, 'Franil Store', 'Tp Link - 300Mbps', 'shipped');

        $response = $this->actingAs($buyer)->get(route('orders.index'));

        $response->assertOk()
            ->assertSee('aria-label="Franil Store progress"', false)
            // Shipped is where the order is, so it keeps its step number while the steps
            // behind it are ticked off, and Delivered is still ahead of it.
            ->assertSeeInOrder(['aria-label="Franil Store progress"', '>✓</span>', 'Processing', 'aria-current="step"', '>4</span>', 'Shipped', '>5</span>', 'Delivered'], false);
    }

    public function test_a_cancelled_store_order_shows_no_timeline(): void
    {
        $buyer = User::factory()->create();
        $order = $this->order($buyer, 'cancelled');
        $this->storeOrder($order, 'Trail & Tide', 'Bike Handlebar Bag', 'cancelled');

        // A cancelled order left the line rather than stopping on it, so there is no step to mark.
        $this->actingAs($buyer)->get(route('orders.index'))
            ->assertOk()
            ->assertDontSee('aria-label="Trail &amp; Tide progress"', false);
    }

    private function order(User $buyer, string $status): Order
    {
        $order = Order::create([
            'user_id' => $buyer->id,
            'number' => 'ORD-'.str()->upper(str()->random(10)),
            'status' => $status,
            'recipient_name' => 'Ava Buyer',
            'phone' => '+639171234567',
            'address_line' => '1 Market Street',
            'city' => 'Manila',
            'subtotal' => '299.00',
            'shipping_amount' => '0.00',
            'total' => '299.00',
        ]);
        $order->payment()->create(['method' => 'cod', 'status' => 'pending', 'amount' => '299.00']);

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
}
