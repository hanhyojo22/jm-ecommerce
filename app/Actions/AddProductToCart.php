<?php

namespace App\Actions;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AddProductToCart
{
    public function handle(User $user, Product $product, int $quantity): CartItem
    {
        abort_unless(
            $user->is_active && $user->account_type === 'buyer' && $user->hasVerifiedClerkEmail(),
            403,
        );

        $product = Product::query()->with(['store', 'tenant'])->findOrFail($product->id);

        if (
            $product->status !== 'active'
            || $product->store->status !== 'active'
            || $product->tenant->status !== 'active'
            || $product->stock_quantity < $quantity
        ) {
            throw ValidationException::withMessages(['quantity' => 'This product is not currently available in that quantity.']);
        }

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        $item = $cart->items()->firstOrNew(['product_id' => $product->id]);
        $item->quantity = min($product->stock_quantity, ($item->exists ? $item->quantity : 0) + $quantity);
        $item->save();

        return $item;
    }
}
