<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="mt-1 marketplace-page-title">{{ ($filters['queue'] ?? null) === 'pending_appeals' ? 'Stores with pending appeals' : 'Stores overview' }}</h1>
            </div>
            <a href="#seller-stores" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-4 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Manage stores</a>
        </div>
    </x-slot>

    @php($statusStyles = ['active' => 'bg-success/10 text-success ring-success/20', 'inactive' => 'bg-canvas text-muted ring-line', 'suspended' => 'bg-danger/10 text-danger-text ring-danger/20'])
    @php($hasFilters = filled($filters['search'] ?? null) || filled($filters['status'] ?? null) || filled($filters['joined'] ?? null) || filled($filters['category_id'] ?? null) || filled($filters['sort'] ?? null))

    <div class="bg-canvas py-8 sm:py-10">
        <div class="mx-auto max-w-[1320px] px-[18px] sm:px-8">
            @if (session('status'))
                <div class="mb-5 rounded-lg border border-success/20 bg-success/10 px-4 py-3 text-sm font-medium text-success" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-5 rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">We could not apply those filters. {{ $errors->first() }}</div>
            @endif

            @if ($pendingReviewCount > 0)
                <div class="mb-5 flex flex-col gap-2 rounded-lg border border-brand/20 bg-surface px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between" role="status">
                    <p class="font-medium text-ink">{{ number_format($pendingReviewCount) }} {{ Str::plural('seller application', $pendingReviewCount) }} awaiting review</p>
                    <a href="{{ route('admin.seller-applications.index') }}" class="w-fit font-semibold text-brand underline decoration-brand/30 underline-offset-4 transition hover:text-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Review application</a>
                </div>
            @endif

            <section class="grid grid-cols-2 gap-3 xl:grid-cols-4" aria-label="Store summaries">
                @foreach ([
                    ['label' => 'Total stores', 'count' => $totalStores, 'valueClass' => 'text-ink'],
                    ['label' => 'Active', 'count' => $statusCounts['active'] ?? 0, 'valueClass' => 'text-success'],
                    ['label' => 'Inactive', 'count' => $statusCounts['inactive'] ?? 0, 'valueClass' => 'text-ink'],
                    ['label' => 'Suspended', 'count' => $statusCounts['suspended'] ?? 0, 'valueClass' => 'text-danger'],
                ] as $summary)
                    <div class="rounded-lg border border-line bg-surface px-5 py-4 ">
                        <p class="text-2xl font-semibold tabular-nums tracking-tight {{ $summary['valueClass'] }}">{{ number_format($summary['count']) }}</p>
                        <p class="mt-1 text-sm font-medium text-muted">{{ $summary['label'] }}</p>
                    </div>
                @endforeach
            </section>

            <section id="seller-stores" class="mt-9 scroll-mt-6" aria-labelledby="seller-stores-heading">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 id="seller-stores-heading" class="text-lg font-semibold tracking-tight text-ink">Seller stores</h2>
                        <p class="mt-1 text-sm text-muted">{{ number_format($totalStores) }} {{ Str::plural('store', $totalStores) }} on the marketplace</p>
                    </div>
                    <form method="GET" action="{{ route('admin.stores.index') }}" class="flex w-full flex-col gap-2 sm:flex-row lg:w-auto" role="search" aria-label="Search seller stores">
                        @foreach (['status', 'joined', 'category_id', 'sort'] as $filter)
                            @if (filled($filters[$filter] ?? null))
                                <input type="hidden" name="{{ $filter }}" value="{{ $filters[$filter] }}">
                            @endif
                        @endforeach
                        <label class="sr-only" for="search">Search stores</label>
                        <input id="search" name="search" value="{{ $filters['search'] ?? '' }}" type="search" placeholder="Search stores or sellers" class="block h-11 w-full rounded-lg border-line bg-surface text-sm text-ink placeholder:text-muted focus:border-brand focus:ring-brand sm:w-72">
                        @if ($hasFilters)
                            <a href="{{ route('admin.stores.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg px-3 text-sm font-semibold text-muted transition hover:bg-surface hover:text-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Reset</a>
                        @endif
                    </form>
                </div>

                <div class="mt-4 flex flex-col gap-3 border-y border-line py-3 sm:flex-row sm:items-center sm:justify-between">
                    <nav class="flex flex-wrap gap-x-1 border-b border-line sm:pb-0" aria-label="Store status filters">
                        @foreach (['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'] as $value => $label)
                            @php($selected = ($filters['status'] ?? '') === $value)
                            <a href="{{ route('admin.stores.index', [...request()->except('status', 'page'), ...($value ? ['status' => $value] : [])]) }}" @class([
                                'inline-flex min-h-11 shrink-0 items-center gap-2 border-b-2 px-3 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand',
                                'border-brand text-brand' => $selected,
                                'border-transparent text-muted hover:border-line hover:text-ink' => ! $selected,
                            ]) @if ($selected) aria-current="page" @endif>
                                {{ $label }} <span @class(['rounded-full px-2 py-0.5 text-xs tabular-nums', 'bg-brand/10 text-brand-hover' => $selected, 'bg-canvas text-muted' => ! $selected])>{{ number_format($value ? ($statusCounts[$value] ?? 0) : $totalStores) }}</span>
                            </a>
                        @endforeach
                    </nav>

                    <details class="relative shrink-0">
                        <summary class="flex min-h-11 cursor-pointer list-none items-center rounded-lg px-3 text-sm font-semibold text-muted transition hover:bg-surface hover:text-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">More filters <span class="ml-1.5 text-xs" aria-hidden="true">⌄</span></summary>
                        <form method="GET" action="{{ route('admin.stores.index') }}" class="absolute right-0 z-10 mt-2 w-72 rounded-lg border border-line bg-surface p-4 shadow-lg">
                            @if (filled($filters['search'] ?? null))
                                <input type="hidden" name="search" value="{{ $filters['search'] }}">
                            @endif
                            @if (filled($filters['status'] ?? null))
                                <input type="hidden" name="status" value="{{ $filters['status'] }}">
                            @endif
                            <div class="space-y-3">
                                <div>
                                    <label for="joined" class="mb-1.5 block text-sm font-medium text-ink">Date joined</label>
                                    <select id="joined" name="joined" class="block h-11 w-full rounded-lg border-line text-sm focus:border-brand focus:ring-brand">
                                        <option value="">Any time</option>
                                        <option value="last_30_days" @selected(($filters['joined'] ?? null) === 'last_30_days')>Last 30 days</option>
                                        <option value="last_90_days" @selected(($filters['joined'] ?? null) === 'last_90_days')>Last 90 days</option>
                                        <option value="this_year" @selected(($filters['joined'] ?? null) === 'this_year')>This year</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="category_id" class="mb-1.5 block text-sm font-medium text-ink">Product category</label>
                                    <select id="category_id" name="category_id" class="block h-11 w-full rounded-lg border-line text-sm focus:border-brand focus:ring-brand">
                                        <option value="">All categories</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}" @selected(($filters['category_id'] ?? null) === $category->id)>{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="sort" class="mb-1.5 block text-sm font-medium text-ink">Sort by</label>
                                    <select id="sort" name="sort" class="block h-11 w-full rounded-lg border-line text-sm focus:border-brand focus:ring-brand">
                                        <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Newest</option>
                                        <option value="name" @selected(($filters['sort'] ?? null) === 'name')>Store name</option>
                                        <option value="recently_active" @selected(($filters['sort'] ?? null) === 'recently_active')>Most recently active</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-brand px-4 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Apply filters</button>
                        </form>
                    </details>
                </div>

                <div class="mt-5 overflow-hidden rounded-lg border border-line bg-surface" aria-label="Seller stores">
                    <div class="hidden grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_6rem_6rem_7rem_auto] gap-4 border-b border-line bg-canvas px-5 py-3 text-xs font-semibold uppercase tracking-[0.1em] text-muted md:grid">
                        <span>Store</span><span>Owner</span><span>Products</span><span>Orders</span><span>Status</span><span>Next step</span>
                    </div>
                    @forelse ($stores as $store)
                        @php($owner = $store->tenant->members->firstWhere('role', 'owner')?->user ?? $store->tenant->members->first()?->user)
                        <article class="grid gap-3 border-b border-line px-4 py-4 last:border-0 sm:px-5 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_6rem_6rem_7rem_auto] md:items-center md:gap-4">
                            <div class="flex min-w-0 items-center gap-3">
                                @if ($store->logo_path)
                                    <img src="{{ Storage::disk('public')->url($store->logo_path) }}" alt="{{ $store->name }} logo" class="size-11 shrink-0 rounded-md border border-line object-cover">
                                @else
                                    <span class="grid size-11 shrink-0 place-items-center rounded-md bg-brand text-sm font-semibold text-white" aria-hidden="true">{{ mb_strtoupper(mb_substr($store->name, 0, 1)) }}</span>
                                @endif
                                <div class="min-w-0">
                                    <p class="mb-1 text-xs font-semibold uppercase tracking-[0.08em] text-muted md:hidden">Store</p>
                                    <a id="store-{{ $store->id }}-name" href="{{ route('admin.stores.show', $store) }}" title="{{ $store->name }}" class="block truncate text-sm font-semibold text-brand hover:text-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">{{ $store->name }}</a>
                                </div>
                            </div>
                            <div class="min-w-0">
                                <p class="mb-1 text-xs font-semibold uppercase tracking-[0.08em] text-muted md:hidden">Owner</p>
                                <p title="{{ $owner?->name ?? 'No owner on file' }}" class="truncate text-sm text-ink">{{ $owner?->name ?? 'No owner on file' }}</p>
                            </div>
                            <p class="flex items-baseline gap-2 text-sm font-semibold tabular-nums text-ink"><span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted md:hidden">Products</span>{{ number_format($store->products_count) }}</p>
                            <p class="flex items-baseline gap-2 text-sm font-semibold tabular-nums text-ink"><span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted md:hidden">Orders</span>{{ number_format($store->seller_orders_count) }}</p>
                            <div class="flex items-center justify-between gap-3 md:block">
                                <p class="mb-1 text-xs font-semibold uppercase tracking-[0.08em] text-muted md:hidden">Status</p>
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusStyles[$store->status] }}">{{ ucfirst($store->status) }}</span>
                            </div>
                            <a href="{{ route('admin.stores.show', $store) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-3 text-sm font-semibold text-white transition hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 md:justify-self-start">View store</a>
                        </article>
                    @empty
                        <div class="px-6 py-14 text-center">
                            <p class="font-semibold text-ink">{{ $hasFilters ? 'No stores match these filters.' : 'No seller stores yet.' }}</p>
                            <p class="mt-2 text-sm leading-6 text-muted">{{ $hasFilters ? 'Try widening the search or clearing a filter.' : 'Approved sellers will appear here with their product and order counts.' }}</p>
                            @if ($hasFilters)<a href="{{ route('admin.stores.index') }}" class="mt-5 inline-flex min-h-11 items-center rounded-lg bg-brand px-4 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Clear filters</a>@endif
                        </div>
                    @endforelse
                </div>

                @if ($stores->hasPages())
                    <div class="mt-6 rounded-lg border border-line bg-surface px-4 py-4 ">{{ $stores->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
