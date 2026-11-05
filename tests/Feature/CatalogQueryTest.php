<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Queries\CatalogQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_filters_public_products_and_keeps_facets_scoped_to_all_visible_products(): void
    {
        $audio = Category::create(['name' => 'Audio', 'slug' => 'audio', 'is_active' => true]);
        $speakers = Category::create(['name' => 'Speakers', 'slug' => 'speakers', 'is_active' => true]);
        $hiddenCategory = Category::create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => true]);
        $store = $this->store();
        $otherStore = $this->store();

        $matchingProduct = $this->product($store, [
            'name' => 'Aurora headphones',
            'brand' => 'Aurora',
            'category_id' => $audio->id,
            'price' => 199,
            'stock_quantity' => 2,
        ]);
        $this->product($otherStore, [
            'name' => 'Portable speakers',
            'brand' => 'Volt',
            'category_id' => $speakers->id,
            'price' => 300,
        ]);
        $this->product($store, [
            'name' => 'Draft headphones',
            'brand' => 'Hidden draft',
            'category_id' => $hiddenCategory->id,
            'status' => 'draft',
        ]);
        $this->product($this->store('inactive'), ['name' => 'Hidden store item', 'brand' => 'Hidden store']);
        $this->product($this->store(tenantStatus: 'suspended'), ['name' => 'Hidden tenant item', 'brand' => 'Hidden tenant']);

        $result = CatalogQuery::search([
            'q' => 'Aurora',
            'max_price' => '24999.99',
            'in_stock' => '1',
            'brands' => ['Aurora'],
        ]);

        $this->assertSame([$matchingProduct->id], $result['products']->getCollection()->pluck('id')->all());
        $this->assertSame(['Aurora', 'Volt'], $result['brands']->all());

        $categories = $result['categories']->keyBy('id');
        $this->assertSame(1, (int) $categories->get($audio->id)->product_count);
        $this->assertSame(1, (int) $categories->get($speakers->id)->product_count);
        $this->assertSame(0, (int) $categories->get($hiddenCategory->id)->product_count);
    }

    public function test_store_scope_limits_products_and_facets_while_sorting_by_price(): void
    {
        $audio = Category::create(['name' => 'Audio', 'slug' => 'audio', 'is_active' => true]);
        $accessories = Category::create(['name' => 'Accessories', 'slug' => 'accessories', 'is_active' => true]);
        $store = $this->store();
        $otherStore = $this->store();

        $expensiveProduct = $this->product($store, ['name' => 'Expensive headphones', 'brand' => 'Aurora', 'category_id' => $audio->id, 'price' => 300]);
        $cheapProduct = $this->product($store, ['name' => 'Cheap case', 'brand' => 'Volt', 'category_id' => $accessories->id, 'price' => 100]);
        $this->product($otherStore, ['name' => 'Other store item', 'brand' => 'Outside', 'price' => 50]);

        $result = CatalogQuery::search(['sort' => 'price_asc'], $store);

        $this->assertSame([$cheapProduct->id, $expensiveProduct->id], $result['products']->getCollection()->pluck('id')->all());
        $this->assertSame(['Aurora', 'Volt'], $result['brands']->all());
        $this->assertSame([$accessories->id, $audio->id], $result['categories']->pluck('id')->all());
    }

    public function test_relevance_places_exact_and_name_matches_before_description_matches(): void
    {
        $store = $this->store();
        $exact = $this->product($store, ['name' => 'Arc']);
        $prefix = $this->product($store, ['name' => 'Arc Keyboard']);
        $description = $this->product($store, [
            'name' => 'Newest accessory',
            'description' => 'Includes an arc-shaped stand.',
        ]);
        $exact->forceFill(['created_at' => now()->subDays(3)])->save();
        $prefix->forceFill(['created_at' => now()->subDays(2)])->save();
        $description->forceFill(['created_at' => now()->subDay()])->save();

        $result = CatalogQuery::search(['q' => 'arc', 'sort' => 'relevance']);

        $this->assertSame(
            [$exact->id, $prefix->id, $description->id],
            $result['products']->getCollection()->pluck('id')->all(),
        );
    }

    public function test_search_treats_sql_wildcards_as_literal_characters(): void
    {
        $store = $this->store();
        $matchingProduct = $this->product($store, ['name' => '100% Cotton Shirt']);
        $this->product($store, ['name' => 'Plain Cotton Shirt']);

        $result = CatalogQuery::search(['q' => '%', 'sort' => 'relevance']);

        $this->assertSame(
            [$matchingProduct->id],
            $result['products']->getCollection()->pluck('id')->all(),
        );
    }

    private function store(string $status = 'active', string $tenantStatus = 'active'): Store
    {
        $suffix = str()->lower(str()->random(8));
        $tenant = Tenant::create(['name' => "Tenant {$suffix}", 'slug' => "tenant-{$suffix}", 'status' => $tenantStatus]);

        return Store::create([
            'tenant_id' => $tenant->id,
            'name' => "Store {$suffix}",
            'slug' => "store-{$suffix}",
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => $status,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function product(Store $store, array $attributes = []): Product
    {
        $suffix = str()->lower(str()->random(8));

        return Product::create([
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->id,
            'name' => "Product {$suffix}",
            'slug' => "product-{$suffix}",
            'sku' => "SKU-{$suffix}",
            'price' => 199,
            'stock_quantity' => 3,
            'status' => 'active',
            ...$attributes,
        ]);
    }
}
