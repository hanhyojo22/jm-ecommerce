<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ReorderToCart
{
    public function __construct(private AddProductToCart $addProductToCart) {}

    /**
     * Puts a past order's items back in the buyer's cart at today's prices. Items that were
     * removed or cannot be filled now are left out and named, so the buyer knows what is missing.
     *
     * @return list<string> names of the items that were left out
     */
    public function handle(User $user, Order $order, ?string $branchId = null): array
    {
        $skipped = [];

        foreach ($order->sellerOrders()->with('items')->get()->flatMap->items as $item) {
            $product = $item->product_id === null ? null : Product::query()->find($item->product_id);

            try {
                if ($product === null) {
                    $skipped[] = $item->product_name;

                    continue;
                }
                $this->addProductToCart->handle($user, $product, (int) $item->quantity, $item->product_variant_id, false, $branchId);
            } catch (ValidationException) {
                $skipped[] = $item->product_name;
            }
        }

        $user->unsetRelation('cart');

        return array_values(array_unique($skipped));
    }
}
