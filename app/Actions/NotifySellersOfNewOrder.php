<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\User;
use App\Notifications\NewSellerOrderPlaced;
use App\Services\PushNotifier;
use Throwable;

class NotifySellersOfNewOrder
{
    public function __construct(private readonly PushNotifier $notifier) {}

    /**
     * Alerts each store's active staff that a new order came in for them, once per store in
     * the order. Best-effort and deferred: the order does not depend on this, and a replayed
     * checkout already notified sellers on the first attempt.
     */
    public function handle(Order $order): void
    {
        if (! $order->wasRecentlyCreated) {
            return;
        }

        $order->loadMissing('sellerOrders.store');

        defer(function () use ($order): void {
            foreach ($order->sellerOrders as $sellerOrder) {
                $this->notifyStoreStaff($order, $sellerOrder);
            }
        });
    }

    private function notifyStoreStaff(Order $order, SellerOrder $sellerOrder): void
    {
        $recipients = User::query()
            ->where('account_type', 'seller')
            ->where('is_active', true)
            ->whereHas('tenantMemberships', fn ($query) => $query
                ->where('tenant_id', $sellerOrder->tenant_id)
                ->whereIn('role', ['owner', 'manager', 'staff'])
                ->whereHas('tenant', fn ($tenant) => $tenant
                    ->where('status', 'active')
                    ->whereHas('store', fn ($store) => $store->where('status', 'active'))))
            ->get();

        $title = "New order #{$order->number}";
        $body = "{$sellerOrder->store->name} received a new order worth PHP {$sellerOrder->total}.";

        foreach ($recipients as $recipient) {
            try {
                $this->notifier->sendToUser($recipient, $title, $body, [
                    'type' => 'seller_order',
                    'seller_order_id' => $sellerOrder->id,
                ]);
            } catch (Throwable $exception) {
                report($exception);
            }

            try {
                $recipient->notify(new NewSellerOrderPlaced($sellerOrder->id, $title, $body));
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
