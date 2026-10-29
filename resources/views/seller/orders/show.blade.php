@php
    $guidance = match ($sellerOrder->status) {
        'pending' => 'Confirm this order to begin preparing it.',
        'confirmed' => 'Move the order to processing when preparation has started.',
        'processing' => 'Mark it shipped only after it has left your store.',
        'shipped' => 'Mark it delivered after the customer receives it.',
        'delivered' => 'This seller order is complete.',
        default => 'This seller order was cancelled and its stock was restored.',
    };
    $paymentStatus = $sellerOrder->cod_collected_at ? 'paid' : 'pending';
    $isOnline = $sellerOrder->order->payment?->isOnline() ?? false;
    if ($isOnline && ! $sellerOrder->cod_collected_at && $sellerOrder->status === 'pending') {
        $guidance = 'Waiting for the buyer to pay online. You can confirm once PayMongo reports the payment.';
    }
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="sc-page-head">
            <div>
                <a href="{{ route('seller.orders.index') }}" class="sc-back" aria-label="Back to orders"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>Orders</a>
                <div class="flex flex-wrap items-center gap-3"><h1 class="sc-title">Order details</h1><x-marketplace.order-status :status="$sellerOrder->status" /></div>            </div>
        </div>
    </x-slot>

    <div class="page-shell"><div class="page-container seller-workspace-page sc-stack">
        @if (session('status'))<div class="sc-notice" data-tone="success" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="sc-notice" data-tone="danger" role="alert">{{ $errors->first() }}</div>@endif

        @if ($sellerOrder->returnCases->isNotEmpty())
            <section class="sc-card" aria-labelledby="returns-heading">
                <div class="sc-card-head">
                    <div class="min-w-0"><h2 id="returns-heading" class="sc-card-title">Returns</h2><p class="sc-card-note">{{ $sellerOrder->returnCases->count() }} {{ Str::plural('case', $sellerOrder->returnCases->count()) }} on this store order. Respond before the deadline to keep the resolution on your side.</p></div>
                    <a href="{{ route('seller.returns.index') }}" class="sc-link">All returns</a>
                </div>
                <div class="sc-list">
                    @foreach ($sellerOrder->returnCases as $case)
                        @php
                            $caseAmount = $case->items->reduce(fn (string $sum, $item): string => bcadd($sum, bcmul((string) $item->unit_price, (string) $item->quantity, 2), 2), '0.00');
                            $firstItem = $case->items->first()?->orderItem?->product_name;
                            $otherItems = max(0, $case->items->count() - 1);
                        @endphp
                        <a href="{{ route('seller.returns.show', $case) }}" class="sc-list-item flex flex-wrap items-start justify-between gap-4 hover:bg-[#fbfcfe]">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2"><x-returns.status-badge :status="$case->status" /><x-returns.deadline :case="$case" /></div>
                                <p class="mt-2 text-sm font-semibold text-ink">{{ $case->items->sum('quantity') }} {{ Str::plural('unit', $case->items->sum('quantity')) }}@if ($firstItem) · {{ $firstItem }}@if ($otherItems > 0) <span class="font-normal text-muted">+{{ $otherItems }} more</span>@endif @endif</p>
                                <p class="mt-1 sc-meta">Opened {{ $case->created_at->format('M j, Y') }} · <span class="font-semibold tabular-nums text-ink">₱{{ number_format((float) $caseAmount, 2) }}</span> at stake</p>
                            </div>
                            <span class="sc-link">View case <x-returns.icon name="chevron-right" class="size-4" /></span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($buyerCodRefusals > 0)
            <div class="sc-notice" data-tone="warning" role="status">This buyer refused {{ trans_choice('{1} a cash-on-delivery parcel|[2,*] :count cash-on-delivery parcels', $buyerCodRefusals) }} in the last {{ config('marketplace.cod_refusals.window_days') }} days. Consider calling them to confirm before you ship.</div>
        @endif

        @if ($sellerOrder->issues->isNotEmpty())
            <section class="sc-notice !block" data-tone="warning" aria-labelledby="buyer-requests-heading">
                <h2 id="buyer-requests-heading" class="font-semibold">Requests to JMMart support</h2>
                <p class="mt-1">JMMart support reviews these requests. Wait for its decision before changing this order.</p>
                @foreach ($sellerOrder->issues as $issue)
                    <div class="mt-3 border-t border-amber-200 pt-3"><p class="font-semibold">{{ $issue->type === 'failed_delivery' ? 'Failed delivery (reported by you)' : str($issue->type)->replace('_', ' ')->title() }} · {{ ucfirst($issue->status) }}</p><p class="mt-1 whitespace-pre-wrap">{{ $issue->details }}</p>@if ($issue->resolution_note)<p class="mt-1 font-medium">Support: {{ $issue->resolution_note }}</p>@endif</div>
                @endforeach
            </section>
        @endif

        <div class="sc-grid-detail grid gap-5">
            <div class="sc-stack min-w-0">
                <section class="sc-card sc-card-pad" aria-labelledby="order-status-heading">
                    <p class="break-all sc-meta">Order #{{ $sellerOrder->order->number }} · placed {{ $sellerOrder->created_at->format('M j, Y · g:i A') }}</p>
                    <h2 id="order-status-heading" class="mt-1 sc-card-title">Seller order status</h2>
                    <p class="mt-1 sc-card-note">{{ $guidance }}</p>
                    @include('seller.orders._steps', ['sellerOrder' => $sellerOrder])
                </section>

                <section class="sc-card" aria-labelledby="items-heading">
                    <div class="sc-card-head"><div><h2 id="items-heading" class="sc-card-title">Items</h2><p class="sc-card-note">Purchase-time item and price snapshots.</p></div></div>
                    <div class="sc-list">
                        @foreach ($sellerOrder->items as $item)
                            <div class="sc-list-item sc-row items-start">
                                <span class="sc-thumb is-lg">@if ($item->product?->images->first())<img src="{{ Storage::disk('product_images')->url($item->product->images->first()->path) }}" alt="" loading="lazy">@endif</span>
                                <div class="sc-grow"><p class="font-medium text-ink">{{ $item->product_name }}</p>@if ($item->variant_label)<p class="mt-0.5 text-sm text-ink">{{ $item->variant_label }}</p>@endif<p class="mt-0.5 sc-meta">SKU {{ $item->sku }} · ₱{{ number_format($item->unit_price, 2) }} each</p></div>
                                <div class="text-right text-sm"><p class="font-semibold tabular-nums text-ink">₱{{ number_format($item->line_total, 2) }}</p><p class="mt-0.5 sc-meta">Qty {{ $item->quantity }}</p></div>
                            </div>
                        @endforeach
                    </div>
                    <dl class="sc-sum border-t border-line px-5 py-4">
                        <div><dt>Items</dt><dd>₱{{ number_format($sellerOrder->subtotal, 2) }}</dd></div>
                        <div><dt>Shipping</dt><dd>₱{{ number_format($sellerOrder->shipping_amount, 2) }}</dd></div>
                        <div class="is-total"><dt>Your order total</dt><dd>₱{{ number_format($sellerOrder->total, 2) }}</dd></div>
                    </dl>
                </section>
            </div>

            <aside class="sc-stack min-w-0">
                @if ($canManageOrders && count($sellerOrder->available_transitions))
                    <section x-data="{ confirmation: null }" class="sc-card sc-card-pad" aria-labelledby="next-action-heading">
                        <h2 id="next-action-heading" class="sc-card-title">Next action</h2>
                        <p class="mt-1 sc-card-note">Only valid transitions for this order are available.</p>
                        <div class="mt-4 grid gap-2">
                            @foreach ($sellerOrder->available_transitions as $transition)
                                @continue($transition === 'confirmed' && $isOnline && ! $sellerOrder->cod_collected_at)
                                @if (in_array($transition, ['shipped', 'cancelled'], true))
                                    <button type="button" @click="confirmation = '{{ $transition }}'; $nextTick(() => $refs.confirmation.showModal())" class="{{ $transition === 'cancelled' ? 'sc-btn sc-btn-danger' : 'seller-primary-button' }} w-full">{{ $transition === 'shipped' ? 'Mark as shipped' : 'Cancel order' }}</button>
                                @else
                                    <form method="POST" action="{{ route('seller.orders.update', $sellerOrder) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $transition }}"><button type="submit" class="seller-primary-button w-full">Mark as {{ $transition }}</button></form>
                                @endif
                            @endforeach
                        </div>
                        <dialog x-ref="confirmation" @close="confirmation = null" class="w-[calc(100%-2rem)] max-w-md rounded-lg border-0 p-0 shadow-2xl backdrop:bg-slate-950/40">
                            <div class="bg-white p-6">
                                <h3 class="text-lg font-semibold text-ink" x-text="confirmation === 'shipped' ? 'Mark order as shipped?' : 'Cancel this order?'"></h3>
                                <p class="mt-2 text-sm leading-6 text-muted" x-text="confirmation === 'shipped' ? 'This tells your customer the order has left your store. You can only move it to delivered afterward.' : 'This cancels only your store’s part of the checkout and restores its item stock. This cannot be undone.'"></p>
                                <div class="mt-6 flex justify-end gap-2">
                                    <button type="button" @click="$refs.confirmation.close()" class="seller-outline-button">Keep editing</button>
                                    <form method="POST" action="{{ route('seller.orders.update', $sellerOrder) }}">@csrf @method('PATCH')<input type="hidden" name="status" :value="confirmation">
                                        <button type="submit" class="sc-btn text-white" :class="confirmation === 'cancelled' ? 'bg-rose-700 hover:bg-rose-800' : 'bg-brand hover:bg-brand-hover'" x-text="confirmation === 'shipped' ? 'Mark shipped' : 'Cancel order'"></button></form>
                                </div>
                            </div>
                        </dialog>
                    </section>
                @endif

                @if ($canManageOrders && $sellerOrder->status === 'shipped' && ! $sellerOrder->issues->contains('status', 'open'))
                    <section class="sc-card sc-card-pad" aria-labelledby="failed-delivery-heading">
                        <h2 id="failed-delivery-heading" class="sc-card-title">Parcel didn't arrive?</h2>
                        <p class="mt-1 sc-card-note">If the buyer refused it or the courier returned it, tell JMMart. Otherwise this order is marked delivered on {{ ($sellerOrder->shipped_at ?? now())->copy()->addDays((int) config('marketplace.orders.auto_deliver_days'))->format('M j') }} and charged its platform fee.</p>
                        <details class="group mt-3">
                            <summary class="sc-btn w-full cursor-pointer list-none justify-center">Report failed delivery</summary>
                            <form method="POST" action="{{ route('seller.orders.failed-delivery', $sellerOrder) }}" class="mt-3 grid gap-3">@csrf
                                <div class="sc-field"><label for="failed-delivery-details">What happened?</label><textarea id="failed-delivery-details" name="details" rows="3" required minlength="10" maxlength="2000" class="sc-input" placeholder="For example: buyer refused the parcel, courier returned it on Oct 20.">{{ old('details') }}</textarea></div>
                                <button type="submit" class="seller-primary-button w-full">Send to JMMart support</button>
                            </form>
                        </details>
                    </section>
                @endif

                <section class="sc-card sc-card-pad" aria-labelledby="delivery-heading">
                    <h2 id="delivery-heading" class="sc-card-title">Delivery</h2>
                    <dl class="sc-dl mt-4">
                        <div><dt>Recipient</dt><dd class="font-medium">{{ $sellerOrder->order->recipient_name }}</dd></div>
                        <div><dt>Phone</dt><dd class="font-medium">{{ $sellerOrder->order->phone }}</dd></div>
                        <div><dt>Address</dt><dd>{{ $sellerOrder->order->address_line }}<br>{{ collect([$sellerOrder->order->city, $sellerOrder->order->province])->filter()->join(', ') }} {{ $sellerOrder->order->postal_code }}</dd></div>
                        @if ($sellerOrder->order->delivery_note)<div><dt>Delivery note</dt><dd class="whitespace-pre-line">{{ $sellerOrder->order->delivery_note }}</dd></div>@endif
                    </dl>
                </section>

                <section class="sc-card sc-card-pad" aria-labelledby="payment-heading">
                    <h2 id="payment-heading" class="sc-card-title">Payment</h2>
                    <dl class="sc-dl mt-4">
                        <div><dt>Method</dt><dd class="font-medium">{{ \App\Models\Payment::METHOD_LABELS[$sellerOrder->order->payment?->method] ?? 'Cash on delivery' }}</dd></div>
                        <div><dt>Payment status</dt><dd class="mt-1"><x-marketplace.order-status :status="$paymentStatus" payment /></dd></div>
                        @if ($isOnline && $sellerOrder->cod_collected_at)<div><dt>Your share</dt><dd class="font-medium tabular-nums">₱{{ number_format(\App\Actions\StartOnlinePayment::storeShare($sellerOrder, $sellerOrder->order), 2) }} <span class="text-muted">after JMMart’s fee</span></dd></div>@endif
                        @if ($sellerOrder->gateway_refund_id)<div><dt>Refund</dt><dd class="font-medium">Refunded through PayMongo</dd></div>@endif
                    </dl>
                    <x-input-error :messages="$errors->get('payment')" class="mt-3" />
                    @if ($isOnline)
                        @if ($sellerOrder->cod_collected_at)
                            <p class="mt-4 text-sm font-semibold text-success">Paid online {{ $sellerOrder->cod_collected_at->format('M j, Y g:i A') }}. Your share goes to your PayMongo account.</p>
                        @endif
                    @elseif ($sellerOrder->cod_collected_at)
                        <p class="mt-4 text-sm font-semibold text-success">COD collected {{ $sellerOrder->cod_collected_at->format('M j, Y g:i A') }}</p>
                    @elseif ($canManageOrders && $sellerOrder->status === 'delivered')
                        <form method="POST" action="{{ route('seller.orders.cod-collection', $sellerOrder) }}" class="mt-4 border-t border-line pt-4">@csrf @method('PATCH')
                            <p class="sc-meta">Record only cash your store actually collected for this seller order.</p>
                            <button class="seller-primary-button mt-3 w-full">Record COD collected</button>
                        </form>
                    @endif
                </section>
            </aside>
        </div>
    </div></div>
</x-app-layout>
