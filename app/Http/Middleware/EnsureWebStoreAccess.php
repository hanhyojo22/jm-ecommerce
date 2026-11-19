<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWebStoreAccess
{
    /**
     * Keep the buyer web app closed when the web store is disabled.
     *
     * With no parameter every visitor is blocked. With "buyer", only buyer accounts
     * are blocked, so shared pages such as Seller Center chat and the account page
     * keep working for sellers and administrators.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $blockedAudience = null): Response
    {
        if (config('marketplace.web_store.enabled')) {
            return $next($request);
        }

        if ($blockedAudience === null || $request->user()?->account_type === 'buyer') {
            abort(410);
        }

        return $next($request);
    }
}
