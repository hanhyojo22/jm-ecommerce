@php
    $statusStyles = ['pending'=>'bg-amber-50 text-warning-text ring-amber-200','confirmed'=>'bg-blue-50 text-brand-hover ring-blue-200','processing'=>'bg-blue-50 text-brand-hover ring-blue-200','shipped'=>'bg-blue-50 text-brand-hover ring-blue-200','delivered'=>'bg-green-50 text-success ring-green-200','cancelled'=>'bg-red-50 text-danger ring-red-200'];
    $paymentStyles = ['pending'=>'bg-amber-50 text-warning-text ring-amber-200','paid'=>'bg-green-50 text-success ring-green-200','failed'=>'bg-red-50 text-danger ring-red-200','refunded'=>'bg-slate-100 text-muted ring-line'];
    $currentStep = ['pending'=>1,'confirmed'=>2,'processing'=>2,'shipped'=>3,'delivered'=>4][$order->status] ?? 1;
    $storeStep = fn (string $status): int => ['pending'=>1,'confirmed'=>2,'processing'=>2,'shipped'=>3,'delivered'=>4][$status] ?? 1;
    $statusLabel = fn (string $status): string => $status === 'pending' ? 'Awaiting confirmation' : ucfirst($status);
    $isOnlinePayment = $order->payment?->isOnline() ?? false;
    $methodLabel = \App\Models\Payment::METHOD_LABELS[$order->payment?->method] ?? 'Cash on delivery';
    $storeCount = $order->sellerOrders->count();
    $isMultiStore = $storeCount > 1;
    $deliveredStores = $order->sellerOrders->where('status', 'delivered')->count();
    $cancelledStores = $order->sellerOrders->where('status', 'cancelled')->count();
    $allSettled = $storeCount > 0 && $deliveredStores + $cancelledStores === $storeCount;
    $anyStoreMoved = $order->sellerOrders->contains(fn ($sellerOrder) => $sellerOrder->status !== 'pending');
    $multiStoreHeadline = $allSettled && $deliveredStores > 0 ? 'Delivered' : ($anyStoreMoved ? 'Order in progress' : 'Order received');
@endphp
<div class="buyer-modal-head">
    <div>
        <p class="buyer-modal-eyebrow">Order details</p>
        <h2 id="order-modal-title" class="buyer-modal-title">Order #{{ $order->number }}</h2>
        <div class="buyer-modal-meta">
            <span>Placed {{ $order->created_at->format('M j, Y · g:i A') }}</span>
            <span class="sep" aria-hidden="true">·</span>
            <span class="status is-good"><span class="dot is-good" aria-hidden="true"></span>{{ $isMultiStore ? $multiStoreHeadline : ($order->status === 'pending' ? 'Awaiting confirmation' : ucfirst($order->status)) }}</span>
        </div>
    </div>
    <button type="button" class="buyer-modal-close" data-modal-focus aria-label="Close order details"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
</div>

<div class="buyer-modal-body">
    <div class="buyer-modal-grid">
        <div class="buyer-modal-journey" aria-label="Order progress">
            <p class="journey-title">Progress</p>
            @php($steps = ['Placed' => $order->created_at, 'Confirmed' => null, 'Shipped' => $order->sellerOrders->first()?->shipped_at, 'Delivered' => $order->sellerOrders->first()?->delivered_at])
            @foreach (['Placed','Confirmed','Shipped','Delivered'] as $i => $label)
                @php($done = $i + 1 <= $currentStep)
                <div class="vstep {{ $done ? 'done' : '' }} {{ $label === 'Delivered' && $done ? 'is-final' : '' }}">
                    <span class="vdot" aria-hidden="true"></span>
                    <div><p class="vlabel">{{ $label }}</p><p class="vtime num">@if($label === 'Placed'){{ $order->created_at->format('M j, g:i A') }}@elseif($label === 'Shipped' && $order->sellerOrders->first()?->shipped_at){{ $order->sellerOrders->first()->shipped_at->format('M j') }}@elseif($label === 'Delivered' && $order->sellerOrders->first()?->delivered_at){{ $order->sellerOrders->first()->delivered_at->format('M j') }}@elseif($label === 'Confirmed'){{ $order->created_at->format('M j') }}@endif</p></div>
                </div>
            @endforeach
        </div>

        <div>
            @include('marketplace.orders._store-section')

            <section class="buyer-modal-section">
                <h3>Delivery &amp; payment</h3>
                <dl>
                    <div><dt>Delivery address</dt><dd class="addr"><strong>{{ $order->recipient_name }}</strong><br>{{ $order->address_line }}<br>{{ collect([$order->city, $order->province])->filter()->join(', ') }} {{ $order->postal_code }}<br>{{ $order->phone }}</dd></div>
                    <div><dt>Payment method</dt><dd>{{ $methodLabel }}</dd></div>
                    <div><dt>Payment status</dt><dd style="color:var(--color-success);font-weight:600">{{ $order->payment ? ucfirst($order->payment->status) : 'Unavailable' }}</dd></div>
                    <div><dt>Shipping</dt><dd>{{ $order->shipping_amount == 0 ? 'Free' : '₱'.number_format($order->shipping_amount, 2) }}</dd></div>
                </dl>
            </section>

            @include('marketplace.orders._returns')
            @include('marketplace.orders._issues')

            <div class="buyer-modal-summary">
                <h3>Order summary</h3>
                <dl>
                    <div class="row"><dt>Items</dt><dd class="num">₱{{ number_format($order->subtotal, 2) }}</dd></div>
                    <div class="row"><dt>Shipping</dt><dd class="num" style="color:var(--color-success);font-weight:600">{{ $order->shipping_amount == 0 ? 'Free' : '₱'.number_format($order->shipping_amount, 2) }}</dd></div>
                </dl>
                <div class="total-row"><dt>Total</dt><dd class="num">₱{{ number_format($order->total, 2) }}</dd></div>
                <div class="pay"><span class="label">Payment</span><span class="value"><i aria-hidden="true"></i>{{ $methodLabel }} · {{ $order->payment ? ucfirst($order->payment->status) : 'n/a' }}</span></div>
            </div>
        </div>
    </div>
</div>

<div class="buyer-modal-foot">
    <a class="open-full" href="{{ route('orders.show', $order) }}">Open full page ↗</a>
    <button type="button" class="done-btn" onclick="this.closest('dialog').close()">Done</button>
</div>