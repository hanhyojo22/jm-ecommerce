<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-emerald-700">Marketplace administration</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight text-slate-950">Dashboard</h1>
            <p class="mt-2 text-sm text-slate-600">Monitor every seller store from one place. Urgent reviews appear first.</p>
        </div>
    </x-slot>
    @php($statusStyles = ['active' => 'bg-emerald-50 text-emerald-800 ring-emerald-200', 'inactive' => 'bg-slate-100 text-slate-700 ring-slate-200', 'suspended' => 'bg-rose-50 text-rose-800 ring-rose-200'])
    @php($hasFilters = filled($filters['search'] ?? null) || filled($filters['status'] ?? null))
    <div class="py-8 sm:py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">We could not apply those filters. {{ $errors->first() }}</div>
            @endif

            @if ($pendingReviewCount > 0)
                <section class="mb-6 flex flex-col gap-4 rounded-xl border border-amber-200 bg-amber-50/70 p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between" aria-label="Pending reviews needing attention">
                    <div class="flex gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-base font-bold text-amber-800" aria-hidden="true">!</div>
                        <div>
                            <h2 class="font-semibold text-slate-950">{{ number_format($pendingReviewCount) }} {{ Str::plural('seller application', $pendingReviewCount) }} {{ Str::plural('needs', $pendingReviewCount) }} review</h2>
                            <p class="mt-1 text-sm text-amber-900">Pending sellers have no store record yet. Approve or reject them in the applications queue.</p>
                        </div>
                    </div>
                    <a href="{{ route('admin.seller-applications.index') }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-amber-600 px-4 text-sm font-semibold text-white transition hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-600 focus:ring-offset-2">Review applications</a>
                </section>
            @endif

            <section aria-label="Stores overview">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <a href="{{ route('admin.stores.index') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-emerald-300 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Total stores</p>
                        <p class="mt-2 text-3xl font-semibold tabular-nums tracking-tight text-slate-950">{{ number_format($totalStores) }}</p>
                        <p class="mt-1 text-sm text-slate-500">Every seller storefront record. View all &rarr;</p>
                    </a>
                    <a href="{{ route('admin.stores.index', ['status' => 'active']) }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-emerald-300 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Active stores</p>
                        <p class="mt-2 text-3xl font-semibold tabular-nums tracking-tight text-emerald-800">{{ number_format($statusCounts['active'] ?? 0) }}</p>
                        <p class="mt-1 text-sm text-slate-500">Visible on the marketplace storefront. Filter active &rarr;</p>
                    </a>
                    <a href="{{ route('admin.seller-applications.index') }}" class="rounded-xl border border-amber-200 bg-amber-50/60 p-5 shadow-sm transition hover:border-amber-300 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-amber-800">Pending review</p>
                        <p class="mt-2 text-3xl font-semibold tabular-nums tracking-tight text-slate-950">{{ number_format($pendingReviewCount) }}</p>
                        <p class="mt-1 text-sm text-amber-900">Applications awaiting a decision. Review queue &rarr;</p>
                    </a>
                    <a href="{{ route('admin.stores.index', ['status' => 'suspended']) }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-emerald-300 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Suspended stores</p>
                        <p class="mt-2 text-3xl font-semibold tabular-nums tracking-tight text-rose-700">{{ number_format($statusCounts['suspended'] ?? 0) }}</p>
                        <p class="mt-1 text-sm text-slate-500">Hidden until reactivated. Filter suspended &rarr;</p>
                    </a>
                </div>
            </section>

            <section class="mt-10" aria-label="Stores">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold tracking-tight text-slate-950">Stores</h2>
                        <p class="mt-1 text-sm text-slate-600">Browse every seller store. Select a card for a quick look, or open the Stores page for full moderation.</p>
                    </div>
                    <a href="{{ route('admin.stores.index') }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">Manage all stores</a>
                </div>

                <nav class="mt-5 flex gap-2 overflow-x-auto pb-1" aria-label="Store status filters">
                    @foreach (['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'] as $value => $label)
                        @php($selected = ($filters['status'] ?? '') === $value)
                        <a href="{{ route('dashboard', [...request()->except('status', 'page', 'selected'), ...($value ? ['status' => $value] : [])]) }}" class="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-lg border px-3 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 {{ $selected ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-300' }}">
                            {{ $label }}
                            <span class="rounded-full px-1.5 py-0.5 text-xs {{ $selected ? 'bg-white/20' : 'bg-slate-100 text-slate-600' }}">{{ $value ? ($statusCounts[$value] ?? 0) : $totalStores }}</span>
                        </a>
                    @endforeach
                </nav>

                <div class="mt-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <form method="GET" action="{{ route('dashboard') }}" x-data="{ submitting: false }" @submit="submitting = true" class="grid gap-3 lg:grid-cols-12 lg:items-end" role="search" aria-label="Search stores">
                        @if (filled($filters['status'] ?? null))
                            <input type="hidden" name="status" value="{{ $filters['status'] }}">
                        @endif
                        <div class="lg:col-span-9">
                            <label for="dashboard-search" class="mb-1.5 block text-sm font-medium text-slate-700">Search stores</label>
                            <input id="dashboard-search" name="search" value="{{ $filters['search'] ?? '' }}" type="search" placeholder="Store or seller name" class="block h-11 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-emerald-700 focus:ring-emerald-700">
                        </div>
                        <div class="flex gap-2 lg:col-span-3">
                            <button type="submit" :disabled="submitting" class="inline-flex h-11 flex-1 items-center justify-center rounded-lg bg-emerald-700 px-4 text-sm font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 disabled:opacity-60" x-text="submitting ? 'Working…' : 'Search'"></button>
                            @if ($hasFilters)
                                <a href="{{ route('dashboard') }}" class="inline-flex h-11 items-center justify-center rounded-lg border border-slate-200 px-4 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">Reset</a>
                            @endif
                        </div>
                    </form>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    @forelse ($stores as $store)
                        @php($owner = $store->tenant->members->firstWhere('role', 'owner')?->user ?? $store->tenant->members->first()?->user)
                        @php($detailUrl = route('dashboard', [...request()->except('page'), 'selected' => $store->id]))
                        @php($isSelected = ($selectedStore?->id ?? null) === $store->id)
                        <article aria-labelledby="store-{{ $store->id }}-name" class="flex flex-col overflow-hidden rounded-xl border bg-white shadow-sm transition hover:shadow-md focus-within:ring-2 focus-within:ring-emerald-700 {{ $isSelected ? 'border-emerald-500 ring-2 ring-emerald-600' : 'border-slate-200' }}">
                            <div class="relative flex h-28 items-center justify-center bg-gradient-to-br from-emerald-100 via-slate-100 to-slate-200" aria-hidden="true">
                                <span class="text-4xl font-bold text-emerald-800/60">{{ mb_strtoupper(mb_substr($store->name, 0, 1)) }}</span>
                                <span class="absolute right-3 top-3 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusStyles[$store->status] }}">{{ ucfirst($store->status) }}</span>
                            </div>
                            <div class="flex flex-1 flex-col p-5 pt-0">
                                <div class="-mt-7 mb-3">
                                    @if ($store->logo_path)
                                        <img src="{{ Storage::disk('public')->url($store->logo_path) }}" alt="" class="h-14 w-14 rounded-xl object-cover ring-4 ring-white">
                                    @else
                                        <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-emerald-700 text-lg font-bold text-white ring-4 ring-white" aria-hidden="true">{{ mb_strtoupper(mb_substr($store->name, 0, 1)) }}</div>
                                    @endif
                                </div>
                                <h3 id="store-{{ $store->id }}-name" class="truncate text-base font-semibold text-slate-950">
                                    <a href="{{ $detailUrl }}" class="rounded focus:outline-none focus:ring-2 focus:ring-emerald-700">{{ $store->name }}</a>
                                </h3>
                                <p class="mt-0.5 truncate text-sm text-slate-500">{{ $owner?->name ?? 'No owner on file' }}</p>
                                <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-slate-100 pt-4 text-sm">
                                    <div>
                                        <dt class="text-xs font-medium text-slate-500">Products</dt>
                                        <dd class="mt-1 font-semibold tabular-nums text-slate-800">{{ number_format($store->products_count) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium text-slate-500">Orders</dt>
                                        <dd class="mt-1 font-semibold tabular-nums text-slate-800">{{ number_format($store->seller_orders_count) }}</dd>
                                    </div>
                                </dl>
                                <a href="{{ $detailUrl }}" class="mt-4 inline-flex min-h-11 items-center justify-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">View details</a>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-xl border border-slate-200 bg-white px-6 py-14 text-center shadow-sm sm:col-span-2 xl:col-span-3">
                            <p class="font-semibold text-slate-900">{{ $hasFilters ? 'No stores match these filters.' : 'No seller stores yet.' }}</p>
                            <p class="mx-auto mt-2 max-w-md text-sm text-slate-600">{{ $hasFilters ? 'Try widening the search or clearing the status filter.' : 'Approved sellers will appear here with their product and order counts.' }}</p>
                            @if ($hasFilters)
                                <a href="{{ route('dashboard') }}" class="mt-5 inline-flex min-h-11 items-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">Clear search and filters</a>
                            @endif
                        </div>
                    @endforelse
                </div>
                @if ($stores->hasPages())
                    <div class="mt-5 rounded-xl border border-slate-200 bg-white px-4 py-4 shadow-sm">{{ $stores->links() }}</div>
                @endif
            </section>
        </div>
    </div>

    @if ($selectedStore)
        @php($selectedOwner = $selectedStore->tenant->members->firstWhere('role', 'owner')?->user ?? $selectedStore->tenant->members->first()?->user)
        @php($closeUrl = route('dashboard', request()->except('selected')))
        <div x-data @keydown.escape.window="window.location.href = '{{ $closeUrl }}'">
            <a href="{{ $closeUrl }}" aria-label="Close store details" class="fixed inset-0 z-40 bg-slate-950/40"></a>
            <aside role="dialog" aria-modal="true" aria-labelledby="store-detail-title" x-init="$refs.closeButton.focus()" class="fixed inset-y-0 right-0 z-50 flex w-full flex-col bg-white shadow-2xl sm:max-w-md">
                <div class="relative flex h-32 shrink-0 items-center justify-center bg-gradient-to-br from-emerald-100 via-slate-100 to-slate-200" aria-hidden="true">
                    <span class="text-5xl font-bold text-emerald-800/60">{{ mb_strtoupper(mb_substr($selectedStore->name, 0, 1)) }}</span>
                    <a href="{{ $closeUrl }}" x-ref="closeButton" aria-label="Close store details" class="absolute right-4 top-4 inline-flex h-11 w-11 items-center justify-center rounded-full bg-white text-xl font-semibold text-slate-700 shadow transition hover:text-slate-950 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">&times;</a>
                </div>
                <div class="flex-1 overflow-y-auto p-6 pt-0">
                    <div class="-mt-7 mb-4">
                        @if ($selectedStore->logo_path)
                            <img src="{{ Storage::disk('public')->url($selectedStore->logo_path) }}" alt="" class="h-14 w-14 rounded-xl object-cover ring-4 ring-white">
                        @else
                            <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-emerald-700 text-lg font-bold text-white ring-4 ring-white" aria-hidden="true">{{ mb_strtoupper(mb_substr($selectedStore->name, 0, 1)) }}</div>
                        @endif
                    </div>
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusStyles[$selectedStore->status] }}">{{ ucfirst($selectedStore->status) }}</span>
                    <h2 id="store-detail-title" class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{{ $selectedStore->name }}</h2>
                    @if (filled($selectedStore->description))
                        <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $selectedStore->description }}</p>
                    @endif
                    <dl class="mt-5 space-y-4 border-t border-slate-100 pt-5 text-sm">
                        @if ($selectedOwner)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Seller</dt>
                                <dd class="mt-1 font-medium text-slate-900">{{ $selectedOwner->name }}</dd>
                                <dd class="mt-0.5 break-all"><a href="mailto:{{ $selectedOwner->email }}" class="font-medium text-emerald-800 underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-emerald-700">{{ $selectedOwner->email }}</a></dd>
                            </div>
                        @endif
                        @if (filled($selectedStore->phone))
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Store phone</dt>
                                <dd class="mt-1 font-medium text-slate-900">{{ $selectedStore->phone }}</dd>
                            </div>
                        @endif
                        @if (filled($selectedStore->address_line) || filled($selectedStore->city))
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Location</dt>
                                <dd class="mt-1 font-medium text-slate-900">{{ collect([$selectedStore->address_line, $selectedStore->city])->filter()->implode(', ') }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Joined</dt>
                            <dd class="mt-1 font-medium text-slate-900">{{ $selectedStore->created_at->format('M j, Y') }}</dd>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Products</dt>
                                <dd class="mt-1 text-lg font-semibold tabular-nums text-slate-950">{{ number_format($selectedStore->products_count) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Orders</dt>
                                <dd class="mt-1 text-lg font-semibold tabular-nums text-slate-950">{{ number_format($selectedStore->seller_orders_count) }}</dd>
                            </div>
                        </div>
                    </dl>
                </div>
                <div class="grid shrink-0 gap-2 border-t border-slate-200 p-4 sm:grid-cols-2">
                    <a href="{{ route('catalog.store', $selectedStore->slug) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-200 px-4 text-sm font-semibold text-slate-800 transition hover:border-emerald-300 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">Open storefront</a>
                    <a href="{{ route('admin.stores.show', $selectedStore) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">Manage store</a>
                </div>
            </aside>
        </div>
    @endif
</x-app-layout>
