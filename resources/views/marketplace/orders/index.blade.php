<x-app-layout>
    @php
        $statusStyles = [
            'pending' => ['Awaiting seller confirmation', 'bg-amber-50 text-warning-text'],
            'confirmed' => ['Preparing your order', 'bg-blue-50 text-brand-hover'],
            'processing' => ['Preparing your order', 'bg-blue-50 text-brand-hover'],
            'shipped' => ['On the way', 'bg-blue-50 text-brand-hover'],
            'delivered' => ['Delivered', 'bg-green-50 text-success'],
            'cancelled' => ['Cancelled', 'bg-red-50 text-danger'],
        ];
        $hasFilters = filled($filters['search'] ?? null) || filled($filters['status'] ?? null);
    @endphp

    <x-marketplace.account-shell active="orders" title="My purchases">
        <div data-purchases class="min-w-0 space-y-5 sm:space-y-6">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-ink">My purchases</h1>
                    <p class="mt-1.5 text-sm text-muted">Track orders and manage your purchases.</p>
                </div>
                <a href="{{ route('catalog.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand-hover hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Continue shopping <span aria-hidden="true" class="ml-1">→</span></a>
            </header>

            <nav class="flex min-w-0 gap-1 overflow-x-auto border-b border-line" aria-label="Purchase status filters" tabindex="0">
                @foreach ($tabs as $key => $label)
                    <a href="{{ route('orders.index', [...request()->except('tab', 'page'), 'tab' => $key]) }}" @class([
                        'relative inline-flex min-h-12 shrink-0 items-center gap-2 whitespace-nowrap px-3 pb-1 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-brand sm:px-4',
                        'text-brand-hover after:absolute after:inset-x-3 after:bottom-0 after:h-0.5 after:rounded-full after:bg-brand' => $tab === $key,
                        'text-muted hover:text-ink' => $tab !== $key,
                    ]) @if ($tab === $key) aria-current="page" @endif>
                        {{ $label }} <span @class(['rounded-full px-2 py-0.5 text-xs tabular-nums', 'bg-blue-50 text-brand-hover' => $tab === $key, 'bg-slate-100 text-muted' => $tab !== $key])>{{ $tabCounts[$key] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="flex min-w-0 flex-wrap items-center justify-between gap-3">
                <form method="GET" action="{{ route('orders.index') }}" class="flex min-w-0 w-full max-w-md items-center gap-2 sm:w-auto sm:flex-1">
                    @if ($tab !== 'all')<input type="hidden" name="tab" value="{{ $tab }}">@endif
                    @if (filled($filters['status'] ?? null))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
                    <label for="purchase-search" class="sr-only">Search purchases</label>
                    <input id="purchase-search" name="search" value="{{ $filters['search'] ?? '' }}" type="search" maxlength="100" class="field-control min-w-0 flex-1 bg-surface" placeholder="Search orders, stores, or products" @error('search') aria-invalid="true" aria-describedby="purchase-search-error" @enderror>
                    <button type="submit" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Search</button>
                </form>
                <p class="text-xs text-muted">Newest first</p>
            </div>
            <x-input-error id="purchase-search-error" :messages="$errors->get('search')" />
            <x-input-error :messages="$errors->get('tab')" />
            <x-input-error :messages="$errors->get('status')" />

            <section class="space-y-4" aria-labelledby="purchase-results-heading">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="purchase-results-heading" class="text-base font-semibold text-ink">{{ $tab === 'all' ? 'All purchases' : $tabs[$tab] }} <span class="ml-1 text-sm font-normal tabular-nums text-muted">({{ $sellerOrders->total() }})</span></h2>
                    @if ($tab !== 'all' || $hasFilters)
                        <a href="{{ route('orders.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand-hover hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">{{ $hasFilters ? 'Reset filters' : 'All purchases' }}</a>
                    @endif
                </div>

                @forelse ($sellerOrders as $sellerOrder)
                    @php
                        $order = $sellerOrder->order;
                        $returnIssue = $sellerOrder->issues->firstWhere('type', 'return_refund');
                        $reviewableItemId = $reviewableItemIds[$sellerOrder->id] ?? null;
                    @endphp
                    <article class="min-w-0 overflow-hidden rounded-2xl border border-line bg-surface shadow-sm" aria-labelledby="purchase-{{ $sellerOrder->id }}">
                        <header class="flex flex-wrap items-start justify-between gap-x-5 gap-y-2 border-b border-line px-4 py-3 sm:items-center sm:px-5">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="grid size-9 shrink-0 place-items-center overflow-hidden rounded-lg bg-blue-50 font-bold text-brand-hover" aria-hidden="true">{{ Str::upper(Str::substr($sellerOrder->store->name, 0, 1)) }}</span>
                                <div class="min-w-0">
                                    <h3 id="purchase-{{ $sellerOrder->id }}" class="truncate text-sm font-bold text-ink"><a href="{{ route('catalog.store', $sellerOrder->store->slug) }}" class="hover:text-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">{{ $sellerOrder->store->name }}</a></h3>
                                    <p class="text-xs text-muted">{{ $sellerOrder->items->count() }} {{ Str::plural('item', $sellerOrder->items->count()) }}</p>
                                </div>
                            </div>
                            <p class="min-w-0 break-all text-xs text-muted sm:text-right">Order #{{ $order->number }} <span aria-hidden="true">·</span> <time datetime="{{ $order->created_at->toIso8601String() }}">{{ $order->created_at->format('M j, Y') }}</time></p>
                        </header>

                        <div class="px-4 sm:px-5">
                            <div class="flex justify-end pt-3">
                                @if ($returnIssue && $tab === 'returns')
                                    <span class="rounded-lg bg-purple-50 px-3 py-1.5 text-xs font-bold text-purple-700">Return request {{ str($returnIssue->status)->replace('_', ' ') }}</span>
                                @else
                                    <span class="rounded-lg px-3 py-1.5 text-xs font-bold {{ $statusStyles[$sellerOrder->status][1] }}">{{ $statusStyles[$sellerOrder->status][0] }}</span>
                                @endif
                            </div>
                            <div id="purchase-items-{{ $sellerOrder->id }}" class="divide-y divide-line">
                                @foreach ($sellerOrder->items as $item)
                                    <div class="flex min-w-0 gap-3 py-4 sm:gap-4">
                                        <div class="grid size-20 shrink-0 place-items-center overflow-hidden rounded-xl bg-canvas sm:size-24">
                                            @if ($item->image_path || $item->product?->thumbnail)
                                                <img src="{{ Storage::disk('product_images')->url($item->image_path ?? $item->product->thumbnail->path) }}" alt="{{ $item->product_name }}" width="96" height="96" loading="lazy" class="size-full object-contain">
                                            @else
                                                <svg viewBox="0 0 24 24" class="size-7 text-muted" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2" /><circle cx="8" cy="9" r="1.5" /><path d="m4 18 5-5 3 3 3-3 5 5" /></svg>
                                                <span class="sr-only">Product image unavailable</span>
                                            @endif
                                        </div>
                                        <div class="flex min-w-0 flex-1 flex-col justify-center">
                                            @if ($item->product && $item->product->store)
                                                <a href="{{ route('catalog.show', [$item->product->store->slug, $item->product->slug]) }}" class="break-words text-sm font-bold leading-5 text-ink hover:text-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand sm:text-base">{{ $item->product_name }}</a>
                                            @else
                                                <p class="break-words text-sm font-bold leading-5 text-ink sm:text-base">{{ $item->product_name }}</p>
                                            @endif
                                            @if ($item->variant_label)<p class="mt-1 text-xs text-muted">{{ $item->variant_label }}</p>@endif
                                            <p class="mt-2 text-xs text-muted sm:hidden">Qty <span class="font-semibold text-ink">{{ $item->quantity }}</span></p>
                                            <p class="mt-1 text-xs font-semibold tabular-nums text-ink sm:hidden">Amount ₱{{ number_format($item->line_total, 2) }}</p>
                                        </div>
                                        <p class="hidden w-16 shrink-0 self-center text-center text-sm text-muted sm:block">Qty <span class="font-semibold tabular-nums text-ink">{{ $item->quantity }}</span></p>
                                        <p class="hidden shrink-0 self-center text-sm font-semibold tabular-nums text-ink sm:block">₱{{ number_format($item->line_total, 2) }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <footer class="flex flex-col gap-3 border-t border-line bg-slate-50/60 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                            <div class="text-xs text-muted">
                                <p>{{ $order->payment?->method === 'cod' ? 'Cash on delivery' : Str::headline($order->payment?->method ?? 'Payment') }} <span aria-hidden="true">·</span> Placed {{ $order->created_at->format('M j, Y') }}</p>
                                @if ($order->seller_orders_count > 1)<p class="mt-1">Checkout total ₱{{ number_format($order->total, 2) }} across {{ $order->seller_orders_count }} stores</p>@endif
                            </div>
                            <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                <p class="mr-1 w-full text-sm text-muted sm:w-auto">Store total <strong class="ml-1 text-lg tabular-nums text-ink">₱{{ number_format($sellerOrder->total, 2) }}</strong></p>
                                <form method="POST" action="{{ route('messages.seller-orders.start', $sellerOrder) }}">@csrf<button type="submit" class="inline-flex min-h-10 items-center rounded-lg border border-line bg-surface px-3 text-xs font-semibold text-ink hover:border-brand hover:text-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Message seller</button></form>
                                <a href="{{ route('orders.show', $order) }}" class="inline-flex min-h-10 items-center rounded-lg border border-line bg-surface px-3 text-xs font-semibold text-ink hover:border-brand hover:text-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">View order</a>
                                @if ($tab === 'returns' && $returnIssue)
                                    <a href="{{ route('orders.show', $order) }}#order-help" class="inline-flex min-h-10 items-center rounded-lg bg-brand px-3 text-xs font-semibold text-white hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">View return</a>
                                @elseif ($reviewableItemId)
                                    <a href="{{ route('orders.show', $order) }}#review-item-{{ $reviewableItemId }}" class="inline-flex min-h-10 items-center rounded-lg bg-brand px-3 text-xs font-semibold text-white hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Write review</a>
                                @elseif ($sellerOrder->status === 'delivered' && ! $sellerOrder->issues->contains('status', 'open'))
                                    <a href="{{ route('orders.show', ['order' => $order, 'help' => 'return_refund', 'seller_order_id' => $sellerOrder->id]) }}#order-help" class="inline-flex min-h-10 items-center rounded-lg bg-brand px-3 text-xs font-semibold text-white hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Request return</a>
                                @endif
                            </div>
                        </footer>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-line bg-surface px-5 py-14 text-center">
                        <div class="mx-auto grid size-14 place-items-center rounded-xl bg-blue-50 text-brand" aria-hidden="true"><svg viewBox="0 0 24 24" class="size-7" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m12 3 9 5v8l-9 5-9-5V8l9-5Zm0 10v8M3 8l9 5 9-5" /></svg></div>
                        <h3 class="mt-4 text-lg font-semibold text-ink">{{ $hasFilters ? 'No purchases match those filters' : ($tab === 'all' ? 'Your first purchase starts here' : 'Nothing here yet') }}</h3>
                        <p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-muted">{{ $hasFilters ? 'Try another product, store, or order number.' : ($tab === 'all' ? 'Your orders and delivery updates will appear here.' : 'Orders at this stage will appear here when their status changes.') }}</p>
                        <a href="{{ $tab === 'all' && ! $hasFilters ? route('catalog.index') : route('orders.index') }}" class="mt-5 inline-flex min-h-11 items-center rounded-lg bg-brand px-5 text-sm font-semibold text-white hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">{{ $tab === 'all' && ! $hasFilters ? 'Browse products' : 'All purchases' }}</a>
                    </div>
                @endforelse
            </section>

            @if ($sellerOrders->hasPages())<div>{{ $sellerOrders->links() }}</div>@endif
        </div>
    </x-marketplace.account-shell>
</x-app-layout>
