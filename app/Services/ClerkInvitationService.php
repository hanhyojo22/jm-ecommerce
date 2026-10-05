<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ClerkInvitationService
{
    public function inviteAdministrator(string $email): void
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
            ->post('/invitations', ['email_address' => $email, 'redirect_url' => url('/sign-in')])
            ->throw();
    }
}
