<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\PaymentAdjustment;
use App\Models\SellerOrder;
use App\Models\User;
use App\Services\PushNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class RemoveSeniorPwdDiscount
{
    /**
     * The customer could not show a valid senior citizen / PWD ID at handover, so the branch charges
     * full price. Only for cash orders not yet delivered: a prepaid order cannot be charged more.
     */
    public function handle(User $actor, SellerOrder $sellerOrder, ?string $ipAddress = null): Order
    {
        $order = DB::transaction(function () use ($actor, $sellerOrder, $ipAddress): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($sellerOrder->order_id);
            $sellerOrders = $order->sellerOrders()->orderBy('id')->lockForUpdate()->get();
            $payment = $order->payment()->lockForUpdate()->firstOrFail();

            if (bccomp((string) $order->discount_amount, '0', 2) === 0) {
                throw ValidationException::withMessages(['discount' => 'This order has no senior citizen / PWD discount.']);
            }

            if ($payment->method !== 'cod' || $payment->status !== 'pending') {
                throw ValidationException::withMessages(['discount' => 'This order is already paid, so it cannot be charged more. Cancel it instead if the ID is not valid.']);
            }

            if ($sellerOrders->contains(fn (SellerOrder $storeOrder): bool => in_array($storeOrder->status, ['delivered', 'cancelled'], true))) {
                throw ValidationException::withMessages(['discount' => 'The discount can only be removed before the order is handed over.']);
            }

            $before = ['discount_amount' => (string) $order->discount_amount, 'total' => (string) $order->total];

            foreach ($sellerOrders as $storeOrder) {
                $storeOrder->items()->update(['discount_amount' => '0.00']);
                $storeOrder->update(['discount_amount' => '0.00', 'total' => bcadd((string) $storeOrder->subtotal, (string) $storeOrder->shipping_amount, 2)]);
            }

            $total = bcadd((string) $order->subtotal, (string) $order->shipping_amount, 2);
            $order->update(['discount_amount' => '0.00', 'total' => $total]);

            PaymentAdjustment::query()->create([
                'payment_id' => $payment->id,
                'actor_user_id' => $actor->id,
                'reason' => 'senior_pwd_discount.removed',
                'amount' => bcsub($total, (string) $payment->amount, 2),
                'resulting_amount' => $total,
            ]);
            $payment->update(['amount' => $total]);

            // The card number stays out of the log; the order keeps it encrypted.
            AuditLog::query()->create([
                'actor_user_id' => $actor->id,
                'action' => 'order.senior_pwd_discount_removed',
                'entity_type' => Order::class,
                'entity_id' => $order->id,
                'old_values' => $before,
                'new_values' => ['discount_amount' => '0.00', 'total' => $total],
                'ip_address' => $ipAddress,
            ]);

            return $order;
        });

        // After the commit, so a failed push never undoes the change; the customer learns the new amount.
        try {
            app(PushNotifier::class)->sendToUser(
                $order->user,
                'Senior citizen / PWD discount removed',
                'The branch could not confirm your ID. Your order total is now ₱'.number_format((float) $order->total, 2).'.',
                ['type' => 'order', 'order_id' => $order->id, 'status' => $sellerOrder->status],
            );
        } catch (Throwable $exception) {
            report($exception);
        }

        return $order;
    }
}
