<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\SellerOrder;
use App\Services\PayMongo;
use Illuminate\Support\Facades\DB;
use LogicException;

class RefundOnlinePayment
{
    public function __construct(private PayMongo $payMongo) {}

    /**
     * Refunds a paid store order that was cancelled, in full, and records the refund on it. A store
     * order already refunded is left alone, so a retry never pays the buyer twice.
     */
    public function cancelled(SellerOrder $sellerOrder): ?string
    {
        return DB::transaction(function () use ($sellerOrder): ?string {
            $sellerOrder = SellerOrder::query()->with('order.payment', 'store')->lockForUpdate()->findOrFail($sellerOrder->id);

            if ($sellerOrder->status !== 'cancelled' || $sellerOrder->gateway_refund_id !== null) {
                return $sellerOrder->gateway_refund_id;
            }

            $refundId = $this->refund($sellerOrder, (string) $sellerOrder->total, 'cancelled-order:'.$sellerOrder->id);

            SellerOrder::query()->whereKey($sellerOrder->id)->whereNull('gateway_refund_id')->update(['gateway_refund_id' => $refundId]);
            AuditLog::query()->create([
                'actor_user_id' => null, 'action' => 'seller_order.online_refunded', 'entity_type' => SellerOrder::class,
                'entity_id' => $sellerOrder->id, 'new_values' => ['amount' => (string) $sellerOrder->total, 'gateway_refund_id' => $refundId],
            ]);

            return $refundId;
        });
    }

    /**
     * Refunds part of a store order's online payment, for an approved return. The pharmacy's own
     * account gives it all back; PayMongo's fee, which the buyer paid separately, is not refunded.
     */
    public function refund(SellerOrder $sellerOrder, string $amount, ?string $idempotencyKey = null): string
    {
        $payment = $sellerOrder->order->payment;

        if ($payment === null || ! $payment->isOnline() || $payment->gateway_payment_id === null) {
            throw new LogicException('This store order was not paid online, so there is no payment to refund.');
        }

        return $this->payMongo->refund($payment->gateway_payment_id, $amount, $idempotencyKey);
    }
}
