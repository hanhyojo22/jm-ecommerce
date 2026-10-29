<x-app-layout>
    @php
        $method = $order->payment?->method ?? 'cod';
        $isCod = ! in_array($method, \App\Models\Payment::DIRECT_METHODS, true);
        $methodLabel = \App\Models\Payment::METHOD_LABELS[$method];
        $storeCount = $order->sellerOrders->count();
        $itemCount = $order->sellerOrders->sum(fn ($sellerOrder) => $sellerOrder->items->sum('quantity'));
        $total = '₱'.number_format($order->total, 2);
        $firstName = Str::before(trim((string) $order->recipient_name), ' ');
        $placedAt = $order->created_at->format('M j, Y · g:i A');
        // Wallet orders are not paid at checkout: each store is paid directly, before this deadline.
        $deadline = $isCod ? null : $order->created_at->copy()->addHours((int) config('marketplace.wallet.payment_hours.'.$method))->format('M j, g:i A');
        $stores = $storeCount === 1 ? 'the store' : ($storeCount === 2 ? 'both stores' : 'all '.$storeCount.' stores');
        $steps = $isCod
            ? [['Order placed', $order->created_at->format('M j, g:i A')], ['Store confirms', 'Each store sets its delivery timing'], ['Packed & shipped', null], ['Delivered', 'Pay the courier']]
            : [['Order placed', $order->created_at->format('M j, g:i A')], ['Pay each store', 'By '.$deadline], ['Store confirms', 'Once your payment arrives'], ['Shipped & delivered', null]];
        $arrow = '<svg class="size-[13px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-5-5 5 5-5 5"/></svg>';
    @endphp

    <div class="page-shell min-h-[calc(100dvh-4.5rem)] bg-[radial-gradient(1040px_400px_at_50%_-170px,#eaf1ff_0%,rgb(234_241_255/0%)_72%)] !pt-10 pb-24 max-sm:!pt-6">
        <div class="page-container grid grid-cols-1 items-start gap-7 lg:grid-cols-[minmax(0,1fr)_372px] lg:gap-x-9 lg:gap-y-8">
            <section class="flex items-start gap-5 max-sm:block" aria-labelledby="placed-heading">
                <span class="grid size-14 shrink-0 place-items-center rounded-full border border-[#bfe3cc] bg-[#f1faf4] text-success max-sm:mb-3.5 max-sm:size-11" aria-hidden="true"><svg class="size-[27px] max-sm:size-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.5 4.5L19 6.5"/></svg></span>
                <div class="min-w-0">
                    <h1 id="placed-heading" class="text-[46px] font-semibold leading-[1.04] tracking-[-0.035em] text-ink [text-wrap:balance] max-lg:text-[42px] max-sm:text-[30px]">Order placed</h1>
                    <p class="mt-2.5 max-w-[55ch] text-[15.5px] leading-[1.6] text-muted max-sm:text-[14.5px]">
                        @if($isCod)
                            Thanks, {{ $firstName }}. Your order is in. {{ ucfirst($stores) }} {{ $storeCount === 1 ? 'is' : 'are' }} getting it ready, and you&rsquo;ll pay the courier on delivery.
                        @else
                            Thanks, {{ $firstName }}. Your items are reserved. Pay {{ $storeCount === 1 ? 'the store' : 'each store' }} with {{ $methodLabel }} by {{ $deadline }} so {{ $storeCount === 1 ? 'it' : 'they' }} can confirm and ship.
                        @endif
                    </p>

                    <div class="mt-5 flex flex-wrap items-center gap-x-3 gap-y-2 text-[12.5px] text-muted">
                        <span x-data="{ copied: false, copy() { navigator.clipboard?.writeText(@js($order->number)); this.copied = true; setTimeout(() => this.copied = false, 2200) } }" class="inline-flex min-h-8 items-center gap-2 rounded-lg border border-line bg-surface py-[3px] pl-2.5 pr-1 font-semibold text-ink max-sm:grid max-sm:w-full max-sm:grid-cols-[minmax(0,1fr)_auto]">
                            <span class="text-xs uppercase tracking-[0.08em] text-muted">Order</span>
                            <span class="whitespace-nowrap tabular-nums max-sm:col-start-1 max-sm:row-start-2 max-sm:whitespace-normal max-sm:break-all">{{ $order->number }}</span>
                            <button type="button" @click="copy" :class="copied ? 'bg-[#f1faf4] text-success' : 'bg-slate-100 text-muted hover:bg-[#eef3ff] hover:text-brand'" class="inline-flex min-h-[30px] items-center gap-1 rounded-[5px] px-2 text-xs font-semibold transition max-sm:col-start-2 max-sm:row-span-2 max-sm:row-start-1" :aria-label="copied ? 'Order number copied' : 'Copy order number'"><svg class="size-[11px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="8" y="8" width="11" height="11" rx="2"/><path stroke-linecap="round" d="M16 8V6.5A1.5 1.5 0 0 0 14.5 5h-8A1.5 1.5 0 0 0 5 6.5v8A1.5 1.5 0 0 0 6.5 16H8"/></svg><span x-text="copied ? 'Copied' : 'Copy'">Copy</span></button>
                        </span>
                        <span class="inline-flex min-h-7 items-center rounded-full border border-[#f4d9a8] bg-[#fffaf0] px-2.5 text-xs font-semibold tabular-nums text-warning-text">{{ $isCod ? 'Due on delivery' : 'To pay' }} {{ $total }}</span>
                        <span>Placed {{ $placedAt }}</span>
                    </div>

                    <div class="mt-6 flex flex-wrap items-center gap-2.5 print:hidden max-sm:grid max-sm:grid-cols-1 max-sm:gap-2">
                        @if($isCod)
                            <a href="{{ route('orders.show', $order) }}" class="inline-flex min-h-[46px] items-center justify-center gap-2 rounded-[10px] bg-brand px-5 text-[13.5px] font-semibold text-white transition hover:bg-brand-hover">Track order {!! $arrow !!}</a>
                        @else
                            <a href="{{ route('orders.show', [$order, 'pay' => 1]) }}#pay-stores" class="inline-flex min-h-[46px] items-center justify-center gap-2 rounded-[10px] bg-brand px-5 text-[13.5px] font-semibold text-white transition hover:bg-brand-hover">Pay {{ $storeCount === 1 ? 'store' : 'stores' }} now {!! $arrow !!}</a>
                        @endif
                        <a href="{{ route('home') }}" class="inline-flex min-h-[46px] items-center justify-center rounded-[10px] border border-[#cbd4e0] bg-surface px-5 text-[13.5px] font-semibold text-ink transition hover:border-ink">Continue shopping</a>
                    </div>
                </div>
            </section>

            <aside class="placed-receipt w-full max-lg:max-w-[460px] max-sm:max-w-none lg:sticky lg:top-24 lg:row-span-2 lg:col-start-2 lg:row-start-1" aria-labelledby="receipt-heading">
                <div class="drop-shadow-[0_18px_22px_rgb(24_44_72/8%)] print:drop-shadow-none">
                    <section class="placed-docket">
                        <div class="flex items-center justify-between gap-3 px-5 pt-[17px]">
                            <h2 id="receipt-heading" class="text-sm font-semibold text-ink">Order receipt</h2>
                            <span class="text-xs font-semibold uppercase tracking-[0.1em] text-muted">JMMart</span>
                        </div>

                        <div class="relative px-5 pb-6 pt-[22px]">
                            <p class="text-xs font-semibold uppercase tracking-[0.09em] text-muted">{{ $isCod ? 'Due on delivery' : 'To pay stores directly' }}</p>
                            <p class="mt-1 text-[34px] font-semibold leading-[1.08] tracking-[-0.035em] tabular-nums text-ink max-sm:text-[31px]">{{ $total }}</p>
                            <p class="mt-2 flex items-center gap-1.5 text-xs font-medium text-warning-text"><svg class="size-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v4.5"/><circle cx="12" cy="16" r=".8" fill="currentColor" stroke="none"/></svg>{{ $isCod ? 'Pay the courier when it arrives' : $storeCount.' '.Str::plural('payment', $storeCount).' · by '.$deadline }}</p>
                            <span class="placed-stamp" aria-hidden="true"><span><strong>{{ $isCod ? 'COD' : 'To pay' }}</strong><small>{{ $isCod ? 'On delivery' : $methodLabel }}</small></span></span>
                        </div>

                        <div class="px-5 py-[18px]">
                            <dl class="grid gap-2 text-[12.5px] text-muted">
                                <div class="flex justify-between gap-3"><dt>Items ({{ $itemCount }})</dt><dd class="tabular-nums text-ink">₱{{ number_format($order->subtotal, 2) }}</dd></div>
                                <div class="flex justify-between gap-3"><dt>Shipping</dt><dd class="font-semibold text-success">{{ $order->shipping_amount == 0 ? 'Free' : '₱'.number_format($order->shipping_amount, 2) }}</dd></div>
                                <div class="mt-0.5 flex justify-between gap-3 border-t border-line pt-2.5 text-[13px] font-semibold text-ink"><dt>Total</dt><dd class="text-[15px] tabular-nums">{{ $total }}</dd></div>
                            </dl>

                            <div class="mt-4 flex gap-2.5 border-t border-line-soft pt-[15px]">
                                <span class="grid size-[34px] shrink-0 place-items-center rounded-[9px] {{ $isCod ? 'bg-[#fffaf0] text-warning-text' : 'bg-[#eef3ff] text-brand' }}" aria-hidden="true">
                                    @if($isCod)
                                        <svg class="size-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="7" width="18" height="11" rx="2"/><circle cx="12" cy="12.5" r="2.4"/><path stroke-linecap="round" d="M6 10h.01M18 15h.01"/></svg>
                                    @else
                                        <svg class="size-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="6" width="18" height="13" rx="2.5"/><path stroke-linecap="round" d="M3 10h18"/><path stroke-linecap="round" d="M7 15h3"/></svg>
                                    @endif
                                </span>
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold uppercase tracking-[0.09em] text-muted">Payment</p>
                                    <p class="mt-0.5 text-[13px] font-semibold leading-[1.35] text-ink">{{ $methodLabel }}</p>
                                    <p class="mt-0.5 text-xs leading-[1.45] text-muted">{{ $isCod ? 'Prepare '.$total.' for the courier.' : 'Paid straight to each store. Only use the details shown on your JMMart order page.' }}</p>
                                </div>
                            </div>

                            <div class="mt-4 border-t border-line-soft pt-[15px]">
                                <p class="text-xs font-semibold uppercase tracking-[0.09em] text-muted">Delivering to</p>
                                <address class="mt-1 text-[12.5px] not-italic leading-[1.55] text-ink">
                                    {{ $order->recipient_name }}<br>
                                    {{ $order->address_line }}<br>
                                    {{ collect([$order->city, $order->province])->filter()->join(', ') }} {{ $order->postal_code }}<br>
                                    {{ $order->phone }}
                                </address>
                            </div>

                            <button type="button" onclick="window.print()" class="mt-[18px] inline-flex min-h-[42px] w-full items-center justify-center gap-2 rounded-[10px] border border-[#cbd4e0] bg-surface px-5 text-[13.5px] font-semibold text-ink transition hover:border-ink print:hidden">
                                <svg class="size-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 9V4h10v5M7 18H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"/><rect x="7" y="14" width="10" height="7" rx="1.5"/></svg>
                                Print receipt
                            </button>
                        </div>

                        <div class="flex items-baseline gap-2 rounded-b-[18px] border-t border-line-soft bg-[#f8fafc] px-5 pb-3.5 pt-3 [overflow-wrap:anywhere]">
                            <span class="shrink-0 text-xs font-semibold uppercase tracking-[0.1em] text-muted">Order</span>
                            <strong class="text-xs font-semibold tracking-[0.02em] tabular-nums text-ink">{{ $order->number }}</strong>
                        </div>
                    </section>
                </div>
            </aside>

            <div class="grid min-w-0 gap-7">
                <section class="rounded-2xl border border-line bg-surface p-[22px] shadow-[0_1px_2px_rgb(24_44_72/4%)] max-sm:px-4 max-sm:py-[18px]" aria-labelledby="next-heading">
                    <h2 id="next-heading" class="text-[19px] font-semibold tracking-[-0.022em] text-ink">What happens next</h2>
                    <p class="mt-1.5 max-w-[64ch] text-[13px] leading-[1.55] text-muted">{{ $storeCount > 1 ? 'Each store packs and ships its own items, so parcels may arrive on different days.' : 'The store packs and ships your items, and your order page updates at each step.' }}</p>
                    <ol class="placed-steps">
                        @foreach($steps as $index => [$label, $note])
                            <li class="{{ $index === 0 ? 'is-done' : ($index === 1 ? 'is-current' : '') }}">
                                <span class="placed-step-dot" aria-hidden="true">@if($index === 0)<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/></svg>@endif</span>
                                <p class="placed-step-label">{{ $label }}</p>
                                @if($note)<p class="mt-0.5 text-xs leading-[1.45] text-muted">{{ $note }}</p>@endif
                            </li>
                        @endforeach
                    </ol>
                </section>

                <section aria-labelledby="order-heading">
                    <div class="mb-3 flex items-baseline justify-between gap-3">
                        <h2 id="order-heading" class="text-[19px] font-semibold tracking-[-0.022em] text-ink">Your order</h2>
                        <p class="whitespace-nowrap text-[12.5px] text-muted">{{ $itemCount }} {{ Str::plural('item', $itemCount) }} from {{ $storeCount }} {{ Str::plural('store', $storeCount) }}</p>
                    </div>

                    <div class="grid gap-3">
                        @foreach($order->sellerOrders as $sellerOrder)
                            @php($sent = $sellerOrder->payment_reference !== null)
                            <article class="overflow-hidden rounded-[14px] border border-line bg-surface shadow-[0_1px_2px_rgb(24_44_72/4%)]">
                                <div class="flex items-center gap-3 border-b border-line-soft px-[18px] py-[15px] max-sm:px-[15px]">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-[10px] text-sm font-bold {{ $loop->odd ? 'bg-[#eef3ff] text-brand' : 'bg-orange-50 text-warning-text' }}" aria-hidden="true">{{ Str::upper(Str::substr($sellerOrder->store->name, 0, 1)) }}</span>
                                    <div class="min-w-0">
                                        <h3 class="truncate text-sm font-semibold tracking-[-0.01em] text-ink">{{ $sellerOrder->store->name }}</h3>
                                        <p class="mt-0.5 text-xs leading-[1.35] text-muted">{{ $storeCount > 1 ? 'Ships separately' : 'Ships from this store' }}</p>
                                    </div>
                                    <span class="ml-auto inline-flex min-h-6 shrink-0 items-center gap-1.5 rounded-full border px-2 text-xs font-semibold before:size-[5px] before:rounded-full before:bg-current {{ $isCod || $sent ? 'border-[#d6e2fb] bg-[#eef3ff] text-brand-hover' : 'border-[#f4d9a8] bg-[#fffaf0] text-warning-text' }}">{{ $isCod ? 'Awaiting confirmation' : ($sent ? 'Reference sent' : 'To pay') }}</span>
                                </div>
                                <div class="divide-y divide-line-soft px-[18px] py-0.5 max-sm:px-[15px]">
                                    @foreach($sellerOrder->items as $item)
                                        @php($imagePath = $item->image_path ?? $item->product?->images->first()?->path)
                                        <div class="grid grid-cols-[56px_minmax(0,1fr)_auto] items-center gap-[13px] py-3.5 max-sm:grid-cols-[48px_minmax(0,1fr)_auto] max-sm:gap-2.5">
                                            <span class="size-14 overflow-hidden rounded-[10px] border border-line bg-slate-100 max-sm:size-12">@if($imagePath)<img src="{{ Storage::disk('product_images')->url($imagePath) }}" alt="" class="size-full object-cover" loading="lazy">@endif</span>
                                            <span class="min-w-0">
                                                <span class="block break-words text-[13.5px] font-medium leading-[1.4] text-ink">{{ $item->product_name }}</span>
                                                <span class="mt-0.5 block text-xs leading-[1.4] text-muted">{{ collect([$item->variant_label, 'Qty '.$item->quantity])->filter()->join(' · ') }}</span>
                                            </span>
                                            <span class="whitespace-nowrap text-[13.5px] font-semibold tabular-nums text-ink">₱{{ number_format($item->line_total, 2) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-2 border-t border-line-soft bg-[#fbfcfe] px-[18px] py-[11px] max-sm:px-[15px]">
                                    <span class="text-xs text-muted">Subtotal <strong class="ml-1.5 text-[12.5px] font-semibold tabular-nums text-ink">₱{{ number_format($sellerOrder->total, 2) }}</strong></span>
                                    <form method="POST" action="{{ route('messages.seller-orders.start', $sellerOrder) }}" class="ml-auto">@csrf<button class="inline-flex min-h-8 items-center gap-1 whitespace-nowrap text-xs font-semibold text-brand transition hover:text-brand-hover">Message store {!! $arrow !!}</button></form>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section class="flex items-center gap-[13px] rounded-[14px] border border-[#d6e2fb] bg-[#eef3ff] px-[18px] py-[17px] print:hidden max-sm:flex-wrap max-sm:items-start" aria-labelledby="help-heading">
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-surface text-brand" aria-hidden="true"><svg class="size-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H8l-4 3v-4.2A7.5 7.5 0 0 1 12.5 4 7.5 7.5 0 0 1 20 11.5Z"/><path stroke-linecap="round" d="M9 11.5h6"/></svg></span>
                    <div class="min-w-0 max-sm:flex-[1_1_220px]">
                        <h2 id="help-heading" class="text-[13px] font-semibold text-ink">Something not right?</h2>
                        <p class="mt-0.5 text-xs leading-[1.5] text-[#3f5470]">Message the store from your order page. They can help with items, delivery, or returns.</p>
                    </div>
                    <a href="{{ route('orders.show', $order) }}" class="ml-auto inline-flex min-h-9 shrink-0 items-center gap-1 whitespace-nowrap text-[12.5px] font-semibold text-brand hover:text-brand-hover max-sm:ml-[49px]">Go to my order {!! $arrow !!}</a>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
