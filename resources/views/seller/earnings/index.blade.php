@php
    $earningState = fn ($earning): array => [
        'label' => $earning->voided_at ? ($earning->sellerOrder->cod_collected_at ? 'Cash collected' : 'Awaiting cash') : ($earning->status === 'available' ? 'Platform-held' : 'Paid out'),
        'tone' => ($earning->status === 'available' && ! $earning->voided_at) || $earning->sellerOrder->cod_collected_at ? 'success' : 'neutral',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="sc-page-head">
            <div>
                <h1 class="sc-title">Earnings</h1>            </div>
        </div>
    </x-slot>

    <div class="page-shell">
        <div class="page-container seller-workspace-page sc-stack">
            <section aria-label="Earnings summary">
                <dl class="sc-metrics" data-columns="4">
                    <div class="sc-metric"><dt>Net earnings</dt><dd><strong class="sc-metric-value is-accent">₱{{ number_format((float) $netEarnings, 2) }}</strong><span class="sc-metric-context">Delivered sales after refunds and ₱{{ number_format((float) $commissionTotal, 2) }} JMMart commission.</span></dd></div>
                    <div class="sc-metric"><dt>Delivered sales</dt><dd><strong class="sc-metric-value">₱{{ number_format((float) $deliveredSales, 2) }}</strong><span class="sc-metric-context">Order value of everything you have delivered, less refunds, before commission.</span></dd></div>
                    <div class="sc-metric"><dt>COD collected</dt><dd><strong class="sc-metric-value">₱{{ number_format((float) $codCollected, 2) }}</strong><span class="sc-metric-context">Cash you have confirmed receiving from buyers.</span></dd></div>
                    <div class="sc-metric"><dt>Awaiting collection</dt><dd><strong class="sc-metric-value">₱{{ number_format((float) $awaitingCollection, 2) }}</strong><span class="sc-metric-context">Delivered COD sales not yet marked as collected.</span></dd></div>
                </dl>
            </section>

            <section class="sc-card" aria-labelledby="earnings-history">
                <div class="sc-card-head">
                    <div class="min-w-0">
                        <h2 id="earnings-history" class="sc-card-title">Earnings history</h2>
                        <p class="sc-card-note">COD buyers pay you directly, so JMMart never holds this money. Commission is billed on your monthly <a href="{{ route('seller.platform-fees.index') }}" class="font-semibold text-brand underline underline-offset-2">platform fee statement</a>.</p>
                    </div>
                </div>

                @if ($earnings->isNotEmpty())
                    <div class="sc-table-wrap hidden md:block">
                        <table class="sc-table min-w-[720px]">
                            <thead><tr><th scope="col">Order</th><th scope="col">Delivered</th><th scope="col" class="is-num">Gross sales</th><th scope="col" class="is-num">Commission</th><th scope="col" class="is-num">Net earnings</th><th scope="col">Status</th></tr></thead>
                            <tbody>
                                @foreach ($earnings as $earning)
                                    @php($state = $earningState($earning))
                                    <tr>
                                        <th scope="row"><a href="{{ route('seller.orders.show', $earning->sellerOrder) }}" class="font-semibold text-ink hover:text-brand"><x-marketplace.order-number :number="$earning->sellerOrder->order->number" /></a></th>
                                        <td class="is-muted whitespace-nowrap">{{ $earning->available_at->format('M j, Y') }}</td>
                                        <td class="is-num">₱{{ number_format((float) $earning->gross_amount, 2) }}</td>
                                        <td class="is-num is-muted">−₱{{ number_format((float) $earning->commission_amount, 2) }}</td>
                                        <td class="is-num font-semibold">₱{{ number_format((float) $earning->net_amount, 2) }}</td>
                                        <td><span class="sc-badge" data-tone="{{ $state['tone'] }}">{{ $state['label'] }}</span>@if ($earning->payout)<span class="mt-1 block sc-meta">{{ $earning->payout->reference }}</span>@endif</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="sc-list md:hidden">
                        @foreach ($earnings as $earning)
                            @php($state = $earningState($earning))
                            <article class="sc-list-item">
                                <div class="sc-row items-start"><div class="sc-grow"><a href="{{ route('seller.orders.show', $earning->sellerOrder) }}" class="sc-strong"><x-marketplace.order-number :number="$earning->sellerOrder->order->number" /></a><p class="sc-meta">Delivered {{ $earning->available_at->format('M j, Y') }}</p></div><span class="sc-badge" data-tone="{{ $state['tone'] }}">{{ $state['label'] }}</span></div>
                                <dl class="sc-sum mt-3"><div><dt>Gross sales</dt><dd>₱{{ number_format((float) $earning->gross_amount, 2) }}</dd></div><div><dt>Commission</dt><dd>−₱{{ number_format((float) $earning->commission_amount, 2) }}</dd></div><div class="is-total !text-sm"><dt>Net earnings</dt><dd>₱{{ number_format((float) $earning->net_amount, 2) }}</dd></div></dl>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="sc-empty"><h3>No earnings yet</h3><p>An earning is recorded when you mark an order as delivered.</p></div>
                @endif

                @if ($earnings->hasPages())<div class="sc-card-foot">{{ $earnings->links() }}</div>@endif
            </section>
        </div>
    </div>
</x-app-layout>
