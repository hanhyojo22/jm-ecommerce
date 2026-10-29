<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\Payment;
use App\Models\SellerOrder;
use App\Services\PayMongo;
use App\Support\PlatformFee;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Throwable;

class StartOnlinePayment
{
    public function __construct(private PayMongo $payMongo) {}

    /**
     * Opens a PayMongo checkout for what the buyer still owes and returns its URL.
     *
     * Each store's share (its total less JMMart's fee) is split to the store's PayMongo account and
     * JMMart keeps the remainder. PayMongo takes its own fee before splitting, so the buyer pays a
     * fee line sized to cover it; see config('marketplace.online_payment.fees').
     */
    public function handle(Order $order): string
    {
        $order->loadMissing(['payment', 'sellerOrders.store', 'sellerOrders.tenant', 'sellerOrders.items']);
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

        $session = $this->payMongo->createCheckoutSession([
            'line_items' => [...$this->lineItems($sellerOrders), ['name' => 'Payment fee', 'amount' => PayMongo::centavos($fee), 'currency' => 'PHP', 'quantity' => 1]],
            'payment_method_types' => [Payment::PAYMONGO_TYPES[$payment->method]],
            'success_url' => route('orders.placed', $order),
            'cancel_url' => route('orders.show', $order),
            'reference_number' => $order->number,
            'description' => "JMMart order {$order->number}",
            'metadata' => ['order_id' => $order->id, 'payment_id' => $payment->id],
            'split_payment' => ['recipients' => $sellerOrders->map(fn (SellerOrder $sellerOrder): array => [
                'merchant_id' => $sellerOrder->store->paymongo_account_id,
                'split_type' => 'fixed',
                'value' => PayMongo::centavos(self::storeShare($sellerOrder, $order)),
            ])->values()->all()],
        ]);

        $previousSessionId = $payment->checkout_session_id;
        $payment->update(['checkout_session_id' => $session['id'], 'fee_amount' => $fee]);

        // A buyer who opens checkout again gets a fresh session; the old one must not take a second payment.
        if ($previousSessionId !== null) {
            try {
                $this->payMongo->expireCheckoutSession($previousSessionId);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $session['checkout_url'];
    }

    /**
     * What the store receives: its total less JMMart's fee at the rate agreed when the order was placed.
     */
    public static function storeShare(SellerOrder $sellerOrder, Order $order): string
    {
        $total = (string) $sellerOrder->total;

        return bcsub($total, PlatformFee::on($total, $sellerOrder->tenant->platformFeeRateAt($order->created_at)), 2);
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
        return $sellerOrders->flatMap(fn (SellerOrder $sellerOrder) => $sellerOrder->items->map(fn ($item): array => [
            'name' => $item->product_name.($item->variant_label ? " ({$item->variant_label})" : ''),
            'amount' => PayMongo::centavos((string) $item->unit_price),
            'currency' => 'PHP',
            'quantity' => $item->quantity,
        ]))->values()->all();
    }
}
