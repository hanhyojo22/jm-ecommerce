<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdministrator
{
    /**
     * Restrict the request to users with an active platform administrator role.
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isPlatformAdministrator() ?? false, 403);

        return $next($request);
    }
}
