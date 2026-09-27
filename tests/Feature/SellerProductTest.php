<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_a_product_for_their_active_store(): void
    {
        [$owner, $tenant, $store] = $this->seller();

        $this->actingAs($owner)->post(route('seller.products.store'), [
            'name' => 'Canvas Tote', 'sku' => 'TOTE-001', 'description' => 'A durable tote.',
            'price' => '299.00', 'stock_quantity' => 12, 'status' => 'active',
        ])->assertRedirect(route('seller.products.index'));

        $this->assertDatabaseHas('products', ['tenant_id' => $tenant->id, 'store_id' => $store->id, 'sku' => 'TOTE-001', 'status' => 'active']);
    }

    public function test_seller_cannot_view_another_tenants_product_listing(): void
    {
        [$owner] = $this->seller();
        [, , $otherStore] = $this->seller();
        $otherStore->products()->create(['tenant_id' => $otherStore->tenant_id, 'name' => 'Private Item', 'slug' => 'private-item', 'sku' => 'PRIVATE-1', 'price' => 10, 'stock_quantity' => 1, 'status' => 'active']);

        $this->actingAs($owner)->get(route('seller.products.index'))
            ->assertOk()->assertDontSee('Private Item');
    }

    public function test_owner_can_assign_a_category_to_a_product(): void
    {
        [$owner, $tenant, $store] = $this->seller();
        $category = Category::create(['name' => 'Fashion', 'slug' => 'fashion', 'is_active' => true]);

        $this->actingAs($owner)->post(route('seller.products.store'), [
            'name' => 'Canvas Tote',
            'sku' => 'TOTE-001',
            'category_id' => $category->id,
            'price' => '299.00',
            'stock_quantity' => 12,
            'status' => 'active',
        ])->assertRedirect(route('seller.products.index'));

        $this->assertDatabaseHas('products', [
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'category_id' => $category->id,
        ]);
    }

    public function test_database_rejects_a_product_with_a_store_from_another_tenant(): void
    {
        [, $tenant] = $this->seller();
        [, , $otherStore] = $this->seller();

        $this->expectException(QueryException::class);

        Product::create([
            'tenant_id' => $tenant->id,
            'store_id' => $otherStore->id,
            'name' => 'Invalid product',
            'slug' => 'invalid-product',
            'sku' => 'INVALID-001',
            'price' => '100.00',
            'stock_quantity' => 1,
            'status' => 'active',
        ]);
    }

    public function test_product_creation_rejects_a_non_image_upload(): void
    {
        [$owner] = $this->seller();

        $this->actingAs($owner)->post(route('seller.products.store'), [
            'name' => 'Canvas Tote',
            'sku' => 'TOTE-001',
            'price' => '299.00',
            'stock_quantity' => 12,
            'status' => 'active',
            'image' => UploadedFile::fake()->create('payload.jpg', 10, 'text/plain'),
        ])->assertSessionHasErrors(['image']);
    }

    public function test_product_creation_stores_and_renders_an_uploaded_image(): void
    {
        Storage::fake('public');
        [$owner] = $this->seller();
        $image = UploadedFile::fake()->createWithContent(
            'canvas-tote.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL6TAAAAABJRU5ErkJggg=='),
        );

        $response = $this->actingAs($owner)->post(route('seller.products.store'), [
            'name' => 'Canvas Tote',
            'sku' => 'TOTE-001',
            'price' => '299.00',
            'stock_quantity' => 12,
            'status' => 'active',
            'image' => $image,
        ]);
        $product = Product::query()->with('images')->firstOrFail();
        $path = $product->images->firstOrFail()->path;

        $response->assertRedirect(route('seller.products.index'));
        Storage::disk('public')->assertExists($path);
        $this->actingAs($owner)
            ->get(route('seller.products.index'))
            ->assertSee(Storage::disk('public')->url($path));
    }

    public function test_seller_product_list_filters_by_search_status_and_stock(): void
    {
        [$owner, $tenant, $store] = $this->seller();
        $store->products()->create(['tenant_id' => $tenant->id, 'name' => 'Canvas Tote', 'slug' => 'canvas-tote', 'sku' => 'TOTE-001', 'price' => '299.00', 'stock_quantity' => 0, 'status' => 'draft']);
        $store->products()->create(['tenant_id' => $tenant->id, 'name' => 'Leather Wallet', 'slug' => 'leather-wallet', 'sku' => 'WALLET-001', 'price' => '499.00', 'stock_quantity' => 8, 'status' => 'active']);

        $this->actingAs($owner)->get(route('seller.products.index', [
            'search' => 'tote',
            'status' => 'draft',
            'stock' => 'out_of_stock',
            'sort' => 'name',
        ]))
            ->assertSee('Canvas Tote')
            ->assertDontSee('Leather Wallet')
            ->assertSee('Reset filters');
    }

    public function test_owner_can_update_their_own_product(): void
    {
        [$owner, $tenant, $store] = $this->seller();
        $product = $store->products()->create(['tenant_id' => $tenant->id, 'name' => 'Canvas Tote', 'slug' => 'canvas-tote', 'sku' => 'TOTE-001', 'price' => '299.00', 'stock_quantity' => 2, 'status' => 'draft']);

        $this->actingAs($owner)->patch(route('seller.products.update', $product), [
            'name' => 'Canvas Tote Deluxe',
            'sku' => 'TOTE-001',
            'price' => '349.00',
            'stock_quantity' => 7,
            'status' => 'active',
        ])->assertRedirect(route('seller.products.index'));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Canvas Tote Deluxe', 'stock_quantity' => 7, 'status' => 'active']);
    }

    public function test_owner_can_render_the_edit_product_form(): void
    {
        [$owner, $tenant, $store] = $this->seller();
        $product = $store->products()->create(['tenant_id' => $tenant->id, 'name' => 'Canvas Tote', 'slug' => 'canvas-tote', 'sku' => 'TOTE-001', 'price' => '299.00', 'stock_quantity' => 2, 'status' => 'draft']);

        $this->actingAs($owner)
            ->get(route('seller.products.edit', $product))
            ->assertSee('Edit product')
            ->assertSee('Canvas Tote')
            ->assertSee('Save changes');
    }

    public function test_seller_cannot_update_another_tenants_product(): void
    {
        [$owner] = $this->seller();
        [, $otherTenant, $otherStore] = $this->seller();
        $product = $otherStore->products()->create(['tenant_id' => $otherTenant->id, 'name' => 'Private Item', 'slug' => 'private-item', 'sku' => 'PRIVATE-1', 'price' => '100.00', 'stock_quantity' => 1, 'status' => 'active']);

        $this->actingAs($owner)->patch(route('seller.products.update', $product), [
            'name' => 'Changed', 'sku' => 'PRIVATE-1', 'price' => '100.00', 'stock_quantity' => 1, 'status' => 'active',
        ])->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Private Item']);
    }

    private function seller(): array
    {
        $owner = User::factory()->seller()->create(['email_verified_at' => now()]);
        $tenant = Tenant::create(['name' => 'Northwind', 'slug' => 'northwind-'.str()->lower(str()->random(6)), 'status' => 'active']);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => 'Northwind Goods', 'slug' => 'northwind-goods-'.str()->lower(str()->random(6)), 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'role' => 'owner']);

        return [$owner, $tenant, $store];
    }
}
