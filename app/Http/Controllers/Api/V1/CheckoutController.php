<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CheckoutCart;
use App\Actions\NotifySellersOfNewOrder;
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

        $onlineFees = fn (string $total): array => $onlineAvailable
            ? collect(Payment::ONLINE_METHODS)->mapWithKeys(fn (string $method): array => [$method => StartOnlinePayment::buyerFee($total, $method)])->all()
            : [];

        return response()->json(['data' => [
            'items' => CartItemResource::collection($cart->items)->resolve($request),
            'subtotal' => $subtotal,
            'branch' => $branch === null ? null : [
                'id' => $branch->id,
                'name' => $branch->name,
                'address_line' => $branch->address_line,
                'city' => $branch->city,
                'delivery_radius_km' => $branch->delivery_radius_km,
            ],
            // Pickup is free; delivery adds head office's flat fee unless the items reach the free-delivery amount.
            'fulfillment_methods' => [
                'delivery' => ['fee' => $deliveryFee, 'total' => $deliveryTotal, 'payment_fees' => $onlineFees($deliveryTotal)],
                'pickup' => ['fee' => '0.00', 'total' => $subtotal, 'payment_fees' => $onlineFees($subtotal)],
            ],
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
        NotifySellersOfNewOrder $notifySellers,
    ): JsonResponse {
        $data = $request->validate([
            'fulfillment_method' => ['sometimes', Rule::in(['delivery', 'pickup'])],
            'recipient_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'regex:/^(?:\+63|0)9\d{9}$/'],
            'address_line' => ['required_unless:fulfillment_method,pickup', 'nullable', 'string', 'max:255'],
            'city' => ['required_unless:fulfillment_method,pickup', 'nullable', 'string', 'max:100'],
            'province' => ['required_unless:fulfillment_method,pickup', 'nullable', 'string', 'max:100'],
            'postal_code' => ['required_unless:fulfillment_method,pickup', 'nullable', 'regex:/^\d{4}$/'],
            'delivery_latitude' => ['required_unless:fulfillment_method,pickup', 'nullable', 'numeric', 'between:-90,90'],
            'delivery_longitude' => ['required_unless:fulfillment_method,pickup', 'nullable', 'numeric', 'between:-180,180'],
            'delivery_note' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['sometimes', Rule::in(['cod', ...Payment::ONLINE_METHODS])],
            'client_request_id' => ['nullable', 'string', 'max:100'],
            'success_url' => ['nullable', 'url:http,https'],
            'cancel_url' => ['nullable', 'url:http,https'],
            'prescription' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ]);

        $paymentMethod = $data['payment_method'] ?? 'cod';
        $fulfillmentMethod = $data['fulfillment_method'] ?? 'delivery';
        $order = $checkout->handle(
            $request->user(),
            Arr::only($data, ['recipient_name', 'phone', 'address_line', 'city', 'province', 'postal_code', 'delivery_note']),
            $paymentMethod,
            $data['client_request_id'] ?? null,
            $fulfillmentMethod,
            $fulfillmentMethod === 'delivery' ? ['latitude' => (float) $data['delivery_latitude'], 'longitude' => (float) $data['delivery_longitude']] : null,
            $request->file('prescription'),
        );

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
