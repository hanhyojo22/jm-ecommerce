<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-emerald-800">Marketplace administration</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Marketplace orders</h1>
            <p class="mt-2 text-sm text-slate-600">Monitor and manage orders across all seller stores.</p>
        </div>
    </x-slot>

    @php
        $statusStyles = ['pending' => 'bg-amber-50 text-amber-800 ring-amber-200', 'confirmed' => 'bg-sky-50 text-sky-800 ring-sky-200', 'processing' => 'bg-violet-50 text-violet-800 ring-violet-200', 'shipped' => 'bg-indigo-50 text-indigo-800 ring-indigo-200', 'delivered' => 'bg-emerald-50 text-emerald-800 ring-emerald-200', 'cancelled' => 'bg-rose-50 text-rose-800 ring-rose-200', 'paid' => 'bg-emerald-50 text-emerald-800 ring-emerald-200', 'failed' => 'bg-rose-50 text-rose-800 ring-rose-200', 'refunded' => 'bg-slate-100 text-slate-700 ring-slate-200'];
        $hasFilters = filled($filters['search'] ?? null) || filled($filters['status'] ?? null) || filled($filters['payment_status'] ?? null) || filled($filters['store_id'] ?? null) || filled($filters['from'] ?? null) || filled($filters['to'] ?? null);
        $summaryCards = [
            ['label' => 'Total orders', 'count' => $totalOrders, 'status' => null, 'valueClass' => 'text-slate-950'],
            ['label' => 'Awaiting action', 'count' => $statusCounts['pending'] ?? 0, 'status' => 'pending', 'valueClass' => 'text-amber-700'],
            ['label' => 'Processing', 'count' => $statusCounts['processing'] ?? 0, 'status' => 'processing', 'valueClass' => 'text-violet-700'],
            ['label' => 'Completed', 'count' => $statusCounts['delivered'] ?? 0, 'status' => 'delivered', 'valueClass' => 'text-emerald-800'],
            ['label' => 'Cancelled', 'count' => $statusCounts['cancelled'] ?? 0, 'status' => 'cancelled', 'valueClass' => 'text-rose-700'],
        ];
    @endphp

    <div class="bg-[#faf8f5] py-8 sm:py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900" role="status">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert"><span>{{ $errors->first() }}</span><a href="{{ route('admin.orders.index', request()->query()) }}" class="font-semibold underline underline-offset-4">Retry</a></div>
            @endif

            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" aria-label="Marketplace order summaries">
                @foreach ($summaryCards as $summary)
                    @php($selected = ($filters['status'] ?? null) === $summary['status'])
                    <a href="{{ route('admin.orders.index', [...request()->except('status', 'page'), ...($summary['status'] ? ['status' => $summary['status']] : [])]) }}" aria-current="{{ $selected ? 'page' : 'false' }}" class="rounded-xl border bg-white px-5 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition hover:border-emerald-300 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 {{ $selected ? 'border-emerald-700 ring-1 ring-emerald-700' : 'border-stone-200' }}">
                        <p class="text-2xl font-semibold tabular-nums tracking-tight {{ $summary['valueClass'] }}">{{ number_format($summary['count']) }}</p>
                        <p class="mt-1 text-sm font-medium text-slate-600">{{ $summary['label'] }}</p>
                    </a>
                @endforeach
            </section>

            <section class="mt-8" aria-labelledby="orders-heading">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 id="orders-heading" class="text-lg font-semibold tracking-tight text-slate-950">All marketplace orders</h2>
                        <p class="mt-1 text-sm text-slate-500">Review fulfilment across every seller store.</p>
                    </div>
                    @if ($hasFilters)<a href="{{ route('admin.orders.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg px-3 text-sm font-semibold text-slate-600 transition hover:bg-white hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">Clear filters</a>@endif
                </div>

                <form method="GET" action="{{ route('admin.orders.index') }}" x-data="{ submitting: false }" @submit="submitting = true" class="mt-5 grid gap-3 rounded-xl border border-stone-200 bg-white p-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)] lg:grid-cols-12" role="search" aria-label="Filter marketplace orders">
                    <div class="lg:col-span-4">
                        <label for="search" class="sr-only">Search orders</label>
                        <input id="search" name="search" value="{{ $filters['search'] ?? '' }}" type="search" placeholder="Order, customer, seller, or store" class="block h-10 w-full rounded-lg border-stone-200 text-sm shadow-sm placeholder:text-slate-400 focus:border-emerald-700 focus:ring-emerald-700">
                    </div>
                    <div class="lg:col-span-2">
                        <label for="status" class="sr-only">Order status</label>
                        <select id="status" name="status" class="block h-10 w-full rounded-lg border-stone-200 text-sm shadow-sm focus:border-emerald-700 focus:ring-emerald-700"><option value="">All order statuses</option>@foreach (['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
                    </div>
                    <div class="lg:col-span-2">
                        <label for="payment_status" class="sr-only">Payment status</label>
                        <select id="payment_status" name="payment_status" class="block h-10 w-full rounded-lg border-stone-200 text-sm shadow-sm focus:border-emerald-700 focus:ring-emerald-700"><option value="">All payments</option>@foreach (['pending', 'paid', 'failed', 'refunded'] as $status)<option value="{{ $status }}" @selected(($filters['payment_status'] ?? null) === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
                    </div>
                    <div class="lg:col-span-2">
                        <label for="store_id" class="sr-only">Store</label>
                        <select id="store_id" name="store_id" class="block h-10 w-full rounded-lg border-stone-200 text-sm shadow-sm focus:border-emerald-700 focus:ring-emerald-700"><option value="">All stores</option>@foreach ($stores as $store)<option value="{{ $store->id }}" @selected(($filters['store_id'] ?? null) === $store->id)>{{ $store->name }}</option>@endforeach</select>
                    </div>
                    <div class="lg:col-span-1">
                        <label for="from" class="sr-only">From date</label>
                        <input id="from" name="from" value="{{ $filters['from'] ?? '' }}" type="date" class="block h-10 w-full rounded-lg border-stone-200 text-sm shadow-sm focus:border-emerald-700 focus:ring-emerald-700">
                    </div>
                    <div class="lg:col-span-1">
                        <label for="to" class="sr-only">To date</label>
                        <input id="to" name="to" value="{{ $filters['to'] ?? '' }}" type="date" class="block h-10 w-full rounded-lg border-stone-200 text-sm shadow-sm focus:border-emerald-700 focus:ring-emerald-700">
                    </div>
                    <div class="lg:col-span-12 flex justify-end">
                        <button type="submit" :disabled="submitting" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-70"><span x-show="!submitting">Apply filters</span><span x-cloak x-show="submitting">Loading orders…</span></button>
                    </div>
                </form>

                @if ($hasFilters)
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-slate-600"><span class="font-medium">Active filters:</span>@if (filled($filters['search'] ?? null))<span class="rounded-full bg-white px-3 py-1 ring-1 ring-stone-200">Search: {{ $filters['search'] }}</span>@endif@if (filled($filters['status'] ?? null))<span class="rounded-full bg-white px-3 py-1 ring-1 ring-stone-200">Status: {{ ucfirst($filters['status']) }}</span>@endif@if (filled($filters['payment_status'] ?? null))<span class="rounded-full bg-white px-3 py-1 ring-1 ring-stone-200">Payment: {{ ucfirst($filters['payment_status']) }}</span>@endif@if (filled($filters['from'] ?? null) || filled($filters['to'] ?? null))<span class="rounded-full bg-white px-3 py-1 ring-1 ring-stone-200">Date range selected</span>@endif</div>
                @endif

                <section class="mt-6 overflow-hidden rounded-xl border border-stone-200 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.08)]" aria-label="Marketplace orders">
                    <div class="table-scroll hidden lg:block">
                        <table class="min-w-[1120px] divide-y divide-stone-200">
                            <thead class="bg-stone-50 text-left text-xs font-semibold uppercase tracking-[0.1em] text-slate-500"><tr><th scope="col" class="px-5 py-3.5">Order</th><th scope="col" class="px-5 py-3.5">Customer</th><th scope="col" class="px-5 py-3.5">Store / seller</th><th scope="col" class="px-5 py-3.5">Placed</th><th scope="col" class="px-5 py-3.5 text-right">Items</th><th scope="col" class="px-5 py-3.5 text-right">Total</th><th scope="col" class="px-5 py-3.5">Payment</th><th scope="col" class="px-5 py-3.5">Fulfilment</th><th scope="col" class="px-5 py-3.5"><span class="sr-only">Actions</span></th></tr></thead>
                            <tbody class="divide-y divide-stone-100">@forelse ($orders as $order)@php($firstSellerOrder = $order->sellerOrders->first())@php($seller = $firstSellerOrder?->store?->tenant?->members->firstWhere('role', 'owner')?->user ?? $firstSellerOrder?->store?->tenant?->members->first()?->user)@php($storeNames = $order->sellerOrders->pluck('store.name')->filter()->implode(', '))@php($itemCount = $order->sellerOrders->sum(fn ($sellerOrder): int => (int) $sellerOrder->item_quantity))<tr class="transition hover:bg-stone-50/80"><td class="whitespace-nowrap px-5 py-4"><a href="{{ route('admin.orders.show', $order) }}" class="font-semibold text-slate-950 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700">{{ $order->number }}</a></td><td class="max-w-44 px-5 py-4"><p class="truncate text-sm font-medium text-slate-900">{{ $order->user->name }}</p><p class="mt-0.5 truncate text-xs text-slate-500">{{ $order->user->email }}</p></td><td class="max-w-52 px-5 py-4"><p title="{{ $storeNames }}" class="truncate text-sm font-medium text-slate-800">{{ $storeNames }}</p><p title="{{ $seller?->name ?? 'Seller unavailable' }}" class="mt-0.5 truncate text-xs text-slate-500">{{ $seller?->name ?? 'Seller unavailable' }}@if ($order->sellerOrders->count() > 1) · {{ $order->sellerOrders->count() }} stores @endif</p></td><td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ $order->created_at->format('M j, Y') }}<span class="block text-xs">{{ $order->created_at->format('g:i A') }}</span></td><td class="px-5 py-4 text-right text-sm font-semibold tabular-nums text-slate-800">{{ number_format($itemCount) }}</td><td class="whitespace-nowrap px-5 py-4 text-right text-sm font-semibold tabular-nums text-slate-950">₱{{ number_format($order->total, 2) }}</td><td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusStyles[$order->payment->status] }}">{{ ucfirst($order->payment->status) }}</span></td><td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusStyles[$order->status] }}">{{ ucfirst($order->status) }}</span></td><td class="whitespace-nowrap px-5 py-4 text-right"><a href="{{ route('admin.orders.show', $order) }}" class="inline-flex min-h-10 items-center rounded-lg bg-slate-900 px-3 text-sm font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">View details</a></td></tr>@empty<tr><td colspan="9" class="px-6 py-16 text-center"><p class="font-semibold text-slate-900">{{ $hasFilters ? 'No orders match your filters' : 'No marketplace orders yet' }}</p><p class="mt-2 text-sm text-slate-600">{{ $hasFilters ? 'Try changing a filter or clearing the current search.' : 'Customer orders across seller stores will appear here.' }}</p>@if ($hasFilters)<a href="{{ route('admin.orders.index') }}" class="mt-5 inline-flex min-h-10 items-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-emerald-800">Clear filters</a>@endif</td></tr>@endforelse</tbody>
                        </table>
                    </div>

                    <div class="divide-y divide-stone-100 lg:hidden">@forelse ($orders as $order)@php($firstSellerOrder = $order->sellerOrders->first())@php($seller = $firstSellerOrder?->store?->tenant?->members->firstWhere('role', 'owner')?->user ?? $firstSellerOrder?->store?->tenant?->members->first()?->user)@php($itemCount = $order->sellerOrders->sum(fn ($sellerOrder): int => (int) $sellerOrder->item_quantity))<article class="p-5"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><a href="{{ route('admin.orders.show', $order) }}" class="block truncate font-semibold text-slate-950 focus:outline-none focus:ring-2 focus:ring-emerald-700">{{ $order->number }}</a><p class="mt-1 truncate text-sm text-slate-600">{{ $order->user->name }}</p></div><span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusStyles[$order->status] }}">{{ ucfirst($order->status) }}</span></div><div class="mt-4 grid grid-cols-2 gap-3 border-t border-stone-100 pt-4 text-sm"><div><p class="text-xs font-medium text-slate-500">Store</p><p class="mt-1 truncate font-medium text-slate-800">{{ $firstSellerOrder?->store?->name ?? 'Store unavailable' }}</p></div><div><p class="text-xs font-medium text-slate-500">Seller</p><p class="mt-1 truncate font-medium text-slate-800">{{ $seller?->name ?? 'Seller unavailable' }}</p></div><div><p class="text-xs font-medium text-slate-500">Items</p><p class="mt-1 font-semibold tabular-nums text-slate-800">{{ number_format($itemCount) }}</p></div><div><p class="text-xs font-medium text-slate-500">Total</p><p class="mt-1 font-semibold tabular-nums text-slate-950">₱{{ number_format($order->total, 2) }}</p></div></div><div class="mt-4 flex items-center justify-between gap-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusStyles[$order->payment->status] }}">Payment: {{ ucfirst($order->payment->status) }}</span><a href="{{ route('admin.orders.show', $order) }}" class="inline-flex min-h-10 items-center rounded-lg bg-slate-900 px-3 text-sm font-semibold text-white focus:outline-none focus:ring-2 focus:ring-emerald-700">View details</a></div></article>@empty<div class="px-6 py-16 text-center"><p class="font-semibold text-slate-900">{{ $hasFilters ? 'No orders match your filters' : 'No marketplace orders yet' }}</p>@if ($hasFilters)<a href="{{ route('admin.orders.index') }}" class="mt-5 inline-flex min-h-10 items-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white">Clear filters</a>@endif</div>@endforelse</div>
                    @if ($orders->hasPages())<div class="border-t border-stone-200 bg-stone-50 px-4 py-4">{{ $orders->links() }}</div>@endif
                </section>
            </section>
        </div>
    </div>
</x-app-layout>
