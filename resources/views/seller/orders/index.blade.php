@php
    $statuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
    $paymentStatuses = ['pending' => 'Unpaid', 'paid' => 'Paid', 'failed' => 'Failed', 'refunded' => 'Refunded'];
    $queryWithoutStatus = request()->except('status', 'review', 'page', 'order');
    $hasFilters = collect($filters)->filter()->isNotEmpty();
    $panelFilterCount = collect(['status', 'payment_status', 'from', 'to'])->filter(fn (string $field): bool => filled($filters[$field] ?? null))->count();
    $activeStatus = $filters['status'] ?? null;
    $hasPanelFilters = filled($filters['status'] ?? null) || filled($filters['payment_status'] ?? null) || filled($filters['from'] ?? null) || filled($filters['to'] ?? null);
    $nextStep = fn ($sellerOrder): string => match ($sellerOrder->status) {
        'pending' => match (true) {
            (bool) $sellerOrder->awaiting_prescription_photo => 'Waiting for a clearer photo',
            (bool) $sellerOrder->prescription_checked => 'Checked · pharmacist to approve',
            (bool) $sellerOrder->needs_prescription_review => 'Prescription to review',
            default => 'Accept to start preparing',
        },
        'confirmed' => 'Start preparing',
        'processing' => $sellerOrder->order?->fulfillment_method === 'pickup' ? 'Mark ready for pickup' : 'Hand to the rider',
        'shipped' => $sellerOrder->order?->fulfillment_method === 'pickup' ? 'Waiting for the customer' : 'Out for delivery',
        'delivered' => $sellerOrder->cod_collected_at ? 'Complete' : 'Record cash collected',
        default => 'Stock restored',
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="sc-page-head !items-center">
            <h1 class="sc-title">Orders</h1>

        </div>
    </x-slot>

    <div class="page-shell"><div class="page-container seller-workspace-page seller-measure-wide sc-stack">
        @if (session('status'))<div class="sc-notice" data-tone="success" role="status">{{ session('status') }}</div>@endif

        @if ($cashToCollectCount > 0)
            <div class="sc-notice" data-tone="warning"><span><strong>{{ number_format($cashToCollectCount) }} delivered {{ Str::plural('order', $cashToCollectCount) }}</strong> still {{ $cashToCollectCount === 1 ? 'has' : 'have' }} cash to collect.</span><a href="{{ route('seller.orders.index', ['status' => 'delivered', 'payment_status' => 'pending']) }}">Review unpaid deliveries</a></div>
        @endif

        {{-- The selected order lives in ?order= so refreshes, shared links and action redirects reopen its detail panel. --}}
        <section class="sc-card sc-orders-workspace" aria-label="Orders list" @keydown.escape.window="if (! filtersOpen && ! document.querySelector('dialog[open]')) close()" @keydown.window="if (current && ! document.querySelector('dialog[open]') && ['ArrowLeft', 'ArrowRight'].includes($event.key) && ! $event.target.closest('input, textarea, select')) step($event.key === 'ArrowRight' ? 1 : -1)" x-data="{
            filtersOpen: false, current: null, shown: null, html: '', loading: false, failed: false, pushed: false, cache: {}, pending: {}, warmTimer: null,
            ids: @js($orders->pluck('id')), showUrl: @js(route('seller.orders.show', '__ORDER__')),
            init() {
                const id = new URLSearchParams(location.search).get('order');
                if (id || this.ids.length) { this.open(id || this.ids[0], 'replace') }
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
                    if (this.current === id) { this.loading = false; this.$nextTick(() => { if (! this.$refs.modal.contains(document.activeElement)) { this.$refs.modal.querySelector('[data-modal-focus]')?.focus({ preventScroll: true }) } }) }
                }
            },
            close() { this.closed() },
            closed() {
                this.loading = false; this.current = null; this.shown = null; this.html = '';
                const url = new URL(location.href);
                if (! url.searchParams.has('order')) { return }
                this.pushed = false;
                url.searchParams.delete('order'); window.history.replaceState({}, '', url);
            },
            hasStep(offset) { const index = this.ids.indexOf(this.current); return index !== -1 && this.ids[index + offset] !== undefined },
            step(offset) { if (this.hasStep(offset)) { this.open(this.ids[this.ids.indexOf(this.current) + offset], 'replace') } },
        }">
            <div class="sc-orders-columns">
                <aside class="sc-orders-queue" aria-label="Order queue">
                    <div class="sc-queue-head"><h2>Order queue</h2><span>{{ number_format($orders->total()) }}</span>
                <button type="button" @class(['seller-outline-button !min-h-9 shrink-0', '!border-[#c7d6fb] !bg-[#eef3ff] !text-[#1d4ed8]' => $panelFilterCount > 0]) @click="filtersOpen = ! filtersOpen" :aria-expanded="filtersOpen.toString()" aria-controls="order-filters"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg><span class="sr-only">Filters</span> @if ($panelFilterCount > 0)<span class="sc-tab-count !bg-white !text-[#1d4ed8]" aria-hidden="true">{{ $panelFilterCount }}</span><span class="sr-only">, {{ $panelFilterCount }} active {{ Str::plural('filter', $panelFilterCount) }}</span>@endif</button>
                <form id="order-filters" method="GET" action="{{ route('seller.orders.index') }}" class="sc-popover" x-cloak x-show="filtersOpen" @click.outside="filtersOpen = false" @keydown.escape.window="filtersOpen = false" aria-label="Order filters">
                    @foreach (['search'] as $field)@if (filled($filters[$field] ?? null))<input type="hidden" name="{{ $field }}" value="{{ $filters[$field] }}">@endif @endforeach
                    <div class="sc-field"><label for="review-filter">Documents</label><select id="review-filter" name="review" class="sc-input"><option value="">All documents</option><option value="prescription" @selected(($filters['review'] ?? null) === 'prescription')>Prescription</option><option value="discount" @selected(($filters['review'] ?? null) === 'discount')>SC/PWD</option></select></div>
                    <div class="sc-field"><label for="order-status-filter">Status</label><select id="order-status-filter" name="status" class="sc-input"><option value="">All statuses</option>@foreach ($statuses as $status)<option value="{{ $status }}" @selected($activeStatus === $status)>{{ \App\Models\SellerOrder::statusLabel($status) }}</option>@endforeach</select></div>
                    <div class="sc-field"><label for="payment_status">Payment</label><select id="payment_status" name="payment_status" class="sc-input"><option value="">All payments</option>@foreach ($paymentStatuses as $value => $label)<option value="{{ $value }}" @selected(($filters['payment_status'] ?? null) === $value)>{{ $label }}</option>@endforeach</select></div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="sc-field"><label for="from">From</label><input id="from" name="from" value="{{ $filters['from'] ?? '' }}" type="date" class="sc-input"></div>
                        <div class="sc-field"><label for="to">To</label><input id="to" name="to" value="{{ $filters['to'] ?? '' }}" type="date" class="sc-input"></div>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        @if ($hasPanelFilters)<a href="{{ route('seller.orders.index', request()->only('search', 'review')) }}" class="sc-link">Reset</a>@else<span></span>@endif
                        <button type="submit" class="seller-primary-button">Apply filters</button>
                    </div>
                </form>
                    </div>
            <form method="GET" action="{{ route('seller.orders.index') }}" role="search" class="sc-queue-search">
                @foreach (['status', 'review', 'payment_status', 'from', 'to'] as $field)@if (filled($filters[$field] ?? null))<input type="hidden" name="{{ $field }}" value="{{ $filters[$field] }}">@endif @endforeach
                <label for="search" class="sr-only">Search orders</label>
                <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                <input id="search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search customer or order" data-seller-shortcut="search" class="sc-input pl-9">
            </form>
                    <nav class="sc-queue-filters" aria-label="Order status">
                        <a href="{{ route('seller.orders.index', $queryWithoutStatus) }}" @if (! $activeStatus && empty($filters['review'])) aria-current="page" @endif>All orders</a>
                        <a href="{{ route('seller.orders.index', [...$queryWithoutStatus, 'review' => 'prescription']) }}" @if (($filters['review'] ?? null) === 'prescription') aria-current="page" @endif>Prescription</a>
                        <a href="{{ route('seller.orders.index', [...$queryWithoutStatus, 'status' => 'processing']) }}" @if ($activeStatus === 'processing') aria-current="page" @endif>Preparing</a>
                    </nav>
            @php($confirmableIds = $orders->filter(fn ($sellerOrder) => $sellerOrder->status === 'pending' && ! $sellerOrder->has_open_cancellation && ! $sellerOrder->needs_prescription_review)->pluck('id')->take(20))
            @if ($canManageOrders && $activeStatus === 'pending' && $confirmableIds->isNotEmpty())
                <form method="POST" action="{{ route('seller.orders.bulk-confirm') }}" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b border-[#eef1f5] bg-[#f8fafc] px-5 py-2.5 max-sm:px-4" data-confirm-title="Confirm {{ $confirmableIds->count() }} pending {{ Str::plural('order', $confirmableIds->count()) }}?" data-confirm="They move to confirmed and the buyers are notified." data-confirm-reference="{{ $orders->whereIn('id', $confirmableIds)->take(3)->map(fn ($sellerOrder) => \App\Models\Order::shortNumber($sellerOrder->order->number))->join(', ') }}{{ $confirmableIds->count() > 3 ? ' and '.($confirmableIds->count() - 3).' more' : '' }}" data-confirm-from="Pending" data-confirm-to="Confirmed" data-confirm-action="Confirm {{ Str::plural('order', $confirmableIds->count()) }}">
                    @csrf
                    @foreach ($confirmableIds as $confirmableId)<input type="hidden" name="seller_order_ids[]" value="{{ $confirmableId }}">@endforeach
                    <p class="text-sm text-muted">{{ $confirmableIds->count() }} {{ Str::plural('order', $confirmableIds->count()) }} waiting for you to confirm.</p>
                    <button type="submit" class="seller-primary-button">Confirm all pending ({{ $confirmableIds->count() }})</button>
                </form>
            @endif
            <x-input-error :messages="$errors->get('seller_order_ids')" class="mx-5 my-3" />


                    <div class="sc-queue-list">
                    @forelse ($orders as $sellerOrder)
                        <a href="{{ route('seller.orders.show', $sellerOrder) }}" class="sc-queue-order" :aria-current="current === @js($sellerOrder->id) ? 'true' : null" @click="if (! ($event.metaKey || $event.ctrlKey || $event.shiftKey)) { $event.preventDefault(); open(@js($sellerOrder->id)) }" @pointerenter="warm(@js($sellerOrder->id))" @pointerleave="clearTimeout(warmTimer)" @focus="preload(@js($sellerOrder->id))">
                            <div class="sc-queue-row"><strong class="sc-queue-number" title="{{ $sellerOrder->order->number }}">{{ $sellerOrder->order->number }}</strong><time datetime="{{ $sellerOrder->created_at->toIso8601String() }}" title="{{ $sellerOrder->created_at->format('M j, Y · g:i A') }}">{{ $sellerOrder->created_at->gt(now()->subWeek()) ? $sellerOrder->created_at->diffForHumans(short: true) : $sellerOrder->created_at->format('M j') }}</time></div>
                            <p class="sc-queue-customer">{{ $sellerOrder->order->recipient_name }}</p>
                            <p class="sr-only">{{ $sellerOrder->items->first()?->product_name }}@if ($sellerOrder->items->count() > 1) <span>+{{ $sellerOrder->items->count() - 1 }}</span>@endif</p>
                            <div class="sc-queue-row"><span class="sc-queue-state" data-tone="{{ $sellerOrder->needs_prescription_review ? 'warning' : ($sellerOrder->status === 'delivered' ? 'success' : 'info') }}">{{ $sellerOrder->needs_prescription_review ? ($sellerOrder->awaiting_prescription_photo ? 'Clearer photo requested' : 'Prescription review') : \App\Models\SellerOrder::statusLabel($sellerOrder->status, $sellerOrder->order->fulfillment_method) }}</span><span class="sr-only">{{ $nextStep($sellerOrder) }}</span><strong class="sc-num">₱{{ number_format($sellerOrder->total, 2) }}</strong></div>

                            @if ($sellerOrder->order->discount_document_path)<span class="sc-queue-document">SC/PWD ID</span>@endif
                            <span class="sr-only">View order</span>
                        </a>
                    @empty
                        <div class="sc-empty"><h3>{{ $hasFilters ? 'No orders match these filters' : 'No orders yet' }}</h3><p>{{ $hasFilters ? 'Adjust your search or clear filters to try again.' : 'Orders from your store will appear here after checkout.' }}</p>@if ($hasFilters)<a href="{{ route('seller.orders.index') }}" class="seller-outline-button">Clear filters</a>@endif</div>
                    @endforelse
                    </div>
                    @if ($orders->total() > 0)
                        <div class="sc-queue-footer">
                            <span>{{ $orders->firstItem() }}–{{ $orders->lastItem() }} of {{ number_format($orders->total()) }}</span>
                            <span class="sr-only">Showing {{ $orders->firstItem() }}–{{ $orders->lastItem() }} of {{ number_format($orders->total()) }} {{ Str::plural('order', $orders->total()) }}</span>
                            <form method="GET" action="{{ route('seller.orders.index') }}">
                                @foreach (request()->except('per_page', 'page', 'order') as $field => $value)@if (is_string($value))<input type="hidden" name="{{ $field }}" value="{{ $value }}">@endif @endforeach
                                <label for="per-page" class="sr-only">Rows per page</label><select id="per-page" name="per_page" data-submit-on-change>@foreach (\App\Http\Controllers\Seller\SellerOrderController::PER_PAGE_OPTIONS as $option)<option value="{{ $option }}" @selected($orders->perPage() === $option)>{{ $option }}</option>@endforeach</select>
                                <noscript><button type="submit">Show</button></noscript>
                            </form>
                            @if ($orders->previousPageUrl())<a href="{{ $orders->previousPageUrl() }}" aria-label="Previous page"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="m15 18-6-6 6-6"/></svg></a>@endif
                            @if ($orders->nextPageUrl())<a href="{{ $orders->nextPageUrl() }}" aria-label="Next page"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="m9 6 6 6-6 6"/></svg></a>@endif
                        </div>
                    @endif
                </aside>
                <section class="sc-orders-detail" x-ref="modal" role="region" aria-label="Selected order details" :aria-busy="loading">
                    <x-input-error :messages="$errors->get('discount')" class="mx-6 mt-6" />
                    <x-input-error :messages="$errors->get('prescription')" class="mx-6 mt-6" />
                    <x-input-error :messages="$errors->get('reason')" class="mx-6 mt-6" />
                    <div x-show="loading && ! html" class="sc-modal-body" role="status"><span class="sr-only">Loading order</span><div class="animate-pulse motion-reduce:animate-none space-y-4"><div class="h-6 w-48 rounded bg-paper"></div><div class="h-44 rounded bg-paper"></div></div></div>
                    <div x-show="failed" x-cloak class="sc-empty" role="alert"><h3>We couldn’t load this order.</h3><p>Check your connection and try again, or open the full order page.</p><div class="mt-4 flex justify-center gap-2"><button type="button" class="seller-outline-button" @click="open(current, 'none')">Try again</button><a :href="showUrl.replace('__ORDER__', current)" class="seller-primary-button">Open full page</a></div></div>
                    <div x-show="html" x-html="html" :inert="shown !== current" :data-stale="String(shown !== current)"></div>
                    <div x-show="! current && ! loading" class="sc-empty"><h2>Select an order</h2><p>Choose an order from the queue to review documents and manage its next step.</p></div>
                </section>
            </div>
        </section>
    </div></div>
</x-app-layout>
