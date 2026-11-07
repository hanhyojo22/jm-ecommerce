<?php

namespace App\Actions;

use App\Models\OrderIssue;
use App\Models\SellerOrder;
use App\Models\User;
use App\Notifications\OrderIssueOpened;
use App\Services\GmailOrderMailer;
use App\Services\PushNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class OpenOrderIssue
{
    /**
     * @var array<string, string>
     */
    private const LABELS = [
        'cancellation' => 'cancel this order',
        'delivery' => 'a delivery problem',
        'other' => 'help with this order',
    ];

    public function __construct(
        private readonly PushNotifier $notifier,
        private readonly GmailOrderMailer $mailer,
    ) {}

    /**
     * Open the buyer's one support request for a store order.
     */
    public function handle(SellerOrder $sellerOrder, User $buyer, string $type, string $details): OrderIssue
    {
        $issue = DB::transaction(function () use ($sellerOrder, $buyer, $type, $details): OrderIssue {
            $sellerOrder = SellerOrder::query()->with('order')->lockForUpdate()->findOrFail($sellerOrder->id);
            abort_unless($sellerOrder->order->user_id === $buyer->id, 404);

            if ($type === 'cancellation' && ! in_array($sellerOrder->status, ['pending', 'confirmed'], true)) {
                throw ValidationException::withMessages(['type' => 'This order is already being prepared or delivered. Ask for order help instead.']);
            }

            if ($sellerOrder->issues()->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['type' => 'A request for this store order is already open.']);
            }

            return OrderIssue::query()->create([
                'seller_order_id' => $sellerOrder->id,
                'buyer_id' => $buyer->id,
                'type' => $type,
                'details' => $details,
                'status' => 'open',
            ]);
        });

        // Best-effort and deferred: the buyer's request does not depend on the store
        // staff being paged, only on the issue existing.
        defer(function () use ($sellerOrder, $type): void {
            $this->notifyStoreStaff($sellerOrder, $type);
        });

        return $issue;
    }

    private function notifyStoreStaff(SellerOrder $sellerOrder, string $type): void
    {
        $sellerOrder->loadMissing('order', 'store', 'tenant');

        $recipients = $sellerOrder->tenant->activeNotifiableStaff();

        $title = "Order #{$sellerOrder->order->number} needs attention";
        $body = 'A buyer asked to '.(self::LABELS[$type] ?? self::LABELS['other']).' for '.$sellerOrder->store->name.'.';

        foreach ($recipients as $recipient) {
            try {
                $this->notifier->sendToUser($recipient, $title, $body, [
                    'type' => 'order_issue',
                    'seller_order_id' => $sellerOrder->id,
                ]);
            } catch (Throwable $exception) {
                report($exception);
            }

            try {
                $recipient->notify(new OrderIssueOpened($sellerOrder->id, $title, $body));
            } catch (Throwable $exception) {
                report($exception);
            }

            try {
                $this->mailer->send($recipient, $title, $body."\n\nView the order: ".route('seller.orders.show', $sellerOrder->id));
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
