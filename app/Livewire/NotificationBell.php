<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

class NotificationBell extends Component
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

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    /**
     * @param  array{endpoint?: string, keys?: array{p256dh?: string, auth?: string}}  $subscription
     */
    public function savePushSubscription(array $subscription): void
    {
        $validator = Validator::make($subscription, [
            'endpoint' => ['required', 'url'],
            'keys.p256dh' => ['required', 'string', 'min:20', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],
            'keys.auth' => ['required', 'string', 'min:10', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        $untrustedHost = ! $validator->fails() && ! $this->isTrustedPushEndpoint($subscription['endpoint']);

        if ($validator->fails() || $untrustedHost) {
            // This previously failed silently with no trace anywhere, which made a rejected
            // subscription indistinguishable from the JS simply never calling this method.
            Log::warning('Rejected a web push subscription.', [
                'user_id' => auth()->id(),
                'reason' => $untrustedHost ? 'untrusted_host' : $validator->errors()->keys(),
                'endpoint' => $subscription['endpoint'] ?? null,
            ]);

            return;
        }

        auth()->user()->updatePushSubscription(
            $subscription['endpoint'],
            $subscription['keys']['p256dh'],
            $subscription['keys']['auth'],
        );
    }

    /**
     * deletePushSubscription() is already scoped through the authenticated user's own
     * pushSubscriptions() relation, so an arbitrary endpoint can only ever delete a row this
     * user owns, never someone else's.
     */
    public function removePushSubscription(string $endpoint): void
    {
        auth()->user()->deletePushSubscription($endpoint);
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

    public function goTo(string $id): mixed
    {
        $notification = auth()->user()->notifications()->whereKey($id)->first();

        if ($notification === null) {
            return null;
        }

        $notification->markAsRead();

        return match ($notification->data['type'] ?? null) {
            'chat' => $this->redirect(route('messages.show', $notification->data['conversation_id'])),
            'order' => $this->redirect(route('orders.show', $notification->data['order_id'])),
            'seller_order' => $this->redirect(route('seller.orders.show', $notification->data['seller_order_id'])),
            default => null,
        };
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.notification-bell', [
            'unreadCount' => $user->unreadNotifications()->count(),
            'notifications' => $user->notifications()->latest()->limit(10)->get(),
        ]);
    }
}
