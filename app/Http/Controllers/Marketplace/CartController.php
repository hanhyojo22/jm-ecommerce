<?php

namespace App\Http\Controllers\Marketplace;

use App\Actions\AddProductToCart;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(Request $request): View
    {
        $cart = $request->user()->cart()->with([
            'items.product:id,tenant_id,store_id,name,slug,price,stock_quantity,status',
            'items.product.store:id,name,slug,status',
            'items.product.tenant:id,status',
            'items.product.thumbnail',
            'items.product.variants',
            'items.product.images' => fn ($query) => $query->whereNotNull('color'),
            'items.variant',
        ])->first();

        $cart?->items->each(function (CartItem $item): void {
            $item->setAttribute('line_total', bcmul($item->unitPrice(), (string) $item->quantity, 2));
        });

        $this->deselectUnavailableItems($cart);

        return view('marketplace.cart.index', [
            'cart' => $cart,
            'subtotal' => $this->subtotal($cart),
        ]);
    }

    /**
     * Replace the buyer's selection with the given cart item ids.
     */
    public function updateSelection(Request $request): JsonResponse
    {
        $data = $request->validate([
            'selected' => ['present', 'array'],
            'selected.*' => ['ulid'],
        ]);

        $cart = $request->user()->cart()->first();

        if ($cart === null) {
            return response()->json(['selected_count' => 0, 'subtotal' => '0.00']);
        }

        $selectedIds = $cart->items()->whereKey($data['selected'])->pluck('id');

        $cart->items()->whereKey($selectedIds)->update(['selected' => true]);
        $cart->items()->whereKeyNot($selectedIds)->update(['selected' => false]);

        return response()->json([
            'selected_count' => $selectedIds->count(),
            'subtotal' => $this->subtotal($cart->load([
                'items.product:id,tenant_id,store_id,name,slug,price,stock_quantity,status',
                'items.variant',
            ])),
        ]);
    }

    public function destroySelected(Request $request): RedirectResponse
    {
        $cart = $request->user()->cart()->first();
        $removed = $cart?->items()->where('selected', true)->delete() ?? 0;

        return to_route('cart.index')->with(
            'status',
            $removed > 0
                ? $removed.' '.Str::plural('item', $removed).' removed.'
                : 'Select an item to remove first.',
        );
    }

    public function store(Request $request, AddProductToCart $addProductToCart, Product $product): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:100'], 'variant_id' => ['nullable', 'ulid']]);

        $addProductToCart->handle($request->user(), $product, $data['quantity'], $data['variant_id'] ?? null);

        if ($request->expectsJson()) {
            return response()->json(['cart_count' => $request->user()->cartItemCount()]);
        }

        return to_route('cart.index')->with('status', 'Added to your cart.');
    }

    public function update(Request $request, CartItem $cartItem): RedirectResponse
    {
        $this->authorize('update', $cartItem);

        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:100']]);

        $product = Product::query()
            ->with(['store', 'tenant'])
            ->findOrFail($cartItem->product_id);

        if ($request->user()->isMemberOfTenant($product->tenant_id)) {
            throw ValidationException::withMessages(['cart' => 'You cannot buy products from a store you manage.']);
        }

        $variant = $cartItem->product_variant_id !== null ? $product->variants()->find($cartItem->product_variant_id) : null;
        $availableStock = $variant?->stock_quantity ?? $product->stock_quantity;
        if (
            $product->status !== 'active'
            || $product->store->status !== 'active'
            || $product->tenant->status !== 'active'
            || ($cartItem->product_variant_id !== null && $variant === null)
            || ($cartItem->product_variant_id === null && $product->variants()->exists())
            || $availableStock < $data['quantity']
        ) {
            throw ValidationException::withMessages(['quantity' => 'This product is not currently available in that quantity.']);
        }

        $cartItem->update(['quantity' => $data['quantity']]);

        return to_route('cart.index')->with('status', 'Cart updated.');
    }

    public function destroy(Request $request, CartItem $cartItem): RedirectResponse
    {
        $this->authorize('delete', $cartItem);

        $removedCartItem = [
            'product_id' => $cartItem->product_id,
            'quantity' => $cartItem->quantity,
        ];
        if ($cartItem->product_variant_id !== null) {
            $removedCartItem['product_variant_id'] = $cartItem->product_variant_id;
        }

        $cartItem->delete();

        return to_route('cart.index')
            ->with('status', 'Item removed.')
            ->with('removed_cart_item', $removedCartItem);
    }

    public function restore(Request $request): RedirectResponse
    {
        $removedCartItem = $request->session()->get('removed_cart_item');

        if (! is_array($removedCartItem)) {
            return to_route('cart.index')->with('status', 'There is no removed item to restore.');
        }

        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);

        $product = Product::query()
            ->with(['store', 'tenant'])
            ->findOrFail($removedCartItem['product_id']);

        if ($request->user()->isMemberOfTenant($product->tenant_id)) {
            throw ValidationException::withMessages(['cart' => 'You cannot buy products from a store you manage.']);
        }

        $variantId = $removedCartItem['product_variant_id'] ?? null;
        $variant = $variantId !== null ? $product->variants()->find($variantId) : null;
        $availableStock = $variant?->stock_quantity ?? $product->stock_quantity;
        if (
            $product->status !== 'active'
            || $product->store->status !== 'active'
            || $product->tenant->status !== 'active'
            || ($variantId !== null && $variant === null)
            || ($variantId === null && $product->variants()->exists())
            || $availableStock < $removedCartItem['quantity']
        ) {
            throw ValidationException::withMessages(['cart' => 'This item is no longer available in that quantity.']);
        }

        $cartItem = $cart->items()->firstOrNew(['product_id' => $product->id, 'variant_key' => $variantId ?? 'default']);
        $cartItem->product_variant_id = $variantId;
        $cartItem->quantity = ($cartItem->exists ? $cartItem->quantity : 0) + $removedCartItem['quantity'];

        if ($cartItem->quantity > $availableStock) {
            throw ValidationException::withMessages(['cart' => 'This item is no longer available in that quantity.']);
        }

        $cartItem->save();

        $request->session()->forget('removed_cart_item');

        return to_route('cart.index')->with('status', 'Item restored to your cart.');
    }

    /**
     * An item that went out of stock while ticked is unticked, so the summary
     * and the checkout button always agree with what the buyer can see.
     */
    private function deselectUnavailableItems(?Cart $cart): void
    {
        $unavailableIds = $cart?->items
            ->filter(fn (CartItem $item): bool => $item->selected && ! $item->isAvailable())
            ->pluck('id');

        if ($unavailableIds === null || $unavailableIds->isEmpty()) {
            return;
        }

        $cart->items()->whereKey($unavailableIds)->update(['selected' => false]);
        $cart->items->whereIn('id', $unavailableIds)->each->setAttribute('selected', false);
    }

    private function subtotal(?Cart $cart): string
    {
        return $cart?->items
            ->where('selected', true)
            ->reduce(
                fn (string $total, CartItem $item): string => bcadd(
                    $total,
                    bcmul($item->unitPrice(), (string) $item->quantity, 2),
                    2,
                ),
                '0.00',
            ) ?? '0.00';
    }
}
