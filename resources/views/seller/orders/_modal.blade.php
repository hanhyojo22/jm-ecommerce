@php
    $order = $sellerOrder->order;
    $isOnline = $order->payment?->isOnline() ?? false;
    $methodLabel = \App\Models\Payment::METHOD_LABELS[$order->payment?->method] ?? 'Cash on delivery';
    $isCancelled = $sellerOrder->status === 'cancelled';
    $awaitingOnlinePayment = $isOnline && $sellerOrder->status === 'pending' && $sellerOrder->cod_collected_at === null;
    $isPickup = $order->fulfillment_method === 'pickup';
    $label = fn (string $status): string => \App\Models\SellerOrder::statusLabel($status, $order->fulfillment_method);
    $transitionLabels = ['confirmed' => 'Accept Order', 'processing' => 'Start Preparing', 'shipped' => $isPickup ? 'Ready for Pickup' : 'Out for Delivery', 'delivered' => $isPickup ? 'Mark Picked Up' : 'Mark Delivered'];
    $nextTransition = $canManageOrders
        ? collect($sellerOrder->available_transitions)->first(fn (string $status): bool => $status !== 'cancelled')
        : null;
    $waitingForPrescription = $order->prescription !== null && $order->prescription->status !== 'approved' && ! $isCancelled;
    $canReviewPrescription = auth()->user()->sellerMembership()?->canReviewPrescriptions() ?? false;
    if ($awaitingOnlinePayment || $waitingForPrescription) {
        $nextTransition = null;
    }
    $canCancel = $canManageOrders && in_array('cancelled', $sellerOrder->available_transitions, true);
    $cancelLabel = $sellerOrder->status === 'pending' ? 'Decline' : 'Cancel';
    $canRecordCash = $canManageOrders
        && $sellerOrder->status === 'delivered'
        && $sellerOrder->cod_collected_at === null
        && $order->payment?->method === 'cod';
    $hasPrimaryAction = $nextTransition !== null || $canRecordCash;
    $canCheckDiscountId = auth()->user()->sellerMembership()?->canWorkOrders() ?? false;
    $hasDiscount = bccomp((string) $sellerOrder->discount_amount, '0', 2) > 0;
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
    $progressLabels = ['Order placed', 'Accepted', 'Preparing', $label($sellerOrder->status === 'delivered' ? 'delivered' : 'shipped')];
    $deliveryFee = bccomp((string) $sellerOrder->shipping_amount, '0', 2) === 0 ? 'Free' : '₱'.number_format($sellerOrder->shipping_amount, 2);
@endphp
<header class="sc-premium-head sc-order-head">
    <div class="sc-order-head-main">
        <div class="sc-order-title-row">
            <h2 id="order-modal-title" class="sc-order-ref">Order {{ $order->number }}</h2>
            <button type="button" class="sc-copy-button" x-data="{ copied: false, copy() { if (! navigator.clipboard) { return; } navigator.clipboard.writeText(@js($order->number)).then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000); }); } }" @click="copy()" aria-label="Copy order number">
                <svg x-show="! copied" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="8" width="11" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h2"/></svg>
                <svg x-show="copied" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                <span class="sr-only" aria-live="polite" x-text="copied ? 'Order number copied' : ''"></span>
            </button>
        </div>
        <p class="sc-order-subline">{{ $order->recipient_name }} · {{ $sellerOrder->created_at->format('M j, g:i A') }}</p>
    </div>
    <div class="sc-order-head-end">
        <div class="sc-premium-price">
            <strong>₱{{ number_format($sellerOrder->total, 2) }}</strong>
            <small>{{ $methodLabel }} · {{ $paymentState }}</small>
        </div>
        @if ($canCancel || $hasPrimaryAction || $waitingForPrescription || $awaitingOnlinePayment)
            <div class="sc-order-head-actions">
                @if ($canCancel)
                    <form method="POST" action="{{ route('seller.orders.update', $sellerOrder) }}" data-confirm-title="{{ $cancelLabel }} this order?" data-confirm-reference="Order {{ $order->number }}" data-confirm-amount="₱{{ number_format($sellerOrder->total, 2) }}" data-confirm-from="{{ $label($sellerOrder->status) }}" data-confirm-to="Cancelled" data-confirm="Only your store’s part of the order is cancelled and its item stock is restored.{{ $isOnline && $sellerOrder->cod_collected_at ? ' The buyer is refunded through PayMongo.' : '' }} This cannot be undone." data-confirm-action="{{ $cancelLabel }} order" data-confirm-dismiss="Keep order" data-confirm-tone="danger">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="cancelled">
                        <button type="submit" class="sc-btn sc-btn-danger sc-head-decline">{{ $cancelLabel }}</button>
                    </form>
                @endif
                @if ($waitingForPrescription && $order->prescription->status === 'pending' && $canReviewPrescription)
                    <form method="POST" action="{{ route('seller.orders.prescription.update', $sellerOrder) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="decision" value="approve">
                        <button type="submit" class="seller-primary-button sc-head-primary" aria-label="Approve prescription and accept order">Approve &amp; accept</button>
                    </form>
                @elseif ($waitingForPrescription)
                    <button type="button" class="seller-primary-button sc-head-primary" disabled>{{ $order->prescription->status === 'needs_photo' ? 'Awaiting clearer photo' : 'Awaiting pharmacist' }}</button>
                @elseif ($awaitingOnlinePayment)
                    <button type="button" class="seller-primary-button sc-head-primary" disabled>Awaiting payment</button>
                @elseif ($canManageOrders && $nextTransition === 'shipped' && ! $isPickup)
                    {{-- The full page asks for the rider before the order goes out. --}}
                    <a href="{{ route('seller.orders.show', $sellerOrder) }}" class="seller-primary-button sc-head-primary">{{ $transitionLabels['shipped'] }}</a>
                @elseif ($canManageOrders && $nextTransition)
                    <form method="POST" action="{{ route('seller.orders.update', $sellerOrder) }}" @if ($nextTransition === 'shipped') data-confirm-title="{{ $isPickup ? 'Mark this order ready for pickup?' : 'Mark this order out for delivery?' }}" data-confirm-reference="Order {{ $order->number }}" data-confirm-amount="₱{{ number_format($sellerOrder->total, 2) }}" data-confirm-from="{{ $label($sellerOrder->status) }}" data-confirm-to="{{ $label('shipped') }}" data-confirm="{{ $isPickup ? 'Your customer is told the order is waiting at the counter.' : 'Your customer is told the rider is on the way. Do this only once the rider has the order.' }}" data-confirm-action="{{ $transitionLabels['shipped'] }}" @endif>
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ $nextTransition }}">
                        <button type="submit" class="seller-primary-button sc-head-primary">{{ $transitionLabels[$nextTransition] }}</button>
                    </form>
                @elseif ($canRecordCash)
                    <form method="POST" action="{{ route('seller.orders.cod-collection', $sellerOrder) }}" data-confirm-title="Record cash collected?" data-confirm-reference="Order {{ $order->number }}" data-confirm-amount="₱{{ number_format($sellerOrder->total, 2) }}" data-confirm-from="Unpaid" data-confirm-to="Paid" data-confirm="Confirm your store received ₱{{ number_format($sellerOrder->total, 2) }} in cash for this order. Record only cash you actually have." data-confirm-action="Record cash">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="seller-primary-button sc-head-primary">Record Cash Collected</button>
                    </form>
                @endif
            </div>
        @endif
    </div>
</header>
@if (! $isCancelled)
    <ol class="sc-premium-steps" aria-label="Order progress, step {{ $progressIndex + 1 }} of {{ count($progressLabels) }}">
        @foreach ($progressLabels as $index => $stepLabel)
            @php
                $stepState = $index < $progressIndex ? 'done' : ($index === $progressIndex ? 'current' : 'upcoming');
            @endphp
            <li @class(['sc-order-step', 'is-'.$stepState]) @if ($index === $progressIndex) aria-current="step" @endif>
                <span class="sc-order-step-node" aria-hidden="true">
                    @if ($stepState === 'done')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                    @endif
                </span>
                <span class="sc-order-step-label">{{ $stepLabel }}@if ($stepState === 'done')<span class="sr-only">, done</span>@elseif ($stepState === 'current')<span class="sr-only"> (current step)</span>@endif</span>
            </li>
        @endforeach
    </ol>
@endif
<div class="sc-modal-body sc-premium-body">
    @if ($waitingForPrescription || $awaitingOnlinePayment || $isCancelled || $canRecordCash)
        <div class="sc-premium-notice" data-tone="{{ $isCancelled ? 'danger' : 'warning' }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><h3>{{ $statusTitle }}</h3></div>
    @endif
    @foreach ($sellerOrder->issues as $issue)
        <div class="sc-notice mb-6" data-tone="warning"><div><strong>{{ str($issue->type)->replace('_', ' ')->title() }} · {{ ucfirst($issue->status) }}</strong><p>{{ $issue->details }}</p>@if ($issue->status === 'open')<p>Wait for Everyday Pharmacy support before preparing this order.</p>@endif</div></div>
    @endforeach
    @if ($sellerOrder->returnCases->isNotEmpty())<div class="sc-notice mb-6" data-tone="info"><span>{{ $sellerOrder->returnCases->count() }} return {{ Str::plural('case', $sellerOrder->returnCases->count()) }}</span><a href="{{ route('seller.returns.show', $sellerOrder->returnCases->first()) }}">View returns</a></div>@endif
    <x-input-error :messages="$errors->get('payment')" class="mb-6" />
    @if ($order->prescription !== null)
        <section class="sc-card sc-card-pad sc-order-prescription" aria-label="Prescription review" x-data="{}">
            @include('seller.orders._prescription', ['sellerOrder' => $sellerOrder, 'class' => '', 'approvalInFooter' => true])
        </section>
    @endif

    <div class="sc-order-grid">
        <div class="sc-order-column">
            <section class="sc-card sc-order-receipt" aria-labelledby="order-items-heading">
                <div class="sc-card-head">
                    <div class="min-w-0">
                        <h2 id="order-items-heading" class="sc-card-title">Order items &amp; Billing</h2>
                        <p class="sc-card-note">{{ $sellerOrder->items->sum('quantity') }} {{ Str::plural('item', $sellerOrder->items->sum('quantity')) }} in this store order</p>
                    </div>
                </div>
                <ul class="sc-order-items">
                    @foreach ($sellerOrder->items as $item)
                        <li class="sc-order-item">
                            <span class="sc-order-item-thumb">
                                @if ($item->product?->images->first())
                                    <img src="{{ Storage::disk('product_images')->url($item->product->images->first()->path) }}" alt="" loading="lazy">
                                @else
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m7 17 10-10M5 19l2-2m10-10 2-2M8.5 5.5l10 10M5.5 8.5l10 10"/></svg>
                                @endif
                            </span>
                            <div class="sc-order-item-info">
                                <p class="sc-order-item-name">{{ $item->product_name }}</p>
                                <p class="sc-order-item-meta">{{ $item->quantity }} × ₱{{ number_format($item->unit_price, 2) }}@if ($item->variant_label) · {{ $item->variant_label }}@endif</p>
                            </div>
                            <strong class="sc-order-item-amount">₱{{ number_format($item->line_total, 2) }}</strong>
                        </li>
                    @endforeach
                </ul>
                <dl class="sc-order-receipt-totals">
                    <div><dt>Subtotal</dt><dd>₱{{ number_format($sellerOrder->subtotal, 2) }}</dd></div>
                    @if ($hasDiscount)
                        <div class="is-discount"><dt>{{ \App\Support\SeniorPwdDiscount::CARD_TYPES[$order->discount_card_type] ?? 'SC/PWD' }} discount</dt><dd>−₱{{ number_format($sellerOrder->discount_amount, 2) }}</dd></div>
                    @endif
                    <div><dt>{{ $isPickup ? 'Pickup' : 'Delivery Fee' }}</dt><dd>{{ $deliveryFee }}</dd></div>
                    <div class="is-total"><dt>Total</dt><dd>₱{{ number_format($sellerOrder->total, 2) }}</dd></div>
                </dl>
                <div class="sc-order-delivery">
                    <h3 class="sc-card-title">Customer &amp; Delivery</h3>
                    <div class="sc-customer-lines">
                        <p class="sc-customer-name">{{ $order->recipient_name }}</p>
                        <div class="sc-customer-line">
                            <a href="tel:{{ $order->phone }}" class="sc-customer-phone">{{ $order->phone }}</a>
                            <button type="button" class="sc-copy-button is-small" x-data="{ copied: false, copy() { if (! navigator.clipboard) { return; } navigator.clipboard.writeText(@js($order->phone)).then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000); }); } }" @click="copy()" aria-label="Copy phone number">
                                <svg x-show="! copied" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="8" width="11" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h2"/></svg>
                                <svg x-show="copied" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                                <span class="sr-only" aria-live="polite" x-text="copied ? 'Phone number copied' : ''"></span>
                            </button>
                        </div>
                        @unless ($isPickup)
                            <p class="sc-customer-address">{{ collect([$order->address_line, $order->subdivision, $order->barangay])->filter()->join(', ') }}<br>{{ collect([$order->city, $order->province])->filter()->join(', ') }} {{ $order->postal_code }}</p>
                        @else
                            <p class="sc-customer-address">Pickup order. The customer collects this at the counter.</p>
                        @endunless
                        @if ($order->delivery_note)
                            <p class="sc-customer-note"><span>Delivery note</span>{{ $order->delivery_note }}</p>
                        @endif
                        @if ($sellerOrder->rider_name)
                            <p class="sc-customer-note"><span>Rider</span>{{ $sellerOrder->rider_name }} · {{ $sellerOrder->rider_phone }}</p>
                        @endif
                    </div>
                    @unless ($isPickup)
                        <x-marketplace.delivery-pin :order="$order" stack />
                    @endunless
                </div>
            </section>
        </div>

        <aside class="sc-order-column sc-order-side">
            @if ($hasDiscount)
                @include('seller.orders._id-verification', ['sellerOrder' => $sellerOrder])
            @endif

            <details class="sc-clean-history">
                <summary>Review history</summary>
                @if ($order->prescription)
                    @include('seller.orders._prescription-history', ['prescription' => $order->prescription])
                @else
                    @include('seller.orders._steps', ['sellerOrder' => $sellerOrder])
                @endif
            </details>
        </aside>
    </div>
</div>
<footer class="sc-modal-foot sc-order-foot">
    <div class="sc-order-foot-left">
        <a href="{{ route('seller.orders.show', $sellerOrder) }}" class="sc-link sc-full-page">Full page</a>
    </div>
    <div class="sc-order-foot-actions">
        @if ($canManageOrders && $waitingForPrescription && $order->prescription->status === 'pending')
            <button type="button" class="seller-outline-button" aria-haspopup="dialog" aria-controls="prescription-change-{{ $sellerOrder->id }}" @click="document.getElementById(@js('prescription-change-'.$sellerOrder->id)).showModal()">Request clearer photo</button>
        @endif
        @if ($conversation)
            <a href="{{ route('messages.show', $conversation) }}" class="seller-outline-button sc-order-secondary">Message buyer</a>
        @endif
    </div>
</footer>
