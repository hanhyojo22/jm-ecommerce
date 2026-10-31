@php
    $currentStep = ['pending'=>1,'confirmed'=>2,'processing'=>2,'shipped'=>3,'delivered'=>4][$order->status] ?? 1;
    $statusLabel = fn (string $status): string => $status === 'pending' ? 'Awaiting confirmation' : ucfirst($status);
    $statusTone = fn (string $status): string => match ($status) {
        'pending' => 'is-warn',
        'confirmed', 'processing', 'shipped' => 'is-moving',
        'delivered' => 'is-good',
        'cancelled' => 'is-stopped',
        default => '',
    };
    $isOnlinePayment = $order->payment?->isOnline() ?? false;
    $awaitingOnlinePayment = $isOnlinePayment && $order->payment->status === 'pending'
        && $order->sellerOrders->contains(fn ($sellerOrder): bool => $sellerOrder->status === 'pending' && $sellerOrder->cod_collected_at === null);
    $methodLabel = \App\Models\Payment::METHOD_LABELS[$order->payment?->method] ?? 'Cash on delivery';
    $paymentStatus = ! $order->payment ? 'Unavailable' : match (true) {
        $order->payment->status === 'refunded' => 'Refunded',
        $order->payment->status === 'failed' => 'Failed',
        $order->payment->status === 'paid' => 'Paid',
        default => 'Awaiting payment',
    };
    $paymentTone = ! $order->payment ? '' : match ($order->payment->status) {
        'paid' => 'is-good',
        'failed' => 'is-stopped',
        'refunded' => 'is-muted',
        default => 'is-warn',
    };
    $storeCount = $order->sellerOrders->count();
    $isMultiStore = $storeCount > 1;
    $deliveredStores = $order->sellerOrders->where('status', 'delivered')->count();
    $cancelledStores = $order->sellerOrders->where('status', 'cancelled')->count();
    $allSettled = $storeCount > 0 && $deliveredStores + $cancelledStores === $storeCount;
    $anyStoreMoved = $order->sellerOrders->contains(fn ($sellerOrder): bool => $sellerOrder->status !== 'pending');
    $multiStoreHeadline = $allSettled && $deliveredStores > 0 ? 'Delivered' : ($anyStoreMoved ? 'Order in progress' : 'Order received');
    $headline = $isMultiStore ? $multiStoreHeadline : ($order->status === 'pending' ? 'Awaiting confirmation' : ucfirst($order->status));
    $shippedAt = $order->sellerOrders->first()?->shipped_at;
    $deliveredAt = $order->sellerOrders->first()?->delivered_at;
@endphp

<div class="dlg-head">
    <div class="dlg-heading">
        <p class="dlg-eyebrow">Order details</p>
        <div class="dlg-title-row">
            <h1 class="dlg-title" id="dlgTitle">Order #{{ $order->number }}</h1>
            <button type="button" class="copy-btn" aria-label="Copy order number" onclick="navigator.clipboard?.writeText(@js($order->number)); this.textContent = 'Copied'; setTimeout(() => this.textContent = 'Copy', 1500)">Copy</button>
        </div>
        <div class="dlg-meta" id="dlgMeta">
            <span>Placed {{ $order->created_at->format('M j, Y · g:i A') }}</span>
            <span class="sep" aria-hidden="true">·</span>
            <span class="status {{ $statusTone($order->status) }}"><span class="dot" aria-hidden="true"></span>{{ $headline }}</span>
        </div>
    </div>
    <button type="button" class="close-btn" data-modal-focus aria-label="Close order details" onclick="this.closest('dialog').close()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
</div>

@if($order->status === 'cancelled')
<div class="block is-danger">
    <div class="block-head"><span class="block-title">Order cancelled</span></div>
    <div class="block-pad"><p class="block-copy">This order was cancelled, so delivery and review actions are unavailable.</p></div>
</div>
@endif

@if($awaitingOnlinePayment)
@php($payBy = $order->created_at->copy()->addHours((int) config('marketplace.online_payment.payment_hours')))
<div class="block is-warning">
    <div class="block-head"><span class="block-title">Pay now</span></div>
    <div class="block-pad">
        <p class="block-copy-strong">Pay ₱{{ number_format(bcadd((string) $order->total, (string) $order->payment->fee_amount, 2), 2) }} to confirm your order</p>
        <p class="block-copy mt-1">Pay with {{ $methodLabel }} on PayMongo before {{ $payBy->format('M j, g:i A') }}. Unpaid orders are cancelled and the items released.</p>
        <a href="{{ route('orders.pay', $order) }}" class="pay-now-btn">Pay now</a>
    </div>
</div>
@endif

<div class="dlg-journey" aria-label="Order progress">
    <ol class="journey-line">
        @foreach(['Placed','Confirmed','Shipped','Delivered'] as $i => $label)
            @php($done = $i + 1 <= $currentStep)
            @php($current = $i + 1 === $currentStep)
            <li class="step {{ $done ? 'done' : '' }} {{ $current ? 'current' : '' }} {{ $label === 'Delivered' && $done ? 'final' : '' }}" @if($current) aria-current="step" @endif>
                <span class="dot" aria-hidden="true"></span>
                <span class="step-label">{{ $label }}@if($current)<span class="sr-only"> (current step)</span>@endif</span>
            </li>
        @endforeach
    </ol>
    <div class="journey-dates" aria-hidden="true">
        <span>{{ $order->created_at->format('M j') }}</span>
        <span>{{ $order->created_at->format('M j') }}</span>
        <span>{{ $shippedAt?->format('M j') ?? '–' }}</span>
        <span class="num">{{ $deliveredAt?->format('M j') ?? '–' }}</span>
    </div>
</div>

<div class="dlg-body">
    @foreach($order->sellerOrders as $sellerOrder)
    <div class="block">
        <div class="block-head">
            <div class="block-store">
                <span class="block-store-avatar" aria-hidden="true">{{ Str::upper(Str::substr($sellerOrder->store->name,0,1)) }}</span>
                <span class="block-store-name">{{ $sellerOrder->store->name }}</span>
            </div>
            <div class="block-actions">
                <span class="block-state {{ $statusTone($sellerOrder->status) }}"><i aria-hidden="true"></i>{{ $statusLabel($sellerOrder->status) }}</span>
                <form method="POST" action="{{ route('messages.seller-orders.start', $sellerOrder) }}">
                    @csrf
                    <button type="submit" class="msg-seller">Message seller</button>
                </form>
            </div>
        </div>
        <ul class="items">
            @foreach($sellerOrder->items as $item)
            <li class="item">
                <div class="item-thumb">
                    @if($item->image_path || $item->product?->images->first())
                    <img src="{{ Storage::disk('product_images')->url($item->image_path ?? $item->product->images->first()->path) }}" alt="" width="40" height="40" loading="lazy">
                    @else
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M4 8h16l-1 12H5L4 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
                    @endif
                </div>
                <div class="item-info">
                    <p class="item-name">{{ $item->product_name }}</p>
                    @if($item->variant_label)<p class="item-variant">{{ $item->variant_label }}</p>@endif
                    @if($sellerOrder->status === 'delivered' && $item->product_id)
                    <a href="{{ route('orders.show', $order) }}#review-item-{{ $item->id }}" class="item-review">Write a review</a>
                    @endif
                </div>
                <div class="item-money">
                    <strong class="num">₱{{ number_format($item->unit_price, 2) }}</strong>
                    <span class="num">×{{ $item->quantity }}</span>
                </div>
            </li>
            @endforeach
        </ul>
        <div class="block-foot"><span class="label">Store subtotal</span><span class="value num">₱{{ number_format($sellerOrder->subtotal, 2) }}</span></div>
    </div>
    @endforeach

    <div class="block">
        <div class="block-head"><span class="block-title">Delivery &amp; payment</span></div>
        <div class="block-pad">
            <dl class="kv">
                <div class="kv-row"><dt>Delivery address</dt><dd class="addr"><strong>{{ $order->recipient_name }}</strong><br>{{ $order->address_line }}<br>{{ collect([$order->city, $order->province])->filter()->join(', ') }} {{ $order->postal_code }}<br>{{ $order->phone }}</dd></div>
                @if($order->delivery_note)
                <div class="kv-row"><dt>Delivery note</dt><dd class="addr">{{ $order->delivery_note }}</dd></div>
                @endif
                <div class="kv-row"><dt>Payment</dt><dd>{{ $methodLabel }} <span class="pay-status {{ $paymentTone }}"><i aria-hidden="true"></i>{{ $paymentStatus }}</span></dd></div>
                <div class="kv-row"><dt>Shipping</dt><dd>{{ $order->shipping_amount == 0 ? 'Free' : '₱'.number_format($order->shipping_amount, 2) }}</dd></div>
            </dl>
            <div class="total-row"><span>Total</span><strong class="num">₱{{ number_format($order->total, 2) }}</strong></div>
        </div>
    </div>

    <p class="dlg-note">Returns, replacements, and help requests are handled on the full order page. <a href="{{ route('orders.show', $order) }}">Open full page →</a></p>
</div>

<div class="dlg-foot">
    <a class="open-full" href="{{ route('orders.show', $order) }}">Open full page ↗</a>
    <button type="button" class="done-btn" onclick="this.closest('dialog').close()">Done</button>
</div>