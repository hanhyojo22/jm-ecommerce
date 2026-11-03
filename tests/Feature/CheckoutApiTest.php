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
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckoutApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_reach_checkout(): void
    {
        $this->getJson('/api/v1/checkout')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'auth.unauthenticated');
    }

    public function test_an_empty_cart_gets_a_structured_checkout_error(): void
    {
        $buyer = User::factory()->create();

        $this->actingAs($buyer)
            ->getJson('/api/v1/checkout')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation.failed')
            ->assertJsonPath('error.fields.cart.0', 'Your cart is empty. Add a product before checking out.');
    }

    public function test_the_quote_describes_the_selected_items_and_available_payment_methods(): void
    {
        [$buyer, $product] = $this->cartWithProduct();

        $this->actingAs($buyer)
            ->getJson('/api/v1/checkout')
            ->assertOk()
            ->assertJsonPath('data.items.0.product_id', $product->id)
            ->assertJsonPath('data.items.0.line_total', '398.00')
            ->assertJsonPath('data.subtotal', '398.00')
            ->assertJsonPath('data.store_totals.0.name', $product->store->name)
            ->assertJsonPath('data.payment_methods.cod.available', true)
            ->assertJsonPath('data.payment_methods.gcash.available', false)
            ->assertJsonPath('data.payment_methods.gcash.fee', null)
            ->assertJsonPath('data.preferred_address', null);
    }

    public function test_a_cod_order_is_created_once_and_the_retry_replays_it(): void
    {
        [$buyer, $product] = $this->cartWithProduct();
        $payload = [...$this->delivery(), 'payment_method' => 'cod', 'client_request_id' => 'api-checkout-1'];

        $first = $this->actingAs($buyer)->postJson('/api/v1/checkout', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment.method', 'cod')
            ->assertJsonPath('data.payment_url', null)
            ->assertJsonPath('data.seller_orders.0.items.0.product_name', $product->name)
            ->assertJsonPath('data.seller_orders.0.store.slug', $product->store->slug);

        $orderId = $first->json('data.id');

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'user_id' => $buyer->id, 'client_request_id' => 'api-checkout-1']);
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseHas('delivery_addresses', ['user_id' => $buyer->id, 'city' => 'Manila']);

        $this->actingAs($buyer)
            ->postJson('/api/v1/checkout', $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $orderId);

        $this->assertDatabaseCount('orders', 1);

        $this->actingAs($buyer)
            ->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $first->json('data.seller_orders.0.id'))
            ->assertJsonPath('tab_counts.all', 1)
            ->assertJsonPath('tab_counts.delivered', 0);

        $otherBuyer = User::factory()->create();
        $this->actingAs($otherBuyer)
            ->getJson("/api/v1/orders/{$orderId}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'resource.not_found');
    }

    public function test_an_online_order_returns_the_paymongo_checkout_url(): void
    {
        config()->set('services.paymongo.secret_key', 'sk_test_123');
        Http::fake([
            'https://api.paymongo.com/v1/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_test_1',
                    'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/cs_test_1'],
                ],
            ]),
        ]);

        [$buyer, $product] = $this->cartWithProduct(online: true);

        $this->actingAs($buyer)
            ->getJson('/api/v1/checkout')
            ->assertOk()
            ->assertJsonPath('data.payment_methods.gcash.available', true)
            ->assertJsonPath('data.payment_methods.maya.available', true);

        $this->actingAs($buyer)
            ->postJson('/api/v1/checkout', [...$this->delivery(), 'payment_method' => 'gcash'])
            ->assertCreated()
            ->assertJsonPath('data.payment.method', 'gcash')
            ->assertJsonPath('data.can_pay', true)
            ->assertJsonPath('data.payment_url', 'https://checkout.paymongo.com/cs_test_1');

        $order = Order::query()->sole();

        $this->actingAs($buyer)
            ->postJson("/api/v1/orders/{$order->id}/pay")
            ->assertOk()
            ->assertJsonPath('data.payment_url', 'https://checkout.paymongo.com/cs_test_1');

        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'method' => 'gcash']);
        $this->assertSame($product->id, $order->sellerOrders()->first()->items()->first()->product_id);
    }

    public function test_the_buyer_confirms_a_shipped_store_order_arrived(): void
    {
        [$buyer, $sellerOrder] = $this->shippedOrder();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/v1/orders/seller-orders/{$sellerOrder->id}/received")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'resource.not_found');

        $this->actingAs($buyer)
            ->postJson("/api/v1/orders/seller-orders/{$sellerOrder->id}/received")
            ->assertOk()
            ->assertJsonPath('data.id', $sellerOrder->id)
            ->assertJsonPath('data.status', 'delivered');

        $this->assertSame('delivered', $sellerOrder->fresh()->status);
    }

    /**
     * @return array{0: User, 1: Product}
     */
    private function cartWithProduct(bool $online = false): array
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
            'paymongo_account_id' => $online ? 'acct_'.$suffix : null,
            'paymongo_account_status' => $online ? 'activated' : null,
        ]);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $seller->id, 'role' => 'owner']);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Checkout Tote',
            'slug' => "checkout-tote-{$suffix}",
            'sku' => "CHECKOUT-TOTE-{$suffix}",
            'price' => '199.00',
            'stock_quantity' => 5,
            'status' => 'active',
        ]);

        $this->actingAs($buyer)
            ->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertCreated();

        return [$buyer, $product];
    }

    /**
     * @return array{0: User, 1: SellerOrder}
     */
    private function shippedOrder(): array
    {
        [$buyer, $product] = $this->cartWithProduct();
        $order = Order::create([
            'user_id' => $buyer->id,
            'number' => 'JM-'.str()->upper(str()->random(8)),
            'status' => 'shipped',
            'recipient_name' => $buyer->name,
            'phone' => '+639171234567',
            'address_line' => '100 Market Street',
            'city' => 'Manila',
            'subtotal' => '199.00',
            'shipping_amount' => '0.00',
            'total' => '199.00',
        ]);
        $order->payment()->create(['method' => 'cod', 'amount' => '199.00']);
        $sellerOrder = $order->sellerOrders()->create([
            'tenant_id' => $product->tenant_id,
            'store_id' => $product->store_id,
            'status' => 'shipped',
            'subtotal' => '199.00',
            'shipping_amount' => '0.00',
            'total' => '199.00',
            'shipped_at' => now(),
        ]);

        return [$buyer, $sellerOrder];
    }

    /**
     * @return array<string, string>
     */
    private function delivery(): array
    {
        return [
            'recipient_name' => 'Ava Buyer',
            'phone' => '+639171234567',
            'address_line' => '100 Market Street',
            'city' => 'Manila',
            'province' => 'Metro Manila',
            'postal_code' => '1000',
            'delivery_note' => 'Leave with the guard.',
        ];
    }
}
