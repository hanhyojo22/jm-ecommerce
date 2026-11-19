<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\Payment;
use App\Models\SellerOrder;
use App\Services\PayMongo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class StartOnlinePayment
{
    public function __construct(private PayMongo $payMongo) {}

    /**
     * Opens a PayMongo checkout for what the buyer still owes and returns its URL.
     *
     * The whole amount goes to the pharmacy's own PayMongo account. PayMongo takes its own fee,
     * so the buyer pays a fee line sized to cover it; see config('marketplace.online_payment.fees').
     *
     * The API passes app return URLs (by default the "go back to the app" page) so a finished
     * checkout never lands on the web order page. The web leaves them null and keeps its routes.
     */
    public function handle(Order $order, ?string $successUrl = null, ?string $cancelUrl = null): string
    {
        $url = Cache::lock('online-payment:'.$order->id, 60)->get(
            fn (): string => $this->start($order, $successUrl, $cancelUrl),
        );

        if ($url === false) {
            throw ValidationException::withMessages(['payment' => 'A payment request is already in progress. Check your order before trying again.']);
        }

        return $url;
    }

    private function start(Order $order, ?string $successUrl, ?string $cancelUrl): string
    {
        $order->refresh()->load(['payment', 'sellerOrders.store', 'sellerOrders.items']);
        $payment = $order->payment;
        $sellerOrders = $order->sellerOrders->reject(fn (SellerOrder $sellerOrder): bool => $sellerOrder->status === 'cancelled');

        if (! in_array($payment?->method, Payment::ONLINE_METHODS, true) || $payment->status !== 'pending' || $sellerOrders->isEmpty() || $sellerOrders->contains(fn (SellerOrder $sellerOrder): bool => $sellerOrder->status !== 'pending' || $sellerOrder->cod_collected_at !== null)) {
            throw ValidationException::withMessages(['payment' => 'This order is not waiting for an online payment.']);
        }

        if ($store = $sellerOrders->first(fn (SellerOrder $sellerOrder): bool => ! $sellerOrder->store->acceptsOnlinePayments())?->store) {
            throw ValidationException::withMessages(['payment' => "{$store->name} can’t take online payments right now. Cancel its part of the order or contact support."]);
        }

        $total = $sellerOrders->reduce(fn (string $sum, SellerOrder $sellerOrder): string => bcadd($sum, (string) $sellerOrder->total, 2), '0.00');
        $fee = self::buyerFee($total, $payment->method);

        if ($payment->checkout_session_id !== null) {
            $this->payMongo->expireCheckoutSession($payment->checkout_session_id);
            $payment->update(['checkout_session_id' => null]);
        }

        $session = $this->payMongo->createCheckoutSession([
            'line_items' => [...$this->lineItems($sellerOrders), ['name' => 'Payment fee', 'amount' => PayMongo::centavos($fee), 'currency' => 'PHP', 'quantity' => 1]],
            'payment_method_types' => [Payment::PAYMONGO_TYPES[$payment->method]],
            'success_url' => $successUrl ?? route('orders.placed', $order),
            'cancel_url' => $cancelUrl ?? route('orders.show', $order),
            'reference_number' => $order->number,
            'description' => "JMMart order {$order->number}",
            'metadata' => ['order_id' => $order->id, 'payment_id' => $payment->id],
        ]);

        $payment->update(['checkout_session_id' => $session['id'], 'fee_amount' => $fee]);

        return $session['checkout_url'];
    }

    /**
     * The fee line that leaves the order total intact after PayMongo takes rate × charge + fixed,
     * rounded up to the centavo so the stores' shares always fit.
     */
    public static function buyerFee(string $total, string $method): string
    {
        ['rate' => $rate, 'fixed' => $fixed] = config("marketplace.online_payment.fees.{$method}");
        $exact = bcdiv(bcadd(bcmul($total, $rate, 6), $fixed, 6), bcsub('1', $rate, 6), 6);
        $fee = bcadd($exact, '0', 2);

        return bccomp($fee, $exact, 6) < 0 ? bcadd($fee, '0.01', 2) : $fee;
    }

    /**
     * @param  Collection<int, SellerOrder>  $sellerOrders
     * @return list<array{name: string, amount: int, currency: string, quantity: int}>
     */
    private function lineItems(Collection $sellerOrders): array
    {
        $items = $sellerOrders->flatMap(fn (SellerOrder $sellerOrder) => $sellerOrder->items->map(function ($item): array {
            $name = $item->product_name.($item->variant_label ? " ({$item->variant_label})" : '');

            // A discounted line may not split into whole centavos per unit, so it is charged as one line.
            return bccomp((string) $item->discount_amount, '0', 2) > 0
                ? ['name' => "{$name} × {$item->quantity} (SC/PWD discount)", 'amount' => PayMongo::centavos(bcsub((string) $item->line_total, (string) $item->discount_amount, 2)), 'currency' => 'PHP', 'quantity' => 1]
                : ['name' => $name, 'amount' => PayMongo::centavos((string) $item->unit_price), 'currency' => 'PHP', 'quantity' => $item->quantity];
        }))->values()->all();
        $deliveryFee = $sellerOrders->reduce(fn (string $sum, SellerOrder $sellerOrder): string => bcadd($sum, (string) $sellerOrder->shipping_amount, 2), '0.00');

        return bccomp($deliveryFee, '0', 2) > 0
            ? [...$items, ['name' => 'Delivery fee', 'amount' => PayMongo::centavos($deliveryFee), 'currency' => 'PHP', 'quantity' => 1]]
            : $items;
    }
}
