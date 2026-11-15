<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\User;
use App\Services\GmailOrderMailer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * A pharmacist's decision on an order's prescription. Approval lets the branch move the order past
 * pending; rejection cancels it through UpdateSellerOrderStatus, which puts the stock back at the
 * branch and refunds an online payment once this commits.
 */
class ReviewPrescription
{
    public function __construct(private UpdateSellerOrderStatus $updateSellerOrderStatus) {}

    public function handle(User $pharmacist, Prescription $prescription, bool $approve, ?string $reason = null, ?string $ipAddress = null): Prescription
    {
        $reviewed = DB::transaction(function () use ($pharmacist, $prescription, $approve, $reason, $ipAddress): Prescription {
            // The order lock first, as UpdateSellerOrderStatus takes it, so a status change waits for this decision.
            $order = Order::query()->lockForUpdate()->findOrFail($prescription->order_id);
            $prescription = Prescription::query()->lockForUpdate()->findOrFail($prescription->id);
            $sellerOrder = $order->sellerOrders()->sole();

            if ($prescription->status !== 'pending') {
                throw ValidationException::withMessages(['prescription' => 'This prescription was already reviewed.']);
            }
            if ($sellerOrder->status === 'cancelled') {
                throw ValidationException::withMessages(['prescription' => 'This order was already cancelled.']);
            }

            $status = $approve ? 'approved' : 'rejected';
            $prescription->update(['status' => $status, 'reviewed_by' => $pharmacist->id, 'reviewed_at' => now(), 'reason' => $reason]);

            AuditLog::query()->create([
                'actor_user_id' => $pharmacist->id,
                'action' => 'prescription.'.$status,
                'entity_type' => Prescription::class,
                'entity_id' => $prescription->id,
                'old_values' => ['status' => 'pending'],
                'new_values' => ['status' => $status, 'reason' => $reason, 'order_id' => $order->id],
                'ip_address' => $ipAddress,
            ]);

            if (! $approve) {
                $this->updateSellerOrderStatus->handle($sellerOrder, 'cancelled', $pharmacist, $ipAddress);
            }

            return $prescription;
        });

        DB::afterCommit(function () use ($reviewed): void {
            $order = $reviewed->order()->with('user')->first();
            $message = $reviewed->status === 'approved'
                ? "A pharmacist approved the prescription for order #{$order->number}. The branch will now prepare your order."
                : "A pharmacist could not accept the prescription for order #{$order->number}, so the order was cancelled: {$reviewed->reason}";

            try {
                app(GmailOrderMailer::class)->send($order->user, "Prescription for order {$order->number}", $message."\n\nView your order: ".route('orders.show', $order));
            } catch (Throwable $exception) {
                report($exception);
            }
        });

        return $reviewed;
    }
}
