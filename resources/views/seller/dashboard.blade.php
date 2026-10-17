@php
    $setupItems = collect($setupItems);
    $incompleteSetupItems = $setupItems->reject(fn (array $item): bool => $item['complete']);
    $nextSetupItem = $incompleteSetupItems->first(fn (array $item): bool => filled($item['href']));
    $completedSetupCount = $setupItems->count() - $incompleteSetupItems->count();
    $chartPeak = max(1, (float) $salesHistory->max('amount'));
    $lastThirtyDaysSales = $salesHistory->reduce(fn (string $total, array $day): string => bcadd($total, (string) $day['amount'], 2), '0.00');
    $orderStyles = ['pending' => 'bg-amber-50 text-amber-800 ring-amber-200', 'confirmed' => 'bg-emerald-50 text-emerald-800 ring-emerald-200', 'processing' => 'bg-blue-50 text-blue-800 ring-blue-200', 'shipped' => 'bg-blue-50 text-blue-800 ring-blue-200', 'delivered' => 'bg-emerald-50 text-emerald-800 ring-emerald-200', 'cancelled' => 'bg-rose-50 text-rose-800 ring-rose-200'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-[32px] font-bold tracking-tight text-ink">Overview</h1>
                    <span @class(['rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset', 'bg-emerald-50 text-emerald-800 ring-emerald-200' => $store->status === 'active', 'bg-rose-50 text-rose-800 ring-rose-200' => $store->status === 'suspended', 'bg-slate-100 text-muted ring-line' => ! in_array($store->status, ['active', 'suspended'], true)])>{{ ucfirst($store->status) }} store</span>
                </div>
                <p class="mt-1 text-sm text-muted">Here&rsquo;s what&rsquo;s happening at {{ $store->name }} today.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('catalog.store', $store->slug) }}" target="_blank" rel="noopener noreferrer" class="seller-outline-button" aria-label="View {{ $store->name }} storefront in a new tab">View storefront <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9"/><path d="M18 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5"/></svg></a>
                @if ($canManageProducts)<a href="{{ $nextSetupItem['href'] ?? route('seller.products.create') }}" class="seller-primary-button">{{ $nextSetupItem && $nextSetupItem['label'] !== 'First product' ? 'Finish store setup' : 'Add product' }}</a>@endif
            </div>
        </div>
    </x-slot>

    <div class="page-shell" data-seller-dashboard data-dashboard-key="{{ $store->id }}"><div class="page-container">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm"><p class="max-w-xl text-blue-950"><strong>Seller Center</strong> tracks your store sales. You can also shop with this account; your personal purchases are kept separately.</p><div class="flex flex-wrap gap-3"><a href="{{ route('home') }}" class="inline-flex min-h-11 items-center font-semibold text-brand">Shop the marketplace</a><a href="{{ route('orders.index') }}" class="inline-flex min-h-11 items-center font-semibold text-brand">My purchases</a></div></div>
        @if (session('status'))<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">{{ session('status') }}</div>@endif

        <section aria-label="Store summary">
            <dl class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                <div class="seller-kpi-card"><dt class="seller-kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 8h16l1 12H3L4 8Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/></svg></dt><dd><span class="seller-kpi-label">Sales total</span><strong class="seller-kpi-value">₱{{ number_format($salesTotal, 2) }}</strong><span class="seller-kpi-context">All time · seller order totals; cancelled orders excluded</span></dd></div>
                <div class="seller-kpi-card"><dt class="seller-kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 4h2l2.5 12h10L21 8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg></dt><dd><span class="seller-kpi-label">Orders</span><strong class="seller-kpi-value">{{ number_format($totalOrders) }}</strong><span class="seller-kpi-context">All order statuses, including cancelled</span></dd></div>
                <div class="seller-kpi-card"><dt class="seller-kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m12 2 9 5v10l-9 5-9-5V7l9-5Z"/><path d="m3 7 9 5 9-5M12 12v10"/></svg></dt><dd><span class="seller-kpi-label">Products</span><strong class="seller-kpi-value">{{ number_format($totalProducts) }}</strong><span class="seller-kpi-context">{{ number_format($activeProducts) }} active · {{ number_format($totalProducts - $activeProducts) }} other listings</span></dd></div>
                <div class="seller-kpi-card"><dt class="seller-kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M2 20v-2a6 6 0 0 1 12 0v2H2ZM17 5a3 3 0 0 1 0 6M17 14a5 5 0 0 1 5 5v1h-5"/></svg></dt><dd><span class="seller-kpi-label">Unique buyers</span><strong class="seller-kpi-value">{{ number_format($customerCount) }}</strong><span class="seller-kpi-context">Lifetime buyers across non-cancelled orders</span></dd></div>
            </dl>
        </section>

        @if ($pendingOrderCount > 0 || $outOfStockCount > 0)
            <section class="seller-panel mt-4 p-4" aria-labelledby="action-required-heading">
                <h2 id="action-required-heading" class="font-semibold">Needs your attention</h2>
                <div class="mt-2 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    @if ($pendingOrderCount > 0)<a href="{{ route('seller.orders.index', ['status' => 'pending']) }}" class="text-brand underline underline-offset-4">{{ number_format($pendingOrderCount) }} {{ Str::plural('order', $pendingOrderCount) }} awaiting confirmation · Review orders</a>@endif
                    @if ($outOfStockCount > 0)<a href="{{ route('seller.products.index', ['stock' => 'out_of_stock']) }}" class="text-brand underline underline-offset-4">{{ number_format($outOfStockCount) }} {{ Str::plural('product', $outOfStockCount) }} out of stock · Review products</a>@endif
                </div>
            </section>
        @endif

        <div class="seller-overview-grid mt-4">
            <section class="seller-panel min-w-0 p-4 sm:p-5" aria-labelledby="sales-overview-heading">
                <div class="flex flex-wrap items-center justify-between gap-3"><h2 id="sales-overview-heading">Sales overview</h2><span class="seller-period">Last 30 days</span></div>
                <div class="mt-3 text-[25px] font-bold tracking-tight text-ink">₱{{ number_format($lastThirtyDaysSales, 2) }}</div>
                <div class="seller-sales-chart" role="img" aria-label="Sales trend for the last 30 days. Daily store sales in pesos; total {{ number_format($lastThirtyDaysSales, 2) }} pesos.">
                    @foreach ($salesHistory as $day)
                        <div class="seller-sales-day">
                            <span class="seller-sales-bar" style="height: {{ (float) $day['amount'] > 0 ? max(2, (float) $day['amount'] / $chartPeak * 100) : 0 }}%"><span class="sr-only">{{ $day['date'] }}: {{ number_format($day['amount'], 2) }} pesos</span></span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 flex justify-between text-xs text-muted"><span>{{ \Carbon\Carbon::parse($salesHistory->first()['date'])->format('M j') }}</span><span>{{ \Carbon\Carbon::parse($salesHistory[15]['date'])->format('M j') }}</span><span>{{ \Carbon\Carbon::parse($salesHistory->last()['date'])->format('M j') }}</span></div>
                @if (bccomp($lastThirtyDaysSales, '0.00', 2) === 0)<p class="mt-3 text-sm text-muted">No sales recorded in the last 30 days.</p>@endif
            </section>

            <section class="seller-panel seller-store-activity p-4 sm:p-5" aria-labelledby="store-activity-heading">
                <div class="flex items-center justify-between gap-3"><h2 id="store-activity-heading">A little store activity</h2><svg class="size-5 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></div>
                <div class="seller-activity-list">
                    @if ($recentOrders->first())
                        @php($latestOrder = $recentOrders->first())
                        <a href="{{ route('seller.orders.show', $latestOrder) }}" class="seller-activity">
                            <span class="seller-activity-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 8h16l1 12H3L4 8Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/></svg></span>
                            <span><strong>Order {{ $latestOrder->order->number }}</strong><small>{{ ucfirst($latestOrder->status) }} &middot; {{ $latestOrder->order->recipient_name }}</small></span>
                            <time datetime="{{ $latestOrder->created_at->toIso8601String() }}">{{ $latestOrder->created_at->diffForHumans() }}</time>
                        </a>
                    @endif
                    @foreach ($recentProducts->take(2) as $activityProduct)
                        <a href="{{ $canManageProducts ? route('seller.products.edit', $activityProduct) : route('seller.products.index') }}" class="seller-activity">
                            <span class="seller-activity-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="m12 3 9 5v8l-9 5-9-5V8Z"/><path d="m3 8 9 5 9-5M12 13v8"/></svg></span>
                            <span><strong>{{ $activityProduct->name }}</strong><small>{{ \App\Models\Product::STATUSES[$activityProduct->status] }}</small></span>
                            <time datetime="{{ $activityProduct->updated_at->toIso8601String() }}">{{ $activityProduct->updated_at->diffForHumans() }}</time>
                        </a>
                    @endforeach
                    @if ($recentOrders->isEmpty() && $recentProducts->isEmpty())<p class="py-6 text-sm text-muted">Your store activity will appear as you add products and receive orders.</p>@endif
                </div>
                <a href="{{ route('seller.reviews.index') }}" class="seller-panel-link">Reply to customers <span aria-hidden="true">&rarr;</span></a>
            </section>
        </div>

        <div class="seller-overview-grid seller-overview-grid-secondary mt-4">
            <section class="seller-panel relative min-w-0 p-4 sm:p-5" data-dashboard-section="orders" aria-labelledby="recent-orders-heading">
                <details class="group" data-seller-responsive-panel open><summary class="flex cursor-pointer items-center gap-2 pr-32"><h2 id="recent-orders-heading">Recent orders</h2><svg class="size-4 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m5 7.5 5 5 5-5" /></svg></summary>
                @if ($recentOrders->isNotEmpty())
                    <div class="mt-3 hidden overflow-x-auto rounded border border-line sm:block"><table class="w-full min-w-[560px] text-left text-xs"><thead class="bg-slate-50 text-ink"><tr><th class="px-3 py-2">Order #</th><th class="px-3 py-2">Customer</th><th class="px-3 py-2">Items</th><th class="px-3 py-2">Total</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Date</th><th class="px-3 py-2">Action</th></tr></thead><tbody class="divide-y divide-line">@foreach ($recentOrders as $sellerOrder)<tr><td class="px-3 py-3 font-semibold">{{ $sellerOrder->order->number }}</td><td class="px-3 py-3">{{ $sellerOrder->order->recipient_name }}</td><td class="px-3 py-3">{{ $sellerOrder->items_count }}</td><td class="px-3 py-3 font-semibold tabular-nums">₱{{ number_format($sellerOrder->total, 2) }}</td><td class="px-3 py-3"><span class="inline-flex rounded-full px-2 py-1 font-semibold ring-1 ring-inset {{ $orderStyles[$sellerOrder->status] ?? 'bg-slate-100 text-slate-700 ring-line' }}">{{ ucfirst($sellerOrder->status) }}</span></td><td class="px-3 py-3">{{ $sellerOrder->created_at->format('M j') }}</td><td class="px-3 py-3"><a href="{{ route('seller.orders.show', $sellerOrder) }}" class="font-semibold text-brand">View order</a></td></tr>@endforeach</tbody></table></div>
                    <div class="mt-3 divide-y divide-line sm:hidden">@foreach ($recentOrders as $sellerOrder)<article class="py-3 text-sm"><div class="flex items-center justify-between gap-3"><strong>{{ $sellerOrder->order->number }}</strong><span class="font-semibold tabular-nums">₱{{ number_format($sellerOrder->total, 2) }}</span></div><p class="mt-1 text-xs text-muted">{{ $sellerOrder->order->recipient_name }} · {{ $sellerOrder->items_count }} {{ Str::plural('item', $sellerOrder->items_count) }} · {{ ucfirst($sellerOrder->status) }}</p><a href="{{ route('seller.orders.show', $sellerOrder) }}" class="mt-2 inline-flex text-xs font-semibold text-brand">View order</a></article>@endforeach</div>
                @else
                    <div class="seller-empty"><svg class="mx-auto size-10 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m12 2 9 5v10l-9 5-9-5V7l9-5Z"/><path d="m3 7 9 5 9-5M12 12v10"/></svg><p class="mt-1 font-semibold text-ink">No orders yet</p><p class="mt-1 text-xs text-muted">Orders from your store will appear here.</p><a href="{{ route('seller.orders.index') }}" class="seller-primary-button mt-2">View all orders</a></div>
                @endif
                </details><a href="{{ route('seller.orders.index') }}" data-seller-shortcut="orders" aria-keyshortcuts="O" class="seller-panel-link absolute right-4 top-5 sm:right-5">View all orders</a>
            </section>
            <section class="seller-panel p-4 sm:p-5" data-dashboard-section="setup" aria-labelledby="setup-heading">
                <div class="flex items-center justify-between gap-3"><h2 id="setup-heading">Store setup</h2><span class="text-xs text-muted">{{ $completedSetupCount }} of {{ $setupItems->count() }} complete</span></div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuenow="{{ $completedSetupCount }}" aria-valuemin="0" aria-valuemax="{{ $setupItems->count() }}" aria-label="Store setup progress"><div class="h-full rounded-full bg-blue-600" style="width: {{ $completedSetupCount * 100 / max(1, $setupItems->count()) }}%"></div></div>
                <ul class="mt-3 divide-y divide-line">
                    @foreach ($setupItems as $item)
                        <li class="flex min-h-10 items-center gap-3 py-1 text-sm">
                            <span @class(['grid size-6 shrink-0 place-items-center rounded-full text-sm', 'bg-emerald-500 text-white' => $item['complete'], 'border border-slate-300 text-slate-400' => ! $item['complete']]) aria-hidden="true">{{ $item['complete'] ? '✓' : '' }}</span>
                            <span class="min-w-0 flex-1"><span class="block font-medium text-ink">{{ $item['label'] }}</span><span class="block text-xs text-muted">{{ $item['complete'] ? 'Completed' : ($item['href'] ? $item['action'] : 'Ask a store owner or manager') }}</span></span>
                            @if (! $item['complete'] && $item['href'])<a href="{{ $item['href'] }}" class="text-brand focus-visible:ring-2 focus-visible:ring-brand" aria-label="{{ $item['action'] }}: {{ $item['label'] }}">›</a>@endif
                        </li>
                    @endforeach
                </ul>
            </section>
            <section class="seller-panel min-w-0 p-4 sm:p-5" data-dashboard-section="products" aria-labelledby="products-heading">
                <div class="flex items-center justify-between gap-3"><h2 id="products-heading">Recent products</h2><a href="{{ route('seller.products.index') }}" class="seller-panel-link">View all products</a></div>
                @if ($recentProducts->isNotEmpty())
                    <div class="mt-3 divide-y divide-line">@foreach ($recentProducts as $product)<div class="flex items-center gap-3 py-2.5"><div class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-lg bg-slate-100 text-slate-500">@if ($product->images->first())<img src="{{ Storage::disk('product_images')->url($product->images->first()->path) }}" alt="" class="size-full object-cover">@else{{ mb_strtoupper(mb_substr($product->name, 0, 1)) }}@endif</div><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold" title="{{ $product->name }}">{{ $product->name }}</p><p class="text-xs text-muted">{{ \App\Models\Product::STATUSES[$product->status] }} · {{ number_format($product->stock_quantity) }} in stock</p></div><span class="text-sm font-semibold tabular-nums">₱{{ number_format($product->price, 2) }}</span>@if ($canManageProducts)<a href="{{ route('seller.products.edit', $product) }}" class="text-xs font-semibold text-brand">Edit</a>@endif</div>@endforeach</div>
                @else
                    <div class="seller-empty"><svg class="mx-auto size-10 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m12 2 9 5v10l-9 5-9-5V7l9-5Z"/><path d="m3 7 9 5 9-5M12 12v10"/></svg><p class="mt-1 font-semibold text-ink">No products yet</p><p class="mt-1 text-xs text-muted">Add your first product to start selling on JMMart.</p>@if ($canManageProducts)<a href="{{ route('seller.products.create') }}" data-seller-shortcut="new-product" aria-keyshortcuts="N" class="seller-primary-button mt-2">Add product</a>@endif</div>
                @endif
            </section>
        </div>

        <div class="seller-overview-grid seller-overview-grid-footer mt-4">
            <details class="seller-panel p-4 sm:p-5" data-dashboard-section="help" data-seller-guide open>
                <summary class="cursor-pointer text-base font-semibold text-ink">Need a hand? <span class="text-sm font-normal text-muted">Search the seller guide</span></summary>
                <div class="mt-3 grid gap-4 border-t border-line pt-3 lg:grid-cols-[1fr_220px]">
                    <div><p class="text-sm font-semibold">Search the seller guide</p><p class="mt-1 text-xs text-muted">Find answers and shortcuts for running your store.</p><label for="seller-guide-search" class="sr-only">Search seller help</label><input id="seller-guide-search" type="search" autocomplete="off" data-seller-help-search aria-keyshortcuts="?" placeholder="Search seller help..." class="mt-3 block min-h-10 w-full rounded-lg border-line text-sm focus:border-brand focus:ring-brand"><p class="mt-2 text-[11px] text-muted">Shortcuts: S products · N add product · O orders · ? help</p></div>
                    <div><p class="text-xs font-semibold">Popular topics</p><ul class="mt-1 max-h-32 overflow-y-auto text-xs" data-seller-help-list>
                        <li data-seller-help-item class="seller-help-item"><a href="{{ route('seller.products.index') }}">Managing products</a></li>
                        <li data-seller-help-item class="seller-help-item"><a href="{{ route('seller.orders.index', ['status' => 'pending']) }}">Review new orders</a></li>
                        <li data-seller-help-item class="seller-help-item"><a href="{{ route('seller.reviews.index') }}">View reviews</a></li>
                        <li data-seller-help-item class="seller-help-item"><a href="{{ route('seller.products.index', ['stock' => 'out_of_stock']) }}">Review out-of-stock products</a></li>
                        <li data-seller-help-item class="seller-help-item"><a href="{{ route('seller.earnings.index') }}">Track earnings</a></li>
                        <li data-seller-help-item class="seller-help-item"><a href="{{ route('catalog.store', $store->slug) }}">Visit your storefront</a></li>
                        <li data-seller-help-item class="seller-help-item"><a href="{{ route('messages.index') }}">Reply to buyer messages</a></li>
                        @if ($canManageProducts)<li data-seller-help-item class="seller-help-item"><a href="{{ route('seller.store-settings.edit') }}">Update store details</a></li>@endif
                    </ul><p class="mt-2 hidden text-xs text-muted" data-seller-help-empty role="status">No help topics match that search.</p></div>
                </div>
            </details>
            <section class="seller-panel p-4 sm:p-5" aria-labelledby="quick-actions-heading"><h2 id="quick-actions-heading">Quick actions</h2><div class="mt-3 grid grid-cols-2 gap-2">
                @if ($canManageProducts)<a href="{{ route('seller.products.create') }}" data-seller-shortcut="new-product" aria-keyshortcuts="N" class="seller-action"><span class="seller-action-icon"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></span><span><strong>Add product</strong><small>List a new product</small></span></a>@endif
                <a href="{{ route('catalog.store', $store->slug) }}" class="seller-action"><span class="seller-action-icon"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 10v10h16V10M3 10l2-6h14l2 6M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/></svg></span><span><strong>View storefront</strong><small>Open your public store</small></span></a>
                @if ($canManageProducts)<a href="{{ route('seller.store-settings.edit') }}" class="seller-action"><span class="seller-action-icon"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 20 4.5-1 9.9-9.9a2.1 2.1 0 0 0-3-3L5.5 16 4 20Z"/><path d="m13.5 6.5 3 3"/></svg></span><span><strong>Update store info</strong><small>Edit store details</small></span></a>@endif
                <a href="{{ $canManageProducts ? route('seller.store-settings.edit') : route('seller.earnings.index') }}" class="seller-action"><span class="seller-action-icon"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span><span><strong>{{ $canManageProducts ? 'Store settings' : 'Earnings' }}</strong><small>{{ $canManageProducts ? 'Manage preferences' : 'Review payouts' }}</small></span></a>
            </div></section>
        </div>

        <details class="mt-4 rounded-xl border border-line bg-white px-4 py-3 text-sm"><summary class="cursor-pointer font-semibold">Customize overview</summary><fieldset class="mt-3 grid gap-2 border-t border-line pt-3 sm:grid-cols-2"><legend class="sr-only">Overview sections to show</legend>@foreach (['setup' => 'Store setup', 'help' => 'Seller guide', 'orders' => 'Recent orders', 'products' => 'Recent products'] as $section => $label)<label class="inline-flex min-h-10 items-center gap-2"><input type="checkbox" data-dashboard-toggle="{{ $section }}" checked class="rounded border-line text-brand focus:ring-brand">{{ $label }}</label>@endforeach<button type="button" data-dashboard-reset class="min-h-10 justify-self-start font-semibold text-brand underline underline-offset-4">Restore defaults</button></fieldset></details>
    </div></div>
</x-app-layout>
