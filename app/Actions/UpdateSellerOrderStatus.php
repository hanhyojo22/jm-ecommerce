<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\PaymentAdjustment;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\User;
use App\Notifications\SellerOrderReceiptConfirmed;
use App\Notifications\SellerOrderStatusUpdated;
use App\Queries\TopProductQuery;
use App\Services\GmailOrderMailer;
use App\Services\PushNotifier;
use App\Support\BuyerLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateSellerOrderStatus
{
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['shipped'],
        'shipped' => ['delivered'],
    ];

    public function __construct(private RefundOnlinePayment $refundOnlinePayment) {}

    /**
     * A null actor is Everyday Pharmacy itself, completing an order the buyer never confirmed or cancelling
     * one the buyer never paid for.
     *
     * Cancelling a store order the buyer already paid online refunds its share through PayMongo
     * once this commits; a refund that fails stays visible as due until it is retried.
     *
     * @param  ?array{name: string, phone: string}  $rider  who took a delivery out, saved when it is marked shipped
     */
    public function handle(SellerOrder $sellerOrder, string $status, ?User $actor, ?string $ipAddress = null, ?array $rider = null): SellerOrder
    {
        $updatedSellerOrder = DB::transaction(function () use ($sellerOrder, $status, $actor, $ipAddress, $rider): SellerOrder {
            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($sellerOrder->order_id);

            $sellerOrders = $order->sellerOrders()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $sellerOrder = $sellerOrders->firstWhere('id', $sellerOrder->id);

            // Support cancels a shipped order only when its store reported the parcel came back.
            $isReturnedParcel = $sellerOrder !== null
                && $sellerOrder->status === 'shipped'
                && $status === 'cancelled'
                && $actor?->isPlatformAdministrator()
                && $sellerOrder->issues()->where('status', 'open')->where('type', 'failed_delivery')->exists();

            if ($sellerOrder === null || (! $isReturnedParcel && ! in_array($status, self::transitionsFor($sellerOrder->status), true))) {
                throw ValidationException::withMessages([
                    'status' => 'This order cannot transition to that status.',
                ]);
            }

            if ($status !== 'cancelled' && $sellerOrder->issues()->where('status', 'open')->where('type', 'cancellation')->exists()) {
                throw ValidationException::withMessages(['status' => 'A cancellation request is awaiting review.']);
            }

            // Checked under the order lock that ReviewPrescription also takes, so a review and a status change cannot interleave.
            if ($status !== 'cancelled' && $order->prescription()->where('status', '!=', 'approved')->exists()) {
                throw ValidationException::withMessages(['status' => 'A pharmacist must approve this order’s prescription first.']);
            }

            $payment = $order->payment()->first();
            $isOnlinePayment = $payment?->isOnline() ?? false;

            // Paid to the store's own account before PayMongo, so there is no gateway payment to refund from.
            if ($isOnlinePayment && $status === 'cancelled' && $sellerOrder->cod_collected_at !== null && $payment->gateway_payment_id === null && ! $actor?->isPlatformAdministrator()) {
                throw ValidationException::withMessages(['status' => 'This order was paid to your store directly before online payments moved to PayMongo. Ask Everyday Pharmacy support to cancel it so the refund is recorded.']);
            }

            if ($isOnlinePayment && $status === 'confirmed' && $sellerOrder->cod_collected_at === null) {
                throw ValidationException::withMessages(['status' => 'Wait for the buyer’s online payment before confirming this order.']);
            }

            $previousStatus = $sellerOrder->status;
            $isPickup = $order->fulfillment_method === 'pickup';

            if ($status === 'cancelled') {
                $this->restoreStock($sellerOrder);
            }

            $sellerOrder->update([
                'status' => $status,
                'shipped_at' => $status === 'shipped' ? now() : $sellerOrder->shipped_at,
                // The first time it is ready stays the time the buyer was told to collect it.
                'pickup_ready_at' => $status === 'shipped' && $isPickup ? ($sellerOrder->pickup_ready_at ?? now()) : $sellerOrder->pickup_ready_at,
                'delivered_at' => $status === 'delivered' ? now() : $sellerOrder->delivered_at,
                ...($status === 'shipped' && $rider !== null ? ['rider_name' => $rider['name'], 'rider_phone' => $rider['phone']] : []),
            ]);

            if ($status === 'delivered') {
                // The parcel arrived after all, so a failed delivery report has nothing left to decide.
                $sellerOrder->issues()->where('status', 'open')->where('type', 'failed_delivery')
                    ->update(['status' => 'resolved', 'resolution_note' => 'Closed automatically: the order was marked delivered.', 'resolved_by' => $actor?->id]);
            }

            $activeSellerOrders = $sellerOrders->reject(
                fn (SellerOrder $order): bool => $order->status === 'cancelled',
            );
            $remainingTotal = $order->total;

            if ($status === 'cancelled') {
                $remainingSubtotal = $this->sumTotals($activeSellerOrders->pluck('subtotal')->all());
                $remainingShipping = $this->sumTotals($activeSellerOrders->pluck('shipping_amount')->all());
                $remainingDiscount = $this->sumTotals($activeSellerOrders->pluck('discount_amount')->all());
                $remainingTotal = bcadd(bcsub($remainingSubtotal, $remainingDiscount, 2), $remainingShipping, 2);

                $order->update([
                    'status' => $this->aggregateStatus($sellerOrders->pluck('status')->all()),
                    'subtotal' => $remainingSubtotal,
                    'shipping_amount' => $remainingShipping,
                    'discount_amount' => $remainingDiscount,
                    'total' => $remainingTotal,
                ]);

                $payment = $order->payment()->lockForUpdate()->firstOrFail();
                $previousPaymentAmount = $payment->amount;

                if (bccomp($previousPaymentAmount, $remainingTotal, 2) !== 0) {
                    $payment->update(['amount' => $remainingTotal]);

                    PaymentAdjustment::query()->create([
                        'payment_id' => $payment->id,
                        'actor_user_id' => $actor?->id,
                        'reason' => 'seller_order.cancelled',
                        'amount' => bcsub($remainingTotal, $previousPaymentAmount, 2),
                        'resulting_amount' => $remainingTotal,
                    ]);
                }
            } else {
                $order->update([
                    'status' => $this->aggregateStatus($sellerOrders->pluck('status')->all()),
                ]);
            }

            AuditLog::query()->create([
                'actor_user_id' => $actor?->id,
                'action' => 'seller_order.status_updated',
                'entity_type' => SellerOrder::class,
                'entity_id' => $sellerOrder->id,
                'old_values' => ['status' => $previousStatus],
                'new_values' => ['status' => $status, 'order_total' => $remainingTotal],
                'ip_address' => $ipAddress,
            ]);

            return $sellerOrder;
        });

        TopProductQuery::forgetCachedRankings();

        // The buyer confirming receipt is the only delivery event the store has no other
        // signal for: the order leaves its active queue and any COD becomes collectible.
        if ($status === 'delivered' && $actor !== null) {
            $updatedSellerOrder->loadMissing('order');

            if ($actor->id === $updatedSellerOrder->order->user_id) {
                defer(function () use ($updatedSellerOrder): void {
                    $this->notifyStoreStaffOfReceipt($updatedSellerOrder);
                });
            }
        }

        if ($status === 'cancelled' && $updatedSellerOrder->cod_collected_at !== null && $updatedSellerOrder->order->payment?->gateway_payment_id !== null) {
            DB::afterCommit(function () use ($updatedSellerOrder): void {
                try {
                    $this->refundOnlinePayment->cancelled($updatedSellerOrder);
                } catch (Throwable $exception) {
                    report($exception);
                }
            });
        }

        DB::afterCommit(function () use ($updatedSellerOrder, $status, $actor): void {
            $updatedSellerOrder->load('order.user', 'store');
            if ($actor?->is($updatedSellerOrder->order->user)) {
                return;
            }

            $number = $updatedSellerOrder->order->number;
            $isPickup = $updatedSellerOrder->order->fulfillment_method === 'pickup';
            $message = $actor === null
                ? "Your order #{$number} from {$updatedSellerOrder->store->name} was marked delivered because it went out for delivery ".config('marketplace.orders.auto_deliver_days').' days ago. If it never arrived, ask for order help.'
                : "Order #{$number} from {$updatedSellerOrder->store->name}: ".SellerOrder::statusLabel($status, $updatedSellerOrder->order->fulfillment_method).'.';

            try {
                app(GmailOrderMailer::class)->send(
                    $updatedSellerOrder->order->user,
                    "Everyday Pharmacy order {$number} update",
                    $message."\n\n".BuyerLink::emailLine('View your order', route('orders.show', $updatedSellerOrder->order_id)),
                );
            } catch (Throwable $exception) {
                report($exception);
            }

            try {
                $lead = $updatedSellerOrder->items()->orderBy('id')->first();
                $prescription = $updatedSellerOrder->order->prescription;
                $pushTitle = match (true) {
                    $status === 'confirmed' && $prescription?->status === 'approved' => 'Prescription approved · your order is being prepared',
                    $status === 'cancelled' && $prescription?->status === 'rejected' => 'Prescription not accepted · order cancelled',
                    default => null,
                } ?? match ($status) {
                    'confirmed' => 'Your order was accepted',
                    'processing' => 'Your order is being prepared',
                    'shipped' => match (true) {
                        $isPickup => 'Your order is ready for pickup',
                        filled($updatedSellerOrder->rider_name) => "Your order is out for delivery with {$updatedSellerOrder->rider_name}",
                        default => 'Your order is out for delivery',
                    },
                    'delivered' => $isPickup ? 'Your order was picked up' : 'Your order was delivered',
                    'cancelled' => 'Your order was cancelled',
                    default => 'Your order has an update',
                };
                app(PushNotifier::class)->sendToUser(
                    $updatedSellerOrder->order->user,
                    $pushTitle,
                    $status === 'cancelled' && $prescription?->status === 'rejected' && filled($prescription->reason)
                        ? (string) $prescription->reason
                        : implode(' · ', array_filter([$lead?->product_name, $lead?->variant_label, $updatedSellerOrder->store->name])),
                    array_filter([
                        'type' => 'order', 'order_id' => $updatedSellerOrder->order_id, 'status' => $status,
                        'subtitle' => $updatedSellerOrder->store->name,
                        'image_url' => $lead?->image_path !== null ? Storage::disk('product_images')->url($lead->image_path) : null,
                    ]),
                );
            } catch (Throwable $exception) {
                report($exception);
            }

            try {
                $updatedSellerOrder->order->user->notify(
                    new SellerOrderStatusUpdated($updatedSellerOrder->order_id, $status, "Order #{$number}", $message)
                );
            } catch (Throwable $exception) {
                report($exception);
            }
        });

        return $updatedSellerOrder;
    }

    private function notifyStoreStaffOfReceipt(SellerOrder $sellerOrder): void
    {
        $sellerOrder->loadMissing('order.payment', 'store', 'tenant');

        $recipients = $sellerOrder->tenant->activeNotifiableStaff($sellerOrder->store_id);

        if ($recipients->isEmpty()) {
            return;
        }

        $number = $sellerOrder->order->number;
        $title = "Order #{$number} confirmed received";
        $body = "{$sellerOrder->store->name}: the buyer confirmed they received this order.";

        if ($sellerOrder->order->payment?->method === 'cod' && $sellerOrder->cod_collected_at === null) {
            $body .= ' Collect the cash payment if it is still due.';
        }

        foreach ($recipients as $recipient) {
            try {
                app(PushNotifier::class)->sendToUser($recipient, $title, $body, [
                    'type' => 'seller_order',
                    'seller_order_id' => $sellerOrder->id,
                ]);
            } catch (Throwable $exception) {
                report($exception);
            }

            try {
                $recipient->notify(new SellerOrderReceiptConfirmed($sellerOrder->id, $title, $body));
            } catch (Throwable $exception) {
                report($exception);
            }

            try {
                app(GmailOrderMailer::class)->send(
                    $recipient,
                    "Everyday Pharmacy {$title}",
                    $body."\n\nView the order: ".route('seller.orders.show', $sellerOrder->id),
                );
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    public static function transitionsFor(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }

    private function restoreStock(SellerOrder $sellerOrder): void
    {
        $items = $sellerOrder->items()
            ->with('inventoryAllocations')
            ->orderBy('product_id')
            ->lockForUpdate()
            ->get();

        $products = Product::query()
            ->withTrashed()
            ->whereIn('id', $items->pluck('product_id')->filter())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($items as $item) {
            $product = $products->get($item->product_id);

            if ($product !== null) {
                $variant = $item->product_variant_id === null ? null : $product->variants()->whereKey($item->product_variant_id)->first();
                if ($item->product_variant_id === null || $variant !== null) {
                    app(BranchInventory::class)->restoreAllocations(
                        $sellerOrder->store_id,
                        $product,
                        $variant,
                        $item->inventoryAllocations->map(fn ($allocation): array => [
                            'batch_id' => $allocation->inventory_batch_id,
                            'quantity' => $allocation->quantity,
                        ])->all(),
                        $item->quantity,
                    );
                }
            }
        }
    }

    /**
     * The order is only as far along as the store that has moved the least.
     *
     * Reporting the furthest store instead told the buyer their order was shipped while another
     * store had not yet confirmed, and it put the order under that status in their purchases.
     * Cancelled stores are left out: they are no longer part of what is being waited on, and an
     * order is cancelled only once every store is.
     *
     * @param  array<int, string>  $statuses
     */
    private function aggregateStatus(array $statuses): string
    {
        $awaited = array_filter($statuses, static fn (string $status): bool => $status !== 'cancelled');

        if ($awaited === []) {
            return 'cancelled';
        }

        foreach (['pending', 'confirmed', 'processing', 'shipped', 'delivered'] as $status) {
            if (in_array($status, $awaited, true)) {
                return $status;
            }
        }

        return 'pending';
    }

    /**
     * @param  array<int, string>  $totals
     */
    private function sumTotals(array $totals): string
    {
        return array_reduce(
            $totals,
            fn (string $total, string $lineTotal): string => bcadd($total, $lineTotal, 2),
            '0.00',
        );
    }
}
