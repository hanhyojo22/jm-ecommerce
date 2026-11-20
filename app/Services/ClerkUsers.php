<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ClerkUsers
{
    /**
     * Removes the sign-in itself, so a deleted account cannot sign back in to it.
     */
    public function delete(string $clerkUserId): void
    {
        $secretKey = config('services.clerk.secret_key');

        if (! is_string($secretKey) || $secretKey === '') {
            throw new RuntimeException('Clerk is not configured.');
        }

        Http::baseUrl('https://api.clerk.com/v1')
            ->connectTimeout(3)
            ->timeout(5)
            ->withToken($secretKey)
            ->acceptJson()
            ->delete('/users/'.rawurlencode($clerkUserId))
            ->throw();
    }
}
