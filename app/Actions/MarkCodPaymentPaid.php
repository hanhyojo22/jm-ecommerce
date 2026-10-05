<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class MarkCodPaymentPaid
{
    public function __construct(private CreateSellerEarningsForPaidOrder $createSellerEarnings) {}

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
                ->get(['id', 'status']);

            $fulfillableSellerOrders = $sellerOrders->reject(fn ($sellerOrder): bool => $sellerOrder->status === 'cancelled');

            if ($fulfillableSellerOrders->isEmpty() || $fulfillableSellerOrders->contains(fn ($sellerOrder): bool => $sellerOrder->status !== 'delivered')) {
                throw new LogicException('COD payments can only be marked as paid when every non-cancelled seller order has been delivered.');
            }

            if ($payment->method !== 'cod' || $payment->status !== 'pending') {
                throw new LogicException('This payment cannot be marked as paid.');
            }

            $payment->update(['status' => 'paid']);
            $this->createSellerEarnings->handle($order);

            AuditLog::query()->create([
                'actor_user_id' => $administrator->id,
                'action' => 'payment.cod_marked_paid',
                'entity_type' => Payment::class,
                'entity_id' => $payment->id,
                'old_values' => ['status' => 'pending'],
                'new_values' => ['status' => 'paid', 'order_id' => $order->id],
                'ip_address' => $ipAddress,
            ]);

            return $payment;
        });
    }
}
