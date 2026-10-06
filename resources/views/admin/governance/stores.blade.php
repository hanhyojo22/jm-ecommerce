<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-emerald-800">Marketplace administration</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ ($filters['queue'] ?? null) === 'pending_appeals' ? 'Stores with pending appeals' : 'Stores overview' }}</h1>
            </div>
            <a href="#seller-stores" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">Manage stores</a>
        </div>
    </x-slot>

    @php($statusStyles = ['active' => 'bg-emerald-50 text-emerald-800 ring-emerald-200', 'inactive' => 'bg-slate-100 text-slate-700 ring-slate-200', 'suspended' => 'bg-rose-50 text-rose-800 ring-rose-200'])
    @php($hasFilters = filled($filters['search'] ?? null) || filled($filters['status'] ?? null) || filled($filters['joined'] ?? null) || filled($filters['category_id'] ?? null) || filled($filters['sort'] ?? null))

    <div class="bg-[#faf8f5] py-8 sm:py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">We could not apply those filters. {{ $errors->first() }}</div>
            @endif

            @if ($pendingReviewCount > 0)
                <div class="mb-5 flex flex-col gap-2 rounded-xl border border-emerald-100 bg-white px-4 py-3 text-sm shadow-sm sm:flex-row sm:items-center sm:justify-between" role="status">
                    <p class="font-medium text-slate-800">{{ number_format($pendingReviewCount) }} {{ Str::plural('seller application', $pendingReviewCount) }} awaiting review</p>
                    <a href="{{ route('admin.seller-applications.index') }}" class="w-fit font-semibold text-emerald-800 underline decoration-emerald-300 underline-offset-4 transition hover:text-emerald-950 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">Review application</a>
                </div>
            @endif

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Store summaries">
                @foreach ([
                    ['label' => 'Total stores', 'count' => $totalStores, 'valueClass' => 'text-slate-950'],
                    ['label' => 'Active', 'count' => $statusCounts['active'] ?? 0, 'valueClass' => 'text-emerald-800'],
                    ['label' => 'Inactive', 'count' => $statusCounts['inactive'] ?? 0, 'valueClass' => 'text-slate-700'],
                    ['label' => 'Suspended', 'count' => $statusCounts['suspended'] ?? 0, 'valueClass' => 'text-rose-700'],
                ] as $summary)
                    <div class="rounded-xl border border-stone-200 bg-white px-5 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
                        <p class="text-2xl font-semibold tabular-nums tracking-tight {{ $summary['valueClass'] }}">{{ number_format($summary['count']) }}</p>
                        <p class="mt-1 text-sm font-medium text-slate-600">{{ $summary['label'] }}</p>
                    </div>
                @endforeach
            </section>

            <section id="seller-stores" class="mt-9 scroll-mt-6" aria-labelledby="seller-stores-heading">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 id="seller-stores-heading" class="text-lg font-semibold tracking-tight text-slate-950">Seller stores</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ number_format($totalStores) }} {{ Str::plural('store', $totalStores) }} on the marketplace</p>
                    </div>
                    <form method="GET" action="{{ route('admin.stores.index') }}" class="flex w-full flex-col gap-2 sm:flex-row lg:w-auto" role="search" aria-label="Search seller stores">
                        @foreach (['status', 'joined', 'category_id', 'sort'] as $filter)
                            @if (filled($filters[$filter] ?? null))
                                <input type="hidden" name="{{ $filter }}" value="{{ $filters[$filter] }}">
                            @endif
                        @endforeach
                        <label class="sr-only" for="search">Search stores</label>
                        <input id="search" name="search" value="{{ $filters['search'] ?? '' }}" type="search" placeholder="Search stores or sellers" class="block h-10 w-full rounded-lg border-stone-200 bg-white text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-emerald-700 focus:ring-emerald-700 sm:w-72">
                        @if ($hasFilters)
                            <a href="{{ route('admin.stores.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg px-3 text-sm font-semibold text-slate-600 transition hover:bg-white hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">Reset</a>
                        @endif
                    </form>
                </div>

                <div class="mt-4 flex flex-col gap-3 border-y border-stone-200 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <nav class="flex gap-2 overflow-x-auto pb-1 sm:pb-0" aria-label="Store status filters">
                        @foreach (['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'] as $value => $label)
                            @php($selected = ($filters['status'] ?? '') === $value)
                            <a href="{{ route('admin.stores.index', [...request()->except('status', 'page'), ...($value ? ['status' => $value] : [])]) }}" aria-current="{{ $selected ? 'page' : 'false' }}" class="inline-flex min-h-9 shrink-0 items-center rounded-full px-3 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 {{ $selected ? 'bg-emerald-800 text-white' : 'text-slate-600 hover:bg-white hover:text-emerald-800' }}">
                                {{ $label }} <span class="ml-1.5 tabular-nums {{ $selected ? 'text-emerald-100' : 'text-slate-400' }}">{{ number_format($value ? ($statusCounts[$value] ?? 0) : $totalStores) }}</span>
                            </a>
                        @endforeach
                    </nav>

                    <details class="relative shrink-0">
                        <summary class="flex min-h-9 cursor-pointer list-none items-center rounded-lg px-3 text-sm font-semibold text-slate-600 transition hover:bg-white hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">More filters <span class="ml-1.5 text-xs" aria-hidden="true">⌄</span></summary>
                        <form method="GET" action="{{ route('admin.stores.index') }}" class="absolute right-0 z-10 mt-2 w-72 rounded-xl border border-stone-200 bg-white p-4 shadow-lg">
                            @if (filled($filters['search'] ?? null))
                                <input type="hidden" name="search" value="{{ $filters['search'] }}">
                            @endif
                            @if (filled($filters['status'] ?? null))
                                <input type="hidden" name="status" value="{{ $filters['status'] }}">
                            @endif
                            <div class="space-y-3">
                                <div>
                                    <label for="joined" class="mb-1.5 block text-sm font-medium text-slate-700">Date joined</label>
                                    <select id="joined" name="joined" class="block h-10 w-full rounded-lg border-stone-200 text-sm shadow-sm focus:border-emerald-700 focus:ring-emerald-700">
                                        <option value="">Any time</option>
                                        <option value="last_30_days" @selected(($filters['joined'] ?? null) === 'last_30_days')>Last 30 days</option>
                                        <option value="last_90_days" @selected(($filters['joined'] ?? null) === 'last_90_days')>Last 90 days</option>
                                        <option value="this_year" @selected(($filters['joined'] ?? null) === 'this_year')>This year</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="category_id" class="mb-1.5 block text-sm font-medium text-slate-700">Product category</label>
                                    <select id="category_id" name="category_id" class="block h-10 w-full rounded-lg border-stone-200 text-sm shadow-sm focus:border-emerald-700 focus:ring-emerald-700">
                                        <option value="">All categories</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}" @selected(($filters['category_id'] ?? null) === $category->id)>{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="sort" class="mb-1.5 block text-sm font-medium text-slate-700">Sort by</label>
                                    <select id="sort" name="sort" class="block h-10 w-full rounded-lg border-stone-200 text-sm shadow-sm focus:border-emerald-700 focus:ring-emerald-700">
                                        <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Newest</option>
                                        <option value="name" @selected(($filters['sort'] ?? null) === 'name')>Store name</option>
                                        <option value="recently_active" @selected(($filters['sort'] ?? null) === 'recently_active')>Most recently active</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="mt-4 inline-flex min-h-10 w-full items-center justify-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">Apply filters</button>
                        </form>
                    </details>
                </div>

                <div class="mt-6 grid grid-cols-[repeat(auto-fill,minmax(17rem,20rem))] gap-5" aria-label="Seller stores">
                    @forelse ($stores as $store)
                        @php($owner = $store->tenant->members->firstWhere('role', 'owner')?->user ?? $store->tenant->members->first()?->user)
                        <article class="min-h-[20rem] overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.08)] transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md focus-within:border-emerald-300 focus-within:ring-2 focus-within:ring-emerald-700">
                            <a href="{{ route('admin.stores.show', $store) }}" class="group flex h-full flex-col rounded-2xl focus:outline-none" aria-labelledby="store-{{ $store->id }}-name">
                                <div class="relative h-24 shrink-0 bg-gradient-to-br from-emerald-100 via-stone-100 to-stone-200" aria-hidden="true">
                                    <span class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_rgba(255,255,255,0.8),_transparent_48%)]"></span>
                                    <span class="absolute right-3 top-3 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusStyles[$store->status] }}">{{ ucfirst($store->status) }}</span>
                                </div>
                                <div class="flex flex-1 flex-col px-5 pb-5 pt-0">
                                    <div class="-mt-7 mb-4">
                                        @if ($store->logo_path)
                                            <img src="{{ Storage::disk('public')->url($store->logo_path) }}" alt="{{ $store->name }} logo" class="h-14 w-14 rounded-2xl border-4 border-white object-cover shadow-sm">
                                        @else
                                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl border-4 border-white bg-emerald-800 text-lg font-bold text-white shadow-sm" aria-hidden="true">{{ mb_strtoupper(mb_substr($store->name, 0, 1)) }}</div>
                                        @endif
                                    </div>
                                    <h3 id="store-{{ $store->id }}-name" title="{{ $store->name }}" class="truncate text-lg font-semibold tracking-tight text-slate-950">{{ $store->name }}</h3>
                                    <p title="{{ $owner?->name ?? 'No owner on file' }}" class="mt-1 truncate text-sm text-slate-500">{{ $owner?->name ?? 'No owner on file' }}</p>
                                    <dl class="mt-5 grid grid-cols-2 gap-3 border-t border-stone-100 pt-4 text-sm">
                                        <div><dt class="text-xs font-medium text-slate-500">Products</dt><dd class="mt-1 text-base font-semibold tabular-nums text-slate-900">{{ number_format($store->products_count) }}</dd></div>
                                        <div><dt class="text-xs font-medium text-slate-500">Orders</dt><dd class="mt-1 text-base font-semibold tabular-nums text-slate-900">{{ number_format($store->seller_orders_count) }}</dd></div>
                                    </dl>
                                    <span class="mt-auto inline-flex min-h-10 items-center pt-4 text-sm font-semibold text-emerald-800 transition group-hover:text-emerald-950">View details <span class="ml-1.5" aria-hidden="true">→</span></span>
                                </div>
                            </a>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-stone-200 bg-white px-6 py-14 text-center shadow-sm">
                            <p class="font-semibold text-slate-900">{{ $hasFilters ? 'No stores match these filters.' : 'No seller stores yet.' }}</p>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $hasFilters ? 'Try widening the search or clearing a filter.' : 'Approved sellers will appear here with their product and order counts.' }}</p>
                            @if ($hasFilters)<a href="{{ route('admin.stores.index') }}" class="mt-5 inline-flex min-h-10 items-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">Clear filters</a>@endif
                        </div>
                    @endforelse
                </div>

                @if ($stores->hasPages())
                    <div class="mt-6 rounded-xl border border-stone-200 bg-white px-4 py-4 shadow-sm">{{ $stores->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
