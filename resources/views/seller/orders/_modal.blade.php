@php
    $order = $sellerOrder->order;
    $isOnline = $order->payment?->isOnline() ?? false;
    $methodLabel = \App\Models\Payment::METHOD_LABELS[$order->payment?->method] ?? 'Cash on delivery';
    $itemCount = (int) $sellerOrder->items->sum('quantity');
    $isCancelled = $sellerOrder->status === 'cancelled';
    $awaitingOnlinePayment = $isOnline && $sellerOrder->status === 'pending' && $sellerOrder->cod_collected_at === null;
    $transitionLabels = ['confirmed' => 'Confirm order', 'processing' => 'Start preparing', 'shipped' => 'Mark as shipped', 'delivered' => 'Mark as delivered'];
    $nextTransition = $canManageOrders
        ? collect($sellerOrder->available_transitions)->first(fn (string $status): bool => $status !== 'cancelled')
        : null;
    if ($awaitingOnlinePayment) {
        $nextTransition = null;
    }
    $canCancel = $canManageOrders && in_array('cancelled', $sellerOrder->available_transitions, true);
    $canRecordCash = $canManageOrders
        && $sellerOrder->status === 'delivered'
        && $sellerOrder->cod_collected_at === null
        && $order->payment?->method === 'cod';
    $hasPrimaryAction = $nextTransition !== null || $canRecordCash;

    $statusTitle = match (true) {
        $isCancelled => 'Order cancelled',
        $sellerOrder->status === 'pending' => 'New order',
        $sellerOrder->status === 'confirmed' => 'Order confirmed',
        $sellerOrder->status === 'processing' => 'Preparing your order',
        $sellerOrder->status === 'shipped' => 'On the way',
        $canRecordCash => 'Delivered',
        default => 'Delivered',
    };
    $statusSub = match (true) {
        $isCancelled => 'Your part of the order was cancelled and stock was restored.',
        $awaitingOnlinePayment => 'The buyer has not paid online yet. Confirm once PayMongo reports the payment.',
        $sellerOrder->status === 'pending' => 'Confirm to start preparing. Stock is already reserved for you.',
        $sellerOrder->status === 'confirmed' => 'Start preparing, then move the order to processing.',
        $sellerOrder->status === 'processing' => 'Mark shipped once the parcel has left your store.',
        $sellerOrder->status === 'shipped' => 'Mark delivered once the buyer has the parcel.',
        $canRecordCash => 'The buyer has the parcel. Record the cash once you have it.',
        default => 'This order is complete.',
    };
    $statusTone = match (true) {
        $isCancelled => 'danger',
        $awaitingOnlinePayment, $canRecordCash => 'warn',
        $sellerOrder->status === 'delivered' => 'good',
        default => 'moving',
    };
    $statusIcon = match (true) {
        $isCancelled => 'x-circle',
        $awaitingOnlinePayment => 'clock',
        $sellerOrder->status === 'pending' => 'bell',
        $sellerOrder->status === 'confirmed' => 'check-circle',
        $sellerOrder->status === 'processing' => 'box',
        $sellerOrder->status === 'shipped' => 'truck',
        $canRecordCash => 'banknote',
        default => 'check-circle',
    };
    $journeyDate = match (true) {
        $sellerOrder->status === 'delivered' && $sellerOrder->delivered_at => 'Delivered '.$sellerOrder->delivered_at->format('M j, Y'),
        $sellerOrder->status === 'shipped' && $sellerOrder->shipped_at => 'Shipped '.$sellerOrder->shipped_at->format('M j, Y'),
        default => 'Placed '.$sellerOrder->created_at->format('M j, Y · g:i A'),
    };

    $itemsHint = match (true) {
        $isCancelled => 'Stock restored',
        $sellerOrder->status === 'delivered' => 'Delivered',
        default => 'Stock reserved',
    };
    $paymentTone = match (true) {
        $isCancelled => 'neutral',
        $sellerOrder->cod_collected_at !== null => 'good',
        $order->payment?->status === 'failed' => 'danger',
        $isOnline => 'warn',
        $sellerOrder->status === 'delivered' => 'warn',
        default => 'neutral',
    };
    $paymentState = match (true) {
        $isCancelled && ($order->payment?->status === 'refunded' || $sellerOrder->gateway_refund_id !== null) => 'Refunded',
        $isCancelled && $isOnline && $order->payment?->status === 'paid' => 'Refund pending',
        $isCancelled => 'Not collected',
        $sellerOrder->cod_collected_at !== null => 'Paid',
        $order->payment?->status === 'failed' => 'Failed',
        $isOnline => 'Awaiting payment',
        $sellerOrder->status === 'delivered' => 'Cash due',
        default => 'Due on delivery',
    };
    $paymentLine = match (true) {
        $isCancelled && ($order->payment?->status === 'refunded' || $sellerOrder->gateway_refund_id !== null) => 'Refund sent through PayMongo.',
        $isCancelled && $isOnline && $order->payment?->status === 'paid' => 'Refund is being processed through PayMongo.',
        $isCancelled => 'No cash was collected for this order.',
        $sellerOrder->cod_collected_at !== null => ($isOnline ? 'Paid ' : 'Collected ').$sellerOrder->cod_collected_at->format('M j, Y'),
        $order->payment?->status === 'failed' => 'PayMongo could not complete the payment.',
        $isOnline => 'The buyer pays through PayMongo before you confirm.',
        $sellerOrder->status === 'delivered' => 'Collect ₱'.number_format($sellerOrder->total, 2).' from the buyer.',
        default => 'Collect ₱'.number_format($sellerOrder->total, 2).' on delivery.',
    };

    $nextStep = match (true) {
        $awaitingOnlinePayment => [
            'icon' => 'clock',
            'title' => 'Waiting for online payment',
            'copy' => 'PayMongo has not reported the buyer’s payment yet. The order cannot be confirmed until it does.',
            'note' => 'Payment is confirmed automatically.',
        ],
        $nextTransition === 'confirmed' => [
            'icon' => 'check-circle',
            'title' => 'Confirm this order',
            'copy' => 'Confirming accepts the order and tells '.$order->recipient_name.' you are preparing it. Stock is already reserved for you.',
            'note' => $sellerOrder->cod_collected_at !== null
                ? 'Payment confirmed via '.$methodLabel.'.'
                : 'Collect ₱'.number_format($sellerOrder->total, 2).' on delivery.',
        ],
        $nextTransition === 'processing' => [
            'icon' => 'box',
            'title' => 'Start preparing',
            'copy' => 'Move the order to processing when you begin packing. The buyer sees the change right away.',
            'note' => 'The delivery clock starts only when you mark it shipped.',
        ],
        $nextTransition === 'shipped' => [
            'icon' => 'truck',
            'title' => 'Hand the parcel to your courier',
            'copy' => $order->recipient_name.' is notified when you mark it shipped. Do this only once the parcel has left your store.',
            'note' => 'The delivery clock starts when you mark it shipped.',
        ],
        $nextTransition === 'delivered' => [
            'icon' => 'check-circle',
            'title' => 'Deliver to the buyer',
            'copy' => 'Mark delivered after the customer receives it. That starts the return and auto-confirm clocks.',
            'note' => 'Only mark delivered once the buyer has the parcel.',
        ],
        $canRecordCash => [
            'icon' => 'banknote',
            'title' => 'Record cash collected',
            'copy' => 'Confirm you received ₱'.number_format($sellerOrder->total, 2).' from '.$order->recipient_name.'. Only record cash you actually have.',
            'note' => 'This closes your side of the order.',
        ],
        default => null,
    };
@endphp
{{-- Body of the order modal on the orders list. Every form posts back to the list, which reopens this order. --}}
<header class="sc-modal-head sc-order-head">
    <h2 id="order-modal-title" class="sr-only">Order {{ $order->number }}</h2>
    <div class="sc-order-top">
        <div class="sc-order-id">
            <span class="sc-order-eyebrow">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 8 8-4 8 4v9l-8 4-8-4V8Z"/><path d="m4 8 8 4 8-4M12 12v9"/></svg>
                Seller order
            </span>
            <span class="sc-order-ref" title="{{ $order->number }}">{{ \App\Models\Order::shortNumber($order->number) }}</span>
            <span class="sr-only">Order #{{ $order->number }}</span>
            <button type="button" class="sc-order-copy" x-data="{ copied: false }" @click="navigator.clipboard?.writeText(@js($order->number)); copied = true; setTimeout(() => copied = false, 1500)" :class="copied && 'is-copied'" :aria-label="copied ? 'Order number copied' : 'Copy full order number'" title="Copy full order number">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="8" width="11" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h2"/></svg>
            </button>
        </div>
        <div class="sc-order-head-actions">
            <span class="sc-order-nav">
                <button type="button" class="sc-icon-button" @click="step(-1)" :disabled="! hasStep(-1)" aria-label="Previous order"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg></button>
                <button type="button" class="sc-icon-button" @click="step(1)" :disabled="! hasStep(1)" aria-label="Next order"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></button>
            </span>
            <button type="button" class="sc-icon-button" @click="close()" aria-label="Close order details" data-modal-focus><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
        </div>
    </div>

    <div class="sc-order-status">
        <span class="sc-order-status-icon is-{{ $statusTone }}" aria-hidden="true">
            @switch($statusIcon)
                @case('bell')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
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
                @case('banknote')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6 10h.01M18 14h.01"/></svg>
                    @break
                @case('x-circle')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/></svg>
                    @break
                @default
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>
            @endswitch
        </span>
        <div class="sc-order-status-copy">
            <div class="sc-order-status-line">
                <p class="sc-order-status-title">{{ $statusTitle }}</p>
                @if ($canRecordCash)
                    <span class="sc-badge" data-tone="warning">Cash to collect</span>
                @else
                    <x-marketplace.order-status :status="$sellerOrder->status" />
                @endif
            </div>
            <p class="sc-order-sub">{{ $statusSub }}</p>
            <p class="sc-order-meta">
                <span>{{ $journeyDate }}</span>
                <span class="sc-order-meta-dot" aria-hidden="true">·</span>
                <span>{{ $itemCount }} {{ Str::plural('item', $itemCount) }}</span>
                <span class="sc-order-meta-dot" aria-hidden="true">·</span>
                <span>₱{{ number_format($sellerOrder->total, 2) }}</span>
            </p>
        </div>
    </div>

    @if (! $isCancelled)
        @include('seller.orders._steps', ['sellerOrder' => $sellerOrder, 'class' => 'sc-order-tracker'])
    @endif
</header>

<div class="sc-modal-body sc-order-body">
    @if ($isCancelled)
        <div class="sc-order-notice is-danger" role="status">
            <span class="sc-order-notice-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/></svg></span>
            <div>
                <p class="sc-order-notice-title">This order was cancelled</p>
                <p class="sc-order-notice-copy">Only your store’s part of the checkout was cancelled. Both items were returned to stock and the buyer was notified.</p>
            </div>
        </div>
    @endif

    @foreach ($sellerOrder->issues as $issue)
        <div class="sc-notice !block mb-3" data-tone="warning"><p class="font-semibold">{{ str($issue->type)->replace('_', ' ')->title() }} request · {{ ucfirst($issue->status) }}</p><p class="mt-1 whitespace-pre-wrap">{{ $issue->details }}</p>@if ($issue->status === 'open')<p class="mt-1">JMMart support reviews this request. Wait for the decision before preparing this order.</p>@endif</div>
    @endforeach

    @if ($sellerOrder->returnCases->isNotEmpty())
        <div class="sc-notice mb-3" data-tone="info"><span>{{ $sellerOrder->returnCases->count() }} return {{ Str::plural('case', $sellerOrder->returnCases->count()) }} on this order. Respond before the deadline.</span><a href="{{ route('seller.returns.show', $sellerOrder->returnCases->first()) }}">View {{ $sellerOrder->returnCases->count() === 1 ? 'case' : 'cases' }}</a></div>
    @endif

    <div class="sc-order-grid">
        <div class="sc-order-main">
            @if ($nextStep)
                <section class="sc-order-next">
                    <p class="sc-order-next-eyebrow">
                        <span class="sc-order-next-icon" aria-hidden="true">
                            @switch($nextStep['icon'])
                                @case('clock')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                    @break
                                @case('box')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m4 8 8-4 8 4v9l-8 4-8-4V8Z"/><path d="m4 8 8 4 8-4M12 12v9"/></svg>
                                    @break
                                @case('truck')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h11v10H3z"/><path d="M14 9h4l3 3v4h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/></svg>
                                    @break
                                @case('banknote')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6 10h.01M18 14h.01"/></svg>
                                    @break
                                @default
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>
                            @endswitch
                        </span>
                        Next step
                    </p>
                    <h3 class="sc-order-next-title">{{ $nextStep['title'] }}</h3>
                    <p class="sc-order-next-copy">{{ $nextStep['copy'] }}</p>
                    @if ($nextStep['note'])
                        <p class="sc-order-next-note">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
                            {{ $nextStep['note'] }}
                        </p>
                    @endif
                </section>
            @endif

            <section @class(['sc-order-card', 'is-muted' => $isCancelled]) aria-labelledby="order-modal-items">
                <header class="sc-order-card-head">
                    <p class="sc-order-card-title">Items <b>({{ $sellerOrder->items->count() }})</b></p>
                    <span class="sc-order-card-hint">{{ $itemsHint }}</span>
                </header>
                <ul>
                    @foreach ($sellerOrder->items as $item)
                        <li class="sc-order-item">
                            <span class="sc-thumb">@if ($item->product?->images->first())<img src="{{ Storage::disk('product_images')->url($item->product->images->first()->path) }}" alt="" loading="lazy">@endif</span>
                            <div class="sc-order-item-copy">
                                <p class="sc-order-item-title">{{ $item->product_name }}</p>
                                @if ($item->variant_label)<p class="sc-order-item-meta">{{ $item->variant_label }}</p>@endif
                                <p class="sc-order-item-meta">SKU {{ $item->sku }} · Qty {{ $item->quantity }} × ₱{{ number_format($item->unit_price, 2) }}</p>
                            </div>
                            <span class="sc-order-item-amount">₱{{ number_format($item->line_total, 2) }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>

        <aside class="sc-order-rail">
            <section class="sc-order-rail-card" aria-labelledby="order-modal-summary">
                <h3 id="order-modal-summary" class="sc-order-rail-label">Order summary</h3>
                <dl class="sc-order-summary">
                    <div><dt>Subtotal</dt><dd>₱{{ number_format($sellerOrder->subtotal, 2) }}</dd></div>
                    <div><dt>Shipping</dt><dd>{{ bccomp((string) $sellerOrder->shipping_amount, '0', 2) === 0 ? 'Free' : '₱'.number_format($sellerOrder->shipping_amount, 2) }}</dd></div>
                </dl>
                <div class="sc-order-total">
                    <span>Your total</span>
                    <strong>₱{{ number_format($sellerOrder->total, 2) }}</strong>
                </div>
                <p class="sc-order-pay"><span class="sc-order-dot" data-tone="{{ $paymentTone }}" aria-hidden="true"></span>{{ $methodLabel }} · {{ $paymentState }}</p>
                <p class="sc-order-rail-line">{{ $paymentLine }}</p>
            </section>

            <section class="sc-order-rail-card" aria-labelledby="order-modal-delivery">
                <h3 id="order-modal-delivery" class="sc-order-rail-label">Deliver to</h3>
                <p class="sc-order-rail-name">{{ $order->recipient_name }}</p>
                <p class="sc-order-rail-line" x-data="{ copied: false }">
                    {{ $order->phone }}
                    <button type="button" class="sc-order-copy-mini" @click="navigator.clipboard?.writeText(@js($order->phone)); copied = true; setTimeout(() => copied = false, 1500)" :class="copied && 'is-copied'" :aria-label="copied ? 'Phone number copied' : 'Copy phone number'" title="Copy phone number">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="8" width="11" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h2"/></svg>
                    </button>
                </p>
                <p class="sc-order-rail-line">{{ $order->address_line }}, {{ collect([$order->city, $order->province])->filter()->join(', ') }} {{ $order->postal_code }}</p>
                @if ($order->delivery_note)<p class="sc-order-rail-note">“{{ $order->delivery_note }}”</p>@endif
            </section>
        </aside>
    </div>
</div>

<footer class="sc-modal-foot sc-order-foot">
    <div class="sc-order-foot-left">
        <div class="sc-more" x-data="{ open: false }" @keydown.escape.window="open = false">
            <button type="button" class="sc-btn sc-more-btn" @click="open = ! open" :aria-expanded="open" aria-haspopup="menu" aria-label="More order actions">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg>
                <span>More</span>
            </button>
            <div class="sc-more-menu" role="menu" x-show="open" x-cloak @click.outside="open = false">
                <button type="button" class="sc-more-item" role="menuitem" @click="navigator.clipboard?.writeText(@js($order->number)); open = false">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="8" width="11" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h2"/></svg>
                    Copy order number
                </button>
                @if ($hasPrimaryAction)
                    <a href="{{ route('seller.orders.show', $sellerOrder) }}" class="sc-more-item" role="menuitem">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6"/><path d="M20 4 10 14"/><path d="M18 13v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h6"/></svg>
                        Open full page
                    </a>
                @endif
                @if ($canCancel)
                    <div class="sc-more-sep" role="separator"></div>
                    <form method="POST" action="{{ route('seller.orders.update', $sellerOrder) }}" data-confirm-title="Cancel this order?" data-confirm-reference="Order {{ \App\Models\Order::shortNumber($order->number) }}" data-confirm-amount="₱{{ number_format($sellerOrder->total, 2) }}" data-confirm-from="{{ ucfirst($sellerOrder->status) }}" data-confirm-to="Cancelled" data-confirm="Only your store’s part of the checkout is cancelled and its item stock is restored.{{ $isOnline && $sellerOrder->cod_collected_at ? ' The buyer is refunded through PayMongo from your share.' : '' }} This can’t be undone." data-confirm-action="Cancel order" data-confirm-dismiss="Keep order" data-confirm-tone="danger">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="cancelled">
                        <button type="submit" class="sc-more-item is-danger" role="menuitem">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/></svg>
                            Cancel order
                        </button>
                    </form>
                @endif
            </div>
        </div>
        <span class="sc-order-hint max-lg:hidden">Esc to close · ← → to switch orders</span>
    </div>
    <div class="sc-order-foot-actions">
        @if ($conversation)
            <a href="{{ route('messages.show', $conversation) }}" class="seller-outline-button sc-order-secondary">Message buyer</a>
        @endif
        @if ($canManageOrders && $nextTransition)
            <form method="POST" action="{{ route('seller.orders.update', $sellerOrder) }}" class="sc-order-primary-form" @if ($nextTransition === 'shipped') data-confirm-title="Mark this order as shipped?" data-confirm-reference="Order {{ \App\Models\Order::shortNumber($order->number) }}" data-confirm-amount="₱{{ number_format($sellerOrder->total, 2) }}" data-confirm-from="{{ ucfirst($sellerOrder->status) }}" data-confirm-to="Shipped" data-confirm="Your customer is told the parcel has left your store. Do this only once it’s with your courier." data-confirm-action="Mark as shipped" @endif>
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $nextTransition }}">
                <button type="submit" class="seller-primary-button sc-order-primary">{{ $transitionLabels[$nextTransition] }}</button>
            </form>
        @elseif ($canRecordCash)
            <form method="POST" action="{{ route('seller.orders.cod-collection', $sellerOrder) }}" class="sc-order-primary-form" data-confirm-title="Record cash collected?" data-confirm-reference="Order {{ \App\Models\Order::shortNumber($order->number) }}" data-confirm-amount="₱{{ number_format($sellerOrder->total, 2) }}" data-confirm-from="Unpaid" data-confirm-to="Paid" data-confirm="Confirm your store received ₱{{ number_format($sellerOrder->total, 2) }} in cash for this order. Record only cash you actually have." data-confirm-action="Record cash">
                @csrf
                @method('PATCH')
                <button type="submit" class="seller-primary-button sc-order-primary">Record cash collected</button>
            </form>
        @else
            <a href="{{ route('seller.orders.show', $sellerOrder) }}" class="seller-primary-button sc-order-primary">Full page</a>
        @endif
    </div>
</footer>
