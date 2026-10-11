<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_search_and_filter_marketplace_products(): void
    {
        $administrator = User::factory()->administrator()->create();
        $category = Category::create(['name' => 'Fashion', 'slug' => 'fashion', 'is_active' => true]);
        $store = $this->store('Northwind Goods');
        $matchingProduct = $this->product($store, 'Canvas Tote', 'TOTE-001', 'draft', $category->id);
        $otherProduct = $this->product($this->store('Harbor Market'), 'Travel Mug', 'MUG-001', 'active');

        $this->actingAs($administrator)->get(route('admin.products.index', [
            'search' => 'northwind',
            'store_id' => $store->id,
            'category_id' => $category->id,
            'status' => 'draft',
            'added' => 'last_30_days',
            'sort' => 'name',
        ]))
            ->assertSee($matchingProduct->name)
            ->assertSee($store->name)
            ->assertDontSee($otherProduct->name);
    }

    public function test_administrator_can_view_product_details_and_recorded_listing_history(): void
    {
        $administrator = User::factory()->administrator()->create();
        $store = $this->store('Northwind Goods');
        $product = $this->product($store, 'Canvas Tote', 'TOTE-001', 'active', description: 'A durable market tote.');
        $product->variants()->create(['color' => 'Navy', 'size' => 'Medium', 'sku' => 'TOTE-N-M', 'stock_quantity' => 3]);
        AuditLog::create(['actor_user_id' => $administrator->id, 'action' => 'product.reviewed', 'entity_type' => Product::class, 'entity_id' => $product->id]);

        $this->actingAs($administrator)->get(route('admin.products.show', $product))
            ->assertSee('Canvas Tote')
            ->assertSee('A durable market tote.')
            ->assertSee('Northwind Goods')
            ->assertSee('Moderate listing')
            ->assertSee('Navy / Medium')
            ->assertSee('Product Reviewed');
    }

    public function test_administrator_can_moderate_a_product_and_record_the_reason(): void
    {
        $administrator = User::factory()->administrator()->create();
        $product = $this->product($this->store('Northwind Goods'), 'Canvas Tote', 'TOTE-001', 'draft');

        $this->actingAs($administrator)->patch(route('admin.products.moderate', $product), [
            'status' => 'active',
            'reason' => 'Listing passed the product review.',
        ])->assertRedirect(route('admin.products.show', $product));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'active']);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $administrator->id,
            'action' => 'product.moderated',
            'entity_type' => Product::class,
            'entity_id' => $product->id,
            'old_values->status' => 'draft',
            'new_values->status' => 'active',
            'new_values->reason' => 'Listing passed the product review.',
        ]);
    }

    public function test_non_administrators_cannot_moderate_a_product(): void
    {
        $product = $this->product($this->store('Northwind Goods'), 'Canvas Tote', 'TOTE-001', 'draft');

        $this->actingAs(User::factory()->seller()->create())->patch(route('admin.products.moderate', $product), [
            'status' => 'active',
            'reason' => 'This should not be allowed.',
        ])->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'draft']);
    }

    public function test_product_pagination_keeps_selected_filters(): void
    {
        $administrator = User::factory()->administrator()->create();
        $store = $this->store('Northwind Goods');

        for ($number = 1; $number <= 21; $number++) {
            $this->product($store, sprintf('Product %02d', $number), sprintf('SKU-%02d', $number), 'active');
        }

        $this->actingAs($administrator)->get(route('admin.products.index', ['status' => 'active', 'sort' => 'name', 'page' => 2]))
            ->assertSee('Product 21')
            ->assertDontSee('Product 01')
            ->assertSee('status=active', false)
            ->assertSee('sort=name', false);
    }

    public function test_non_administrators_cannot_access_marketplace_product_administration(): void
    {
        $product = $this->product($this->store('Northwind Goods'), 'Canvas Tote', 'TOTE-001', 'active');
        $buyer = User::factory()->create();
        $seller = User::factory()->seller()->create();

        $this->actingAs($buyer)->get(route('admin.products.index'))->assertForbidden();
        $this->actingAs($seller)->get(route('admin.products.show', $product))->assertForbidden();
    }

    private function store(string $name): Store
    {
        $slug = str($name)->slug().'-'.str()->lower(str()->random(6));
        $tenant = Tenant::create(['name' => $name, 'slug' => $slug, 'status' => 'active']);

        return Store::create(['tenant_id' => $tenant->id, 'name' => $name, 'slug' => $slug, 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);
    }

    private function product(Store $store, string $name, string $sku, string $status, ?string $categoryId = null, ?string $description = null): Product
    {
        return Product::create(['tenant_id' => $store->tenant_id, 'store_id' => $store->id, 'category_id' => $categoryId, 'name' => $name, 'slug' => str($name)->slug().'-'.str()->lower(str()->random(6)), 'sku' => $sku, 'description' => $description, 'price' => '299.00', 'stock_quantity' => 12, 'status' => $status]);
    }
}
