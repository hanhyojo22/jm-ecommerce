<?php

namespace Tests\Feature;

use App\Auth\ClerkJwtKeyResolver;
use Clerk\Backend\Helpers\Jwks\VerifyToken;
use Clerk\Backend\Helpers\Jwks\VerifyTokenOptions;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use phpseclib3\Crypt\RSA;
use Tests\TestCase;

/**
 * Clerk's PHP SDK fetches the instance key set from Clerk's API inside every request that
 * verifies a token, and its cache only lasts for that one request. The resolver caches the key
 * set locally instead, so these tests pin down the local lookup: the PEM it hands back has to be
 * one the SDK verifies with, and every uncertain case has to answer null so that the verifier
 * can fall back to the SDK's own lookup rather than refuse a session that is still valid.
 */
class ClerkJwtKeyResolverTest extends TestCase
{
    private const KEY_ID = 'ins_resolver_test';

    private ?RSA\PrivateKey $privateKey = null;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.clerk.secret_key' => 'sk_test_resolver',
            'services.clerk.audiences' => [],
            'services.clerk.authorized_parties' => [],
        ]);
    }

    public function test_it_resolves_a_pem_that_clerk_verifies_a_session_token_with(): void
    {
        Http::fake(['api.clerk.com/v1/jwks' => Http::response(['keys' => [$this->keySetEntry()]])]);

        $token = $this->sessionToken();
        $pem = app(ClerkJwtKeyResolver::class)->forToken($token);

        $this->assertIsString($pem);
        $this->assertNotFalse(openssl_pkey_get_public($pem));

        $claims = VerifyToken::verifyToken($token, new VerifyTokenOptions(jwtKey: $pem));

        $this->assertSame('user_resolver', $claims->sub);
    }

    public function test_it_serves_the_key_set_from_the_cache_between_requests(): void
    {
        Http::fake(['api.clerk.com/v1/jwks' => Http::response(['keys' => [$this->keySetEntry()]])]);

        $resolver = app(ClerkJwtKeyResolver::class);
        $token = $this->sessionToken();

        $this->assertNotNull($resolver->forToken($token));
        $this->assertSame($resolver->forToken($token), $resolver->forToken($token));

        Http::assertSentCount(1);
    }

    public function test_it_declines_a_key_id_the_cached_key_set_does_not_carry(): void
    {
        Http::fake(['api.clerk.com/v1/jwks' => Http::response(['keys' => [$this->keySetEntry('ins_other')]])]);

        $this->assertNull(app(ClerkJwtKeyResolver::class)->forToken($this->sessionToken()));
    }

    public function test_it_declines_a_key_set_it_cannot_download_without_caching_the_failure(): void
    {
        Http::fake(['api.clerk.com/v1/jwks' => Http::response('', 500)]);

        $resolver = app(ClerkJwtKeyResolver::class);
        $token = $this->sessionToken();

        $this->assertNull($resolver->forToken($token));
        $this->assertNull($resolver->forToken($token));

        Http::assertSentCount(2);
    }

    public function test_it_declines_a_token_that_carries_no_key_id(): void
    {
        Http::fake();

        $withoutKeyId = JWT::encode(['sub' => 'user_resolver'], $this->privateKey()->toString('PKCS8'), 'RS256');

        $this->assertNull(app(ClerkJwtKeyResolver::class)->forToken($withoutKeyId));
        $this->assertNull(app(ClerkJwtKeyResolver::class)->forToken('not-a-token'));

        Http::assertNothingSent();
    }

    /**
     * @return array<string, mixed>
     */
    private function keySetEntry(string $keyId = self::KEY_ID): array
    {
        $entry = json_decode($this->privateKey()->getPublicKey()->toString('JWK'), true)['keys'][0];

        return $entry + ['kid' => $keyId, 'alg' => 'RS256', 'use' => 'sig'];
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function sessionToken(array $claims = []): string
    {
        return JWT::encode($claims + [
            'sub' => 'user_resolver',
            'sid' => 'sess_resolver',
            'v' => 2,
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + 60,
        ], $this->privateKey()->toString('PKCS8'), 'RS256', self::KEY_ID);
    }

    private function privateKey(): RSA\PrivateKey
    {
        return $this->privateKey ??= RSA::createKey(2048);
    }
}
