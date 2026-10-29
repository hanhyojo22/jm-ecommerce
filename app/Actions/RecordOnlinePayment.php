<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SellerOrder;
use Illuminate\Support\Facades\DB;
use Throwable;

class RecordOnlinePayment
{
    public function __construct(private RefundOnlinePayment $refundOnlinePayment) {}

    /**
     * PayMongo reports that a checkout session was paid. The split already sent each store its
     * share, so every store order still open is marked paid and can be confirmed. A store order
     * cancelled before the money arrived is refunded from that store's share once this commits.
     *
     * Replays are harmless: a payment already marked paid is left as it is.
     */
    public function handle(string $checkoutSessionId, string $gatewayPaymentId): ?Payment
    {
        $cancelled = collect();

        $payment = DB::transaction(function () use ($checkoutSessionId, $gatewayPaymentId, &$cancelled): ?Payment {
            $orderId = Payment::query()->where('checkout_session_id', $checkoutSessionId)->value('order_id');

            if ($orderId === null) {
                return null;
            }

            $order = Order::query()->lockForUpdate()->findOrFail($orderId);
            $payment = $order->payment()->lockForUpdate()->firstOrFail();

            if ($payment->status !== 'pending' || $payment->checkout_session_id !== $checkoutSessionId) {
                return $payment;
            }

            $sellerOrders = $order->sellerOrders()->orderBy('id')->lockForUpdate()->get();
            [$cancelled, $open] = $sellerOrders->partition(fn (SellerOrder $sellerOrder): bool => $sellerOrder->status === 'cancelled');

            SellerOrder::query()->whereKey($sellerOrders->modelKeys())->whereNull('cod_collected_at')->update(['cod_collected_at' => now()]);
            $payment->update(['status' => 'paid', 'gateway_payment_id' => $gatewayPaymentId]);

            AuditLog::query()->create([
                'actor_user_id' => null,
                'action' => 'payment.online_paid',
                'entity_type' => Payment::class,
                'entity_id' => $payment->id,
                'old_values' => ['status' => 'pending'],
                'new_values' => ['status' => 'paid', 'gateway_payment_id' => $gatewayPaymentId, 'seller_order_ids' => $open->modelKeys()],
            ]);

            return $payment;
        });

        foreach ($cancelled as $sellerOrder) {
            try {
                $this->refundOnlinePayment->cancelled($sellerOrder);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $payment;
    }
}
