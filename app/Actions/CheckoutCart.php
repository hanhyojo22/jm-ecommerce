<?php

namespace App\Actions;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Distance;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutCart
{
    public function __construct(private BranchInventory $inventory) {}

    /**
     * @param  array{recipient_name: string, phone: string, address_line: string, city: string, province: string, postal_code: string, delivery_note?: ?string}  $delivery
     * @param  'cod'|'gcash'|'maya'|'card'  $paymentMethod  an online method is paid through one PayMongo checkout opened right after this
     * @param  ?string  $clientRequestId  a caller-generated key that makes a retried checkout return the original order
     * @param  'delivery'|'pickup'  $fulfillmentMethod  pickup is collected at the branch: no fee, and 'cod' means pay at the counter
     * @param  ?array{latitude: float, longitude: float}  $deliveryPin  where the customer dropped the delivery pin; checked against the branch's radius
     * @param  ?UploadedFile  $prescription  required when a medicine needs one; a branch pharmacist reviews it before the order moves past pending
     */
    public function handle(User $user, array $delivery, string $paymentMethod = 'cod', ?string $clientRequestId = null, string $fulfillmentMethod = 'delivery', ?array $deliveryPin = null, ?UploadedFile $prescription = null): Order
    {
        abort_unless($user->is_active && in_array($user->account_type, ['buyer', 'seller'], true) && $user->hasVerifiedClerkEmail(), 403);

        $clientRequestId = $clientRequestId !== null ? trim($clientRequestId) : null;

        if ($clientRequestId === '') {
            $clientRequestId = null;
        }

        $fingerprint = $clientRequestId === null ? null : $this->fingerprint($delivery, $paymentMethod, $fulfillmentMethod, $deliveryPin, Cart::query()->where('user_id', $user->id)->value('store_id'));

        if ($clientRequestId !== null) {
            $existing = $this->existingOrder($user, $clientRequestId);

            if ($existing !== null) {
                return $this->replay($existing, $fingerprint);
            }
        }

        try {
            return DB::transaction(function () use ($user, $delivery, $paymentMethod, $clientRequestId, $fingerprint, $fulfillmentMethod, $deliveryPin, $prescription): Order {
                $cart = Cart::query()
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if ($cart === null) {
                    throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
                }

                // The cart lock above serializes this buyer's checkouts, so two tabs cannot both slip past.
                // A prepaid order cannot be refused at the door, so the limit is for COD only.
                if ($paymentMethod === 'cod' && $fulfillmentMethod === 'delivery' && $user->hasReachedCodOrderLimit()) {
                    throw ValidationException::withMessages(['cart' => 'Because cash-on-delivery parcels were refused recently, you can have one order in progress at a time. You can place this order once your current one is delivered.']);
                }

                $items = $cart->items()
                    ->where('selected', true)
                    ->orderBy('product_id')
                    ->orderBy('variant_key')
                    ->lockForUpdate()
                    ->get();

                if ($items->isEmpty()) {
                    throw ValidationException::withMessages(['cart' => 'Select an item to check out.']);
                }

                // Lock the branch so it cannot close part-way through this checkout.
                $branchId = $cart->branchFor((string) Product::query()->whereKey($items->first()->product_id)->value('tenant_id'))?->id;
                $branch = $branchId === null ? null : Store::query()->whereKey($branchId)->lockForUpdate()->first();

                if ($branch === null || $branch->status !== 'active') {
                    throw ValidationException::withMessages(['cart' => 'Your branch is closed. Choose another branch before checking out.']);
                }

                $opensAt = $branch->nextOpeningAt(now());
                if ($fulfillmentMethod === 'delivery' && $opensAt !== null) {
                    throw ValidationException::withMessages(['cart' => "{$branch->name} is closed now and opens at {$opensAt->format('g:i A')}. Choose pickup, or order for delivery then."]);
                }

                if ($fulfillmentMethod === 'delivery' && $deliveryPin !== null && $branch->delivery_radius_km !== null && $branch->latitude !== null) {
                    $distance = Distance::km($deliveryPin['latitude'], $deliveryPin['longitude'], $branch->latitude, $branch->longitude);
                    if ($distance > $branch->delivery_radius_km) {
                        throw ValidationException::withMessages(['delivery_latitude' => "{$branch->name} delivers within {$branch->delivery_radius_km} km; this address is ".round($distance, 1).' km away. Choose pickup or another branch.']);
                    }
                }

                // A pickup order is collected at the branch, so the branch address is what goes on it.
                if ($fulfillmentMethod === 'pickup') {
                    $delivery = [...$delivery, 'address_line' => $branch->address_line, 'city' => $branch->city, 'province' => $branch->province, 'postal_code' => $branch->postal_code];
                }

                $products = Product::query()
                    ->with(['store', 'tenant'])
                    ->whereIn('id', $items->pluck('product_id'))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $prescriptionMedicines = $products->where('requires_prescription', true);
                if ($prescriptionMedicines->isNotEmpty() && $prescription === null) {
                    throw ValidationException::withMessages(['prescription' => 'Upload a photo of your prescription for '.$prescriptionMedicines->pluck('name')->join(', ', ' and ').'.']);
                }

                $managedTenantIds = $user->tenantMemberships()->whereIn('role', ['owner', 'manager', 'staff'])->pluck('tenant_id')->all();

                $lines = $items->map(function ($item) use ($products, $managedTenantIds, $paymentMethod, $branch): array {
                    $product = $products->get($item->product_id);

                    $variant = $product !== null && $item->product_variant_id !== null
                        ? $product->variants()->lockForUpdate()->find($item->product_variant_id)
                        : null;

                    if ($product !== null && in_array($product->tenant_id, $managedTenantIds, true)) {
                        throw ValidationException::withMessages(['cart' => 'Remove products from stores you manage before checking out.']);
                    }

                    if (
                        $product === null
                        || $product->status !== 'active'
                        || $product->tenant->status !== 'active'
                        || $product->tenant_id !== $branch->tenant_id
                        || ($item->product_variant_id !== null && $variant === null)
                        || ($item->product_variant_id === null && $product->variants()->exists())
                    ) {
                        throw ValidationException::withMessages(['cart' => 'A product in your cart is unavailable.']);
                    }

                    if ($paymentMethod !== 'cod' && ! $branch->acceptsOnlinePayments()) {
                        throw ValidationException::withMessages(['payment_method' => 'Online payments are not available yet. Choose cash on delivery.']);
                    }

                    $this->inventory->take($branch->id, $product, $variant, $item->quantity);

                    $unitPrice = (string) ($variant?->price ?? $product->price);

                    return [
                        'product' => $product,
                        'variant' => $variant,
                        'quantity' => $item->quantity,
                        'unit_price' => $unitPrice,
                        'image_path' => (filled($variant?->color) ? $product->images()->where('color', $variant->color)->value('path') : null) ?? $product->images()->value('path'),
                        'total' => bcmul($unitPrice, (string) $item->quantity, 2),
                    ];
                });

                $total = $this->sumTotals($lines->pluck('total')->all());

                if (bccomp($total, '9999999999.99', 2) > 0) {
                    throw ValidationException::withMessages([
                        'cart' => 'Your cart total exceeds the maximum order amount.',
                    ]);
                }

                $shipping = $fulfillmentMethod === 'pickup' ? '0.00' : self::deliveryFee($branch->tenant, $total);
                $grandTotal = bcadd($total, $shipping, 2);

                $order = Order::query()->create([
                    ...$delivery,
                    'user_id' => $user->id,
                    'number' => 'JM-'.Str::upper((string) Str::ulid()),
                    'status' => 'pending',
                    'client_request_id' => $clientRequestId,
                    'client_request_hash' => $fingerprint,
                    'fulfillment_method' => $fulfillmentMethod,
                    'delivery_latitude' => $fulfillmentMethod === 'delivery' ? $deliveryPin['latitude'] ?? null : null,
                    'delivery_longitude' => $fulfillmentMethod === 'delivery' ? $deliveryPin['longitude'] ?? null : null,
                    'subtotal' => $total,
                    'shipping_amount' => $shipping,
                    'total' => $grandTotal,
                ]);
                if ($fulfillmentMethod === 'delivery') {
                    $user->deliveryAddresses()->firstOrCreate(collect($delivery)->except('delivery_note')->all());
                }

                // One branch fills the whole order.
                $lines->groupBy(fn (array $line): string => $branch->id)
                    ->each(function ($storeLines) use ($order, $branch, $shipping): void {
                        $subtotal = $this->sumTotals($storeLines->pluck('total')->all());

                        $sellerOrder = $order->sellerOrders()->create([
                            'tenant_id' => $branch->tenant_id,
                            'store_id' => $branch->id,
                            'status' => 'pending',
                            'subtotal' => $subtotal,
                            'shipping_amount' => $shipping,
                            'total' => bcadd($subtotal, $shipping, 2),
                        ]);

                        foreach ($storeLines as $line) {
                            $sellerOrder->items()->create([
                                'product_id' => $line['product']->id,
                                'product_variant_id' => $line['variant']?->id,
                                'product_name' => $line['product']->name,
                                'sku' => $line['variant']?->sku ?? $line['product']->sku,
                                'variant_label' => $line['variant']?->label(),
                                'unit_price' => $line['unit_price'],
                                'image_path' => $line['image_path'],
                                'quantity' => $line['quantity'],
                                'line_total' => $line['total'],
                            ]);
                        }
                    });

                $order->payment()->create([
                    'method' => $paymentMethod,
                    'status' => 'pending',
                    'amount' => $grandTotal,
                    'fee_amount' => $paymentMethod === 'cod' ? '0.00' : StartOnlinePayment::buyerFee($grandTotal, $paymentMethod),
                ]);
                if ($prescriptionMedicines->isNotEmpty()) {
                    // shortcut: a checkout that fails after this line leaves the file behind, as return evidence does; sweep orphans if storage cost matters.
                    $path = $prescription->store('prescriptions/'.$order->id, 'chat_attachments');
                    if ($path === false) {
                        throw ValidationException::withMessages(['prescription' => 'Your prescription could not be saved. Try again.']);
                    }
                    $order->prescription()->create(['user_id' => $user->id, 'path' => $path, 'original_name' => $prescription->getClientOriginalName()]);
                }
                $cart->items()->whereKey($items->modelKeys())->delete();
                $cart->touch();

                return $order;
            });
        } catch (UniqueConstraintViolationException $exception) {
            /*
             * Two first attempts with the same key can pass the lookup above together. The
             * unique index lets one win; the loser replays the winner's order instead of
             * surfacing a 500 or charging the buyer twice.
             */
            if ($clientRequestId === null) {
                throw $exception;
            }

            $existing = $this->existingOrder($user, $clientRequestId);

            if ($existing === null) {
                throw $exception;
            }

            return $this->replay($existing, $fingerprint);
        }
    }

    /**
     * How the receipt says the order will be paid.
     */
    public static function paymentNote(string $paymentMethod, string $fulfillmentMethod): string
    {
        return match (true) {
            $paymentMethod === 'cod' && $fulfillmentMethod === 'pickup' => 'payable at the branch when you collect it. We will tell you when it is ready for pickup.',
            $paymentMethod === 'cod' => 'payable on delivery. The branch confirms its delivery timing.',
            default => 'to be paid by '.Payment::METHOD_LABELS[$paymentMethod].'. If you left the payment page, pay from your order page so the branch can prepare it.',
        };
    }

    /**
     * Head office's flat delivery fee, waived once the items reach its free-delivery amount.
     */
    public static function deliveryFee(Tenant $tenant, string $subtotal): string
    {
        $minimum = $tenant->free_delivery_minimum;

        return $minimum !== null && bccomp($subtotal, (string) $minimum, 2) >= 0 ? '0.00' : (string) $tenant->delivery_fee;
    }

    private function existingOrder(User $user, string $clientRequestId): ?Order
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->where('client_request_id', $clientRequestId)
            ->first();
    }

    private function replay(Order $order, ?string $fingerprint): Order
    {
        if ($fingerprint !== null && ! hash_equals((string) $order->client_request_hash, $fingerprint)) {
            throw ValidationException::withMessages([
                'client_request_id' => 'This request identifier was already used for a different checkout.',
            ]);
        }

        return $order;
    }

    /**
     * The checkout payload, minus the cart, so a retry with the same details matches.
     *
     * @param  array{recipient_name: string, phone: string, address_line: string, city: string, province: string, postal_code: string, delivery_note?: ?string}  $delivery
     */
    private function fingerprint(array $delivery, string $paymentMethod, string $fulfillmentMethod, ?array $deliveryPin, ?string $branchId): string
    {
        $normalized = [
            'recipient_name' => trim((string) ($delivery['recipient_name'] ?? '')),
            'phone' => trim((string) ($delivery['phone'] ?? '')),
            'address_line' => trim((string) ($delivery['address_line'] ?? '')),
            'city' => trim((string) ($delivery['city'] ?? '')),
            'province' => trim((string) ($delivery['province'] ?? '')),
            'postal_code' => trim((string) ($delivery['postal_code'] ?? '')),
            'delivery_note' => trim((string) ($delivery['delivery_note'] ?? '')),
            'payment_method' => $paymentMethod,
            'fulfillment_method' => $fulfillmentMethod,
            'delivery_pin' => $deliveryPin === null ? null : [round((float) $deliveryPin['latitude'], 7), round((float) $deliveryPin['longitude'], 7)],
            'branch_id' => $branchId,
        ];

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<int, string>  $totals
     */
    private function sumTotals(array $totals): string
    {
        return array_reduce(
            $totals,
            fn (string $total, string $lineTotal): string => bcadd($total, $lineTotal, 2),
            '0.00',
        );
    }
}
