<?php

namespace Tests\Feature;

use App\Auth\ClerkIdentity;
use App\Auth\ClerkSessionVerifier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ApiFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unauthenticated_api_request_gets_a_stable_json_error(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertHeader('X-Request-Id')
            ->assertJsonPath('error.code', 'auth.unauthenticated')
            ->assertJsonPath('error.fields', []);
    }

    public function test_an_api_request_without_a_json_accept_header_still_gets_json(): void
    {
        $this->get('/api/v1/me', ['Accept' => 'text/html'])
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('error.code', 'auth.unauthenticated');
    }

    public function test_a_bearer_identity_resolves_the_linked_local_buyer(): void
    {
        $user = User::factory()->create(['clerk_user_id' => 'user_api']);
        $this->app->instance(ClerkSessionVerifier::class, new ApiClerkVerifier(new ClerkIdentity('user_api')));

        $this->withHeader('Authorization', 'Bearer session-token')
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.account_type', 'buyer')
            ->assertJsonMissingPath('data.clerk_user_id')
            ->assertJsonMissingPath('data.platform_role');
    }

    public function test_an_unverified_email_gets_a_json_403_instead_of_a_redirect(): void
    {
        User::factory()->unverified()->create(['clerk_user_id' => 'user_unverified']);
        $this->app->instance(ClerkSessionVerifier::class, new ApiClerkVerifier(new ClerkIdentity('user_unverified')));

        $this->withHeader('Authorization', 'Bearer session-token')
            ->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'auth.email_unverified');
    }

    public function test_a_missing_api_route_uses_the_same_error_shape(): void
    {
        $this->getJson('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'resource.not_found')
            ->assertHeader('X-Request-Id');
    }

    public function test_a_safe_client_request_id_is_echoed_for_support(): void
    {
        $this->withHeader('X-Request-Id', 'support-case-12345')
            ->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertHeader('X-Request-Id', 'support-case-12345')
            ->assertJsonPath('request_id', 'support-case-12345');
    }

    public function test_an_unsafe_client_request_id_is_replaced(): void
    {
        $response = $this->withHeader('X-Request-Id', 'bad id with spaces')
            ->getJson('/api/v1/me')
            ->assertUnauthorized();

        $this->assertNotSame('bad id with spaces', $response->headers->get('X-Request-Id'));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9._-]{8,100}$/', $response->headers->get('X-Request-Id'));
    }
}

class ApiClerkVerifier implements ClerkSessionVerifier
{
    public function __construct(private ClerkIdentity $identity) {}

    public function verify(Request $request): ?ClerkIdentity
    {
        return $this->identity;
    }

    public function fetchUser(string $clerkUserId): ClerkIdentity
    {
        return $this->identity;
    }
}
