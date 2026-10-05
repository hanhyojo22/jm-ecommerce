<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
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
            ->assertSee('livewire.js', false)
            ->assertSee($visible->name)
            ->assertDontSee($hiddenProduct->name)
            ->assertDontSee($hiddenStore->name)
            ->assertDontSee($hiddenTenant->name);
    }

    public function test_guest_catalog_keeps_the_sign_in_link_visible_on_small_screens(): void
    {
        $this->get(route('catalog.index'))
            ->assertSee(route('login'), false)
            ->assertSee('class="inline-flex min-h-11 items-center px-2 text-sm font-semibold text-slate-600 hover:text-emerald-800"', false)
            ->assertDontSee('class="hidden min-h-11 items-center px-2 text-sm font-semibold text-slate-600 hover:text-emerald-800 sm:inline-flex"', false);
    }

    public function test_marketplace_pages_include_the_clerk_configuration_needed_by_the_shared_header(): void
    {
        $product = $this->product();

        foreach ([
            route('catalog.index'),
            route('catalog.stores'),
            route('catalog.top-products'),
            route('catalog.show', [$product->store->slug, $product->slug]),
        ] as $url) {
            $this->get($url)->assertSee('name="clerk-publishable-key"', false);
        }
    }

    public function test_catalog_product_page_404s_for_a_non_public_product(): void
    {
        $product = $this->product(['status' => 'draft']);

        $this->get(route('catalog.show', [$product->store->slug, $product->slug]))->assertNotFound();
    }

    public function test_catalog_product_page_renders_its_primary_image(): void
    {
        Storage::fake('product_images');
        $product = $this->product();
        $path = 'products/canvas-tote.png';
        Storage::disk('product_images')->put($path, 'image');
        $product->images()->create(['path' => $path]);

        $this->get(route('catalog.show', [$product->store->slug, $product->slug]))
            ->assertOk()
            ->assertSee('livewire.js', false)
            ->assertSee(Storage::disk('product_images')->url($path));
    }

    public function test_verified_buyer_sees_add_to_cart_actions_in_the_catalog(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();

        $this->actingAs($buyer)->get(route('catalog.index'))
            ->assertSee('Cart')
            ->assertSee('Add to cart')
            ->assertSee('aria-controls="header-account-menu"', false)
            ->assertSee('livewire.js', false)
            ->assertSee(route('cart.store', $product))
            ->assertSee(route('profile.edit'));
    }

    public function test_marketplace_search_matches_a_public_store_name(): void
    {
        $product = $this->product(['name' => 'Unrelated product']);

        $this->get(route('catalog.index', ['q' => $product->store->name]))
            ->assertSee($product->name);
    }

    public function test_store_directory_links_guests_to_public_storefronts(): void
    {
        $product = $this->product();

        $this->get(route('catalog.stores'))
            ->assertSee('livewire.js', false)
            ->assertSee($product->store->name)
            ->assertSee(route('catalog.store', $product->store->slug), false);

        $this->get(route('catalog.store', $product->store->slug))
            ->assertSee($product->store->name);
    }

    public function test_catalog_filters_products_by_category_and_sorts_them(): void
    {
        $category = Category::create(['name' => 'Bags', 'slug' => 'bags', 'is_active' => true]);
        $categorizedProduct = $this->product(['name' => 'Canvas Market Bag', 'category_id' => $category->id, 'price' => 199]);
        $otherProduct = $this->product(['name' => 'Handmade Walnut Desk', 'price' => 9999]);

        $this->get(route('catalog.index', ['category' => $category->slug, 'sort' => 'price_asc']))
            ->assertSee($categorizedProduct->name)
            ->assertDontSee($otherProduct->name)
            ->assertSee('value="price_asc" selected', false);
    }

    public function test_catalog_uses_a_full_width_product_grid_without_filters(): void
    {
        $this->product();

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertDontSee('catalog-filters', false)
            ->assertDontSee('price_min', false)
            ->assertDontSee('name="seller"', false)
            ->assertDontSee('Apply filters')
            ->assertSee('xl:grid-cols-6', false);
    }

    public function test_catalog_shows_purchased_units_and_excludes_cancelled_seller_orders(): void
    {
        $product = $this->product();
        $buyer = User::factory()->create();
        $suffix = str()->lower(str()->random(8));
        $confirmedOrder = Order::create(['user_id' => $buyer->id, 'number' => "ORD-{$suffix}-confirmed", 'status' => 'confirmed', 'recipient_name' => 'Buyer', 'phone' => '+639171234567', 'address_line' => '1 Market Street', 'city' => 'Manila', 'subtotal' => 597, 'shipping_amount' => 0, 'total' => 597]);
        $confirmedSellerOrder = $confirmedOrder->sellerOrders()->create(['tenant_id' => $product->tenant_id, 'store_id' => $product->store_id, 'status' => 'confirmed', 'subtotal' => 597, 'shipping_amount' => 0, 'total' => 597]);
        $confirmedSellerOrder->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku, 'unit_price' => 199, 'quantity' => 3, 'line_total' => 597]);
        $cancelledOrder = Order::create(['user_id' => $buyer->id, 'number' => "ORD-{$suffix}-cancelled", 'status' => 'cancelled', 'recipient_name' => 'Buyer', 'phone' => '+639171234567', 'address_line' => '1 Market Street', 'city' => 'Manila', 'subtotal' => 796, 'shipping_amount' => 0, 'total' => 796]);
        $cancelledSellerOrder = $cancelledOrder->sellerOrders()->create(['tenant_id' => $product->tenant_id, 'store_id' => $product->store_id, 'status' => 'cancelled', 'subtotal' => 796, 'shipping_amount' => 0, 'total' => 796]);
        $cancelledSellerOrder->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku, 'unit_price' => 199, 'quantity' => 4, 'line_total' => 796]);

        $this->get(route('catalog.index'))
            ->assertSee('3 Sold')
            ->assertDontSee('7 Sold');
    }

    public function test_catalog_pagination_preserves_category_and_sort_queries(): void
    {
        $category = Category::create(['name' => 'Office', 'slug' => 'office', 'is_active' => true]);

        foreach (range(1, 13) as $number) {
            $this->product(['name' => "Office product {$number}", 'category_id' => $category->id, 'price' => $number]);
        }

        $this->get(route('catalog.index', ['category' => $category->slug, 'sort' => 'price_asc']))
            ->assertOk()
            ->assertSee('category=office&amp;sort=price_asc&amp;page=2', false);
    }

    public function test_catalog_category_navigation_shows_active_categories(): void
    {
        $visibleCategory = Category::create(['name' => 'Electronics', 'slug' => 'electronics', 'is_active' => true]);
        $secondVisibleCategory = Category::create(['name' => 'Fashion', 'slug' => 'fashion', 'is_active' => true]);
        $inactiveCategory = Category::create(['name' => 'Archived category', 'slug' => 'archived-category', 'is_active' => false]);
        $draftCategory = Category::create(['name' => 'Draft category', 'slug' => 'draft-category', 'is_active' => true]);

        $this->product(['category_id' => $visibleCategory->id]);
        $this->product(['category_id' => $visibleCategory->id]);
        $this->product(['category_id' => $secondVisibleCategory->id]);
        $this->product(['category_id' => $inactiveCategory->id]);
        $this->product(['category_id' => $draftCategory->id, 'status' => 'draft']);

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('Shop by category')
            ->assertSee('Electronics')
            ->assertSee('images/categories/electronics.png', false)
            ->assertSee('Draft category')
            ->assertDontSee('Because you are alive', false)
            ->assertDontSee('Archived category');
    }

    public function test_catalog_hides_the_category_explorer_when_only_one_category_is_available(): void
    {
        $category = Category::create(['name' => 'Electronics', 'slug' => 'electronics', 'is_active' => true]);
        $this->product(['category_id' => $category->id]);

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertDontSee('Shop by category')
            ->assertDontSee('All categories');
    }

    public function test_seller_storefront_shows_only_that_sellers_products(): void
    {
        $storefrontProduct = $this->product(['name' => 'Storefront Espresso Cup']);
        $otherProduct = $this->product(['name' => 'Other Store Tote']);

        $this->get(route('catalog.store', $storefrontProduct->store->slug))
            ->assertSee($storefrontProduct->name)
            ->assertDontSee($otherProduct->name)
            ->assertSee($storefrontProduct->store->name)
            ->assertSee('Open')
            ->assertSee('1 product')
            ->assertDontSee('from 2 sellers')
            ->assertDontSee($storefrontProduct->store->phone);
    }

    public function test_seller_storefront_only_lists_its_public_product_categories(): void
    {
        $firstCategory = Category::create(['name' => 'Desk goods', 'slug' => 'desk-goods', 'is_active' => true]);
        $secondCategory = Category::create(['name' => 'Travel goods', 'slug' => 'travel-goods', 'is_active' => true]);
        $otherCategory = Category::create(['name' => 'Other goods', 'slug' => 'other-goods', 'is_active' => true]);
        $firstProduct = $this->product(['category_id' => $firstCategory->id]);
        $this->product(['tenant_id' => $firstProduct->tenant_id, 'store_id' => $firstProduct->store_id, 'category_id' => $secondCategory->id]);
        $this->product(['category_id' => $otherCategory->id]);

        $this->get(route('catalog.store', $firstProduct->store->slug))
            ->assertSee('Desk goods')
            ->assertSee('Travel goods')
            ->assertSee('Other goods')
            ->assertDontSee('All sellers');
    }

    public function test_seller_sees_the_preview_toolbar_only_on_their_own_storefront(): void
    {
        $product = $this->product();
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);
        $otherSeller = User::factory()->seller()->create(['email_verified_at' => now()]);
        $otherTenant = Tenant::create(['name' => 'Other tenant', 'slug' => 'other-tenant-'.str()->lower(str()->random(8)), 'status' => 'active']);
        $otherStore = Store::create(['tenant_id' => $otherTenant->id, 'name' => 'Other Store', 'slug' => 'other-store-'.str()->lower(str()->random(8)), 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);
        TenantMember::create(['tenant_id' => $product->tenant_id, 'user_id' => $seller->id, 'role' => 'owner']);
        TenantMember::create(['tenant_id' => $otherTenant->id, 'user_id' => $otherSeller->id, 'role' => 'owner']);

        $this->actingAs($seller)->get(route('catalog.store', $product->store->slug))
            ->assertOk()
            ->assertSee('You’re viewing your public storefront')
            ->assertSee(route('seller.dashboard'), false)
            ->assertSee(route('seller.products.index'), false);

        $this->actingAs($otherSeller)->get(route('catalog.store', $product->store->slug))
            ->assertOk()
            ->assertDontSee('You’re viewing your public storefront')
            ->assertDontSee($otherStore->phone);
    }

    public function test_active_storefront_is_available_before_its_first_product_is_published(): void
    {
        $suffix = str()->lower(str()->random(8));
        $tenant = Tenant::create(['name' => "Tenant {$suffix}", 'slug' => "tenant-{$suffix}", 'status' => 'active']);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => 'New Store', 'slug' => "new-store-{$suffix}", 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);

        $this->get(route('catalog.store', $store->slug))
            ->assertOk()
            ->assertSee('New Store');
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
