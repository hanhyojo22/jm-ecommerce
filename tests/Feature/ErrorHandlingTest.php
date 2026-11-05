<?php

namespace Tests\Feature;

use App\Auth\ClerkSessionVerifier;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    public function test_livewire_authentication_failure_is_json_without_redirecting_away_from_the_draft(): void
    {
        Route::middleware('auth')->post('/livewire-auth-test', fn () => response()->json(['ok' => true]));

        $this->withHeader('X-Livewire', '1')->post('/livewire-auth-test')
            ->assertStatus(401)->assertJsonPath('message', 'Sign in to continue.');
    }

    public function test_http_exception_details_are_hidden_from_web_json_even_in_debug_mode(): void
    {
        config(['app.debug' => true]);
        Route::get('/http-error-json-test', fn () => abort(503, 'private-provider-token'));

        $this->getJson('/http-error-json-test')->assertStatus(503)
            ->assertJsonPath('message', 'This service is temporarily unavailable. Please try again later.')
            ->assertDontSee('private-provider-token');
    }

    public function test_database_exception_reporting_omits_sql_bindings_and_previous_messages(): void
    {
        Log::spy();
        Route::get('/api/v1/database-error-test', fn () => throw new QueryException(
            'sqlite', 'insert into users (password) values (?)', ['private-token'], new \PDOException('private-connection-password'),
        ));

        $this->getJson('/api/v1/database-error-test')->assertStatus(500)->assertDontSee('private-token');
        Log::shouldHaveReceived('error')->once()->with('Application exception.', \Mockery::on(
            fn (array $context): bool => $context['exception_type'] === QueryException::class
                && ! str_contains(json_encode($context), 'private-token')
                && ! str_contains(json_encode($context), 'private-connection-password')
                && ! str_contains(json_encode($context), 'insert into users')
        ));
    }

    public function test_provider_connection_failure_has_a_safe_retryable_response(): void
    {
        Http::preventStrayRequests();
        Http::fake(['provider.test/*' => Http::failedConnection()]);
        Route::get('/api/v1/provider-test', fn () => Http::get('https://provider.test/status')->throw());

        $this->getJson('/api/v1/provider-test')->assertStatus(503)
            ->assertJsonPath('error.code', 'service.unavailable')
            ->assertDontSee('provider.test');
    }

    public function test_a_clerk_outage_returns_unavailable_instead_of_a_sign_in_loop(): void
    {
        $this->mock(ClerkSessionVerifier::class, function ($mock): void {
            $mock->shouldReceive('verify')->andReturnUsing(function (Request $request): null {
                $request->attributes->set('auth_service_unavailable', true);

                return null;
            });
        });

        $this->getJson('/api/v1/me')->assertStatus(503)->assertJsonPath('error.code', 'auth.unavailable');
    }

    public function test_http_failures_keep_the_api_contract_and_retry_headers(): void
    {
        Route::get('/api/v1/unavailable-test', fn () => abort(503, 'internal secret', ['Retry-After' => '30']));

        $this->getJson('/api/v1/unavailable-test')
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'server.unavailable')
            ->assertHeader('Retry-After', '30')
            ->assertHeader('X-Request-Id')
            ->assertDontSee('internal secret');
    }

    public function test_web_json_errors_never_expose_exception_details_even_with_debug_enabled(): void
    {
        config(['app.debug' => true]);
        Route::get('/error-json-test', fn () => throw new RuntimeException('password=private-token'));

        $this->getJson('/error-json-test')->assertStatus(500)
            ->assertJsonPath('message', 'Something went wrong. Please try again.')
            ->assertDontSee('private-token');
    }

    public function test_error_pages_do_not_need_authentication_database_or_the_asset_manifest(): void
    {
        config(['app.debug' => false]);
        Route::get('/error-page-test', fn () => throw new RuntimeException('private-token'));

        $this->get('/error-page-test')->assertStatus(500)
            ->assertSee('Check your orders before trying a purchase again.')
            ->assertSee('Reference')
            ->assertDontSee('private-token');
    }

    public function test_exception_reporting_keeps_a_reference_without_logging_secrets(): void
    {
        Log::shouldReceive('error')->once()->with('Application exception.', \Mockery::on(
            fn (array $context): bool => $context['exception_type'] === RuntimeException::class
                && $context['request_id'] === 'support-error-123'
                && ! str_contains(json_encode($context), 'private-token')
        ));
        Route::get('/safe-report-test', fn () => throw new RuntimeException('private-token'));

        $this->withHeader('X-Request-Id', 'support-error-123')
            ->getJson('/safe-report-test')->assertStatus(500);
    }
}
