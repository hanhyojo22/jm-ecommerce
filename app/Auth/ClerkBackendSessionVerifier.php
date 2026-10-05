<?php

namespace App\Auth;

use Clerk\Backend\Helpers\Jwks\AuthenticateRequest;
use Clerk\Backend\Helpers\Jwks\AuthenticateRequestOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ClerkBackendSessionVerifier implements ClerkSessionVerifier
{
    public function verify(Request $request): ?ClerkIdentity
    {
        $secretKey = config('services.clerk.secret_key');

        if (! is_string($secretKey) || $secretKey === '') {
            return null;
        }

        $state = AuthenticateRequest::authenticateRequest($request, new AuthenticateRequestOptions(
            secretKey: $secretKey,
            audiences: config('services.clerk.audiences') ?: null,
            authorizedParties: config('services.clerk.authorized_parties'),
            acceptsToken: ['session_token'],
        ));

        if (! $state->isAuthenticated()) {
            return null;
        }

        $payload = $state->getPayload();
        $clerkUserId = is_object($payload) ? ($payload->sub ?? null) : null;

        if (! is_string($clerkUserId) || $clerkUserId === '') {
            return null;
        }

        return new ClerkIdentity($clerkUserId);
    }

    public function fetchUser(string $clerkUserId): ClerkIdentity
    {
        $response = Http::baseUrl('https://api.clerk.com/v1')
            ->connectTimeout(3)
            ->timeout(5)
            ->retry([100, 500])
            ->withToken(config('services.clerk.secret_key'))
            ->acceptJson()
            ->get('/users/'.$clerkUserId)
            ->throw()
            ->json();

        if (! is_array($response)) {
            throw new RuntimeException('Clerk did not return the authenticated user.');
        }

        $email = null;
        $verified = false;

        foreach ($response['email_addresses'] ?? [] as $emailAddress) {
            if (($emailAddress['id'] ?? null) === ($response['primary_email_address_id'] ?? null)) {
                $email = $emailAddress['email_address'] ?? null;
                $verified = ($emailAddress['verification']['status'] ?? null) === 'verified';
                break;
            }
        }

        $name = trim(implode(' ', array_filter([$response['first_name'] ?? null, $response['last_name'] ?? null])));

        return new ClerkIdentity(
            id: $clerkUserId,
            email: $email,
            emailVerified: $verified,
            name: $name !== '' ? $name : null,
        );
    }
}
