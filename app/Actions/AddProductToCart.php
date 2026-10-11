<?php

namespace App\Actions;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AddProductToCart
{
    public function handle(User $user, Product $product, int $quantity, ?string $variantId = null): CartItem
    {
        abort_unless(
            $user->is_active && in_array($user->account_type, ['buyer', 'seller'], true) && $user->hasVerifiedClerkEmail(),
            403,
        );

        $product = Product::query()->with(['store', 'tenant'])->findOrFail($product->id);

        if ($user->isMemberOfTenant($product->tenant_id)) {
            throw ValidationException::withMessages(['cart' => 'You cannot buy products from a store you manage.']);
        }

        $hasVariants = $product->variants()->exists();
        $variant = $variantId !== null ? $product->variants()->find($variantId) : null;
        if (($hasVariants && $variant === null) || (! $hasVariants && $variantId !== null)) {
            throw ValidationException::withMessages(['variant_id' => 'Choose an available color and size.']);
        }

        $availableStock = $variant?->stock_quantity ?? $product->stock_quantity;

        if (
            $product->status !== 'active'
            || $product->store->status !== 'active'
            || $product->tenant->status !== 'active'
            || $availableStock < $quantity
        ) {
            throw ValidationException::withMessages(['quantity' => 'This product is not currently available in that quantity.']);
        }

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        $item = $cart->items()->firstOrNew(['product_id' => $product->id, 'variant_key' => $variant?->id ?? 'default']);
        $item->product_variant_id = $variant?->id;
        $item->quantity = min($availableStock, ($item->exists ? $item->quantity : 0) + $quantity);
        $item->save();

        return $item;
    }
}
