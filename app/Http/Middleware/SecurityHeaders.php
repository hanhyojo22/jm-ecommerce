<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Apply the same baseline browser protections on every deployment target.
     *
     * Caddy sets these at the edge on the VPS. Render terminates TLS in front of
     * Apache, so the application has to supply them there.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Views read this through Vite::cspNonce(); @vite and Livewire stamp it on their own tags.
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        /*
         * Only a script tag carrying this request's nonce runs, and whatever such a script loads
         * itself (Clerk's UI, Vite's chunks) is trusted through 'strict-dynamic'. Injected markup
         * and inline handlers are refused. Alpine and Livewire evaluate expressions, hence
         * 'unsafe-eval'; https: only serves browsers too old for 'strict-dynamic'.
         */
        $headers = [
            'Content-Security-Policy' => "base-uri 'self'; frame-ancestors 'none'; object-src 'none'; script-src 'nonce-{$nonce}' 'strict-dynamic' 'unsafe-eval' https:; worker-src 'self' blob:",
            'Permissions-Policy' => 'geolocation=(self), microphone=(), camera=()',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
        ];

        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
