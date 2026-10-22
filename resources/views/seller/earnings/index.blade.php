<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-[28px] font-bold tracking-[-0.02em] text-ink leading-[1.3]">Earnings</h1>
            <p class="mt-2 text-sm text-muted">Delivered sales, commission, and COD cash collection for your store.</p>
        </div>
    </x-slot>

    <div class="page-shell">
        <div class="page-container seller-workspace-page">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Earnings summary">
                <article class="section-card">
                    <p class="text-sm font-medium text-muted">Net earnings</p>
                    <p class="mt-2 text-3xl font-semibold tabular-nums tracking-tight text-brand">₱{{ number_format((float) $netEarnings, 2) }}</p>
                    <p class="mt-2 text-sm text-muted">Delivered sales after refunds and ₱{{ number_format((float) $commissionTotal, 2) }} JMMart commission.</p>
                </article>
                <article class="section-card">
                    <p class="text-sm font-medium text-muted">Delivered sales</p>
                    <p class="mt-2 text-3xl font-semibold tabular-nums tracking-tight text-ink">₱{{ number_format((float) $deliveredSales, 2) }}</p>
                    <p class="mt-2 text-sm text-muted">Order value of everything you have delivered, less refunds, before commission.</p>
                </article>
                <article class="section-card">
                    <p class="text-sm font-medium text-muted">COD collected</p>
                    <p class="mt-2 text-3xl font-semibold tabular-nums tracking-tight text-ink">₱{{ number_format((float) $codCollected, 2) }}</p>
                    <p class="mt-2 text-sm text-muted">Cash you have confirmed receiving from buyers.</p>
                </article>
                <article class="section-card">
                    <p class="text-sm font-medium text-muted">Awaiting collection</p>
                    <p class="mt-2 text-3xl font-semibold tabular-nums tracking-tight text-ink">₱{{ number_format((float) $awaitingCollection, 2) }}</p>
                    <p class="mt-2 text-sm text-muted">Delivered COD sales not yet marked as collected.</p>
                </article>
            </section>

            <p class="mt-4 text-sm text-muted">COD buyers pay you directly, so JMMart never holds this money. Commission is billed on your monthly <a href="{{ route('seller.platform-fees.index') }}" class="font-semibold text-brand underline">platform fee statement</a>.</p>

            <section class="mt-6 content-card" aria-labelledby="earnings-history">
                <div class="border-b border-line px-5 py-4 sm:px-6">
                    <h2 id="earnings-history" class="text-lg font-semibold text-ink">Earnings history</h2>
                    <p class="mt-1 text-sm text-muted">Commission is JMMart's platform fee on each delivered order. You collect COD cash yourself, so it is billed on your monthly <a href="{{ route('seller.platform-fees.index') }}" class="font-semibold text-brand underline">platform fee statement</a>.</p>
                </div>

                <div class="table-scroll hidden md:block">
                    <table class="w-full min-w-[760px] divide-y divide-line text-sm">
                        <thead class="bg-canvas text-left text-xs font-semibold uppercase tracking-[0.1em] text-muted">
                            <tr><th class="px-5 py-3.5">Order</th><th class="px-5 py-3.5">Delivered</th><th class="px-5 py-3.5 text-right">Gross sales</th><th class="px-5 py-3.5 text-right">Commission</th><th class="px-5 py-3.5 text-right">Net earnings</th><th class="px-5 py-3.5">Status</th></tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @forelse ($earnings as $earning)
                                <tr class="hover:bg-canvas/70">
                                    <td class="px-5 py-4"><a href="{{ route('seller.orders.show', $earning->sellerOrder) }}" class="font-semibold text-ink hover:text-brand focus:outline-none focus:ring-2 focus:ring-brand">{{ $earning->sellerOrder->order->number }}</a><p class="mt-1 text-xs text-muted">Seller order delivered</p></td>
                                    <td class="whitespace-nowrap px-5 py-4 text-muted">{{ $earning->available_at->format('M j, Y') }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right font-semibold tabular-nums text-ink">₱{{ number_format((float) $earning->gross_amount, 2) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right tabular-nums text-muted">₱{{ number_format((float) $earning->commission_amount, 2) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right font-semibold tabular-nums text-ink">₱{{ number_format((float) $earning->net_amount, 2) }}</td>
                                    <td class="px-5 py-4"><span class="status-badge {{ ($earning->status === 'available' && ! $earning->voided_at) || $earning->sellerOrder->cod_collected_at ? 'bg-success/10 text-success ring-success/20' : 'bg-slate-100 text-muted ring-line' }}">{{ $earning->voided_at ? ($earning->sellerOrder->cod_collected_at ? 'Cash collected' : 'Awaiting cash') : ($earning->status === 'available' ? 'Platform-held' : 'Paid out') }}</span>@if ($earning->payout)<p class="mt-1 text-xs text-muted">{{ $earning->payout->reference }}</p>@endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="empty-state"><p class="font-semibold text-ink">No earnings yet</p><p class="mt-2 text-sm text-muted">An earning is recorded when you mark an order as delivered.</p></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-line md:hidden">
                    @forelse ($earnings as $earning)
                        <article class="p-5"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><a href="{{ route('seller.orders.show', $earning->sellerOrder) }}" class="break-all font-semibold text-ink">{{ $earning->sellerOrder->order->number }}</a><p class="mt-1 text-sm text-muted">Delivered {{ $earning->available_at->format('M j, Y') }}</p></div><span class="status-badge shrink-0 {{ ($earning->status === 'available' && ! $earning->voided_at) || $earning->sellerOrder->cod_collected_at ? 'bg-success/10 text-success ring-success/20' : 'bg-slate-100 text-muted ring-line' }}">{{ $earning->voided_at ? ($earning->sellerOrder->cod_collected_at ? 'Cash collected' : 'Awaiting cash') : ($earning->status === 'available' ? 'Platform-held' : 'Paid out') }}</span></div><dl class="mt-4 grid grid-cols-2 gap-3 border-t border-line pt-4 text-sm"><div><dt class="text-muted">Gross sales</dt><dd class="mt-1 font-semibold tabular-nums text-ink">₱{{ number_format((float) $earning->gross_amount, 2) }}</dd></div><div><dt class="text-muted">Net earnings</dt><dd class="mt-1 font-semibold tabular-nums text-ink">₱{{ number_format((float) $earning->net_amount, 2) }}</dd></div></dl></article>
                    @empty
                        <div class="empty-state"><p class="font-semibold text-ink">No earnings yet</p><p class="mt-2 text-sm text-muted">Orders appear here once you mark them as delivered.</p></div>
                    @endforelse
                </div>

                @if ($earnings->hasPages())<div class="border-t border-line bg-canvas px-4 py-4">{{ $earnings->links() }}</div>@endif
            </section>
        </div>
    </div>
</x-app-layout>
