<?php

namespace Tests\Feature;

use App\Actions\UpdateSellerOrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderAggregateStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    public function test_an_order_waits_on_the_store_that_has_moved_the_least(): void
    {
        [$order, $first, $second] = $this->twoStoreOrder();

        $this->advance($first, ['confirmed', 'processing', 'shipped']);

        // The second store has not confirmed, so the order is not shipped.
        $this->assertSame('pending', $order->refresh()->status);

        $this->advance($second, ['confirmed']);
        $this->assertSame('confirmed', $order->refresh()->status);
    }

    public function test_an_order_is_delivered_only_once_every_store_has_delivered(): void
    {
        [$order, $first, $second] = $this->twoStoreOrder();

        $this->advance($first, ['confirmed', 'processing', 'shipped', 'delivered']);
        $this->assertSame('pending', $order->refresh()->status);

        $this->advance($second, ['confirmed', 'processing', 'shipped', 'delivered']);
        $this->assertSame('delivered', $order->refresh()->status);
    }

    public function test_a_cancelled_store_is_no_longer_waited_on(): void
    {
        [$order, $first, $second] = $this->twoStoreOrder();

        $this->advance($second, ['cancelled']);
        $this->advance($first, ['confirmed', 'processing', 'shipped', 'delivered']);

        $this->assertSame('delivered', $order->refresh()->status);
    }

    public function test_an_order_is_cancelled_only_once_every_store_is(): void
    {
        [$order, $first, $second] = $this->twoStoreOrder();

        $this->advance($first, ['cancelled']);
        $this->assertSame('pending', $order->refresh()->status);

        $this->advance($second, ['cancelled']);
        $this->assertSame('cancelled', $order->refresh()->status);
    }

    public function test_a_status_transition_does_not_rewrite_order_or_payment_totals(): void
    {
        [$order, $first] = $this->twoStoreOrder();
        $first->update(['subtotal' => '250.00', 'total' => '250.00']);

        $this->advance($first, ['confirmed']);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'subtotal' => '598.00', 'total' => '598.00']);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'amount' => '598.00']);
        $this->assertDatabaseCount('payment_adjustments', 0);
    }

    /**
     * @param  array<int, string>  $statuses
     */
    private function advance(SellerOrder $sellerOrder, array $statuses): void
    {
        foreach ($statuses as $status) {
            app(UpdateSellerOrderStatus::class)->handle($sellerOrder->refresh(), $status, $this->seller($sellerOrder));
        }
    }

    private function seller(SellerOrder $sellerOrder): User
    {
        return User::query()->whereHas('tenantMemberships', fn ($query) => $query->where('tenant_id', $sellerOrder->tenant_id))->sole();
    }

    /**
     * @return array{0: Order, 1: SellerOrder, 2: SellerOrder}
     */
    private function twoStoreOrder(): array
    {
        $buyer = User::factory()->create();
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

        return [$order, $this->storeOrder($order), $this->storeOrder($order)];
    }

    private function storeOrder(Order $order): SellerOrder
    {
        $suffix = str()->lower(str()->random(6));
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);
        $tenant = Tenant::create(['name' => 'Tenant '.$suffix, 'slug' => 'tenant-'.$suffix, 'status' => 'active']);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $seller->id, 'role' => 'owner']);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => 'Store '.$suffix, 'slug' => 'store-'.$suffix, 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);
        $product = Product::create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'name' => 'Product '.$suffix, 'slug' => 'p-'.$suffix, 'sku' => 'SKU-'.$suffix, 'price' => '299.00', 'stock_quantity' => 5, 'status' => 'active']);

        $sellerOrder = $order->sellerOrders()->create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'status' => 'pending', 'subtotal' => '299.00', 'shipping_amount' => '0.00', 'total' => '299.00']);
        $sellerOrder->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku, 'unit_price' => '299.00', 'quantity' => 1, 'line_total' => '299.00']);

        return $sellerOrder;
    }
}
