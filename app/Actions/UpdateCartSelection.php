<?php

namespace App\Actions;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;

class UpdateCartSelection
{
    /**
     * Replace the buyer's checked items with the given cart item ids.
     *
     * Ids belonging to another buyer are ignored rather than rejected, so a stale
     * selection from a second device cannot be used to probe other carts.
     *
     * @param  list<string>  $selectedIds
     * @return array{selected_count: int, subtotal: string}
     */
    public function handle(User $user, array $selectedIds): array
    {
        $cart = $user->cart()->first();

        if ($cart === null) {
            return ['selected_count' => 0, 'subtotal' => '0.00'];
        }

        $ownedIds = $cart->items()->whereKey($selectedIds)->pluck('id');

        $cart->items()->whereKey($ownedIds)->update(['selected' => true]);
        $cart->items()->whereKeyNot($ownedIds)->update(['selected' => false]);
        $cart->touch();

        return [
            'selected_count' => $ownedIds->count(),
            'subtotal' => $this->subtotal($cart),
        ];
    }

    private function subtotal(Cart $cart): string
    {
        $items = $cart->items()
            ->where('selected', true)
            ->with([
                'product:id,tenant_id,store_id,name,slug,price,stock_quantity,status',
                'variant',
            ])
            ->get();

        return $items->reduce(
            fn (string $total, CartItem $item): string => bcadd(
                $total,
                bcmul($item->unitPrice(), (string) $item->quantity, 2),
                2,
            ),
            '0.00',
        );
    }
}
