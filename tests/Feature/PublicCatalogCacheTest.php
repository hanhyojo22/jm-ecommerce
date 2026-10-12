<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Queries\CatalogQuery;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicCatalogCacheTest extends TestCase
{
    use DatabaseTruncation {
        truncateDatabaseTables as protected truncateDatabaseTablesUsingTrait;
    }

    protected function truncateDatabaseTables(): void
    {
        RefreshDatabaseState::$migrated = false;
        $this->truncateDatabaseTablesUsingTrait();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        RefreshDatabaseState::$migrated = false;
    }

    public function test_public_facets_are_reused_until_their_ttl_expires(): void
    {
        $this->freezeTime();
        $product = $this->product($this->store(), 'Original');

        $this->assertSame(['Original'], CatalogQuery::search([])['brands']->all());
        DB::table('products')->where('id', $product->id)->update(['brand' => 'Changed']);

        $this->assertSame(['Original'], CatalogQuery::search([])['brands']->all());
        $this->travel(301)->seconds();
        $this->assertSame(['Changed'], CatalogQuery::search([])['brands']->all());
    }

    public function test_product_and_store_changes_invalidate_public_facets_without_leaking_store_facets(): void
    {
        $firstStore = $this->store();
        $secondStore = $this->store();
        $firstProduct = $this->product($firstStore, 'First');
        $this->product($secondStore, 'Second');

        $this->assertSame(['First', 'Second'], CatalogQuery::search([])['brands']->all());
        $this->assertSame(['First'], CatalogQuery::search([], $firstStore)['brands']->all());

        $firstProduct->update(['brand' => 'Updated']);

        $this->assertSame(['Second', 'Updated'], CatalogQuery::search([])['brands']->all());
        $this->assertSame(['Updated'], CatalogQuery::search([], $firstStore)['brands']->all());
        $this->assertSame(['Second'], CatalogQuery::search([], $secondStore)['brands']->all());

        $secondStore->update(['status' => 'suspended']);

        $this->assertSame(['Updated'], CatalogQuery::search([])['brands']->all());
    }

    public function test_public_product_price_stock_and_category_visibility_remain_live(): void
    {
        $category = Category::create(['name' => 'Audio', 'slug' => 'audio', 'is_active' => true]);
        $product = $this->product($this->store(), 'Audio brand', $category);
        CatalogQuery::search([]);

        $product->update(['price' => '25.00', 'stock_quantity' => 0]);
        $result = CatalogQuery::search([]);

        $this->assertSame('25.00', $result['products']->first()->price);
        $this->assertSame(0, $result['products']->first()->stock_quantity);

        $category->update(['is_active' => false]);

        $this->assertSame([], CatalogQuery::search([])['categories']->pluck('id')->all());
    }

    public function test_product_category_changes_refresh_public_category_counts(): void
    {
        $audio = Category::create(['name' => 'Audio', 'slug' => 'audio', 'is_active' => true]);
        $books = Category::create(['name' => 'Books', 'slug' => 'books', 'is_active' => true]);
        $product = $this->product($this->store(), 'Brand', $audio);

        $initial = CatalogQuery::search([])['categories']->keyBy('id');
        $this->assertSame(1, (int) $initial->get($audio->id)->product_count);

        $product->update(['category_id' => $books->id]);

        $updated = CatalogQuery::search([])['categories']->keyBy('id');
        $this->assertSame(0, (int) $updated->get($audio->id)->product_count);
        $this->assertSame(1, (int) $updated->get($books->id)->product_count);
    }

    private function store(): Store
    {
        $suffix = str()->lower(str()->random(8));
        $tenant = Tenant::create(['name' => "Tenant {$suffix}", 'slug' => "tenant-{$suffix}", 'status' => 'active']);

        return Store::create([
            'tenant_id' => $tenant->id,
            'name' => "Store {$suffix}",
            'slug' => "store-{$suffix}",
            'phone' => '+639171234567',
            'address_line' => '1 Market Street',
            'city' => 'Manila',
            'status' => 'active',
        ]);
    }

    private function product(Store $store, string $brand, ?Category $category = null): Product
    {
        $suffix = str()->lower(str()->random(8));

        return Product::create([
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->id,
            'category_id' => $category?->id,
            'name' => "Product {$suffix}",
            'slug' => "product-{$suffix}",
            'sku' => "SKU-{$suffix}",
            'brand' => $brand,
            'price' => '20.00',
            'stock_quantity' => 3,
            'status' => 'active',
        ]);
    }
}
