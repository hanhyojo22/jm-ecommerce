@php
    $currentStep = ['pending'=>1,'confirmed'=>2,'processing'=>2,'shipped'=>3,'delivered'=>4][$order->status] ?? 1;
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
    $paymentLine = ! $order->payment ? $methodLabel : match (true) {
        $order->payment->status === 'paid' => $methodLabel.' · Paid',
        $order->payment->status === 'refunded' => $methodLabel.' · Refunded',
        $order->payment->status === 'failed' => $methodLabel.' · Failed',
        default => $methodLabel.($isOnlinePayment ? ' · Awaiting payment' : ' · Due on delivery'),
    };
    $storeCount = $order->sellerOrders->count();
    $isMultiStore = $storeCount > 1;
    $deliveredStores = $order->sellerOrders->where('status', 'delivered')->count();
    $cancelledStores = $order->sellerOrders->where('status', 'cancelled')->count();
    $allSettled = $storeCount > 0 && $deliveredStores + $cancelledStores === $storeCount;
    $anyStoreMoved = $order->sellerOrders->contains(fn ($sellerOrder): bool => $sellerOrder->status !== 'pending');
    $multiStoreHeadline = $allSettled && $deliveredStores > 0 ? 'Delivered' : ($anyStoreMoved ? 'Order in progress' : 'Order received');
    $headline = $isMultiStore ? $multiStoreHeadline : ($order->status === 'pending' ? 'Awaiting confirmation' : ucfirst($order->status));
    $headlineTone = $isMultiStore
        ? ($allSettled && $deliveredStores > 0 ? 'is-good' : ($anyStoreMoved ? 'is-moving' : 'is-warn'))
        : $statusTone($order->status);
    $progress = ($currentStep - 1) / 3;
@endphp

<div class="clean-head">
    <div>
        <h1 class="clean-title" id="dlgTitle">Order #{{ $order->number }}</h1>
        <p class="clean-status {{ $headlineTone }}"><i aria-hidden="true"></i>{{ $headline }}</p>
        <div class="clean-meta" id="dlgMeta">Placed {{ $order->created_at->format('M j, Y') }}</div>
    </div>
    <button type="button" class="close-btn" data-modal-focus aria-label="Close order details" onclick="this.closest('dialog').close()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
</div>

@if($order->status === 'cancelled')
<div class="clean-notice is-danger">
    <p class="title">Order cancelled</p>
    <p>Delivery and review actions are unavailable for this order.</p>
</div>
@endif

@if($awaitingOnlinePayment)
@php($payBy = $order->created_at->copy()->addHours((int) config('marketplace.online_payment.payment_hours')))
<div class="clean-notice is-warning">
    <p class="title">Pay ₱{{ number_format(bcadd((string) $order->total, (string) $order->payment->fee_amount, 2), 2) }} to confirm</p>
    <p>Pay with {{ $methodLabel }} on PayMongo before {{ $payBy->format('M j, g:i A') }}. Unpaid orders are cancelled and the items released.</p>
    <a href="{{ route('orders.pay', $order) }}">Pay now →</a>
</div>
@endif

<div class="clean-journey" aria-label="Order progress">
    <ol class="tracker" style="--progress: {{ $progress }}">
        @foreach(['Placed','Confirmed','Shipped','Delivered'] as $i => $label)
            @php($done = $i + 1 <= $currentStep)
            @php($current = $i + 1 === $currentStep)
            <li class="tracker-step {{ $done ? 'done' : '' }} {{ $current ? 'current' : '' }} {{ $label === 'Delivered' && $done ? 'final' : '' }}" @if($current) aria-current="step" @endif>
                <span class="node" aria-hidden="true">
                    @switch($label)
                        @case('Placed')<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h16l-1 12H5L4 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>@break
                        @case('Confirmed')<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg>@break
                        @case('Shipped')<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h11v10H3z"/><path d="M14 9h4l3 3v4h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/></svg>@break
                        @default<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10 12 4l8 6v9a1 1 0 0 1-1 1h-5v-5h-4v5H5a1 1 0 0 1-1-1z"/></svg>
                    @endswitch
                </span>
                <span class="label">{{ $label }}@if($current)<span class="sr-only"> (current step)</span>@endif</span>
            </li>
        @endforeach
    </ol>
</div>

<div class="clean-body">
    @foreach($order->sellerOrders as $sellerOrder)
        <p class="clean-store">{{ $sellerOrder->store->name }}</p>
        <ul class="clean-items">
            @foreach($sellerOrder->items as $item)
            <li class="clean-item">
                <div class="item-thumb">
                    @if($item->image_path || $item->product?->images->first())
                    <img src="{{ Storage::disk('product_images')->url($item->image_path ?? $item->product->images->first()->path) }}" alt="" width="40" height="40" loading="lazy">
                    @else
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M4 8h16l-1 12H5L4 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
                    @endif
                </div>
                <div class="item-info">
                    <p class="item-title">{{ $item->product_name }}</p>
                    @if($item->variant_label)<p class="item-sub">{{ $item->variant_label }}</p>@endif
                </div>
                <p class="item-price num">₱{{ number_format($item->unit_price, 2) }}<span>×{{ $item->quantity }}</span></p>
            </li>
            @endforeach
        </ul>
    @endforeach

    <div class="clean-section">
        <p class="clean-label">Delivery</p>
        <p class="clean-address"><strong>{{ $order->recipient_name }}</strong>{{ $order->address_line }}<br>{{ collect([$order->city, $order->province])->filter()->join(', ') }} {{ $order->postal_code }}<br>{{ $order->phone }}</p>
    </div>

    <div class="clean-total"><span class="label">Total</span><strong class="value num">₱{{ number_format($order->total, 2) }}</strong></div>
    <p class="clean-pay">{{ $paymentLine }}</p>
</div>

<div class="clean-foot">
    <a class="open-full" href="{{ route('orders.show', $order) }}">Open full page ↗</a>
    <button type="button" class="done-btn" onclick="this.closest('dialog').close()">Done</button>
</div>