<?php

namespace App\Actions;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddProductToCart
{
    /**
     * Add (or top up) a cart line. With $selectOnly (Buy now) the line becomes the
     * only ticked item in the same transaction, so checkout needs no extra request.
     */
    public function handle(User $user, Product $product, int $quantity, ?string $variantId = null, bool $selectOnly = false): CartItem
    {
        abort_unless(
            $user->is_active && in_array($user->account_type, ['buyer', 'seller'], true) && $user->hasVerifiedClerkEmail(),
            403,
        );

        return DB::transaction(function () use ($user, $product, $quantity, $variantId, $selectOnly): CartItem {
            $cart = Cart::query()->lockForUpdate()->firstOrCreate(['user_id' => $user->id]);
            $product = Product::query()->with(['store', 'tenant'])->lockForUpdate()->findOrFail($product->id);

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
                || $quantity < 1
                || $quantity > 100
                || min($availableStock, $product->stock_quantity) < $quantity
            ) {
                throw ValidationException::withMessages(['quantity' => 'This product is not currently available in that quantity.']);
            }

            $item = $cart->items()->firstOrNew(['product_id' => $product->id, 'variant_key' => $variant?->id ?? 'default']);
            $item->product_variant_id = $variant?->id;
            $item->quantity = ($item->exists ? $item->quantity : 0) + $quantity;
            // Adding a product says it is wanted, so a row left unticked earlier is ticked again.
            // Without this, Buy now on an unticked item sends the buyer back to the cart.
            $item->selected = true;
            if ($item->quantity > min($availableStock, $product->stock_quantity, 100)) {
                throw ValidationException::withMessages(['quantity' => 'The combined cart quantity exceeds the available stock or the 100 item limit.']);
            }
            $item->save();

            if ($selectOnly) {
                $cart->items()->whereKeyNot($item->id)->update(['selected' => false]);
                $cart->touch();
            }

            return $item;
        });
    }
}
