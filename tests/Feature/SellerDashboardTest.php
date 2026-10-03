<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_seller_sees_store_scoped_dashboard_and_empty_states(): void
    {
        [$seller, $tenant, $store] = $this->seller();
        Product::create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'name' => 'Canvas Tote', 'slug' => 'canvas-tote', 'sku' => 'TOTE-001', 'price' => '299.00', 'stock_quantity' => 0, 'status' => 'active']);
        [, $otherTenant, $otherStore] = $this->seller();
        Product::create(['tenant_id' => $otherTenant->id, 'store_id' => $otherStore->id, 'name' => 'Private Item', 'slug' => 'private-item', 'sku' => 'PRIVATE-001', 'price' => '199.00', 'stock_quantity' => 5, 'status' => 'active']);

        $this->actingAs($seller)->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('Seller Center')
            ->assertSee('Northwind Goods')
            ->assertSee('Canvas Tote')
            ->assertDontSee('Private Item')
            ->assertSee('No orders yet')
            ->assertSee('Action required')
            ->assertSee(route('seller.products.create'), false)
            ->assertSee(route('catalog.store', $store->slug), false)
            ->assertSee('target="_blank"', false)
            ->assertSee('View Northwind Goods storefront in a new tab');
    }

    public function test_seller_without_active_store_is_redirected_to_application_onboarding(): void
    {
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);

        $this->actingAs($seller)->get(route('seller.dashboard'))
            ->assertRedirect(route('seller-application.create'));
    }

    /**
     * @return array{0: User, 1: Tenant, 2: Store}
     */
    private function seller(): array
    {
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);
        $tenant = Tenant::create(['name' => 'Northwind', 'slug' => 'northwind-'.str()->lower(str()->random(6)), 'status' => 'active']);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => 'Northwind Goods', 'slug' => 'northwind-goods-'.str()->lower(str()->random(6)), 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $seller->id, 'role' => 'owner']);

        return [$seller, $tenant, $store];
    }
}
