{{-- GCash, Maya, and bank transfer orders are paid store by store, straight into each store's own
     account. The checklist shows where each payment stands; right after checkout (and after each
     reference sent from it) a guided dialog walks the buyer to the next store still to pay. --}}
@php
    $method = $order->payment->method;
    $label = \App\Models\Payment::METHOD_LABELS[$method];
    $payable = $order->sellerOrders->reject(fn ($sellerOrder) => $sellerOrder->status === 'cancelled')->values();
    $stateOf = fn ($sellerOrder): string => match (true) {
        $sellerOrder->cod_collected_at !== null => 'received',
        $sellerOrder->payment_reference !== null => 'sent',
        $sellerOrder->status === 'pending' => 'to_pay',
        default => 'received',
    };
    $toPay = $payable->filter(fn ($sellerOrder) => $stateOf($sellerOrder) === 'to_pay')->values();
    $doneCount = $payable->count() - $toPay->count();
    $leftAmount = $toPay->reduce(fn (string $sum, $sellerOrder) => bcadd($sum, (string) $sellerOrder->total, 2), '0.00');
    $deadline = $order->created_at->copy()->addHours((int) config('marketplace.wallet.payment_hours.'.$method));
    // Also reopens after a reference sent from the guide was rejected, so the buyer sees why.
    $openGuide = (request()->boolean('pay') || session('guided_pay') || ($errors->has('payment_reference') && old('guided'))) && $toPay->isNotEmpty();
    $ringOffset = $payable->count() > 0 ? 170 - (170 * $doneCount / $payable->count()) : 170;
@endphp

@if ($payable->isNotEmpty())
<section id="pay-stores" class="scroll-mt-24 space-y-4" aria-labelledby="pay-stores-heading"
    x-data="{ deadline: {{ $deadline->getTimestamp() * 1000 }}, left: '', tick() { const s = Math.max(0, Math.floor((this.deadline - Date.now()) / 1000)); this.left = s === 0 ? 'time is up' : [Math.floor(s / 3600), Math.floor(s % 3600 / 60), s % 60].map(n => String(n).padStart(2, '0')).join(':'); } }"
    x-init="tick(); setInterval(() => tick(), 1000)">
    <div class="flex flex-wrap items-center gap-5 rounded-[22px] bg-ink px-5 py-5 text-white shadow-[0_24px_50px_-30px_rgba(24,44,72,0.9)] sm:px-6">
        <div class="relative size-16 shrink-0">
            <svg class="size-16 -rotate-90" viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="27" fill="none" stroke="rgba(255,255,255,0.18)" stroke-width="7"/><circle cx="32" cy="32" r="27" fill="none" stroke="#3DDC84" stroke-width="7" stroke-linecap="round" stroke-dasharray="170" stroke-dashoffset="{{ $ringOffset }}"/></svg>
            <span class="absolute inset-0 grid place-items-center text-base font-bold tabular-nums">{{ $doneCount }}/{{ $payable->count() }}</span>
        </div>
        <div class="min-w-0 flex-1">
            <h2 id="pay-stores-heading" class="text-lg font-semibold sm:text-xl">
                @if ($toPay->isEmpty())
                    {{ $payable->count() === 1 ? 'Payment sent' : 'All '.$payable->count().' stores paid' }}
                @else
                    Pay {{ $toPay->count() === 1 ? $toPay->first()->store->name : $toPay->count().' stores' }} by {{ $label }}
                @endif
            </h2>
            <p class="mt-1 text-sm leading-6 text-slate-300">
                @if ($toPay->isEmpty())
                    Each store confirms your payment in its own {{ $method === 'bank' ? 'account' : $label }}, then ships.
                @else
                    ₱{{ number_format($leftAmount, 2) }} left · items are held for <span class="font-semibold text-white tabular-nums" x-text="left">{{ $deadline->diffForHumans(null, true) }}</span>, until {{ $deadline->format('M j, g:i A') }}
                @endif
            </p>
        </div>
        @if ($toPay->isNotEmpty())
            <button type="button" @click="$dispatch('open-pay-guide')" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-white px-4 text-sm font-semibold text-ink transition hover:bg-blue-50 max-sm:w-full max-sm:justify-center">
                Pay now<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
            </button>
        @endif
    </div>

    <div class="grid gap-4">
        @foreach ($payable as $sellerOrder)
            @php($state = $stateOf($sellerOrder))
            <article class="rounded-[20px] border bg-surface p-5 sm:p-6 {{ $state === 'to_pay' ? 'border-brand/60 shadow-[0_18px_40px_-30px_rgba(32,91,244,0.8)]' : 'border-line' }}" aria-labelledby="pay-store-{{ $sellerOrder->id }}">
                <header class="flex items-center gap-3">
                    @if ($state === 'to_pay')
                        <span class="grid size-8 shrink-0 place-items-center rounded-full border-2 border-brand text-sm font-bold text-brand" aria-hidden="true">{{ $loop->iteration }}</span>
                    @else
                        <span class="grid size-8 shrink-0 place-items-center rounded-full bg-success text-white" aria-hidden="true"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg></span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <h3 id="pay-store-{{ $sellerOrder->id }}" class="truncate font-semibold text-ink">{{ $sellerOrder->store->name }}</h3>
                        <p class="text-xs text-muted">{{ $sellerOrder->items->count() }} {{ Str::plural('item', $sellerOrder->items->count()) }} · {{ $label }}</p>
                    </div>
                    <p class="font-semibold tabular-nums text-ink">₱{{ number_format($sellerOrder->total, 2) }}</p>
                </header>

                @if ($state === 'received')
                    <p class="mt-4 flex items-center gap-2 rounded-xl bg-green-50 px-4 py-3 text-sm font-semibold text-success ring-1 ring-green-200">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
                        Payment confirmed by the store{{ $sellerOrder->cod_collected_at ? ' · '.$sellerOrder->cod_collected_at->format('M j, g:i A') : '' }}
                    </p>
                @elseif ($state === 'sent')
                    <div class="mt-4 rounded-xl bg-green-50 px-4 py-3 text-sm ring-1 ring-green-200">
                        <p class="font-semibold text-success">Reference sent · waiting for the store to confirm</p>
                        <p class="mt-0.5 tabular-nums text-[#365A45]">Ref {{ $sellerOrder->payment_reference }}</p>
                        <p class="mt-1 text-xs leading-5 text-[#365A45]">Stores usually confirm within a few hours. Not confirmed in 2 days? <a href="#order-help" class="font-semibold text-brand underline">Ask for help</a> and report it to {{ $method === 'bank' ? 'your bank' : $label }}.</p>
                    </div>
                    @if ($sellerOrder->status === 'pending')
                        <details class="group mt-3">
                            <summary class="inline-flex min-h-11 cursor-pointer items-center text-sm font-semibold text-brand hover:text-brand-hover">Wrong reference? Edit it</summary>
                            <div class="mt-2">@include('marketplace.orders._store-pay', ['guided' => false, 'submitLabel' => 'Update reference'])</div>
                        </details>
                    @endif
                @else
                    <div class="mt-5">@include('marketplace.orders._store-pay', ['guided' => false])</div>
                @endif
            </article>
        @endforeach
    </div>

    @error('payment_reference')<p class="text-sm text-danger" role="alert">{{ $message }}</p>@enderror

    <p class="flex items-start gap-2 text-xs leading-5 text-muted">
        <svg class="mt-0.5 size-4 shrink-0 text-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.4 7.5 9.5 4.3-1.1 7.5-4.9 7.5-9.5V6L12 3Z"/></svg>
        <span>Only pay the QR or account shown here. Your money goes to the store, not JMMart, and JMMart and stores never send payment numbers by chat or ask for your MPIN or OTP. <a href="{{ route('terms') }}#wallet-payments" class="font-semibold text-brand underline">What JMMart can and can't do</a></span>
    </p>

    @if ($toPay->isNotEmpty())
        @php($current = $toPay->first())
        {{-- x-trap keeps focus inside, locks page scroll, and hands focus back when it closes. --}}
        <div x-data="{ open: @js($openGuide) }" @open-pay-guide.window="open = true" @keydown.escape.window="open = false">
            <div x-cloak x-show="open" x-trap.noscroll="open" class="fixed inset-0 z-50 flex items-end bg-slate-950/50 sm:items-center sm:justify-center sm:p-6" @click.self="open = false">
                <section role="dialog" aria-modal="true" aria-labelledby="pay-guide-title" class="max-h-[94dvh] w-full overflow-y-auto rounded-t-[26px] bg-surface shadow-2xl sm:max-w-4xl sm:rounded-[28px] lg:grid lg:grid-cols-[17rem_minmax(0,1fr)] lg:overflow-hidden">
                    <span class="mx-auto mt-2.5 block h-1 w-10 rounded-full bg-slate-300 sm:hidden" aria-hidden="true"></span>
                    <nav aria-label="Stores to pay" class="hidden flex-col gap-5 bg-ink p-7 text-white lg:flex">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-400">{{ request()->boolean('pay') ? 'Order placed ✓' : 'Paying your order' }}</p>
                            <p class="mt-1.5 text-xl font-semibold">Pay {{ $payable->count() }} {{ Str::plural('store', $payable->count()) }}</p>
                            <p class="mt-1 text-sm text-slate-300">Items held for <span class="tabular-nums" x-text="left"></span></p>
                        </div>
                        <ol class="space-y-1.5">
                            @foreach ($payable as $sellerOrder)
                                @php($state = $stateOf($sellerOrder))
                                <li @if ($sellerOrder->is($current)) aria-current="step" @endif class="flex items-center gap-3 rounded-xl p-3 {{ $sellerOrder->is($current) ? 'bg-white/10' : '' }}">
                                    @if ($state === 'to_pay')
                                        <span class="grid size-7 shrink-0 place-items-center rounded-full text-xs font-bold {{ $sellerOrder->is($current) ? 'bg-blue-500' : 'border border-slate-500 text-slate-300' }}">{{ $loop->iteration }}</span>
                                    @else
                                        <span class="grid size-7 shrink-0 place-items-center rounded-full bg-[#3DDC84] text-ink"><svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg><span class="sr-only">Paid</span></span>
                                    @endif
                                    <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold">{{ $sellerOrder->store->name }}</span><span class="block text-xs text-slate-400">₱{{ number_format($sellerOrder->total, 2) }} · {{ $state === 'to_pay' ? ($sellerOrder->is($current) ? 'paying now' : 'next') : 'reference sent' }}</span></span>
                                </li>
                            @endforeach
                        </ol>
                        <p class="mt-auto text-xs leading-5 text-slate-400">Money goes to each store, not JMMart. Never share your MPIN or OTP.</p>
                    </nav>
                    <div class="p-5 sm:p-7">
                        <div class="flex items-start gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-muted lg:hidden">Store {{ $payable->search(fn ($sellerOrder) => $sellerOrder->is($current)) + 1 }} of {{ $payable->count() }}</p>
                                <h2 id="pay-guide-title" class="text-xl font-semibold tracking-[-0.01em] text-ink sm:text-2xl">Pay {{ $current->store->name }} with {{ $label }}</h2>
                                <p class="mt-1 text-sm text-muted">Pay the exact amount, then send the reference number from your receipt.</p>
                            </div>
                            <button type="button" @click="open = false" class="grid size-11 shrink-0 place-items-center rounded-xl border border-line text-ink hover:bg-canvas" aria-label="Close and pay later"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
                        </div>
                        @if ($payable->count() > 1)
                            <div class="mt-4 grid gap-1.5 lg:hidden" style="grid-template-columns: repeat({{ $payable->count() }}, minmax(0, 1fr));" aria-hidden="true">
                                @foreach ($payable as $sellerOrder)<span class="h-1 rounded-full {{ $stateOf($sellerOrder) !== 'to_pay' || $sellerOrder->is($current) ? 'bg-brand' : 'bg-line' }}"></span>@endforeach
                            </div>
                        @endif
                        <div class="mt-5">
                            @include('marketplace.orders._store-pay', ['sellerOrder' => $current, 'guided' => true, 'submitLabel' => $toPay->count() > 1 ? 'Send reference · next store' : 'Send reference · done'])
                        </div>
                        <div class="mt-5 flex items-center border-t border-line pt-4">
                            <button type="button" @click="open = false" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:text-brand-hover">I'll pay later from this page</button>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    @endif
</section>
@endif
