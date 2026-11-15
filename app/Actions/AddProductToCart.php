<?php

namespace App\Actions;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Queries\CatalogQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddProductToCart
{
    /**
     * Add (or top up) a cart line. With $selectOnly (Buy now) the line becomes the
     * only ticked item in the same transaction, so checkout needs no extra request.
     */
    public function handle(User $user, Product $product, int $quantity, ?string $variantId = null, bool $selectOnly = false, ?string $branchId = null): CartItem
    {
        abort_unless(
            $user->is_active && in_array($user->account_type, ['buyer', 'seller'], true) && $user->hasVerifiedClerkEmail(),
            403,
        );

        return DB::transaction(function () use ($user, $product, $quantity, $variantId, $selectOnly, $branchId): CartItem {
            $cart = Cart::query()->lockForUpdate()->firstOrCreate(['user_id' => $user->id]);
            $product = Product::query()->with(['store', 'tenant'])->lockForUpdate()->findOrFail($product->id);
            $branch = $this->branch($cart, $product, $branchId);

            if ($user->isMemberOfTenant($product->tenant_id)) {
                throw ValidationException::withMessages(['cart' => 'You cannot buy products from a store you manage.']);
            }

            $hasVariants = $product->variants()->exists();
            $variant = $variantId !== null ? $product->variants()->find($variantId) : null;
            if (($hasVariants && $variant === null) || (! $hasVariants && $variantId !== null)) {
                throw ValidationException::withMessages(['variant_id' => 'Choose an available color and size.']);
            }

            $availableStock = BranchInventory::available($branch->id, $product->id, $variant?->id);

            if (
                $product->status !== 'active'
                || $product->tenant->status !== 'active'
                || $quantity < 1
                || $quantity > 100
                || $availableStock < $quantity
            ) {
                throw ValidationException::withMessages(['quantity' => 'This product is not currently available in that quantity.']);
            }

            $item = $cart->items()->firstOrNew(['product_id' => $product->id, 'variant_key' => $variant?->id ?? 'default']);
            $item->product_variant_id = $variant?->id;
            $item->quantity = ($item->exists ? $item->quantity : 0) + $quantity;
            // Adding a product says it is wanted, so a row left unticked earlier is ticked again.
            // Without this, Buy now on an unticked item sends the buyer back to the cart.
            $item->selected = true;
            if ($item->quantity > min($availableStock, 100)) {
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

    /**
     * The branch the cart buys from. A named branch becomes the cart's branch. Without one the cart
     * keeps its branch, or — shortcut: the web has no branch picker until Phase 7 — takes the
     * company's first open branch. The app always names a branch, so it never relies on this.
     */
    private function branch(Cart $cart, Product $product, ?string $branchId): Store
    {
        if ($branchId !== null) {
            $cart->update(['store_id' => CatalogQuery::publicStores()->whereKey($branchId)->firstOrFail()->id]);
        } elseif (
            $cart->store_id === null
            || $cart->store()->value('status') !== 'active'
            || ($cart->store()->value('tenant_id') !== $product->tenant_id && ! $cart->items()->exists())
        ) {
            // A cart left on a branch that has since closed (or an empty one left on another company's
            // branch) moves to the first open one, so the web (which cannot pick a branch yet) is never stuck.
            $cart->update(['store_id' => null]);
            $cart->update(['store_id' => $cart->branchFor($product->tenant_id)?->id]);
        }

        $branch = $cart->store()->first();

        if ($branch === null || $branch->status !== 'active' || $branch->tenant_id !== $product->tenant_id) {
            throw ValidationException::withMessages(['quantity' => 'This item is not sold at your selected branch.']);
        }

        return $branch;
    }
}
