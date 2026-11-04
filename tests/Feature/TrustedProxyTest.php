<?php

namespace Tests\Feature;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    public function test_forwarded_for_header_is_trusted_behind_the_platform_proxy(): void
    {
        Route::middleware('web')->get('/proxy-ip-audit', fn (Request $request) => $request->ip());

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->withHeader('X-Forwarded-For', '203.0.113.20')
            ->get('/proxy-ip-audit')
            ->assertOk()
            ->assertSee('203.0.113.20');
    }

    public function test_forwarded_for_header_is_ignored_outside_the_trusted_proxy_range(): void
    {
        config(['app.trusted_proxies' => '10.0.0.0/8']);
        TrustProxies::at('10.0.0.0/8');

        Route::middleware('web')->get('/proxy-ip-audit', fn (Request $request) => $request->ip());

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->withHeader('X-Forwarded-For', '203.0.113.20')
            ->get('/proxy-ip-audit')
            ->assertOk()
            ->assertSee('198.51.100.10');
    }
}
