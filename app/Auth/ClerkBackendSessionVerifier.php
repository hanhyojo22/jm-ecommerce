<?php

namespace App\Auth;

use Clerk\Backend\Helpers\Jwks\AuthenticateRequest;
use Clerk\Backend\Helpers\Jwks\AuthenticateRequestOptions;
use Clerk\Backend\Helpers\Jwks\RequestState;
use Clerk\Backend\Helpers\Jwks\TokenVerificationErrorReason;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ClerkBackendSessionVerifier implements ClerkSessionVerifier
{
    public function __construct(private ClerkJwtKeyResolver $jwtKeys) {}

    public function verify(Request $request): ?ClerkIdentity
    {
        $secretKey = $this->secretKey();
        $sessionToken = $this->sessionToken($request);

        if ($secretKey === null || $sessionToken === null) {
            return null;
        }

        $jwtKey = $this->jwtKeys->forToken($sessionToken);
        $state = $jwtKey === null ? null : $this->authenticate($request, $secretKey, $jwtKey);

        if ($state === null || (! $state->isAuthenticated() && $this->signingKeyMayBeStale($state))) {
            // A cached key set goes stale the moment Clerk rotates its signing keys: a token can
            // name a key we do not hold, or carry a signature our key cannot confirm. Either is
            // worth one bounded re-read of the key set — never a wait on Clerk of unknown length.
            $refreshedKey = $this->jwtKeys->refreshForToken($sessionToken);

            if ($refreshedKey !== null && $refreshedKey !== $jwtKey) {
                $state = $this->authenticate($request, $secretKey, $refreshedKey);
            }
        }

        return $state === null ? null : $this->identity($state);
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

    /**
     * Verification is local: the signing key is resolved before the SDK is handed the request,
     * so the SDK never reaches out to Clerk through a client with no timeout of its own. A
     * failure here has to read as "no session" rather than as a broken page, so it is answered
     * with the same sign-in bridge as any other refusal.
     */
    private function authenticate(Request $request, string $secretKey, ?string $jwtKey): ?RequestState
    {
        try {
            return AuthenticateRequest::authenticateRequest($request, new AuthenticateRequestOptions(
                secretKey: $secretKey,
                jwtKey: $jwtKey,
                audiences: config('services.clerk.audiences') ?: null,
                authorizedParties: config('services.clerk.authorized_parties'),
                acceptsToken: ['session_token'],
            ));
        } catch (Throwable $exception) {
            // Reported rather than swallowed: an invalid or expired token is answered by the SDK
            // with a refusal, not an exception, so anything landing here is a fault of ours —
            // and an unreported one looks exactly like a visitor who is simply not signed in.
            report($exception);

            return null;
        }
    }

    private function identity(RequestState $state): ?ClerkIdentity
    {
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

    /**
     * An expired, unsigned or otherwise unreadable token says nothing about the cached key set,
     * so only a signature the key itself was meant to confirm is worth a second lookup.
     */
    private function signingKeyMayBeStale(RequestState $state): bool
    {
        $reason = $state->getErrorReason();

        if ($reason === null) {
            return false;
        }

        return in_array($reason->getId(), [
            TokenVerificationErrorReason::$TOKEN_INVALID_SIGNATURE->getId(),
            TokenVerificationErrorReason::$JWK_LOCAL_INVALID->getId(),
        ], true);
    }

    private function secretKey(): ?string
    {
        $secretKey = config('services.clerk.secret_key');

        return is_string($secretKey) && $secretKey !== '' ? $secretKey : null;
    }

    /**
     * The same token the SDK picks up: the Authorization header when the request carries one,
     * otherwise the first __session cookie in the raw Cookie header — taken whatever its value,
     * exactly as the SDK takes it, so the key resolved here belongs to the token it verifies.
     */
    private function sessionToken(Request $request): ?string
    {
        $authorization = $request->headers->get('Authorization');

        if (is_string($authorization) && $authorization !== '') {
            return str_replace('Bearer ', '', $authorization);
        }

        $cookieHeader = $request->headers->get('Cookie');

        if (! is_string($cookieHeader) || $cookieHeader === '') {
            return null;
        }

        foreach (array_map('trim', explode(';', $cookieHeader)) as $cookie) {
            [$name, $value] = explode('=', $cookie, 2) + ['', ''];

            if (str_starts_with($name, '__session')) {
                return $value;
            }
        }

        return null;
    }
}
