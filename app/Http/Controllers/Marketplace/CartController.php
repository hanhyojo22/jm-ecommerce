<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(Request $request): View
    {
        $cart = $request->user()->cart()->with([
            'items.product:id,store_id,name,slug,price,stock_quantity,status',
            'items.product.store:id,name,slug',
            'items.product.thumbnail',
        ])->first();

        $cart?->items->each(function (CartItem $item): void {
            $item->setAttribute('line_total', bcmul((string) $item->product->price, (string) $item->quantity, 2));
        });

        return view('marketplace.cart.index', [
            'cart' => $cart,
            'subtotal' => $this->subtotal($cart),
        ]);
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:100']]);
        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);

        $product = Product::query()->with(['store', 'tenant'])->findOrFail($product->id);
        if ($product->status !== 'active' || $product->store->status !== 'active' || $product->tenant->status !== 'active' || $product->stock_quantity < $data['quantity']) {
            throw ValidationException::withMessages(['quantity' => 'This product is not currently available in that quantity.']);
        }

        $item = $cart->items()->firstOrNew(['product_id' => $product->id]);
        $item->quantity = min($product->stock_quantity, ($item->exists ? $item->quantity : 0) + $data['quantity']);
        $item->save();

        return to_route('cart.index')->with('status', 'Added to your cart.');
    }

    public function update(Request $request, CartItem $cartItem): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:100']]);

        DB::transaction(function () use ($cartItem, $data, $request): void {
            $lockedCartItem = CartItem::query()
                ->whereKey($cartItem->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($lockedCartItem->cart()->where('user_id', $request->user()->id)->exists(), 403);

            $product = Product::query()
                ->with(['store', 'tenant'])
                ->lockForUpdate()
                ->findOrFail($lockedCartItem->product_id);

            if (
                $product->status !== 'active'
                || $product->store->status !== 'active'
                || $product->tenant->status !== 'active'
                || $product->stock_quantity < $data['quantity']
            ) {
                throw ValidationException::withMessages(['quantity' => 'This product is not currently available in that quantity.']);
            }

            $lockedCartItem->update(['quantity' => $data['quantity']]);
        });

        return to_route('cart.index')->with('status', 'Cart updated.');
    }

    public function destroy(Request $request, CartItem $cartItem): RedirectResponse
    {
        abort_unless($cartItem->cart->user_id === $request->user()->id, 403);

        $removedCartItem = [
            'product_id' => $cartItem->product_id,
            'quantity' => $cartItem->quantity,
        ];

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

        if (
            $product->status !== 'active'
            || $product->store->status !== 'active'
            || $product->tenant->status !== 'active'
            || $product->stock_quantity < $removedCartItem['quantity']
        ) {
            throw ValidationException::withMessages(['cart' => 'This item is no longer available in that quantity.']);
        }

        $cartItem = $cart->items()->firstOrNew(['product_id' => $product->id]);
        $cartItem->quantity = ($cartItem->exists ? $cartItem->quantity : 0) + $removedCartItem['quantity'];

        if ($cartItem->quantity > $product->stock_quantity) {
            throw ValidationException::withMessages(['cart' => 'This item is no longer available in that quantity.']);
        }

        $cartItem->save();

        $request->session()->forget('removed_cart_item');

        return to_route('cart.index')->with('status', 'Item restored to your cart.');
    }

    private function subtotal(?Cart $cart): string
    {
        return $cart?->items->reduce(
            fn (string $total, CartItem $item): string => bcadd(
                $total,
                bcmul((string) $item->product->price, (string) $item->quantity, 2),
                2,
            ),
            '0.00',
        ) ?? '0.00';
    }
}
