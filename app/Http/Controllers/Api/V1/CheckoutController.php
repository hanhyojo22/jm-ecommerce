<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CheckoutCart;
use App\Actions\SendOrderReceipt;
use App\Actions\StartOnlinePayment;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CartItemResource;
use App\Http\Resources\Api\V1\DeliveryAddressResource;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Payment;
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

        $stores = $cart->items->pluck('product.store')->unique('id');
        $storeTotals = $cart->items->groupBy(fn ($item) => $item->product->store_id)->map(fn ($storeItems): array => [
            'name' => $storeItems->first()->product->store->name,
            'total' => $storeItems->reduce(fn (string $sum, $item): string => bcadd($sum, bcmul($item->unitPrice(), (string) $item->quantity, 2), 2), '0.00'),
        ])->values();

        $addresses = $request->user()->deliveryAddresses()->latest()->get();
        $preferredAddress = $addresses->first()
            ?? $request->user()->orders()->latest()->first(['recipient_name', 'phone', 'address_line', 'city', 'province', 'postal_code']);

        $onlineAvailable = $stores->every(fn ($store): bool => $store->acceptsOnlinePayments());
        $codBlocked = $request->user()->hasReachedCodOrderLimit();
        $paymentMethods = [
            'cod' => [
                'available' => ! $codBlocked,
                'fee' => '0.00',
                'reason' => $codBlocked
                    ? 'Because cash-on-delivery parcels were refused recently, you can have one order in progress at a time.'
                    : null,
            ],
        ];

        foreach (Payment::ONLINE_METHODS as $method) {
            $paymentMethods[$method] = [
                'available' => $onlineAvailable,
                'fee' => $onlineAvailable ? StartOnlinePayment::buyerFee($subtotal, $method) : null,
                'reason' => $onlineAvailable ? null : 'One of the stores in your cart does not take online payments yet.',
            ];
        }

        return response()->json(['data' => [
            'items' => CartItemResource::collection($cart->items)->resolve($request),
            'subtotal' => $subtotal,
            'store_totals' => $storeTotals,
            'addresses' => DeliveryAddressResource::collection($addresses)->resolve($request),
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
    ): JsonResponse {
        $data = $request->validate([
            'recipient_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'regex:/^(?:\+63|0)9\d{9}$/'],
            'address_line' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'regex:/^\d{4}$/'],
            'delivery_note' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['sometimes', Rule::in(['cod', ...Payment::ONLINE_METHODS])],
            'client_request_id' => ['nullable', 'string', 'max:100'],
            'success_url' => ['nullable', 'url:http,https'],
            'cancel_url' => ['nullable', 'url:http,https'],
        ]);

        $paymentMethod = $data['payment_method'] ?? 'cod';
        $order = $checkout->handle(
            $request->user(),
            Arr::except($data, ['payment_method', 'client_request_id', 'success_url', 'cancel_url']),
            $paymentMethod,
            $data['client_request_id'] ?? null,
        );

        $sendReceipt->handle($request->user(), $order, $paymentMethod === 'cod'
            ? 'payable on delivery. Each store confirms its delivery timing.'
            : 'to be paid by '.Payment::METHOD_LABELS[$paymentMethod].'. If you left the payment page, pay from your order page so the stores can confirm and ship.');

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
