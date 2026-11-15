<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaderWorkspaceScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_workspace_shows_the_cart_and_bell(): void
    {
        $seller = $this->sellerWhoAlsoBuys();

        $this->actingAs($seller)
            ->get(route('orders.index'))
            ->assertOk()
            ->assertSee('data-cart-link', false)
            ->assertSee('data-notification-bell', false);
    }

    public function test_seller_workspace_hides_the_cart_but_keeps_the_bell(): void
    {
        $seller = $this->sellerWhoAlsoBuys();

        $this->actingAs($seller)
            ->get(route('seller.dashboard'))
            ->assertOk()
            ->assertDontSee('data-cart-link', false)
            ->assertSee('data-notification-bell', false);
    }

    private function sellerWhoAlsoBuys(): User
    {
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);

        $suffix = str()->lower(str()->random(8));
        $tenant = Tenant::create(['name' => "Tenant {$suffix}", 'slug' => "tenant-{$suffix}", 'status' => 'active']);
        Store::create([
            'tenant_id' => $tenant->id,
            'name' => "Store {$suffix}",
            'slug' => "store-{$suffix}",
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => 'active',
        ]);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $seller->id, 'role' => 'owner']);

        return $seller;
    }
}
