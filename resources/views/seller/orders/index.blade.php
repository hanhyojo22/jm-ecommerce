@php
    $statuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
    $paymentStatuses = ['pending' => 'Unpaid', 'paid' => 'Paid', 'failed' => 'Failed', 'refunded' => 'Refunded'];
    $queryWithoutStatus = request()->except('status', 'page', 'order');
    $hasFilters = collect($filters)->filter()->isNotEmpty();
    $panelFilterCount = collect(['payment_status', 'from', 'to'])->filter(fn (string $field): bool => filled($filters[$field] ?? null))->count();
    $activeStatus = $filters['status'] ?? null;
    $hasPanelFilters = filled($filters['payment_status'] ?? null) || filled($filters['from'] ?? null) || filled($filters['to'] ?? null);
    $nextStep = fn ($sellerOrder): string => match ($sellerOrder->status) {
        'pending' => 'Confirm to start preparing',
        'confirmed' => 'Start preparing',
        'processing' => 'Ship when packed',
        'shipped' => 'Awaiting delivery',
        'delivered' => $sellerOrder->cod_collected_at ? 'Complete' : 'Record cash collected',
        default => 'Stock restored',
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="sc-page-head !items-center">
            <h1 class="sc-title">Orders</h1>
            <form method="GET" action="{{ route('seller.orders.index') }}" role="search" class="relative w-full sm:w-80">
                @foreach (['status', 'payment_status', 'from', 'to'] as $field)@if (filled($filters[$field] ?? null))<input type="hidden" name="{{ $field }}" value="{{ $filters[$field] }}">@endif @endforeach
                <label for="search" class="sr-only">Search orders</label>
                <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                <input id="search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search order number or customer" class="sc-input pl-9">
            </form>
        </div>
    </x-slot>

    <div class="page-shell"><div class="page-container seller-workspace-page sc-stack">
        @if (session('status'))<div class="sc-notice" data-tone="success" role="status">{{ session('status') }}</div>@endif

        @if ($cashToCollectCount > 0)
            <div class="sc-notice" data-tone="warning"><span><strong>{{ number_format($cashToCollectCount) }} delivered {{ Str::plural('order', $cashToCollectCount) }}</strong> still {{ $cashToCollectCount === 1 ? 'has' : 'have' }} cash to collect.</span><a href="{{ route('seller.orders.index', ['status' => 'delivered', 'payment_status' => 'pending']) }}">Review unpaid deliveries</a></div>
        @endif

        {{-- Orders open in a modal over the list. The open order lives in ?order= so a refresh, a shared
             link or the redirect after an action reopens it, and the browser back button closes it. --}}
        <section class="sc-card" aria-label="Orders list" @keydown.window="if ($refs.modal.open && ['ArrowLeft', 'ArrowRight'].includes($event.key) && ! $event.target.closest('input, textarea, select')) step($event.key === 'ArrowRight' ? 1 : -1)" x-data="{
            filtersOpen: false, current: null, shown: null, html: '', loading: false, failed: false, pushed: false, cache: {}, pending: {}, warmTimer: null,
            ids: @js($orders->pluck('id')), showUrl: @js(route('seller.orders.show', '__ORDER__')),
            init() {
                const id = new URLSearchParams(location.search).get('order');
                if (id) { this.open(id, 'replace') }
                window.addEventListener('popstate', () => { const id = new URLSearchParams(location.search).get('order'); this.pushed = false; id ? this.open(id, 'none') : this.close() });
            },
            {{-- X-Requires-Session lets app.js renew the 60-second Clerk cookie first; without it a lapsed
                 cookie redirects this fetch to the sign-in bridge page and the order fails to load.
                 A preload and a click on the same order share one request. --}}
            load(id) {
                return this.pending[id] ??= fetch(this.showUrl.replace('__ORDER__', id), { headers: { 'X-Order-Modal': '1', 'X-Requires-Session': '1', Accept: 'text/html' } })
                    .then(async (response) => { if (! response.ok || response.redirected) { throw new Error('Order unavailable') } return this.cache[id] = await response.text() })
                    .finally(() => delete this.pending[id]);
            },
            preload(id) { if (id && ! this.cache[id]) { this.load(id).catch(() => {}) } },
            {{-- Resting the pointer on an order starts loading it, so the click usually finds it ready. --}}
            warm(id) { clearTimeout(this.warmTimer); this.warmTimer = setTimeout(() => this.preload(id), 100) },
            {{-- A cached order shows at once and is refreshed underneath; the orders either side are fetched ahead so stepping is instant. --}}
            async open(id, history = 'push') {
                clearTimeout(this.warmTimer);
                this.current = id; this.loading = true; this.failed = false;
                if (this.cache[id]) { this.html = this.cache[id]; this.shown = id }
                if (! this.$refs.modal.open) { this.$refs.modal.showModal() }
                const url = new URL(location.href); url.searchParams.set('order', id);
                if (history === 'push' && ! this.pushed) { window.history.pushState({ order: id }, '', url); this.pushed = true } else if (history !== 'none') { window.history.replaceState({ order: id }, '', url) }
                try {
                    const html = await this.load(id);
                    if (this.current !== id) { return }
                    if (html !== this.html) { this.html = html }
                    this.shown = id;
                    const index = this.ids.indexOf(id);
                    if (index !== -1) { this.preload(this.ids[index + 1]); this.preload(this.ids[index - 1]) }
                } catch (error) {
                    if (this.current === id && this.shown !== id) { this.failed = true; this.html = '' }
                } finally {
                    if (this.current === id) { this.loading = false; this.$nextTick(() => { if (! this.$refs.modal.contains(document.activeElement)) { this.$refs.modal.querySelector('[data-modal-focus]')?.focus() } }) }
                }
            },
            close() { if (this.$refs.modal.open) { this.$refs.modal.close() } },
            closed() {
                this.loading = false; this.current = null; this.shown = null; this.html = '';
                const url = new URL(location.href);
                if (! url.searchParams.has('order')) { return }
                if (this.pushed) { this.pushed = false; window.history.back(); return }
                url.searchParams.delete('order'); window.history.replaceState({}, '', url);
            },
            hasStep(offset) { const index = this.ids.indexOf(this.current); return index !== -1 && this.ids[index + offset] !== undefined },
            step(offset) { if (this.hasStep(offset)) { this.open(this.ids[this.ids.indexOf(this.current) + offset], 'replace') } },
        }">
            <div class="relative flex items-center gap-2 border-b border-[#eef1f5] pr-3 sm:pr-5">
                <nav class="sc-tabs sc-tabs-scroll min-w-0 flex-1 !border-0" aria-label="Order status">
                    <a href="{{ route('seller.orders.index', $queryWithoutStatus) }}" class="sc-tab" @if (! $activeStatus) aria-current="page" @endif>All <span class="sc-tab-count" aria-hidden="true">{{ number_format($statusCounts->sum()) }}</span></a>
                    @foreach ($statuses as $status)
                        <a href="{{ route('seller.orders.index', [...$queryWithoutStatus, 'status' => $status]) }}" class="sc-tab" @if ($activeStatus === $status) aria-current="page" @endif>{{ ucfirst($status) }} <span class="sc-tab-count" aria-hidden="true">{{ number_format($statusCounts[$status] ?? 0) }}</span></a>
                    @endforeach
                </nav>
                {{-- Search shows in its box and status in the tabs; payment and dates are summarised on this button. --}}
                @if ($hasFilters)<a href="{{ route('seller.orders.index') }}" class="sc-link shrink-0 max-sm:hidden">Clear</a>@endif
                <button type="button" @class(['seller-outline-button !min-h-9 shrink-0', '!border-[#c7d6fb] !bg-[#eef3ff] !text-[#1d4ed8]' => $panelFilterCount > 0]) @click="filtersOpen = ! filtersOpen" :aria-expanded="filtersOpen.toString()" aria-controls="order-filters"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>Filters @if ($panelFilterCount > 0)<span class="sc-tab-count !bg-white !text-[#1d4ed8]" aria-hidden="true">{{ $panelFilterCount }}</span><span class="sr-only">, {{ $panelFilterCount }} active {{ Str::plural('filter', $panelFilterCount) }}</span>@endif</button>

                <form id="order-filters" method="GET" action="{{ route('seller.orders.index') }}" class="sc-popover" x-cloak x-show="filtersOpen" @click.outside="filtersOpen = false" @keydown.escape.window="filtersOpen = false" aria-label="Order filters">
                    @foreach (['search', 'status'] as $field)@if (filled($filters[$field] ?? null))<input type="hidden" name="{{ $field }}" value="{{ $filters[$field] }}">@endif @endforeach
                    <div class="sc-field"><label for="payment_status">Payment</label><select id="payment_status" name="payment_status" class="sc-input"><option value="">All payments</option>@foreach ($paymentStatuses as $value => $label)<option value="{{ $value }}" @selected(($filters['payment_status'] ?? null) === $value)>{{ $label }}</option>@endforeach</select></div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="sc-field"><label for="from">From</label><input id="from" name="from" value="{{ $filters['from'] ?? '' }}" type="date" class="sc-input"></div>
                        <div class="sc-field"><label for="to">To</label><input id="to" name="to" value="{{ $filters['to'] ?? '' }}" type="date" class="sc-input"></div>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        @if ($hasPanelFilters)<a href="{{ route('seller.orders.index', request()->only('search', 'status')) }}" class="sc-link">Reset</a>@else<span></span>@endif
                        <button type="submit" class="seller-primary-button">Apply filters</button>
                    </div>
                </form>
            </div>


            {{-- On the Pending tab, one button confirms the pending orders on this page, up to the 20 the bulk
                 action accepts. Orders the buyer asked to cancel are left for support to decide. --}}
            @php($confirmableIds = $orders->filter(fn ($sellerOrder) => $sellerOrder->status === 'pending' && ! $sellerOrder->has_open_cancellation)->pluck('id')->take(20))
            @if ($canManageOrders && $activeStatus === 'pending' && $confirmableIds->isNotEmpty())
                <form method="POST" action="{{ route('seller.orders.bulk-confirm') }}" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b border-[#eef1f5] bg-[#f8fafc] px-5 py-2.5 max-sm:px-4" x-data @submit="if (! confirm(@js('Confirm '.$confirmableIds->count().' pending '.Str::plural('order', $confirmableIds->count()).'? They move to confirmed and the buyers are notified.'))) $event.preventDefault()">
                    @csrf
                    @foreach ($confirmableIds as $confirmableId)<input type="hidden" name="seller_order_ids[]" value="{{ $confirmableId }}">@endforeach
                    <p class="text-sm text-muted">{{ $confirmableIds->count() }} {{ Str::plural('order', $confirmableIds->count()) }} waiting for you to confirm.</p>
                    <button type="submit" class="seller-primary-button">Confirm all pending ({{ $confirmableIds->count() }})</button>
                </form>
            @endif
            <x-input-error :messages="$errors->get('seller_order_ids')" class="mx-5 my-3" />

            @if ($orders->isNotEmpty())
                <div class="sc-table-wrap hidden lg:block">
                    <table class="sc-table">
                        <thead><tr><th scope="col">Order</th><th scope="col">Items</th><th scope="col" class="is-num">Your total</th><th scope="col">Status</th><th scope="col" class="is-tight"><span class="sr-only">Open</span></th></tr></thead>
                        <tbody>
                            @foreach ($orders as $sellerOrder)
                                @php($otherItems = max(0, $sellerOrder->items->count() - 1))
                                <tr class="cursor-pointer" @click="if (! $event.target.closest('a, button, input, label')) open(@js($sellerOrder->id))">
                                    {{-- Who and when sit under the order number: number · time, then customer · city. --}}
                                    <th scope="row" class="max-w-[280px]">
                                        <p class="whitespace-nowrap"><a href="{{ route('seller.orders.show', $sellerOrder) }}" @click="if (! ($event.metaKey || $event.ctrlKey || $event.shiftKey)) { $event.preventDefault(); open(@js($sellerOrder->id)) }" @pointerenter="warm(@js($sellerOrder->id))" @pointerleave="clearTimeout(warmTimer)" @focus="preload(@js($sellerOrder->id))" class="font-semibold text-ink hover:text-brand"><x-marketplace.order-number :number="$sellerOrder->order->number" /></a> <span class="sc-meta" title="{{ $sellerOrder->created_at->format('M j, Y · g:i A') }}">· {{ $sellerOrder->created_at->diffForHumans(short: true) }}</span></p>
                                        <p class="truncate sc-meta" title="{{ collect([$sellerOrder->order->recipient_name, $sellerOrder->order->city])->filter()->join(' · ') }}">{{ collect([$sellerOrder->order->recipient_name, $sellerOrder->order->city])->filter()->join(' · ') }}</p>
                                    </th>
                                    <td>@include('seller.orders._items', ['sellerOrder' => $sellerOrder, 'otherItems' => $otherItems])</td>
                                    <td class="is-num font-semibold">₱{{ number_format($sellerOrder->total, 2) }}</td>
                                    <td><x-marketplace.order-status :status="$sellerOrder->status" /><span class="mt-0.5 block whitespace-nowrap text-xs text-muted">{{ $nextStep($sellerOrder) }}</span></td>
                                    <td class="is-tight"><a href="{{ route('seller.orders.show', $sellerOrder) }}" @click="if (! ($event.metaKey || $event.ctrlKey || $event.shiftKey)) { $event.preventDefault(); open(@js($sellerOrder->id)) }" @pointerenter="warm(@js($sellerOrder->id))" @pointerleave="clearTimeout(warmTimer)" @focus="preload(@js($sellerOrder->id))" class="grid size-8 place-items-center rounded-lg text-[#a3b1c4] hover:bg-[#eef1f5] hover:text-ink"><span class="sr-only">View order</span><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="sc-list lg:hidden">
                    @foreach ($orders as $sellerOrder)
                        @php($otherItems = max(0, $sellerOrder->items->count() - 1))
                        <article class="sc-list-item">
                            <div class="sc-row">
                                <p class="sc-grow"><a href="{{ route('seller.orders.show', $sellerOrder) }}" @click="if (! ($event.metaKey || $event.ctrlKey || $event.shiftKey)) { $event.preventDefault(); open(@js($sellerOrder->id)) }" @pointerenter="warm(@js($sellerOrder->id))" @pointerleave="clearTimeout(warmTimer)" @focus="preload(@js($sellerOrder->id))" class="sc-strong hover:text-brand"><x-marketplace.order-number :number="$sellerOrder->order->number" /></a> <span class="sc-meta">· {{ $sellerOrder->created_at->diffForHumans(short: true) }}</span></p>
                                <x-marketplace.order-status :status="$sellerOrder->status" />
                            </div>
                            <div class="sc-row mt-3">
                                <div class="sc-grow">@include('seller.orders._items', ['sellerOrder' => $sellerOrder, 'otherItems' => $otherItems])<p class="mt-1 truncate sc-meta">{{ $sellerOrder->order->recipient_name }}@if ($sellerOrder->order->city) · {{ $sellerOrder->order->city }}@endif</p></div>
                                <span class="sc-strong sc-num">₱{{ number_format($sellerOrder->total, 2) }}</span>
                            </div>
                            <div class="sc-row mt-2">
                                <span class="sc-grow truncate text-xs text-muted">{{ $nextStep($sellerOrder) }}</span>
                                <a href="{{ route('seller.orders.show', $sellerOrder) }}" @click="if (! ($event.metaKey || $event.ctrlKey || $event.shiftKey)) { $event.preventDefault(); open(@js($sellerOrder->id)) }" @pointerenter="warm(@js($sellerOrder->id))" @pointerleave="clearTimeout(warmTimer)" @focus="preload(@js($sellerOrder->id))" class="sc-link min-h-10">View order <span aria-hidden="true">&rsaquo;</span></a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="sc-empty"><h3>{{ $hasFilters ? 'No orders match these filters' : 'No orders yet' }}</h3><p>{{ $hasFilters ? 'Adjust your search or clear filters to try again.' : 'Orders from your store will appear here after checkout.' }}</p>@if ($hasFilters)<a href="{{ route('seller.orders.index') }}" class="seller-outline-button">Clear filters</a>@endif</div>
            @endif

            @if ($orders->total() > 0)
                <div class="sc-card-foot sc-pager">
                    <span>Showing {{ $orders->firstItem() ?? 0 }}–{{ $orders->lastItem() ?? 0 }} of {{ number_format($orders->total()) }} {{ Str::plural('order', $orders->total()) }}</span>
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-3">
                        <form method="GET" action="{{ route('seller.orders.index') }}" class="flex items-center gap-2">
                            @foreach (request()->except('per_page', 'page', 'order') as $field => $value)@if (is_string($value))<input type="hidden" name="{{ $field }}" value="{{ $value }}">@endif @endforeach
                            <label for="per-page" class="whitespace-nowrap">Rows per page</label>
                            <select id="per-page" name="per_page" class="sc-input !h-9 !w-auto !min-h-9 !py-0 !pl-3 !pr-8" onchange="this.form.submit()">@foreach (\App\Http\Controllers\Seller\SellerOrderController::PER_PAGE_OPTIONS as $option)<option value="{{ $option }}" @selected($orders->perPage() === $option)>{{ $option }}</option>@endforeach</select>
                            <noscript><button type="submit" class="seller-outline-button !min-h-9">Show</button></noscript>
                        </form>
                        @if ($orders->hasPages()){{ $orders->links() }}@endif
                    </div>
                </div>
            @endif
            <dialog x-ref="modal" class="sc-modal" aria-labelledby="order-modal-title" @close="closed()" @click="if ($event.target === $refs.modal) close()">
                <div x-show="loading && ! html" class="sc-modal-body" role="status"><span class="sr-only">Loading order</span><div class="animate-pulse space-y-4"><div class="h-5 w-48 rounded bg-[#eef1f5]"></div><div class="h-1 w-full rounded bg-[#eef1f5]"></div><div class="h-24 rounded-lg bg-[#f5f7fa]"></div><div class="grid gap-4 sm:grid-cols-2"><div class="h-28 rounded-lg bg-[#f5f7fa]"></div><div class="h-28 rounded-lg bg-[#f5f7fa]"></div></div></div></div>
                <div x-show="failed" x-cloak class="sc-modal-body text-center" role="alert"><p class="font-semibold text-ink">We couldn’t load this order.</p><p class="mt-1 sc-meta">Check your connection and try again, or open the full order page.</p><div class="mt-4 flex justify-center gap-2"><button type="button" class="seller-outline-button" @click="close()">Close</button><button type="button" class="seller-outline-button" @click="open(current, 'none')">Try again</button><a :href="showUrl.replace('__ORDER__', current)" class="seller-primary-button">Open full page</a></div></div>
                {{-- While the next order loads, the previous one stays visible but its body and actions are disabled so nothing is done to the wrong order. --}}
                <div x-show="html" x-html="html" class="contents" :data-stale="String(shown !== current)"></div>
            </dialog>
        </section>
    </div></div>
</x-app-layout>
