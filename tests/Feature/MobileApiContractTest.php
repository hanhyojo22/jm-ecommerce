<?php

namespace Tests\Feature;

use App\Actions\BranchInventory;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Pins the API fields the Flutter buyer app parses (mobile/lib/src/models/models.dart).
 * The app falls back to blanks for missing keys, so a renamed field would not crash
 * it; it would silently show empty prices or names. These tests catch that instead.
 */
class MobileApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_account_has_the_fields_the_app_reads(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'id', 'name', 'email', 'account_type', 'email_verified', 'is_platform_administrator',
            ]]);
    }

    public function test_the_catalog_and_product_page_have_the_fields_the_app_reads(): void
    {
        [, $product] = $this->buyerAndProduct();

        $list = $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonStructure(['data' => ['*' => [
                'id', 'name', 'slug', 'price', 'effective_price', 'thumbnail_url', 'in_stock',
                'store' => ['id', 'name', 'slug'],
            ]]]);
        $this->assertMoney($list, ['data.0.price', 'data.0.effective_price']);

        $page = $this->getJson("/api/v1/stores/{$product->store->slug}/products/{$product->slug}")
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'id', 'name', 'slug', 'price', 'effective_price', 'description', 'sku', 'in_stock',
                'has_variants', 'requires_variant_selection', 'stock_quantity', 'images', 'variants',
                'store' => ['id', 'name', 'slug'],
            ]]);
        $this->assertMoney($page, ['data.price', 'data.effective_price']);
    }

    public function test_the_cart_and_checkout_quote_have_the_fields_the_app_reads(): void
    {
        [$buyer, $product] = $this->buyerAndProduct();
        $this->actingAs($buyer)
            ->postJson('/api/v1/cart/items', ['branch' => $product->store_id, 'product_id' => $product->id, 'quantity' => 2])
            ->assertCreated();

        $cart = $this->actingAs($buyer)
            ->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'id', 'selected_count', 'subtotal',
                'items' => ['*' => [
                    'id', 'product_id', 'product_variant_id', 'quantity', 'selected', 'available',
                    'unit_price', 'line_total', 'image_url', 'product' => ['name', 'requires_prescription'],
                ]],
            ]]);
        $this->assertMoney($cart, ['data.subtotal', 'data.items.0.unit_price', 'data.items.0.line_total']);

        $quote = $this->actingAs($buyer)
            ->getJson('/api/v1/checkout')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'items', 'subtotal', 'addresses', 'preferred_address',
                'store_totals' => ['*' => ['name', 'total']],
                'payment_methods' => ['cod' => ['available', 'fee', 'reason']],
            ]]);
        $this->assertMoney($quote, ['data.subtotal', 'data.store_totals.0.total']);
    }

    public function test_a_prescription_medicine_in_the_cart_is_flagged_for_the_app(): void
    {
        [$buyer, $product] = $this->buyerAndProduct();
        $product->forceFill(['requires_prescription' => true])->save();
        $this->actingAs($buyer)
            ->postJson('/api/v1/cart/items', ['branch' => $product->store_id, 'product_id' => $product->id, 'quantity' => 1])
            ->assertCreated();

        $this->actingAs($buyer)
            ->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.items.0.product.requires_prescription', true);
    }

    public function test_placed_orders_have_the_fields_the_app_reads(): void
    {
        [$buyer, $product] = $this->buyerAndProduct();
        $this->actingAs($buyer)
            ->postJson('/api/v1/cart/items', ['branch' => $product->store_id, 'product_id' => $product->id, 'quantity' => 1])
            ->assertCreated();
        $orderId = $this->actingAs($buyer)
            ->postJson('/api/v1/checkout', [
                'recipient_name' => 'Ava Buyer',
                'phone' => '+639171234567',
                'address_line' => '100 Market Street',
                'city' => 'Manila',
                'province' => 'Metro Manila',
                'postal_code' => '1000',
                'delivery_latitude' => 14.5995,
                'delivery_longitude' => 120.9842,
                'payment_method' => 'cod',
                'client_request_id' => 'contract-1',
            ])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'payment_url']])
            ->json('data.id');

        $sellerOrderShape = [
            'id', 'status', 'subtotal', 'shipping_amount', 'total', 'can_confirm_receipt',
            'store' => ['id', 'name', 'slug'],
            'items' => ['*' => [
                'id', 'product_id', 'product_name', 'sku', 'variant_label', 'unit_price', 'quantity',
                'line_total', 'image_url', 'reviewed',
            ]],
        ];

        $list = $this->actingAs($buyer)
            ->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonStructure(['data' => ['*' => $sellerOrderShape], 'tab_counts']);
        $this->assertMoney($list, ['data.0.total', 'data.0.items.0.unit_price']);

        $detail = $this->actingAs($buyer)
            ->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'id', 'number', 'short_number', 'status', 'created_at', 'subtotal', 'shipping_amount',
                'total', 'can_pay',
                'delivery' => ['recipient_name', 'phone', 'address_line', 'city', 'province', 'postal_code'],
                'payment' => ['method', 'label', 'status', 'amount'],
                'seller_orders' => ['*' => $sellerOrderShape],
            ]]);
        $this->assertMoney($detail, [
            'data.subtotal', 'data.shipping_amount', 'data.total', 'data.payment.amount',
            'data.seller_orders.0.total', 'data.seller_orders.0.items.0.line_total',
        ]);
    }

    /**
     * The app adds money as two-decimal strings (formatters.dart addMoney), never floats.
     *
     * @param  array<int, string>  $paths
     */
    private function assertMoney(TestResponse $response, array $paths): void
    {
        foreach ($paths as $path) {
            $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', (string) $response->json($path), "{$path} is not a two-decimal money string.");
            $this->assertIsString($response->json($path), "{$path} must be a string, not a number.");
        }
    }

    /**
     * @return array{0: User, 1: Product}
     */
    private function buyerAndProduct(): array
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

        $product = tap(Product::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Contract Tote',
            'slug' => "contract-tote-{$suffix}",
            'sku' => "CONTRACT-TOTE-{$suffix}",
            'price' => '199.00',
            'stock_quantity' => 5,
            'status' => 'active',
        ]), fn (Product $product) => $this->stock($product, (int) (5)));

        return [$buyer, $product];
    }

    /**
     * Sets a product's (or variant's) units on hand at its first branch, keeping the company total in step.
     */
    private function stock(Product $product, int $quantity, ?ProductVariant $variant = null): void
    {
        BranchStock::query()->updateOrCreate(
            ['store_id' => $product->store_id, 'product_id' => $product->id, 'variant_key' => $variant?->id ?? 'default'],
            ['product_variant_id' => $variant?->id, 'quantity' => $quantity],
        );
        app(BranchInventory::class)->syncTotals($product);
    }
}
