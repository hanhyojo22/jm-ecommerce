<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Queries\TopProductQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_popular_catalog_ranks_qualifying_sales_first_and_keeps_other_products(): void
    {
        $this->freezeTime();
        $winner = $this->product('Popular');
        $runnerUp = $this->product('Runner up');
        $cancelled = $this->product('Cancelled');
        $refunded = $this->product('Refunded');
        $unsold = $this->product('Unsold');
        $this->sellerOrderItem($winner, 4, 'delivered', 'paid');
        $this->sellerOrderItem($runnerUp, 2, 'delivered', 'paid');
        $this->sellerOrderItem($cancelled, 20, 'cancelled', 'paid');
        $this->sellerOrderItem($refunded, 20, 'delivered', 'refunded');

        $this->getJson(route('api.v1.products.index', ['sort' => 'popular', 'per_page' => 1]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $winner->id)
            ->assertJsonPath('meta.total', 5);
        $this->getJson(route('api.v1.products.index', ['sort' => 'popular', 'per_page' => 1, 'page' => 2]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $runnerUp->id);
        $response = $this->getJson(route('api.v1.products.index', ['sort' => 'popular']))->assertOk();
        $this->assertEqualsCanonicalizing(
            [$winner->id, $runnerUp->id, $cancelled->id, $refunded->id, $unsold->id],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_popular_catalog_without_sales_has_stable_pagination(): void
    {
        $this->freezeTime();
        $first = $this->product('First');
        $second = $this->product('Second');
        $expected = collect([$first->id, $second->id])->sort()->values()->all();

        foreach ($expected as $index => $productId) {
            $this->getJson(route('api.v1.products.index', ['sort' => 'popular', 'per_page' => 1, 'page' => $index + 1]))
                ->assertOk()
                ->assertJsonPath('data.0.id', $productId);
        }
    }

    public function test_top_products_uses_delivered_cod_sales_and_excludes_cancelled_or_refunded_sales(): void
    {
        $this->freezeTime();
        $winner = $this->product('Winner');
        $excluded = $this->product('Excluded');

        $this->sellerOrderItem($winner, 4, 'delivered', 'pending');
        $this->sellerOrderItem($excluded, 9, 'cancelled', 'paid');
        $this->sellerOrderItem($excluded, 9, 'delivered', 'refunded');
        TopProductQuery::forgetCachedRankings();

        $this->get(route('catalog.top-products'))
            ->assertSee('data-module-url=', false)
            ->assertSee('Winner')
            ->assertDontSee('Excluded')
            ->assertSee('last 30 days');
    }

    public function test_delivered_store_items_rank_while_another_store_in_the_order_is_pending(): void
    {
        $deliveredProduct = $this->product('Delivered store item');
        $pendingProduct = $this->product('Pending store item');
        $order = $this->sellerOrderItem($deliveredProduct, 2, 'delivered', 'pending');
        $order->sellerOrders()->create([
            'tenant_id' => $pendingProduct->tenant_id,
            'store_id' => $pendingProduct->store_id,
            'status' => 'pending',
            'subtotal' => '299.00',
            'shipping_amount' => '0.00',
            'total' => '299.00',
        ]);
        $order->update(['status' => 'pending']);
        TopProductQuery::forgetCachedRankings();

        $this->assertSame(
            ['Delivered store item'],
            $this->get(route('home'))->viewData('bestSellers')->pluck('name')->all(),
        );
        $this->get(route('catalog.top-products'))
            ->assertSee('Delivered store item')
            ->assertDontSee('Pending store item');
    }

    private function product(string $name): Product
    {
        $suffix = str()->lower(str()->random(8));
        $tenant = Tenant::create(['name' => "Tenant {$suffix}", 'slug' => "tenant-{$suffix}", 'status' => 'active']);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => "Store {$suffix}", 'slug' => "store-{$suffix}", 'phone' => '+639171234567', 'address_line' => '1 Market Street', 'city' => 'Manila', 'status' => 'active']);

        return Product::create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'name' => $name, 'slug' => str($name)->slug()."-{$suffix}", 'sku' => "SKU-{$suffix}", 'price' => '299.00', 'stock_quantity' => 5, 'status' => 'active']);
    }

    private function sellerOrderItem(Product $product, int $quantity, string $sellerOrderStatus, string $paymentStatus): Order
    {
        $buyer = User::factory()->create();
        $order = Order::create(['user_id' => $buyer->id, 'number' => 'ORD-'.str()->upper(str()->random(10)), 'status' => $sellerOrderStatus === 'delivered' ? 'delivered' : 'cancelled', 'recipient_name' => 'Buyer', 'phone' => '+639171234567', 'address_line' => '1 Market Street', 'city' => 'Manila', 'subtotal' => '299.00', 'shipping_amount' => '0.00', 'total' => '299.00']);
        $sellerOrder = $order->sellerOrders()->create(['tenant_id' => $product->tenant_id, 'store_id' => $product->store_id, 'status' => $sellerOrderStatus, 'subtotal' => '299.00', 'shipping_amount' => '0.00', 'total' => '299.00']);
        $sellerOrder->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku, 'unit_price' => '299.00', 'quantity' => $quantity, 'line_total' => bcmul('299.00', (string) $quantity, 2)]);
        $order->payment()->create(['method' => 'cod', 'status' => $paymentStatus, 'amount' => '299.00']);

        return $order;
    }
}
