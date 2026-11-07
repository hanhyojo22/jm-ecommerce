<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SavePushSubscription
{
    /**
     * A small allowlist of the browser vendors' push services. The subscription's endpoint URL
     * is otherwise attacker-steerable (a tampered client could submit any URL), and this server
     * later POSTs to it on every notification — unvalidated, that is an SSRF vector.
     *
     * @var array<int, string>
     */
    private const TRUSTED_PUSH_HOSTS = [
        'fcm.googleapis.com',
        'updates.push.services.mozilla.com',
        'web.push.apple.com',
    ];

    /**
     * @param  array{endpoint?: mixed, keys?: array{p256dh?: mixed, auth?: mixed}}  $subscription
     */
    public function handle(User $user, array $subscription): bool
    {
        $validator = Validator::make($subscription, [
            'endpoint' => ['required', 'url', 'max:1024'],
            'keys.p256dh' => ['required', 'string', 'min:20', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],
            'keys.auth' => ['required', 'string', 'min:10', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        $endpoint = is_string($subscription['endpoint'] ?? null) ? $subscription['endpoint'] : null;
        $untrustedHost = ! $validator->fails() && ! $this->isTrustedPushEndpoint((string) $endpoint);

        if ($validator->fails() || $untrustedHost) {
            // A rejected subscription is otherwise indistinguishable from the JS never calling
            // this operation. Do not log the endpoint itself: it is a capability URL.
            Log::warning('Rejected a web push subscription.', [
                'user_id' => $user->getKey(),
                'reason' => $untrustedHost ? 'untrusted_host' : $validator->errors()->keys(),
                'endpoint_hash' => $endpoint === null ? null : hash('sha256', $endpoint),
            ]);

            return false;
        }

        try {
            $user->updatePushSubscription(
                (string) $endpoint,
                (string) $subscription['keys']['p256dh'],
                (string) $subscription['keys']['auth'],
            );
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }

        return true;
    }

    private function isTrustedPushEndpoint(string $endpoint): bool
    {
        $parts = parse_url($endpoint);
        $host = $parts['host'] ?? '';

        if (($parts['scheme'] ?? null) !== 'https' || $host === '') {
            return false;
        }

        return in_array($host, self::TRUSTED_PUSH_HOSTS, true) || str_ends_with($host, '.notify.windows.com');
    }
}
