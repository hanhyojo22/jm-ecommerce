<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('admin.orders.index') }}" class="text-sm font-semibold text-brand underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-brand">Marketplace orders</a>
                <h1 class="mt-2 break-all marketplace-page-title">{{ $order->number }}</h1>
                <p class="mt-2 text-sm text-muted">Placed {{ $order->created_at->format('M j, Y · g:i A') }} · {{ $order->sellerOrders->count() }} {{ Str::plural('seller order', $order->sellerOrders->count()) }}</p>
            </div>
            <span class="inline-flex h-fit rounded-full px-3 py-1.5 text-sm font-semibold ring-1 ring-inset {{ ['pending' => 'bg-warning/10 text-warning-text ring-warning/20', 'confirmed' => 'bg-brand/10 text-brand-hover ring-brand/20', 'processing' => 'bg-brand/10 text-brand-hover ring-brand/20', 'shipped' => 'bg-brand/10 text-brand-hover ring-brand/20', 'delivered' => 'bg-success/10 text-success ring-success/20', 'cancelled' => 'bg-danger/10 text-danger-text ring-danger/20'][$order->status] }}">{{ ucfirst($order->status) }}</span>
        </div>
    </x-slot>

    @php($statusStyles = ['pending' => 'bg-warning/10 text-warning-text ring-warning/20', 'confirmed' => 'bg-brand/10 text-brand-hover ring-brand/20', 'processing' => 'bg-brand/10 text-brand-hover ring-brand/20', 'shipped' => 'bg-brand/10 text-brand-hover ring-brand/20', 'delivered' => 'bg-success/10 text-success ring-success/20', 'cancelled' => 'bg-danger/10 text-danger-text ring-danger/20', 'paid' => 'bg-success/10 text-success ring-success/20', 'failed' => 'bg-danger/10 text-danger-text ring-danger/20', 'refunded' => 'bg-canvas text-muted ring-line'])

    <div class="bg-canvas py-8 sm:py-10">
        <div class="mx-auto max-w-[1320px] px-[18px] sm:px-8">
            @if (session('status'))<div class="mb-5 rounded-2xl border border-success/20 bg-success/10 px-4 py-3 text-sm font-medium text-success" role="status">{{ session('status') }}</div>@endif
            @if ($errors->has('payment'))<div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert"><span>{{ $errors->first('payment') }}</span><a href="{{ route('admin.orders.show', $order) }}" class="font-semibold underline underline-offset-4">Retry</a></div>@endif

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_21rem]">
                <div class="space-y-6">
                    <section class="rounded-2xl border border-line bg-surface p-5 sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-4"><div><h2 class="font-semibold text-ink">Order summary</h2><p class="mt-1 text-sm text-muted">Marketplace order totals captured at checkout.</p></div><span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusStyles[$order->payment->status] }}">Payment: {{ ucfirst($order->payment->status) }}</span></div>
                        <dl class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3"><div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Order date</dt><dd class="mt-1 font-medium text-ink">{{ $order->created_at->format('M j, Y · g:i A') }}</dd></div><div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Payment method</dt><dd class="mt-1 font-medium text-ink">{{ $order->payment->method === 'cod' ? 'Cash on delivery' : ucfirst($order->payment->method) }}</dd></div><div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Payment status</dt><dd class="mt-1 font-medium text-ink">{{ ucfirst($order->payment->status) }}</dd></div><div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Subtotal</dt><dd class="mt-1 font-semibold tabular-nums text-ink">₱{{ number_format($order->subtotal, 2) }}</dd></div><div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Shipping fee</dt><dd class="mt-1 font-semibold tabular-nums text-ink">₱{{ number_format($order->shipping_amount, 2) }}</dd></div><div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Final total</dt><dd class="mt-1 text-lg font-semibold tabular-nums text-ink">₱{{ number_format($order->total, 2) }}</dd></div></dl>
                    </section>

                    <section class="rounded-2xl border border-line bg-surface p-5 sm:p-6">
                        <h2 class="font-semibold text-ink">Customer information</h2>
                        <div class="mt-5 grid gap-5 sm:grid-cols-2"><div><p class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Customer</p><p class="mt-1 font-medium text-ink">{{ $order->user->name }}</p><p class="mt-1 text-sm text-muted">{{ $order->user->email }}</p><p class="mt-1 text-sm text-muted">{{ $order->phone }}</p></div><div><p class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Delivery address</p><p class="mt-1 text-sm leading-6 text-ink">{{ $order->recipient_name }}<br>{{ $order->address_line }}<br>{{ collect([$order->city, $order->province])->filter()->join(', ') }} {{ $order->postal_code }}</p>@if ($order->delivery_note)<p class="mt-2 whitespace-pre-line text-sm text-ink"><span class="font-semibold">Delivery note:</span> {{ $order->delivery_note }}</p>@endif</div></div>
                    </section>

                    <section class="space-y-5" aria-labelledby="seller-orders-heading">
                        <div><h2 id="seller-orders-heading" class="font-semibold text-ink">Seller sub-orders</h2><p class="mt-1 text-sm text-muted">Each store fulfils its own portion of this marketplace order.</p></div>
                        @foreach ($order->sellerOrders as $sellerOrder)
                            @php($seller = $sellerOrder->store->tenant->members->firstWhere('role', 'owner')?->user ?? $sellerOrder->store->tenant->members->first()?->user)
                            <section class="overflow-hidden rounded-2xl border border-line bg-surface ">
                                <div class="flex flex-col gap-4 border-b border-line px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6"><div class="min-w-0"><p class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Seller order</p><h3 class="mt-1 truncate font-semibold text-ink">{{ $sellerOrder->store->name }}</h3><p class="mt-1 text-sm text-muted">{{ $seller?->name ?? 'Seller unavailable' }}</p></div><span class="inline-flex h-fit w-fit rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusStyles[$sellerOrder->status] }}">{{ ucfirst($sellerOrder->status) }}</span></div>
                                <div class="divide-y divide-line">@foreach ($sellerOrder->items as $item)<div class="flex gap-4 px-5 py-4 sm:px-6">@if ($item->product?->images->first())<img src="{{ Storage::disk('product_images')->url($item->product->images->first()->path) }}" alt="" class="h-14 w-14 shrink-0 rounded-lg border border-line object-cover">@else<div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg bg-canvas text-sm font-semibold text-muted" aria-hidden="true">{{ Str::upper(Str::substr($item->product_name, 0, 1)) }}</div>@endif<div class="min-w-0 flex-1"><p class="truncate font-medium text-ink">{{ $item->product_name }}</p>@if ($item->variant_label)<p class="mt-1 text-xs font-medium text-brand">{{ $item->variant_label }}</p>@endif<p class="mt-1 text-xs text-muted">SKU {{ $item->sku }} · ₱{{ number_format($item->unit_price, 2) }} each</p></div><div class="shrink-0 text-right text-sm"><p class="font-semibold tabular-nums text-ink">₱{{ number_format($item->line_total, 2) }}</p><p class="mt-1 text-muted">Qty {{ $item->quantity }}</p></div></div>@endforeach</div>
                                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line bg-canvas px-5 py-3 text-sm sm:px-6"><div class="flex gap-4"><a href="{{ route('admin.stores.show', $sellerOrder->store) }}" class="font-semibold text-brand underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-brand">Admin store details</a><a href="{{ route('catalog.store', $sellerOrder->store) }}" class="font-semibold text-brand underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-brand">Public storefront</a></div><p class="font-semibold tabular-nums text-ink">Store total ₱{{ number_format($sellerOrder->total, 2) }}</p></div>
                            </section>
                        @endforeach
                    </section>

                    <section class="rounded-2xl border border-line bg-surface p-5 sm:p-6">
                        <h2 class="font-semibold text-ink">Recorded history</h2><p class="mt-1 text-sm text-muted">Only existing marketplace audit records are shown.</p>
                        <div class="mt-5 space-y-4">@forelse ($auditLogs as $log)<div class="relative border-l border-line pl-4"><span class="absolute -left-1.5 top-1.5 h-3 w-3 rounded-full bg-brand"></span><p class="font-medium text-ink">{{ str($log->action)->replace('.', ' ')->headline() }}</p><p class="mt-1 text-sm text-muted">{{ $log->actor?->name ?? 'System' }} · {{ $log->created_at->format('M j, Y · g:i A') }}</p></div>@empty<p class="text-sm text-muted">No marketplace audit events have been recorded for this order.</p>@endforelse</div>
                    </section>
                </div>

                <aside class="space-y-6">
                    <section class="rounded-2xl border border-line bg-surface p-5 ">
                        <h2 class="font-semibold text-ink">Payment</h2><p class="mt-1 text-sm text-muted">The parent COD payment summarizes collection across seller orders. Verify each store before a bulk correction.</p>
                        <dl class="mt-5 space-y-4 text-sm"><div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Method</dt><dd class="mt-1 font-medium text-ink">Cash on delivery</dd></div><div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Status</dt><dd class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusStyles[$order->payment->status] }}">{{ ucfirst($order->payment->status) }}</dd></div></dl>
                        @if ($order->status === 'delivered' && $order->sellerOrders->isNotEmpty() && $order->sellerOrders->every(fn ($sellerOrder): bool => $sellerOrder->status === 'delivered') && $order->payment->method === 'cod' && $order->payment->status === 'pending')<form method="POST" action="{{ route('admin.orders.payment.mark-paid', $order) }}" x-data="{ submitting: false }" @submit="submitting = true" class="mt-6 border-t border-line pt-5">@csrf @method('PATCH')<button type="submit" :disabled="submitting" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-brand px-4 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2 disabled:cursor-wait disabled:opacity-70"><span x-show="!submitting">Confirm all stores collected COD</span><span x-cloak x-show="submitting">Recording payment…</span></button></form>@endif
                    </section>

                    <section class="rounded-2xl border border-line bg-surface p-5 ">
                        <h2 class="font-semibold text-ink">Marketplace record</h2><dl class="mt-5 space-y-4 text-sm"><div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Overall status</dt><dd class="mt-1 font-medium text-ink">{{ ucfirst($order->status) }}</dd></div><div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Stores involved</dt><dd class="mt-1 font-medium text-ink">{{ $order->sellerOrders->count() }}</dd></div><div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Order reference</dt><dd class="mt-1 break-all font-medium text-ink">{{ $order->number }}</dd></div></dl></section>
                </aside>
            </div>
        </div>
    </div>
    <div class="mx-auto max-w-[1320px] px-[18px] pb-10 sm:px-8">
        <section class="overflow-hidden rounded-2xl border border-line bg-surface" aria-labelledby="late-claims-heading">
            <div class="flex flex-wrap items-start gap-3 border-b border-line bg-canvas/60 px-5 py-5 sm:px-6">
                <span class="grid size-10 shrink-0 place-items-center rounded-[10px] bg-blue-50 text-brand-hover" aria-hidden="true">
                    <x-returns.icon name="shield" class="size-5" />
                </span>
                <div class="min-w-0">
                    <h2 id="late-claims-heading" class="font-semibold text-ink">Later return or warranty claims</h2>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-muted">Support may open a case when the seven-day interface window has ended or delivery timing is uncertain.</p>
                </div>
            </div>

            <div class="space-y-4 p-4 sm:p-5">
                @foreach ($order->sellerOrders as $sellerOrder)
                    <div class="returns-late-store overflow-hidden">
                        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-4 py-3.5">
                            <div class="min-w-0">
                                <h3 class="font-semibold text-ink">{{ $sellerOrder->store->name }}</h3>
                                <p class="mt-0.5 text-xs text-muted">Store order · {{ ucfirst($sellerOrder->status) }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span @class([
                                    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset',
                                    'bg-green-50 text-success ring-green-200' => $sellerOrder->cod_collected_at,
                                    'bg-amber-50 text-warning-text ring-amber-200' => ! $sellerOrder->cod_collected_at,
                                ])>
                                    <x-returns.icon :name="$sellerOrder->cod_collected_at ? 'check' : 'clock'" class="size-3.5" />
                                    COD {{ $sellerOrder->cod_collected_at ? 'collected '.$sellerOrder->cod_collected_at->format('M j, Y') : 'not recorded' }}
                                </span>
                                @if ($sellerOrder->status === 'delivered' && ! $sellerOrder->cod_collected_at)
                                    <form method="POST" action="{{ route('admin.orders.seller-cod-collection', $sellerOrder) }}">@csrf @method('PATCH')
                                        <button class="min-h-9 rounded-[8px] border border-line bg-white px-3 text-xs font-semibold text-ink transition hover:border-brand hover:text-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Record this store's collection</button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        @if ($sellerOrder->returnCases->isNotEmpty())
                            <div>
                                <p class="px-4 pt-3 text-[11px] font-bold uppercase tracking-[0.1em] text-muted">Existing cases</p>
                                @foreach ($sellerOrder->returnCases as $case)
                                    <a href="{{ route('admin.returns.show', $case) }}" class="returns-late-case group transition hover:bg-canvas focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <x-returns.status-badge :status="$case->status" />
                                                <x-returns.deadline :case="$case" />
                                            </div>
                                            <p class="mt-1.5 text-sm text-ink">
                                                {{ ucfirst(str_replace('_', ' ', $case->reason)) }} · {{ $case->items->sum('quantity') }} {{ Str::plural('unit', $case->items->sum('quantity')) }} · opened {{ $case->created_at->format('M j, Y') }}
                                            </p>
                                        </div>
                                        <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand">
                                            Review case
                                            <x-returns.icon name="chevron-right" class="size-4 transition group-hover:translate-x-0.5" />
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        @if ($sellerOrder->status === 'cancelled')
                            <p class="border-t border-line px-4 py-3.5 text-sm text-muted">A cancelled store order cannot have a return case.</p>
                        @else
                            <details class="group border-t border-line" @if ($sellerOrder->returnCases->isEmpty()) open @endif>
                                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-semibold text-brand transition hover:bg-canvas focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand [&::-webkit-details-marker]:hidden">
                                    <span class="flex items-center gap-2">
                                        <x-returns.icon name="plus" class="size-4" />
                                        Open a late return case
                                    </span>
                                    <x-returns.icon name="chevron-down" class="size-4 transition group-open:rotate-180" />
                                </summary>
                                <form method="POST" action="{{ route('admin.returns.store', $sellerOrder) }}" class="grid gap-4 border-t border-line bg-canvas/40 px-4 py-4 sm:px-5">@csrf
                                    <p class="text-sm leading-6 text-muted">Support can open a case on this store order at any time, including after the buyer's seven-day window.</p>

                                    @if ($sellerOrder->issues->contains('status', 'open'))
                                        <div>
                                            <label for="late-issue-{{ $sellerOrder->id }}" class="field-label">Convert an open support claim</label>
                                            <select id="late-issue-{{ $sellerOrder->id }}" name="order_issue_id" class="field-control">
                                                <option value="">Open separate case</option>
                                                @foreach ($sellerOrder->issues->where('status', 'open') as $issue)
                                                    <option value="{{ $issue->id }}">{{ str($issue->type)->replace('_', ' ')->headline() }} · {{ Str::limit($issue->details, 70) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endif

                                    <fieldset>
                                        <legend class="field-label">Items in this claim</legend>
                                        <p class="-mt-1 mb-2 text-xs text-muted">Set a quantity for every item the buyer says is affected.</p>
                                        <div class="grid gap-2 sm:grid-cols-2">
                                            @foreach ($sellerOrder->items as $item)
                                                <label for="late-qty-{{ $sellerOrder->id }}-{{ $item->id }}" class="flex items-center justify-between gap-3 rounded-[10px] border border-line bg-white px-3 py-2.5 text-sm">
                                                    <span class="min-w-0">
                                                        <span class="block font-medium text-ink">{{ $item->product_name }}</span>
                                                        <span class="mt-0.5 block text-xs text-muted">Purchased {{ $item->quantity }}</span>
                                                    </span>
                                                    <input type="hidden" name="items[{{ $loop->index }}][order_item_id]" value="{{ $item->id }}">
                                                    <input id="late-qty-{{ $sellerOrder->id }}-{{ $item->id }}" type="number" name="items[{{ $loop->index }}][quantity]" value="0" min="0" max="{{ $item->quantity }}" class="h-10 w-16 rounded-[8px] border-line p-0 text-center text-sm font-semibold tabular-nums text-ink focus:border-brand focus:ring-brand">
                                                </label>
                                            @endforeach
                                        </div>
                                    </fieldset>

                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label for="late-reason-{{ $sellerOrder->id }}" class="field-label">Reason</label>
                                            <select id="late-reason-{{ $sellerOrder->id }}" name="reason" class="field-control">
                                                <option value="damaged">Damaged</option>
                                                <option value="defective">Defective</option>
                                                <option value="wrong_item">Wrong item</option>
                                                <option value="misdescribed">Materially misdescribed</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="late-remedy-{{ $sellerOrder->id }}" class="field-label">Preferred remedy</label>
                                            <select id="late-remedy-{{ $sellerOrder->id }}" name="requested_remedy" class="field-control">
                                                <option value="refund">Refund</option>
                                                <option value="replacement">Replacement</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="late-details-{{ $sellerOrder->id }}" class="field-label">Claim details</label>
                                        <textarea id="late-details-{{ $sellerOrder->id }}" name="details" required minlength="10" maxlength="2000" rows="3" class="field-control" placeholder="Record what the buyer reported and what support verified."></textarea>
                                    </div>

                                    <button class="inline-flex min-h-11 justify-self-start items-center justify-center rounded-[10px] bg-brand px-4 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Open return case</button>
                                </form>
                            </details>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-app-layout>
