<?php

namespace Tests\Feature;

use App\Auth\ClerkIdentity;
use App\Auth\ClerkSessionVerifier;
use App\Auth\ClerkUserResolver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClerkUserResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_clerk_session_resolves_its_linked_local_user(): void
    {
        $user = User::factory()->create(['clerk_user_id' => 'user_linked']);
        $resolver = new ClerkUserResolver(new FakeClerkSessionVerifier(new ClerkIdentity('user_linked')));

        $this->assertTrue($resolver->resolve(Request::create('/'))?->is($user));
    }

    public function test_the_clerk_guard_makes_the_local_user_available_to_protected_routes(): void
    {
        $user = User::factory()->create(['clerk_user_id' => 'user_linked']);
        $this->app->instance(ClerkSessionVerifier::class, new FakeClerkSessionVerifier(new ClerkIdentity('user_linked')));

        $this->get('/profile')
            ->assertSee('data-clerk-component="user-profile"', false)
            ->assertDontSee('aria-label="Breadcrumb"', false)
            ->assertSee('aria-busy="true"', false)
            ->assertDontSee('id="account-heading"', false)
            ->assertDontSee('Sign-in and security')
            ->assertDontSee('Account details');

        $this->assertAuthenticatedAs($user, 'clerk');
    }

    public function test_a_verified_email_links_exactly_one_existing_local_user(): void
    {
        $user = User::factory()->create(['email' => 'buyer@example.com', 'clerk_user_id' => null]);
        $resolver = new ClerkUserResolver(new FakeClerkSessionVerifier(
            new ClerkIdentity('user_new'),
            new ClerkIdentity('user_new', 'buyer@example.com', true, 'Buyer Example'),
        ));

        $this->assertTrue($resolver->resolve(Request::create('/'))?->is($user));
        $this->assertSame('user_new', $user->refresh()->clerk_user_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.clerk_linked',
            'entity_type' => User::class,
            'entity_id' => $user->id,
        ]);
    }

    public function test_verified_email_matching_is_case_insensitive(): void
    {
        $user = User::factory()->create(['email' => 'buyer@example.com', 'clerk_user_id' => null]);
        $resolver = new ClerkUserResolver(new FakeClerkSessionVerifier(
            new ClerkIdentity('user_new'),
            new ClerkIdentity('user_new', 'Buyer@Example.com', true, 'Buyer Example'),
        ));

        $this->assertTrue($resolver->resolve(Request::create('/'))?->is($user));
        $this->assertSame('user_new', $user->refresh()->clerk_user_id);
    }

    public function test_an_administrator_without_an_invitation_cannot_be_linked_by_email(): void
    {
        $administrator = User::factory()->administrator()->create([
            'email' => 'admin@example.com',
            'clerk_user_id' => null,
            'admin_invited_at' => null,
        ]);
        $resolver = new ClerkUserResolver(new FakeClerkSessionVerifier(
            new ClerkIdentity('user_attacker'),
            new ClerkIdentity('user_attacker', 'admin@example.com', true, 'Attacker'),
        ));

        $this->assertNull($resolver->resolve(Request::create('/')));
        $this->assertNull($administrator->refresh()->clerk_user_id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.clerk_link_blocked',
            'entity_type' => User::class,
            'entity_id' => $administrator->id,
        ]);
    }

    public function test_an_invited_administrator_is_linked_and_the_link_is_audited(): void
    {
        $administrator = User::factory()->administrator()->create([
            'email' => 'admin@example.com',
            'clerk_user_id' => null,
            'admin_invited_at' => now(),
        ]);
        $resolver = new ClerkUserResolver(new FakeClerkSessionVerifier(
            new ClerkIdentity('user_admin'),
            new ClerkIdentity('user_admin', 'ADMIN@example.com', true, 'Admin'),
        ));

        $this->assertTrue($resolver->resolve(Request::create('/'))?->is($administrator));
        $this->assertSame('user_admin', $administrator->refresh()->clerk_user_id);
        $this->assertNull($administrator->admin_invited_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.clerk_linked',
            'entity_type' => User::class,
            'entity_id' => $administrator->id,
        ]);
    }

    public function test_an_inactive_linked_user_is_not_resolved(): void
    {
        User::factory()->create(['clerk_user_id' => 'user_linked', 'is_active' => false]);
        $resolver = new ClerkUserResolver(new FakeClerkSessionVerifier(new ClerkIdentity('user_linked')));

        $this->assertNull($resolver->resolve(Request::create('/')));
    }

    public function test_an_inactive_local_user_is_not_linked_by_email(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'clerk_user_id' => null,
            'is_active' => false,
        ]);
        $resolver = new ClerkUserResolver(new FakeClerkSessionVerifier(
            new ClerkIdentity('user_inactive'),
            new ClerkIdentity('user_inactive', 'inactive@example.com', true, 'Inactive User'),
        ));

        $this->assertNull($resolver->resolve(Request::create('/')));
        $this->assertNull($user->refresh()->clerk_user_id);
    }

    public function test_an_unverified_clerk_email_cannot_link_or_create_a_marketplace_user(): void
    {
        $resolver = new ClerkUserResolver(new FakeClerkSessionVerifier(
            new ClerkIdentity('user_unverified'),
            new ClerkIdentity('user_unverified', 'buyer@example.com', false),
        ));

        $this->assertNull($resolver->resolve(Request::create('/')));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_a_verified_clerk_identity_without_a_local_match_creates_a_buyer_only(): void
    {
        $resolver = new ClerkUserResolver(new FakeClerkSessionVerifier(
            new ClerkIdentity('user_buyer'),
            new ClerkIdentity('user_buyer', 'new@example.com', true, 'New Buyer'),
        ));

        $user = $resolver->resolve(Request::create('/'));

        $this->assertNotNull($user);
        $this->assertSame('buyer', $user->account_type);
        $this->assertSame('user_buyer', $user->clerk_user_id);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_a_concurrent_first_sign_in_reuses_the_row_created_by_the_other_request(): void
    {
        $resolver = new ClerkUserResolver(new FakeClerkSessionVerifier(
            new ClerkIdentity('user_race'),
            new ClerkIdentity('user_race', 'race@example.com', true, 'Race Buyer'),
        ));

        $inserted = false;
        User::creating(function (User $user) use (&$inserted): void {
            if ($inserted || $user->clerk_user_id !== 'user_race') {
                return;
            }

            $inserted = true;

            DB::table('users')->insert([
                'id' => (string) Str::ulid(),
                'clerk_user_id' => 'user_race',
                'name' => 'Winning Request',
                'email' => 'race@example.com',
                'password' => 'not-a-real-password',
                'account_type' => 'buyer',
                'is_active' => true,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $user = $resolver->resolve(Request::create('/'));

        $this->assertNotNull($user);
        $this->assertSame('Winning Request', $user->name);
        $this->assertSame('user_race', $user->clerk_user_id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_an_unverified_local_user_is_sent_to_the_clerk_profile_instead_of_a_removed_breeze_route(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('cart.index'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'Verify your email in Clerk to continue.');
    }
}

class FakeClerkSessionVerifier implements ClerkSessionVerifier
{
    public function __construct(private ?ClerkIdentity $sessionIdentity, private ?ClerkIdentity $userIdentity = null) {}

    public function verify(Request $request): ?ClerkIdentity
    {
        return $this->sessionIdentity;
    }

    public function fetchUser(string $clerkUserId): ClerkIdentity
    {
        return $this->userIdentity ?? $this->sessionIdentity;
    }
}
