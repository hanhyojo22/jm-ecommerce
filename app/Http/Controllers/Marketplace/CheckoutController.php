<?php

namespace App\Http\Controllers\Marketplace;

use App\Actions\CheckoutCart;
use App\Actions\NotifySellersOfNewOrder;
use App\Actions\SaveDiscountCard;
use App\Actions\SendOrderReceipt;
use App\Actions\StartOnlinePayment;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Support\SeniorPwdDiscount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

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

        // Totals are worked out for delivery; the page drops the fee when the shopper picks pickup.
        $branch = $cart->branchFor($cart->items->first()->product->tenant_id);
        $deliveryFee = $branch === null ? '0.00' : CheckoutCart::deliveryFee($branch->tenant, $subtotal);
        $total = bcadd($subtotal, $deliveryFee, 2);
        $storeTotals = collect([['name' => $branch?->name, 'total' => $subtotal]]);
        $seniorPwdDiscount = SeniorPwdDiscount::preview($cart->items->map(fn ($item): array => ['product' => $item->product, 'total' => (string) $item->line_total]));
        // What each online method charges for every way the order can end up: delivery or pickup, with or without a card.
        $itemsTotals = ['delivery' => $total, 'pickup' => $subtotal, 'deliveryCard' => bcsub($total, $seniorPwdDiscount, 2), 'pickupCard' => bcsub($subtotal, $seniorPwdDiscount, 2)];

        return view('marketplace.checkout.create', [
            'orderLimitReached' => $request->user()->hasReachedCodOrderLimit(),
            'storeTotals' => $storeTotals,
            'onlineFees' => $branch?->acceptsOnlinePayments()
                ? collect(Payment::ONLINE_METHODS)->mapWithKeys(fn (string $method): array => [$method => StartOnlinePayment::buyerFee($total, $method)])->all()
                : [],
            'onlineQuotes' => $branch?->acceptsOnlinePayments()
                ? collect($itemsTotals)->map(fn (string $items): array => ['items' => '₱'.number_format((float) $items, 2)] + collect(Payment::ONLINE_METHODS)->mapWithKeys(fn (string $method): array => [$method => [
                    'fee' => '₱'.number_format((float) StartOnlinePayment::buyerFee($items, $method), 2),
                    'pay' => '₱'.number_format((float) bcadd($items, StartOnlinePayment::buyerFee($items, $method), 2), 2),
                ]])->all())->all()
                : [],
            'cart' => $cart,
            'subtotal' => $subtotal,
            'seniorPwdDiscount' => $seniorPwdDiscount,
            'deliveryFee' => $deliveryFee,
            'total' => $total,
            'addresses' => $addresses,
            'preferredAddress' => $preferredAddress,
            'discountCards' => $request->user()->discountCards()->latest()->get(),
            'branchName' => $branch?->name ?? 'your branch',
            'branchAddress' => collect([$branch?->address_line, $branch?->city])->filter()->join(', '),
            'closedUntil' => $branch?->nextOpeningAt(now()),
        ]);
    }

    public function store(Request $request, CheckoutCart $checkout, SendOrderReceipt $sendReceipt, NotifySellersOfNewOrder $notifySellers, SaveDiscountCard $saveDiscountCard): RedirectResponse
    {
        $data = $request->validate([
            'fulfillment_method' => ['sometimes', Rule::in(['delivery', 'pickup'])],
            'recipient_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'regex:/^(?:\+63|0)9\d{9}$/'],
            'address_line' => ['required_unless:fulfillment_method,pickup', 'nullable', 'string', 'max:255'],
            'city' => ['required_unless:fulfillment_method,pickup', 'nullable', 'string', 'max:100'],
            // Same-town delivery needs the street and city only, as in the app.
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'regex:/^\d{4}$/'],
            'delivery_note' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['sometimes', Rule::in(['cod', ...Payment::ONLINE_METHODS])],
            'client_request_id' => ['nullable', 'string', 'max:100'],
            'prescription' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
            'prescriptions' => ['nullable', 'array', 'max:10'],
            'prescriptions.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
            // A saved ID replaces the typed fields; saving applies only to a typed card.
            'discount_card_id' => ['nullable', 'string'],
            'save_discount_card' => ['nullable', 'boolean'],
            ...SeniorPwdDiscount::rules(),
        ]);
        $paymentMethod = $data['payment_method'] ?? 'cod';
        $fulfillmentMethod = $data['fulfillment_method'] ?? 'delivery';
        $prescriptions = array_values(array_filter([
            ...Arr::wrap($request->file('prescriptions')),
            ...Arr::wrap($request->file('prescription')),
        ]));
        $discountCard = $this->discountCard($request, $data);
        // shortcut: the web checkout has no map pin until Phase 7, so its deliveries skip the radius check.
        $order = $checkout->handle(
            $request->user(),
            Arr::only($data, ['recipient_name', 'phone', 'address_line', 'city', 'province', 'postal_code', 'delivery_note']),
            $paymentMethod,
            $data['client_request_id'] ?? null,
            $fulfillmentMethod,
            prescriptions: $prescriptions,
            discountCard: $discountCard,
        );

        // A failed save must not turn a placed order into an error: the discount already applies.
        if (($data['save_discount_card'] ?? false) && blank($data['discount_card_id'] ?? null) && $discountCard !== null) {
            try {
                $saveDiscountCard->handle($request->user(), $discountCard);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
        $paymentNote = CheckoutCart::paymentNote($paymentMethod, $fulfillmentMethod);
        $buyer = $request->user();

        $sendReceipt->handle($buyer, $order, $paymentNote);
        $notifySellers->handle($order);

        return $paymentMethod === 'cod' ? to_route('orders.placed', $order) : to_route('orders.pay', $order);
    }

    /**
     * A saved ID wins over typed fields. Scoping the lookup to the customer makes
     * anyone else's ID a 404, and ignores a tampered type or number from the client.
     *
     * @param  array<string, mixed>  $data
     * @return ?array{type: string, id_number: string, name: string}
     */
    private function discountCard(Request $request, array $data): ?array
    {
        if (filled($data['discount_card_id'] ?? null)) {
            return $request->user()->discountCards()->findOrFail($data['discount_card_id'])->toCard();
        }

        return SeniorPwdDiscount::cardFrom($data);
    }
}
