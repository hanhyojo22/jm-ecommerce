<?php

namespace App\Http\Controllers\Marketplace;

use App\Actions\CheckoutCart;
use App\Actions\NotifySellersOfNewOrder;
use App\Actions\SendOrderReceipt;
use App\Actions\StartOnlinePayment;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function create(Request $request): RedirectResponse|View
    {
        /*
         * Only the ticked items are bought here, so only they are worth loading products, variants
         * and images for. The count still covers the whole cart, which is what tells an empty cart
         * apart from one where nothing is ticked.
         */
        $cart = $request->user()->cart()
            ->withCount('items')
            ->with([
                'items' => fn ($query) => $query->where('selected', true),
                'items.cart.store',
                'items.product.store',
                'items.product.tenant',
                'items.product.thumbnail',
                'items.product.variants',
                'items.product.images' => fn ($query) => $query->whereNotNull('color'),
                'items.variant',
            ])
            ->first();

        if ($cart === null || $cart->items_count === 0) {
            return to_route('cart.index')->with('status', 'Your cart is empty. Add a product before checking out.');
        }

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

        $stores = $cart->items->pluck('product.store')->unique('id');
        // One online payment is split between the stores, so checkout still shows what each one gets.
        $storeTotals = $cart->items->groupBy(fn ($item) => $item->product->store_id)->map(fn ($storeItems) => [
            'name' => $storeItems->first()->product->store->name,
            'total' => $storeItems->reduce(fn (string $sum, $item): string => bcadd($sum, (string) $item->line_total, 2), '0.00'),
        ])->values();

        return view('marketplace.checkout.create', [
            'orderLimitReached' => $request->user()->hasReachedCodOrderLimit(),
            'storeTotals' => $storeTotals,
            // Online payment needs every store in the order to be connected to PayMongo.
            'onlineFees' => $stores->every(fn ($store): bool => $store->acceptsOnlinePayments())
                ? collect(Payment::ONLINE_METHODS)->mapWithKeys(fn (string $method): array => [$method => StartOnlinePayment::buyerFee($subtotal, $method)])->all()
                : [],
            'cart' => $cart,
            'subtotal' => $subtotal,
            'addresses' => $addresses,
            'preferredAddress' => $preferredAddress,
        ]);
    }

    public function store(Request $request, CheckoutCart $checkout, SendOrderReceipt $sendReceipt, NotifySellersOfNewOrder $notifySellers): RedirectResponse
    {
        $data = $request->validate([
            'fulfillment_method' => ['sometimes', Rule::in(['delivery', 'pickup'])],
            'recipient_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'regex:/^(?:\+63|0)9\d{9}$/'],
            'address_line' => ['required_unless:fulfillment_method,pickup', 'nullable', 'string', 'max:255'],
            'city' => ['required_unless:fulfillment_method,pickup', 'nullable', 'string', 'max:100'],
            'province' => ['required_unless:fulfillment_method,pickup', 'nullable', 'string', 'max:100'],
            'postal_code' => ['required_unless:fulfillment_method,pickup', 'nullable', 'regex:/^\d{4}$/'],
            'delivery_note' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['sometimes', Rule::in(['cod', ...Payment::ONLINE_METHODS])],
            'client_request_id' => ['nullable', 'string', 'max:100'],
        ]);
        $paymentMethod = $data['payment_method'] ?? 'cod';
        $fulfillmentMethod = $data['fulfillment_method'] ?? 'delivery';
        // shortcut: the web checkout has no map pin until Phase 7, so its deliveries skip the radius check.
        $order = $checkout->handle(
            $request->user(),
            Arr::only($data, ['recipient_name', 'phone', 'address_line', 'city', 'province', 'postal_code', 'delivery_note']),
            $paymentMethod,
            $data['client_request_id'] ?? null,
            $fulfillmentMethod,
        );
        $paymentNote = CheckoutCart::paymentNote($paymentMethod, $fulfillmentMethod);
        $buyer = $request->user();

        $sendReceipt->handle($buyer, $order, $paymentNote);
        $notifySellers->handle($order);

        return $paymentMethod === 'cod' ? to_route('orders.placed', $order) : to_route('orders.pay', $order);
    }
}
