<?php

namespace Tests\Feature;

use App\Models\BranchStock;
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

    public function test_search_matches_generic_names_and_their_aliases(): void
    {
        $store = $this->store();
        $biogesic = $this->product($store, ['name' => 'Biogesic 500 mg Tablet', 'generic_name' => 'Paracetamol']);
        $ascorbicAcid = $this->product($store, ['name' => 'Ceelin Drops', 'generic_name' => 'Ascorbic Acid (Vitamin C)']);
        $sodiumAscorbate = $this->product($store, ['name' => 'Fern-C 500 mg Capsule', 'generic_name' => 'Sodium Ascorbate']);
        $this->product($store, ['name' => 'Cotton balls']);

        $ids = fn (string $search): array => CatalogQuery::search(['q' => $search])['products']->getCollection()->pluck('id')->sort()->values()->all();

        $this->assertSame([$biogesic->id], $ids('paracetamol'));
        $this->assertSame([$biogesic->id], $ids('Acetaminophen'));
        $this->assertEqualsCanonicalizing([$ascorbicAcid->id, $sodiumAscorbate->id], $ids('vitamin c'));
        $this->assertSame('Ascorbic Acid', CatalogQuery::search(['q' => 'vitamin c'])['search']['ingredient']);
        $this->assertNull(CatalogQuery::search(['q' => 'paracetamol'])['search']['ingredient']);
    }

    public function test_a_combination_reached_through_an_alias_names_its_own_ingredient(): void
    {
        $store = $this->store();
        $this->product($store, ['name' => 'Ceelin Drops', 'generic_name' => 'Ascorbic Acid']);
        $combination = $this->product($store, ['name' => 'Immunpro Tablet', 'generic_name' => 'Sodium Ascorbate + Zinc']);

        $products = CatalogQuery::search(['q' => 'vitamin c'])['products']->getCollection()->keyBy('id');

        $this->assertSame('Also contains Sodium Ascorbate', $products[$combination->id]->search_section);
    }

    public function test_a_brand_the_branch_lacks_still_lists_other_brands_the_branch_has(): void
    {
        $branch = $this->store();
        $this->product($branch, ['name' => 'Biogesic 500 mg Tablet', 'generic_name' => 'Paracetamol']);
        $ritemed = $this->product($branch, ['name' => 'RiteMED 500 mg Tablet', 'generic_name' => 'Paracetamol']);
        BranchStock::create(['store_id' => $branch->id, 'product_id' => $ritemed->id, 'variant_key' => 'default', 'quantity' => 4]);

        $products = CatalogQuery::search(['q' => 'biogesic'], branch: $branch)['products']->getCollection();

        $this->assertSame([$ritemed->id], $products->pluck('id')->all());
        $this->assertSame(['Other brands of Paracetamol'], $products->pluck('search_section')->all());
    }

    public function test_brand_search_lists_the_brand_then_other_brands_then_combinations(): void
    {
        $store = $this->store();
        $biogesic = $this->product($store, ['name' => 'Biogesic 500 mg Tablet', 'generic_name' => 'Paracetamol', 'price' => 5.50]);
        $tempra = $this->product($store, ['name' => 'Tempra 500 mg Tablet', 'generic_name' => 'Paracetamol', 'price' => 8]);
        $ritemed = $this->product($store, ['name' => 'RiteMED 500 mg Tablet', 'generic_name' => 'Paracetamol', 'price' => 2.75]);
        $neozep = $this->product($store, ['name' => 'Neozep Forte Tablet', 'generic_name' => 'Phenylephrine HCl + Paracetamol', 'price' => 6]);
        $organizer = $this->product($store, ['name' => 'Pill organizer', 'description' => 'Fits a Biogesic strip.']);
        $this->product($store, ['name' => 'Losartan 50 mg', 'generic_name' => 'Losartan Potassium']);

        $products = CatalogQuery::search(['q' => 'biogesic'])['products']->getCollection();

        $this->assertSame(
            [$biogesic->id, $ritemed->id, $tempra->id, $neozep->id, $organizer->id],
            $products->pluck('id')->all(),
        );
        $this->assertSame(
            [null, 'Other brands of Paracetamol', 'Other brands of Paracetamol', 'Also contains Paracetamol', 'Other results'],
            $products->pluck('search_section')->all(),
        );
        $this->assertSame(
            [$ritemed->id, $biogesic->id, $neozep->id, $tempra->id, $organizer->id],
            CatalogQuery::search(['q' => 'biogesic', 'sort' => 'price_asc'])['products']->getCollection()->pluck('id')->all(),
        );
    }

    public function test_search_without_ingredient_matches_has_no_sections(): void
    {
        $store = $this->store();
        $this->product($store, ['name' => 'Arc Keyboard']);
        $this->product($store, ['name' => 'Stand', 'description' => 'For an arc keyboard.']);

        $products = CatalogQuery::search(['q' => 'arc'])['products']->getCollection();

        $this->assertSame([null, null], $products->pluck('search_section')->all());
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
