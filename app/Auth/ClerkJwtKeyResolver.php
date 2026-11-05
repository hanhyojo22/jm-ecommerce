<?php

namespace App\Auth;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use phpseclib3\Crypt\RSA;
use phpseclib3\Math\BigInteger;
use Throwable;

/**
 * Resolves the key that signed a Clerk session token into a PEM public key.
 *
 * Clerk's PHP SDK fetches the instance key set from Clerk's Backend API on every request that
 * verifies a token, through a client with no timeout of its own, and its cache only lasts for
 * that one request. Resolving the key here keeps the lookup inside a timeout-configured client
 * that survives between requests, so an authenticated page does not wait on Clerk's API before
 * it can render, and no request can be made to wait on it indefinitely.
 *
 * The lookup answers null whenever a key cannot be resolved locally, which the verifier reads as
 * "no session" after one bounded re-read of the key set. Every path to Clerk's API runs through
 * this class, so every one of them is bounded.
 */
class ClerkJwtKeyResolver
{
    /**
     * Clerk rotates signing keys rarely and the SDK's own cache lasts five minutes.
     */
    private const CACHE_SECONDS = 600;

    /**
     * A token can name any key id at all, so refusing one must not cost a request to Clerk each
     * time. One re-read of the key set per window is enough to pick up a rotation promptly.
     */
    private const REFRESH_SECONDS = 60;

    /**
     * The PEM public key that verifies the given session token, or null when the cached key set
     * does not carry a key for the token's key id.
     */
    public function forToken(string $token): ?string
    {
        $keyId = $this->keyId($token);

        if ($keyId === null) {
            return null;
        }

        return $this->publicKeys()[$keyId] ?? null;
    }

    /**
     * The same lookup with the cached key set discarded first, so a key Clerk has rotated in is
     * picked up without waiting out the cache. Answers null, without asking Clerk anything, once
     * the window above has already been spent.
     */
    public function refreshForToken(string $token): ?string
    {
        if ($this->keyId($token) === null) {
            return null;
        }

        if (! Cache::add($this->cacheKey().'.refreshed', true, self::REFRESH_SECONDS)) {
            return null;
        }

        Cache::forget($this->cacheKey());

        return $this->forToken($token);
    }

    /**
     * The converted key set, keyed by key id, so a request costs one cache read.
     *
     * @return array<string, string>
     */
    private function publicKeys(): array
    {
        try {
            $publicKeys = Cache::remember(
                $this->cacheKey(),
                self::CACHE_SECONDS,
                fn (): array => $this->download(),
            );
        } catch (Throwable $exception) {
            if (app()->bound('request')) {
                request()->attributes->set('auth_service_unavailable', true);
            }
            // Clerk did not answer, in time or at all. Every session on the site is refused for
            // as long as that lasts, so it is reported rather than left to look like a site full
            // of signed-out visitors. The parse failures below are not: a token carrying
            // nonsense is any caller's to send, and reporting those would be a flood on demand.
            report($exception);

            return [];
        }

        return is_array($publicKeys) ? array_filter($publicKeys, 'is_string') : [];
    }

    /**
     * @return array<string, string>
     */
    private function download(): array
    {
        $keys = Http::baseUrl('https://api.clerk.com/v1')
            ->connectTimeout(3)
            ->timeout(5)
            ->withToken((string) config('services.clerk.secret_key'))
            ->acceptJson()
            ->get('/jwks')
            ->throw()
            ->json('keys');

        if (! is_array($keys)) {
            return [];
        }

        $publicKeys = [];

        foreach ($keys as $jwk) {
            if (! is_array($jwk)) {
                continue;
            }

            $keyId = $jwk['kid'] ?? null;
            $publicKey = $this->publicKey($jwk);

            if (is_string($keyId) && $keyId !== '' && $publicKey !== null) {
                $publicKeys[$keyId] = $publicKey;
            }
        }

        return $publicKeys;
    }

    /**
     * The key id Clerk signs with, read from the token's JOSE header. Nothing here is trusted:
     * the id only selects a key, and the signature still decides whether the token is genuine.
     */
    private function keyId(string $token): ?string
    {
        try {
            $header = json_decode(JWT::urlsafeB64Decode(explode('.', $token)[0] ?? ''), flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        $keyId = is_object($header) ? ($header->kid ?? null) : null;

        return is_string($keyId) && $keyId !== '' ? $keyId : null;
    }

    /**
     * @param  array<string, mixed>  $jwk
     */
    private function publicKey(array $jwk): ?string
    {
        $modulus = $jwk['n'] ?? null;
        $exponent = $jwk['e'] ?? null;

        if (! is_string($modulus) || ! is_string($exponent)) {
            return null;
        }

        try {
            return RSA::loadPublicKey([
                'n' => new BigInteger(JWT::urlsafeB64Decode($modulus), 256),
                'e' => new BigInteger(JWT::urlsafeB64Decode($exponent), 256),
            ])->toString('PKCS8');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Keyed by the secret key so a rotated key, or another instance, never shares a cached set.
     */
    private function cacheKey(): string
    {
        return 'clerk.jwt_keys.'.hash('sha256', (string) config('services.clerk.secret_key'));
    }
}
