<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\User;
use App\Services\GmailOrderMailer;
use App\Services\PushNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * A pharmacist's decision on an order's prescription. Approval accepts the order (unless an online
 * payment is still due); rejection cancels it through UpdateSellerOrderStatus, which puts the stock
 * back at the branch and refunds an online payment; asking for a clearer photo keeps the order
 * waiting until the customer sends one (ResubmitPrescription).
 */
class ReviewPrescription
{
    public const DECISIONS = ['approve', 'reject', 'needs_photo'];

    public function __construct(private UpdateSellerOrderStatus $updateSellerOrderStatus) {}

    /**
     * @param  string  $licenseNumber  The pharmacist's PRC license, kept on the prescription as the record of who decided.
     * @param  'approve'|'reject'|'needs_photo'  $decision
     */
    public function handle(User $pharmacist, string $licenseNumber, Prescription $prescription, string $decision, ?string $reason = null, ?string $ipAddress = null): Prescription
    {
        $accepted = false;
        $reviewed = DB::transaction(function () use ($pharmacist, $licenseNumber, $prescription, $decision, $reason, $ipAddress, &$accepted): Prescription {
            // The order lock first, as UpdateSellerOrderStatus takes it, so a status change waits for this decision.
            $order = Order::query()->with('payment')->lockForUpdate()->findOrFail($prescription->order_id);
            $prescription = Prescription::query()->lockForUpdate()->findOrFail($prescription->id);
            $sellerOrder = $order->sellerOrders()->sole();

            if ($prescription->status !== 'pending') {
                throw ValidationException::withMessages(['prescription' => 'This prescription was already reviewed.']);
            }

            if ($sellerOrder->status === 'cancelled') {
                throw ValidationException::withMessages(['prescription' => 'This order was already cancelled.']);
            }

            $status = ['approve' => 'approved', 'reject' => 'rejected', 'needs_photo' => 'needs_photo'][$decision];
            $prescription->update(['status' => $status, 'reviewed_by' => $pharmacist->id, 'reviewer_license_number' => $licenseNumber, 'reviewed_at' => now(), 'reason' => $reason]);

            AuditLog::query()->create([
                'actor_user_id' => $pharmacist->id,
                'action' => 'prescription.'.$status,
                'entity_type' => Prescription::class,
                'entity_id' => $prescription->id,
                'old_values' => ['status' => 'pending'],
                'new_values' => ['status' => $status, 'reason' => $reason, 'order_id' => $order->id, 'license_number' => $licenseNumber],
                'ip_address' => $ipAddress,
            ]);

            if ($status === 'rejected') {
                $this->updateSellerOrderStatus->handle($sellerOrder, 'cancelled', $pharmacist, $ipAddress);
            }

            // Approving is the go-ahead, so the branch does not have to accept the order separately;
            // an online payment still due waits for PayMongo, as a manual accept would.
            $paymentDue = $order->payment?->isOnline() && $order->payment->status !== 'paid';
            if ($status === 'approved' && $sellerOrder->status === 'pending' && ! $paymentDue) {
                $this->updateSellerOrderStatus->handle($sellerOrder, 'confirmed', $pharmacist, $ipAddress);
                $accepted = true;
            }

            return $prescription;
        });

        DB::afterCommit(function () use ($reviewed, $accepted): void {
            $order = $reviewed->order()->with('user')->first();
            $message = match ($reviewed->status) {
                'approved' => "A pharmacist approved the prescription for order #{$order->number}. The branch will now prepare your order.",
                'needs_photo' => "A pharmacist needs a clearer photo of the prescription for order #{$order->number}: {$reviewed->reason} Send a new photo from your order page.",
                default => "A pharmacist could not accept the prescription for order #{$order->number}, so the order was cancelled: {$reviewed->reason}",
            };

            try {
                app(GmailOrderMailer::class)->send($order->user, "Prescription for order {$order->number}", $message."\n\nView your order: ".route('orders.show', $order));
            } catch (Throwable $exception) {
                report($exception);
            }

            // Accepting and cancelling send their own push, which names the prescription.
            if ($reviewed->status === 'needs_photo' || ($reviewed->status === 'approved' && ! $accepted)) {
                try {
                    app(PushNotifier::class)->sendToUser(
                        $order->user,
                        $reviewed->status === 'approved' ? 'Prescription approved' : 'Send a clearer prescription photo',
                        $reviewed->status === 'approved' ? 'Your order will be prepared once your payment arrives.' : (string) $reviewed->reason,
                        ['type' => 'order', 'order_id' => $order->id, 'status' => 'pending'],
                    );
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        });

        return $reviewed;
    }
}
