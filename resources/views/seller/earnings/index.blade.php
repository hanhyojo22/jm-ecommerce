<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-[28px] font-bold tracking-[-0.02em] text-ink leading-[1.3]">Earnings</h1>
            <p class="mt-2 text-sm text-muted">Delivered, paid orders become available for marketplace payout.</p>
        </div>
    </x-slot>

    <div class="page-shell">
        <div class="page-container seller-workspace-page">
            <section class="grid gap-4 sm:grid-cols-2" aria-label="Earnings summary">
                <article class="section-card">
                    <p class="text-sm font-medium text-muted">Available for payout</p>
                    <p class="mt-2 text-3xl font-semibold tabular-nums tracking-tight text-brand">₱{{ number_format((float) $availableBalance, 2) }}</p>
                    <p class="mt-2 text-sm text-muted">Paid, delivered sales not yet recorded in a payout.</p>
                </article>
                <article class="section-card">
                    <p class="text-sm font-medium text-muted">Paid out</p>
                    <p class="mt-2 text-3xl font-semibold tabular-nums tracking-tight text-ink">₱{{ number_format((float) $paidOutTotal, 2) }}</p>
                    <p class="mt-2 text-sm text-muted">Manual payouts recorded by the marketplace administrator.</p>
                </article>
            </section>

            <section class="mt-6 content-card" aria-labelledby="earnings-history">
                <div class="border-b border-line px-5 py-4 sm:px-6">
                    <h2 id="earnings-history" class="text-lg font-semibold text-ink">Earnings history</h2>
                    <p class="mt-1 text-sm text-muted">Commission is ₱0.00 for this MVP, so gross sales and net payable are equal.</p>
                </div>

                <div class="table-scroll hidden md:block">
                    <table class="w-full min-w-[760px] divide-y divide-line text-sm">
                        <thead class="bg-canvas text-left text-xs font-semibold uppercase tracking-[0.1em] text-muted">
                            <tr><th class="px-5 py-3.5">Order</th><th class="px-5 py-3.5">Available</th><th class="px-5 py-3.5 text-right">Gross sales</th><th class="px-5 py-3.5 text-right">Commission</th><th class="px-5 py-3.5 text-right">Net payable</th><th class="px-5 py-3.5">Status</th></tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @forelse ($earnings as $earning)
                                <tr class="hover:bg-canvas/70">
                                    <td class="px-5 py-4"><a href="{{ route('seller.orders.show', $earning->sellerOrder) }}" class="font-semibold text-ink hover:text-brand focus:outline-none focus:ring-2 focus:ring-brand">{{ $earning->sellerOrder->order->number }}</a><p class="mt-1 text-xs text-muted">Seller order delivered</p></td>
                                    <td class="whitespace-nowrap px-5 py-4 text-muted">{{ $earning->available_at->format('M j, Y') }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right font-semibold tabular-nums text-ink">₱{{ number_format((float) $earning->gross_amount, 2) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right tabular-nums text-muted">₱{{ number_format((float) $earning->commission_amount, 2) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right font-semibold tabular-nums text-ink">₱{{ number_format((float) $earning->net_amount, 2) }}</td>
                                    <td class="px-5 py-4"><span class="status-badge {{ $earning->status === 'available' && ! $earning->voided_at ? 'bg-success/10 text-success ring-success/20' : 'bg-slate-100 text-muted ring-line' }}">{{ $earning->voided_at ? 'Voided COD' : ($earning->status === 'available' ? 'Available' : 'Paid out') }}</span>@if ($earning->payout)<p class="mt-1 text-xs text-muted">{{ $earning->payout->reference }}</p>@endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="empty-state"><p class="font-semibold text-ink">No earnings available yet</p><p class="mt-2 text-sm text-muted">An earning is created after a delivered COD order is confirmed as paid.</p></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-line md:hidden">
                    @forelse ($earnings as $earning)
                        <article class="p-5"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><a href="{{ route('seller.orders.show', $earning->sellerOrder) }}" class="break-all font-semibold text-ink">{{ $earning->sellerOrder->order->number }}</a><p class="mt-1 text-sm text-muted">Available {{ $earning->available_at->format('M j, Y') }}</p></div><span class="status-badge shrink-0 {{ $earning->status === 'available' && ! $earning->voided_at ? 'bg-success/10 text-success ring-success/20' : 'bg-slate-100 text-muted ring-line' }}">{{ $earning->voided_at ? 'Voided COD' : ($earning->status === 'available' ? 'Available' : 'Paid out') }}</span></div><dl class="mt-4 grid grid-cols-2 gap-3 border-t border-line pt-4 text-sm"><div><dt class="text-muted">Gross sales</dt><dd class="mt-1 font-semibold tabular-nums text-ink">₱{{ number_format((float) $earning->gross_amount, 2) }}</dd></div><div><dt class="text-muted">Net payable</dt><dd class="mt-1 font-semibold tabular-nums text-ink">₱{{ number_format((float) $earning->net_amount, 2) }}</dd></div></dl></article>
                    @empty
                        <div class="empty-state"><p class="font-semibold text-ink">No earnings available yet</p><p class="mt-2 text-sm text-muted">Delivered COD orders will appear here once payment is confirmed.</p></div>
                    @endforelse
                </div>

                @if ($earnings->hasPages())<div class="border-t border-line bg-canvas px-4 py-4">{{ $earnings->links() }}</div>@endif
            </section>
        </div>
    </div>
</x-app-layout>
