<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    public function test_untrusted_forwarded_for_header_does_not_replace_client_ip(): void
    {
        Route::middleware('web')->get('/proxy-ip-audit', fn (Request $request) => $request->ip());

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->withHeader('X-Forwarded-For', '203.0.113.20')
            ->get('/proxy-ip-audit')
            ->assertOk()
            ->assertSee('198.51.100.10');
    }
}
