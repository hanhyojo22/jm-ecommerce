<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountType
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$accountTypes): Response
    {
        $user = $request->user();

        abort_unless($user !== null && $user->is_active && in_array($user->account_type, $accountTypes, true), 403);

        return $next($request);
    }
}
