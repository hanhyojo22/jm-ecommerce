<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_only_shows_active_products_from_active_stores_and_tenants(): void
    {
        $visible = $this->product();
        $hiddenProduct = $this->product(['status' => 'draft']);
        $hiddenStore = $this->product(storeStatus: 'inactive');
        $hiddenTenant = $this->product(tenantStatus: 'suspended');

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee($visible->name)
            ->assertDontSee($hiddenProduct->name)
            ->assertDontSee($hiddenStore->name)
            ->assertDontSee($hiddenTenant->name);
    }

    public function test_catalog_product_page_404s_for_a_non_public_product(): void
    {
        $product = $this->product(['status' => 'draft']);

        $this->get(route('catalog.show', [$product->store->slug, $product->slug]))->assertNotFound();
    }

    public function test_catalog_product_page_renders_its_primary_image(): void
    {
        Storage::fake('public');
        $product = $this->product();
        $path = 'products/canvas-tote.png';
        Storage::disk('public')->put($path, 'image');
        $product->images()->create(['path' => $path]);

        $this->get(route('catalog.show', [$product->store->slug, $product->slug]))
            ->assertOk()
            ->assertSee(Storage::disk('public')->url($path));
    }

    public function test_verified_buyer_sees_add_to_cart_actions_in_the_catalog(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();

        $this->actingAs($buyer)->get(route('catalog.index'))
            ->assertSee('Cart')
            ->assertSee('Add to cart')
            ->assertSee(route('cart.store', $product))
            ->assertSee(route('profile.edit'));
    }

    public function test_catalog_filters_products_by_price_and_seller(): void
    {
        $affordableProduct = $this->product(['name' => 'Canvas Market Bag', 'price' => 199]);
        $premiumProduct = $this->product(['name' => 'Handmade Walnut Desk', 'price' => 9999]);

        $this->get(route('catalog.index', ['price_max' => 500]))
            ->assertSee($affordableProduct->name)
            ->assertDontSee($premiumProduct->name);

        $this->get(route('catalog.index', ['seller' => $premiumProduct->store->slug]))
            ->assertSee($premiumProduct->name)
            ->assertDontSee($affordableProduct->name);
    }

    public function test_seller_storefront_shows_only_that_sellers_products(): void
    {
        $storefrontProduct = $this->product(['name' => 'Storefront Espresso Cup']);
        $otherProduct = $this->product(['name' => 'Other Store Tote']);

        $this->get(route('catalog.store', $storefrontProduct->store->slug))
            ->assertSee($storefrontProduct->name)
            ->assertDontSee($otherProduct->name)
            ->assertSee('Everything currently available from this independent seller.');
    }

    private function product(array $attributes = [], string $tenantStatus = 'active', string $storeStatus = 'active'): Product
    {
        $suffix = str()->lower(str()->random(8));
        $tenant = Tenant::create(['name' => "Tenant {$suffix}", 'slug' => "tenant-{$suffix}", 'status' => $tenantStatus]);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => "Store {$suffix}", 'slug' => "store-{$suffix}", 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => $storeStatus]);

        return Product::create([
            'tenant_id' => $tenant->id, 'store_id' => $store->id, 'name' => "Product {$suffix}", 'slug' => "product-{$suffix}", 'sku' => "SKU-{$suffix}", 'price' => 199, 'stock_quantity' => 3, 'status' => 'active', ...$attributes,
        ]);
    }
}
