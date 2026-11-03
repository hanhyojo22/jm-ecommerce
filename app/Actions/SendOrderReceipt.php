<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\User;
use App\Services\GmailOrderMailer;
use Throwable;

class SendOrderReceipt
{
    public function __construct(private GmailOrderMailer $mailer) {}

    /**
     * Send the "order received" mail once, after the response is already on its way.
     *
     * Two calls to Google, each allowed eight seconds, used to sit between the buyer
     * pressing Place order and seeing it. The order does not depend on the mail, so a
     * failure is reported and never fails the order. A queued job would add retries if
     * the receipt ever has to be guaranteed.
     */
    public function handle(User $buyer, Order $order, string $paymentNote): void
    {
        // A replayed checkout already sent its receipt on the first attempt.
        if (! $order->wasRecentlyCreated) {
            return;
        }

        defer(function () use ($buyer, $order, $paymentNote): void {
            try {
                $this->mailer->send(
                    $buyer,
                    "JMMart order {$order->number} received",
                    "Thanks for your order!\n\nOrder #{$order->number} was placed for PHP {$order->total}, {$paymentNote}\n\nTrack your order or request help: ".route('orders.show', $order),
                );
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
