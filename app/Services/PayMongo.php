<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The few PayMongo calls JMMart makes. Amounts come in as two-decimal peso strings and leave as
 * integer centavos, which is what PayMongo expects.
 */
class PayMongo
{
    /**
     * @param  array<string, mixed>  $attributes  checkout session attributes, including `split_payment`
     * @return array{id: string, checkout_url: string}
     */
    public function createCheckoutSession(array $attributes): array
    {
        $session = $this->client()->post('/v1/checkout_sessions', ['data' => ['attributes' => $attributes]])->throw()->json('data');

        if (! is_array($session) || ! is_string($session['id'] ?? null) || $session['id'] === ''
            || ! is_string($session['attributes']['checkout_url'] ?? null)
            || filter_var($session['attributes']['checkout_url'], FILTER_VALIDATE_URL) === false
            || parse_url($session['attributes']['checkout_url'], PHP_URL_SCHEME) !== 'https') {
            throw new RuntimeException('The payment service returned an invalid checkout session.');
        }

        return ['id' => $session['id'], 'checkout_url' => $session['attributes']['checkout_url']];
    }

    public function expireCheckoutSession(string $sessionId): void
    {
        try {
            $this->client()->post("/v1/checkout_sessions/{$sessionId}/expire")->throw();
        } catch (RequestException $exception) {
            if ($exception->response->status() === 400
                && $this->client()->get("/v1/checkout_sessions/{$sessionId}")->throw()->json('data.attributes.status') === 'expired') {
                return;
            }

            throw $exception;
        }
    }

    /**
     * Refunds part of a split payment, taking each amount back from the account that received it.
     *
     * @param  array<string, string>  $sources  merchant ID => peso amount it gives back
     */
    public function refund(string $paymentId, string $amount, array $sources, ?string $idempotencyKey = null): string
    {
        $client = $this->client();

        if ($idempotencyKey !== null) {
            $client = $client->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        $refundId = $client->post('/v1/refunds', ['data' => ['attributes' => [
            'amount' => self::centavos($amount),
            'payment_id' => $paymentId,
            'reason' => 'requested_by_customer',
            'split_refund' => ['refund_sources' => collect($sources)
                ->map(fn (string $share, string $merchantId): array => ['merchant_id' => $merchantId, 'split_type' => 'fixed', 'value' => self::centavos($share)])
                ->values()->all()],
        ]]])->throw()->json('data.id');

        if (! is_string($refundId) || $refundId === '') {
            throw new RuntimeException('The payment service returned an invalid refund.');
        }

        return $refundId;
    }

    public function createChildAccount(): string
    {
        return $this->client()->post('/v2/accounts', ['type' => 'merchant'])->throw()->json('data.id');
    }

    /** The hosted page where the store owner captures their ID and selfie; it expires after 72 hours. */
    public function identityVerificationUrl(string $accountId): string
    {
        return $this->client()->post("/v2/accounts/{$accountId}/identity_verification")->throw()->json('data.url');
    }

    /** One of pending, under_review, activated, or declined. */
    public function accountStatus(string $accountId): string
    {
        return $this->client()->get("/v2/accounts/{$accountId}")->throw()->json('data.activation_status');
    }

    /**
     * PayMongo signs `timestamp.raw_body` with the endpoint's secret and sends it as
     * `Paymongo-Signature: t=…,te=…,li=…` (te for test events, li for live ones).
     */
    public function hasValidSignature(?string $header, string $body, bool $livemode): bool
    {
        $secret = config('services.paymongo.webhook_secret');

        if (blank($secret) || blank($header)) {
            return false;
        }

        $parts = collect(explode(',', $header))->mapWithKeys(function (string $part): array {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            return [$key => $value];
        });
        $signature = $parts->get($livemode ? 'li' : 'te');

        return filled($parts->get('t')) && filled($signature)
            && hash_equals(hash_hmac('sha256', $parts->get('t').'.'.$body, $secret), $signature);
    }

    public static function centavos(string $amount): int
    {
        return (int) bcmul($amount, '100', 0);
    }

    private function client(): PendingRequest
    {
        $secret = config('services.paymongo.secret_key') ?: throw new RuntimeException('PayMongo is not configured: set PAYMONGO_SECRET_KEY.');

        return Http::baseUrl(config('services.paymongo.base_url'))->withBasicAuth($secret, '')->acceptJson()->asJson()->connectTimeout(3)->timeout(15);
    }
}
