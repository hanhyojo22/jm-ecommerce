<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Database\Seeder;

class PlaywrightQaSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            throw new \LogicException('Playwright fixtures may only be seeded in the testing environment.');
        }

        $this->call(CategorySeeder::class);

        User::factory()->create([
            'name' => 'QA Customer',
            'email' => 'qa-customer@example.test',
            'clerk_user_id' => 'qa-customer-identity',
        ]);
        $seller = User::factory()->seller()->create([
            'name' => 'QA Seller',
            'email' => 'qa-seller@example.test',
            'clerk_user_id' => 'qa-seller-identity',
        ]);
        $otherSeller = User::factory()->seller()->create([
            'name' => 'QA Other Seller',
            'email' => 'qa-other-seller@example.test',
            'clerk_user_id' => 'qa-other-seller-identity',
        ]);
        User::factory()->administrator()->create([
            'name' => 'QA Admin',
            'email' => 'qa-admin@example.test',
            'clerk_user_id' => 'qa-admin-identity',
        ]);

        $category = Category::query()->where('slug', 'electronics')->firstOrFail();
        [$tenant, $store] = $this->storeFor($seller, 'qa-northwind', 'QA Northwind');
        [$otherTenant, $otherStore] = $this->storeFor($otherSeller, 'qa-southwind', 'QA Southwind');

        $this->product($tenant, $store, $category, 'QA Canvas Tote', 'qa-canvas-tote', 'QA-TOTE-001', '19.99', 25);
        $this->product($otherTenant, $otherStore, $category, 'Other Seller QA Product', 'other-qa-product', 'QA-OTHER-001', '39.99', 10);
    }

    /**
     * @return array{0: Tenant, 1: Store}
     */
    private function storeFor(User $seller, string $slug, string $name): array
    {
        $tenant = Tenant::query()->create(['name' => $name, 'slug' => $slug, 'status' => 'active']);
        $store = Store::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name.' Store',
            'slug' => $slug.'-store',
            'phone' => '+639171234567',
            'address_line' => '10 QA Street',
            'city' => 'Manila',
            'status' => 'active',
        ]);

        TenantMember::query()->create(['tenant_id' => $tenant->id, 'user_id' => $seller->id, 'role' => 'owner']);

        return [$tenant, $store];
    }

    private function product(Tenant $tenant, Store $store, Category $category, string $name, string $slug, string $sku, string $price, int $stock): Product
    {
        return Product::query()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'sku' => $sku,
            'description' => 'Deterministic Playwright fixture for marketplace QA.',
            'price' => $price,
            'stock_quantity' => $stock,
            'status' => 'active',
        ]);
    }
}
