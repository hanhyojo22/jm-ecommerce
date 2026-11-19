<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CheckoutCart;
use App\Actions\NotifySellersOfNewOrder;
use App\Actions\SaveDiscountCard;
use App\Actions\SendOrderReceipt;
use App\Actions\StartOnlinePayment;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CartItemResource;
use App\Http\Resources\Api\V1\DeliveryAddressResource;
use App\Http\Resources\Api\V1\DiscountCardResource;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Payment;
use App\Support\SeniorPwdDiscount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class CheckoutController extends Controller
{
    /**
     * What the app needs to render the checkout screen before placing the order.
     */
    public function show(Request $request): JsonResponse
    {
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
            throw ValidationException::withMessages(['cart' => 'Your cart is empty. Add a product before checking out.']);
        }

        if ($cart->items->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Select an item to check out.']);
        }

        if ($cart->items->contains(fn ($item): bool => ! $item->isAvailable() || $request->user()->isMemberOfTenant($item->product->tenant_id))) {
            throw ValidationException::withMessages(['cart' => 'A product in your cart is unavailable. Update your cart before checking out.']);
        }

        $subtotal = $cart->items->reduce(
            fn (string $total, $item): string => bcadd($total, bcmul($item->unitPrice(), (string) $item->quantity, 2), 2),
            '0.00',
        );

        $branch = $cart->branchFor($cart->items->first()->product->tenant_id);
        $deliveryFee = $branch === null ? '0.00' : CheckoutCart::deliveryFee($branch->tenant, $subtotal);
        $deliveryTotal = bcadd($subtotal, $deliveryFee, 2);
        $storeTotals = collect([['name' => $branch?->name, 'total' => $subtotal]]);

        $addresses = $request->user()->deliveryAddresses()->latest()->get();
        $preferredAddress = $addresses->first()
            ?? $request->user()->orders()->latest()->first(['recipient_name', 'phone', 'address_line', 'city', 'province', 'postal_code']);

        $onlineAvailable = (bool) $branch?->acceptsOnlinePayments();
        $codBlocked = $request->user()->hasReachedCodOrderLimit();
        $paymentMethods = [
            'cod' => [
                'available' => ! $codBlocked,
                'fee' => '0.00',
                // The refusal limit is for deliveries; paying at the pickup counter stays open.
                'reason' => $codBlocked
                    ? 'Because cash-on-delivery parcels were refused recently, you can have one delivery in progress at a time. Pay online or pick up at the branch.'
                    : null,
            ],
        ];

        foreach (Payment::ONLINE_METHODS as $method) {
            $paymentMethods[$method] = [
                'available' => $onlineAvailable,
                // The fee is on what delivery would charge; fulfillment_methods.pickup.payment_fees has pickup's.
                'fee' => $onlineAvailable ? StartOnlinePayment::buyerFee($deliveryTotal, $method) : null,
                'reason' => $onlineAvailable ? null : 'Online payments are not available yet.',
            ];
        }

        $seniorPwdDiscount = SeniorPwdDiscount::preview($cart->items->map(fn ($item): array => ['product' => $item->product, 'total' => bcmul($item->unitPrice(), (string) $item->quantity, 2)]));
        $onlineFees = fn (string $total): array => $onlineAvailable
            ? collect(Payment::ONLINE_METHODS)->mapWithKeys(fn (string $method): array => [$method => StartOnlinePayment::buyerFee($total, $method)])->all()
            : [];

        return response()->json(['data' => [
            'items' => CartItemResource::collection($cart->items)->resolve($request),
            'subtotal' => $subtotal,
            // What a senior citizen / PWD card would take off; '0.00' when nothing is a medicine.
            'senior_pwd_discount' => $seniorPwdDiscount,
            'branch' => $branch === null ? null : [
                'id' => $branch->id,
                'name' => $branch->name,
                'address_line' => $branch->address_line,
                'city' => $branch->city,
                'delivery_radius_km' => $branch->delivery_radius_km,
            ],
            // Pickup is free; delivery adds head office's flat fee unless the items reach the free-delivery amount.
            'fulfillment_methods' => [
                // Set while the branch is closed: delivery is refused until then, pickup is not.
                // payment_fees_with_card: the same fees on the total after the SC/PWD discount.
                'delivery' => ['fee' => $deliveryFee, 'total' => $deliveryTotal, 'payment_fees' => $onlineFees($deliveryTotal), 'payment_fees_with_card' => $onlineFees(bcsub($deliveryTotal, $seniorPwdDiscount, 2)), 'closed_until' => $branch?->nextOpeningAt(now())?->toIso8601String()],
                'pickup' => ['fee' => '0.00', 'total' => $subtotal, 'payment_fees' => $onlineFees($subtotal), 'payment_fees_with_card' => $onlineFees(bcsub($subtotal, $seniorPwdDiscount, 2))],
            ],
            'store_totals' => $storeTotals,
            'addresses' => DeliveryAddressResource::collection($addresses)->resolve($request),
            // Saved SC/PWD IDs the customer can pick instead of typing; masked numbers only.
            'discount_cards' => DiscountCardResource::collection($request->user()->discountCards()->latest()->get())->resolve($request),
            'preferred_address' => $preferredAddress === null ? null : [
                'recipient_name' => $preferredAddress->recipient_name,
                'phone' => $preferredAddress->phone,
                'address_line' => $preferredAddress->address_line,
                'city' => $preferredAddress->city,
                'province' => $preferredAddress->province,
                'postal_code' => $preferredAddress->postal_code,
            ],
            'payment_methods' => $paymentMethods,
        ]]);
    }

    public function store(
        Request $request,
        CheckoutCart $checkout,
        StartOnlinePayment $startOnlinePayment,
        SendOrderReceipt $sendReceipt,
        NotifySellersOfNewOrder $notifySellers,
        SaveDiscountCard $saveDiscountCard,
    ): JsonResponse {
        $data = $request->validate([
            'fulfillment_method' => ['sometimes', Rule::in(['delivery', 'pickup'])],
            'recipient_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'regex:/^(?:\+63|0)9\d{9}$/'],
            'address_line' => ['required_unless:fulfillment_method,pickup', 'nullable', 'string', 'max:255'],
            'city' => ['required_unless:fulfillment_method,pickup', 'nullable', 'string', 'max:100'],
            // The map pin locates a same-town delivery, so province and postal code are optional.
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'regex:/^\d{4}$/'],
            'delivery_latitude' => ['required_unless:fulfillment_method,pickup', 'nullable', 'numeric', 'between:-90,90'],
            'delivery_longitude' => ['required_unless:fulfillment_method,pickup', 'nullable', 'numeric', 'between:-180,180'],
            'delivery_note' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['sometimes', Rule::in(['cod', ...Payment::ONLINE_METHODS])],
            'client_request_id' => ['nullable', 'string', 'max:100'],
            'success_url' => ['nullable', 'url:http,https'],
            'cancel_url' => ['nullable', 'url:http,https'],
            'prescription' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
            'prescriptions' => ['nullable', 'array', 'max:10'],
            'prescriptions.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
            // A saved ID replaces the typed fields; saving applies only to a typed card.
            'discount_card_id' => ['nullable', 'string'],
            'save_discount_card' => ['nullable', 'boolean'],
            'discount_document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
            ...SeniorPwdDiscount::rules(),
        ]);

        $paymentMethod = $data['payment_method'] ?? 'cod';
        $fulfillmentMethod = $data['fulfillment_method'] ?? 'delivery';
        $prescriptions = array_values(array_filter([
            ...Arr::wrap($request->file('prescriptions')),
            ...Arr::wrap($request->file('prescription')),
        ]));
        // A saved ID wins over typed fields; scoping it to the customer makes anyone else's ID a 404.
        $savedCard = filled($data['discount_card_id'] ?? null) ? $request->user()->discountCards()->findOrFail($data['discount_card_id']) : null;
        $discountCard = $savedCard?->toCard() ?? SeniorPwdDiscount::cardFrom($data);
        $discountDocument = $request->file('discount_document');
        $order = $checkout->handle(
            $request->user(),
            Arr::only($data, ['recipient_name', 'phone', 'address_line', 'city', 'province', 'postal_code', 'delivery_note']),
            $paymentMethod,
            $data['client_request_id'] ?? null,
            $fulfillmentMethod,
            $fulfillmentMethod === 'delivery' ? ['latitude' => (float) $data['delivery_latitude'], 'longitude' => (float) $data['delivery_longitude']] : null,
            $prescriptions,
            $discountCard,
            $discountDocument,
        );

        // A failed save must not turn a placed order into an error: the discount already applies.
        try {
            if ($savedCard !== null && $discountDocument !== null) {
                $saveDiscountCard->replaceDocument($savedCard, $discountDocument);
            } elseif ($savedCard === null && $discountCard !== null && $discountDocument !== null && ($data['save_discount_card'] ?? false)) {
                $saveDiscountCard->handle($request->user(), $discountCard, $discountDocument);
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        $sendReceipt->handle($request->user(), $order, CheckoutCart::paymentNote($paymentMethod, $fulfillmentMethod));
        $notifySellers->handle($order);

        $paymentUrl = null;
        $paymentError = null;

        if ($paymentMethod !== 'cod') {
            try {
                $paymentUrl = $startOnlinePayment->handle(
                    $order,
                    $data['success_url'] ?? null,
                    $data['cancel_url'] ?? null,
                );
            } catch (ValidationException $exception) {
                $paymentError = $exception->getMessage();
            } catch (Throwable $exception) {
                report($exception);
                $paymentError = 'We couldn’t open the payment page. Please try again in a few minutes.';
            }
        }

        $order->load('sellerOrders.store', 'sellerOrders.items', 'payment');

        return response()->json(['data' => [
            ...OrderResource::make($order)->resolve($request),
            'payment_url' => $paymentUrl,
            'payment_error' => $paymentError,
        ]], $order->wasRecentlyCreated ? 201 : 200);
    }
}
