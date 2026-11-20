@php
    $order = $sellerOrder->order;
    $isOnline = $order->payment?->isOnline() ?? false;
    $methodLabel = \App\Models\Payment::METHOD_LABELS[$order->payment?->method] ?? 'Cash on delivery';
    $itemCount = (int) $sellerOrder->items->sum('quantity');
    $isCancelled = $sellerOrder->status === 'cancelled';
    $awaitingOnlinePayment = $isOnline && $sellerOrder->status === 'pending' && $sellerOrder->cod_collected_at === null;
    $isPickup = $order->fulfillment_method === 'pickup';
    $label = fn (string $status): string => \App\Models\SellerOrder::statusLabel($status, $order->fulfillment_method);
    $transitionLabels = ['confirmed' => 'Accept order', 'processing' => 'Start preparing', 'shipped' => $isPickup ? 'Ready for pickup' : 'Out for delivery', 'delivered' => $isPickup ? 'Mark picked up' : 'Mark delivered'];
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

    $waitingForPrescription = $order->prescription !== null && $order->prescription->status !== 'approved' && ! $isCancelled;
    $statusTitle = match (true) {
        $waitingForPrescription => $order->prescription->status === 'needs_photo' ? 'Waiting for a clearer photo' : 'Waiting for pharmacist approval',
        $isCancelled => 'Cancelled',
        $awaitingOnlinePayment => 'Awaiting payment',
        $canRecordCash => 'Cash to collect',
        default => $label($sellerOrder->status),
    };
    $paymentState = match (true) {
        $isCancelled && ($order->payment?->status === 'refunded' || $sellerOrder->gateway_refund_id !== null) => 'Refunded',
        $isCancelled && $isOnline && $order->payment?->status === 'paid' => 'Refund pending',
        $isCancelled => 'Not collected',
        $sellerOrder->cod_collected_at !== null => 'Paid',
        $order->payment?->status === 'failed' => 'Failed',
        $isOnline => 'Awaiting payment',
        $sellerOrder->status === 'delivered' => 'Cash due',
        default => 'Unpaid',
    };
    $progressIndex = match ($sellerOrder->status) { 'pending' => 0, 'confirmed' => 1, 'processing' => 2, default => 3 };
    $progressLabels = [$waitingForPrescription ? 'Prescription review' : 'Order placed', 'Accepted', 'Preparing', $label($sellerOrder->status === 'delivered' ? 'delivered' : 'shipped')];
@endphp
<header class="sc-premium-head">
    <div><h2 id="order-modal-title" class="sc-order-ref" title="{{ $order->number }}">Order {{ \App\Models\Order::shortNumber($order->number) }}</h2><span class="sr-only">Order {{ $order->number }}</span><p>{{ $order->recipient_name }} · {{ $sellerOrder->created_at->format('M j, g:i A') }}</p></div>
    <div class="sc-premium-price"><strong>₱{{ number_format($sellerOrder->total, 2) }}</strong><small>{{ $methodLabel }} · {{ $paymentState }}</small></div>
</header>
@if (! $isCancelled)
    <ol class="sc-premium-steps" aria-label="Order progress">
        @foreach ($progressLabels as $index => $stepLabel)<li @if ($index === $progressIndex) aria-current="step" @endif>{{ $stepLabel }}</li>@endforeach
    </ol>
@endif
<div class="sc-modal-body sc-premium-body">
    @if ($waitingForPrescription || $awaitingOnlinePayment || $isCancelled || $canRecordCash)
        <div class="sc-premium-notice" data-tone="{{ $isCancelled ? 'danger' : 'warning' }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><h3>{{ $statusTitle }}</h3></div>
    @endif
    @foreach ($sellerOrder->issues as $issue)
        <div class="sc-notice mb-4" data-tone="warning"><div><strong>{{ str($issue->type)->replace('_', ' ')->title() }} · {{ ucfirst($issue->status) }}</strong><p>{{ $issue->details }}</p>@if ($issue->status === 'open')<p>Wait for Everyday Pharmacy support before preparing this order.</p>@endif</div></div>
    @endforeach
    @if ($sellerOrder->returnCases->isNotEmpty())<div class="sc-notice mb-4" data-tone="info"><span>{{ $sellerOrder->returnCases->count() }} return {{ Str::plural('case', $sellerOrder->returnCases->count()) }}</span><a href="{{ route('seller.returns.show', $sellerOrder->returnCases->first()) }}">View returns</a></div>@endif
    @include('seller.orders._documents', ['sellerOrder' => $sellerOrder, 'approvalInFooter' => true])
    <div class="sc-premium-order-info">
        <section class="sc-clean-items" aria-label="Order items"><h3>Order items</h3><ul>
            @foreach ($sellerOrder->items as $item)
                <li class="sc-clean-item"><span class="sc-premium-item-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m12 3 9 5v9l-9 5-9-5V8l9-5ZM3 8l9 5 9-5M12 13v9"/></svg></span><div><p>{{ $item->product_name }}</p><small>{{ $item->quantity }} × ₱{{ number_format($item->unit_price, 2) }}@if ($item->variant_label) · {{ $item->variant_label }}@endif</small></div><strong>₱{{ number_format($item->line_total, 2) }}</strong></li>
            @endforeach
        </ul><p class="sc-meta">{{ $isPickup ? 'Pickup' : 'Delivery' }} · {{ bccomp((string) $sellerOrder->shipping_amount, '0', 2) === 0 ? 'Free' : '₱'.number_format($sellerOrder->shipping_amount, 2) }}</p></section>
        <section class="sc-premium-customer"><h3>Customer</h3><p>{{ $order->recipient_name }}</p><a href="tel:{{ $order->phone }}">{{ $order->phone }}</a>@unless ($isPickup)<p class="sc-meta">{{ collect([$order->address_line, $order->subdivision, $order->barangay])->filter()->join(', ') }}, {{ collect([$order->city, $order->province])->filter()->join(', ') }} {{ $order->postal_code }}</p>@endunless @if ($order->delivery_note)<p class="sc-meta">{{ $order->delivery_note }}</p>@endif @unless ($isPickup)<x-marketplace.delivery-pin :order="$order" />@endunless</section>
    </div>
    @if (bccomp((string) $sellerOrder->discount_amount, '0', 2) > 0)
        <dl class="sc-premium-costs" aria-label="Discount summary"><div><dt>Subtotal</dt><dd>₱{{ number_format($sellerOrder->subtotal, 2) }}</dd></div><div><dt>{{ $order->discount_card_type === 'senior' ? 'SC discount' : 'PWD discount' }}</dt><dd>−₱{{ number_format($sellerOrder->discount_amount, 2) }}</dd></div><div><dt>Total</dt><dd>₱{{ number_format($sellerOrder->total, 2) }}</dd></div></dl>
    @endif
    <details class="sc-clean-history"><summary>Review history</summary>
        @if ($order->prescription)@include('seller.orders._prescription-history', ['prescription' => $order->prescription])@else @include('seller.orders._steps', ['sellerOrder' => $sellerOrder])@endif
        @if ($canManageOrders && bccomp((string) $sellerOrder->discount_amount, '0', 2) > 0)
            <p class="sc-meta">{{ $order->discount_card_name }} · {{ $order->discount_card_number }}</p>
            @unless (! ($canSupervise ?? false) || in_array($sellerOrder->status, ['delivered', 'cancelled'], true))<form method="POST" action="{{ route('seller.orders.senior-pwd-discount.destroy', $sellerOrder) }}" data-confirm="Remove the discount and charge full price?">@csrf @method('DELETE')<button type="submit" class="sc-link text-danger">Remove discount</button></form>@endunless
        @endif
    </details>
</div>
<footer class="sc-modal-foot sc-order-foot">
    <div class="sc-order-foot-left">
        <div class="sc-more" x-data="{ open: false }" @keydown.escape.window="open = false">
            <button type="button" class="sc-btn sc-more-btn" @click="open = ! open" :aria-expanded="open" aria-haspopup="menu" aria-label="More order actions">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg>
                <span class="sr-only">More</span>
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
                    <form method="POST" action="{{ route('seller.orders.update', $sellerOrder) }}" data-confirm-title="Cancel this order?" data-confirm-reference="Order {{ \App\Models\Order::shortNumber($order->number) }}" data-confirm-amount="₱{{ number_format($sellerOrder->total, 2) }}" data-confirm-from="{{ $label($sellerOrder->status) }}" data-confirm-to="Cancelled" data-confirm="Only your store’s part of the checkout is cancelled and its item stock is restored.{{ $isOnline && $sellerOrder->cod_collected_at ? ' The buyer is refunded through PayMongo from your share.' : '' }} This can’t be undone." data-confirm-action="Cancel order" data-confirm-dismiss="Keep order" data-confirm-tone="danger">
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

    </div>
    <div class="sc-order-foot-actions">
        @if ($canManageOrders && $waitingForPrescription && $order->prescription->status === 'pending')
            <button type="button" class="seller-outline-button" aria-haspopup="dialog" aria-controls="prescription-change-{{ $sellerOrder->id }}" @click="document.getElementById(@js('prescription-change-'.$sellerOrder->id)).showModal()">Request clearer photo</button>
        @endif
        @if ($conversation)
            <a href="{{ route('messages.show', $conversation) }}" class="seller-outline-button sc-order-secondary">Message buyer</a>
        @endif
        @if ($waitingForPrescription && $order->prescription->status === 'pending' && (auth()->user()->sellerMembership()?->canReviewPrescriptions() ?? false))
            <form method="POST" action="{{ route('seller.orders.prescription.update', $sellerOrder) }}" class="sc-order-primary-form">
                @csrf
                @method('PATCH')
                <input type="hidden" name="decision" value="approve">
                <button type="submit" class="seller-primary-button sc-order-primary" aria-label="Approve prescription and accept order">Approve &amp; accept</button>
            </form>
        @elseif ($waitingForPrescription)
            <button type="button" class="seller-outline-button" disabled>{{ $order->prescription->status === 'needs_photo' ? 'Awaiting clearer photo' : 'Awaiting pharmacist' }}</button>
        @elseif ($canManageOrders && $nextTransition === 'shipped' && ! $isPickup)
            {{-- The full page asks for the rider before the order goes out. --}}
            <a href="{{ route('seller.orders.show', $sellerOrder) }}" class="seller-primary-button sc-order-primary">{{ $transitionLabels['shipped'] }}…</a>
        @elseif ($canManageOrders && $nextTransition)
            <form method="POST" action="{{ route('seller.orders.update', $sellerOrder) }}" class="sc-order-primary-form" @if ($nextTransition === 'shipped') data-confirm-title="{{ $isPickup ? 'Mark this order ready for pickup?' : 'Mark this order out for delivery?' }}" data-confirm-reference="Order {{ \App\Models\Order::shortNumber($order->number) }}" data-confirm-amount="₱{{ number_format($sellerOrder->total, 2) }}" data-confirm-from="{{ $label($sellerOrder->status) }}" data-confirm-to="{{ $label('shipped') }}" data-confirm="{{ $isPickup ? 'Your customer is told the order is waiting at the counter.' : 'Your customer is told the rider is on the way. Do this only once the rider has the order.' }}" data-confirm-action="{{ $transitionLabels['shipped'] }}" @endif>
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
            <a href="{{ route('seller.orders.show', $sellerOrder) }}" class="sc-link sc-full-page">Full page</a>
        @endif
    </div>
</footer>
