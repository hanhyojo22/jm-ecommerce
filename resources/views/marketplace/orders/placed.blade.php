<x-app-layout>
    @php
        $method = $order->payment?->method ?? 'cod';
        $isCod = ! ($order->payment?->isOnline() ?? false);
        // PayMongo's webhook marks the payment paid; until then the buyer may have just paid, or not yet.
        $isPaid = ! $isCod && $order->payment->status === 'paid';
        $methodLabel = \App\Models\Payment::METHOD_LABELS[$method];
        $storeCount = $order->sellerOrders->count();
        $itemCount = $order->sellerOrders->sum(fn ($sellerOrder) => $sellerOrder->items->sum('quantity'));
        $total = '₱'.number_format($order->total, 2);
        $fee = (string) ($order->payment?->fee_amount ?? '0.00');
        $charged = '₱'.number_format(bcadd((string) $order->total, $fee, 2), 2);
        $firstName = Str::before(trim((string) $order->recipient_name), ' ');
        $deadline = $isCod ? null : $order->created_at->copy()->addHours((int) config('marketplace.online_payment.payment_hours'))->format('M j, g:i A');
        $stores = $storeCount === 1 ? 'the store' : ($storeCount === 2 ? 'both stores' : 'all '.$storeCount.' stores');
        [$steps, $current] = match (true) {
            $isCod => [[['Placed', $order->created_at->format('M j, g:i A')], ['Store confirms', 'Sets its delivery timing'], ['Shipped', null], ['Delivered', 'Pay the courier']], 1],
            $isPaid => [[['Placed', $order->created_at->format('M j, g:i A')], ['Paid', 'With '.$methodLabel], ['Store confirms', 'Sets its delivery timing'], ['Shipped & delivered', null]], 2],
            default => [[['Placed', $order->created_at->format('M j, g:i A')], ['You pay online', 'By '.$deadline], ['Store confirms', 'Once your payment arrives'], ['Shipped & delivered', null]], 1],
        };
        $arrow = '<svg class="size-[13px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-5-5 5 5-5 5"/></svg>';
    @endphp

    <div class="page-shell min-h-[calc(100dvh-4.5rem)] pb-24">
        <div class="page-container grid grid-cols-1 items-start gap-x-12 gap-y-10 lg:grid-cols-[minmax(0,1fr)_360px]">

            {{-- The confirmation and the next move lead; the progress track belongs to the same thought. --}}
            <section class="min-w-0" aria-labelledby="placed-heading">
                <h1 id="placed-heading" class="flex items-center gap-3 text-[44px] font-semibold leading-[1.05] tracking-[-0.035em] text-ink max-lg:text-[38px] max-sm:text-[30px]">
                    <svg class="size-[0.72em] shrink-0 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke-width="1.6"/><path stroke-linecap="round" stroke-linejoin="round" d="m7.5 12.3 3 3 6-6.3"/></svg>
                    {{ $isPaid ? 'Payment successful' : 'Order placed' }}
                </h1>
                <p class="mt-4 max-w-[52ch] text-[17px] leading-[1.55] text-ink max-sm:text-[15.5px]">
                    @if($isCod)
                        Thanks, {{ $firstName }}. {{ ucfirst($stores) }} will confirm and ship, and you pay <strong class="font-semibold">{{ $total }}</strong> to the courier on delivery.
                    @elseif($isPaid)
                        Thanks, {{ $firstName }}. We received <strong class="font-semibold">{{ $charged }}</strong> by {{ $methodLabel }}. {{ ucfirst($stores) }} will confirm and ship.
                    @else
                        Thanks, {{ $firstName }}. Your items are reserved until <strong class="font-semibold">{{ $deadline }}</strong>. If you’ve just paid, this page updates in a moment; otherwise pay <strong class="font-semibold">{{ $charged }}</strong> with {{ $methodLabel }} now.
                    @endif
                </p>

                <div class="mt-7 flex flex-wrap items-center gap-3 print:hidden max-sm:grid max-sm:grid-cols-1">
                    @if($isCod || $isPaid)
                        <a href="{{ route('orders.show', $order) }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-md bg-brand px-6 text-sm font-semibold text-white transition hover:bg-brand-hover">Track order {!! $arrow !!}</a>
                    @else
                        <a href="{{ route('orders.pay', $order) }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-md bg-brand px-6 text-sm font-semibold text-white transition hover:bg-brand-hover">Pay now {!! $arrow !!}</a>
                        <a href="{{ route('orders.placed', $order) }}" class="inline-flex min-h-12 items-center justify-center rounded-md px-4 text-sm font-semibold text-brand transition hover:bg-[#eef3ff]">I’ve paid, refresh</a>
                    @endif
                    <a href="{{ route('home') }}" class="inline-flex min-h-12 items-center justify-center rounded-md px-4 text-sm font-semibold text-brand transition hover:bg-[#eef3ff]">Continue shopping</a>
                </div>

                <ol class="mt-10 grid grid-cols-4 gap-2 max-sm:mt-8 max-sm:grid-cols-2 max-sm:gap-x-3 max-sm:gap-y-5" aria-label="What happens next">
                    @foreach($steps as $index => [$label, $note])
                        <li class="min-w-0" @if($index === $current) aria-current="step" @endif>
                            <span @class(['block h-1.5 rounded-full', 'bg-brand' => $index < $current, 'placed-step-now' => $index === $current, 'bg-slate-200' => $index > $current]) aria-hidden="true"></span>
                            <p @class(['mt-3 text-[13px] leading-[1.35]', 'font-semibold text-ink' => $index <= $current, 'font-medium text-muted' => $index > $current])>{{ $label }}</p>
                            @if($note)<p @class(['mt-0.5 text-xs leading-[1.4]', 'font-medium text-warning-text' => $index === $current && ! $isCod && ! $isPaid, 'text-muted' => ! ($index === $current && ! $isCod && ! $isPaid)])>{{ $note }}</p>@endif
                        </li>
                    @endforeach
                </ol>
                <p class="mt-4 text-[13px] leading-[1.55] text-muted">{{ $storeCount > 1 ? 'Each store ships its own items, so parcels may arrive on different days.' : 'Your order page updates at each step.' }}</p>
            </section>

            {{-- A receipt reads as a ledger: what it cost, how it is paid, where it goes. --}}
            <aside class="placed-receipt w-full max-lg:max-w-[460px] max-sm:max-w-none lg:sticky lg:top-24 lg:col-start-2 lg:row-span-2 lg:row-start-1" aria-labelledby="receipt-heading">
                <div class="drop-shadow-[0_18px_22px_rgb(24_44_72/8%)] print:drop-shadow-none">
                    <section class="placed-docket">
                        <div class="relative px-6 pb-6 pt-5">
                            <h2 id="receipt-heading" class="text-sm font-semibold text-ink">Receipt</h2>
                            <p class="text-xs text-muted">{{ $order->created_at->format('M j, Y · g:i A') }}</p>
                            <p class="mt-6 text-[34px] font-semibold leading-none tracking-[-0.035em] tabular-nums text-ink max-sm:text-[31px]">{{ $isCod ? $total : $charged }}</p>
                            <p @class(['mt-2 text-[13px] font-medium', 'text-success' => $isPaid, 'text-warning-text' => ! $isPaid])>{{ $isCod ? 'Due on delivery' : ($isPaid ? 'Paid in full' : 'Awaiting payment') }}</p>
                            <span @class(['placed-stamp', 'is-paid' => $isPaid]) aria-hidden="true"><span><strong>{{ $isCod ? 'COD' : ($isPaid ? 'Paid' : 'To pay') }}</strong><small>{{ $isCod ? 'On delivery' : $methodLabel }}</small></span></span>
                        </div>

                        <dl class="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-y-2.5 [&>dd]:pl-3 px-6 pb-5 pt-6 text-[13px] leading-[1.5]">
                            <dt class="text-muted">Items ({{ $itemCount }})</dt><dd class="text-right tabular-nums text-ink">₱{{ number_format($order->subtotal, 2) }}</dd>
                            <dt class="text-muted">Shipping</dt><dd class="text-right font-semibold text-success">{{ $order->shipping_amount == 0 ? 'Free' : '₱'.number_format($order->shipping_amount, 2) }}</dd>
                            @unless($isCod)
                                <dt class="text-muted">Payment fee</dt><dd class="text-right tabular-nums text-ink">₱{{ number_format($fee, 2) }}</dd>
                            @endunless
                            <dt class="border-t border-line pt-2.5 font-semibold text-ink">Total</dt><dd class="border-t border-line pt-2.5 text-right font-semibold tabular-nums text-ink">{{ $isCod ? $total : $charged }}</dd>

                            <dt class="mt-3 border-t border-line-soft pt-4 text-muted">Payment</dt>
                            <dd class="mt-3 border-t border-line-soft pt-4 text-ink"><span class="font-semibold">{{ $methodLabel }}</span><span class="mt-0.5 block text-xs leading-[1.45] text-muted">{{ $isCod ? 'Have '.$total.' ready for the courier.' : ($isPaid ? 'Paid through PayMongo. Each store gets its share automatically.' : 'Pay on PayMongo’s secure page.') }}</span></dd>
                            <dt class="text-muted">Deliver to</dt>
                            <dd class="text-ink"><address class="not-italic">{{ $order->recipient_name }}<br>{{ $order->address_line }}<br>{{ collect([$order->city, $order->province])->filter()->join(', ') }} {{ $order->postal_code }}<br><span class="tabular-nums text-muted">{{ $order->phone }}</span></address></dd>
                        </dl>

                        <div class="px-6 pb-5 print:hidden">
                            <button type="button" onclick="window.print()" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-md border border-[#cbd4e0] bg-surface px-5 text-[13.5px] font-semibold text-ink transition hover:border-ink">
                                <svg class="size-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 9V4h10v5M7 18H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"/><rect x="7" y="14" width="10" height="7" rx="1.5"/></svg>
                                Print receipt
                            </button>
                        </div>

                        <div x-data="{ copied: false, copy() { navigator.clipboard?.writeText(@js($order->number)); this.copied = true; setTimeout(() => this.copied = false, 2200) } }" class="flex items-center gap-3 rounded-b-lg border-t border-line-soft bg-[#f8fafc] py-2 pl-6 pr-3">
                            <p class="min-w-0 flex-1 text-xs leading-[1.4] text-muted">Order <span class="block font-semibold tracking-[0.02em] tabular-nums text-ink break-all">{{ $order->number }}</span></p>
                            <button type="button" @click="copy" class="inline-flex min-h-10 shrink-0 items-center rounded-lg px-2.5 text-xs font-semibold text-brand transition hover:bg-[#eef3ff] print:hidden" :aria-label="copied ? 'Order number copied' : 'Copy order number'" x-text="copied ? 'Copied' : 'Copy'">Copy</button>
                        </div>
                    </section>
                </div>
            </aside>

            {{-- One surface for everything bought; stores divide it instead of each becoming its own card. --}}
            <section class="min-w-0" aria-labelledby="order-heading">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 id="order-heading" class="text-[19px] font-semibold tracking-[-0.022em] text-ink">Your order</h2>
                    <p class="text-[13px] text-muted">{{ $itemCount }} {{ Str::plural('item', $itemCount) }} · {{ $storeCount }} {{ Str::plural('store', $storeCount) }}</p>
                </div>

                <div class="mt-4 divide-y-[6px] divide-canvas overflow-hidden rounded-lg border border-line bg-surface">
                    @foreach($order->sellerOrders as $sellerOrder)
                        @php($storePaid = $sellerOrder->cod_collected_at !== null)
                        <article aria-label="{{ $sellerOrder->store->name }}">
                            <header class="flex flex-wrap items-center gap-x-3 gap-y-1 px-5 pb-1 pt-4 max-sm:px-4">
                                <h3 class="min-w-0 truncate text-sm font-semibold text-ink">{{ $sellerOrder->store->name }}</h3>
                                <span @class(['inline-flex items-center gap-1.5 text-xs font-semibold before:size-1.5 before:rounded-full before:bg-current', 'text-brand-hover' => $isCod || $storePaid, 'text-warning-text' => ! $isCod && ! $storePaid])>{{ $isCod || $storePaid ? 'Awaiting confirmation' : 'To pay' }}</span>
                                <form method="POST" action="{{ route('messages.seller-orders.start', $sellerOrder) }}" class="ml-auto print:hidden">@csrf<button class="inline-flex min-h-10 items-center gap-1 whitespace-nowrap text-xs font-semibold text-brand transition hover:text-brand-hover">Message store {!! $arrow !!}</button></form>
                            </header>
                            <ul class="divide-y divide-line-soft px-5 max-sm:px-4">
                                @foreach($sellerOrder->items as $item)
                                    @php($imagePath = $item->image_path ?? $item->product?->images->first()?->path)
                                    <li class="grid grid-cols-[56px_minmax(0,1fr)_auto] items-center gap-4 py-3.5 max-sm:grid-cols-[48px_minmax(0,1fr)_auto] max-sm:gap-3">
                                        <span class="grid size-14 place-items-center overflow-hidden rounded-md bg-slate-100 max-sm:size-12">@if($imagePath)<img src="{{ Storage::disk('product_images')->url($imagePath) }}" alt="" class="size-full object-cover" loading="lazy">@else<svg class="size-6 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8" cy="9" r="1.5"/><path d="m4 18 5-5 3 3 3-3 5 5"/></svg>@endif</span>
                                        <span class="min-w-0">
                                            <span class="block break-words text-sm font-medium leading-[1.4] text-ink">{{ $item->product_name }}</span>
                                            <span class="mt-0.5 block text-xs leading-[1.4] text-muted">{{ collect([$item->variant_label, 'Qty '.$item->quantity])->filter()->join(' · ') }}</span>
                                        </span>
                                        <span class="whitespace-nowrap text-sm font-semibold tabular-nums text-ink">₱{{ number_format($item->line_total, 2) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            @if($storeCount > 1)
                                <p class="flex justify-between gap-3 border-t border-line-soft px-5 py-3 text-xs text-muted max-sm:px-4">Store subtotal<strong class="text-[13px] font-semibold tabular-nums text-ink">₱{{ number_format($sellerOrder->total, 2) }}</strong></p>
                            @endif
                        </article>
                    @endforeach
                </div>

                <p class="mt-6 text-[13px] leading-[1.55] text-muted print:hidden">Something not right? Message the store from <a href="{{ route('orders.show', $order) }}" class="font-semibold text-brand underline decoration-[#bcd0ff] underline-offset-4 hover:text-brand-hover hover:decoration-current">your order page</a>. They can help with items, delivery, or returns.</p>
            </section>
        </div>
        @if ($recommendedProducts->isNotEmpty())
            <section class="marketplace-section mt-12 print:hidden" aria-labelledby="placed-recommended-heading">
                <div class="marketplace-section-head"><div><h2 id="placed-recommended-heading" class="marketplace-section-title">More you might like</h2><p class="marketplace-section-subtitle">Explore products related to your shopping interests.</p></div></div>
                <div class="marketplace-product-grid">
                    @foreach ($recommendedProducts as $product)
                        <x-marketplace.top-product-card :product="$product" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
