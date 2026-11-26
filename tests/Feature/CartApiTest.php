<?php

namespace Tests\Feature;

use App\Actions\BranchInventory;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CartApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_told_to_sign_in_before_reading_a_cart(): void
    {
        $this->getJson('/api/v1/cart')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'auth.unauthenticated');
    }

    public function test_a_buyer_can_add_change_select_and_remove_cart_items(): void
    {
        [$buyer, $product] = $this->buyerAndProduct();

        $this->actingAs($buyer)
            ->postJson('/api/v1/cart/items', ['branch' => $product->store_id, 'product_id' => $product->id, 'quantity' => 2])
            ->assertCreated()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.selected', true)
            ->assertJsonPath('data.items.0.line_total', '398.00')
            ->assertJsonPath('data.subtotal', '398.00')
            ->assertJsonPath('data.selected_count', 1);

        $itemId = $this->actingAs($buyer)->getJson('/api/v1/cart')->json('data.items.0.id');

        $this->actingAs($buyer)
            ->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 1])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 1)
            ->assertJsonPath('data.subtotal', '199.00');

        $this->actingAs($buyer)
            ->patchJson('/api/v1/cart/selection', ['selected' => []])
            ->assertOk()
            ->assertJsonPath('data.selected_count', 0)
            ->assertJsonPath('data.subtotal', '0.00');

        $this->actingAs($buyer)
            ->patchJson('/api/v1/cart/selection', ['selected' => [$itemId]])
            ->assertOk()
            ->assertJsonPath('data.selected_count', 1)
            ->assertJsonPath('data.subtotal', '199.00');

        $this->actingAs($buyer)
            ->deleteJson("/api/v1/cart/items/{$itemId}")
            ->assertOk()
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.subtotal', '0.00');
    }

    public function test_cart_items_stay_in_the_order_they_were_added(): void
    {
        [$buyer, $product] = $this->buyerAndProduct();
        $this->actingAs($buyer)
            ->postJson('/api/v1/cart/items', ['branch' => $product->store_id, 'product_id' => $product->id, 'quantity' => 1])
            ->assertCreated();
        $second = $product->replicate()->fill(['slug' => $product->slug.'-2', 'sku' => $product->sku.'-2']);
        $second->save();
        // Saved after the first item but added a minute earlier, so the order cannot come from storage.
        $earlier = $buyer->cart->items()->make(['product_id' => $second->id, 'quantity' => 1, 'selected' => true]);
        $earlier->id = Str::lower((string) Str::ulid(now()->subMinute()));
        $earlier->save();

        $this->actingAs($buyer)
            ->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $earlier->id)
            ->assertJsonPath('data.items.1.product_id', $product->id);
    }

    public function test_buy_now_adds_and_selects_only_that_item_in_one_request(): void
    {
        [$buyer, $product] = $this->buyerAndProduct();
        $other = tap(Product::create([
            'tenant_id' => $product->tenant_id,
            'store_id' => $product->store_id,
            'name' => 'API Mug',
            'slug' => "{$product->slug}-mug",
            'sku' => "{$product->sku}-MUG",
            'price' => '50.00',
            'stock_quantity' => 5,
            'status' => 'active',
        ]), fn (Product $product) => $this->stock($product, (int) (5)));

        $this->actingAs($buyer)
            ->postJson('/api/v1/cart/items', ['branch' => $other->store_id, 'product_id' => $other->id, 'quantity' => 1])
            ->assertCreated();

        $response = $this->actingAs($buyer)
            ->postJson('/api/v1/cart/items', ['branch' => $product->store_id, 'product_id' => $product->id, 'quantity' => 1, 'buy_now' => true])
            ->assertCreated()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.selected_count', 1)
            ->assertJsonPath('data.subtotal', '199.00');

        $selected = collect($response->json('data.items'))->where('selected', true);
        $this->assertSame([$product->id], $selected->pluck('product_id')->values()->all());
    }

    public function test_a_product_with_variants_requires_the_buyer_to_choose_one(): void
    {
        [$buyer, $product] = $this->buyerAndProduct();
        $variant = tap($product->variants()->create([
            'color' => 'Black',
            'size' => 'Large',
            'sku' => 'API-TOTE-BLACK',
            'stock_quantity' => 4,
            'price' => null,
        ]), fn (ProductVariant $variant) => $this->stock($product, (int) (4), $variant));

        $this->actingAs($buyer)
            ->postJson('/api/v1/cart/items', ['branch' => $product->store_id, 'product_id' => $product->id, 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation.failed')
            ->assertJsonPath('error.fields.variant_id.0', 'Choose an available color and size.');

        $this->actingAs($buyer)
            ->postJson('/api/v1/cart/items', [
                'branch' => $product->store_id,
                'product_id' => $product->id,
                'quantity' => 1,
                'variant_id' => $variant->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.variant.id', $variant->id)
            ->assertJsonPath('data.items.0.variant.label', 'Black / Large');
    }

    public function test_a_buyer_cannot_touch_another_buyers_cart_item(): void
    {
        [$buyer, $product] = $this->buyerAndProduct();
        $otherBuyer = User::factory()->create();

        $this->actingAs($buyer)
            ->postJson('/api/v1/cart/items', ['branch' => $product->store_id, 'product_id' => $product->id, 'quantity' => 1])
            ->assertCreated();
        $itemId = $this->actingAs($buyer)->getJson('/api/v1/cart')->json('data.items.0.id');

        $this->actingAs($otherBuyer)
            ->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 5])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'resource.not_found');

        $this->actingAs($otherBuyer)
            ->deleteJson("/api/v1/cart/items/{$itemId}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'resource.not_found');

        $this->actingAs($otherBuyer)
            ->patchJson('/api/v1/cart/selection', ['selected' => [$itemId]])
            ->assertOk()
            ->assertJsonPath('data.selected_count', 0);

        $this->assertDatabaseHas('cart_items', ['id' => $itemId, 'quantity' => 1]);
    }

    public function test_a_seller_cannot_buy_from_a_store_they_manage(): void
    {
        [$buyer, $product] = $this->buyerAndProduct();
        $product->tenant->members()->create(['user_id' => $buyer->id, 'role' => 'owner']);
        $buyer->update(['account_type' => 'seller']);

        $this->actingAs($buyer)
            ->postJson('/api/v1/cart/items', ['branch' => $product->store_id, 'product_id' => $product->id, 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('error.fields.cart.0', 'You cannot buy products from a store you manage.');
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
        $tenant->members()->create(['user_id' => $seller->id, 'role' => 'owner']);

        $product = tap(Product::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'API Tote',
            'slug' => "api-tote-{$suffix}",
            'sku' => "API-TOTE-{$suffix}",
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
