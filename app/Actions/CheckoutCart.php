<?php

namespace App\Actions;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutCart
{
    /**
     * @param  array{recipient_name: string, phone: string, address_line: string, city: string}  $delivery
     */
    public function handle(User $user, array $delivery): Order
    {
        return DB::transaction(function () use ($user, $delivery): Order {
            $cart = Cart::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($cart === null) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
            }

            $items = $cart->items()
                ->orderBy('product_id')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
            }

            $products = Product::query()
                ->with(['store', 'tenant'])
                ->whereIn('id', $items->pluck('product_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lines = $items->map(function ($item) use ($products): array {
                $product = $products->get($item->product_id);

                if (
                    $product === null
                    || $product->status !== 'active'
                    || $product->store->status !== 'active'
                    || $product->tenant->status !== 'active'
                    || $product->stock_quantity < $item->quantity
                ) {
                    throw ValidationException::withMessages(['cart' => 'A product in your cart is unavailable.']);
                }

                $product->decrement('stock_quantity', $item->quantity);

                return [
                    'product' => $product,
                    'quantity' => $item->quantity,
                    'total' => bcmul((string) $product->price, (string) $item->quantity, 2),
                ];
            });

            $total = $this->sumTotals($lines->pluck('total')->all());

            if (bccomp($total, '9999999999.99', 2) > 0) {
                throw ValidationException::withMessages([
                    'cart' => 'Your cart total exceeds the maximum order amount.',
                ]);
            }

            $order = Order::query()->create([
                ...$delivery,
                'user_id' => $user->id,
                'number' => 'JM-'.Str::upper((string) Str::ulid()),
                'subtotal' => $total,
                'shipping_amount' => '0.00',
                'total' => $total,
            ]);

            $lines->groupBy(fn (array $line): string => $line['product']->store_id)
                ->each(function ($storeLines) use ($order): void {
                    $firstLine = $storeLines->first();
                    $subtotal = $this->sumTotals($storeLines->pluck('total')->all());

                    $sellerOrder = $order->sellerOrders()->create([
                        'tenant_id' => $firstLine['product']->tenant_id,
                        'store_id' => $firstLine['product']->store_id,
                        'subtotal' => $subtotal,
                        'shipping_amount' => '0.00',
                        'total' => $subtotal,
                    ]);

                    foreach ($storeLines as $line) {
                        $sellerOrder->items()->create([
                            'product_id' => $line['product']->id,
                            'product_name' => $line['product']->name,
                            'sku' => $line['product']->sku,
                            'unit_price' => $line['product']->price,
                            'quantity' => $line['quantity'],
                            'line_total' => $line['total'],
                        ]);
                    }
                });

            $order->payment()->create(['method' => 'cod', 'amount' => $total]);
            $cart->items()->delete();

            return $order;
        });
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
