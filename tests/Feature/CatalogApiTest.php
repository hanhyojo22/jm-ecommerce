<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_catalog_lists_only_published_products(): void
    {
        $visible = $this->product(['name' => 'Cedar Lamp']);
        $visible->store->update(['logo_path' => 'store-logos/cedar-lamp.png']);
        $this->product(['name' => 'Cedar Draft', 'status' => 'draft']);
        $this->product(['name' => 'Cedar Suspended Store'], 'active', 'suspended');
        $this->product(['name' => 'Cedar Suspended Tenant'], 'suspended');

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id)
            ->assertJsonPath('data.0.name', 'Cedar Lamp')
            ->assertJsonPath('data.0.store.logo_url', Storage::disk('public')->url('store-logos/cedar-lamp.png'))
            ->assertJsonPath('meta.per_page', 12);
    }

    public function test_the_catalog_filters_by_search_category_seller_and_viewer_flags(): void
    {
        $category = Category::create(['name' => 'Lighting', 'slug' => 'lighting']);
        $first = $this->product(['name' => 'Brass Lamp', 'category_id' => $category->id]);
        $second = $this->product(['name' => 'Steel Chair', 'price' => '1500.00']);

        $this->getJson('/api/v1/products?q=brass')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $first->id);

        $this->getJson('/api/v1/products?category=lighting')
            ->assertOk()
            ->assertJsonPath('data.0.id', $first->id);

        $this->getJson('/api/v1/products?seller='.$second->store->slug)
            ->assertOk()
            ->assertJsonPath('data.0.id', $second->id);

        $this->getJson('/api/v1/products?in_stock=false')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/products?category=lighting')
            ->assertJsonPath('facets.categories.0.slug', 'lighting');
    }

    public function test_product_detail_returns_the_gallery_variants_and_the_lowest_price(): void
    {
        $product = $this->product(['name' => 'Canvas Tote', 'price' => '500.00']);
        $product->images()->create(['path' => 'products/tote-front.jpg', 'position' => 0]);
        $product->variants()->create([
            'color' => 'Black',
            'size' => 'Large',
            'sku' => 'TOTE-BLACK-L',
            'stock_quantity' => 4,
            'price' => '449.00',
        ]);

        $this->getJson("/api/v1/stores/{$product->store->slug}/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.effective_price', '449.00')
            ->assertJsonPath('data.has_variants', true)
            ->assertJsonPath('data.requires_variant_selection', true)
            ->assertJsonPath('data.variants.0.label', 'Black / Large')
            ->assertJsonPath('data.variants.0.in_stock', true)
            ->assertJsonPath('data.store.slug', $product->store->slug)
            ->assertJsonPath('data.review_summary.count', 0)
            ->assertJsonCount(1, 'data.images')
            ->assertJsonCount(0, 'data.related_products')
            ->assertJsonCount(0, 'data.more_from_store');

        $this->assertStringContainsString('products/tote-front.jpg', $this->getJson(
            "/api/v1/stores/{$product->store->slug}/products/{$product->slug}",
        )->json('data.images.0.url'));
    }

    public function test_a_hidden_product_is_reported_as_missing_instead_of_being_readable(): void
    {
        $draft = $this->product(['name' => 'Hidden Draft', 'status' => 'draft']);

        $this->getJson("/api/v1/stores/{$draft->store->slug}/products/{$draft->slug}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'resource.not_found');

        $this->getJson('/api/v1/stores/does-not-exist')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'resource.not_found');
    }

    public function test_categories_and_stores_only_expose_public_records(): void
    {
        $category = Category::create(['name' => 'Lighting', 'slug' => 'lighting']);
        $this->product(['name' => 'Visible', 'category_id' => $category->id]);
        $this->product(['name' => 'Draft', 'category_id' => $category->id, 'status' => 'draft']);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'lighting')
            ->assertJsonPath('data.0.product_count', 1);

        $this->getJson('/api/v1/stores')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $suspended = $this->product(['name' => 'Hidden store'], 'active', 'suspended');

        $this->getJson('/api/v1/stores')
            ->assertOk()
            ->assertJsonMissing(['slug' => $suspended->store->slug]);
    }

    public function test_suggestions_and_top_products_are_public(): void
    {
        $product = $this->product(['name' => 'Copper Headphones', 'price' => '299.00']);
        $product->images()->create(['path' => 'products/copper.jpg', 'position' => 0]);

        $this->getJson('/api/v1/products/suggestions?q=copper')
            ->assertOk()
            ->assertJsonPath('items.0.product_id', $product->id)
            ->assertJsonPath('items.0.slug', $product->slug)
            ->assertJsonPath('items.0.store_slug', $product->store->slug)
            ->assertJsonPath('items.0.price', '299.00')
            ->assertJsonPath('items.0.sold_out', false);

        $this->getJson('/api/v1/products/top')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_reviews_only_include_published_entries_and_the_seller_reply(): void
    {
        $product = $this->product(['name' => 'Reviewable Bag']);
        $published = $this->review($product, 5, 'published', 'Holds everything.');
        $this->review($product, 1, 'hidden', 'Not shown.');
        $published->responses()->create([
            'user_id' => User::factory()->seller()->create()->id,
            'author_type' => 'seller',
            'body' => 'Thanks for the kind words.',
        ]);

        $this->getJson("/api/v1/stores/{$product->store->slug}/products/{$product->slug}/reviews")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rating', 5)
            ->assertJsonPath('data.0.body', 'Holds everything.')
            ->assertJsonPath('data.0.verified_purchase', true)
            ->assertJsonPath('data.0.responses.0.body', 'Thanks for the kind words.');
    }

    private function product(array $attributes = [], string $tenantStatus = 'active', string $storeStatus = 'active'): Product
    {
        $suffix = str()->lower(str()->random(8));
        $tenant = Tenant::create(['name' => "Tenant {$suffix}", 'slug' => "tenant-{$suffix}", 'status' => $tenantStatus]);
        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => "Store {$suffix}",
            'slug' => "store-{$suffix}",
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => $storeStatus,
        ]);

        return Product::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => "Product {$suffix}",
            'slug' => "product-{$suffix}",
            'sku' => "SKU-{$suffix}",
            'price' => '199.00',
            'stock_quantity' => 3,
            'status' => 'active',
            ...$attributes,
        ]);
    }

    private function review(Product $product, int $rating, string $status, string $body): ProductReview
    {
        $buyer = User::factory()->create();
        $suffix = str()->lower(str()->random(8));
        $order = Order::create([
            'user_id' => $buyer->id,
            'number' => "ORD-{$suffix}",
            'status' => 'delivered',
            'recipient_name' => 'Buyer',
            'phone' => '+639171234567',
            'address_line' => '1 Market Street',
            'city' => 'Manila',
            'subtotal' => '199.00',
            'shipping_amount' => '0.00',
            'total' => '199.00',
        ]);
        $sellerOrder = $order->sellerOrders()->create([
            'tenant_id' => $product->tenant_id,
            'store_id' => $product->store_id,
            'status' => 'delivered',
            'subtotal' => '199.00',
            'shipping_amount' => '0.00',
            'total' => '199.00',
        ]);
        $item = $sellerOrder->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => '199.00',
            'quantity' => 1,
            'line_total' => '199.00',
        ]);

        return ProductReview::create([
            'product_id' => $product->id,
            'order_item_id' => $item->id,
            'user_id' => $buyer->id,
            'rating' => $rating,
            'body' => $body,
            'status' => $status,
        ]);
    }
}
