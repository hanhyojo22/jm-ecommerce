<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClerkEmailIsVerified
{
    /**
     * Require the email verification state synchronized from Clerk.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        if ($request->user()?->hasVerifiedClerkEmail()) {
            return $next($request);
        }

        return to_route('profile.edit')->with('status', 'Verify your email in Clerk to continue.');
    }
}
