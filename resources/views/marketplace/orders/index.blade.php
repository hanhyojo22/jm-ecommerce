<x-app-layout>
    <style>
        /* Clean ledger order-details dialog. Tokens come from resources/css/app.css. */
        dialog.buyer-order-dialog { position: fixed; inset: 0; margin: auto; width: min(920px, calc(100vw - 40px)); max-height: calc(100dvh - 40px); display: flex; flex-direction: column; border: 0; border-radius: 16px; padding: 0; background: #fff; color: var(--color-fg); box-shadow: 0 24px 64px rgb(15 23 42 / 16%), 0 2px 8px rgb(15 23 42 / 5%); overflow: hidden; }
        dialog.buyer-order-dialog:not([open]) { display: none; }
        dialog.buyer-order-dialog[open] { animation: buyer-dialog-in .18s ease-out; }
        @keyframes buyer-dialog-in { from { opacity: 0; transform: translateY(8px) scale(.985); } to { opacity: 1; transform: none; } }
        dialog.buyer-order-dialog::backdrop { background: rgb(15 23 42 / 40%); backdrop-filter: blur(2px); }
        @media (max-width: 760px) { dialog.buyer-order-dialog { width: 100vw; max-width: 100vw; height: 100dvh; max-height: 100dvh; border-radius: 0; margin: 0; } dialog.buyer-order-dialog::backdrop { background: transparent; backdrop-filter: none; } }
        @media (prefers-reduced-motion: reduce) { dialog.buyer-order-dialog[open] { animation: none; } }

        dialog.buyer-order-dialog.buyer-order-dialog--ledger { width: min(520px, calc(100vw - 32px)); max-height: calc(100dvh - 32px); }
        @media (max-width: 640px) {
            dialog.buyer-order-dialog.buyer-order-dialog--ledger { width: 100vw; max-width: 100vw; height: 100dvh; max-height: 100dvh; border-radius: 0; margin: 0; }
            dialog.buyer-order-dialog.buyer-order-dialog--ledger::backdrop { background: transparent; backdrop-filter: none; }
        }

        .buyer-modal-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: 20px 24px 24px; overscroll-behavior: contain; }

        .clean-head { flex: 0 0 auto; display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 22px 24px 14px; }
        .clean-title { font-size: 20px; font-weight: 600; letter-spacing: -.025em; line-height: 1.2; }
        .clean-status { display: inline-flex; align-items: center; gap: 6px; margin-top: 7px; font-size: 12px; font-weight: 600; }
        .clean-status i { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .clean-meta { margin-top: 5px; font-size: 12px; color: var(--color-muted); }
        .is-good { color: var(--color-success); }
        .is-moving { color: var(--color-accent-hover); }
        .is-warn { color: var(--color-warning-text); }
        .is-stopped { color: var(--color-danger); }
        .is-muted { color: var(--color-muted); }
        .close-btn { flex: none; width: 44px; height: 44px; display: grid; place-items: center; border: 0; border-radius: 8px; background: transparent; color: var(--color-muted); cursor: pointer; touch-action: manipulation; transition: background .15s, color .15s; }
        .close-btn:hover { background: var(--color-sunken); color: var(--color-fg); }
        .close-btn svg { width: 18px; height: 18px; }

        .clean-notice { margin: 0 24px 16px; padding: 12px 14px; border: 1px solid var(--color-border); border-radius: 8px; background: var(--color-sunken); }
        .clean-notice.is-danger { border-color: var(--color-danger-line); background: var(--color-danger-wash); }
        .clean-notice.is-warning { border-color: var(--color-warning-line); background: var(--color-warning-wash); }
        .clean-notice p { font-size: 12.5px; line-height: 1.5; color: var(--color-muted); }
        .clean-notice .title { margin-bottom: 2px; font-weight: 600; color: var(--color-fg); }
        .clean-notice a { display: inline-flex; align-items: center; min-height: 44px; margin-top: 2px; font-size: 12.5px; font-weight: 600; color: var(--color-accent); }
        .clean-notice a:hover { color: var(--color-accent-hover); text-decoration: underline; text-underline-offset: 2px; }

        .clean-journey { flex: 0 0 auto; padding: 0 24px 18px; }
        .tracker { position: relative; display: flex; margin: 0; padding: 0; list-style: none; }
        .tracker::before { content: ''; position: absolute; top: 14px; left: 14px; right: 14px; height: 2px; border-radius: 1px; background: var(--color-line-soft); }
        .tracker::after { content: ''; position: absolute; top: 14px; left: 14px; height: 2px; border-radius: 1px; background: var(--color-accent); width: calc((100% - 28px) * var(--progress, 1)); }
        .tracker-step { position: relative; z-index: 1; flex: 1; display: flex; flex-direction: column; align-items: center; gap: 8px; min-width: 0; }
        .node { width: 28px; height: 28px; border-radius: 50%; display: grid; place-items: center; border: 1.5px solid var(--color-border-strong); background: var(--color-surface); color: var(--color-muted); transition: background .2s, border-color .2s, color .2s; }
        .node svg { width: 14px; height: 14px; }
        .tracker-step.done .node { background: var(--color-accent); border-color: var(--color-accent); color: #fff; }
        .tracker-step.current .node { box-shadow: 0 0 0 4px rgb(32 91 244 / 12%); }
        .tracker-step.final.done .node { background: var(--color-success); border-color: var(--color-success); }
        .tracker-step.current:not(.final) .node { animation: node-pulse 2s ease-out infinite; }
        @keyframes node-pulse { 0%, 100% { box-shadow: 0 0 0 4px rgb(32 91 244 / 12%); } 50% { box-shadow: 0 0 0 7px rgb(32 91 244 / 0%); } }
        .label { font-size: 10.5px; font-weight: 600; color: var(--color-muted); text-align: center; line-height: 1.3; }
        .tracker-step.done .label { color: var(--color-fg); }
        .tracker-step.current .label { color: var(--color-accent); }
        .tracker-step.final.done .label { color: var(--color-success); }
        @media (prefers-reduced-motion: reduce) { .tracker-step.current:not(.final) .node { animation: none; } }

        .clean-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: 0 24px 18px; overscroll-behavior: contain; }
        .clean-store { font-size: 13px; font-weight: 600; color: var(--color-fg); padding-bottom: 8px; }
        .clean-items { list-style: none; padding: 0; margin: 0; }
        .clean-item { display: grid; grid-template-columns: 40px minmax(0, 1fr) auto; gap: 12px; align-items: start; padding: 11px 0; border-top: 1px solid var(--color-line-soft); }
        .item-thumb { width: 40px; height: 40px; border-radius: 6px; border: 1px solid var(--color-border); background: var(--color-paper); overflow: hidden; position: relative; }
        .item-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .item-thumb svg { position: absolute; inset: 0; margin: auto; width: 16px; height: 16px; color: #94a3b8; }
        .item-info { min-width: 0; }
        .item-title { font-size: 13px; font-weight: 500; line-height: 1.4; color: var(--color-fg); overflow-wrap: anywhere; }
        .item-sub { margin-top: 2px; font-size: 11.5px; color: var(--color-muted); }
        .item-price { text-align: right; font-size: 13px; font-weight: 600; color: var(--color-fg); white-space: nowrap; }
        .item-price span { display: block; margin-top: 2px; font-size: 11.5px; font-weight: 400; color: var(--color-muted); }

        .clean-section { margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--color-line-soft); }
        .clean-label { font-size: 10.5px; font-weight: 600; letter-spacing: .1em; text-transform: uppercase; color: var(--color-muted); }
        .clean-address { margin-top: 7px; font-size: 12.5px; line-height: 1.6; color: var(--color-muted); }
        .clean-address strong { display: block; font-weight: 600; color: var(--color-fg); }
        .clean-total { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--color-border-strong); }
        .clean-total .label { font-size: 13px; font-weight: 600; color: var(--color-fg); }
        .clean-total .value { font-size: 21px; font-weight: 600; letter-spacing: -.03em; color: var(--color-fg); }
        .clean-pay { margin-top: 5px; font-size: 12px; color: var(--color-muted); text-align: right; }

        .clean-foot { flex: 0 0 auto; display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 12px 24px calc(12px + env(safe-area-inset-bottom)); border-top: 1px solid var(--color-line-soft); }
        .open-full { display: inline-flex; align-items: center; min-height: 44px; font-size: 12.5px; font-weight: 600; color: var(--color-muted); touch-action: manipulation; transition: color .15s; }
        .open-full:hover { color: var(--color-accent); }
        .done-btn { min-height: 44px; padding: 0 20px; border: 0; border-radius: 8px; background: var(--color-accent); color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; touch-action: manipulation; transition: background .15s, transform .1s; }
        .done-btn:hover { background: var(--color-accent-hover); }
        .done-btn:active { transform: scale(.98); }
        .num { font-variant-numeric: tabular-nums; }

        .sk { display: grid; gap: 12px; }
        .sk-line, .sk-block { border-radius: 6px; background: var(--color-line-soft); animation: sk-pulse 1.2s ease-in-out infinite; }
        .sk-line { height: 12px; }
        .sk-line.is-title { height: 20px; width: 55%; }
        .sk-line.is-short { width: 38%; }
        .sk-journey { height: 48px; border-radius: 8px; background: var(--color-sunken); animation: sk-pulse 1.2s ease-in-out infinite; }
        .sk-block { height: 104px; border-radius: 8px; }
        @keyframes sk-pulse { 0%, 100% { opacity: .55; } 50% { opacity: 1; } }
        @media (prefers-reduced-motion: reduce) { .sk-line, .sk-block, .sk-journey { animation: none; } }

        @media (max-width: 640px) {
            .clean-head { padding: 18px 20px 12px; }
            .clean-title { font-size: 18px; }
            .clean-journey { padding: 0 20px 16px; }
            .clean-body { padding: 0 20px 16px; }
            .clean-notice { margin-inline: 20px; }
            .clean-foot { padding: 12px 20px calc(12px + env(safe-area-inset-bottom)); }
        }
        @media (max-width: 480px) {
            .clean-foot { flex-direction: column-reverse; align-items: stretch; }
            .clean-foot .open-full { justify-content: center; }
            .done-btn { width: 100%; }
        }
        @media (prefers-reduced-motion: reduce) {
            .close-btn, .done-btn, .open-full, .node { transition: none; }
        }
    </style>


    @php

        $statusStyles = [
            'pending' => ['Awaiting store', 'bg-muted', 'text-ink'],
            'confirmed' => ['Preparing', 'bg-muted', 'text-ink'],
            'processing' => ['Preparing', 'bg-muted', 'text-ink'],
            'shipped' => ['On the way', 'bg-brand', 'text-ink'],
            'delivered' => ['Delivered', 'bg-success', 'text-success'],
            'cancelled' => ['Cancelled', 'bg-danger', 'text-danger-text'],
        ];
        $emptyCopy = [
            'all' => ['Your first purchase starts here', 'Your orders and delivery updates will appear here.'],
            'to_pay' => ['Nothing to pay', 'Orders you pay a store for directly, by GCash, Maya, or bank transfer, wait here until you send the payment.'],
            'awaiting_confirmation' => ['Nothing waiting on a store', 'Orders a store has not confirmed yet appear here.'],
            'to_dispatch' => ['Nothing being prepared', 'Orders a store has confirmed and is getting ready to ship appear here.'],
            'to_receive' => ['Nothing on the way', 'Shipped orders appear here until you confirm they arrived.'],
            'delivered' => ['No delivered orders yet', 'Orders appear here once they arrive.'],
            'to_review' => ['You’re all caught up', 'Delivered items you haven’t reviewed appear here.'],
            'returns' => ['No returns or refund requests', 'Open a return from a delivered order within '.(int) config('marketplace.orders.return_window_days').' days of delivery. Requests you open appear here.'],
        ];
        $search = trim($filters['search'] ?? '');
        $hasFilters = $search !== '' || filled($filters['status'] ?? null);
        $waitingOnYou = array_diff_key($attentionCounts, ['to_receive' => true]);
        $attentionLabel = fn (string $key, int $count): string => match ($key) {
            'to_pay' => $count.' to pay',
            'returns' => $count.' '.Str::plural('return', $count).' '.($count === 1 ? 'needs' : 'need').' you',
            'refunds' => $count.' '.Str::plural('refund', $count).' to chase',
        };
        $attentionHref = fn (string $key): string => match ($key) {
            'to_pay' => route('orders.index', ['tab' => 'to_pay']),
            'returns' => route('orders.index', ['tab' => 'returns']),
            // Refunds due already lead the All list, so the link only has to reach it.
            'refunds' => '#purchases',
        };
        // Compact sizes only where a fine pointer is likely; phones and tablets keep 44px targets.
        $quietAction = 'inline-flex min-h-11 items-center rounded-md px-1.5 text-meta font-semibold text-brand underline-offset-2 transition-colors hover:text-brand-hover hover:underline lg:min-h-10';
        $primaryAction = 'inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-3.5 text-meta font-semibold text-white transition-colors hover:bg-brand-hover lg:min-h-10';
        $outlineAction = 'inline-flex min-h-11 items-center justify-center rounded-lg border border-line bg-surface px-3.5 text-meta font-semibold text-ink transition-colors hover:border-brand hover:text-brand-hover lg:min-h-10';
        $inlineLink = 'font-semibold text-brand underline-offset-2 hover:text-brand-hover hover:underline';
        // Chat is a quiet icon beside the state: a full 44px target on phones, a compact 32px ghost on
        // wide screens. Its label lives in the tooltip and accessible name so the store bar stays calm.
        $chatAction = 'inline-grid size-11 shrink-0 place-items-center rounded-lg text-muted transition-colors duration-150 hover:bg-[var(--color-sunken)] hover:text-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand lg:size-8';
    @endphp

    <x-marketplace.account-shell active="orders">
        <div data-purchases class="min-w-0">
            <header class="flex flex-wrap items-end justify-between gap-x-6 gap-y-4">
                <div class="min-w-0">
                    <h1 class="text-3xl font-semibold tracking-tight text-ink">My purchases</h1>
                    <p class="mt-1 text-meta text-muted">
                        @if ($sellerOrders->total() > 0){{ $sellerOrders->total() }} {{ Str::plural('order', $sellerOrders->total()) }}<span aria-hidden="true" class="px-1.5">·</span>@endif
                        <a href="{{ route('catalog.index') }}" class="{{ $inlineLink }}">Continue shopping <span aria-hidden="true">→</span></a>
                    </p>
                </div>

                <form method="GET" action="{{ route('orders.index') }}" class="w-full sm:w-80" role="search">
                    @if ($tab !== 'all')<input type="hidden" name="tab" value="{{ $tab }}">@endif
                    @if (filled($filters['status'] ?? null))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
                    <label for="purchase-search" class="sr-only">Search purchases</label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                        <input id="purchase-search" name="search" value="{{ $search }}" type="search" maxlength="100" enterkeyhint="search" class="field-control pl-9 text-[16px] sm:text-meta lg:min-h-10 {{ $search !== '' ? 'pr-11' : '' }}" placeholder="Order #, store, or item" @error('search') aria-invalid="true" aria-describedby="purchase-search-error" @enderror>
                        @if ($search !== '')
                            <a href="{{ route('orders.index', request()->except('search', 'page')) }}" class="absolute right-0 top-0 grid size-11 place-items-center rounded-lg text-muted hover:text-ink lg:size-10" aria-label="Clear search"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg></a>
                        @endif
                    </div>
                    <x-input-error id="purchase-search-error" :messages="$errors->get('search')" class="mt-2" />
                </form>
            </header>

            {{-- What waits on the buyer, in one strip that shares the list's inner edge. It turns warm only
                 when something is the buyer's to do; parcels on the way alone stay neutral. --}}
            @if ($attentionCounts !== [] && $tab === 'all' && ! $hasFilters)
                <div role="region" aria-label="Waiting on you" @class([
                    'mt-5 flex flex-wrap items-center gap-x-5 gap-y-1 rounded-lg border px-4 py-2.5 text-meta lg:px-6',
                    'border-[var(--color-warning-line)] bg-[var(--color-warning-wash)]' => $waitingOnYou !== [],
                    'border-line bg-surface' => $waitingOnYou === [],
                ])>
                    @if ($waitingOnYou !== [])
                        <strong class="font-semibold text-ink">Waiting on you</strong>
                        @foreach ($waitingOnYou as $key => $count)
                            <a href="{{ $attentionHref($key) }}" class="inline-flex min-h-8 items-center gap-1.5 font-semibold text-warning-text underline-offset-2 hover:underline"><span class="size-1.5 rounded-full bg-warning" aria-hidden="true"></span>{{ $attentionLabel($key, $count) }}</a>
                        @endforeach
                    @endif
                    @isset ($attentionCounts['to_receive'])
                        <a href="{{ route('orders.index', ['tab' => 'to_receive']) }}" class="inline-flex min-h-8 items-center gap-1.5 font-semibold text-brand underline-offset-2 hover:underline"><span class="size-1.5 rounded-full bg-brand" aria-hidden="true"></span>{{ $attentionCounts['to_receive'] }} on the way to you</a>
                    @endisset
                </div>
            @endif

            @if (session('status'))
                <p role="status" class="mt-3 rounded-md border border-[var(--color-success-line)] bg-[var(--color-success-wash)] px-3.5 py-2.5 text-meta font-medium text-success">{{ session('status') }}</p>
            @endif
            @error('received')
                <p role="alert" class="mt-3 rounded-md border border-[var(--color-danger-line)] bg-[var(--color-danger-wash)] px-3.5 py-2.5 text-meta font-medium text-danger-text">{{ $message }}</p>
            @enderror

            <div class="mt-5">
                {{-- The tabs read as the order's journey, bracketed by All and by what comes after delivery,
                     so a rule sits between those groups. Only a tab where the next move is the buyer's gets a
                     warm count; every other count stays quiet. The track scrolls on a phone, so the selected
                     tab and whichever tab takes focus are kept in view. --}}
                @php
                    $buyerTurn = ['to_pay' => $tabCounts['to_pay'] > 0, 'returns' => isset($attentionCounts['returns'])];
                @endphp
                <nav class="relative flex min-w-0 items-stretch overflow-x-auto border-b border-line [scrollbar-width:none] max-sm:[mask-image:linear-gradient(to_right,transparent,#000_20px,#000_calc(100%-20px),transparent)] [&::-webkit-scrollbar]:hidden" aria-label="Purchase status filters" x-data @focusin="$event.target.scrollIntoView({ block: 'nearest', inline: 'center' })">
                    @foreach ($tabs as $key => $label)
                        @if (in_array($key, ['to_pay', 'to_review'], true))<span aria-hidden="true" class="mx-1.5 h-4 w-px shrink-0 self-center bg-line"></span>@endif
                        <a href="{{ route('orders.index', [...request()->except('tab', 'page'), 'tab' => $key]) }}" @class([
                            '-mb-px inline-flex min-h-11 shrink-0 items-center gap-1.5 whitespace-nowrap border-b-2 px-2.5 text-meta font-semibold transition-colors focus-visible:outline-offset-[-2px] lg:min-h-10',
                            'border-brand text-ink' => $tab === $key,
                            'border-transparent text-muted hover:border-line hover:text-ink' => $tab !== $key,
                        ]) @if ($tab === $key) aria-current="page" x-init="$el.parentElement.scrollLeft = Math.max(0, $el.offsetLeft - ($el.parentElement.clientWidth - $el.offsetWidth) / 2)" @endif>
                            {{ $label }}
                            @if ($buyerTurn[$key] ?? false)
                                <span class="inline-grid h-5 min-w-5 place-items-center rounded-full border border-[var(--color-warning-line)] bg-[var(--color-warning-wash)] px-1.5 text-[11px] font-semibold tabular-nums text-warning-text">{{ $tabCounts[$key] }}<span class="sr-only"> {{ Str::plural('order', $tabCounts[$key]) }}, waiting on you</span></span>
                            @elseif ($tabCounts[$key] > 0 || $tab === $key)
                                <span @class(['text-[11px] font-semibold tabular-nums', 'text-brand' => $tab === $key, 'text-muted' => $tab !== $key])>{{ $tabCounts[$key] }}<span class="sr-only"> {{ Str::plural('order', $tabCounts[$key]) }}</span></span>
                            @endif
                        </a>
                    @endforeach
                </nav>
            </div>
            @if (filled($filters['status'] ?? null) && isset($statusStyles[$filters['status']]))
                <p class="mt-2 text-meta text-muted">Filtered to checkouts marked {{ $statusStyles[$filters['status']][0] }} <span aria-hidden="true">·</span> <a href="{{ route('orders.index', request()->except('status', 'page')) }}" class="{{ $inlineLink }}">Show all</a></p>
            @endif
            <x-input-error :messages="$errors->get('tab')" class="mt-2" />
            <x-input-error :messages="$errors->get('status')" class="mt-2" />

            {{-- Each order is its own card: a quiet store bar with the total, then the first two item rows.
                 Every product name keeps a two-row area and breaks after six words on wide screens, and
                 longer orders end with a single "+ N more items" link into the order. --}}
            <div x-data="{
                current: null, shown: null, html: '', loading: false, failed: false, pushed: false, cache: {}, pending: {}, warmTimer: null, trigger: null,
                ids: @js($sellerOrders->pluck('order.id')->unique()->values()),
                showUrl: @js(route('orders.show', ['order' => '__ORDER__'])),
                init() {
                    const id = new URLSearchParams(location.search).get('order');
                    if (id) { this.open(id, 'replace') }
                    window.addEventListener('popstate', () => { const id = new URLSearchParams(location.search).get('order'); this.pushed = false; id ? this.open(id, 'none') : this.close() });
                },
                load(id) {
                    return this.pending[id] ??= fetch(this.showUrl.replace('__ORDER__', id), { credentials: 'same-origin', headers: { 'X-Order-Modal': '1', 'X-Requires-Session': '1', Accept: 'text/html' } })
                        .then(async (response) => { if (! response.ok || response.redirected) { throw new Error('Order unavailable') } return this.cache[id] = await response.text() })
                        .finally(() => delete this.pending[id]);
                },
                preload(id) { if (id && ! this.cache[id]) { this.load(id).catch(() => {}) } },
                warm(id) { clearTimeout(this.warmTimer); this.warmTimer = setTimeout(() => this.preload(id), 100) },
                async open(id, history = 'push') {
                    clearTimeout(this.warmTimer);
                    if (! this.trigger) { this.trigger = document.activeElement }
                    this.current = id; this.loading = true; this.failed = false;
                    if (this.cache[id]) { this.html = this.cache[id]; this.shown = id }
                    if (! this.$refs.modal.open) { this.$refs.modal.showModal(); document.documentElement.style.overflow = 'hidden'; document.documentElement.style.scrollbarGutter = 'stable'; }
                    const url = new URL(location.href); url.searchParams.set('order', id);
                    if (history === 'push' && ! this.pushed) { window.history.pushState({ order: id }, '', url); this.pushed = true } else if (history !== 'none') { window.history.replaceState({ order: id }, '', url) }
                    try {
                        const html = await this.load(id);
                        if (this.current !== id) { return }
                        if (html !== this.html) { this.html = html }
                        this.shown = id;
                    } catch (error) {
                        if (this.current === id && this.shown !== id) { this.failed = true; this.html = '' }
                    } finally {
                        if (this.current === id) { this.loading = false; this.$nextTick(() => { if (! this.$refs.modal.contains(document.activeElement)) { this.$refs.modal.querySelector('[data-modal-focus]')?.focus() } }) }
                    }
                },
                close() { if (this.$refs.modal.open) { this.$refs.modal.close() } },
                closed() {
                    this.loading = false; this.current = null; this.shown = null; this.html = '';
                    document.documentElement.style.overflow = '';
                    document.documentElement.style.scrollbarGutter = '';
                    const trigger = this.trigger; this.trigger = null;
                    this.$nextTick(() => trigger?.focus?.());
                    const url = new URL(location.href);
                    if (! url.searchParams.has('order')) { return }
                    if (this.pushed) { this.pushed = false; window.history.back(); return }
                    url.searchParams.delete('order'); window.history.replaceState({}, '', url);
                },
            }" class="min-w-0">
            <section id="purchases" class="mt-5 scroll-mt-24 flex flex-col gap-6" aria-labelledby="purchase-results-heading">
                <h2 id="purchase-results-heading" class="sr-only">{{ $tabs[$tab] }} purchases{{ $search !== '' ? ' matching “'.$search.'”' : '' }} ({{ $sellerOrders->total() }})</h2>

                @php
                    $orderGroups = $sellerOrders->getCollection()
                        ->groupBy(fn ($sellerOrder): string => $sellerOrder->order->created_at->toDateString())
                        ->sortKeysDesc();
                @endphp

                @forelse ($orderGroups as $groupOrders)
                    @php
                        $groupDate = $groupOrders->first()->order->created_at->copy()->startOfDay();
                        $groupLabel = $groupDate->isToday() ? 'Today' : ($groupDate->isYesterday() ? 'Yesterday' : $groupDate->format($groupDate->isCurrentYear() ? 'M j' : 'M j, Y'));
                        $groupTotal = $groupOrders->reduce(fn (string $sum, $sellerOrder): string => bcadd($sum, (string) $sellerOrder->total, 2), '0.00');
                    @endphp
                    <section class="space-y-3" aria-label="Orders from {{ $groupDate->format('M j, Y') }}">
                        <div class="flex items-baseline justify-between gap-3 px-1">
                            <h3 class="text-meta font-semibold text-ink">{{ $groupLabel }}@if ($groupDate->isToday() || $groupDate->isYesterday())<span class="ml-1.5 font-medium text-muted">{{ $groupDate->format('M j') }}</span>@endif</h3>
                            <p class="text-xs tabular-nums text-muted">{{ $groupOrders->count() }} {{ Str::plural('order', $groupOrders->count()) }} · ₱{{ number_format($groupTotal, 2) }}</p>
                        </div>
                            @foreach ($groupOrders as $sellerOrder)
                                @php
                                    $order = $sellerOrder->order;
                                    $store = $sellerOrder->store->name;
                                    $cancelRequest = $sellerOrder->issues->first(fn ($issue): bool => $issue->type === 'cancellation' && $issue->status === 'open');
                                    $returnCase = $sellerOrder->returnCases->first();
                                    // A missed deadline flips the pill to the turn that matters now.
                                    $returnOverdue = $returnCase?->isOverdue() ?? false;
                                    $storeLate = $returnOverdue && $returnCase->status === 'awaiting_seller';
                                    // Only answering an offer or rejection has a hard buyer cutoff; a late send-back can still be recorded.
                                    $buyerLate = $returnOverdue && in_array($returnCase->status, ['offered', 'rejected'], true);
                                    $storeMissed = $returnOverdue && in_array($returnCase->status, ['awaiting_seller', 'awaiting_receipt', 'awaiting_fulfillment'], true);
                                    $isDirect = $order->payment?->isOnline() ?? false;
                                    $needsPayment = $isDirect && $sellerOrder->status === 'pending' && $sellerOrder->cod_collected_at === null;
                                    [$statusLabel, $statusDot, $statusText] = $statusStyles[$sellerOrder->status];
                                    if ($needsPayment) {
                                        [$statusLabel, $statusDot, $statusText] = ['To pay', 'bg-warning', 'text-warning-text'];
                                    } elseif ($cancelRequest) {
                                        [$statusLabel, $statusDot, $statusText] = ['Cancel requested', 'bg-warning', 'text-warning-text'];
                                    }
                                    $items = $sellerOrder->items;
                                    $itemCount = $items->count();
                                    $remainingItems = max($itemCount - 2, 0);
                                    $shortNumber = Str::upper(Str::substr($order->number, -6));
                                    // The pill is the order's own state; buyer/store lateness replaces it with the turn that matters now.
                                    $overlayLabel = $statusLabel;
                                    $overlayDot = $statusDot;
                                    $overlayText = $statusText;
                                    if ($buyerLate) {
                                        [$overlayLabel, $overlayDot, $overlayText] = ['Response window passed', 'bg-muted', 'text-muted'];
                                    } elseif ($storeMissed) {
                                        [$overlayLabel, $overlayDot, $overlayText] = [$storeLate ? 'Store missed its deadline' : 'Store is running late', $storeLate ? 'bg-warning' : 'bg-muted', $storeLate ? 'text-warning-text' : 'text-ink'];
                                    }
                                @endphp

                                <article class="overflow-hidden rounded-lg border border-line bg-surface shadow-[0_1px_2px_rgb(24_44_72/4%)] cursor-pointer" aria-label="Order {{ $order->number }} from {{ $store }}" @click="if (! $event.target.closest('a, button, form, input')) open(@js($order->id))" @pointerenter="warm(@js($order->id))">
                                    <header class="flex items-center justify-between gap-4 border-b border-line-soft px-4 py-2.5 lg:px-6">
                                        <div class="min-w-0">
                                            <div class="flex min-w-0 items-center gap-2">
                                                <svg class="size-4 shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9.5 5.5 4h13L20 9.5"/><path d="M4 9.5a2.67 2.67 0 0 0 5.33 0 2.67 2.67 0 0 0 5.34 0 2.67 2.67 0 0 0 5.33 0"/><path d="M5.5 12.5V20h13v-7.5"/><path d="M10 20v-4h4v4"/></svg>
                                                <a href="{{ route('catalog.store', $sellerOrder->store->slug) }}" class="truncate text-sm font-semibold text-ink hover:text-brand-hover">{{ $store }}</a>
                                            </div>
                                            <p class="mt-px flex flex-wrap items-center gap-x-1.5 text-xs tabular-nums text-muted">
                                                <a id="purchase-number-{{ $sellerOrder->id }}" href="{{ route('orders.show', $order) }}" @click.prevent="open(@js($order->id))" @pointerenter="warm(@js($order->id))" class="inline-flex min-h-6 items-center underline-offset-2 hover:text-ink hover:underline"><span aria-hidden="true">#…{{ $shortNumber }}</span><span class="sr-only">Order {{ $order->number }}</span></a>
                                                <span aria-hidden="true" class="text-[var(--color-border-strong)]">·</span>
                                                <span>{{ $itemCount }} {{ Str::plural('item', $itemCount) }}</span>
                                                <span aria-hidden="true" class="text-[var(--color-border-strong)]">·</span>
                                                <span>₱{{ number_format($sellerOrder->total, 2) }}</span>
                                            </p>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-2.5">
                                            <span @class(['inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap text-xs font-semibold', $overlayText])>
                                                <span class="size-1.5 rounded-full {{ $overlayDot }}" aria-hidden="true"></span>
                                                {{ $overlayLabel }}
                                            </span>
                                            <form method="POST" action="{{ route('messages.seller-orders.start', $sellerOrder) }}">
                                                @csrf
                                                <button type="submit" class="{{ $chatAction }}" title="Chat with {{ $store }}" aria-label="Chat with {{ $store }}, order {{ $order->number }}">
                                                    <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.5 11.5a8.5 8.5 0 0 1-4.7 7.6 8.38 8.38 0 0 1-3.8.9 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </header>

                                    <ul class="divide-y divide-line-soft">
                                        @foreach ($items->take(2) as $item)
                                            @php
                                                $itemThumb = $item->image_path ?? $item->product?->thumbnail?->path;
                                                $nameWords = preg_split('/\s+/', trim($item->product_name), -1, PREG_SPLIT_NO_EMPTY);
                                                $nameFirstLine = implode(' ', array_slice($nameWords, 0, 6));
                                                $nameRemaining = implode(' ', array_slice($nameWords, 6));
                                            @endphp
                                            <li>
                                                <a href="{{ route('orders.show', $order) }}" @click.prevent="open(@js($order->id))" @pointerenter="warm(@js($order->id))" aria-label="View {{ $item->product_name }} in order {{ $order->number }} from {{ $store }}" class="group grid grid-cols-[56px_minmax(0,1fr)] gap-x-4 px-4 py-3.5 transition-colors hover:bg-[var(--color-sunken)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-brand sm:grid-cols-[56px_minmax(0,1fr)_108px_44px] lg:px-6">
                                                    <div class="relative size-14 overflow-hidden rounded-md border border-line bg-paper">
                                                        @if ($itemThumb)
                                                            <img src="{{ Storage::disk('product_images')->url($itemThumb) }}" alt="" width="56" height="56" loading="lazy" class="size-full object-cover">
                                                        @else
                                                            <svg class="absolute inset-0 m-auto size-5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M4 8h16l-1 12H5L4 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
                                                        @endif
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="line-clamp-2 min-h-10 break-words text-sm leading-5 text-ink transition-colors group-hover:text-brand-hover">{{ $nameFirstLine }}@if ($nameRemaining !== '') <br class="hidden lg:block">{{ $nameRemaining }}@endif</p>
                                                        @if ($item->variant_label)
                                                            <p class="mt-0.5 text-xs leading-[18px] text-muted">{{ $item->variant_label }}</p>
                                                        @endif
                                                    </div>
                                                    <div class="col-start-2 mt-1 flex items-baseline justify-between gap-4 sm:contents">
                                                        <p class="tabular-nums text-sm text-ink sm:col-start-3 sm:pt-px sm:text-right"><span class="sr-only">Price </span>₱{{ number_format($item->unit_price, 2) }}</p>
                                                        <p class="tabular-nums text-[13px] text-muted sm:col-start-4 sm:pt-px sm:text-right"><span aria-hidden="true">×</span>{{ $item->quantity }}<span class="sr-only"> quantity, {{ $item->quantity }}</span></p>
                                                    </div>
                                                </a>
                                            </li>
                                        @endforeach
                                        @if ($remainingItems > 0)
                                            <li>
                                                <a href="{{ route('orders.show', $order) }}" @click.prevent="open(@js($order->id))" @pointerenter="warm(@js($order->id))" aria-label="View {{ $remainingItems }} more {{ Str::plural('item', $remainingItems) }} in order {{ $order->number }} from {{ $store }}" class="grid grid-cols-[56px_minmax(0,1fr)] items-center gap-x-4 px-4 py-2.5 text-[13px] font-semibold text-muted transition-colors hover:bg-[var(--color-sunken)] hover:text-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-brand lg:px-6">
                                                    <span aria-hidden="true"></span>
                                                    <span class="flex items-center gap-1.5">
                                                        <span aria-hidden="true">+</span>
                                                        {{ $remainingItems }} more {{ Str::plural('item', $remainingItems) }}
                                                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                                                    </span>
                                                </a>
                                            </li>
                                        @endif
                                    </ul>

                                </article>
                            @endforeach
                    </section>
                @empty
                    <div class="rounded-lg border border-line bg-surface shadow-[0_1px_2px_rgb(24_44_72/4%)] px-5 py-14 text-center">
                        @if ($hasFilters)
                            <h3 class="text-lg font-semibold text-ink">{{ $search !== '' ? 'No purchases match “'.$search.'”'.($tab !== 'all' ? ' in '.$tabs[$tab] : '') : 'No purchases match those filters' }}</h3>
                            <p class="mx-auto mt-2 max-w-sm text-meta text-muted">Try an order number, a store name, or part of a product name.</p>
                            <div class="mt-4 flex flex-wrap justify-center gap-2">
                                @if ($search !== '')<a href="{{ route('orders.index', request()->except('search', 'page')) }}" class="{{ $outlineAction }}">Clear search</a>@endif
                                @if ($tab !== 'all' || filled($filters['status'] ?? null))<a href="{{ route('orders.index') }}" class="{{ $outlineAction }}">Reset filters</a>@endif
                            </div>
                        @else
                            <h3 class="text-lg font-semibold text-ink">{{ $emptyCopy[$tab][0] }}</h3>
                            <p class="mx-auto mt-2 max-w-sm text-meta text-muted">{{ $emptyCopy[$tab][1] }}</p>
                            @php($showAll = $tab !== 'all' && $tabCounts['all'] > 0)
                            @if ($tab === 'returns' && $tabCounts['delivered'] > 0)
                                <a href="{{ route('orders.index', ['tab' => 'delivered']) }}" class="{{ $outlineAction }} mt-4">View delivered orders</a>
                            @else
                                <a href="{{ $showAll ? route('orders.index') : route('catalog.index') }}" class="{{ $showAll ? $outlineAction : $primaryAction }} mt-4">{{ $showAll ? 'View all purchases' : 'Browse products' }}</a>
                            @endif
                        @endif
                    </div>
                @endforelse

                {{-- Load more swaps in the whole longer list rather than appending rows, because orders are
                     grouped by day and a day can straddle two batches. The link still works without script. --}}
                @if ($sellerOrders->total() > $sellerOrders->count() || $sellerOrders->count() > 10)
                    <div class="flex flex-col items-stretch gap-3 rounded-lg border border-line bg-surface shadow-[0_1px_2px_rgb(24_44_72/4%)] px-4 py-3.5 text-center sm:flex-row sm:items-center sm:justify-between sm:text-left lg:px-6" x-data="{ loading: false, async more(link) {
                        if (this.loading) return;
                        this.loading = true;
                        const shown = document.querySelectorAll('#purchases article').length;
                        try {
                            const response = await fetch(link.href, { headers: { Accept: 'text/html' } });
                            if (! response.ok) throw new Error(response.status);
                            const fresh = new DOMParser().parseFromString(await response.text(), 'text/html').getElementById('purchases');
                            document.getElementById('purchases').replaceWith(fresh);
                            history.replaceState(history.state, '', link.href);
                            const first = fresh.querySelectorAll('article')[shown];
                            first?.setAttribute('tabindex', '-1');
                            first?.focus({ preventScroll: true });
                        } catch {
                            window.location.assign(link.href);
                        }
                    } }">
                        <p class="text-meta text-muted">Showing {{ $sellerOrders->count() }} of {{ $sellerOrders->total() }} {{ Str::plural('order', $sellerOrders->total()) }}</p>
                        @if ($loadMoreUrl)
                            <a href="{{ $loadMoreUrl }}" @click.prevent="more($el)" :aria-busy="loading" :class="loading && 'pointer-events-none opacity-60'" class="{{ $outlineAction }} sm:w-[152px]">
                                <span x-show="! loading">Load more</span>
                                <span x-cloak x-show="loading">Loading…</span>
                            </a>
                        @endif
                    </div>
                @endif
            </section>

            <dialog x-ref="modal" class="buyer-order-dialog buyer-order-dialog--ledger" aria-labelledby="dlgTitle" aria-describedby="dlgMeta" aria-label="Order details" @close="closed()" @click="if ($event.target === $refs.modal) close()">
                <div x-show="loading && ! html" class="buyer-modal-body" role="status" aria-live="polite" aria-busy="true">
                    <span class="sr-only">Loading order details</span>
                    <div class="sk">
                        <div class="sk-line is-short"></div>
                        <div class="sk-line is-title"></div>
                        <div class="sk-journey"></div>
                        <div class="sk-block"></div>
                        <div class="sk-block"></div>
                    </div>
                </div>
                <div x-show="failed" x-cloak class="buyer-modal-body" role="alert">
                    <div class="flex flex-col items-center justify-center py-10 text-center">
                        <span class="grid size-12 place-items-center rounded-full bg-[var(--color-danger-wash)] text-danger" aria-hidden="true"><svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v5M12 16h.01"/></svg></span>
                        <p class="mt-4 text-base font-semibold text-ink">We couldn’t load this order</p>
                        <p class="mt-1 max-w-xs text-sm leading-6 text-muted">Your connection may have dropped. Try again, or open the full order page.</p>
                        <div class="mt-5 flex flex-wrap justify-center gap-2">
                            <button type="button" class="min-h-11 rounded-lg border border-line bg-surface px-4 text-sm font-semibold text-ink" @click="close()">Close</button>
                            <button type="button" class="min-h-11 rounded-lg border border-line bg-surface px-4 text-sm font-semibold text-ink" @click="open(current, 'none')">Try again</button>
                            <a :href="showUrl.replace('__ORDER__', current)" class="inline-flex min-h-11 items-center rounded-lg bg-brand px-4 text-sm font-semibold text-white">Open full page</a>
                        </div>
                    </div>
                </div>
                <div x-show="html" x-html="html" class="contents" :data-stale="String(shown !== current)"></div>
            </dialog>
            </div>
        </div>
    </x-marketplace.account-shell>
</x-app-layout>
