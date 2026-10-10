<?php

namespace Tests\Feature;

use App\Auth\ClerkIdentity;
use App\Auth\ClerkSessionVerifier;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Clerk session tokens expire 60 seconds after they are minted, so the `__session` cookie
 * that backs `Auth::viaRequest('clerk')` stops verifying almost immediately. Browsing to a
 * protected route after that window must re-bridge the session rather than send a signed-in
 * buyer back to the sign-in page, while still proving every request on its own.
 */
class ClerkSessionRefreshTest extends TestCase
{
    use RefreshDatabase;

    public static function protectedGetRoutes(): array
    {
        return [
            'cart' => ['/cart'],
            'checkout' => ['/checkout'],
            'orders' => ['/orders'],
            'messages' => ['/messages'],
            'profile' => ['/profile'],
            'start selling' => ['/start-selling'],
            'seller dashboard' => ['/seller'],
            'seller store settings' => ['/seller/store-settings'],
            'seller products' => ['/seller/products'],
            'seller product creation' => ['/seller/products/create'],
            'seller orders' => ['/seller/orders'],
            'seller earnings' => ['/seller/earnings'],
            'seller reviews' => ['/seller/reviews'],
            'admin dashboard' => ['/dashboard'],
            'admin moderation' => ['/admin/moderation'],
            'admin chat reports' => ['/admin/chat-reports'],
            'admin seller applications' => ['/admin/seller-applications'],
            'admin orders' => ['/admin/orders'],
            'admin payouts' => ['/admin/payouts'],
            'admin products' => ['/admin/products'],
            'admin stores' => ['/admin/stores'],
            'admin customers' => ['/admin/customers'],
            'admin audit logs' => ['/admin/audit-logs'],
            'admin accounts' => ['/admin/accounts'],
            'admin reviews' => ['/admin/reviews'],
        ];
    }

    #[DataProvider('protectedGetRoutes')]
    public function test_safe_navigation_to_a_protected_route_bridges_the_expired_session(string $uri): void
    {
        $this->get($uri)
            ->assertRedirect(route('clerk.refresh', ['redirect' => $uri]));
    }

    public function test_a_lapsed_session_returns_the_buyer_to_the_page_they_asked_for(): void
    {
        User::factory()->create(['clerk_user_id' => 'user_stale', 'email_verified_at' => now()]);
        $this->fakeVerifiedClerkSession('user_stale');

        // The stale state this fixes: the request carries no verifiable session token because
        // the previous 60 second token lapsed while the buyer was reading the catalogue.
        $this->get(route('cart.index'))
            ->assertRedirect(route('clerk.refresh', ['redirect' => '/cart']));

        $this->get(route('clerk.refresh', ['redirect' => '/cart']))
            ->assertOk()
            ->assertSee('data-clerk-session-handoff', false)
            ->assertSee(route('clerk.handoff.complete'), false)
            ->assertSee('data-redirect-url="/cart"', false);

        Auth::forgetGuards();

        $this->withHeader('Authorization', 'Bearer valid-session-token')
            ->postJson(route('clerk.handoff.complete'), ['redirect' => '/cart'])
            ->assertOk()
            ->assertJson(['redirect' => '/cart']);

        Auth::forgetGuards();

        $this->withHeader('Authorization', '')
            ->withUnencryptedCookie('__session', 'valid-session-token')
            ->get(route('cart.index'))
            ->assertOk();

        $this->assertAuthenticatedAs(User::where('clerk_user_id', 'user_stale')->sole(), 'clerk');
    }

    public function test_the_bridge_does_not_mint_a_session_by_itself(): void
    {
        $response = $this->get(route('clerk.refresh', ['redirect' => '/cart']));

        $response->assertOk();
        $response->assertCookieMissing('__session');

        $this->assertGuest('clerk');

        // Nothing was granted: the destination still refuses the visitor.
        $this->get(route('cart.index'))
            ->assertRedirect(route('clerk.refresh', ['redirect' => '/cart']));
    }

    public function test_the_bridge_offers_a_sign_in_that_returns_to_the_requested_page(): void
    {
        $this->get(route('clerk.refresh', ['redirect' => '/cart']))
            ->assertOk()
            ->assertSee('data-sign-in-url="'.route('login', ['redirect' => '/cart']).'"', false);
    }

    public function test_unsafe_verbs_are_never_bridged_so_a_mutation_is_not_replayed(): void
    {
        [, $product] = $this->sellerProduct();

        $this->post(route('cart.store', $product), ['quantity' => 1])
            ->assertRedirect(route('login'));

        $this->post('/seller/reviews/01J00000000000000000000000/response', ['body' => 'Thank you'])
            ->assertRedirect(route('login'));

        $this->patch('/admin/reviews/01J00000000000000000000000', ['status' => 'hidden', 'reason' => 'Report'])
            ->assertRedirect(route('login'));
    }

    public function test_every_protected_route_refuses_an_expired_session_without_replaying_writes(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (! in_array('auth', $route->gatherMiddleware(), true)) {
                continue;
            }

            $path = '/'.preg_replace('/\{[^}]+\}/', '01J00000000000000000000000', $route->uri());

            foreach (array_intersect($route->methods(), ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']) as $method) {
                $redirect = $method === 'GET'
                    ? route('clerk.refresh', ['redirect' => $path])
                    : route('login');

                $this->call($method, $path)->assertRedirect($redirect);
            }
        }
    }

    public function test_json_clients_receive_unauthorized_instead_of_a_bridge_redirect(): void
    {
        $this->getJson(route('cart.index'))->assertUnauthorized();
    }

    public function test_the_bridge_refuses_an_external_destination(): void
    {
        $this->get(route('clerk.refresh', ['redirect' => '//evil.example']))
            ->assertOk()
            ->assertSee('data-redirect-url="/"', false);
    }

    public function test_repeated_bridging_settles_on_the_sign_in_page(): void
    {
        $this->get(route('clerk.refresh', ['redirect' => '/cart']))->assertOk();

        $this->get(route('clerk.refresh', ['redirect' => '/cart']))
            ->assertRedirect(route('login', ['redirect' => '/cart']));
    }

    public function test_a_completed_handoff_resets_the_bridge_attempt_budget(): void
    {
        User::factory()->create(['clerk_user_id' => 'user_retry', 'email_verified_at' => now()]);
        $this->fakeVerifiedClerkSession('user_retry');

        $this->get(route('clerk.refresh', ['redirect' => '/cart']))->assertOk();

        Auth::forgetGuards();

        $this->withHeader('Authorization', 'Bearer valid-session-token')
            ->postJson(route('clerk.handoff.complete'), ['redirect' => '/cart'])
            ->assertOk();

        // A later lapse must still get a fresh bridge rather than dropping the buyer at sign-in.
        Auth::forgetGuards();

        $this->withHeader('Authorization', '')
            ->get(route('clerk.refresh', ['redirect' => '/cart']))
            ->assertOk();
    }

    public function test_an_authenticated_visitor_goes_straight_to_the_bridge_destination(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user, 'clerk')
            ->get(route('clerk.refresh', ['redirect' => '/cart']))
            ->assertRedirect('/cart');
    }

    public function test_an_authenticated_buyer_is_not_sent_to_the_admin_only_dashboard(): void
    {
        $buyer = User::factory()->create();

        $this->actingAs($buyer, 'clerk')
            ->get(route('login'))
            ->assertRedirect(route('catalog.index'));
    }

    public function test_an_authenticated_seller_is_not_sent_to_the_admin_only_dashboard(): void
    {
        $seller = User::factory()->seller()->create();

        $this->actingAs($seller, 'clerk')
            ->get(route('login'))
            ->assertRedirect(route('seller-application.create'));
    }

    public function test_an_authenticated_administrator_still_reaches_the_dashboard(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin, 'clerk')
            ->get(route('login'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_the_session_cookie_is_written_for_clerk_to_read_and_not_beyond_the_token_window(): void
    {
        config(['services.clerk.session_cookie_lifetime_minutes' => 1]);

        $user = User::factory()->create(['clerk_user_id' => 'user_cookie', 'email_verified_at' => now()]);
        $this->fakeVerifiedClerkSession('user_cookie');

        $response = $this->withHeader('Authorization', 'Bearer valid-session-token')
            ->postJson(route('clerk.handoff.complete'), ['redirect' => '/cart'])
            ->assertOk();

        $cookie = collect($response->headers->getCookies())
            ->firstWhere(fn ($cookie) => $cookie->getName() === '__session');

        $this->assertNotNull($cookie, 'The handoff must hand the browser a backend-readable session cookie.');
        $this->assertSame('valid-session-token', $cookie->getValue());
        $this->assertFalse($cookie->isHttpOnly(), 'Clerk must be able to read the cookie.');
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertSame(
            now()->addMinutes(1)->timestamp,
            $cookie->getExpiresTime(),
            'The cookie must not outlive the 60 second session token it carries.',
        );
    }

    /**
     * Navigation renews the cookie through its own endpoint, which exists so that reopening a
     * page never replays a sign-in that a previous, abandoned attempt left behind.
     */
    public function test_navigation_refreshes_the_cookie_without_replaying_a_deferred_cart_addition(): void
    {
        $user = User::factory()->create(['clerk_user_id' => 'user_navigate', 'email_verified_at' => now()]);
        $this->fakeVerifiedClerkSession('user_navigate');

        [, $product] = $this->sellerProduct();

        // An earlier guest attempt parked an item here and never finished signing in.
        $this->withSession(['pending_cart_addition' => [
            'product_id' => $product->id,
            'expires_at' => now()->addMinutes(15)->timestamp,
        ]]);

        $response = $this->withHeader('Authorization', 'Bearer valid-session-token')
            ->postJson(route('clerk.session.refresh'))
            ->assertOk()
            ->assertJson(['refreshed' => true])
            ->assertSessionHas('pending_cart_addition');

        $cookie = collect($response->headers->getCookies())
            ->firstWhere(fn ($cookie) => $cookie->getName() === '__session');

        $this->assertNotNull($cookie, 'Refreshing a session must hand the browser a new session cookie.');
        $this->assertSame('valid-session-token', $cookie->getValue());
        $this->assertSame(
            now()->addMinutes(1)->timestamp,
            $cookie->getExpiresTime(),
            'The refreshed cookie must not outlive the token it carries.',
        );

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_the_navigation_refresh_endpoint_requires_a_clerk_session(): void
    {
        $verifier = Mockery::mock(ClerkSessionVerifier::class);
        $verifier->shouldReceive('verify')->once()->andReturn(null);
        $this->app->instance(ClerkSessionVerifier::class, $verifier);

        $this->postJson(route('clerk.session.refresh'))->assertUnauthorized();
    }

    public function test_only_signed_in_pages_expose_the_navigation_refresh_endpoint(): void
    {
        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertDontSee('clerk-session-refresh-endpoint', false);

        $this->actingAs(User::factory()->create(), 'clerk')
            ->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('clerk-session-refresh-endpoint', false)
            ->assertSee(route('clerk.session.refresh'), false);
    }

    public function test_a_bridge_attempt_stops_counting_once_it_is_stale(): void
    {
        $this->get(route('clerk.refresh', ['redirect' => '/cart']))->assertOk();

        // A bridge the browser never finished must not send the next attempt to sign-in.
        $this->travel(121)->seconds();

        $this->get(route('clerk.refresh', ['redirect' => '/cart']))->assertOk();
    }

    private function fakeVerifiedClerkSession(string $clerkUserId): void
    {
        $verifier = Mockery::mock(ClerkSessionVerifier::class);
        $verifier->shouldReceive('verify')
            ->zeroOrMoreTimes()
            ->andReturnUsing(fn (Request $request): ?ClerkIdentity => $request->bearerToken() === 'valid-session-token'
                || $request->cookie('__session') === 'valid-session-token'
                    ? new ClerkIdentity($clerkUserId)
                    : null);

        $this->app->instance(ClerkSessionVerifier::class, $verifier);
    }

    /**
     * @return array{0: User, 1: Product}
     */
    private function sellerProduct(): array
    {
        $seller = User::factory()->seller()->create();
        $suffix = str()->lower(str()->random(6));
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
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $seller->id, 'role' => 'owner']);

        return [$seller, Product::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Canvas Tote',
            'slug' => "canvas-tote-{$suffix}",
            'sku' => "TOTE-{$suffix}",
            'price' => '19.99',
            'stock_quantity' => 5,
            'status' => 'active',
        ])];
    }
}
