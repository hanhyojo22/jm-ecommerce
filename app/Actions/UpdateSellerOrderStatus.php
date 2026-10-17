<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Order;
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

    public function handle(SellerOrder $sellerOrder, string $status, User $actor, ?string $ipAddress = null): SellerOrder
    {
        $updatedSellerOrder = DB::transaction(function () use ($sellerOrder, $status, $actor, $ipAddress): SellerOrder {
            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($sellerOrder->order_id);

            $sellerOrders = $order->sellerOrders()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $sellerOrder = $sellerOrders->firstWhere('id', $sellerOrder->id);

            if ($sellerOrder === null || ! in_array($status, self::transitionsFor($sellerOrder->status), true)) {
                throw ValidationException::withMessages([
                    'status' => 'This order cannot transition to that status.',
                ]);
            }

            if ($status !== 'cancelled' && $sellerOrder->issues()->where('status', 'open')->where('type', 'cancellation')->exists()) {
                throw ValidationException::withMessages(['status' => 'A cancellation request is awaiting review.']);
            }

            $previousStatus = $sellerOrder->status;

            if ($status === 'cancelled') {
                $this->restoreStock($sellerOrder);
            }

            $sellerOrder->update(['status' => $status]);

            $activeSellerOrders = $sellerOrders->reject(
                fn (SellerOrder $order): bool => $order->status === 'cancelled',
            );
            $remainingTotal = $this->sumTotals($activeSellerOrders->pluck('total')->all());

            $order->update([
                'status' => $this->aggregateStatus($sellerOrders->pluck('status')->all()),
                'subtotal' => $remainingTotal,
                'shipping_amount' => '0.00',
                'total' => $remainingTotal,
            ]);
            $order->payment()->lockForUpdate()->firstOrFail()->update(['amount' => $remainingTotal]);

            AuditLog::query()->create([
                'actor_user_id' => $actor->id,
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

        DB::afterCommit(function () use ($updatedSellerOrder, $status): void {
            try {
                $updatedSellerOrder->load('order.user', 'store');
                app(GmailOrderMailer::class)->send(
                    $updatedSellerOrder->order->user,
                    "JMMart order {$updatedSellerOrder->order->number} update",
                    "{$updatedSellerOrder->store->name} marked its part of order #{$updatedSellerOrder->order->number} as {$status}.\n\nView your order: ".route('orders.show', $updatedSellerOrder->order_id),
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
