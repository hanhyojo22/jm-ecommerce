<?php

namespace App\Livewire;

use App\Actions\SavePushSubscription;
use App\Notifications\ChatMessageReceived;
use App\Support\WebPushAvailability;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class NotificationBell extends Component
{
    public bool $pushSubscriptionSaved = false;

    public ?string $pushSubscriptionError = null;

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()->where('type', '!=', ChatMessageReceived::class)->update(['read_at' => now()]);
    }

    /**
     * @param  array{endpoint?: string, keys?: array{p256dh?: string, auth?: string}}  $subscription
     */
    public function savePushSubscription(array $subscription, SavePushSubscription $save): bool
    {
        $this->pushSubscriptionSaved = false;
        $this->pushSubscriptionError = null;

        if (! $this->withinRateLimit('save')) {
            $this->pushSubscriptionError = 'Too many attempts. Wait a minute and try again.';

            return false;
        }

        $this->pushSubscriptionSaved = $save->handle(auth()->user(), $subscription);

        if (! $this->pushSubscriptionSaved) {
            $this->pushSubscriptionError = 'Desktop notifications could not be enabled. Please try again.';
        }

        return $this->pushSubscriptionSaved;
    }

    /**
     * deletePushSubscription() is already scoped through the authenticated user's own
     * pushSubscriptions() relation, so an arbitrary endpoint can only ever delete a row this
     * user owns, never someone else's.
     */
    public function removePushSubscription(string $endpoint): bool
    {
        if (! $this->withinRateLimit('remove')) {
            return false;
        }

        auth()->user()->deletePushSubscription($endpoint);

        return true;
    }

    public function goTo(string $id): mixed
    {
        $notification = auth()->user()->notifications()->where('type', '!=', ChatMessageReceived::class)->whereKey($id)->first();

        if ($notification === null) {
            return null;
        }

        $notification->markAsRead();

        return match ($notification->data['type'] ?? null) {
            'order' => $this->redirect(route('orders.show', $notification->data['order_id'])),
            'seller_order' => $this->redirect(route('seller.orders.show', $notification->data['seller_order_id'])),
            default => null,
        };
    }

    public function render(): View
    {
        $user = auth()->user();
        $pushSubscriptions = WebPushAvailability::isConfigured()
            ? $user->pushSubscriptions()->get()
            : collect();

        return view('livewire.notification-bell', [
            'unreadCount' => $user->unreadNotifications()->where('type', '!=', ChatMessageReceived::class)->count(),
            'notifications' => $user->notifications()->where('type', '!=', ChatMessageReceived::class)->latest()->limit(10)->get(),
            'pushConfigured' => WebPushAvailability::isConfigured(),
            'hasPushSubscription' => $pushSubscriptions->isNotEmpty(),
            'pushEndpointHashes' => $pushSubscriptions
                ->map(fn ($subscription): string => hash('sha256', $subscription->endpoint))
                ->values()
                ->all(),
        ]);
    }

    private function withinRateLimit(string $action): bool
    {
        $key = 'web-push:'.$action.':'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            return false;
        }

        RateLimiter::hit($key, 60);

        return true;
    }
}
