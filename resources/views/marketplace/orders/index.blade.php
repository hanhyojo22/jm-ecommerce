<x-app-layout>
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
        // The chat chip stays quiet beside the store name and only takes the brand colour under the pointer,
        // so the order's own state keeps the loudest mark on the row. On a phone it drops its label to an
        // icon so the store name, order number, and status still fit on one line.
        $chatAction = 'inline-flex min-h-11 w-11 shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-[var(--color-border-strong)] bg-surface px-0 text-meta font-semibold text-ink transition-colors duration-150 hover:border-brand hover:bg-brand hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand sm:w-auto sm:px-2.5 lg:min-h-10';
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

            {{-- Each order is its own card: a store bar and its item rows, where every product name keeps a
                 two-row area so long names wrap instead of stretching the row. --}}
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

                                <article class="overflow-hidden rounded-lg border border-line bg-surface shadow-[0_1px_2px_rgb(24_44_72/4%)]" aria-label="Order {{ $order->number }} from {{ $store }}">
                                    <header class="flex items-center justify-between gap-3 border-b border-line-soft px-4 py-3 lg:px-6">
                                        <div class="flex min-w-0 flex-wrap items-center gap-x-2.5 gap-y-0.5">
                                            <svg class="size-[18px] shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9.5 5.5 4h13L20 9.5"/><path d="M4 9.5a2.67 2.67 0 0 0 5.33 0 2.67 2.67 0 0 0 5.34 0 2.67 2.67 0 0 0 5.33 0"/><path d="M5.5 12.5V20h13v-7.5"/><path d="M10 20v-4h4v4"/></svg>
                                            <a href="{{ route('catalog.store', $sellerOrder->store->slug) }}" class="truncate text-sm font-semibold text-ink hover:text-brand-hover">{{ $store }}</a>
                                            <a id="purchase-number-{{ $sellerOrder->id }}" href="{{ route('orders.show', $order) }}" class="inline-flex min-h-6 items-center text-xs font-medium tabular-nums text-muted underline-offset-2 hover:text-ink hover:underline"><span aria-hidden="true">#…{{ $shortNumber }}</span><span class="sr-only">Order {{ $order->number }}</span></a>
                                            <form method="POST" action="{{ route('messages.seller-orders.start', $sellerOrder) }}">
                                                @csrf
                                                <button type="submit" class="{{ $chatAction }}">
                                                    <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.5 11.5a8.5 8.5 0 0 1-4.7 7.6 8.38 8.38 0 0 1-3.8.9 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z"/></svg>
                                                    <span class="sr-only sm:not-sr-only">Chat with seller</span>
                                                    <span class="sr-only"> about {{ $store }}, order {{ $order->number }}</span>
                                                </button>
                                            </form>
                                        </div>
                                        <span @class(['inline-flex shrink-0 items-center gap-1.5 rounded-full bg-[var(--color-sunken)] px-3 py-1 text-xs font-semibold', $overlayText])>
                                            <span class="size-[7px] rounded-full {{ $overlayDot }}" aria-hidden="true"></span>
                                            {{ $overlayLabel }}
                                        </span>
                                    </header>

                                    <ul class="divide-y divide-line-soft">
                                        @foreach ($items as $item)
                                            @php($itemThumb = $item->image_path ?? $item->product?->thumbnail?->path)
                                            <li>
                                                <a href="{{ route('orders.show', $order) }}" aria-label="View {{ $item->product_name }} in order {{ $order->number }} from {{ $store }}" class="grid grid-cols-[64px_minmax(0,1fr)] gap-x-4 px-4 py-4 transition-colors hover:bg-[var(--color-sunken)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-brand sm:grid-cols-[80px_minmax(0,1fr)_120px_80px] lg:px-6">
                                                    <div class="relative size-16 overflow-hidden rounded-md border border-line bg-paper sm:size-20">
                                                        @if ($itemThumb)
                                                            <img src="{{ Storage::disk('product_images')->url($itemThumb) }}" alt="" width="80" height="80" loading="lazy" class="size-full object-cover">
                                                        @else
                                                            <svg class="absolute inset-0 m-auto size-5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M4 8h16l-1 12H5L4 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
                                                        @endif
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="line-clamp-2 min-h-10 break-words text-sm leading-5 text-ink">{{ $item->product_name }}</p>
                                                        @if ($item->variant_label)
                                                            <p class="mt-1 text-xs text-muted">{{ $item->variant_label }}</p>
                                                        @endif
                                                    </div>
                                                    <div class="col-start-2 mt-1.5 flex gap-4 text-sm sm:contents">
                                                        <p class="tabular-nums text-ink sm:mt-0"><span class="sr-only">Price </span>₱{{ number_format($item->unit_price, 2) }}</p>
                                                        <p class="tabular-nums text-ink"><span class="text-muted">Qty:</span> {{ $item->quantity }}</p>
                                                    </div>
                                                </a>
                                            </li>
                                        @endforeach
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
        </div>
    </x-marketplace.account-shell>
</x-app-layout>
