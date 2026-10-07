<x-app-layout>
    @php($peakOrders = max(1, (int) $orderActivity->max('orders')))
    <div class="min-w-0 space-y-6 px-[18px] pb-12 pt-6 sm:px-8 lg:pt-8">
        <div>
            <h1 class="text-[28px] font-bold tracking-[-0.02em] text-ink leading-[1.55]">Marketplace overview</h1>
            <p class="mt-2 text-sm text-muted">Sales, orders, and the work that needs attention.</p>
        </div>

        <dl class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Marketplace key figures">
            <div class="rounded-[14px] border border-line bg-surface p-4"><dt class="text-sm text-muted">Gross merchandise value</dt><dd class="mt-1 text-2xl font-bold tabular-nums text-ink">₱{{ number_format($marketplaceStats['gmv'], 2) }}</dd></div>
            <div class="rounded-[14px] border border-line bg-surface p-4"><dt class="text-sm text-muted">Orders</dt><dd class="mt-1 text-2xl font-bold tabular-nums text-ink">{{ number_format($marketplaceStats['orders']) }}</dd></div>
            <div class="rounded-[14px] border border-line bg-surface p-4"><dt class="text-sm text-muted">Customers</dt><dd class="mt-1 text-2xl font-bold tabular-nums text-ink">{{ number_format($marketplaceStats['customers']) }}</dd></div>
            <div class="rounded-[14px] border border-line bg-surface p-4"><dt class="text-sm text-muted">Active sellers</dt><dd class="mt-1 text-2xl font-bold tabular-nums text-ink">{{ number_format($marketplaceStats['activeSellers']) }}</dd></div>
        </dl>

        <section class="rounded-[14px] border border-line bg-surface p-5 sm:p-6" aria-labelledby="marketplace-health-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h2 id="marketplace-health-heading" class="text-lg font-semibold text-ink">Marketplace health</h2><p class="mt-1 text-sm text-muted">{{ number_format($counts['attention']) }} {{ Str::plural('item', $counts['attention']) }} waiting for review.</p></div>
                <a href="{{ route('admin.moderation') }}" class="inline-flex min-h-11 items-center rounded-[10px] bg-brand px-4 text-sm font-semibold text-white transition hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Open moderation</a>
            </div>
            <div class="mt-5 divide-y divide-line border-t border-line">
                @foreach ([
                    ['label' => 'Seller applications', 'count' => $counts['applications'], 'type' => 'application', 'action' => 'Review'],
                    ['label' => 'Store appeals', 'count' => $counts['appeals'], 'type' => 'appeal', 'action' => 'Review'],
                    ['label' => 'Reported reviews', 'count' => $counts['productReports'], 'type' => 'product_report', 'action' => 'Moderate'],
                    ['label' => 'Chat reports', 'count' => $counts['chatReports'], 'type' => 'chat_report', 'action' => 'Review'],
                ] as $queue)
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 py-3 text-sm sm:grid-cols-[minmax(0,1fr)_5rem_7rem]">
                        <span class="font-medium text-ink">{{ $queue['label'] }}</span>
                        <span class="text-right font-semibold tabular-nums text-ink sm:text-left">{{ number_format($queue['count']) }}</span>
                        <a href="{{ route('admin.moderation', ['type' => $queue['type']]) }}" class="col-span-2 inline-flex min-h-10 w-fit items-center rounded-[10px] border border-line px-3 font-semibold text-brand transition hover:border-brand hover:bg-blue-50 sm:col-span-1">{{ $queue['action'] }}</a>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(19rem,1fr)]">
            <section class="rounded-[14px] border border-line bg-surface p-5 sm:p-6" aria-labelledby="order-activity-heading">
                <h2 id="order-activity-heading" class="text-lg font-semibold text-ink">Orders in the last 7 days</h2>
                @if ($orderActivity->sum('orders') > 0)
                    <div class="mt-5 flex h-44 items-end gap-2" role="img" aria-label="Daily order counts for the last 7 days">
                        @foreach ($orderActivity as $day)
                            <div class="flex h-full min-w-0 flex-1 flex-col justify-end gap-2 text-center">
                                <div class="flex h-36 items-end rounded-md bg-blue-50"><span class="block w-full rounded-md bg-brand" style="height: {{ $day['orders'] ? max(4, round($day['orders'] / $peakOrders * 100)) : 0 }}%" title="{{ $day['date'] }}: {{ $day['orders'] }} {{ Str::plural('order', $day['orders']) }}"></span></div>
                                <span class="text-xs font-medium text-muted">{{ $day['day'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    <ul class="sr-only">@foreach ($orderActivity as $day)<li>{{ $day['date'] }}: {{ $day['orders'] }} {{ Str::plural('order', $day['orders']) }}</li>@endforeach</ul>
                @else
                    <p class="mt-5 rounded-[10px] bg-canvas px-4 py-12 text-center text-sm text-muted">No orders in the last 7 days.</p>
                @endif
            </section>
            <section class="rounded-[14px] border border-line bg-surface p-5 sm:p-6" aria-labelledby="top-sellers-heading">
                <h2 id="top-sellers-heading" class="text-lg font-semibold text-ink">Top sellers and products</h2>
                <div class="mt-4 grid gap-5 sm:grid-cols-2 xl:grid-cols-1">
                    <div><h3 class="text-xs font-semibold uppercase tracking-[0.06em] text-muted">Stores by sales</h3><ol class="mt-2 space-y-2 text-sm">@forelse ($topStores as $store)<li class="flex min-w-0 justify-between gap-3"><a href="{{ route('admin.stores.show', $store) }}" class="truncate font-medium text-brand hover:text-brand-hover">{{ $store->name }}</a><span class="shrink-0 tabular-nums text-muted">₱{{ number_format((float) $store->sales_total, 2) }}</span></li>@empty<li class="text-muted">No stores yet.</li>@endforelse</ol></div>
                    <div><h3 class="text-xs font-semibold uppercase tracking-[0.06em] text-muted">Products by units sold</h3><ol class="mt-2 space-y-2 text-sm">@forelse ($topProducts as $product)<li class="flex min-w-0 justify-between gap-3"><a href="{{ route('admin.products.show', $product) }}" class="truncate font-medium text-brand hover:text-brand-hover">{{ $product->name }}</a><span class="shrink-0 tabular-nums text-muted">{{ number_format((int) $product->sold_quantity) }} sold</span></li>@empty<li class="text-muted">No products yet.</li>@endforelse</ol></div>
                </div>
            </section>
        </div>

        <section class="overflow-hidden rounded-[14px] border border-line bg-surface" aria-labelledby="recent-orders-heading">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4 sm:px-6"><h2 id="recent-orders-heading" class="text-lg font-semibold text-ink">Recent orders</h2><a href="{{ route('admin.orders.index') }}" class="text-sm font-semibold text-brand hover:text-brand-hover">View all orders →</a></div>
            @forelse ($recentOrders as $order)
                <a href="{{ route('admin.orders.show', $order) }}" class="grid gap-1 border-b border-line px-5 py-3 text-sm transition last:border-0 hover:bg-canvas sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_7rem_7rem] sm:items-center sm:gap-3 sm:px-6"><span class="font-semibold text-ink">{{ $order->number }}</span><span class="truncate text-muted">{{ $order->user?->name ?? $order->recipient_name }}</span><span class="capitalize text-muted">{{ str_replace('_', ' ', $order->status) }}</span><span class="font-semibold tabular-nums text-ink sm:text-right">₱{{ number_format($order->total, 2) }}</span></a>
            @empty
                <p class="px-5 py-10 text-center text-sm text-muted">No orders yet.</p>
            @endforelse
        </section>
    </div>
</x-app-layout>
