<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\SellerOrder;
use App\Services\PayMongo;
use App\Support\PlatformFee;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

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
            $sellerOrder = SellerOrder::query()->with('order.payment', 'tenant', 'store')->lockForUpdate()->findOrFail($sellerOrder->id);

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
     * Refunds part of a store order's online payment, for an approved return.
     *
     * The store gives back the amount less JMMart's fee on it, and JMMart gives back that fee, so
     * each side returns what it kept. PayMongo's fee, which the buyer paid separately, is not refunded.
     */
    public function refund(SellerOrder $sellerOrder, string $amount, ?string $idempotencyKey = null): string
    {
        $payment = $sellerOrder->order->payment;

        if ($payment === null || ! $payment->isOnline() || $payment->gateway_payment_id === null || $sellerOrder->store->paymongo_account_id === null) {
            throw new LogicException('This store order was not paid online, so there is no payment to refund.');
        }

        $total = (string) $sellerOrder->total;
        $platformShare = bcsub($total, StartOnlinePayment::storeShare($sellerOrder, $sellerOrder->order), 2);
        $platformPart = PlatformFee::on($amount, $sellerOrder->tenant->platformFeeRateAt($sellerOrder->order->created_at));
        $platformPart = bccomp($platformPart, $platformShare, 2) > 0 ? $platformShare : $platformPart;
        $sources = [$sellerOrder->store->paymongo_account_id => bcsub($amount, $platformPart, 2)];

        if (bccomp($platformPart, '0', 2) > 0) {
            $sources[config('services.paymongo.merchant_id') ?: throw new RuntimeException('PayMongo is not configured: set PAYMONGO_MERCHANT_ID.')] = $platformPart;
        }

        return $this->payMongo->refund($payment->gateway_payment_id, $amount, $sources, $idempotencyKey);
    }
}
