<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use App\Support\BuyerLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebStoreAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('marketplace.web_store.enabled', false);
    }

    public function test_the_public_storefront_is_gone_when_the_web_store_is_disabled(): void
    {
        [$store, $product] = $this->publicStore();

        $this->get(route('home'))->assertStatus(410)->assertSee('JMMart mobile app');
        $this->get(route('catalog.index'))->assertStatus(410);
        $this->get(route('catalog.suggestions'))->assertStatus(410);
        $this->get(route('catalog.top-products'))->assertStatus(410);
        $this->get(route('catalog.stores'))->assertStatus(410);
        $this->get(route('catalog.store', $store))->assertStatus(410);
        $this->get(route('catalog.show', [$store, $product]))->assertStatus(410);
        $this->get(route('web-store.unavailable'))->assertStatus(410);
    }

    public function test_the_buyer_web_app_is_gone_when_the_web_store_is_disabled(): void
    {
        $buyer = User::factory()->create();

        foreach ([
            'cart.index',
            'checkout.create',
            'orders.index',
            'reviews.index',
            'saved-products.index',
            'delivery-addresses.index',
            'messages.index',
            'profile.edit',
        ] as $routeName) {
            $this->actingAs($buyer)->get(route($routeName))->assertStatus(410);
        }

        $this->actingAs($buyer)->post(route('checkout.store'))->assertStatus(410);
    }

    public function test_seller_center_and_admin_workspaces_stay_open_when_the_web_store_is_disabled(): void
    {
        [$seller] = $this->sellerWithStore();
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($seller)->get(route('seller.dashboard'))->assertOk();
        $this->actingAs($seller)->get(route('seller.orders.index'))->assertOk();
        $this->actingAs($seller)->get(route('messages.index', ['context' => 'selling']))->assertOk();
        $this->actingAs($seller)->get(route('profile.edit'))->assertOk();

        $this->actingAs($administrator)->get(route('dashboard'))->assertOk();
        $this->actingAs($administrator)->get(route('admin.orders.index'))->assertOk();
    }

    public function test_seller_role_cannot_shop_on_the_web_store_when_it_is_disabled(): void
    {
        [$seller] = $this->sellerWithStore();

        $this->actingAs($seller)->get(route('home'))->assertStatus(410);
        $this->actingAs($seller)->get(route('cart.index'))->assertStatus(410);
        $this->actingAs($seller)->get(route('orders.index'))->assertStatus(410);
    }

    public function test_sign_in_destinations_follow_the_disabled_web_store(): void
    {
        [$seller] = $this->sellerWithStore();
        $buyer = User::factory()->create();
        $administrator = User::factory()->administrator()->create();

        $this->assertSame('seller.dashboard', $seller->landingRouteName());
        $this->assertSame('web-store.unavailable', $buyer->landingRouteName());
        $this->assertSame('dashboard', $administrator->landingRouteName());
    }

    public function test_a_seller_without_a_store_is_sent_to_the_mobile_notice(): void
    {
        $seller = User::factory()->seller()->create();

        $this->actingAs($seller)
            ->get(route('seller.dashboard'))
            ->assertRedirect(route('web-store.unavailable'));
    }

    public function test_a_seller_cannot_switch_to_the_customer_workspace_when_the_web_store_is_disabled(): void
    {
        [$seller] = $this->sellerWithStore();

        $this->actingAs($seller)
            ->post(route('profile.workspace.update'), ['workspace' => 'customer'])
            ->assertForbidden();

        $this->actingAs($seller)
            ->post(route('profile.workspace.update'), ['workspace' => 'seller'])
            ->assertRedirect(route('seller.dashboard'));
    }

    public function test_the_mobile_api_stays_open_when_the_web_store_is_disabled(): void
    {
        $this->publicStore();
        $buyer = User::factory()->create();

        $this->getJson('/api/v1/products')->assertOk();
        $this->actingAs($buyer)->getJson('/api/v1/cart')->assertOk();
    }

    public function test_a_signed_in_buyer_can_sign_out_from_the_mobile_notice(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('web-store.unavailable'))
            ->assertStatus(410)
            ->assertSee(route('logout'), false)
            ->assertDontSee('Seller or administrator sign in');
    }

    public function test_the_sign_in_page_does_not_send_visitors_to_the_closed_shop(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Browse without an account')
            ->assertDontSee(route('catalog.index'), false)
            ->assertSee('Customers shop in the JMMart mobile app.');
    }

    public function test_buyer_emails_point_to_the_app_instead_of_the_closed_web_store(): void
    {
        $this->assertSame('Open the JMMart app to see your order.', BuyerLink::emailLine('View your order', 'https://example.test/orders/1'));

        config()->set('marketplace.web_store.enabled', true);

        $this->assertSame('View your order: https://example.test/orders/1', BuyerLink::emailLine('View your order', 'https://example.test/orders/1'));
    }

    public function test_the_web_store_still_works_when_the_flag_is_enabled(): void
    {
        config()->set('marketplace.web_store.enabled', true);

        [$seller] = $this->sellerWithStore();
        $buyer = User::factory()->create();

        $this->get(route('home'))->assertOk();
        $this->actingAs($buyer)->get(route('cart.index'))->assertOk();
        $this->actingAs($buyer)->get(route('orders.index'))->assertOk();
        $this->actingAs($buyer)->get(route('profile.edit'))->assertOk();
        $this->actingAs($seller)->get(route('cart.index'))->assertOk();
    }

    /** @return array{0: Store, 1: Product} */
    private function publicStore(): array
    {
        $suffix = str()->lower(str()->random(8));
        $tenant = Tenant::create(['name' => "Tenant {$suffix}", 'slug' => "tenant-{$suffix}", 'status' => 'active']);
        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => "Store {$suffix}",
            'slug' => "store-{$suffix}",
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => 'active',
        ]);
        $product = Product::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => "Product {$suffix}",
            'slug' => "product-{$suffix}",
            'sku' => "SKU-{$suffix}",
            'price' => '99.00',
            'stock_quantity' => 5,
            'status' => 'active',
        ]);

        return [$store, $product];
    }

    /** @return array{0: User, 1: Tenant, 2: Store} */
    private function sellerWithStore(): array
    {
        $seller = User::factory()->seller()->create();
        [$store] = $this->publicStore();
        TenantMember::create(['tenant_id' => $store->tenant_id, 'user_id' => $seller->id, 'role' => 'owner']);

        return [$seller, $store->tenant, $store];
    }
}
