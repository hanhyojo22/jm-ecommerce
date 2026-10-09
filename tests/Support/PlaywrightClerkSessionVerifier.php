<?php

namespace Tests\Support;

use App\Auth\ClerkIdentity;
use App\Auth\ClerkSessionVerifier;
use Illuminate\Http\Request;
use LogicException;

class PlaywrightClerkSessionVerifier implements ClerkSessionVerifier
{
    private const IDENTITIES = [
        'qa-customer-identity',
        'qa-seller-identity',
        'qa-other-seller-identity',
        'qa-admin-identity',
    ];

    public function __construct(private readonly string $authKey) {}

    public function verify(Request $request): ?ClerkIdentity
    {
        $requestKey = $request->header('X-Playwright-Auth-Key');
        $identity = $request->header('X-Playwright-Identity');

        if (is_string($requestKey) && hash_equals($this->authKey, $requestKey) && in_array($identity, self::IDENTITIES, true)) {
            return new ClerkIdentity($identity);
        }

        $sessionToken = $request->bearerToken() ?? $request->cookie('__session');

        foreach (self::IDENTITIES as $allowedIdentity) {
            if ($sessionToken === 'playwright-session-'.$allowedIdentity) {
                return new ClerkIdentity($allowedIdentity);
            }
        }

        return null;
    }

    public function fetchUser(string $clerkUserId): ClerkIdentity
    {
        throw new LogicException('Playwright test identities must already exist in the isolated test database.');
    }
}
