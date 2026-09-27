<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\Product;
use App\Models\SellerOrder;
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

    public function handle(SellerOrder $sellerOrder, string $status): SellerOrder
    {
        return DB::transaction(function () use ($sellerOrder, $status): SellerOrder {
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

            if ($status === 'cancelled') {
                $this->restoreStock($sellerOrder);
            }

            $sellerOrder->update(['status' => $status]);

            $order->update([
                'status' => $this->aggregateStatus($sellerOrders->pluck('status')->all()),
            ]);

            return $sellerOrder;
        });
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
            }
        }
    }

    /**
     * @param  array<int, string>  $statuses
     */
    private function aggregateStatus(array $statuses): string
    {
        if (count(array_diff($statuses, ['cancelled'])) === 0) {
            return 'cancelled';
        }

        if (count(array_diff($statuses, ['delivered', 'cancelled'])) === 0) {
            return 'delivered';
        }

        foreach (['shipped', 'processing', 'confirmed'] as $status) {
            if (in_array($status, $statuses, true)) {
                return $status;
            }
        }

        return 'pending';
    }
}
