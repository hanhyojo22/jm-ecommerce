@php
    $setupItems = collect($setupItems);
    $incompleteSetupItems = $setupItems->reject(fn (array $item): bool => $item['complete']);
    $nextSetupItem = $incompleteSetupItems->first(fn (array $item): bool => filled($item['href']));
    $completedSetupCount = $setupItems->count() - $incompleteSetupItems->count();
    $chartPeak = max(1, (float) $salesHistory->max('amount'));
    $lastThirtyDaysSales = $salesHistory->reduce(fn (string $total, array $day): string => bcadd($total, (string) $day['amount'], 2), '0.00');
    $storeTone = match ($store->status) { 'active' => 'success', 'suspended' => 'danger', default => 'neutral' };
    $attentionItems = array_filter([
        $pendingOrderCount > 0 ? ['count' => number_format($pendingOrderCount), 'text' => Str::plural('order', $pendingOrderCount).' awaiting confirmation', 'cta' => 'Review orders', 'href' => route('seller.orders.index', ['status' => 'pending']), 'tone' => ''] : null,
        $outOfStockCount > 0 ? ['count' => number_format($outOfStockCount), 'text' => Str::plural('product', $outOfStockCount).' out of stock', 'cta' => 'Review products', 'href' => route('seller.products.index', ['stock' => 'out_of_stock']), 'tone' => ''] : null,
        $unpaidFeeStatement ? ['count' => '₱', 'text' => ($unpaidFeeStatement->isOverdue() ? 'Overdue: ' : '').'₱'.number_format((float) $unpaidFeeStatement->amount_due, 2).' platform fee for '.$unpaidFeeStatement->period_start->format('F').', due '.$unpaidFeeStatement->due_at->format('M j'), 'cta' => 'Pay fee', 'href' => route('seller.platform-fees.index').'#statement-'.$unpaidFeeStatement->id, 'tone' => ''] : null,
        $needsFeeTerms ? ['count' => 'New', 'text' => 'Review and accept the Marketplace Fee Policy', 'cta' => 'Review terms', 'href' => route('seller.platform-fees.index'), 'tone' => 'is-info'] : null,
    ]);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="sc-page-head">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="sc-title">Overview</h1>
                    <span class="sc-badge" data-tone="{{ $storeTone }}">{{ ucfirst($store->status) }} store</span>
                </div>            </div>
            <div class="sc-page-actions">
                <a href="{{ route('catalog.store', $store->slug) }}" target="_blank" rel="noopener noreferrer" class="seller-outline-button" aria-label="View {{ $store->name }} storefront in a new tab">View storefront <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9"/><path d="M18 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5"/></svg></a>
                @if ($canManageProducts)<a href="{{ $nextSetupItem['href'] ?? route('seller.products.create') }}" class="seller-primary-button">{{ $nextSetupItem && $nextSetupItem['label'] !== 'First product' ? 'Finish store setup' : 'Add product' }}</a>@endif
            </div>
        </div>
    </x-slot>

    <div class="page-shell" data-seller-dashboard data-dashboard-key="{{ $store->id }}"><div class="page-container sc-stack">
        @if (session('status'))<div class="sc-notice" data-tone="success" role="status">{{ session('status') }}</div>@endif

        @if ($attentionItems)
            <section class="sc-card" aria-labelledby="action-required-heading">
                <div class="sc-card-head"><h2 id="action-required-heading" class="sc-card-title">Needs your attention</h2></div>
                <div class="sc-tasks">
                    @foreach ($attentionItems as $task)
                        <a href="{{ $task['href'] }}" class="sc-task"><span class="sc-task-count {{ $task['tone'] }}">{{ $task['count'] }}</span><span class="min-w-0">{{ $task['text'] }}</span><span class="sc-task-cta">{{ $task['cta'] }} <span aria-hidden="true">&rarr;</span></span></a>
                    @endforeach
                </div>
            </section>
        @endif

        <section aria-label="Store summary">
            <dl class="sc-metrics" data-columns="4">
                <div class="sc-metric"><dt>Sales total</dt><dd><strong class="sc-metric-value">₱{{ number_format($salesTotal, 2) }}</strong><span class="sc-metric-context">All time · seller order totals; cancelled orders excluded</span></dd></div>
                <div class="sc-metric"><dt>Orders</dt><dd><strong class="sc-metric-value">{{ number_format($totalOrders) }}</strong><span class="sc-metric-context">All order statuses, including cancelled</span></dd></div>
                <div class="sc-metric"><dt>Products</dt><dd><strong class="sc-metric-value">{{ number_format($totalProducts) }}</strong><span class="sc-metric-context">{{ number_format($activeProducts) }} active · {{ number_format($totalProducts - $activeProducts) }} other listings</span></dd></div>
                <div class="sc-metric"><dt>Unique buyers</dt><dd><strong class="sc-metric-value">{{ number_format($customerCount) }}</strong><span class="sc-metric-context">Lifetime buyers across non-cancelled orders</span></dd></div>
            </dl>
        </section>

        <div class="sc-grid-main">
            <div class="sc-stack">
                <section class="sc-card sc-card-pad" aria-labelledby="sales-overview-heading">
                    <div class="flex flex-wrap items-baseline justify-between gap-2"><h2 id="sales-overview-heading" class="sc-card-title">Sales overview</h2><span class="sc-meta">Last 30 days</span></div>
                    <p class="mt-1 text-2xl font-semibold tabular-nums tracking-tight text-ink">₱{{ number_format($lastThirtyDaysSales, 2) }}</p>
                    <div class="seller-sales-chart" role="img" aria-label="Sales trend for the last 30 days. Daily store sales in pesos; total {{ number_format($lastThirtyDaysSales, 2) }} pesos.">
                        @foreach ($salesHistory as $day)
                            <div class="seller-sales-day" title="{{ \Carbon\Carbon::parse($day['date'])->format('M j') }}: ₱{{ number_format($day['amount'], 2) }}">
                                <span class="seller-sales-bar" style="height: {{ (float) $day['amount'] > 0 ? max(2, (float) $day['amount'] / $chartPeak * 100) : 0 }}%"><span class="sr-only">{{ $day['date'] }}: {{ number_format($day['amount'], 2) }} pesos</span></span>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-2 flex justify-between text-xs text-muted"><span>{{ \Carbon\Carbon::parse($salesHistory->first()['date'])->format('M j') }}</span><span>{{ \Carbon\Carbon::parse($salesHistory[15]['date'])->format('M j') }}</span><span>{{ \Carbon\Carbon::parse($salesHistory->last()['date'])->format('M j') }}</span></div>
                    @if (bccomp($lastThirtyDaysSales, '0.00', 2) === 0)<p class="mt-3 sc-meta">No sales recorded in the last 30 days.</p>@endif
                </section>

                <section class="sc-card" data-dashboard-section="orders" aria-labelledby="recent-orders-heading">
                    <div class="sc-card-head"><h2 id="recent-orders-heading" class="sc-card-title">Recent orders</h2><a href="{{ route('seller.orders.index') }}" data-seller-shortcut="orders" aria-keyshortcuts="O" class="sc-link">View all orders</a></div>
                    @if ($recentOrders->isNotEmpty())
                        <div class="sc-table-wrap hidden sm:block">
                            <table class="sc-table min-w-[560px]">
                                <thead><tr><th scope="col">Order</th><th scope="col">Customer</th><th scope="col" class="is-num">Items</th><th scope="col" class="is-num">Total</th><th scope="col">Status</th><th scope="col">Date</th></tr></thead>
                                <tbody>
                                    @foreach ($recentOrders as $sellerOrder)
                                        <tr><th scope="row"><a href="{{ route('seller.orders.show', $sellerOrder) }}" class="font-semibold text-ink hover:text-brand"><x-marketplace.order-number :number="$sellerOrder->order->number" /></a></th><td>{{ $sellerOrder->order->recipient_name }}</td><td class="is-num">{{ $sellerOrder->items_count }}</td><td class="is-num font-semibold">₱{{ number_format($sellerOrder->total, 2) }}</td><td><x-marketplace.order-status :status="$sellerOrder->status" /></td><td class="is-muted whitespace-nowrap">{{ $sellerOrder->created_at->format('M j') }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="sc-list sm:hidden">
                            @foreach ($recentOrders as $sellerOrder)
                                <a href="{{ route('seller.orders.show', $sellerOrder) }}" class="sc-list-item"><span class="sc-row"><span class="sc-grow sc-strong"><x-marketplace.order-number :number="$sellerOrder->order->number" /></span><span class="sc-strong sc-num">₱{{ number_format($sellerOrder->total, 2) }}</span></span><span class="mt-1 sc-row"><span class="sc-grow sc-meta truncate">{{ $sellerOrder->order->recipient_name }} · {{ $sellerOrder->items_count }} {{ Str::plural('item', $sellerOrder->items_count) }}</span><x-marketplace.order-status :status="$sellerOrder->status" /></span></a>
                            @endforeach
                        </div>
                    @else
                        <div class="sc-empty"><h3>No orders yet</h3><p>Orders from your store will appear here as soon as a buyer checks out.</p></div>
                    @endif
                </section>
            </div>

            <div class="sc-stack">
                <section class="sc-card sc-card-pad" data-dashboard-section="setup" aria-labelledby="setup-heading">
                    <div class="flex items-baseline justify-between gap-3"><h2 id="setup-heading" class="sc-card-title">Store setup</h2><span class="sc-meta">{{ $completedSetupCount }} of {{ $setupItems->count() }} complete</span></div>
                    <div class="sc-progress mt-3" role="progressbar" aria-valuenow="{{ $completedSetupCount }}" aria-valuemin="0" aria-valuemax="{{ $setupItems->count() }}" aria-label="Store setup progress"><span style="width: {{ $completedSetupCount * 100 / max(1, $setupItems->count()) }}%"></span></div>
                    <ul class="mt-3">
                        @foreach ($setupItems as $item)
                            <li class="flex min-h-11 items-center gap-3 py-1 text-sm">
                                <span @class(['sc-check', 'is-done' => $item['complete']]) aria-hidden="true">@if ($item['complete'])<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m5 12 5 5 9-10"/></svg>@endif</span>
                                <span @class(['min-w-0 flex-1', 'text-muted' => $item['complete'], 'font-medium text-ink' => ! $item['complete']])>{{ $item['label'] }}@unless ($item['complete'] || $item['href'])<span class="block text-xs font-normal text-muted no-underline">Ask a store owner or manager</span>@endunless</span>
                                @if (! $item['complete'] && $item['href'])<a href="{{ $item['href'] }}" class="sc-link" aria-label="{{ $item['action'] }}: {{ $item['label'] }}">{{ $item['action'] }}</a>@endif
                            </li>
                        @endforeach
                    </ul>
                </section>

                <section class="sc-card" aria-labelledby="quick-actions-heading">
                    <div class="sc-card-head"><h2 id="quick-actions-heading" class="sc-card-title">Quick actions</h2></div>
                    <div class="sc-actions-list">
                        @if ($canManageProducts)<a href="{{ route('seller.products.create') }}" data-seller-shortcut="new-product" aria-keyshortcuts="N" class="sc-action"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Add product<small>N</small></a>@endif
                        <a href="{{ route('seller.orders.index', ['status' => 'pending']) }}" class="sc-action"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 4h2l2.5 12h10L21 8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>Review new orders</a>
                        <a href="{{ route('seller.reviews.index') }}" class="sc-action"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 15a4 4 0 0 1-4 4H8l-4 3V7a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4z"/></svg>Reply to customers</a>
                        @if ($canManageProducts)
                            <a href="{{ route('seller.store-settings.edit') }}" class="sc-action"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 20 4.5-1 9.9-9.9a2.1 2.1 0 0 0-3-3L5.5 16 4 20Z"/><path d="m13.5 6.5 3 3"/></svg>Update store info</a>
                        @else
                            <a href="{{ route('seller.earnings.index') }}" class="sc-action"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3"/></svg>Review earnings</a>
                        @endif
                    </div>
                </section>

                <section class="sc-card" data-dashboard-section="products" aria-labelledby="products-heading">
                    <div class="sc-card-head"><h2 id="products-heading" class="sc-card-title">Recent products</h2><a href="{{ route('seller.products.index') }}" class="sc-link">View all</a></div>
                    @if ($recentProducts->isNotEmpty())
                        <div class="sc-list">
                            @foreach ($recentProducts as $product)
                                <div class="sc-list-item sc-row !py-3">
                                    <span class="sc-thumb">@if ($product->images->first())<img src="{{ Storage::disk('product_images')->url($product->images->first()->path) }}" alt="" loading="lazy">@endif</span>
                                    <span class="sc-grow"><a href="{{ $canManageProducts ? route('seller.products.edit', $product) : route('seller.products.show', $product) }}" class="block truncate text-sm font-semibold text-ink hover:text-brand" title="{{ $product->name }}">{{ $product->name }}</a><span class="block sc-meta">{{ \App\Models\Product::STATUSES[$product->status] }} · {{ $product->stock_quantity < 1 ? 'Sold out' : number_format($product->stock_quantity).' in stock' }}</span></span>
                                    <span class="sc-num text-sm font-semibold">₱{{ number_format($product->price, 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="sc-empty"><h3>No products yet</h3><p>Add your first product to start selling on JMMart.</p>@if ($canManageProducts)<a href="{{ route('seller.products.create') }}" data-seller-shortcut="new-product" aria-keyshortcuts="N" class="seller-primary-button">Add product</a>@endif</div>
                    @endif
                </section>
            </div>
        </div>

        <details class="sc-card group" data-dashboard-section="help" data-seller-guide>
            <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-3 px-5 [&::-webkit-details-marker]:hidden"><span><span class="sc-card-title">Seller guide</span> <span class="sc-meta">· Search seller help and shortcuts</span></span><svg class="size-4 shrink-0 text-muted transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m5 7.5 5 5 5-5"/></svg></summary>
            <div class="grid gap-6 border-t border-line px-5 py-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <div>
                    <label for="seller-guide-search" class="text-sm font-semibold text-ink">Search seller help</label>
                    <input id="seller-guide-search" type="search" autocomplete="off" data-seller-help-search aria-keyshortcuts="?" placeholder="Try “orders” or “stock”" class="sc-input mt-2">
                    <p class="mt-3 sc-meta">Shortcuts: S products · N add product · O orders · ? help</p>
                </div>
                <div>
                    <p class="text-sm font-semibold text-ink">Popular topics</p>
                    <ul class="mt-1" data-seller-help-list>
                        <li data-seller-help-item class="seller-help-item"><a href="{{ route('seller.products.index') }}">Managing products</a></li>
                        <li data-seller-help-item class="seller-help-item"><a href="{{ route('seller.orders.index', ['status' => 'pending']) }}">Review new orders</a></li>
                        <li data-seller-help-item class="seller-help-item"><a href="{{ route('seller.reviews.index') }}">View reviews</a></li>
                        <li data-seller-help-item class="seller-help-item"><a href="{{ route('seller.products.index', ['stock' => 'out_of_stock']) }}">Review out-of-stock products</a></li>
                        <li data-seller-help-item class="seller-help-item"><a href="{{ route('seller.earnings.index') }}">Track earnings</a></li>
                        <li data-seller-help-item class="seller-help-item"><a href="{{ route('catalog.store', $store->slug) }}">Visit your storefront</a></li>
                        <li data-seller-help-item class="seller-help-item"><a href="{{ route('messages.index') }}">Reply to buyer messages</a></li>
                        @if ($canManageProducts)<li data-seller-help-item class="seller-help-item"><a href="{{ route('seller.store-settings.edit') }}">Update store details</a></li>@endif
                    </ul>
                    <p class="mt-2 hidden sc-meta" data-seller-help-empty role="status">No help topics match that search.</p>
                </div>
            </div>
        </details>

        <details class="text-sm">
            <summary class="inline-flex min-h-11 cursor-pointer items-center font-semibold text-muted hover:text-ink">Customize overview</summary>
            <fieldset class="sc-card sc-card-pad mt-2 grid gap-2 sm:grid-cols-2"><legend class="sr-only">Overview sections to show</legend>@foreach (['setup' => 'Store setup', 'help' => 'Seller guide', 'orders' => 'Recent orders', 'products' => 'Recent products'] as $section => $label)<label class="inline-flex min-h-10 items-center gap-2"><input type="checkbox" data-dashboard-toggle="{{ $section }}" checked class="rounded-md border-line text-brand focus:ring-brand">{{ $label }}</label>@endforeach<button type="button" data-dashboard-reset class="min-h-10 justify-self-start font-semibold text-brand underline underline-offset-4">Restore defaults</button></fieldset>
        </details>
    </div></div>
</x-app-layout>
