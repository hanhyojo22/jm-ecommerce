@php
    $statusStep = ['pending' => 1, 'confirmed' => 2, 'processing' => 2, 'shipped' => 3, 'delivered' => 4];
    $currentStep = $statusStep[$order->status] ?? 1;
    $sellerOrders = $order->sellerOrders;
    $firstSellerOrder = $sellerOrders->first();
    $shippedAt = $firstSellerOrder?->shipped_at;
    $deliveredAt = $firstSellerOrder?->delivered_at;
    $subtotal = number_format((float) $order->subtotal, 2);
    $shippingLabel = $order->shipping_amount == 0 ? 'Free' : number_format((float) $order->shipping_amount, 2);
    $total = number_format((float) $order->total, 2);
    $methodLabel = \App\Models\Payment::METHOD_LABELS[$order->payment?->method] ?? 'Cash on delivery';
    $paymentStatus = $order->payment ? ucfirst($order->payment->status) : 'Unavailable';
    $storeCount = $sellerOrders->count();
    $deliveredStores = $sellerOrders->where('status', 'delivered')->count();
    $cancelledStores = $sellerOrders->where('status', 'cancelled')->count();
    $allSettled = $storeCount > 0 && ($deliveredStores + $cancelledStores) === $storeCount;
    $anyStoreMoved = $sellerOrders->contains(fn ($sellerOrder) => $sellerOrder->status !== 'pending');
    $multiStoreHeadline = $allSettled && $deliveredStores > 0 ? 'Delivered' : ($anyStoreMoved ? 'Order in progress' : 'Order received');
    $statusStyles = [
        'pending' => 'bg-amber-50 text-warning-text ring-amber-200',
        'confirmed' => 'bg-blue-50 text-brand-hover ring-blue-200',
        'processing' => 'bg-blue-50 text-brand-hover ring-blue-200',
        'shipped' => 'bg-blue-50 text-brand-hover ring-blue-200',
        'delivered' => 'bg-green-50 text-success ring-green-200',
        'cancelled' => 'bg-red-50 text-danger ring-red-200',
    ];
    $statusClass = $statusStyles[$order->status] ?? $statusStyles['pending'];
    $storeStatusClass = $statusStyles[$firstSellerOrder->status] ?? $statusStyles['pending'];
    $paymentStyles = ['pending' => 'bg-amber-50 text-warning-text ring-amber-200', 'paid' => 'bg-green-50 text-success ring-green-200', 'failed' => 'bg-red-50 text-danger ring-red-200', 'refunded' => 'bg-slate-100 text-muted ring-line'];
    $paymentClass = $paymentStyles[$order->payment->status] ?? $paymentStyles['pending'];
    $storeInitial = Str::upper(Str::substr($firstSellerOrder->store->name, 0, 1));
@endphp
<div class="dlg-head">
    <div>
        <p class="dlg-eyebrow">Order details</p>
        <h1 class="dlg-title" id="dlgTitle">Order #{{ $order->number }}</h1>
        <div class="dlg-meta">
            <span>Placed {{ $order->created_at->format('M j, Y · g:i A') }}</span>
            <span class="sep" aria-hidden="true">·</span>
            <span class="status {{ $statusClass }}"><i aria-hidden="true"></i>{{ $multiStoreHeadline }}</span>
        </div>
    </div>
    <button type="button" class="close-btn" onclick="this.closest('dialog').close()" aria-label="Close order details"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
</div>

<div class="dlg-journey" aria-label="Order progress">
    <div class="journey-line">
        @foreach (['Placed', 'Confirmed', 'Shipped', 'Delivered'] as $index => $label)
            @php($step = $index + 1)
            <span class="step {{ $step <= $currentStep ? 'done' : '' }} {{ $label === 'Delivered' && $step <= $currentStep ? 'final' : '' }}">
                <span class="dot" aria-hidden="true"></span>{{ $label }}
            </span>
            @if ($index < 3)
                <span class="seg {{ $step <= $currentStep ? 'done' : '' }}" aria-hidden="true"></span>
            @endif
        @endforeach
    </div>
    <div class="journey-dates">
        <span>{{ $order->created_at->format('M j') }}</span>
        <span>{{ $shippedAt ? $shippedAt->format('M j') : '' }}</span>
        <span>{{ $deliveredAt ? $deliveredAt->format('M j') : '' }}</span>
        <span>{{ $deliveredAt ? $deliveredAt->format('M j') : '' }}</span>
    </div>
</div>

<div class="dlg-body">
    <div class="block">
        <div class="block-head">
            <div class="block-store"><span class="block-store-avatar" aria-hidden="true">{{ $storeInitial }}</span>{{ $firstSellerOrder->store->name }}</div>
            <div style="display:flex;align-items:center;gap:10px">
                <span class="block-state {{ $storeStatusClass }}"><i aria-hidden="true"></i>{{ $firstSellerOrder->status === 'delivered' ? 'Delivered' : ucfirst($firstSellerOrder->status) }}</span>
                <a class="msg-seller" href="#">Message</a>
            </div>
        </div>
        <ul class="items">
            @foreach ($firstSellerOrder->items as $item)
                <li class="item">
                    <div class="item-thumb"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M4 8h16l-1 12H5L4 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg></div>
                    <div><p class="item-name">{{ $item->product_name }}</p>@if ($item->variant_label)<p class="item-variant">{{ $item->variant_label }}</p>@endif</div>
                    <div class="item-money"><strong class="num">₱{{ number_format((float) $item->unit_price, 2) }}</strong><span class="num">×{{ $item->quantity }}</span></div>
                </li>
            @endforeach
        </ul>
        <div class="block-foot"><span class="label">Store subtotal</span><span class="value num">₱{{ $subtotal }}</span></div>
    </div>

    <div class="block">
        <div class="block-head"><span style="font-size:13px;font-weight:600">Delivery &amp; payment</span></div>
        <div style="padding:12px 14px">
            <div class="kv">
                <div class="kv-row"><dt>Address</dt><dd class="addr"><strong>{{ $order->recipient_name }}</strong><br>{{ $order->address_line }}<br>{{ collect([$order->city, $order->province])->filter()->join(', ') }} {{ $order->postal_code }}<br>{{ $order->phone }}</dd></div>
                <div class="kv-row"><dt>Payment</dt><dd>{{ $methodLabel }} · {{ $paymentStatus }}</dd></div>
                <div class="kv-row"><dt>Shipping</dt><dd>{{ $shippingLabel }}</dd></div>
            </div>
            <div class="total-row"><dt>Total</dt><dd class="num">₱{{ $total }}</dd></div>
        </div>
    </div>

    <p style="margin-top:12px;font-size:12px;color:var(--color-muted);line-height:1.5">Returns and help are handled on the full order page. <a href="{{ route('orders.show', $order) }}" style="font-weight:600;color:var(--color-accent)">Open full page →</a></p>
</div>

<div class="dlg-foot">
    <a class="open-full" href="{{ route('orders.show', $order) }}">Open full page ↗</a>
    <button class="done-btn" type="button" onclick="this.closest('dialog').close()">Done</button>
</div>
