<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class ApiError
{
    /**
     * The wait a rate limit sets (its Retry-After seconds) in words, e.g. "Try again in 52 minutes."
     */
    public static function retryAfterMessage(mixed $seconds): string
    {
        $minutes = (int) ceil(((int) $seconds) / 60);

        return $minutes > 0 ? "Try again in {$minutes} ".Str::plural('minute', $minutes).'.' : 'Try again in a moment.';
    }

    /**
     * Render one API failure in the shared shape: a stable code, a readable message,
     * optional field errors, and the request id for support.
     *
     * @param  array<string, array<int, string>|string>  $fields
     */
    public static function response(string $code, string $message, int $status, array $fields = []): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'fields' => (object) $fields,
            ],
            'request_id' => request()->attributes->get('request_id'),
        ], $status);
    }
}
