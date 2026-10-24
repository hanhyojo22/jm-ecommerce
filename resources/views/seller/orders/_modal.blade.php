@php
    $guidance = match ($sellerOrder->status) {
        'pending' => 'Confirm this order to begin preparing it.',
        'confirmed' => 'Move the order to processing when preparation has started.',
        'processing' => 'Mark it shipped only after it has left your store.',
        'shipped' => 'Mark it delivered after the customer receives it.',
        'delivered' => $sellerOrder->cod_collected_at ? 'This order is complete.' : 'Record only cash your store actually collected for this order.',
        default => 'This order was cancelled and its stock was restored.',
    };
    $transitionLabels = ['confirmed' => 'Confirm order', 'processing' => 'Start preparing', 'shipped' => 'Mark as shipped', 'delivered' => 'Mark as delivered'];
    $nextTransition = collect($sellerOrder->available_transitions)->first(fn (string $status): bool => $status !== 'cancelled');
    $canCancel = in_array('cancelled', $sellerOrder->available_transitions, true);
    $canRecordCash = $canManageOrders && $sellerOrder->status === 'delivered' && ! $sellerOrder->cod_collected_at;
    $order = $sellerOrder->order;
@endphp
{{-- Body of the order modal on the orders list. Every form posts back to the list, which reopens this order. --}}
<div class="sc-modal-head">
    <div class="min-w-0 flex-1">
        <p class="sc-meta">Placed {{ $sellerOrder->created_at->format('M j, Y · g:i A') }} · Cash on delivery</p>
        <div class="mt-0.5 flex flex-wrap items-center gap-2"><h2 id="order-modal-title" class="text-lg font-semibold text-ink"><x-marketplace.order-number :number="$order->number" /></h2><x-marketplace.order-status :status="$sellerOrder->status" /></div>
    </div>
    <div class="flex items-center gap-1 max-sm:hidden">
        <button type="button" class="sc-icon-button" @click="step(-1)" :disabled="! hasStep(-1)" aria-label="Previous order"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg></button>
        <button type="button" class="sc-icon-button" @click="step(1)" :disabled="! hasStep(1)" aria-label="Next order"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></button>
    </div>
    <button type="button" class="sc-icon-button" @click="close()" aria-label="Close order details" data-modal-focus><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
</div>

<div class="sc-modal-body">
    @include('seller.orders._steps', ['sellerOrder' => $sellerOrder, 'class' => '!mt-0'])
    <p class="sc-guidance mt-4"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg><span>@if ($nextTransition || $canRecordCash)<span class="font-semibold">Next step:</span> @endif{{ $guidance }}</span></p>

    @foreach ($sellerOrder->issues as $issue)
        <div class="sc-notice !block mt-4" data-tone="warning"><p class="font-semibold">{{ str($issue->type)->replace('_', ' ')->title() }} request · {{ ucfirst($issue->status) }}</p><p class="mt-1 whitespace-pre-wrap">{{ $issue->details }}</p>@if ($issue->status === 'open')<p class="mt-1">JMMart support reviews this request. Wait for the decision before preparing this order.</p>@endif</div>
    @endforeach

    @if ($sellerOrder->returnCases->isNotEmpty())
        <div class="sc-notice mt-4" data-tone="info"><span>{{ $sellerOrder->returnCases->count() }} return {{ Str::plural('case', $sellerOrder->returnCases->count()) }} on this order. Respond before the deadline.</span><a href="{{ route('seller.returns.show', $sellerOrder->returnCases->first()) }}">View {{ $sellerOrder->returnCases->count() === 1 ? 'case' : 'cases' }}</a></div>
    @endif

    <section class="mt-6" aria-labelledby="order-modal-items">
        <h3 id="order-modal-items" class="sc-modal-label">Items</h3>
        <ul class="sc-list">
            @foreach ($sellerOrder->items as $item)
                <li class="sc-row items-start py-3">
                    <span class="sc-thumb is-lg">@if ($item->product?->images->first())<img src="{{ Storage::disk('product_images')->url($item->product->images->first()->path) }}" alt="" loading="lazy">@endif</span>
                    <span class="sc-grow"><span class="block font-semibold text-ink">{{ $item->product_name }}</span>@if ($item->variant_label)<span class="block sc-meta">{{ $item->variant_label }}</span>@endif<span class="block text-xs text-muted">SKU {{ $item->sku }} · Qty {{ $item->quantity }} · ₱{{ number_format($item->unit_price, 2) }} each</span></span>
                    <span class="sc-num font-semibold text-ink">₱{{ number_format($item->line_total, 2) }}</span>
                </li>
            @endforeach
        </ul>
        <dl class="sc-sum mt-2 border-t border-[#eef1f5] pt-3">
            <div><dt>Subtotal</dt><dd>₱{{ number_format($sellerOrder->subtotal, 2) }}</dd></div>
            <div><dt>Shipping</dt><dd>₱{{ number_format($sellerOrder->shipping_amount, 2) }}</dd></div>
            <div class="is-total"><dt>Your total</dt><dd>₱{{ number_format($sellerOrder->total, 2) }}</dd></div>
        </dl>
    </section>

    <div class="mt-6 grid gap-4 sm:grid-cols-2">
        <section class="sc-modal-box" aria-labelledby="order-modal-delivery">
            <h3 id="order-modal-delivery" class="sc-modal-label">Deliver to</h3>
            <p class="font-semibold text-ink">{{ $order->recipient_name }}</p>
            <p class="sc-meta" x-data="{ copied: false }">{{ $order->phone }} <button type="button" class="sc-copy" @click="navigator.clipboard?.writeText(@js($order->phone)); copied = true; setTimeout(() => copied = false, 1500)" x-text="copied ? 'Copied' : 'Copy'">Copy</button></p>
            <p class="sc-meta">{{ $order->address_line }}, {{ collect([$order->city, $order->province])->filter()->join(', ') }} {{ $order->postal_code }}</p>
            @if ($order->delivery_note)<p class="mt-2 whitespace-pre-line text-sm text-ink">“{{ $order->delivery_note }}”</p>@endif
        </section>
        <section class="sc-modal-box" aria-labelledby="order-modal-payment">
            <h3 id="order-modal-payment" class="sc-modal-label">Payment</h3>
            <p class="text-ink">Cash on delivery</p>
            <p class="mt-1.5 flex flex-wrap items-center gap-2"><x-marketplace.order-status :status="$sellerOrder->cod_collected_at ? 'paid' : 'pending'" payment /><span class="sc-meta">{{ $sellerOrder->cod_collected_at ? 'Collected '.$sellerOrder->cod_collected_at->format('M j, Y') : 'Collect ₱'.number_format($sellerOrder->total, 2) }}</span></p>
        </section>
    </div>

    <p class="mt-5 text-xs text-muted" x-data="{ copied: false }">Order ID <span class="break-all">{{ $order->number }}</span> <button type="button" class="sc-copy" @click="navigator.clipboard?.writeText(@js($order->number)); copied = true; setTimeout(() => copied = false, 1500)" x-text="copied ? 'Copied' : 'Copy'">Copy</button></p>
</div>

<div class="sc-modal-foot">
    @if ($canCancel)
        <form method="POST" action="{{ route('seller.orders.update', $sellerOrder) }}" x-data @submit="if (! confirm('Cancel this order? This cancels only your store’s part of the checkout and restores its item stock. This cannot be undone.')) $event.preventDefault()">@csrf @method('PATCH')<input type="hidden" name="status" value="cancelled"><button type="submit" class="sc-btn sc-btn-danger !border-transparent">Cancel order</button></form>
    @endif
    <span class="flex-1 max-sm:hidden"></span>
    <span class="text-xs text-muted max-lg:hidden">Esc to close · ← → to switch orders</span>
    <a href="{{ route('seller.orders.show', $sellerOrder) }}" class="seller-outline-button">Full page</a>
    @if ($conversation)<a href="{{ route('messages.show', $conversation) }}" class="seller-outline-button">Message buyer</a>@endif
    @if ($canManageOrders && $nextTransition)
        <form method="POST" action="{{ route('seller.orders.update', $sellerOrder) }}" class="max-sm:flex-1" @if ($nextTransition === 'shipped') x-data @submit="if (! confirm('Mark this order as shipped? Your customer is told it has left your store.')) $event.preventDefault()" @endif>@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $nextTransition }}"><button type="submit" class="seller-primary-button w-full">{{ $transitionLabels[$nextTransition] }}</button></form>
    @elseif ($canRecordCash)
        <form method="POST" action="{{ route('seller.orders.cod-collection', $sellerOrder) }}" class="max-sm:flex-1" x-data @submit="if (! confirm('Record that your store collected the cash for this order?')) $event.preventDefault()">@csrf @method('PATCH')<button type="submit" class="seller-primary-button w-full">Record cash collected</button></form>
    @endif
</div>
