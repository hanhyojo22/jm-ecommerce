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

    public function store(Request $request, AddProductToCart $addProductToCart, Product $product): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:100']]);

        $addProductToCart->handle($request->user(), $product, $data['quantity']);

        if ($request->expectsJson()) {
            return response()->json(['cart_count' => $request->user()->cartItemCount()]);
        }

        return to_route('cart.index')->with('status', 'Added to your cart.');
    }

    public function update(Request $request, CartItem $cartItem): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:100']]);

        abort_unless($cartItem->cart()->where('user_id', $request->user()->id)->exists(), 403);

        $product = Product::query()
            ->with(['store', 'tenant'])
            ->findOrFail($cartItem->product_id);

        if (
            $product->status !== 'active'
            || $product->store->status !== 'active'
            || $product->tenant->status !== 'active'
            || $product->stock_quantity < $data['quantity']
        ) {
            throw ValidationException::withMessages(['quantity' => 'This product is not currently available in that quantity.']);
        }

        $cartItem->update(['quantity' => $data['quantity']]);

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
