<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_baseline_security_headers_are_applied(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
        $response->assertHeader('Content-Security-Policy', "base-uri 'self'; frame-ancestors 'none'; object-src 'none'");
        $response->assertHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_is_added_for_https_requests(): void
    {
        $this->get('https://localhost/up')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }
}
