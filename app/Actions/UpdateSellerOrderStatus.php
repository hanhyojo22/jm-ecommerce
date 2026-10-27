<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\User;
use App\Queries\TopProductQuery;
use App\Services\GmailOrderMailer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateSellerOrderStatus
{
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['shipped'],
        'shipped' => ['delivered'],
    ];

    public function __construct(private RecordSellerEarning $recordSellerEarning) {}

    /**
     * A null actor is JMMart itself, completing an order the buyer never confirmed or cancelling
     * one the buyer never paid for.
     *
     * A store that cancels after receiving a GCash/Maya payment refunds the buyer itself and
     * records the transfer's reference here.
     */
    public function handle(SellerOrder $sellerOrder, string $status, ?User $actor, ?string $ipAddress = null, ?string $refundReference = null): SellerOrder
    {
        $updatedSellerOrder = DB::transaction(function () use ($sellerOrder, $status, $actor, $ipAddress, $refundReference): SellerOrder {
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

            $isWalletPayment = in_array($order->payment()->value('method'), Payment::DIRECT_METHODS, true);

            if ($isWalletPayment && $status === 'confirmed' && $sellerOrder->cod_collected_at === null) {
                throw ValidationException::withMessages(['status' => 'Record that the buyer’s payment arrived before confirming this order.']);
            }

            $refundReference = $isWalletPayment && $status === 'cancelled' && $sellerOrder->cod_collected_at !== null ? $refundReference : null;

            if ($isWalletPayment && $status === 'cancelled' && $sellerOrder->cod_collected_at !== null && blank($refundReference) && ! $actor?->isPlatformAdministrator()) {
                throw ValidationException::withMessages(['refund_reference' => 'Refund the buyer’s payment, then enter the refund reference number to cancel.']);
            }

            $previousStatus = $sellerOrder->status;

            if ($status === 'cancelled') {
                $this->restoreStock($sellerOrder);
            }

            $sellerOrder->update([
                'refund_reference' => $refundReference ?? $sellerOrder->refund_reference,
                'status' => $status,
                'shipped_at' => $status === 'shipped' ? now() : $sellerOrder->shipped_at,
                'delivered_at' => $status === 'delivered' ? now() : $sellerOrder->delivered_at,
            ]);

            if ($status === 'delivered') {
                $this->recordSellerEarning->handle($sellerOrder);
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
                $remainingTotal = bcadd($remainingSubtotal, $remainingShipping, 2);

                $order->update([
                    'status' => $this->aggregateStatus($sellerOrders->pluck('status')->all()),
                    'subtotal' => $remainingSubtotal,
                    'shipping_amount' => $remainingShipping,
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
                'new_values' => array_filter(['status' => $status, 'order_total' => $remainingTotal, 'refund_reference' => $refundReference], fn ($value): bool => $value !== null),
                'ip_address' => $ipAddress,
            ]);

            return $sellerOrder;
        });

        TopProductQuery::forgetCachedRankings();

        DB::afterCommit(function () use ($updatedSellerOrder, $status, $actor): void {
            try {
                $updatedSellerOrder->load('order.user', 'store');
                if ($actor?->is($updatedSellerOrder->order->user)) {
                    return;
                }

                $number = $updatedSellerOrder->order->number;
                app(GmailOrderMailer::class)->send(
                    $updatedSellerOrder->order->user,
                    "JMMart order {$number} update",
                    ($actor === null
                        ? "Your {$updatedSellerOrder->store->name} items from order #{$number} were marked delivered because they shipped ".config('marketplace.orders.auto_deliver_days').' days ago. If they never arrived, ask for order help.'
                        : "{$updatedSellerOrder->store->name} marked its part of order #{$number} as {$status}.")
                    ."\n\nView your order: ".route('orders.show', $updatedSellerOrder->order_id),
                );
            } catch (\Throwable $exception) {
                report($exception);
            }
        });

        return $updatedSellerOrder;
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
                $product->increment('stock_quantity', $item->quantity);
                if ($item->product_variant_id !== null) {
                    $product->variants()->whereKey($item->product_variant_id)->first()?->increment('stock_quantity', $item->quantity);
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
