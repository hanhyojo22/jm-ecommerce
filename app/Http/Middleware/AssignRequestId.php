<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    /**
     * Give every request a correlation id that is echoed in the body and the header.
     *
     * A client-supplied id is accepted only when it is a safe, bounded token, so it
     * cannot be used to inject log content.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id');

        if (! is_string($requestId) || ! preg_match('/^[A-Za-z0-9._-]{8,100}$/', $requestId)) {
            $requestId = (string) Str::ulid();
        }

        $request->attributes->set('request_id', $requestId);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
