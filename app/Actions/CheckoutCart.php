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
     * @param  array{recipient_name: string, phone: string, address_line: string, city: string, province: string, postal_code: string, delivery_note?: ?string}  $delivery
     */
    public function handle(User $user, array $delivery): Order
    {
        abort_unless($user->is_active && in_array($user->account_type, ['buyer', 'seller'], true) && $user->hasVerifiedClerkEmail(), 403);

        return DB::transaction(function () use ($user, $delivery): Order {
            $cart = Cart::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($cart === null) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
            }

            $items = $cart->items()
                ->where('selected', true)
                ->orderBy('product_id')
                ->orderBy('variant_key')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Select an item to check out.']);
            }

            $products = Product::query()
                ->with(['store', 'tenant'])
                ->whereIn('id', $items->pluck('product_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $managedTenantIds = $user->tenantMemberships()->whereIn('role', ['owner', 'manager', 'staff'])->pluck('tenant_id')->all();

            $lines = $items->map(function ($item) use ($products, $managedTenantIds): array {
                $product = $products->get($item->product_id);

                $variant = $product !== null && $item->product_variant_id !== null
                    ? $product->variants()->with('images')->lockForUpdate()->find($item->product_variant_id)
                    : null;

                if ($product !== null && in_array($product->tenant_id, $managedTenantIds, true)) {
                    throw ValidationException::withMessages(['cart' => 'Remove products from stores you manage before checking out.']);
                }

                if (
                    $product === null
                    || $product->status !== 'active'
                    || $product->store->status !== 'active'
                    || $product->tenant->status !== 'active'
                    || $product->stock_quantity < $item->quantity
                    || ($item->product_variant_id !== null && ($variant === null || $variant->stock_quantity < $item->quantity))
                    || ($item->product_variant_id === null && $product->variants()->exists())
                ) {
                    throw ValidationException::withMessages(['cart' => 'A product in your cart is unavailable.']);
                }

                $product->decrement('stock_quantity', $item->quantity);
                $variant?->decrement('stock_quantity', $item->quantity);

                $unitPrice = (string) ($variant?->price ?? $product->price);

                return [
                    'product' => $product,
                    'variant' => $variant,
                    'quantity' => $item->quantity,
                    'unit_price' => $unitPrice,
                    'image_path' => $variant?->images->first()?->path ?? $product->images()->orderBy('position')->value('path'),
                    'total' => bcmul($unitPrice, (string) $item->quantity, 2),
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
            $user->deliveryAddresses()->firstOrCreate(collect($delivery)->except('delivery_note')->all());

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
                            'product_variant_id' => $line['variant']?->id,
                            'product_name' => $line['product']->name,
                            'sku' => $line['variant']?->sku ?? $line['product']->sku,
                            'variant_label' => $line['variant']?->label(),
                            'unit_price' => $line['unit_price'],
                            'image_path' => $line['image_path'],
                            'quantity' => $line['quantity'],
                            'line_total' => $line['total'],
                        ]);
                    }
                });

            $order->payment()->create(['method' => 'cod', 'amount' => $total]);
            $cart->items()->whereKey($items->modelKeys())->delete();

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
