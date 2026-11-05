<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SafeExceptionReporter
{
    public function report(Throwable $exception, ?Request $request = null): void
    {
        Log::error('Application exception.', [
            'request_id' => $request?->attributes->get('request_id'),
            'route' => $request?->route()?->getName(),
            'method' => $request?->method(),
            'exception_type' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => array_map(
                static fn (array $frame): array => array_intersect_key($frame, array_flip(['file', 'line', 'class', 'function'])),
                array_slice($exception->getTrace(), 0, 30),
            ),
        ]);
    }
}
