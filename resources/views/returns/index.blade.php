<x-app-layout>
    @php
        $isSeller = $role === 'seller';
        $routeName = $isSeller ? 'seller.returns.index' : 'admin.returns.index';
        $showRoute = $isSeller ? 'seller.returns.show' : 'admin.returns.show';

        $statusOptions = $isSeller
            ? [
                '' => 'All cases',
                'needs_action' => 'Needs my action',
                'awaiting_seller' => 'Awaiting my response',
                'awaiting_receipt' => 'Returns arriving',
                'awaiting_fulfillment' => 'Remedies due',
                'awaiting_buyer' => 'Waiting on buyer',
                'with_support' => 'With JMMart',
                'closed' => 'Closed',
            ]
            : [
                '' => 'All cases',
                'needs_action' => 'Needs review',
                'appealed' => 'Appeals to decide',
                'open' => 'In progress',
                'closed' => 'Closed',
            ];

        $kpis = $isSeller
            ? [
                ['label' => 'Awaiting my response', 'count' => $counts['awaiting_seller'], 'hint' => 'Reply to the buyer within 48 hours.', 'href' => route($routeName, ['status' => 'awaiting_seller']), 'active' => $filters['status'] === 'awaiting_seller', 'icon' => 'chat'],
                ['label' => 'Returns arriving', 'count' => $counts['awaiting_receipt'], 'hint' => 'Inspect the parcel and confirm receipt.', 'href' => route($routeName, ['status' => 'awaiting_receipt']), 'active' => $filters['status'] === 'awaiting_receipt', 'icon' => 'package'],
                ['label' => 'Remedies due', 'count' => $counts['awaiting_fulfillment'], 'hint' => 'Refund or ship within 5 business days.', 'href' => route($routeName, ['status' => 'awaiting_fulfillment']), 'active' => $filters['status'] === 'awaiting_fulfillment', 'icon' => 'refund'],
                ['label' => 'Overdue', 'count' => $counts['overdue'], 'hint' => 'JMMart can step in at any time.', 'href' => route($routeName, ['overdue' => 1]), 'active' => $filters['overdue'], 'danger' => true, 'icon' => 'alert'],
            ]
            : [
                ['label' => 'Needs review', 'count' => $counts['needs_review'], 'hint' => 'Appeals and stalled cases.', 'href' => route($routeName, ['needs_action' => 1]), 'active' => $filters['needs_action'], 'danger' => $counts['needs_review'] > 0, 'icon' => 'shield'],
                ['label' => 'Appeals to decide', 'count' => $counts['appealed'], 'hint' => 'Waiting on a support decision.', 'href' => route($routeName, ['status' => 'appealed']), 'active' => $filters['status'] === 'appealed', 'icon' => 'scale'],
                ['label' => 'Overdue', 'count' => $counts['overdue'], 'hint' => 'Deadlines that already passed.', 'href' => route($routeName, ['overdue' => 1]), 'active' => $filters['overdue'], 'danger' => true, 'icon' => 'alert'],
                ['label' => 'Open cases', 'count' => $counts['open'], 'hint' => 'Still moving through the workflow.', 'href' => route($routeName, ['status' => 'open']), 'active' => $filters['status'] === 'open', 'icon' => 'return'],
            ];

        $hasFilters = $filters['search'] !== null || $filters['status'] !== '' || $filters['overdue'] || $filters['needs_action'];
    @endphp

    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="font-display text-[30px] font-semibold leading-[1.15] tracking-[-0.03em] text-ink">{{ $isSeller ? 'Returns' : 'Return cases' }}</h1>
                <p class="mt-2 text-sm text-muted">{{ $needsActionCount }} {{ Str::plural('case', $needsActionCount) }} {{ $needsActionCount === 1 ? 'needs' : 'need' }} {{ $isSeller ? 'seller action' : 'support review' }} · {{ $cases->total() }} {{ Str::plural('case', $cases->total()) }} in view</p>
            </div>
            <a href="{{ route('returns') }}" class="inline-flex min-h-11 items-center gap-1.5 text-sm font-semibold text-brand hover:text-brand-hover">
                <x-returns.icon name="shield" class="size-4" />
                Return policy
            </a>
        </div>
    </x-slot>

    <div class="page-shell"><div class="page-container">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Case summary">
            @foreach ($kpis as $kpi)
                <a href="{{ $kpi['href'] }}" aria-label="{{ $kpi['label'] }}: {{ $kpi['count'] }} {{ Str::plural('case', $kpi['count']) }}" @class([
                    'returns-kpi group focus:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2',
                    'is-active' => $kpi['active'],
                    'is-danger' => $kpi['danger'] ?? false,
                ])>
                    <span class="returns-kpi-icon"><x-returns.icon :name="$kpi['icon']" class="size-[18px]" /></span>
                    <span class="returns-kpi-count">{{ $kpi['count'] }}</span>
                    <span class="returns-kpi-label">{{ $kpi['label'] }}</span>
                    <x-returns.icon name="chevron-right" class="returns-kpi-arrow hidden size-4 sm:block" />
                    <span class="returns-kpi-hint hidden sm:block">{{ $kpi['hint'] }}</span>
                </a>
            @endforeach
        </div>

        <section class="mt-6 rounded-[16px] border border-line bg-surface p-4 sm:p-5" aria-label="Case filters">
            <form method="GET" action="{{ route($routeName) }}" class="grid gap-3 sm:grid-cols-2 sm:items-end lg:grid-cols-12">
                <div class="sm:col-span-2 lg:col-span-5">
                    <label for="search" class="field-label">Search cases</label>
                    <div class="relative">
                        <x-returns.icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-muted" />
                        <input id="search" name="search" value="{{ $filters['search'] }}" type="search" placeholder="{{ $isSeller ? 'Order number or buyer' : 'Order number, buyer, or store' }}" class="field-control h-11 pl-10">
                    </div>
                </div>
                <div class="lg:col-span-3">
                    <label for="status" class="field-label">Case status</label>
                    <select id="status" name="status" class="field-control h-11">
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lg:col-span-2">
                    <label class="flex min-h-11 cursor-pointer items-center gap-2.5 text-sm font-medium text-muted">
                        <input type="checkbox" name="overdue" value="1" @checked($filters['overdue']) class="rounded border-line text-brand focus:ring-brand">
                        Overdue only
                    </label>
                </div>
                <div class="flex items-center gap-3 sm:col-span-2 lg:col-span-2">
                    <button type="submit" class="button-primary flex-1">Apply</button>
                    @if ($hasFilters)
                        <a href="{{ route($routeName) }}" class="button-quiet px-2 text-muted">Clear</a>
                    @endif
                </div>
            </form>
        </section>

        <section class="mt-6" aria-label="Return cases">
            <div class="content-card">
                @forelse ($cases as $case)
                    @php
                        $amount = $case->items->reduce(fn (string $sum, $item): string => bcadd($sum, bcmul((string) $item->unit_price, (string) $item->quantity, 2), 2), '0.00');
                        $previewItems = $case->items->take(2);
                        $remainingItems = $case->items->count() - $previewItems->count();
                    @endphp
                    <a href="{{ route($showRoute, $case) }}" data-status="{{ $case->status }}" class="returns-queue-card group focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand">
                        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between sm:gap-x-4">
                            <div class="min-w-0 flex-1">
                                <p class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                    <span class="font-display text-base font-semibold text-ink">{{ $case->sellerOrder->order->number }}</span>
                                    <span class="text-sm text-muted">{{ $case->sellerOrder->store->name }}</span>
                                </p>
                                <p class="mt-1 text-sm text-muted">
                                    {{ ucfirst(str_replace('_', ' ', $case->reason)) }} requested by {{ $case->buyer->name }} · opened <time datetime="{{ $case->created_at->toIso8601String() }}">{{ $case->created_at->format('M j, Y') }}</time>
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-wrap items-center gap-2 sm:flex-col sm:items-end">
                                <x-returns.status-badge :status="$case->status" />
                                <x-returns.deadline :case="$case" />
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-end justify-between gap-x-4 gap-y-2">
                            <div class="min-w-0">
                                @if ($previewItems->isNotEmpty())
                                    <div class="returns-queue-items">
                                        @foreach ($previewItems as $item)
                                            <span class="returns-queue-item">{{ $item->orderItem?->product_name ?? 'Item' }}</span>
                                        @endforeach
                                        @if ($remainingItems > 0)
                                            <span class="returns-queue-item">+{{ $remainingItems }} more</span>
                                        @endif
                                    </div>
                                @endif
                                <p class="mt-2 text-sm text-ink">
                                    {{ $case->items->sum('quantity') }} {{ Str::plural('unit', $case->items->sum('quantity')) }} · <span class="font-display text-base font-semibold tabular-nums">₱{{ number_format((float) $amount, 2) }}</span> at stake
                                </p>
                            </div>
                            <span class="flex items-center gap-1.5 text-xs font-semibold text-brand">
                                Open case
                                <x-returns.icon name="chevron-right" class="returns-queue-arrow size-4" />
                            </span>
                        </div>
                    </a>
                @empty
                    <div class="empty-state">
                        <span class="mx-auto grid size-12 place-items-center rounded-full bg-blue-50 text-brand-hover" aria-hidden="true">
                            <x-returns.icon name="check" class="size-6" />
                        </span>
                        @if ($hasFilters)
                            <h2 class="mt-4 font-display text-xl font-semibold text-ink">No cases match these filters</h2>
                            <p class="mx-auto mt-1 max-w-md text-sm text-muted">Try a different status or clear the filters to see every case.</p>
                            <a href="{{ route($routeName) }}" class="button-secondary mt-4">Clear filters</a>
                        @else
                            <h2 class="mt-4 font-display text-xl font-semibold text-ink">{{ $isSeller ? 'No return cases yet' : 'Nothing to review' }}</h2>
                            <p class="mx-auto mt-1 max-w-md text-sm text-muted">{{ $isSeller ? 'When a buyer opens a return request, it lands here and JMMart emails you.' : 'Appeals and overdue cases land here. New cases appear as buyers and sellers work through them.' }}</p>
                        @endif
                    </div>
                @endforelse
            </div>
            <div class="mt-5">{{ $cases->links() }}</div>
        </section>
    </div></div>
</x-app-layout>
