<x-app-layout :error-summary="false">
    <x-slot name="header">
        <div>
            <h1 class="mt-1 marketplace-page-title">Marketplace orders</h1>
        </div>
    </x-slot>

    @php
        $statusStyles = ['pending' => 'bg-warning/10 text-warning-text ring-warning/20', 'confirmed' => 'bg-brand/10 text-brand-hover ring-brand/20', 'processing' => 'bg-brand/10 text-brand-hover ring-brand/20', 'shipped' => 'bg-brand/10 text-brand-hover ring-brand/20', 'delivered' => 'bg-success/10 text-success ring-success/20', 'cancelled' => 'bg-danger/10 text-danger-text ring-danger/20', 'paid' => 'bg-success/10 text-success ring-success/20', 'failed' => 'bg-danger/10 text-danger-text ring-danger/20', 'refunded' => 'bg-canvas text-muted ring-line'];
        $hasFilters = filled($filters['search'] ?? null) || filled($filters['status'] ?? null) || filled($filters['payment_status'] ?? null) || filled($filters['store_id'] ?? null) || filled($filters['from'] ?? null) || filled($filters['to'] ?? null);
        $summaryCards = [
            ['label' => 'Total orders', 'count' => $totalOrders, 'status' => null, 'valueClass' => 'text-ink'],
            ['label' => 'Awaiting action', 'count' => $statusCounts['pending'] ?? 0, 'status' => 'pending', 'valueClass' => 'text-warning-text'],
            ['label' => 'Processing', 'count' => $statusCounts['processing'] ?? 0, 'status' => 'processing', 'valueClass' => 'text-brand'],
            ['label' => 'Completed', 'count' => $statusCounts['delivered'] ?? 0, 'status' => 'delivered', 'valueClass' => 'text-success'],
            ['label' => 'Cancelled', 'count' => $statusCounts['cancelled'] ?? 0, 'status' => 'cancelled', 'valueClass' => 'text-danger'],
        ];
    @endphp

    <div class="bg-canvas py-8 sm:py-10">
        <div class="mx-auto max-w-[1320px] px-[18px] sm:px-8">
            @if (session('status'))
                <div class="mb-5 rounded-lg border border-success/20 bg-success/10 px-4 py-3 text-sm font-medium text-success" role="status">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert"><span>{{ $errors->first() }}</span><a href="{{ route('admin.orders.index', request()->query()) }}" class="font-semibold underline underline-offset-4">Retry</a></div>
            @endif

            <section class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5" aria-label="Marketplace order summaries">
                @foreach ($summaryCards as $summary)
                    @php($selected = ($filters['status'] ?? null) === $summary['status'])
                    <a href="{{ route('admin.orders.index', [...request()->except('status', 'page'), ...($summary['status'] ? ['status' => $summary['status']] : [])]) }}" aria-current="{{ $selected ? 'page' : 'false' }}" class="rounded-lg border bg-surface px-5 py-4 transition hover:border-brand/30 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2 {{ $selected ? 'border-brand ring-1 ring-brand' : 'border-line' }}">
                        <p class="text-2xl font-semibold tabular-nums tracking-tight {{ $summary['valueClass'] }}">{{ number_format($summary['count']) }}</p>
                        <p class="mt-1 text-sm font-medium text-muted">{{ $summary['label'] }}</p>
                    </a>
                @endforeach
            </section>

            <section class="mt-8" aria-labelledby="orders-heading">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 id="orders-heading" class="text-lg font-semibold tracking-tight text-ink">All marketplace orders</h2>
                        <p class="mt-1 text-sm text-muted">Review fulfilment across every seller store.</p>
                    </div>
                    @if ($hasFilters)<a href="{{ route('admin.orders.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg px-3 text-sm font-semibold text-muted transition hover:bg-surface hover:text-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Clear filters</a>@endif
                </div>

                <form method="GET" action="{{ route('admin.orders.index') }}" x-data="{ submitting: false }" @submit="submitting = true" class="mt-5 grid gap-3 rounded-lg border border-line bg-surface p-4 lg:grid-cols-12" role="search" aria-label="Filter marketplace orders">
                    <div class="lg:col-span-2">
                        <label for="search" class="sr-only">Search orders</label>
                        <input id="search" name="search" value="{{ $filters['search'] ?? '' }}" type="search" placeholder="Order, name, or store" class="block h-11 w-full rounded-lg border-line text-sm placeholder:text-muted focus:border-brand focus:ring-brand">
                    </div>
                    <div class="lg:col-span-2">
                        <label for="status" class="sr-only">Order status</label>
                        <select id="status" name="status" class="block h-11 w-full rounded-lg border-line text-sm focus:border-brand focus:ring-brand"><option value="">All order statuses</option>@foreach (['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
                    </div>
                    <div class="lg:col-span-2">
                        <label for="payment_status" class="sr-only">Payment status</label>
                        <select id="payment_status" name="payment_status" class="block h-11 w-full rounded-lg border-line text-sm focus:border-brand focus:ring-brand"><option value="">All payments</option>@foreach (['pending', 'paid', 'failed', 'refunded'] as $status)<option value="{{ $status }}" @selected(($filters['payment_status'] ?? null) === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
                    </div>
                    <div class="lg:col-span-2">
                        <label for="store_id" class="sr-only">Store</label>
                        <select id="store_id" name="store_id" class="block h-11 w-full rounded-lg border-line text-sm focus:border-brand focus:ring-brand"><option value="">All stores</option>@foreach ($stores as $store)<option value="{{ $store->id }}" @selected(($filters['store_id'] ?? null) === $store->id)>{{ $store->name }}</option>@endforeach</select>
                    </div>
                    <div class="lg:col-span-2">
                        <label for="from" class="sr-only">From date</label>
                        <input id="from" name="from" value="{{ $filters['from'] ?? '' }}" type="date" class="block h-11 w-full rounded-lg border-line text-sm focus:border-brand focus:ring-brand">
                    </div>
                    <div class="lg:col-span-2">
                        <label for="to" class="sr-only">To date</label>
                        <input id="to" name="to" value="{{ $filters['to'] ?? '' }}" type="date" class="block h-11 w-full rounded-lg border-line text-sm focus:border-brand focus:ring-brand">
                    </div>
                    <div class="lg:col-span-12 flex justify-end">
                        <button type="submit" :disabled="submitting" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-4 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2 disabled:cursor-wait disabled:opacity-70"><span x-show="!submitting">Apply filters</span><span x-cloak x-show="submitting">Loading orders...</span></button>
                    </div>
                </form>

                @if ($hasFilters)
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-muted"><span class="font-medium">Active filters:</span>@if (filled($filters['search'] ?? null))<span class="rounded-full bg-surface px-3 py-1 ring-1 ring-line">Search: {{ $filters['search'] }}</span>@endif@if (filled($filters['status'] ?? null))<span class="rounded-full bg-surface px-3 py-1 ring-1 ring-line">Status: {{ ucfirst($filters['status']) }}</span>@endif@if (filled($filters['payment_status'] ?? null))<span class="rounded-full bg-surface px-3 py-1 ring-1 ring-line">Payment: {{ ucfirst($filters['payment_status']) }}</span>@endif@if (filled($filters['from'] ?? null) || filled($filters['to'] ?? null))<span class="rounded-full bg-surface px-3 py-1 ring-1 ring-line">Date range selected</span>@endif</div>
                @endif

                <section class="mt-6 overflow-hidden rounded-lg border border-line bg-surface " aria-label="Marketplace orders">
                    <div class="table-scroll hidden 2xl:block">
                        <table class="w-full table-fixed divide-y divide-line">
                            <thead class="bg-canvas text-left text-xs font-semibold uppercase tracking-[0.1em] text-muted"><tr><th scope="col" class="w-[15%] px-2 py-3.5">Order</th><th scope="col" class="w-[12%] px-2 py-3.5">Customer</th><th scope="col" class="w-[13%] px-2 py-3.5">Store / seller</th><th scope="col" class="w-[11%] px-2 py-3.5">Placed</th><th scope="col" class="w-[5%] px-2 py-3.5 text-right">Items</th><th scope="col" class="w-[10%] px-2 py-3.5 text-right">Total</th><th scope="col" class="w-[11%] px-2 py-3.5">Payment</th><th scope="col" class="w-[12%] px-2 py-3.5">Fulfilment</th><th scope="col" class="w-[11%] px-2 py-3.5">Actions</th></tr></thead>
                            <tbody class="divide-y divide-line">@forelse ($orders as $order)@php($firstSellerOrder = $order->sellerOrders->first())@php($seller = $firstSellerOrder?->store?->tenant?->members->firstWhere('role', 'owner')?->user ?? $firstSellerOrder?->store?->tenant?->members->first()?->user)@php($storeNames = $order->sellerOrders->pluck('store.name')->filter()->implode(', '))@php($itemCount = $order->sellerOrders->sum(fn ($sellerOrder): int => (int) $sellerOrder->item_quantity))<tr class="transition hover:bg-canvas/80"><td class="px-2 py-4"><a href="{{ route('admin.orders.show', $order) }}" title="{{ $order->number }}" class="block truncate font-semibold text-ink hover:text-brand-hover focus:outline-none focus:ring-2 focus:ring-brand">{{ $order->number }}</a></td><td class="max-w-44 px-2 py-4"><p class="truncate text-sm font-medium text-ink">{{ $order->user->name }}</p><p class="mt-0.5 truncate text-xs text-muted">{{ $order->user->email }}</p></td><td class="max-w-52 px-2 py-4"><p title="{{ $storeNames }}" class="truncate text-sm font-medium text-ink">{{ $storeNames }}</p><p title="{{ $seller?->name ?? 'Seller unavailable' }}" class="mt-0.5 truncate text-xs text-muted">{{ $seller?->name ?? 'Seller unavailable' }}@if ($order->sellerOrders->count() > 1) &middot; {{ $order->sellerOrders->count() }} stores @endif</p></td><td class="whitespace-nowrap px-2 py-4 text-sm text-muted">{{ $order->created_at->format('M j, Y') }}<span class="block text-xs">{{ $order->created_at->format('g:i A') }}</span></td><td class="px-2 py-4 text-right text-sm font-semibold tabular-nums text-ink">{{ number_format($itemCount) }}</td><td class="whitespace-nowrap px-2 py-4 text-right text-xs font-semibold tabular-nums text-ink">&#8369;{{ number_format($order->total, 2) }}</td><td class="px-2 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusStyles[$order->payment->status] }}">{{ ucfirst($order->payment->status) }}</span></td><td class="px-2 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusStyles[$order->status] }}">{{ ucfirst($order->status) }}</span></td><td class="whitespace-nowrap px-2 py-4 text-right"><a href="{{ route('admin.orders.show', $order) }}" aria-label="View order {{ $order->number }}" class="inline-flex min-h-11 items-center rounded-lg bg-brand px-2 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">View</a></td></tr>@empty<tr><td colspan="9" class="px-6 py-16 text-center"><p class="font-semibold text-ink">{{ $hasFilters ? 'No orders match your filters' : 'No marketplace orders yet' }}</p><p class="mt-2 text-sm text-muted">{{ $hasFilters ? 'Try changing a filter or clearing the current search.' : 'Customer orders across seller stores will appear here.' }}</p>@if ($hasFilters)<a href="{{ route('admin.orders.index') }}" class="mt-5 inline-flex min-h-11 items-center rounded-lg bg-brand px-4 text-sm font-semibold text-white transition hover:bg-brand-hover">Clear filters</a>@endif</td></tr>@endforelse</tbody>
                        </table>
                    </div>

                    <div class="divide-y divide-line 2xl:hidden">@forelse ($orders as $order)@php($firstSellerOrder = $order->sellerOrders->first())@php($seller = $firstSellerOrder?->store?->tenant?->members->firstWhere('role', 'owner')?->user ?? $firstSellerOrder?->store?->tenant?->members->first()?->user)@php($itemCount = $order->sellerOrders->sum(fn ($sellerOrder): int => (int) $sellerOrder->item_quantity))<article class="p-5"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><a href="{{ route('admin.orders.show', $order) }}" class="block truncate font-semibold text-ink focus:outline-none focus:ring-2 focus:ring-brand">{{ $order->number }}</a><p class="mt-1 truncate text-sm text-muted">{{ $order->user->name }}</p></div><span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusStyles[$order->status] }}">{{ ucfirst($order->status) }}</span></div><div class="mt-4 grid grid-cols-2 gap-3 border-t border-line pt-4 text-sm"><div><p class="text-xs font-medium text-muted">Store</p><p class="mt-1 truncate font-medium text-ink">{{ $firstSellerOrder?->store?->name ?? 'Store unavailable' }}</p></div><div><p class="text-xs font-medium text-muted">Seller</p><p class="mt-1 truncate font-medium text-ink">{{ $seller?->name ?? 'Seller unavailable' }}</p></div><div><p class="text-xs font-medium text-muted">Items</p><p class="mt-1 font-semibold tabular-nums text-ink">{{ number_format($itemCount) }}</p></div><div><p class="text-xs font-medium text-muted">Total</p><p class="mt-1 font-semibold tabular-nums text-ink">&#8369;{{ number_format($order->total, 2) }}</p></div></div><div class="mt-4 flex items-center justify-between gap-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusStyles[$order->payment->status] }}">Payment: {{ ucfirst($order->payment->status) }}</span><a href="{{ route('admin.orders.show', $order) }}" class="inline-flex min-h-11 items-center rounded-lg bg-brand px-3 text-sm font-semibold text-white focus:outline-none focus:ring-2 focus:ring-brand">View details</a></div></article>@empty<div class="px-6 py-16 text-center"><p class="font-semibold text-ink">{{ $hasFilters ? 'No orders match your filters' : 'No marketplace orders yet' }}</p>@if ($hasFilters)<a href="{{ route('admin.orders.index') }}" class="mt-5 inline-flex min-h-11 items-center rounded-lg bg-brand px-4 text-sm font-semibold text-white">Clear filters</a>@endif</div>@endforelse</div>
                    @if ($orders->hasPages())<div class="border-t border-line bg-canvas px-4 py-4">{{ $orders->links() }}</div>@endif
                </section>
            </section>
        </div>
    </div>
</x-app-layout>
