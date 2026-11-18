<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Branch staff's first look at a waiting prescription (clear photo, patient name, not expired), so the
 * pharmacist can decide faster. It approves nothing: the order still waits for ReviewPrescription.
 */
class CheckPrescription
{
    public function handle(User $checker, Prescription $prescription, ?string $ipAddress = null): Prescription
    {
        return DB::transaction(function () use ($checker, $prescription, $ipAddress): Prescription {
            // The order lock first, as ReviewPrescription takes it, so a check and a decision cannot interleave.
            $order = Order::query()->lockForUpdate()->findOrFail($prescription->order_id);
            $prescription = Prescription::query()->lockForUpdate()->findOrFail($prescription->id);

            if ($prescription->status !== 'pending') {
                throw ValidationException::withMessages(['prescription' => 'This prescription is no longer waiting for a check.']);
            }

            if ($order->sellerOrders()->sole()->status === 'cancelled') {
                throw ValidationException::withMessages(['prescription' => 'This order was already cancelled.']);
            }

            if ($prescription->checked_at !== null) {
                throw ValidationException::withMessages(['prescription' => 'Someone already checked this prescription.']);
            }

            $prescription->update(['checked_by' => $checker->id, 'checked_at' => now()]);

            AuditLog::query()->create([
                'actor_user_id' => $checker->id,
                'action' => 'prescription.checked',
                'entity_type' => Prescription::class,
                'entity_id' => $prescription->id,
                'new_values' => ['order_id' => $order->id],
                'ip_address' => $ipAddress,
            ]);

            return $prescription;
        });
    }
}
