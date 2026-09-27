<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_sees_store_overview_with_links_and_all_stores_list(): void
    {
        $administrator = User::factory()->administrator()->create();
        $active = $this->store('Northwind Goods', 'active');
        $this->attachOwner($active, 'Ava Seller', 'ava@example.com');
        $suspended = $this->store('Harbor Market', 'suspended');
        $this->attachOwner($suspended, 'Ben Seller', 'ben@example.com');
        $seller = User::factory()->seller()->create();
        $seller->sellerApplications()->create([
            'store_name' => 'Pending Shop',
            'seller_name' => 'Pia Pending',
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($administrator)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Stores overview')
            ->assertSee('Total stores')
            ->assertSee('Pending review')
            ->assertSee('Browse every seller store')
            ->assertSee('Northwind Goods')
            ->assertSee('Harbor Market')
            ->assertSee('Ava Seller')
            ->assertSee('View details')
            ->assertSee('Manage all stores')
            ->assertDontSee('<table', false)
            ->assertSee(route('admin.stores.index'), false)
            ->assertSee(route('admin.stores.index', ['status' => 'active']), false)
            ->assertSee(route('admin.stores.index', ['status' => 'suspended']), false)
            ->assertSee(route('admin.seller-applications.index'), false)
            ->assertSee(route('dashboard', ['selected' => $active->id]), false);
    }

    public function test_dashboard_search_status_filter_and_pagination_keep_query(): void
    {
        $administrator = User::factory()->administrator()->create();
        $matching = $this->store('Northwind Goods', 'active');
        $this->attachOwner($matching, 'Ava Seller', 'ava@example.com');
        $other = $this->store('Harbor Market', 'active');
        $this->attachOwner($other, 'Ben Seller', 'ben@example.com');

        $this->actingAs($administrator)->get(route('dashboard', ['search' => 'ava@example.com', 'status' => 'active']))
            ->assertOk()
            ->assertSee('Northwind Goods')
            ->assertDontSee('Harbor Market');

        for ($number = 1; $number <= 11; $number++) {
            $this->store(sprintf('Extra Store %02d', $number), 'active');
        }

        $this->actingAs($administrator)->get(route('dashboard', ['status' => 'active', 'page' => 2]))
            ->assertOk()
            ->assertSee('status=active', false);
    }

    public function test_dashboard_shows_empty_state_when_no_stores(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('No seller stores yet.');
    }

    public function test_administrator_can_open_a_store_detail_panel_with_links(): void
    {
        $administrator = User::factory()->administrator()->create();
        $store = $this->store('Northwind Goods', 'active');
        $this->attachOwner($store, 'Ava Seller', 'ava@example.com');

        $response = $this->actingAs($administrator)->get(route('dashboard', ['selected' => $store->id]));

        $response->assertOk()
            ->assertSee('A fine store selling useful goods.')
            ->assertSee('ava@example.com')
            ->assertSee('+639171234567')
            ->assertSee('10 Market Street, Manila')
            ->assertSee('Open storefront')
            ->assertSee('Manage store')
            ->assertSee(route('catalog.store', $store->slug), false)
            ->assertSee(route('admin.stores.show', $store), false);
    }

    public function test_store_detail_preserves_search_and_status_filters(): void
    {
        $administrator = User::factory()->administrator()->create();
        $matching = $this->store('Northwind Goods', 'active');
        $this->attachOwner($matching, 'Ava Seller', 'ava@example.com');
        $other = $this->store('Harbor Market', 'active');
        $this->attachOwner($other, 'Ben Seller', 'ben@example.com');

        $this->actingAs($administrator)->get(route('dashboard', [
            'search' => 'northwind',
            'status' => 'active',
            'selected' => $matching->id,
        ]))
            ->assertOk()
            ->assertSee('Northwind Goods')
            ->assertSee('A fine store selling useful goods.')
            ->assertSee('search=northwind', false)
            ->assertSee('status=active', false)
            ->assertDontSee('Harbor Market');
    }

    public function test_dashboard_rejects_an_unknown_selected_store(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)->get(route('dashboard', ['selected' => '01k0000000000000000000000']))
            ->assertRedirect()
            ->assertSessionHasErrors('selected');
    }

    public function test_non_administrators_cannot_access_dashboard(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->seller()->create();

        $this->actingAs($buyer)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($seller)->get(route('dashboard'))->assertForbidden();
    }

    private function store(string $name, string $status): Store
    {
        $slug = str($name)->slug().'-'.str()->lower(str()->random(6));
        $tenant = Tenant::create(['name' => $name, 'slug' => $slug.'-tenant', 'status' => $status === 'suspended' ? 'suspended' : 'active']);

        return Store::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'slug' => $slug,
            'description' => 'A fine store selling useful goods.',
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => $status,
        ]);
    }

    private function attachOwner(Store $store, string $name, string $email): User
    {
        $owner = User::factory()->seller()->create(['name' => $name, 'email' => $email]);
        $store->tenant->members()->create(['user_id' => $owner->id, 'role' => 'owner']);

        return $owner;
    }
}
