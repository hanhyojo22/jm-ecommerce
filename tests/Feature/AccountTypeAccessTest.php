<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AccountTypeAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_start_selling_but_cannot_access_seller_management_or_admin_routes(): void
    {
        $buyer = User::factory()->create();

        $this->actingAs($buyer)->get(route('seller-application.create'))->assertOk();
        $this->actingAs($buyer)->get(route('seller.products.index'))->assertForbidden();
        $this->actingAs($buyer)->get(route('admin.orders.index'))->assertForbidden();
    }

    public function test_pending_seller_cannot_manage_products_or_orders(): void
    {
        $seller = User::factory()->seller()->create();

        $this->actingAs($seller)->get(route('seller.products.index'))->assertForbidden();
        $this->actingAs($seller)->get(route('seller.orders.index'))->assertForbidden();
    }

    public function test_seller_cannot_access_buyer_or_admin_routes(): void
    {
        $seller = User::factory()->seller()->create();

        $this->actingAs($seller)->get(route('cart.index'))->assertForbidden();
        $this->actingAs($seller)->get(route('admin.orders.index'))->assertForbidden();
    }

    public function test_admin_routes_and_admin_management_are_restricted_by_role(): void
    {
        $administrator = User::factory()->administrator()->create();
        $owner = User::factory()->owner()->create();

        $this->actingAs($administrator)->get(route('admin.orders.index'))->assertOk();
        $this->actingAs($administrator)->get(route('admin.accounts.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('admin.accounts.index'))->assertOk();
    }

    public function test_owner_can_create_and_deactivate_an_admin_but_not_an_owner(): void
    {
        config()->set('services.clerk.secret_key', 'sk_test');
        Http::fake(['https://api.clerk.com/v1/invitations' => Http::response([], 201)]);
        $owner = User::factory()->owner()->create();
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($owner)->post(route('admin.accounts.store'), [
            'name' => 'Operations Admin',
            'email' => 'operations@example.com',
        ])->assertRedirect(route('admin.accounts.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'operations@example.com',
            'account_type' => 'admin',
            'platform_role' => 'admin',
            'is_active' => true,
        ]);
        $this->actingAs($owner)->get(route('admin.accounts.index'))
            ->assertDontSee('name="password"', false)
            ->assertSee('Send Clerk invitation');
        Http::assertSent(fn ($request) => $request->data() === [
            'email_address' => 'operations@example.com',
            'redirect_url' => url('/sign-in'),
        ]);

        $this->actingAs($owner)->patch(route('admin.accounts.deactivate', $administrator))
            ->assertRedirect(route('admin.accounts.index'));

        $this->assertDatabaseHas('users', ['id' => $administrator->id, 'is_active' => false]);
        $this->actingAs($owner)->patch(route('admin.accounts.deactivate', $owner))->assertForbidden();
    }
}
