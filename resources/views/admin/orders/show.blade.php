<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('admin.orders.index') }}" class="text-sm font-semibold text-emerald-800 underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-emerald-700">Marketplace orders</a>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{{ $order->number }}</h1>
                <p class="mt-2 text-sm text-slate-600">Placed {{ $order->created_at->format('M j, Y · g:i A') }} · {{ $order->sellerOrders->count() }} {{ Str::plural('seller order', $order->sellerOrders->count()) }}</p>
            </div>
            <span class="inline-flex h-fit rounded-full px-3 py-1.5 text-sm font-semibold ring-1 ring-inset {{ ['pending' => 'bg-amber-50 text-amber-800 ring-amber-200', 'confirmed' => 'bg-sky-50 text-sky-800 ring-sky-200', 'processing' => 'bg-violet-50 text-violet-800 ring-violet-200', 'shipped' => 'bg-indigo-50 text-indigo-800 ring-indigo-200', 'delivered' => 'bg-emerald-50 text-emerald-800 ring-emerald-200', 'cancelled' => 'bg-rose-50 text-rose-800 ring-rose-200'][$order->status] }}">{{ ucfirst($order->status) }}</span>
        </div>
    </x-slot>

    @php($statusStyles = ['pending' => 'bg-amber-50 text-amber-800 ring-amber-200', 'confirmed' => 'bg-sky-50 text-sky-800 ring-sky-200', 'processing' => 'bg-violet-50 text-violet-800 ring-violet-200', 'shipped' => 'bg-indigo-50 text-indigo-800 ring-indigo-200', 'delivered' => 'bg-emerald-50 text-emerald-800 ring-emerald-200', 'cancelled' => 'bg-rose-50 text-rose-800 ring-rose-200', 'paid' => 'bg-emerald-50 text-emerald-800 ring-emerald-200', 'failed' => 'bg-rose-50 text-rose-800 ring-rose-200', 'refunded' => 'bg-slate-100 text-slate-700 ring-slate-200'])

    <div class="bg-[#faf8f5] py-8 sm:py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900" role="status">{{ session('status') }}</div>@endif
            @if ($errors->has('payment'))<div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert"><span>{{ $errors->first('payment') }}</span><a href="{{ route('admin.orders.show', $order) }}" class="font-semibold underline underline-offset-4">Retry</a></div>@endif

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_21rem]">
                <div class="space-y-6">
                    <section class="rounded-xl border border-stone-200 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.08)] sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-4"><div><h2 class="font-semibold text-slate-950">Order summary</h2><p class="mt-1 text-sm text-slate-500">Marketplace order totals captured at checkout.</p></div><span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusStyles[$order->payment->status] }}">Payment: {{ ucfirst($order->payment->status) }}</span></div>
                        <dl class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3"><div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Order date</dt><dd class="mt-1 font-medium text-slate-900">{{ $order->created_at->format('M j, Y · g:i A') }}</dd></div><div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Payment method</dt><dd class="mt-1 font-medium text-slate-900">{{ $order->payment->method === 'cod' ? 'Cash on delivery' : ucfirst($order->payment->method) }}</dd></div><div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Payment status</dt><dd class="mt-1 font-medium text-slate-900">{{ ucfirst($order->payment->status) }}</dd></div><div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Subtotal</dt><dd class="mt-1 font-semibold tabular-nums text-slate-900">₱{{ number_format($order->subtotal, 2) }}</dd></div><div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Shipping fee</dt><dd class="mt-1 font-semibold tabular-nums text-slate-900">₱{{ number_format($order->shipping_amount, 2) }}</dd></div><div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Final total</dt><dd class="mt-1 text-lg font-semibold tabular-nums text-slate-950">₱{{ number_format($order->total, 2) }}</dd></div></dl>
                    </section>

                    <section class="rounded-xl border border-stone-200 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.08)] sm:p-6">
                        <h2 class="font-semibold text-slate-950">Customer information</h2>
                        <div class="mt-5 grid gap-5 sm:grid-cols-2"><div><p class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Customer</p><p class="mt-1 font-medium text-slate-900">{{ $order->user->name }}</p><p class="mt-1 text-sm text-slate-600">{{ $order->user->email }}</p><p class="mt-1 text-sm text-slate-600">{{ $order->phone }}</p></div><div><p class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Delivery address</p><p class="mt-1 text-sm leading-6 text-slate-800">{{ $order->recipient_name }}<br>{{ $order->address_line }}<br>{{ $order->city }}</p></div></div>
                    </section>

                    <section class="space-y-5" aria-labelledby="seller-orders-heading">
                        <div><h2 id="seller-orders-heading" class="font-semibold text-slate-950">Seller sub-orders</h2><p class="mt-1 text-sm text-slate-500">Each store fulfils its own portion of this marketplace order.</p></div>
                        @foreach ($order->sellerOrders as $sellerOrder)
                            @php($seller = $sellerOrder->store->tenant->members->firstWhere('role', 'owner')?->user ?? $sellerOrder->store->tenant->members->first()?->user)
                            <section class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.08)]">
                                <div class="flex flex-col gap-4 border-b border-stone-100 px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6"><div class="min-w-0"><p class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Seller order</p><h3 class="mt-1 truncate font-semibold text-slate-950">{{ $sellerOrder->store->name }}</h3><p class="mt-1 text-sm text-slate-600">{{ $seller?->name ?? 'Seller unavailable' }}</p></div><span class="inline-flex h-fit w-fit rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusStyles[$sellerOrder->status] }}">{{ ucfirst($sellerOrder->status) }}</span></div>
                                <div class="divide-y divide-stone-100">@foreach ($sellerOrder->items as $item)<div class="flex gap-4 px-5 py-4 sm:px-6">@if ($item->product?->images->first())<img src="{{ Storage::disk('product_images')->url($item->product->images->first()->path) }}" alt="" class="h-14 w-14 shrink-0 rounded-lg border border-stone-100 object-cover">@else<div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg bg-stone-100 text-sm font-semibold text-stone-400" aria-hidden="true">{{ Str::upper(Str::substr($item->product_name, 0, 1)) }}</div>@endif<div class="min-w-0 flex-1"><p class="truncate font-medium text-slate-900">{{ $item->product_name }}</p><p class="mt-1 text-xs text-slate-500">SKU {{ $item->sku }} · ₱{{ number_format($item->unit_price, 2) }} each</p></div><div class="shrink-0 text-right text-sm"><p class="font-semibold tabular-nums text-slate-950">₱{{ number_format($item->line_total, 2) }}</p><p class="mt-1 text-slate-500">Qty {{ $item->quantity }}</p></div></div>@endforeach</div>
                                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-stone-100 bg-stone-50 px-5 py-3 text-sm sm:px-6"><div class="flex gap-4"><a href="{{ route('admin.stores.show', $sellerOrder->store) }}" class="font-semibold text-emerald-800 underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-emerald-700">Admin store details</a><a href="{{ route('catalog.store', $sellerOrder->store) }}" class="font-semibold text-emerald-800 underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-emerald-700">Public storefront</a></div><p class="font-semibold tabular-nums text-slate-950">Store total ₱{{ number_format($sellerOrder->total, 2) }}</p></div>
                            </section>
                        @endforeach
                    </section>

                    <section class="rounded-xl border border-stone-200 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.08)] sm:p-6">
                        <h2 class="font-semibold text-slate-950">Recorded history</h2><p class="mt-1 text-sm text-slate-500">Only existing marketplace audit records are shown.</p>
                        <div class="mt-5 space-y-4">@forelse ($auditLogs as $log)<div class="relative border-l border-stone-200 pl-4"><span class="absolute -left-1.5 top-1.5 h-3 w-3 rounded-full bg-emerald-700"></span><p class="font-medium text-slate-900">{{ str($log->action)->replace('.', ' ')->headline() }}</p><p class="mt-1 text-sm text-slate-600">{{ $log->actor?->name ?? 'System' }} · {{ $log->created_at->format('M j, Y · g:i A') }}</p></div>@empty<p class="text-sm text-slate-600">No marketplace audit events have been recorded for this order.</p>@endforelse</div>
                    </section>
                </div>

                <aside class="space-y-6">
                    <section class="rounded-xl border border-stone-200 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.08)]">
                        <h2 class="font-semibold text-slate-950">Payment</h2><p class="mt-1 text-sm text-slate-600">Cash on delivery is confirmed only after delivery.</p>
                        <dl class="mt-5 space-y-4 text-sm"><div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Method</dt><dd class="mt-1 font-medium text-slate-900">Cash on delivery</dd></div><div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Status</dt><dd class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusStyles[$order->payment->status] }}">{{ ucfirst($order->payment->status) }}</dd></div></dl>
                        @if ($order->status === 'delivered' && $order->sellerOrders->isNotEmpty() && $order->sellerOrders->every(fn ($sellerOrder): bool => $sellerOrder->status === 'delivered') && $order->payment->method === 'cod' && $order->payment->status === 'pending')<form method="POST" action="{{ route('admin.orders.payment.mark-paid', $order) }}" x-data="{ submitting: false }" @submit="submitting = true" class="mt-6 border-t border-stone-100 pt-5">@csrf @method('PATCH')<button type="submit" :disabled="submitting" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-emerald-700 px-4 text-sm font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-70"><span x-show="!submitting">Mark COD as paid</span><span x-cloak x-show="submitting">Recording payment…</span></button></form>@endif
                    </section>

                    <section class="rounded-xl border border-stone-200 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.08)]">
                        <h2 class="font-semibold text-slate-950">Marketplace record</h2><dl class="mt-5 space-y-4 text-sm"><div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Overall status</dt><dd class="mt-1 font-medium text-slate-900">{{ ucfirst($order->status) }}</dd></div><div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Stores involved</dt><dd class="mt-1 font-medium text-slate-900">{{ $order->sellerOrders->count() }}</dd></div><div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Order reference</dt><dd class="mt-1 break-all font-medium text-slate-900">{{ $order->number }}</dd></div></dl></section>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
