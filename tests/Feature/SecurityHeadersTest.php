<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_baseline_security_headers_are_applied(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            "/^base-uri 'self'; frame-ancestors 'none'; object-src 'none'; script-src 'nonce-[A-Za-z0-9]{40}' 'strict-dynamic' 'unsafe-eval' https:; worker-src 'self' blob:$/",
            $response->headers->get('Content-Security-Policy'),
        );
        $response->assertHeader('Permissions-Policy', 'geolocation=(self), microphone=(), camera=()');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_a_pages_own_inline_script_carries_the_nonce_the_policy_allows(): void
    {
        $response = $this->get(route('clerk.handoff'))->assertOk();

        preg_match("/'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'), $matches);

        $response->assertSee('<script nonce="'.$matches[1].'">', false);
    }

    public function test_each_response_gets_its_own_nonce(): void
    {
        $this->assertNotSame(
            $this->get('/up')->headers->get('Content-Security-Policy'),
            $this->get('/up')->headers->get('Content-Security-Policy'),
        );
    }

    public function test_hsts_is_added_for_https_requests(): void
    {
        $this->get('https://localhost/up')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }
}
