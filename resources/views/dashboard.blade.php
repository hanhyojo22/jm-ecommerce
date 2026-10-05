<x-app-layout>
    @php
        $navItems = [
            ['label' => 'Overview', 'href' => route('dashboard'), 'icon' => 'overview', 'active' => $activeType === 'all'],
            ['label' => 'Seller applications', 'href' => route('admin.seller-applications.index'), 'icon' => 'application', 'active' => false],
            ['label' => 'Store appeals', 'href' => route('dashboard', ['type' => 'appeal']), 'icon' => 'store', 'active' => $activeType === 'appeal'],
            ['label' => 'Product reports', 'href' => route('dashboard', ['type' => 'product_report']), 'icon' => 'product', 'active' => $activeType === 'product_report'],
            ['label' => 'Chat reports', 'href' => route('dashboard', ['type' => 'chat_report']), 'icon' => 'chat', 'active' => $activeType === 'chat_report'],
            ['label' => 'Products', 'href' => route('admin.products.index'), 'icon' => 'box', 'active' => false],
            ['label' => 'Orders', 'href' => route('admin.orders.index'), 'icon' => 'orders', 'active' => false],
            ['label' => 'Audit log', 'href' => route('admin.audit-logs.index'), 'icon' => 'audit', 'active' => false],
        ];
        $queueTabs = [
            'all' => ['label' => 'All', 'count' => $counts['attention']],
            'application' => ['label' => 'Applications', 'count' => $counts['applications']],
            'appeal' => ['label' => 'Store appeals', 'count' => $counts['appeals']],
            'product_report' => ['label' => 'Product reviews', 'count' => $counts['productReports']],
            'chat_report' => ['label' => 'Chat reports', 'count' => $counts['chatReports']],
        ];
    @endphp

    <div class="min-h-[calc(100vh-72px)] bg-[#faf8f5] lg:grid lg:grid-cols-[minmax(196px,15.5vw)_minmax(0,1fr)]">
        <aside class="hidden border-r border-slate-200 bg-white lg:block" aria-label="Admin workspace navigation">
            <nav class="sticky top-[72px] space-y-1 px-3 py-5">
                @foreach ($navItems as $item)
                    <a href="{{ $item['href'] }}" @class([
                        'group flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2',
                        'bg-emerald-50 font-semibold text-emerald-950' => $item['active'],
                        'text-slate-700 hover:bg-slate-50 hover:text-slate-950' => ! $item['active'],
                    ]) {{ $item['active'] ? 'aria-current=page' : '' }}>
                        <svg class="size-5 shrink-0 {{ $item['active'] ? 'text-emerald-800' : 'text-slate-500 group-hover:text-slate-700' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            @switch($item['icon'])
                                @case('overview')<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z" />@break
                                @case('application')<path d="M8 3h7l4 4v14H5V3z" /><path d="M15 3v5h5M8 12h8M8 16h8" />@break
                                @case('store')<path d="M4 10v10h16V10M3 10l2-6h14l2 6M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0M9 20v-6h6v6" />@break
                                @case('product')<path d="m12 3 9 5-9 5-9-5 9-5Z" /><path d="m3 12 9 5 9-5M3 16l9 5 9-5" />@break
                                @case('chat')<path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H5l-2 2v-9.5A7.5 7.5 0 0 1 10.5 4h2A7.5 7.5 0 0 1 20 11.5Z" />@break
                                @case('box')<path d="m12 3 9 5v8l-9 5-9-5V8z" /><path d="m3 8 9 5 9-5M12 13v8M7.5 5.5l9 5" />@break
                                @case('orders')<path d="M3 4h2l2.5 12h10L21 8H6" /><circle cx="9" cy="20" r="1" /><circle cx="18" cy="20" r="1" />@break
                                @case('audit')<circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />@break
                            @endswitch
                        </svg>
                        <span class="min-w-0 truncate">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>
        </aside>

        <div class="min-w-0 px-4 pb-10 pt-6 sm:px-6 lg:px-7 lg:pt-8">
            <nav class="-mx-4 mb-6 flex gap-1 overflow-x-auto border-b border-slate-200 bg-white px-4 py-2.5 sm:-mx-6 sm:px-6 lg:hidden" aria-label="Admin workspace navigation">
                @foreach ($navItems as $item)
                    <a href="{{ $item['href'] }}" @class([
                        'min-h-10 shrink-0 rounded-lg px-3 py-2 text-sm font-semibold focus-visible:ring-2 focus-visible:ring-emerald-700',
                        'bg-emerald-50 text-emerald-900' => $item['active'],
                        'text-slate-600 hover:bg-slate-50 hover:text-slate-950' => ! $item['active'],
                    ]) {{ $item['active'] ? 'aria-current=page' : '' }}>{{ $item['label'] }}</a>
                @endforeach
            </nav>

            @if (session('status'))
                <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950" role="status">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">{{ session('error') }}</div>
            @endif

            <div class="grid items-start gap-7 xl:grid-cols-[minmax(0,1fr)_16.5rem] xl:gap-6">
                <section class="min-w-0" aria-labelledby="moderation-title">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 id="moderation-title" class="text-[2rem] font-semibold leading-tight tracking-[-0.025em] text-slate-950">Moderation</h1>
                            <p class="mt-1.5 text-sm text-slate-600">Review seller and marketplace activity.</p>
                        </div>
                        <p class="mt-2 inline-flex items-center gap-2 text-xs font-medium text-slate-500">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5" /><path d="M5.6 9a7 7 0 0 1 11.8-2L20 12M4 12l2.6 5a7 7 0 0 0 11.8-2" /></svg>
                            <span>Updated just now</span>
                        </p>
                    </div>

                    <div class="mt-6 flex min-h-[4.5rem] flex-col gap-3 rounded-xl bg-emerald-900 px-4 py-3.5 text-white sm:flex-row sm:items-center sm:justify-between sm:px-5" role="status">
                        <div class="flex items-center gap-3.5">
                            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-amber-300 text-emerald-950">
                                @if ($counts['attention'] > 0)
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m10.3 3.9-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-3.1l-8-14a2 2 0 0 0-3.4 0Z" /><path d="M12 9v4m0 4h.01" /></svg>
                                @else
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6" /></svg>
                                @endif
                            </span>
                            <p class="text-base font-semibold sm:text-lg">
                                {{ $counts['attention'] > 0 ? number_format($counts['attention']).' '.Str::plural('item', $counts['attention']).' need attention' : 'All caught up' }}
                            </p>
                        </div>
                        @if ($counts['attention'] > 0)
                            <a href="#review-queue" class="inline-flex min-h-10 items-center gap-2 self-start rounded-lg px-2 text-sm font-semibold text-white underline decoration-white/60 underline-offset-4 hover:decoration-white focus-visible:ring-2 focus-visible:ring-white sm:self-auto">Open review queue <span aria-hidden="true">â†’</span></a>
                        @endif
                    </div>

                    <nav class="mt-5 flex gap-1 overflow-x-auto border-b border-slate-200" aria-label="Moderation queue categories">
                        @foreach ($queueTabs as $key => $tab)
                            <?php $isCurrentTab = $activeType === $key; ?>
                            <a href="{{ $key === 'all' ? route('dashboard') : route('dashboard', ['type' => $key]) }}" @class([
                                'inline-flex min-h-11 shrink-0 items-center gap-2 border-b-2 px-2.5 text-sm font-semibold transition focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-700 sm:px-3',
                                'border-emerald-800 text-emerald-900' => $isCurrentTab,
                                'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-950' => ! $isCurrentTab,
                             ]) {{ $isCurrentTab ? 'aria-current=page' : '' }}>
                                {{ $tab['label'] }}
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs tabular-nums',
                                    'bg-emerald-100 text-emerald-900' => $isCurrentTab,
                                    'bg-slate-100 text-slate-600' => ! $isCurrentTab,
                                ])>{{ number_format($tab['count']) }}</span>
                            </a>
                        @endforeach
                    </nav>

                    <section id="review-queue" class="scroll-mt-24" aria-label="Moderation items needing review">
                        <div class="hidden grid-cols-[minmax(7.8rem,1.15fr)_minmax(8rem,1.4fr)_minmax(7rem,1.2fr)_minmax(6.5rem,1fr)_minmax(7rem,1.15fr)] gap-3 border-b border-slate-200 px-3 py-3 text-[0.65rem] font-semibold uppercase tracking-[0.1em] text-slate-500 md:grid">
                            <span>Type</span><span>Submitted by</span><span>Reason</span><span>Received</span><span>Next step</span>
                        </div>

                        <div role="list" aria-label="Moderation items needing review">
                            <div class="{{ $queueItems->isNotEmpty() ? 'hidden' : '' }} bg-white px-5 py-14 text-center">
                                <span class="mx-auto grid size-11 place-items-center rounded-full bg-emerald-50 text-emerald-800">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6" /></svg>
                                </span>
                                <h2 class="mt-4 text-base font-semibold text-slate-900">No items in this queue</h2>
                                <p class="mx-auto mt-1 max-w-sm text-sm leading-6 text-slate-600">There is nothing waiting for review in this category. Choose another queue to continue.</p>
                                <a href="{{ route('dashboard') }}" class="{{ $activeType === 'all' ? 'hidden' : '' }} mt-4 inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-semibold text-emerald-900 underline underline-offset-4 hover:text-emerald-700 focus-visible:ring-2 focus-visible:ring-emerald-700">View all moderation items</a>
                            </div>
                        <?php foreach ($queueItems as $item): ?>
                            @php
                                $type = match ($item['type']) {
                                    'application' => ['label' => 'Seller application', 'style' => 'bg-emerald-50 text-emerald-800'],
                                    'appeal' => ['label' => 'Store appeal', 'style' => 'bg-amber-50 text-amber-900'],
                                    'product_report' => ['label' => 'Product review', 'style' => 'bg-rose-50 text-rose-800'],
                                    'chat_report' => ['label' => 'Chat report', 'style' => 'bg-slate-100 text-slate-700'],
                                };
                                $receivedAt = $item['received_at'];
                                $receivedLabel = $receivedAt->isToday() ? 'Today, '.$receivedAt->format('g:i A') : ($receivedAt->isYesterday() ? 'Yesterday' : $receivedAt->format('M j'));
                            @endphp
                            <article role="listitem" class="grid gap-3 border-b border-slate-200 bg-white px-4 py-4 md:grid-cols-[minmax(7.8rem,1.15fr)_minmax(8rem,1.4fr)_minmax(7rem,1.2fr)_minmax(6.5rem,1fr)_minmax(7rem,1.15fr)] md:items-center md:gap-3 md:px-3 md:py-4">
                                <div class="flex items-center gap-2.5">
                                    <svg class="size-5 shrink-0 {{ $item['type'] === 'appeal' ? 'text-amber-700' : ($item['type'] === 'product_report' ? 'text-rose-700' : ($item['type'] === 'chat_report' ? 'text-slate-700' : 'text-emerald-800')) }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        @switch($item['type'])
                                            @case('application')<path d="M8 3h7l4 4v14H5V3z" /><path d="M15 3v5h5M8 12h8M8 16h6" />@break
                                            @case('appeal')<path d="M4 10v10h16V10M3 10l2-6h14l2 6M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0" />@break
                                            @case('product_report')<path d="m12 3 9 5-9 5-9-5 9-5Z" /><path d="m3 12 9 5 9-5M3 16l9 5 9-5" />@break
                                            @case('chat_report')<path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H5l-2 2v-9.5A7.5 7.5 0 0 1 10.5 4h2A7.5 7.5 0 0 1 20 11.5Z" />@break
                                        @endswitch
                                    </svg>
                                    <span class="inline-flex max-w-full rounded-full px-2.5 py-1 text-xs font-semibold {{ $type['style'] }}">{{ $type['label'] }}</span>
                                </div>

                                <div class="min-w-0 pl-[1.875rem] md:pl-0">
                                    <p class="mb-0.5 text-[0.65rem] font-semibold uppercase tracking-[0.08em] text-slate-500 md:hidden">Submitted by</p>
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $item['title'] }}</p>
                                    <p class="mt-0.5 truncate text-xs text-slate-500">{{ $item['subtitle'] }}</p>
                                </div>

                                <div class="min-w-0 pl-[1.875rem] md:pl-0">
                                    <p class="mb-0.5 text-[0.65rem] font-semibold uppercase tracking-[0.08em] text-slate-500 md:hidden">Reason</p>
                                    <p class="truncate text-sm text-slate-700">{{ $item['reason'] }}</p>
                                </div>

                                <div class="pl-[1.875rem] md:pl-0">
                                    <p class="mb-0.5 text-[0.65rem] font-semibold uppercase tracking-[0.08em] text-slate-500 md:hidden">Received</p>
                                    <time class="whitespace-nowrap text-sm text-slate-600" datetime="{{ $receivedAt->toIso8601String() }}" title="{{ $receivedAt->format('M j, Y g:i A') }}">{{ $receivedLabel }}</time>
                                </div>

                                <a href="{{ $item['url'] }}" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-emerald-900 px-3 text-sm font-semibold text-white transition hover:bg-emerald-800 focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2 md:justify-self-start">{{ $item['action'] }}</a>
                            </article>
                        <?php endforeach; ?>
                        </div>
                    </section>

                    @if ($queueItems->count() === 12)
                        <p class="mt-3 text-xs text-slate-500">Showing the 12 most recent items. Use a category to review a specific queue.</p>
                    @endif
                </section>

                <aside class="rounded-xl border border-slate-200 bg-white px-4 py-4 xl:mt-0" aria-labelledby="quick-checks-title">
                    <h2 id="quick-checks-title" class="text-lg font-semibold tracking-[-0.02em] text-slate-950">Quick checks</h2>
                    <div class="mt-2 divide-y divide-slate-200">
                        <a href="{{ route('admin.stores.index', ['status' => 'suspended']) }}" class="group flex min-h-[5.25rem] items-center gap-3 py-3 focus-visible:ring-2 focus-visible:ring-emerald-700">
                            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-rose-50 text-rose-800">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 10v10h16V10M3 10l2-6h14l2 6M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0" /></svg>
                            </span>
                            <span class="min-w-0 flex-1"><span class="block text-sm font-medium text-slate-700">Suspended stores</span><span class="mt-0.5 block text-xl font-semibold tabular-nums text-slate-950">{{ number_format($counts['suspendedStores']) }}</span></span>
                            <svg class="size-4 shrink-0 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg>
                        </a>

                        <a href="{{ route('admin.seller-applications.index', ['status' => 'pending']) }}" class="group flex min-h-[5.25rem] items-center gap-3 py-3 focus-visible:ring-2 focus-visible:ring-emerald-700">
                            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-emerald-50 text-emerald-800">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3h7l4 4v14H5V3z" /><path d="M15 3v5h5M8 12h8M8 16h8" /></svg>
                            </span>
                            <span class="min-w-0 flex-1"><span class="block text-sm font-medium text-slate-700">Pending applications</span><span class="mt-0.5 block text-xl font-semibold tabular-nums text-slate-950">{{ number_format($counts['applications']) }}</span></span>
                            <svg class="size-4 shrink-0 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg>
                        </a>

                        <a href="{{ route('admin.audit-logs.index') }}" class="group flex min-h-[5.25rem] items-center gap-3 py-3 focus-visible:ring-2 focus-visible:ring-emerald-700">
                            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-amber-50 text-amber-800">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
                            </span>
                            <span class="min-w-0 flex-1"><span class="block text-sm font-medium text-slate-700">Recent decisions</span><span class="mt-0.5 block text-xs leading-5 text-slate-500">{{ number_format($counts['recentDecisions']) }} {{ Str::plural('decision', $counts['recentDecisions']) }} in the past 7 days</span></span>
                            <svg class="size-4 shrink-0 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg>
                        </a>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
