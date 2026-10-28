<x-app-layout>
    @php
        $statusStyles = [
            'pending' => ['Awaiting seller confirmation', 'bg-warning', 'text-warning-text'],
            'confirmed' => ['Preparing your order', 'bg-brand', 'text-brand-hover'],
            'processing' => ['Preparing your order', 'bg-brand', 'text-brand-hover'],
            'shipped' => ['On the way', 'bg-brand', 'text-brand-hover'],
            'delivered' => ['Delivered', 'bg-success', 'text-success'],
            'cancelled' => ['Cancelled', 'bg-danger', 'text-danger'],
        ];
        $hasFilters = filled($filters['search'] ?? null) || filled($filters['status'] ?? null);
        $quietAction = 'inline-flex min-h-11 items-center rounded-lg px-3 text-[13px] font-semibold text-ink transition-colors hover:bg-slate-100 hover:text-brand-hover sm:min-h-9 sm:px-2.5';
        $outlineAction = 'inline-flex min-h-11 items-center rounded-lg border border-line bg-surface px-3.5 text-[13px] font-semibold text-ink transition-colors hover:border-brand hover:text-brand-hover sm:min-h-9';
        $primaryAction = 'inline-flex min-h-11 items-center rounded-lg bg-brand px-3.5 text-[13px] font-semibold text-white transition-colors hover:bg-brand-hover sm:min-h-9';
    @endphp

    <x-marketplace.account-shell active="orders">
        <div data-purchases class="min-w-0">
            <header class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <h1 class="text-2xl font-semibold tracking-[-0.025em] text-ink">My purchases</h1>
                <a href="{{ route('catalog.index') }}" class="inline-flex min-h-10 items-center gap-1 text-[13px] font-semibold text-brand-hover hover:underline hover:underline-offset-2">Continue shopping <span aria-hidden="true">→</span></a>
            </header>

            <nav class="marketplace-nav-scroll mt-4 flex min-w-0 gap-1 overflow-x-auto border-b border-line" aria-label="Purchase status filters" tabindex="0">
                @foreach ($tabs as $key => $label)
                    <a href="{{ route('orders.index', [...request()->except('tab', 'page'), 'tab' => $key]) }}" @class([
                        'relative inline-flex min-h-11 shrink-0 items-center gap-1.5 whitespace-nowrap px-3 text-[13px] font-semibold transition-colors focus-visible:outline-offset-[-2px]',
                        'text-ink after:absolute after:inset-x-3 after:bottom-0 after:h-0.5 after:rounded-full after:bg-brand' => $tab === $key,
                        'text-muted hover:text-ink' => $tab !== $key,
                    ]) @if ($tab === $key) aria-current="page" @endif>
                        {{ $label }} <span @class(['text-xs font-medium tabular-nums', 'text-brand-hover' => $tab === $key && $tabCounts[$key] > 0, 'text-muted' => $tab !== $key && $tabCounts[$key] > 0, 'text-slate-400' => $tabCounts[$key] === 0])>{{ $tabCounts[$key] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="mt-4 flex min-w-0 flex-wrap items-center justify-between gap-x-4 gap-y-2">
                <form method="GET" action="{{ route('orders.index') }}" class="flex min-w-0 w-full items-center gap-2 sm:w-auto sm:max-w-sm sm:flex-1">
                    @if ($tab !== 'all')<input type="hidden" name="tab" value="{{ $tab }}">@endif
                    @if (filled($filters['status'] ?? null))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
                    <label for="purchase-search" class="sr-only">Search purchases</label>
                    <div class="relative min-w-0 flex-1">
                        <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                        <input id="purchase-search" name="search" value="{{ $filters['search'] ?? '' }}" type="search" maxlength="100" class="field-control min-h-10 bg-surface pl-9 text-[13px]" placeholder="Search orders, stores, or products" @error('search') aria-invalid="true" aria-describedby="purchase-search-error" @enderror>
                    </div>
                    <button type="submit" class="{{ $outlineAction }} min-h-10 sm:min-h-10">Search</button>
                </form>
                <p class="flex items-center gap-3 text-xs text-muted">
                    @if ($hasFilters)
                        <a href="{{ route('orders.index') }}" class="inline-flex min-h-10 items-center font-semibold text-brand-hover hover:underline hover:underline-offset-2">Reset filters</a>
                    @endif
                    Newest first
                </p>
            </div>
            <x-input-error id="purchase-search-error" :messages="$errors->get('search')" />
            <x-input-error :messages="$errors->get('tab')" />
            <x-input-error :messages="$errors->get('status')" />

            <section class="mt-4 grid gap-3" aria-labelledby="purchase-results-heading">
                <h2 id="purchase-results-heading" class="sr-only">{{ $tab === 'all' ? 'All purchases' : $tabs[$tab] }} ({{ $sellerOrders->total() }})</h2>

                @forelse ($sellerOrders as $sellerOrder)
                    @php
                        $order = $sellerOrder->order;
                        $returnIssue = $sellerOrder->issues->firstWhere('type', 'return_refund');
                        $returnCases = $sellerOrder->returnCases;
                        $reviewableItemId = $reviewableItemIds[$sellerOrder->id] ?? null;
                        [$statusLabel, $statusDot, $statusText] = $statusStyles[$sellerOrder->status];
                        if ($tab === 'returns' && $returnCases->isNotEmpty()) {
                            [$statusLabel, $statusDot, $statusText] = ['Return case '.str($returnCases->first()->status)->replace('_', ' '), 'bg-purple-600', 'text-purple-700'];
                        } elseif ($tab === 'returns' && $returnIssue) {
                            [$statusLabel, $statusDot, $statusText] = ['Return request '.str($returnIssue->status)->replace('_', ' '), 'bg-purple-600', 'text-purple-700'];
                        }
                        $hasPrimary = $reviewableItemId || $returnCases->isNotEmpty() || ($returnIssue && ($tab === 'returns' || $returnIssue->status === 'open'));
                    @endphp
                    <article class="min-w-0 rounded-[14px] border border-line bg-surface" aria-labelledby="purchase-{{ $sellerOrder->id }}">
                        <header class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 border-b border-[#EEF1F5] px-4 py-3 sm:px-5">
                            <div class="flex min-w-0 items-center gap-2.5">
                                @if ($sellerOrder->store->logo_path)
                                    <img src="{{ Storage::disk('public')->url($sellerOrder->store->logo_path) }}" alt="" width="20" height="20" class="size-5 shrink-0 rounded object-cover">
                                @else
                                    <svg class="size-4 shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 9h16v11H4zM3 9l1.5-5h15L21 9M9 20v-6h6v6"/></svg>
                                @endif
                                <h3 id="purchase-{{ $sellerOrder->id }}" class="min-w-0 truncate text-sm font-semibold text-ink"><a href="{{ route('catalog.store', $sellerOrder->store->slug) }}" class="hover:text-brand-hover">{{ $sellerOrder->store->name }}</a></h3>
                                <span class="inline-flex shrink-0 items-center gap-1.5 text-xs font-semibold {{ $statusText }}"><span class="size-1.5 rounded-full {{ $statusDot }}" aria-hidden="true"></span>{{ $statusLabel }}</span>
                            </div>
                            <p class="min-w-0 text-xs text-muted [overflow-wrap:anywhere]"><span class="tabular-nums">#{{ $order->number }}</span> <span aria-hidden="true">·</span> <time datetime="{{ $order->created_at->toIso8601String() }}">{{ $order->created_at->format('M j, Y') }}</time></p>
                        </header>

                        <div id="purchase-items-{{ $sellerOrder->id }}" class="divide-y divide-[#EEF1F5] px-4 sm:px-5">
                            @foreach ($sellerOrder->items as $item)
                                <div class="grid min-w-0 grid-cols-[48px_minmax(0,1fr)_auto] items-center gap-3 py-3 sm:grid-cols-[56px_minmax(0,1fr)_4.5rem_6rem] sm:gap-4">
                                    <div class="grid size-12 place-items-center overflow-hidden rounded-[10px] border border-line bg-canvas sm:size-14">
                                        @if ($item->image_path || $item->product?->thumbnail)
                                            <img src="{{ Storage::disk('product_images')->url($item->image_path ?? $item->product->thumbnail->path) }}" alt="{{ $item->product_name }}" width="56" height="56" loading="lazy" class="size-full object-cover">
                                        @else
                                            <svg viewBox="0 0 24 24" class="size-5 text-muted" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2" /><circle cx="8" cy="9" r="1.5" /><path d="m4 18 5-5 3 3 3-3 5 5" /></svg>
                                            <span class="sr-only">Product image unavailable</span>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        @if ($item->product && $item->product->store)
                                            <a href="{{ route('catalog.show', [$item->product->store->slug, $item->product->slug]) }}" class="block break-words text-[13.5px] font-medium leading-5 text-ink hover:text-brand-hover">{{ $item->product_name }}</a>
                                        @else
                                            <p class="break-words text-[13.5px] font-medium leading-5 text-ink">{{ $item->product_name }}</p>
                                        @endif
                                        <p class="mt-0.5 text-xs text-muted">{{ $item->variant_label }}<span class="sm:hidden">{{ $item->variant_label ? ' · ' : '' }}Qty {{ $item->quantity }}</span></p>
                                    </div>
                                    <p class="hidden text-right text-[13px] tabular-nums text-muted sm:block">Qty {{ $item->quantity }}</p>
                                    <p class="text-right text-[13.5px] font-semibold tabular-nums text-ink">₱{{ number_format($item->line_total, 2) }}</p>
                                </div>
                            @endforeach
                        </div>

                        <footer class="flex flex-col gap-2 border-t border-[#EEF1F5] px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5 text-xs text-muted sm:justify-start">
                                <p>{{ $order->payment?->method === 'cod' ? 'Cash on delivery' : Str::headline($order->payment?->method ?? 'Payment') }}@if ($order->seller_orders_count > 1) <span aria-hidden="true">·</span> Checkout total ₱{{ number_format($order->total, 2) }} across {{ $order->seller_orders_count }} stores @endif</p>
                                <p class="text-[13px]">Store total <strong class="ml-1 text-[15px] font-semibold tabular-nums text-ink">₱{{ number_format($sellerOrder->total, 2) }}</strong></p>
                            </div>
                            {{-- On a phone the quiet actions take one line and the buttons share the next, edge to edge. --}}
                            <div class="flex flex-wrap items-center gap-x-1.5 gap-y-1 sm:justify-end">
                                <div class="-ml-3 flex items-center sm:ml-0">
                                    <form method="POST" action="{{ route('messages.seller-orders.start', $sellerOrder) }}">@csrf<button type="submit" class="{{ $quietAction }}">Message seller</button></form>
                                    @if ($returnCases->isEmpty() && ! ($returnIssue && ($tab === 'returns' || $returnIssue->status === 'open')) && $sellerOrder->status === 'delivered')
                                        <a href="{{ route('orders.show', $order) }}#returns" class="{{ $quietAction }}">{{ $sellerOrder->delivered_at && now()->lessThanOrEqualTo($sellerOrder->delivered_at->copy()->addDays(7)) ? 'Request return' : 'Contact support' }}</a>
                                    @endif
                                </div>
                                <div class="grid grid-flow-col gap-1.5 max-sm:w-full max-sm:auto-cols-fr max-sm:[&>*]:justify-center">
                                <a href="{{ route('orders.show', $order) }}" class="{{ $hasPrimary ? $outlineAction : $primaryAction }}">View order</a>
                                @if ($reviewableItemId)
                                    <a href="{{ route('orders.show', $order) }}#review-item-{{ $reviewableItemId }}" class="{{ $primaryAction }}">Write review</a>
                                @endif
                                @if ($returnCases->isNotEmpty())
                                    @foreach ($returnCases as $returnCase)<a href="{{ route('returns.show', $returnCase) }}" class="{{ $reviewableItemId ? $outlineAction : $primaryAction }}">View return{{ $returnCases->count() > 1 ? ' · '.$loop->iteration : '' }}</a>@endforeach
                                @elseif ($returnIssue && ($tab === 'returns' || $returnIssue->status === 'open'))
                                    <a href="{{ route('orders.show', $order) }}#order-help" class="{{ $reviewableItemId ? $outlineAction : $primaryAction }}">View return</a>
                                @endif
                                </div>
                            </div>
                        </footer>
                    </article>
                @empty
                    <div class="rounded-[14px] border border-dashed border-line bg-surface px-5 py-10 text-center">
                        <h3 class="text-[15px] font-semibold text-ink">{{ $hasFilters ? 'No purchases match those filters' : ($tab === 'all' ? 'Your first purchase starts here' : 'Nothing here yet') }}</h3>
                        <p class="mx-auto mt-1.5 max-w-sm text-[13px] leading-5 text-muted">{{ $hasFilters ? 'Try another product, store, or order number.' : ($tab === 'all' ? 'Your orders and delivery updates will appear here.' : 'Orders at this stage will appear here when their status changes.') }}</p>
                        @if ($tab === 'all' && ! $hasFilters)
                            <a href="{{ route('catalog.index') }}" class="{{ $primaryAction }} mt-4">Browse products</a>
                        @endif
                    </div>
                @endforelse
            </section>

            @if ($sellerOrders->hasPages())<div class="mt-5">{{ $sellerOrders->links() }}</div>@endif
        </div>
    </x-marketplace.account-shell>
</x-app-layout>
