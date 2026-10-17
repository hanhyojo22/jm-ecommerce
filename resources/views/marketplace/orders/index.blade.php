<x-app-layout>
    @php
        $statusStyles = [
            'pending' => 'bg-amber-50 text-warning-text ring-amber-200',
            'confirmed' => 'bg-blue-50 text-brand-hover ring-blue-200',
            'processing' => 'bg-blue-50 text-brand-hover ring-blue-200',
            'shipped' => 'bg-blue-50 text-brand-hover ring-blue-200',
            'delivered' => 'bg-green-50 text-success ring-green-200',
            'cancelled' => 'bg-red-50 text-danger ring-red-200',
        ];
        $statusLabels = ['' => 'All', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
        $hasFilters = filled($filters['search'] ?? null) || filled($filters['status'] ?? null);
    @endphp

    <x-marketplace.account-shell active="orders" title="My purchases">
        <div data-purchases class="flex flex-col gap-6 sm:gap-7">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-ink">My purchases</h1>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-muted">Your orders, from checkout to your doorstep.</p>
                </div>
                <a href="{{ route('catalog.index') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-line bg-surface px-4 text-sm font-semibold text-ink transition hover:border-brand hover:text-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                    Continue shopping
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-5-5 5 5-5 5" /></svg>
                </a>
            </header>

            <section class="min-w-0 rounded-2xl border border-line bg-surface" aria-label="Find a purchase">
                <form method="GET" action="{{ route('orders.index') }}" x-data="{ loading: false }" @submit="loading = true" class="flex flex-col gap-3 p-4 sm:flex-row sm:items-end sm:p-5">
                    <div class="min-w-0 flex-1">
                        <label for="purchase-search" class="mb-2 block text-sm font-semibold text-ink">Search purchases</label>
                        <div class="relative">
                            <svg viewBox="0 0 24 24" class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-muted" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path stroke-linecap="round" d="m20 20-4-4" /></svg>
                            <input id="purchase-search" name="search" value="{{ $filters['search'] ?? '' }}" type="search" maxlength="100" class="field-control bg-canvas pl-10" placeholder="Product, store, or order number" @error('search') aria-invalid="true" aria-describedby="purchase-search-error" @enderror>
                        </div>
                        <x-input-error id="purchase-search-error" :messages="$errors->get('search')" class="mt-2" />
                    </div>
                    <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
                    <button type="submit" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-brand px-6 text-sm font-semibold text-white transition hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-wait disabled:opacity-60" :disabled="loading">
                        <span x-text="loading ? 'Searching…' : 'Search'">Search</span>
                    </button>
                </form>
                <x-input-error :messages="$errors->get('status')" class="px-5 pb-3" />
                <nav class="flex gap-1 overflow-x-auto overscroll-x-contain border-t border-line px-3 py-2 sm:px-4" aria-label="Purchase status filters" tabindex="0">
                    @foreach ($statusLabels as $value => $label)
                        @php($active = ($filters['status'] ?? '') === $value)
                        @php($count = $value ? ($statusCounts[$value] ?? 0) : $statusCounts->sum())
                        <a href="{{ route('orders.index', [...request()->except('status', 'page'), ...($value ? ['status' => $value] : [])]) }}" @class([
                            'inline-flex min-h-11 shrink-0 items-center gap-2 rounded-lg px-3 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-brand',
                            'bg-blue-50 text-brand-hover' => $active,
                            'text-muted hover:bg-canvas hover:text-ink' => ! $active,
                        ]) @if ($active) aria-current="page" @endif>
                            {{ $label }}
                            <span @class(['min-w-5 rounded-md px-1.5 py-0.5 text-center text-xs tabular-nums', 'bg-white text-brand-hover' => $active, 'bg-canvas text-muted' => ! $active])>{{ $count }}</span>
                        </a>
                    @endforeach
                </nav>
            </section>

            <section class="flex min-w-0 flex-col gap-4" aria-labelledby="purchase-results-heading">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="purchase-results-heading" class="text-base font-semibold text-ink">{{ $hasFilters ? 'Matching purchases' : 'Order history' }} <span class="ml-1 text-sm font-normal tabular-nums text-muted">({{ $orders->total() }})</span></h2>
                    @if ($hasFilters)
                        <a href="{{ route('orders.index') }}" class="inline-flex min-h-11 items-center gap-1.5 rounded-lg px-2 text-sm font-semibold text-brand-hover transition hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                            Clear filters
                        </a>
                    @else
                        <p class="text-xs text-muted">Most recent first</p>
                    @endif
                </div>

                @forelse ($orders as $order)
                    <article class="min-w-0 overflow-hidden rounded-xl border border-line bg-surface" aria-labelledby="purchase-{{ $order->id }}">
                        <header class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b border-line px-4 py-2.5 sm:px-5">
                            <div class="flex min-w-0 flex-wrap items-center gap-x-1.5 gap-y-0.5 text-[13px]" x-data="{ copied: false, copy() { navigator.clipboard?.writeText(@js($order->number)); this.copied = true; setTimeout(() => this.copied = false, 1800); } }">
                                <h3 id="purchase-{{ $order->id }}" class="min-w-0 break-all font-semibold text-ink">Order #{{ $order->number }}</h3>
                                <p class="text-muted"><span aria-hidden="true">·</span> <span class="sr-only sm:not-sr-only">Placed </span><time datetime="{{ $order->created_at->toIso8601String() }}">{{ $order->created_at->format('M j, Y') }}</time></p>
                                <button type="button" @click="copy" class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg text-muted transition hover:bg-canvas hover:text-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand" :aria-label="copied ? 'Order number copied' : 'Copy order number'" title="Copy order number">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2" /><path stroke-linecap="round" d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" /></svg>
                                </button>
                                <span x-cloak x-show="copied" class="text-xs font-medium text-brand-hover" role="status">Copied</span>
                            </div>
                            <span class="status-badge gap-1.5 {{ $statusStyles[$order->status] }}">
                                <svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    @if ($order->status === 'delivered')
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" />
                                    @elseif ($order->status === 'cancelled')
                                        <path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" />
                                    @else
                                        <circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M12 7v5l3 2" />
                                    @endif
                                </svg>
                                {{ $order->status === 'pending' ? 'Awaiting confirmation' : ucfirst($order->status) }}
                            </span>
                        </header>

                        <div class="divide-y divide-line px-4 sm:px-5">
                            @foreach ($order->sellerOrders as $sellerOrder)
                                <section x-data="{ expanded: false }" class="flex flex-col gap-3.5 py-5" aria-label="Items from {{ $sellerOrder->store->name }} in order {{ $order->number }}">
                                    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
                                        <div class="flex min-w-0 flex-1 items-center gap-2">
                                            <span class="grid size-5 shrink-0 place-items-center overflow-hidden rounded text-brand" aria-hidden="true">
                                                @if ($sellerOrder->store->logo_path)
                                                    <img src="{{ Storage::disk('public')->url($sellerOrder->store->logo_path) }}" alt="" width="20" height="20" loading="lazy" class="size-full rounded object-cover">
                                                @else
                                                    <svg viewBox="0 0 24 24" class="size-[18px]" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.25h16.5l-1.5 10h-13.5l-1.5-10ZM5 9.25l2-5h10l2 5" /></svg>
                                                @endif
                                            </span>
                                            <a href="{{ route('catalog.store', $sellerOrder->store->slug) }}" class="min-w-0 truncate text-[13px] font-semibold text-muted transition hover:text-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">{{ $sellerOrder->store->name }}</a>
                                        </div>
                                        <form method="POST" action="{{ route('messages.seller-orders.start', $sellerOrder) }}" class="shrink-0">
                                            @csrf
                                            <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-2 text-xs font-semibold text-brand-hover transition hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand sm:text-sm">
                                                <svg class="hidden size-4 sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H5l-2 2v-9.5A7.5 7.5 0 0 1 10.5 4h2A7.5 7.5 0 0 1 20 11.5Z" /><path stroke-linecap="round" d="M8 10h8M8 14h5" /></svg>
                                                Message seller
                                            </button>
                                        </form>
                                    </div>

                                    <div id="purchase-items-{{ $sellerOrder->id }}" class="flex flex-col gap-4">
                                        @foreach ($sellerOrder->items as $item)
                                            <div @if ($loop->index >= 3) x-cloak x-show="expanded" @endif class="grid min-w-0 grid-cols-[4rem_minmax(0,1fr)] gap-x-3 gap-y-1 sm:grid-cols-[5rem_minmax(0,1fr)_auto] sm:gap-x-4">
                                                <div class="row-span-2 grid size-16 shrink-0 place-items-center overflow-hidden rounded-xl border border-line bg-canvas sm:size-20">
                                                    @if (($item->image_path || $item->product?->thumbnail))
                                                        <img src="{{ Storage::disk('product_images')->url(($item->image_path ?? $item->product->thumbnail->path)) }}" alt="{{ $item->product_name }}" width="80" height="80" loading="lazy" class="size-full object-contain">
                                                    @else
                                                        <svg viewBox="0 0 24 24" class="size-6 text-muted" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2" /><circle cx="8" cy="9" r="1.5" /><path d="m4 18 5-5 3 3 3-3 5 5" /></svg>
                                                        <span class="sr-only">Product image unavailable</span>
                                                    @endif
                                                </div>
                                                <div class="min-w-0 sm:row-span-2 sm:self-center">
                                                    @if ($item->product)
                                                        <a href="{{ route('catalog.show', [$item->product->store->slug, $item->product->slug]) }}" class="line-clamp-2 break-words text-sm font-semibold leading-6 text-ink hover:text-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">{{ $item->product_name }}</a>
                                                    @else
                                                        <p class="line-clamp-2 break-words text-sm font-semibold leading-6 text-ink">{{ $item->product_name }}</p>
                                                    @endif
                                                    <p class="mt-1 text-xs leading-5 text-muted">@if ($item->variant_label){{ $item->variant_label }} <span aria-hidden="true">·</span> @endif Qty {{ $item->quantity }}</p>
                                                </div>
                                                <p class="col-start-2 text-sm font-semibold tabular-nums text-ink sm:col-start-3 sm:row-span-2 sm:self-center sm:text-right">₱{{ number_format($item->line_total, 2) }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                    @if ($sellerOrder->items->count() > 3)
                                        <button type="button" @click="expanded = !expanded" :aria-expanded="expanded" aria-controls="purchase-items-{{ $sellerOrder->id }}" class="inline-flex min-h-11 items-center justify-center gap-2 self-start rounded-lg border border-line px-3 text-sm font-semibold text-brand-hover transition hover:bg-canvas focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
                                            <span x-text="expanded ? 'Show fewer products' : 'Show all {{ $sellerOrder->items->count() }} products'">Show all {{ $sellerOrder->items->count() }} products</span>
                                            <svg class="size-4" :class="expanded && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                                        </button>
                                    @endif

                                    <x-marketplace.order-timeline :status="$sellerOrder->status" :label="$sellerOrder->store->name.' progress'" class="mb-0.5 mt-2.5" />
                                </section>
                            @endforeach
                        </div>

                        <footer class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 border-t border-line bg-canvas px-4 py-3 sm:px-5">
                            <p class="flex min-w-0 flex-wrap items-baseline gap-x-1.5 text-[13px] text-muted">
                                Order total <span class="text-base font-bold tracking-tight tabular-nums text-ink">₱{{ number_format($order->total, 2) }}</span>
                                @if ($order->payment)
                                    <span class="text-xs"><span aria-hidden="true">·</span> {{ $order->payment->method === 'cod' ? 'Cash on delivery' : Str::headline($order->payment->method) }} <span aria-hidden="true">·</span> {{ $order->payment->status === 'pending' ? 'Awaiting payment' : ucfirst($order->payment->status) }}</span>
                                @endif
                            </p>
                            <a href="{{ route('orders.show', $order) }}" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-lg border border-line bg-surface px-4 text-[13px] font-semibold text-ink transition hover:border-brand hover:text-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                                View order
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-5-5 5 5-5 5" /></svg>
                            </a>
                        </footer>
                    </article>
                @empty
                    <div class="flex flex-col items-center rounded-2xl border border-line bg-surface px-5 py-14 text-center sm:py-20">
                        <span class="grid size-16 place-items-center rounded-2xl border border-blue-100 bg-blue-50 text-brand" aria-hidden="true"><svg viewBox="0 0 24 24" class="size-8" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m12 3 9 5v8l-9 5-9-5V8l9-5Zm0 10v8M3 8l9 5 9-5M7.5 5.5l9 5" /></svg></span>
                        <h3 class="mt-5 text-lg font-semibold text-ink">{{ $hasFilters ? 'No purchases match those filters' : 'Your first purchase starts here' }}</h3>
                        <p class="mt-2 max-w-sm text-sm leading-6 text-muted">{{ $hasFilters ? 'Try another product, store, order number, or status to find your purchase.' : 'Find something you love. Your orders and delivery updates will appear here.' }}</p>
                        <a href="{{ $hasFilters ? route('orders.index') : route('catalog.index') }}" class="mt-6 inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">{{ $hasFilters ? 'Reset filters' : 'Browse products' }}</a>
                    </div>
                @endforelse
            </section>

            @if ($orders->hasPages())
                <div>{{ $orders->links() }}</div>
            @endif
        </div>
    </x-marketplace.account-shell>
</x-app-layout>
