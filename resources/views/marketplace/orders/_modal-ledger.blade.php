@php
    $currentStep = ['pending'=>1,'confirmed'=>2,'processing'=>2,'shipped'=>3,'delivered'=>4][$order->status] ?? 1;
    $statusLabel = fn (string $status): string => $status === 'pending' ? 'Awaiting confirmation' : ucfirst($status);
    $isOnlinePayment = $order->payment?->isOnline() ?? false;
    $awaitingOnlinePayment = $isOnlinePayment && $order->payment->status === 'pending'
        && $order->sellerOrders->contains(fn ($sellerOrder): bool => $sellerOrder->status === 'pending' && $sellerOrder->cod_collected_at === null);
    $methodLabel = \App\Models\Payment::METHOD_LABELS[$order->payment?->method] ?? 'Cash on delivery';
    $storeCount = $order->sellerOrders->count();
    $isMultiStore = $storeCount > 1;
    $deliveredStores = $order->sellerOrders->where('status', 'delivered')->count();
    $cancelledStores = $order->sellerOrders->where('status', 'cancelled')->count();
    $allSettled = $storeCount > 0 && $deliveredStores + $cancelledStores === $storeCount;
    $anyStoreMoved = $order->sellerOrders->contains(fn ($sellerOrder): bool => $sellerOrder->status !== 'pending');
    $multiStoreHeadline = $allSettled && $deliveredStores > 0 ? 'Delivered' : ($anyStoreMoved ? 'Order in progress' : 'Order received');
@endphp

<div class="dlg-head">
    <div>
        <p class="dlg-eyebrow">Order details</p>
        <h1 class="dlg-title" id="dlgTitle">Order #{{ $order->number }}</h1>
        <div class="dlg-meta">
            <span>Placed {{ $order->created_at->format('M j, Y · g:i A') }}</span>
            <span class="sep" aria-hidden="true">·</span>
            <span class="status"><span class="dot" aria-hidden="true"></span>{{ $isMultiStore ? $multiStoreHeadline : ($order->status === 'pending' ? 'Awaiting confirmation' : ucfirst($order->status)) }}</span>
        </div>
    </div>
    <button type="button" class="close-btn" data-modal-focus aria-label="Close order details"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
</div>

@if($order->status === 'cancelled')
<div class="block">
    <div class="block-head"><span style="font-size:13px;font-weight:600">Order cancelled</span></div>
    <div style="padding:12px 14px"><p class="font-semibold text-ink">This order was cancelled, so delivery and review actions are unavailable.</p></div>
</div>
@endif

@if($awaitingOnlinePayment)
@php($payBy = $order->created_at->copy()->addHours((int) config('marketplace.online_payment.payment_hours')))
<div class="block">
    <div class="block-head"><span style="font-size:13px;font-weight:600">Pay now</span></div>
    <div style="padding:12px 14px">
        <p class="font-semibold text-ink">Pay ₱{{ number_format(bcadd((string) $order->total, (string) $order->payment->fee_amount, 2), 2) }} to confirm your order</p>
        <p class="mt-1 text-sm text-warning-text">Pay with {{ $methodLabel }} on PayMongo before {{ $payBy->format('M j, g:i A') }}. Unpaid orders are cancelled and the items released.</p>
        <a href="{{ route('orders.pay', $order) }}" class="inline-flex min-h-10 items-center justify-center rounded-md bg-brand px-3 text-sm font-semibold text-white transition hover:bg-brand-hover">Pay now</a>
    </div>
</div>
@endif

<div class="dlg-journey" aria-label="Order progress">
    <div class="journey-line">
        @foreach(['Placed','Confirmed','Shipped','Delivered'] as $i => $label)
            @php($done = $i + 1 <= $currentStep)
            <span class="step {{ $done ? 'done' : '' }} {{ $label === 'Delivered' && $done ? 'final' : '' }}"><span class="dot" aria-hidden="true"></span>{{ $label }}</span>
            @if($i < 3)<span class="seg {{ $done ? 'done' : '' }}"></span>@endif
        @endforeach
    </div>
    <div class="journey-dates">
        <span>{{ $order->created_at->format('M j') }}</span>
        <span>{{ $order->created_at->format('M j') }}</span>
        @php($shippedAt = $order->sellerOrders->first()?->shipped_at)@if($shippedAt)<span>{{ $shippedAt->format('M j') }}</span>@else<span>–</span>@endif
        @php($deliveredAt = $order->sellerOrders->first()?->delivered_at)@if($deliveredAt)<span class="num">{{ $deliveredAt->format('M j') }}</span>@else<span>–</span>@endif
    </div>
</div>

<div class="dlg-body">
    @foreach($order->sellerOrders as $sellerOrder)
    <div class="block">
        <div class="block-head">
            <div class="block-store">
                <span class="block-store-avatar" aria-hidden="true">{{ Str::upper(Str::substr($sellerOrder->store->name,0,1)) }}</span>{{ $sellerOrder->store->name }}
            </div>
            <div style="display:flex;align-items:center;gap:10px">
                <span class="block-state"><i aria-hidden="true"></i>{{ $statusLabel($sellerOrder->status) }}</span>
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
                <div>
                    <p class="item-name">{{ $item->product_name }}</p>
                    @if($item->variant_label)<p class="item-variant">{{ $item->variant_label }}</p>@endif
                    @if($sellerOrder->status === 'delivered' && $item->product_id)
                    <a href="{{ route('orders.show', $order) }}#review-item-{{ $item->id }}" style="display:inline-block;margin-top:6px;font-size:11.5px;font-weight:600;color:var(--color-accent)">Write a review</a>
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
        <div class="block-head"><span style="font-size:13px;font-weight:600">Delivery &amp; payment</span></div>
        <div style="padding:12px 14px">
            <div class="kv">
                <div class="kv-row"><dt>Delivery address</dt><dd class="addr"><strong>{{ $order->recipient_name }}</strong><br>{{ $order->address_line }}<br>{{ collect([$order->city, $order->province])->filter()->join(', ') }} {{ $order->postal_code }}<br>{{ $order->phone }}</dd></div>
                <div class="kv-row"><dt>Payment method</dt><dd>{{ $methodLabel }}</dd></div>
                <div class="kv-row"><dt>Shipping</dt><dd>{{ $order->shipping_amount == 0 ? 'Free' : '₱'.number_format($order->shipping_amount, 2) }}</dd></div>
            </div>
            <div class="total-row"><dt>Total</dt><dd class="num">₱{{ number_format($order->total, 2) }}</dd></div>
        </div>
    </div>

    <p style="margin-top:12px;font-size:12px;color:var(--color-muted);line-height:1.5">Returns, replacements, and help requests are handled on the full order page. <a href="{{ route('orders.show', $order) }}" style="font-weight:600;color:var(--color-accent)">Open full page →</a></p>
</div>

<div class="dlg-foot">
    <a class="open-full" href="{{ route('orders.show', $order) }}">Open full page ↗</a>
    <button type="button" class="done-btn" onclick="this.closest('dialog').close()">Done</button>
</div>