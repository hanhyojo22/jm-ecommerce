<?php

namespace App\Http\Controllers\Marketplace;

use App\Actions\CheckoutCart;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function create(Request $request): RedirectResponse|View
    {
        $cart = $request->user()->cart()->with('items.product.store')->first();

        if ($cart === null || $cart->items->isEmpty()) {
            return to_route('cart.index')->with('status', 'Your cart is empty. Add a product before checking out.');
        }

        $cart->items->each(function ($item): void {
            $item->setAttribute('line_total', bcmul((string) $item->product->price, (string) $item->quantity, 2));
        });

        $subtotal = $cart->items->reduce(
            fn (string $total, $item): string => bcadd($total, (string) $item->line_total, 2),
            '0.00',
        );

        return view('marketplace.checkout.create', compact('cart', 'subtotal'));
    }

    public function store(Request $request, CheckoutCart $checkout): RedirectResponse
    {
        $data = $request->validate(['recipient_name' => ['required', 'string', 'max:150'], 'phone' => ['required', 'string', 'max:30'], 'address_line' => ['required', 'string', 'max:255'], 'city' => ['required', 'string', 'max:100']]);
        $order = $checkout->handle($request->user(), $data);

        return to_route('orders.show', $order);
    }
}
