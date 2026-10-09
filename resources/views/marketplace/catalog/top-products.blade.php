<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <meta name="clerk-publishable-key" content="{{ config('services.clerk.publishable_key') }}">
    <meta name="clerk-sign-in-url" content="{{ config('services.clerk.sign_in_url') }}">
    <meta name="clerk-sign-up-url" content="{{ config('services.clerk.sign_up_url') }}">
    @auth('clerk')
        {{-- Only signed-in visitors get one of these: it is what lets navigation renew a lapsed
             session cookie before it opens a protected page, and it keeps guests untouched. --}}
        <meta name="clerk-session-refresh-endpoint" content="{{ route('clerk.session.refresh') }}">
    @endauth
    <title>Top products · JMMart</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="marketplace-catalog min-h-dvh bg-canvas font-sans text-ink antialiased">
    <x-marketplace.header />

    <main class="mx-auto min-h-[calc(100dvh-4.5rem)] max-w-[1320px] px-[18px] pb-16 sm:px-8">
        <nav aria-label="Breadcrumb" class="mb-2 mt-5 flex items-center gap-2 text-[13px] text-muted">
            <a href="{{ route('home') }}" class="font-medium transition hover:text-brand-hover">Home</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page" class="font-semibold text-ink">Top products</span>
        </nav>

        <div x-data="{ filtersOpen: false, viewMode: 'grid' }" class="grid items-start gap-7 pt-2 lg:grid-cols-[272px_minmax(0,1fr)]">
            <aside id="top-product-filters" x-cloak x-show="filtersOpen" class="rounded-[14px] border border-line bg-white lg:sticky lg:top-24 lg:!block lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto" aria-label="Product filters">
                <div class="mx-5 flex items-center justify-between border-b border-line py-4">
                    <h2 class="font-semibold">Filters</h2>
                    <a href="{{ route('catalog.top-products') }}" class="inline-flex min-h-11 items-center text-[13px] font-medium text-brand hover:text-brand-hover">Clear all</a>
                </div>
                <div class="px-5 pb-5 pt-4">
                    <h3 class="text-xs font-semibold uppercase tracking-[0.06em] text-muted">Category</h3>
                    <ul class="mt-2 space-y-0.5">
                        @foreach ($categories->take(5) as $category)
                            <x-marketplace.filter-option :item="$category" :href="route('catalog.top-products', ['category' => $category->slug])" :clear-href="route('catalog.top-products')" :selected="$selectedCategory === $category->slug" />
                        @endforeach
                    </ul>
                    @if ($categories->count() > 5)
                        <details class="mt-1 text-sm" @if ($categories->skip(5)->contains('slug', $selectedCategory)) open @endif>
                            <summary class="cursor-pointer py-2 font-medium text-brand hover:text-brand-hover">More categories</summary>
                            <ul class="space-y-0.5 pb-1">
                                @foreach ($categories->skip(5) as $category)
                                    <x-marketplace.filter-option :item="$category" :href="route('catalog.top-products', ['category' => $category->slug])" :clear-href="route('catalog.top-products')" :selected="$selectedCategory === $category->slug" />
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </div>
            </aside>
            <section class="min-w-0" aria-labelledby="top-products-heading">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 id="top-products-heading" class="text-[28px] font-bold leading-[1.55] tracking-[-0.02em]">Top products</h1>
                        <p class="mt-1 text-sm text-muted">{{ number_format($products->total()) }} {{ Str::plural('product', $products->total()) }} ranked by delivered sales in the last {{ $rankingPeriodDays }} days</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 sm:ml-auto">
                        <button type="button" @click="filtersOpen = ! filtersOpen" :aria-expanded="filtersOpen.toString()" aria-controls="top-product-filters" class="inline-flex min-h-11 items-center rounded-[10px] border border-line bg-white px-4 text-sm font-semibold lg:hidden">Filters</button>
                        <span class="inline-flex min-h-10 items-center rounded-[10px] border border-line bg-white px-3 text-[13px] font-medium text-muted">Last {{ $rankingPeriodDays }} days</span>
                        <div class="hidden items-center gap-1 rounded-[10px] border border-line bg-white p-1 sm:flex" role="group" aria-label="View layout">
                            <button type="button" @click="viewMode = 'grid'" :aria-pressed="viewMode === 'grid'" aria-label="Grid view" :class="viewMode === 'grid' ? 'bg-ink text-white' : 'text-muted hover:text-ink'" class="grid size-10 place-items-center rounded-lg transition"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></button>
                            <button type="button" @click="viewMode = 'list'" :aria-pressed="viewMode === 'list'" aria-label="List view" :class="viewMode === 'list' ? 'bg-ink text-white' : 'text-muted hover:text-ink'" class="grid size-10 place-items-center rounded-lg transition"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5h12M9 12h12M9 19h12"/><path d="M3 5h2M3 12h2M3 19h2"/></svg></button>
                        </div>
                    </div>
                </div>

                @if ($selectedCategory)
                    <div class="mt-4 flex flex-wrap items-center gap-2" aria-label="Active filters">
                        <a href="{{ route('catalog.top-products') }}" class="inline-flex min-h-11 items-center gap-1.5 rounded-full border border-line bg-white px-3 text-[13px] font-medium hover:border-brand hover:text-brand">{{ $categories->firstWhere('slug', $selectedCategory)?->name ?? $selectedCategory }} <span aria-hidden="true">×</span><span class="sr-only">Remove category filter</span></a>
                    </div>
                @endif

                @if ($products->isNotEmpty())
                    <div id="catalog-products" :data-view="viewMode" class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4" aria-label="Top products">
                        @foreach ($products as $product)
                            <x-marketplace.top-product-card :product="$product" />
                        @endforeach
                    </div>
                    <div class="mt-10">{{ $products->links('components.marketplace.pagination') }}</div>
                @else
                    <div class="mt-5 rounded-[14px] border border-dashed border-line bg-white px-6 py-16 text-center">
                        <h2 class="text-lg font-semibold">No top products yet</h2>
                        <p class="mt-2 text-sm text-muted">Delivered orders from the last {{ $rankingPeriodDays }} days will appear here.</p>
                        @if ($selectedCategory)<a href="{{ route('catalog.top-products') }}" class="mt-4 inline-flex min-h-11 items-center rounded-[10px] px-3 text-sm font-semibold text-brand hover:bg-blue-50 hover:text-brand-hover">Clear category filter</a>@endif
                    </div>
                @endif
            </section>
        </div>
    </main>

    <x-marketplace.footer />
    @livewireScripts
</body>
</html>
