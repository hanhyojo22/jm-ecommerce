<?php

namespace App\Http\Controllers\Marketplace;

use App\Actions\CheckoutCart;
use App\Http\Controllers\Controller;
use App\Services\GmailOrderMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function create(Request $request): RedirectResponse|View
    {
        $cart = $request->user()->cart()->with(['items.product.store', 'items.product.images', 'items.variant'])->first();

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

        return view('marketplace.checkout.create', [
            'cart' => $cart,
            'subtotal' => $subtotal,
            'addresses' => $request->user()->deliveryAddresses()->latest()->get(),
        ]);
    }

    public function store(Request $request, CheckoutCart $checkout, GmailOrderMailer $mailer): RedirectResponse
    {
        $data = $request->validate(['recipient_name' => ['required', 'string', 'max:150'], 'phone' => ['required', 'string', 'regex:/^(?:\+63|0)9\d{9}$/'], 'address_line' => ['required', 'string', 'max:255'], 'city' => ['required', 'string', 'max:100']]);
        $order = $checkout->handle($request->user(), $data);

        try {
            $mailer->send(
                $request->user(),
                "JMMart order {$order->number} received",
                "Thanks for your order!\n\nOrder #{$order->number} was placed for PHP {$order->total}, payable on delivery. Each store confirms its delivery timing.\n\nTrack your order or request help: ".route('orders.show', $order),
            );
        } catch (\Throwable $exception) {
            report($exception);
        }

        return to_route('orders.show', $order)->with('order_placed', true);
    }
}
