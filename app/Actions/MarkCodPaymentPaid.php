<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SellerOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class MarkCodPaymentPaid
{
    public function handle(User $administrator, Order $order, ?string $ipAddress = null): Payment
    {
        return DB::transaction(function () use ($administrator, $order, $ipAddress): Payment {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $payment = $order->payment()->lockForUpdate()->firstOrFail();

            if ($order->status !== 'delivered') {
                throw new LogicException('COD payments can only be marked as paid after delivery.');
            }

            $sellerOrders = $order->sellerOrders()
                ->lockForUpdate()
                ->get(['id', 'status', 'cod_collected_at']);

            $fulfillableSellerOrders = $sellerOrders->reject(fn ($sellerOrder): bool => $sellerOrder->status === 'cancelled');

            if ($fulfillableSellerOrders->isEmpty() || $fulfillableSellerOrders->contains(fn ($sellerOrder): bool => $sellerOrder->status !== 'delivered')) {
                throw new LogicException('COD payments can only be marked as paid when every non-cancelled seller order has been delivered.');
            }

            if ($payment->method !== 'cod' || $payment->status !== 'pending') {
                throw new LogicException('This payment cannot be marked as paid.');
            }

            foreach ($fulfillableSellerOrders as $sellerOrder) {
                if ($sellerOrder->cod_collected_at === null) {
                    SellerOrder::query()->whereKey($sellerOrder->id)->update(['cod_collected_at' => now()]);
                }
            }
            $payment->update(['status' => 'paid']);

            AuditLog::query()->create([
                'actor_user_id' => $administrator->id,
                'action' => 'payment.cod_marked_paid',
                'entity_type' => Payment::class,
                'entity_id' => $payment->id,
                'old_values' => ['status' => 'pending'],
                'new_values' => ['status' => 'paid', 'order_id' => $order->id, 'seller_order_ids' => $fulfillableSellerOrders->pluck('id')->all()],
                'ip_address' => $ipAddress,
            ]);

            return $payment;
        });
    }

    /**
     * The store records that it collected the COD cash after delivery. Online payments are marked
     * paid by PayMongo, not by the store.
     */
    public function collectSellerOrder(User $actor, SellerOrder $sellerOrder, ?string $ipAddress = null): void
    {
        DB::transaction(function () use ($actor, $sellerOrder, $ipAddress): void {
            $order = Order::query()->lockForUpdate()->findOrFail($sellerOrder->order_id);
            $payment = $order->payment()->lockForUpdate()->firstOrFail();
            $sellerOrder = $order->sellerOrders()->lockForUpdate()->findOrFail($sellerOrder->id);
            $isAwaitingPayment = $payment->method === 'cod' && $sellerOrder->status === 'delivered';
            if (! $isAwaitingPayment || ! in_array($payment->status, ['pending', 'paid'], true) || $sellerOrder->cod_collected_at !== null) {
                throw new LogicException('This seller order is not awaiting payment.');
            }
            $sellerOrder->update(['cod_collected_at' => now()]);
            if (! $order->sellerOrders()->where('status', '!=', 'cancelled')->whereNull('cod_collected_at')->exists()) {
                $payment->update(['status' => 'paid']);
            }
            AuditLog::query()->create([
                'actor_user_id' => $actor->id, 'action' => 'seller_order.cod_collected', 'entity_type' => SellerOrder::class,
                'entity_id' => $sellerOrder->id, 'new_values' => ['cod_collected_at' => $sellerOrder->cod_collected_at, 'method' => $payment->method, 'payment_status' => $payment->fresh()->status], 'ip_address' => $ipAddress,
            ]);
        });
    }
}
