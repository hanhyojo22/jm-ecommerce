<?php

namespace App\Actions;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpdateCartItemQuantity
{
    /**
     * Change a cart line's quantity after re-checking the listing and its stock.
     *
     * Authorization stays in the caller's policy; this only enforces the buying rules.
     */
    public function handle(User $user, CartItem $cartItem, int $quantity): CartItem
    {
        $product = Product::query()
            ->with(['store', 'tenant'])
            ->findOrFail($cartItem->product_id);

        if ($user->isMemberOfTenant($product->tenant_id)) {
            throw ValidationException::withMessages(['cart' => 'You cannot buy products from a store you manage.']);
        }

        $variant = $cartItem->product_variant_id !== null
            ? $product->variants()->find($cartItem->product_variant_id)
            : null;
        $branch = $cartItem->cart->store;
        $availableStock = $branch === null ? 0 : BranchInventory::available($branch->id, $product->id, $variant?->id);

        if (
            $product->status !== 'active'
            || $product->tenant->status !== 'active'
            || $branch?->status !== 'active'
            || $branch->tenant_id !== $product->tenant_id
            || ($cartItem->product_variant_id !== null && $variant === null)
            || ($cartItem->product_variant_id === null && $product->variants()->exists())
            || $availableStock < $quantity
        ) {
            throw ValidationException::withMessages(['quantity' => 'This product is not currently available in that quantity.']);
        }

        $cartItem->update(['quantity' => $quantity]);

        return $cartItem;
    }
}
