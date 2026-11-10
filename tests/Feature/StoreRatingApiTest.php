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

class StoreRatingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_buyer_rates_the_store_once_after_delivery_and_the_store_shows_it(): void
    {
        [$buyer, $sellerOrder, $store] = $this->sellerOrder();

        $this->actingAs($buyer)
            ->getJson(route('api.v1.orders.show', $sellerOrder->order_id))
            ->assertJsonPath('data.seller_orders.0.can_rate_store', true)
            ->assertJsonPath('data.seller_orders.0.store_rating', null);

        $this->actingAs($buyer)
            ->postJson(route('api.v1.store-ratings.store', $sellerOrder), ['rating' => 4, 'comment' => '  Fast shipping.  '])
            ->assertCreated()
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.comment', 'Fast shipping.')
            ->assertJsonPath('data.author', $buyer->name);

        $this->actingAs($buyer)
            ->postJson(route('api.v1.store-ratings.store', $sellerOrder), ['rating' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('error.fields.rating.0', 'You already rated this store for this order.');

        $this->actingAs($buyer)
            ->getJson(route('api.v1.orders.show', $sellerOrder->order_id))
            ->assertJsonPath('data.seller_orders.0.can_rate_store', false)
            ->assertJsonPath('data.seller_orders.0.store_rating.rating', 4);

        $this->getJson(route('api.v1.stores.show', $store->slug))
            ->assertJsonPath('data.rating.count', 1)
            ->assertJsonPath('data.rating.average', 4);

        $this->getJson(route('api.v1.store-ratings.index', $store->slug))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.comment', 'Fast shipping.');

        $this->assertDatabaseCount('store_ratings', 1);
    }

    public function test_the_product_page_includes_the_store_rating(): void
    {
        [$buyer, $sellerOrder, $store, $product] = $this->sellerOrder();
        $this->actingAs($buyer)
            ->postJson(route('api.v1.store-ratings.store', $sellerOrder), ['rating' => 5])
            ->assertCreated();

        $this->getJson(route('api.v1.products.show', [$store->slug, $product->slug]))
            ->assertJsonPath('data.store.rating.count', 1)
            ->assertJsonPath('data.store.rating.average', 5);
    }

    public function test_only_the_buyer_of_a_delivered_order_from_another_store_can_rate(): void
    {
        [$buyer, $sellerOrder, , , $seller] = $this->sellerOrder('shipped');

        $this->actingAs(User::factory()->create())
            ->postJson(route('api.v1.store-ratings.store', $sellerOrder), ['rating' => 5])
            ->assertNotFound();

        $this->actingAs($buyer)
            ->postJson(route('api.v1.store-ratings.store', $sellerOrder), ['rating' => 5])
            ->assertUnprocessable()
            ->assertJsonPath('error.fields.rating.0', 'You can rate the store once this order is delivered.');

        $sellerOrder->update(['status' => 'delivered']);
        $sellerOrder->order->update(['user_id' => $seller->id]);

        $this->actingAs($seller)
            ->postJson(route('api.v1.store-ratings.store', $sellerOrder), ['rating' => 5])
            ->assertUnprocessable()
            ->assertJsonPath('error.fields.rating.0', 'You can’t rate your own store.');

        $this->assertDatabaseCount('store_ratings', 0);
    }

    /**
     * @return array{0: User, 1: SellerOrder, 2: Store, 3: Product, 4: User}
     */
    private function sellerOrder(string $status = 'delivered'): array
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->seller()->create();
        $suffix = str()->lower(str()->random(8));
        $tenant = Tenant::create(['name' => "Tenant {$suffix}", 'slug' => "tenant-{$suffix}", 'status' => 'active']);
        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => "Store {$suffix}",
            'slug' => "store-{$suffix}",
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => 'active',
        ]);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $seller->id, 'role' => 'owner']);
        $product = Product::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Rating Tote',
            'slug' => "rating-tote-{$suffix}",
            'sku' => "RATING-TOTE-{$suffix}",
            'price' => '199.00',
            'stock_quantity' => 5,
            'status' => 'active',
        ]);
        $order = Order::create([
            'user_id' => $buyer->id,
            'number' => 'JM-'.str()->upper(str()->random(8)),
            'status' => $status,
            'recipient_name' => $buyer->name,
            'phone' => '+639171234567',
            'address_line' => '100 Market Street',
            'city' => 'Manila',
            'subtotal' => '199.00',
            'shipping_amount' => '0.00',
            'total' => '199.00',
        ]);
        $order->payment()->create(['method' => 'cod', 'status' => 'paid', 'amount' => '199.00']);
        $sellerOrder = $order->sellerOrders()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'status' => $status,
            'subtotal' => '199.00',
            'shipping_amount' => '0.00',
            'total' => '199.00',
            'delivered_at' => $status === 'delivered' ? now() : null,
        ]);

        return [$buyer, $sellerOrder, $store, $product, $seller];
    }
}
