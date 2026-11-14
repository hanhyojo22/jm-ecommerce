@php
    $sellerOrders = $order->sellerOrders;
    $storeCount = $sellerOrders->count();
    $isMultiStore = $storeCount > 1;
    $activeStores = $sellerOrders->where('status', '!=', 'cancelled');
    $activeCount = $activeStores->count();
    $deliveredStores = $sellerOrders->where('status', 'delivered')->count();
    $cancelledStores = $sellerOrders->where('status', 'cancelled')->count();
    $allSettled = $storeCount > 0 && $deliveredStores + $cancelledStores === $storeCount;
    $isCancelled = $order->status === 'cancelled';
    $isDelivered = $order->status === 'delivered';
    $anyStoreMoved = $sellerOrders->contains(fn ($sellerOrder): bool => $sellerOrder->status !== 'pending');
    $partialDelivered = $deliveredStores > 0 && $deliveredStores < $activeCount;
    $itemCount = $sellerOrders->sum(fn ($sellerOrder): int => (int) $sellerOrder->items->sum('quantity'));
    $firstReviewable = $sellerOrders
        ->filter(fn ($sellerOrder): bool => $sellerOrder->status === 'delivered' && ! in_array($sellerOrder->tenant_id, $ownedTenantIds, true))
        ->flatMap(fn ($sellerOrder) => $sellerOrder->items)
        ->first(fn ($item): bool => $item->product_id !== null && $item->review === null);
    $firstReviewed = $sellerOrders->where('status', 'delivered')->flatMap(fn ($sellerOrder) => $sellerOrder->items)->first(fn ($item): bool => $item->review !== null);

    $statusLabels = ['pending' => 'Awaiting store', 'confirmed' => 'Preparing', 'processing' => 'Preparing', 'shipped' => 'On the way', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
    $statusTones = ['pending' => 'neutral', 'confirmed' => 'neutral', 'processing' => 'neutral', 'shipped' => 'moving', 'delivered' => 'good', 'cancelled' => 'danger'];

    $isOnlinePayment = $order->payment?->isOnline() ?? false;
    $awaitingOnlinePayment = $isOnlinePayment && $order->payment->status === 'pending'
        && $sellerOrders->contains(fn ($sellerOrder): bool => $sellerOrder->status === 'pending' && $sellerOrder->cod_collected_at === null);
    $payBy = $isOnlinePayment && $order->payment?->status === 'pending'
        ? $order->created_at->copy()->addHours((int) config('marketplace.online_payment.payment_hours'))
        : null;
    $methodLabel = \App\Models\Payment::METHOD_LABELS[$order->payment?->method] ?? 'Cash on delivery';
    $partlyCollectedCash = ! $isOnlinePayment && $order->payment?->status !== 'paid' && $activeStores->whereNotNull('cod_collected_at')->isNotEmpty();
    $paymentLine = ! $order->payment ? $methodLabel : match (true) {
        $order->payment->status === 'paid' => $methodLabel.' · Paid',
        $order->payment->status === 'refunded' => $methodLabel.' · Refunded',
        $order->payment->status === 'failed' => $methodLabel.' · Failed',
        $partlyCollectedCash => $methodLabel.' · Partly collected',
        default => $methodLabel.($isOnlinePayment ? ' · Awaiting payment' : ' · Due on delivery'),
    };
    $feeAmount = (string) ($order->payment?->fee_amount ?? '0');
    $hasFee = bccomp($feeAmount, '0', 2) > 0;
    $totalWithFee = bcadd((string) $order->total, $feeAmount, 2);
    $anyCollected = $sellerOrders->contains(fn ($sellerOrder): bool => $sellerOrder->cod_collected_at !== null);
    $anyRefunded = $order->payment?->status === 'refunded'
        || $sellerOrders->contains(fn ($sellerOrder): bool => $sellerOrder->gateway_refund_id !== null);
    $refundSent = $isCancelled && $anyRefunded;
    $refundPending = $isCancelled && $isOnlinePayment && ! $anyRefunded && ($anyCollected || $order->payment?->status === 'paid');

    $paymentTone = match (true) {
        $isCancelled => 'neutral',
        $order->payment?->status === 'paid' => 'good',
        $order->payment?->status === 'failed' => 'danger',
        $order->payment?->status === 'refunded' => 'neutral',
        $isOnlinePayment => 'warn',
        default => 'neutral',
    };
    $paymentNote = match (true) {
        ! $order->payment => 'Payment details unavailable.',
        $refundSent => 'Refund sent through PayMongo.',
        $refundPending => 'Refund is being processed through PayMongo.',
        $isCancelled => 'No payment was taken for this order.',
        $order->payment->status === 'failed' => 'Payment failed. Try again from the full order page.',
        $order->payment->status === 'paid' => $isOnlinePayment ? 'Paid online through PayMongo.' : 'Cash collected by the store.',
        $partlyCollectedCash => 'Cash collected for some stores. See the full order page for each store’s collection status.',
        $isOnlinePayment && $payBy => 'Due by '.$payBy->format('M j, g:i A').'.',
        $isDelivered => 'Cash due to the store.',
        default => 'Pay the store in cash when your order arrives.',
    };
    $receiptNote = match (true) {
        $refundSent => ['refresh', 'Refund sent via '.$methodLabel.'.'],
        $refundPending => ['refresh', 'Refund is being processed through PayMongo.'],
        $isCancelled => ['x-circle', 'No payment was taken.'],
        $awaitingOnlinePayment => ['clock', 'Payment due by '.$payBy->format('M j, g:i A').'.'],
        $order->payment?->status === 'paid' => ['check', $isOnlinePayment ? 'Paid in full via '.$methodLabel.'.' : 'Paid in cash.'],
        $partlyCollectedCash => ['card', 'Cash collected for some stores.'],
        $isOnlinePayment => ['clock', 'Payment due by '.($payBy?->format('M j, g:i A') ?? 'the deadline').'.'],
        $isDelivered => ['card', 'Cash due to the store.'],
        default => ['card', 'Cash due on delivery.'],
    };

    $storeSummary = $sellerOrders->map(function ($sellerOrder): string {
        $name = $sellerOrder->store->name;

        return match ($sellerOrder->status) {
            'delivered' => $sellerOrder->delivered_at ? $name.' delivered '.$sellerOrder->delivered_at->format('M j').'.' : $name.' delivered.',
            'shipped' => $sellerOrder->shipped_at ? $name.' shipped '.$sellerOrder->shipped_at->format('M j').'.' : $name.' is on the way.',
            'processing' => $name.' is preparing your items.',
            'confirmed' => $name.' confirmed your order.',
            'cancelled' => $name.' was cancelled.',
            default => $name.' is awaiting confirmation.',
        };
    })->join(' ');

    if ($isCancelled) {
        $title = 'Order cancelled';
        $statusTone = 'danger';
        $statusIcon = 'x-circle';
        $pillLabel = 'Cancelled';
        $pillTone = 'danger';
        $statusSub = match (true) {
            $refundSent => 'The stores restored stock. Your refund was sent.',
            $refundPending => 'The stores restored stock. Your refund is being processed.',
            default => 'The stores restored stock.',
        };
    } elseif ($awaitingOnlinePayment) {
        $title = 'Payment needed';
        $statusTone = 'warn';
        $statusIcon = 'card';
        $pillLabel = 'Payment due';
        $pillTone = 'warn';
        $statusSub = 'Pay ₱'.number_format($totalWithFee, 2).' to keep your items reserved.';
    } elseif ($isMultiStore) {
        $title = $allSettled && $deliveredStores > 0 ? 'Delivered' : (($anyStoreMoved || $cancelledStores > 0) ? 'Order in progress' : 'Order received');
        $statusTone = $allSettled && $deliveredStores > 0 ? 'good' : (($anyStoreMoved || $cancelledStores > 0) ? 'moving' : 'warn');
        $statusIcon = $statusTone === 'good' ? 'check-circle' : ($statusTone === 'moving' ? 'truck' : 'clock');
        [$pillLabel, $pillTone] = match (true) {
            $partialDelivered => [$deliveredStores.' of '.$activeCount.' delivered', 'neutral'],
            $activeCount > 0 && $deliveredStores === $activeCount && $cancelledStores === 0 => ['Delivered', 'good'],
            $activeCount > 0 && $deliveredStores === $activeCount => [$deliveredStores.' of '.$storeCount.' delivered', 'neutral'],
            $cancelledStores > 0 => ['Partially cancelled', 'warn'],
            default => [$statusLabels[$order->status], $statusTones[$order->status]],
        };
        $statusSub = $storeSummary;
    } else {
        $title = match ($order->status) {
            'pending' => 'Order received',
            'confirmed', 'processing' => 'Preparing your order',
            'shipped' => 'On the way',
            'delivered' => 'Delivered',
            default => 'Order received',
        };
        $statusTone = match ($order->status) {
            'pending' => 'warn',
            'confirmed', 'processing', 'shipped' => 'moving',
            'delivered' => 'good',
            default => 'neutral',
        };
        $statusIcon = match ($order->status) {
            'pending' => 'clock',
            'confirmed', 'processing' => 'box',
            'shipped' => 'truck',
            'delivered' => 'check-circle',
            default => 'receipt',
        };
        [$pillLabel, $pillTone] = [$statusLabels[$order->status], $statusTones[$order->status]];
        $statusSub = $storeSummary.($firstReviewable ? ' Review what you received.' : '');
    }

    $currentStep = match ($order->status) {
        'pending' => 1,
        'confirmed', 'processing' => 2,
        'shipped' => 3,
        'delivered' => 4,
        default => 0,
    };
    $allActiveShipped = $activeCount > 0 && $activeStores->every(fn ($sellerOrder): bool => $sellerOrder->shipped_at !== null);
    $shippedDate = $allActiveShipped ? $activeStores->pluck('shipped_at')->filter()->max() : null;
    $deliveredDate = $isDelivered ? $activeStores->pluck('delivered_at')->filter()->max() : null;

    $markPalettes = [
        ['#e8eef8', '#31507c'],
        ['#e9f1e8', '#3f6240'],
        ['#fdf4e3', '#8a5a13'],
        ['#f1eafc', '#5b3f86'],
    ];
@endphp

<header @class(['dialog-head', 'no-tracker' => $isCancelled || $isDelivered])>
    <h2 id="dlgTitle" class="sr-only">Order #{{ $order->number }}</h2>
    <div class="head-top">
        <div class="head-id">
            <span class="eyebrow">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 3h14v18l-7-4-7 4V3Z"/><path d="M9 8h6M9 12h4"/></svg>
                Order
            </span>
            <span class="order-ref" title="{{ $order->number }}">{{ \App\Models\Order::shortNumber($order->number) }}</span>
            <button type="button" class="copy-ref" x-data="{ copied: false }" @click="navigator.clipboard?.writeText(@js($order->number)); copied = true; setTimeout(() => copied = false, 1500)" :class="copied && 'is-copied'" :aria-label="copied ? 'Order number copied' : 'Copy full order number'" title="Copy full order number">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="8" width="11" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h2"/></svg>
            </button>
        </div>
    </div>

    <div class="head-status">
        <span class="status-icon {{ $statusTone }}" aria-hidden="true">
            @switch($statusIcon)
                @case('card')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3"/></svg>
                    @break
                @case('clock')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    @break
                @case('box')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m4 8 8-4 8 4v9l-8 4-8-4V8Z"/><path d="m4 8 8 4 8-4M12 12v9"/></svg>
                    @break
                @case('truck')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h11v10H3z"/><path d="M14 9h4l3 3v4h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/></svg>
                    @break
                @case('check-circle')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>
                    @break
                @case('x-circle')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/></svg>
                    @break
                @default
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3h14v18l-7-4-7 4V3Z"/><path d="M9 8h6M9 12h4"/></svg>
            @endswitch
        </span>
        <div class="status-copy">
            <div class="status-line">
                <p class="status-title">{{ $title }}</p>
                @if ($pillLabel !== $title)<span class="pill pill-{{ $pillTone }}"><i aria-hidden="true"></i>{{ $pillLabel }}</span>@endif
            </div>
            <p class="status-sub">{{ $statusSub }}</p>
            <p class="head-meta" id="dlgMeta">Placed {{ $order->created_at->format('M j, Y · g:i A') }}<span class="meta-dot" aria-hidden="true">·</span>{{ $itemCount }} {{ Str::plural('item', $itemCount) }}<span class="meta-dot" aria-hidden="true">·</span>₱{{ number_format($order->total, 2) }}</p>
        </div>
    </div>

    @if (! $isCancelled && ! $isDelivered)
        <ol class="tracker" style="--steps: 4" aria-label="Order progress">
            @foreach (['Placed', 'Confirmed', 'Shipped', 'Delivered'] as $index => $label)
                @php
                    $step = $index + 1;
                    $isFinal = $label === 'Delivered';
                    $state = match (true) {
                        $step < $currentStep => 'done',
                        $step === $currentStep && $isDelivered => 'done',
                        $step === $currentStep => 'current',
                        $isFinal && $partialDelivered => 'partial',
                        default => 'upcoming',
                    };
                    $date = match ($step) {
                        1 => $order->created_at->format('M j'),
                        3 => $shippedDate?->format('M j'),
                        4 => $isDelivered ? $deliveredDate?->format('M j') : ($partialDelivered ? $deliveredStores.' of '.$activeCount : null),
                        default => null,
                    };
                @endphp
                <li class="tracker-step {{ $state }} {{ $isFinal && $state === 'done' ? 'final' : '' }}" @if ($state === 'current') aria-current="step" @endif>
                    <span class="node" aria-hidden="true">
                        @if ($state === 'done')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                        @endif
                    </span>
                    <span class="step-label">{{ $label }}@if ($state === 'current')<span class="sr-only"> (current step)</span>@endif</span>
                    @if ($date)<span class="step-date">{{ $date }}</span>@endif
                </li>
            @endforeach
        </ol>
    @endif
</header>

<div class="dialog-body">
    @if ($isCancelled)
        <p class="cancelled-line">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/></svg>
            Order cancelled
        </p>
        @if ($refundSent)
            <section class="notice notice-info">
                <span class="notice-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12a8 8 0 1 0 3-6.2"/><path d="M4 4v5h5"/></svg></span>
                <div class="notice-body">
                    <p class="notice-title">Refund on its way</p>
                    <p class="notice-copy">₱{{ number_format($order->total, 2) }} was refunded to {{ $methodLabel }}. It can take 3–5 business days to appear.</p>
                </div>
            </section>
        @elseif ($refundPending)
            <section class="notice notice-info">
                <span class="notice-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12a8 8 0 1 0 3-6.2"/><path d="M4 4v5h5"/></svg></span>
                <div class="notice-body">
                    <p class="notice-title">Refund is being processed</p>
                    <p class="notice-copy">The stores cancelled their parts. Your refund is sent through PayMongo and can take 3–5 business days to appear.</p>
                </div>
            </section>
        @endif
    @endif

    @if ($awaitingOnlinePayment)
        <section class="notice notice-warn">
            <span class="notice-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
            <div class="notice-body">
                <p class="notice-title">Pay ₱{{ number_format($totalWithFee, 2) }} to confirm this order</p>
                <p class="notice-copy">Your items are reserved until {{ $payBy->format('M j, g:i A') }}. Unpaid orders are cancelled and stock is released.</p>
            </div>
            <a href="{{ route('orders.pay', $order) }}" class="btn btn-primary notice-action">Pay now</a>
        </section>
    @endif

    <div class="shipments">
        @foreach ($sellerOrders as $sellerOrder)
            @php
                $storeNeedsPayment = $awaitingOnlinePayment && $sellerOrder->status === 'pending' && $sellerOrder->cod_collected_at === null;
                [$storeStatus, $storeTone] = match (true) {
                    $sellerOrder->status === 'cancelled' => ['Cancelled', 'danger'],
                    $sellerOrder->status === 'delivered' => ['Delivered', 'good'],
                    $sellerOrder->status === 'shipped' => ['On the way', 'moving'],
                    $sellerOrder->status === 'processing', $sellerOrder->status === 'confirmed' => ['Preparing', 'neutral'],
                    $storeNeedsPayment => ['Waiting for payment', 'warn'],
                    default => ['Awaiting store', 'neutral'],
                };
                $storeMeta = match (true) {
                    $sellerOrder->status === 'cancelled' => 'Stock restored',
                    $sellerOrder->status === 'delivered' => $sellerOrder->delivered_at ? 'Delivered '.$sellerOrder->delivered_at->format('M j') : 'Delivered',
                    $sellerOrder->status === 'shipped' => $sellerOrder->shipped_at ? 'Shipped '.$sellerOrder->shipped_at->format('M j') : 'On the way',
                    $sellerOrder->status === 'processing', $sellerOrder->status === 'confirmed' => 'Preparing your order',
                    $storeNeedsPayment => 'Waiting for payment',
                    default => 'Awaiting confirmation',
                };
                $words = preg_split('/\s+/', trim($sellerOrder->store->name)) ?: [];
                $initials = \Illuminate\Support\Str::upper(collect($words)->filter()->map(fn ($word): string => \Illuminate\Support\Str::substr($word, 0, 1))->take(2)->join(''));
                if ($initials === '') {
                    $initials = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($sellerOrder->store->name, 0, 2));
                }
                [$markBg, $markInk] = $markPalettes[$loop->index % count($markPalettes)];
                $reviewable = ! in_array($sellerOrder->tenant_id, $ownedTenantIds, true)
                    ? $sellerOrder->items->first(fn ($item): bool => $item->product_id !== null && $item->review === null)
                    : null;
            @endphp
            <section class="shipment">
                <header class="shipment-head">
                    <span class="store-mark" style="--mark-bg:{{ $markBg }};--mark-ink:{{ $markInk }}" aria-hidden="true">{{ $initials }}</span>
                    <div class="store-copy">
                        <p class="store-name">{{ $sellerOrder->store->name }}</p>
                        <p class="store-meta">{{ $storeMeta }}</p>
                    </div>
                    <span class="pill pill-{{ $storeTone }}"><i aria-hidden="true"></i>{{ $storeStatus }}</span>
                </header>
                <ul>
                    @foreach ($sellerOrder->items as $item)
                        @php($itemThumb = $item->image_path ?? $item->product?->images->first()?->path)
                        <li class="item">
                            <span class="thumb" aria-hidden="true">
                                @if ($itemThumb)
                                    <img src="{{ Storage::disk('product_images')->url($itemThumb) }}" alt="" width="44" height="44" loading="lazy">
                                @else
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M4 8h16l-1 12H5L4 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
                                @endif
                            </span>
                            <div class="item-copy">
                                <p class="item-title">{{ $item->product_name }}</p>
                                @if ($item->variant_label)<p class="item-meta">{{ $item->variant_label }}</p>@endif
                                <p class="item-meta">Qty {{ $item->quantity }} × ₱{{ number_format($item->unit_price, 2) }}</p>
                            </div>
                            <span class="item-amount num">₱{{ number_format($item->line_total, 2) }}</span>
                        </li>
                    @endforeach
                </ul>
                <footer class="shipment-foot">
                    <span class="subtotal-label">Store subtotal</span>
                    <strong class="subtotal-value num">₱{{ number_format($sellerOrder->subtotal, 2) }}</strong>
                    @if ($sellerOrder->status === 'shipped')
                        <form method="POST" action="{{ route('orders.received', $sellerOrder) }}" class="shipment-action">
                            @csrf
                            <button type="submit" class="btn btn-outline">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
                                Confirm received
                            </button>
                        </form>
                    @elseif ($sellerOrder->status === 'delivered' && $reviewable)
                        <a href="{{ route('orders.show', $order) }}#review-item-{{ $reviewable->id }}" class="btn btn-outline shipment-action">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 3 2.6 5.3 5.9.9-4.3 4.2 1 5.8-5.2-2.7-5.2 2.7 1-5.8-4.3-4.2 5.9-.9L12 3Z"/></svg>
                            Review items
                        </a>
                    @endif
                </footer>
            </section>
        @endforeach
    </div>

    <div class="facts">
        <section class="fact">
            <p class="fact-label">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-5.6 7-12a7 7 0 1 0-14 0c0 6.4 7 12 7 12Z"/><circle cx="12" cy="9" r="2.4"/></svg>
                Delivery
            </p>
            <p class="fact-main">{{ $order->recipient_name }}</p>
            @if ($order->phone)
                <p class="fact-line" x-data="{ copied: false }">
                    {{ $order->phone }}
                    <button type="button" class="copy-mini" @click="navigator.clipboard?.writeText(@js($order->phone)); copied = true; setTimeout(() => copied = false, 1500)" :class="copied && 'is-copied'" :aria-label="copied ? 'Phone number copied' : 'Copy phone number'" title="Copy phone number">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="8" width="11" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h2"/></svg>
                    </button>
                </p>
            @endif
            <p class="fact-line">{{ $order->address_line }}</p>
            <p class="fact-line">{{ collect([$order->city, $order->province])->filter()->join(', ') }} {{ $order->postal_code }}</p>
            @if ($order->delivery_note)<p class="fact-note">“{{ $order->delivery_note }}”</p>@endif
        </section>
        <section class="fact">
            <p class="fact-label">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3"/></svg>
                Payment
            </p>
            <p class="fact-main">{{ $paymentLine }}</p>
            <p class="fact-line"><span class="dot dot-{{ $paymentTone }}" aria-hidden="true"></span>{{ $paymentNote }}</p>
            @if ($hasFee)<p class="fact-line">Includes a ₱{{ number_format((float) $feeAmount, 2) }} PayMongo payment fee.</p>@endif
        </section>
    </div>

    <section class="receipt" aria-label="Order receipt">
        <dl class="receipt-rows">
            <div class="row"><dt>Subtotal</dt><dd class="num">₱{{ number_format($order->subtotal, 2) }}</dd></div>
            <div class="row"><dt>Shipping</dt><dd class="num">{{ bccomp((string) $order->shipping_amount, '0', 2) === 0 ? 'Free' : '₱'.number_format($order->shipping_amount, 2) }}</dd></div>
        </dl>
        <div class="receipt-total">
            <span class="label">Total</span>
            <strong class="value num">₱{{ number_format($order->total, 2) }}</strong>
        </div>
        <p class="receipt-note">
            @switch($receiptNote[0])
                @case('check')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
                    @break
                @case('refresh')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12a8 8 0 1 0 3-6.2"/><path d="M4 4v5h5"/></svg>
                    @break
                @case('clock')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    @break
                @case('x-circle')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/></svg>
                    @break
                @default
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3"/></svg>
            @endswitch
            {{ $receiptNote[1] }}
        </p>
    </section>
</div>

<footer class="dialog-foot">
    <div class="foot-left">
        <span class="foot-help"><a href="{{ route('orders.show', $order) }}#order-help">Get help</a></span>
    </div>
    <div class="foot-actions">
        <a href="{{ route('orders.show', $order) }}" class="btn btn-ghost open-full">Open full page</a>
        @if ($firstReviewable)
            <a href="{{ route('orders.show', $order) }}#review-item-{{ $firstReviewable->id }}" class="btn btn-primary">Review your items</a>
        @elseif ($firstReviewed)
            <a href="{{ route('orders.show', $order) }}#review-item-{{ $firstReviewed->id }}" class="btn btn-primary">View your reviews</a>
        @else
            <button type="button" class="btn btn-primary" onclick="this.closest('dialog').close()">Done</button>
        @endif
    </div>
</footer>
