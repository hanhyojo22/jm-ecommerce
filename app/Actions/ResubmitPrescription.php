<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\User;
use App\Notifications\NewSellerOrderPlaced;
use App\Services\PushNotifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The customer sends a new prescription photo after a pharmacist asked for a clearer one. The
 * prescription goes back to pending so the branch's pharmacists review it again.
 */
class ResubmitPrescription
{
    public function handle(User $customer, Order $order, UploadedFile $file): Prescription
    {
        $path = $file->store('prescriptions/'.$order->id, 'chat_attachments');
        if ($path === false) {
            throw ValidationException::withMessages(['prescription' => 'Your prescription could not be saved. Try again.']);
        }

        try {
            [$prescription, $oldPath] = DB::transaction(function () use ($customer, $order, $file, $path): array {
                // The order lock first, as ReviewPrescription and UpdateSellerOrderStatus take it.
                $order = Order::query()->lockForUpdate()->findOrFail($order->id);
                $prescription = $order->prescription()->lockForUpdate()->first();

                if ($prescription === null || $prescription->status !== 'needs_photo' || $order->sellerOrders()->where('status', 'cancelled')->exists()) {
                    throw ValidationException::withMessages(['prescription' => 'This order is not waiting for a new prescription photo.']);
                }

                $oldPath = $prescription->path;
                $prescription->update([
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'status' => 'pending',
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'reason' => null,
                ]);

                AuditLog::query()->create([
                    'actor_user_id' => $customer->id,
                    'action' => 'prescription.resubmitted',
                    'entity_type' => Prescription::class,
                    'entity_id' => $prescription->id,
                    'old_values' => ['status' => 'needs_photo'],
                    'new_values' => ['status' => 'pending', 'order_id' => $order->id],
                ]);

                return [$prescription, $oldPath];
            });
        } catch (Throwable $exception) {
            Storage::disk('chat_attachments')->delete($path);

            throw $exception;
        }

        Storage::disk('chat_attachments')->delete($oldPath);
        $this->notifyBranch($order);

        return $prescription;
    }

    /**
     * Tells the branch's staff the new photo is in, so a pharmacist reviews it without waiting
     * to notice. Best-effort: the photo is saved either way.
     */
    private function notifyBranch(Order $order): void
    {
        $order->loadMissing('sellerOrders.store', 'sellerOrders.tenant');

        defer(function () use ($order): void {
            foreach ($order->sellerOrders as $sellerOrder) {
                $title = "New prescription photo for order #{$order->number}";
                $body = "The customer sent a clearer photo. Review it on {$sellerOrder->store->name}'s order.";

                foreach ($sellerOrder->tenant->activeNotifiableStaff($sellerOrder->store_id) as $recipient) {
                    try {
                        app(PushNotifier::class)->sendToUser($recipient, $title, $body, ['type' => 'seller_order', 'seller_order_id' => $sellerOrder->id]);
                        $recipient->notify(new NewSellerOrderPlaced($sellerOrder->id, $title, $body));
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                }
            }
        });
    }
}
