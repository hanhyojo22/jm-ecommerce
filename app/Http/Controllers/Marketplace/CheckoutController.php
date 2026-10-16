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
        $cart = $request->user()->cart()->with(['items.product.store', 'items.product.tenant', 'items.product.thumbnail', 'items.product.variants', 'items.variant.images'])->first();

        if ($cart === null || $cart->items->isEmpty()) {
            return to_route('cart.index')->with('status', 'Your cart is empty. Add a product before checking out.');
        }

        $cart->setRelation('items', $cart->items->where('selected', true)->values());

        if ($cart->items->isEmpty()) {
            return to_route('cart.index')->with('status', 'Select an item to check out.');
        }

        if ($cart->items->contains(fn ($item): bool => ! $item->isAvailable() || $request->user()->isMemberOfTenant($item->product->tenant_id))) {
            return to_route('cart.index')->withErrors(['cart' => 'A product in your cart is unavailable. Update your cart before checking out.']);
        }

        $cart->items->each(function ($item): void {
            $item->setAttribute('line_total', bcmul($item->unitPrice(), (string) $item->quantity, 2));
        });

        $subtotal = $cart->items->reduce(
            fn (string $total, $item): string => bcadd($total, (string) $item->line_total, 2),
            '0.00',
        );
        $addresses = $request->user()->deliveryAddresses()->latest()->get();
        $preferredAddress = $addresses->first()
            ?? $request->user()->orders()->latest()->first(['recipient_name', 'phone', 'address_line', 'city', 'province', 'postal_code']);

        return view('marketplace.checkout.create', [
            'cart' => $cart,
            'subtotal' => $subtotal,
            'addresses' => $addresses,
            'preferredAddress' => $preferredAddress,
        ]);
    }

    public function store(Request $request, CheckoutCart $checkout, GmailOrderMailer $mailer): RedirectResponse
    {
        $data = $request->validate([
            'recipient_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'regex:/^(?:\+63|0)9\d{9}$/'],
            'address_line' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'regex:/^\d{4}$/'],
            'delivery_note' => ['nullable', 'string', 'max:1000'],
        ]);
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
