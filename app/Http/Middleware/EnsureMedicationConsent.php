<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Medication data is sensitive personal information (RA 10173): nothing is read or stored until
 * the buyer has opted in.
 */
class EnsureMedicationConsent
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->hasMedicationConsent(), 403, 'Turn on medicine reminders first.');

        return $next($request);
    }
}
