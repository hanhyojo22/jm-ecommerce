<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class ApiError
{
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
