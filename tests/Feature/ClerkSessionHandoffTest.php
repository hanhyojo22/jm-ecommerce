<?php

namespace Tests\Feature;

use App\Auth\ClerkIdentity;
use App\Auth\ClerkSessionVerifier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ClerkSessionHandoffTest extends TestCase
{
    use RefreshDatabase;

    public function test_authentication_pages_redirect_to_the_server_verified_handoff(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('clerk.handoff'), false);

        $this->get(route('clerk.handoff'))
            ->assertOk()
            ->assertSee('data-redirect-url=""', false);

        $this->get(route('register', ['redirect' => '/start-selling']))
            ->assertOk()
            ->assertSee(route('clerk.handoff', ['redirect' => '/start-selling']), false);
    }

    public function test_handoff_page_restricts_redirect_destination_to_a_local_path(): void
    {
        $this->get(route('clerk.handoff', ['redirect' => '//evil.example']))
            ->assertOk()
            ->assertSee('data-redirect-url="/"', false);
    }

    public function test_handoff_page_explains_a_handoff_that_never_finishes(): void
    {
        /*
         * The spinner is only ever replaced by JavaScript, so the page has to carry the failure
         * state and a deadline of its own: a stalled bundle or an unanswered Clerk call used to
         * leave a buyer watching the spinner with nothing to act on.
         */
        $this->get(route('clerk.handoff'))
            ->assertOk()
            ->assertSeeInOrder(['data-clerk-handoff-error', 'hidden', 'role="alert"'], false)
            ->assertSee('data-clerk-handoff-retry', false)
            ->assertSee('window.clerkHandoffState', false)
            ->assertSee(route('login'), false);

        $this->get(route('clerk.refresh', ['redirect' => '/cart']))
            ->assertOk()
            ->assertSee('data-clerk-session-handoff', false)
            ->assertSeeInOrder(['data-clerk-handoff-error', 'hidden'], false)
            ->assertSee(route('login', ['redirect' => '/cart']), false);
    }

    public function test_authenticated_clerk_session_completes_handoff_to_validated_destination(): void
    {
        $user = User::factory()->create(['clerk_user_id' => 'user_handoff']);
        $verifier = \Mockery::mock(ClerkSessionVerifier::class);
        $verifier->shouldReceive('verify')
            ->twice()
            ->with(\Mockery::on(fn (Request $request): bool => $request->bearerToken() === 'valid-session-token'
                || $request->cookie('__session') === 'valid-session-token'))
            ->andReturn(new ClerkIdentity('user_handoff'));
        $this->app->instance(ClerkSessionVerifier::class, $verifier);

        $this->withHeader('Authorization', 'Bearer valid-session-token')
            ->postJson(route('clerk.handoff.complete'), ['redirect' => '/start-selling'])
            ->assertOk()
            ->assertJson(['redirect' => '/start-selling'])
            ->assertPlainCookie('__session', 'valid-session-token');

        Auth::forgetGuards();

        $this->withHeader('Authorization', '');
        $this->withUnencryptedCookie('__session', 'valid-session-token')
            ->get('/profile')
            ->assertOk();

        $this->assertAuthenticatedAs($user, 'clerk');
    }

    public function test_handoff_endpoint_requires_a_verified_clerk_session(): void
    {
        $verifier = \Mockery::mock(ClerkSessionVerifier::class);
        $verifier->shouldReceive('verify')->once()->andReturn(null);
        $this->app->instance(ClerkSessionVerifier::class, $verifier);

        $this->postJson(route('clerk.handoff.complete'), ['redirect' => '/'])
            ->assertUnauthorized();
    }

    public function test_handoff_endpoint_does_not_return_an_external_redirect(): void
    {
        $user = User::factory()->create(['clerk_user_id' => 'user_handoff']);

        $this->actingAs($user, 'clerk')
            ->postJson(route('clerk.handoff.complete'), ['redirect' => '//evil.example'])
            ->assertOk()
            ->assertJson(['redirect' => '/']);
    }
}
