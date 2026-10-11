<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspacePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_switches_views_and_the_saved_choice_controls_account_navigation_and_sign_in(): void
    {
        $seller = $this->sellerWithStore();

        $this->actingAs($seller)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('data-workspace-switch', false)
            ->assertSee('Switch Account')
            ->assertDontSee('Current view: Seller')
            ->assertSee('data-workspace="seller"', false);

        $this->actingAs($seller)->post(route('profile.workspace.update'), ['workspace' => 'customer'])
            ->assertRedirect(route('home'));

        $this->assertDatabaseHas('users', ['id' => $seller->id, 'account_type' => 'seller', 'preferred_workspace' => 'customer']);
        $this->actingAs($seller)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Switch Account')
            ->assertDontSee(route('seller.dashboard'), false)
            ->assertSee('data-role-navigation="customer"', false)
            ->assertDontSee('data-workspace="seller"', false);
        $this->actingAs($seller)->get(route('home'))
            ->assertOk()
            ->assertSee('Switch Account')
            ->assertDontSee(route('seller.dashboard'), false)
            ->assertDontSee(route('seller.products.index'), false);
        $this->actingAs($seller)->postJson(route('clerk.handoff.complete'), ['redirect' => ''])
            ->assertJsonPath('redirect', '/');
        $this->actingAs($seller)->get(route('seller.products.index'))->assertOk();
        $this->actingAs($seller)->get(route('orders.index'))
            ->assertOk()
            ->assertSee('data-role-navigation="customer"', false)
            ->assertDontSee(route('seller.dashboard'), false);

        $this->actingAs($seller)->post(route('profile.workspace.update'), ['workspace' => 'seller'])
            ->assertRedirect(route('seller.dashboard'));
        $this->assertDatabaseHas('users', ['id' => $seller->id, 'preferred_workspace' => 'seller']);
        $this->actingAs($seller)->postJson(route('clerk.handoff.complete'), ['redirect' => ''])
            ->assertJsonPath('redirect', '/seller');
        $this->actingAs($seller)->get(route('home'))
            ->assertOk()
            ->assertSee('Switch Account')
            ->assertDontSee(route('seller.dashboard'), false);
        $this->actingAs($seller)->get(route('orders.index'))
            ->assertOk()
            ->assertSee('data-role-navigation="customer"', false);
        $this->actingAs($seller)->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('Switch Account')
            ->assertDontSee('>Shopping</span>', false);
    }

    public function test_only_an_active_seller_workspace_can_be_selected(): void
    {
        $buyer = User::factory()->create();
        $admin = User::factory()->administrator()->create();
        $sellerWithoutStore = User::factory()->seller()->create();
        $suspendedSeller = $this->sellerWithStore();
        $suspendedSeller->sellerTenant()?->update(['status' => 'suspended']);

        $this->post(route('profile.workspace.update'), ['workspace' => 'customer'])->assertRedirect();
        $this->actingAs($buyer)->post(route('profile.workspace.update'), ['workspace' => 'seller'])->assertForbidden();
        $this->actingAs($admin)->post(route('profile.workspace.update'), ['workspace' => 'seller'])->assertForbidden();
        $this->actingAs($sellerWithoutStore)->post(route('profile.workspace.update'), ['workspace' => 'seller'])->assertForbidden();
        $this->actingAs($suspendedSeller)->post(route('profile.workspace.update'), ['workspace' => 'seller'])->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $buyer->id, 'preferred_workspace' => 'customer']);
        $this->actingAs($buyer)->get(route('profile.edit'))->assertDontSee('data-workspace-switch', false);
        $this->actingAs($admin)->get(route('profile.edit'))->assertDontSee('data-workspace-switch', false);
    }

    public function test_invalid_workspace_is_rejected_and_explicit_sign_in_destinations_are_preserved(): void
    {
        $seller = $this->sellerWithStore();

        $this->actingAs($seller)->post(route('profile.workspace.update'), ['workspace' => 'admin'])
            ->assertSessionHasErrors('workspace');
        $this->assertDatabaseHas('users', ['id' => $seller->id, 'preferred_workspace' => 'seller']);
        $this->actingAs($seller)->postJson(route('clerk.handoff.complete'), ['redirect' => '/cart'])
            ->assertJsonPath('redirect', '/cart');
        $this->actingAs($seller)->postJson(route('clerk.handoff.complete'), ['redirect' => '/'])
            ->assertJsonPath('redirect', '/');
    }

    private function sellerWithStore(): User
    {
        $seller = User::factory()->seller()->create();
        $tenant = Tenant::create(['name' => 'Northwind', 'slug' => 'northwind-'.str()->random(6), 'status' => 'active']);
        Store::create(['tenant_id' => $tenant->id, 'name' => 'Northwind Goods', 'slug' => 'northwind-goods-'.str()->random(6), 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $seller->id, 'role' => 'owner']);

        return $seller;
    }
}
