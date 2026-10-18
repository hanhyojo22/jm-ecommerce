<?php

namespace Tests\Feature;

use App\Auth\ClerkBackendSessionVerifier;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use phpseclib3\Crypt\RSA;
use Tests\TestCase;

/**
 * A request should not have to wait on Clerk's API before Laravel can answer it: the verifier
 * resolves the signing key from the cached key set and hands the SDK a local PEM. These tests
 * cover the decisions that follow from that — which requests are trusted, and which are refused
 * without asking Clerk anything at all.
 */
class ClerkBackendSessionVerifierTest extends TestCase
{
    private const KEY_ID = 'ins_verifier_test';

    private ?RSA\PrivateKey $privateKey = null;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.clerk.secret_key' => 'sk_test_verifier',
            'services.clerk.audiences' => [],
            'services.clerk.authorized_parties' => [],
        ]);
    }

    /**
     * The key set as Clerk serves it, for the tests that get as far as reading it.
     */
    private function fakeKeySet(): void
    {
        Http::fake(['api.clerk.com/v1/jwks' => Http::response(['keys' => [$this->keySetEntry()]])]);
    }

    public function test_a_bearer_token_is_verified_against_the_cached_key_set(): void
    {
        $this->fakeKeySet();

        $request = Request::create('/profile', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->sessionToken(),
        ]);

        $identity = app(ClerkBackendSessionVerifier::class)->verify($request);

        $this->assertNotNull($identity);
        $this->assertSame('user_verifier', $identity->id);

        // One key set fetch, none of the per-request Clerk lookups the token itself used to cost.
        Http::assertSentCount(1);
    }

    public function test_a_session_cookie_is_verified_against_the_cached_key_set(): void
    {
        $this->fakeKeySet();

        $request = Request::create('/profile', 'GET', [], [], [], [
            'HTTP_COOKIE' => '__session='.$this->sessionToken(),
        ]);

        $identity = app(ClerkBackendSessionVerifier::class)->verify($request);

        $this->assertNotNull($identity);
        $this->assertSame('user_verifier', $identity->id);
        Http::assertSentCount(1);
    }

    public function test_an_expired_token_is_refused_without_a_second_key_lookup(): void
    {
        $this->fakeKeySet();

        $request = Request::create('/profile', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->sessionToken(['exp' => time() - 30]),
        ]);

        $this->assertNull(app(ClerkBackendSessionVerifier::class)->verify($request));
        Http::assertSentCount(1);
    }

    public function test_a_rotated_signing_key_is_picked_up_by_re_reading_the_key_set(): void
    {
        /*
         * The key set is cached for ten minutes, so a key Clerk rotates in during that window is
         * one the cache does not carry. Verification re-reads the set once rather than refusing a
         * session that is valid, and never hands the request to the SDK's own untimed lookup.
         */
        $rotatedKey = RSA::createKey(2048);

        Http::fakeSequence('api.clerk.com/v1/jwks')
            ->push(['keys' => [$this->keySetEntry()]])
            ->push(['keys' => [$this->keySetEntry(), $this->keySetEntry($rotatedKey, 'ins_rotated')]]);

        $request = Request::create('/profile', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->sessionToken([], $rotatedKey, 'ins_rotated'),
        ]);

        $identity = app(ClerkBackendSessionVerifier::class)->verify($request);

        $this->assertNotNull($identity);
        $this->assertSame('user_verifier', $identity->id);
        Http::assertSentCount(2);
    }

    public function test_a_forged_signature_costs_one_key_set_re_read_however_often_it_is_sent(): void
    {
        /*
         * Anyone can send a token naming a real key id, so the re-read above has to be bounded:
         * a refused token must not buy a request to Clerk, let alone a wait of unknown length.
         */
        $this->fakeKeySet();

        $forged = $this->sessionToken([], RSA::createKey(2048));

        $request = Request::create('/profile', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$forged,
        ]);

        foreach (range(1, 3) as $ignored) {
            $this->assertNull(app(ClerkBackendSessionVerifier::class)->verify($request));
        }

        Http::assertSentCount(2);
    }

    public function test_a_request_without_a_session_token_is_refused_without_fetching_anything(): void
    {
        Http::fake();

        $this->assertNull(app(ClerkBackendSessionVerifier::class)->verify(Request::create('/profile', 'GET')));

        Http::assertNothingSent();
    }

    public function test_verification_is_skipped_when_clerk_is_not_configured(): void
    {
        config(['services.clerk.secret_key' => null]);
        Http::fake();

        $request = Request::create('/profile', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->sessionToken(),
        ]);

        $this->assertNull(app(ClerkBackendSessionVerifier::class)->verify($request));

        Http::assertNothingSent();
    }

    /**
     * @return array<string, mixed>
     */
    private function keySetEntry(?RSA\PrivateKey $key = null, string $keyId = self::KEY_ID): array
    {
        $publicKey = ($key ?? $this->privateKey())->getPublicKey()->toString('JWK');
        $entry = json_decode($publicKey, true)['keys'][0];

        return $entry + ['kid' => $keyId, 'alg' => 'RS256', 'use' => 'sig'];
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function sessionToken(array $claims = [], ?RSA\PrivateKey $key = null, string $keyId = self::KEY_ID): string
    {
        return JWT::encode($claims + [
            'sub' => 'user_verifier',
            'sid' => 'sess_verifier',
            'v' => 2,
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + 60,
        ], ($key ?? $this->privateKey())->toString('PKCS8'), 'RS256', $keyId);
    }

    private function privateKey(): RSA\PrivateKey
    {
        return $this->privateKey ??= RSA::createKey(2048);
    }
}
