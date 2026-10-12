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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title>{{ $sellerStore ? $sellerStore->name.' storefront' : 'Marketplace' }} · JMMart</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="marketplace-catalog bg-canvas font-sans text-ink antialiased">
    @php
        $isBuyer = Auth::check() && in_array(Auth::user()->account_type, ['buyer', 'seller'], true) && Auth::user()->hasVerifiedClerkEmail();
        $catalogQueryParams = array_filter(request()->only(['q', 'category', 'seller', 'sort', 'min_price', 'max_price', 'in_stock', 'brands', 'conditions', 'on_sale', 'min_rating', 'with_photos']), fn ($value) => filled($value) || $value === '0');
        $catalogRouteName = $sellerStore ? 'catalog.store' : 'catalog.index';
        $catalogRouteParameters = $sellerStore ? ['store' => $sellerStore] : [];
        $catalogUrl = route($catalogRouteName, $catalogRouteParameters);
        $clearSearchUrl = route($catalogRouteName, [...$catalogRouteParameters, ...request()->except(['q', 'page'])]);
        $clearAllUrl = $catalogUrl;
        $isListing = ! $sellerStore;
        $filterChips = [];
        if (filled($search)) { $filterChips[] = ['label' => '“'.Str::limit($search, 24).'”', 'url' => route($catalogRouteName, [...$catalogRouteParameters, ...request()->except(['q', 'page'])])]; }
        if (filled(request('category'))) { $categoryName = $categories->firstWhere('slug', request('category'))?->name ?? request('category'); $filterChips[] = ['label' => $categoryName, 'url' => route($catalogRouteName, [...$catalogRouteParameters, ...request()->except(['category', 'page'])])]; }
        if (! $sellerStore && filled(request('seller'))) { $storeName = $filterStores->firstWhere('slug', request('seller'))?->name ?? request('seller'); $filterChips[] = ['label' => $storeName, 'url' => route($catalogRouteName, request()->except(['seller', 'page']))]; }
        if (! empty($activeFilters['min_price']) || ! empty($activeFilters['max_price'])) { $filterChips[] = ['label' => '₱'.($activeFilters['min_price'] ?? '0').' – ₱'.($activeFilters['max_price'] ?? '∞'), 'url' => route($catalogRouteName, [...$catalogRouteParameters, ...request()->except(['min_price', 'max_price', 'page'])])]; }
        if (! empty($activeFilters['in_stock'])) { $filterChips[] = ['label' => 'In stock', 'url' => route($catalogRouteName, [...$catalogRouteParameters, ...request()->except(['in_stock', 'page'])])]; }
        foreach ($activeFilters['brands'] ?? [] as $activeBrand) {
            $remainingBrands = array_values(array_diff($activeFilters['brands'], [$activeBrand]));
            $queryParameters = request()->except(['brands', 'page']);
            if ($remainingBrands !== []) { $queryParameters['brands'] = $remainingBrands; }
            $filterChips[] = ['label' => $activeBrand, 'url' => route($catalogRouteName, [...$catalogRouteParameters, ...$queryParameters])];
        }
        foreach ($activeFilters['conditions'] ?? [] as $activeCondition) {
            $remainingConditions = array_values(array_diff($activeFilters['conditions'], [$activeCondition]));
            $queryParameters = request()->except(['conditions', 'page']);
            if ($remainingConditions !== []) { $queryParameters['conditions'] = $remainingConditions; }
            $filterChips[] = ['label' => ucfirst($activeCondition), 'url' => route($catalogRouteName, [...$catalogRouteParameters, ...$queryParameters])];
        }
        if (! empty($activeFilters['on_sale'])) { $filterChips[] = ['label' => 'On sale', 'url' => route($catalogRouteName, [...$catalogRouteParameters, ...request()->except(['on_sale', 'page'])])]; }
        if (! empty($activeFilters['min_rating'])) { $filterChips[] = ['label' => $activeFilters['min_rating'].'★ & up', 'url' => route($catalogRouteName, [...$catalogRouteParameters, ...request()->except(['min_rating', 'page'])])]; }
        if (! empty($activeFilters['with_photos'])) { $filterChips[] = ['label' => 'With photos', 'url' => route($catalogRouteName, [...$catalogRouteParameters, ...request()->except(['with_photos', 'page'])])]; }
        $storeBestSellers = $sellerStore ? $products->getCollection()->filter(fn ($p) => (int) ($p->sold_quantity ?? 0) > 0)->sortByDesc(fn ($p) => (int) $p->sold_quantity)->take(4)->values() : collect();
        $storeFeatured = $sellerStore ? $products->getCollection()->take(4) : collect();
    @endphp

    <x-marketplace.header :search="$search" />

    <div x-data="{
        loading: false,
        viewMode: 'grid',
        filtersOpen: false,
        filterTrigger: null,
        toggleFilters(event) {
            if (this.filtersOpen) {
                this.closeFilters();
                return;
            }
            this.filterTrigger = event.currentTarget;
            this.filtersOpen = true;
            this.$nextTick(() => {
                this.$refs.filtersPanel.focus({ preventScroll: true });
                window.scrollTo({ top: window.scrollY + this.$refs.filtersPanel.getBoundingClientRect().top - 16 });
            });
        },
        closeFilters() {
            this.filtersOpen = false;
            this.$nextTick(() => this.filterTrigger?.focus());
        },
        categoriesOpen: false,
        categoryTrigger: null,
        openCategories(event) {
            this.categoryTrigger = event.currentTarget;
            this.categoriesOpen = true;
            this.$nextTick(() => this.$refs.categoryDialog.querySelector('[data-category-focus]').focus());
        },
        closeCategories() {
            this.categoriesOpen = false;
            this.$nextTick(() => this.categoryTrigger?.focus());
        },
        trapCategories(event) {
            const items = [...this.$refs.categoryDialog.querySelectorAll('a,button')].filter(item => item.offsetParent);
            if (event.shiftKey && document.activeElement === items[0]) { event.preventDefault(); items.at(-1)?.focus(); }
            else if (!event.shiftKey && document.activeElement === items.at(-1)) { event.preventDefault(); items[0]?.focus(); }
        },
    }" x-init="$watch('categoriesOpen', value => document.body.classList.toggle('overflow-hidden', value))" class="min-h-screen">

        @if ($isSellerPreview)
            <div class="bg-[#111c32] px-4 py-3 text-white">
                <div class="mx-auto flex max-w-[1320px] flex-wrap items-center justify-between gap-3 text-sm">
                    <p class="font-semibold">Storefront preview <span class="ml-2 font-normal text-slate-300">This is what customers see.</span></p>
                </div>
            </div>
        @endif

        <main class="mx-auto max-w-[1320px] px-[18px] pb-16 min-[1021px]:px-8">
            @if ($errors->any())
                <div role="alert" class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900">
                    {{ $errors->first() }}
                    <a href="#product-results" class="ml-2 font-semibold underline underline-offset-4">Try again</a>
                </div>
            @endif

            @if ($sellerStore)
                {{-- Storefront (storefront.html): banner + snav + featured + best sellers + about --}}
                <nav aria-label="Breadcrumb" class="mb-2 mt-5 flex min-w-0 items-center gap-2 overflow-x-auto whitespace-nowrap text-[13px] text-muted">
                    <a href="{{ route('catalog.index') }}" class="shrink-0 font-medium transition hover:text-brand-hover">Marketplace</a>
                    <span aria-hidden="true">/</span>
                    <a href="{{ route('catalog.stores') }}" class="shrink-0 font-medium transition hover:text-brand-hover">Stores</a>
                    <span aria-hidden="true">/</span>
                    <span aria-current="page" class="min-w-0 truncate font-semibold text-ink">{{ $sellerStore->name }}</span>
                </nav>

                <section class="overflow-hidden rounded-[14px] border border-line bg-white" aria-labelledby="store-heading" data-od-id="store-header">
                    <div class="flex flex-col gap-5 p-6 sm:p-8 lg:flex-row lg:items-center">
                        <div class="flex min-w-0 flex-1 flex-col items-start gap-4 sm:flex-row sm:items-center">
                            <span class="grid size-20 shrink-0 place-items-center overflow-hidden rounded-[14px] bg-blue-50 text-2xl font-bold text-brand">
                                @if ($sellerStore->logo_path)<img src="{{ Storage::disk('public')->url($sellerStore->logo_path) }}" alt="{{ $sellerStore->name }} logo" class="h-full w-full object-cover">@else{{ Str::upper(Str::substr($sellerStore->name, 0, 1)) }}@endif
                            </span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h1 id="store-heading" class="break-words text-2xl font-bold tracking-[-0.02em] sm:text-3xl">{{ $sellerStore->name }}</h1>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-green-50 px-2.5 py-1 text-xs font-semibold text-success ring-1 ring-inset ring-green-200"><span class="size-1.5 rounded-full bg-success" aria-hidden="true"></span>Open</span>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-brand-hover ring-1 ring-inset ring-blue-200">✔ Verified</span>
                                </div>
                                <p class="mt-2 text-sm text-muted">{{ number_format($sellerStore->public_products_count) }} {{ Str::plural('product', $sellerStore->public_products_count) }}@if (filled($sellerStore->city)) · {{ $sellerStore->city }}@endif @if ($sellerStore->created_at)· Joined {{ $sellerStore->created_at->format('M Y') }} @endif</p>
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-2">
                            <a href="#product-results" class="inline-flex min-h-11 items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover">Browse products</a>
                            @php($storeFirstProduct = $storeFeatured->first() ?? $storeBestSellers->first() ?? $products->getCollection()->first())
                            @if ($isBuyer && $storeFirstProduct)
                                <form method="POST" action="{{ route('messages.products.start', $storeFirstProduct) }}" class="contents">
                                    @csrf
                                    <button class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-line px-5 text-sm font-semibold text-ink transition hover:border-brand hover:text-brand">Chat with seller</button>
                                </form>
                            @endif
                            <a href="{{ route('catalog.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-line px-5 text-sm font-semibold text-ink transition hover:border-brand hover:text-brand">Marketplace</a>
                        </div>
                    </div>
                </section>

                <nav class="mt-4 flex gap-6 overflow-x-auto border-b border-line text-sm font-medium" aria-label="Store sections">
                    <a href="#store-heading" class="inline-flex min-h-11 shrink-0 items-center border-b-2 border-brand font-semibold text-ink" aria-current="page">Home</a>
                    <a href="#product-results" class="inline-flex min-h-11 shrink-0 items-center border-b-2 border-transparent text-muted transition hover:border-blue-200 hover:text-brand">Products ({{ number_format($sellerStore->public_products_count) }})</a>
                    <a href="#store-about" class="inline-flex min-h-11 shrink-0 items-center border-b-2 border-transparent text-muted transition hover:border-blue-200 hover:text-brand">About</a>
                </nav>

                @if ($storeFeatured->count() > 1)
                    <section id="store-featured" class="scroll-mt-24 pt-6" aria-labelledby="store-featured-heading">
                        <div class="flex flex-wrap items-end justify-between gap-3">
                            <h2 id="store-featured-heading" class="text-xl font-bold tracking-[-0.02em]">Featured</h2>
                            <a href="#product-results" class="text-sm font-semibold text-brand hover:text-brand-hover">View all →</a>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
                            @foreach ($storeFeatured as $product)
                                <x-marketplace.top-product-card :product="$product" />
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($storeBestSellers->count() > 1)
                    <section class="pt-7" aria-labelledby="store-best-heading">
                        <h2 id="store-best-heading" class="text-xl font-bold tracking-[-0.02em]">Best sellers</h2>
                        <ol class="mt-4 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                            @foreach ($storeBestSellers as $product)
                                <li class="min-w-0"><a href="{{ route('catalog.show', [$product->store->slug, $product->slug]) }}" class="flex min-h-24 flex-col justify-between rounded-[14px] border border-line bg-white p-4 transition hover:border-brand hover:shadow-[0_12px_28px_rgba(15,23,42,.10)]"><span class="line-clamp-2 text-sm font-semibold text-ink">{{ $loop->iteration }} · {{ $product->name }}</span><span class="mt-2 text-xs text-muted">{{ number_format((int) ($product->sold_quantity ?? 0)) }} sold · ₱{{ number_format($product->price, 2) }}@if ($product->stock_quantity < 1)<span class="mt-1 block font-semibold text-danger">Sold out</span>@endif</span></a></li>
                            @endforeach
                        </ol>
                    </section>
                @endif

                <section id="store-about" class="mt-10 scroll-mt-24 rounded-[14px] border border-line bg-white p-5 sm:p-6" aria-labelledby="store-about-heading">
                    <h2 id="store-about-heading" class="text-lg font-semibold">About {{ $sellerStore->name }}</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-muted">{{ filled($sellerStore->description) ? $sellerStore->description : 'An independent JMMart seller offering quality products with cash on delivery and free shipping.' }}</p>
                    <dl class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted">
                        <div><dt class="sr-only">Products</dt><dd><span class="font-semibold text-ink">{{ number_format($sellerStore->public_products_count) }}</span> {{ Str::plural('product', $sellerStore->public_products_count) }}</dd></div>
                        @if (filled($sellerStore->city))<div><dt class="sr-only">Location</dt><dd>{{ $sellerStore->city }}</dd></div>@endif
                        @if ($sellerStore->created_at)<div><dt class="sr-only">Joined</dt><dd>Joined {{ $sellerStore->created_at->format('M Y') }}</dd></div>@endif
                    </dl>
                </section>
            @else
                {{-- Listing header for search / filtered results (marketplace.html) --}}
                <nav aria-label="Breadcrumb" class="mb-1 mt-0 flex min-w-0 items-center gap-2 overflow-x-auto whitespace-nowrap text-[13px] text-muted min-[641px]:mb-2 min-[641px]:pt-[18px] min-[641px]:pb-1">
                    <a href="{{ route('home') }}" class="shrink-0 font-medium transition hover:text-brand-hover">Home</a>
                    <span aria-hidden="true">/</span>
                    <span aria-current="page" class="min-w-0 truncate font-semibold text-ink">{{ filled($search) ? 'Search results' : (request('category') ? ($categories->firstWhere('slug', request('category'))?->name ?? 'Category') : 'Marketplace') }}</span>
                </nav>
            @endif

            {{-- Catalog results with filters (marketplace.html layout) --}}
            <section id="product-results" class="scroll-mt-24 {{ $sellerStore || ! $isListing ? 'pt-12' : 'pt-1 min-[641px]:pt-0' }}" aria-labelledby="product-results-heading" data-od-id="results">
                @if ($sellerStore)
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0"><h2 id="product-results-heading" class="text-2xl font-bold tracking-[-0.02em]">{{ $sellerStore ? 'All products' : 'Products' }}</h2><p class="mt-1 text-sm text-muted">{{ number_format($products->total()) }} {{ Str::plural('product', $products->total()) }}@if ($search) matching “{{ Str::limit($search, 32) }}”@endif</p></div>
                        <button type="button" @click="toggleFilters($event)" :aria-expanded="filtersOpen.toString()" aria-controls="catalog-filters" class="inline-flex min-h-11 items-center gap-2 rounded-[10px] border border-line bg-white px-4 text-sm font-semibold transition hover:border-brand lg:hidden">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M7 12h10m-7 6h4" /></svg>
                            Filters
                            @if (! empty($filterChips))<span class="rounded-full bg-brand px-2 py-0.5 text-xs font-bold text-white">{{ count($filterChips) }}</span>@endif
                        </button>
                    </div>
                    @if (! empty($filterChips))
                        <div class="mt-4 flex flex-wrap items-center gap-2" aria-label="Active filters">
                            @foreach ($filterChips as $chip)
                                <a href="{{ $chip['url'] }}" class="inline-flex min-h-[34px] items-center gap-[7px] rounded-full border border-line bg-white py-[6px] pl-3 pr-1.5 text-[13px] leading-[20px] font-medium transition hover:border-brand hover:text-brand">{{ $chip['label'] }}<span aria-hidden="true" class="grid size-[22px] shrink-0 place-items-center rounded-full bg-canvas text-muted">×</span><span class="sr-only">Remove filter {{ $chip['label'] }}</span></a>
                            @endforeach
                            <a href="{{ $clearAllUrl }}" class="inline-flex min-h-11 items-center rounded-full px-3 text-[13px] font-semibold text-brand hover:bg-blue-50">Clear all</a>
                        </div>
                    @endif
                @endif

                @php($showFiltersSidebar = $sellerStore || $isListing)
                <div class="{{ $isListing && ! $sellerStore ? 'mt-0 gap-7' : 'mt-6 gap-6' }} grid grid-cols-[minmax(min(354px,calc(100vw-36px)),1fr)] items-start {{ $showFiltersSidebar ? ($isListing ? 'min-[1021px]:grid-cols-[272px_minmax(0,1fr)]' : 'lg:grid-cols-[272px_minmax(0,1fr)]') : '' }}">
                    {{-- Filters sidebar for marketplace listings and storefronts --}}
                    @if ($showFiltersSidebar)
                    <aside id="catalog-filters" x-ref="filtersPanel" tabindex="-1" x-cloak x-show="filtersOpen" x-on:keydown.escape.stop="closeFilters()" class="scroll-mt-4 rounded-[14px] border border-line bg-white p-5 {{ $isListing ? 'min-[1021px]:!block min-[1021px]:mt-[9.5px] min-[1021px]:sticky min-[1021px]:top-[130px]' : 'lg:sticky lg:top-[130px] lg:!block' }}" aria-label="Product filters">
                        <div class="flex items-center justify-between">
                            <h3 class="text-[15px] font-bold leading-[1.55]">Filters</h3>
                            <div class="flex items-center gap-3">
                                <a href="{{ $clearAllUrl }}" class="inline-flex min-h-6 items-center text-[13px] leading-[1.55] font-medium text-brand hover:text-brand-hover">Clear all</a>
                                <button type="button" @click="closeFilters()" class="inline-flex size-11 items-center justify-center rounded-lg text-muted hover:bg-canvas hover:text-ink {{ $isListing ? 'min-[1021px]:hidden' : 'lg:hidden' }}" aria-label="Close filters">×</button>
                            </div>
                        </div>
                        @if ($categories->count() > 1)
                            <div class="border-t border-line py-[14px]">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-xs font-semibold uppercase leading-[1.55] tracking-[0.06em] text-muted">Category</h3>
                                    @if ($categories->count() > 5)
                                        <details class="relative text-xs" @if ($categories->skip(5)->contains('slug', request('category'))) open @endif>
                                            <summary class="cursor-pointer list-none font-medium text-brand hover:text-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand [&::-webkit-details-marker]:hidden">More</summary>
                                            <ul class="absolute right-0 top-full z-30 mt-2 max-h-60 w-[230px] overflow-y-auto rounded-xl border border-line bg-white p-2 shadow-lg shadow-ink/10">
                                                @foreach ($categories->skip(5) as $category)
                                                    <x-marketplace.filter-option :item="$category" :href="route($catalogRouteName, [...$catalogRouteParameters, ...request()->except(['category', 'page']), 'category' => $category->slug])" :clear-href="route($catalogRouteName, [...$catalogRouteParameters, ...request()->except(['category', 'page'])])" :selected="request('category') === $category->slug" />
                                                @endforeach
                                            </ul>
                                        </details>
                                    @endif
                                </div>
                                <ul class="mt-[10px]">
                                    @foreach ($categories->take(5) as $category)
                                        <x-marketplace.filter-option :item="$category" :href="route($catalogRouteName, [...$catalogRouteParameters, ...request()->except(['category', 'page']), 'category' => $category->slug])" :clear-href="route($catalogRouteName, [...$catalogRouteParameters, ...request()->except(['category', 'page'])])" :selected="request('category') === $category->slug" />
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <form method="GET" action="{{ $catalogUrl }}" @submit="loading = true; if (!$refs.minPrice.value) $refs.minPrice.disabled = true; if (!$refs.maxPrice.value) $refs.maxPrice.disabled = true" class="p-0">
                            @if (filled($search))<input type="hidden" name="q" value="{{ $search }}">@endif
                            @if (filled(request('category')))<input type="hidden" name="category" value="{{ request('category') }}">@endif
                            @if (! $sellerStore && filled(request('seller')))<input type="hidden" name="seller" value="{{ request('seller') }}">@endif
                            @if (filled(request('sort')))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
                            <div role="group" aria-labelledby="catalog-price-heading" class="border-t border-line py-[14px]">
                                <h3 id="catalog-price-heading" class="mb-2.5 text-xs font-semibold uppercase leading-[1.55] tracking-[0.06em] text-muted">Price</h3>
                                <div class="grid grid-cols-2 gap-2">
                                    <div><label for="minimum-price" class="sr-only">Minimum price in pesos</label><input id="minimum-price" x-ref="minPrice" x-on:change="$nextTick(() => { if (document.activeElement !== $refs.minPrice && document.activeElement !== $refs.maxPrice) $el.form.requestSubmit() })" x-on:keydown.enter.prevent="$el.form.requestSubmit()" type="number" name="min_price" min="0" step="0.01" value="{{ $activeFilters['min_price'] ?? '' }}" placeholder="Min ₱" class="h-[33px] w-full rounded-[10px] border-line px-2 text-sm focus:border-brand focus:ring-brand"></div>
                                    <div><label for="maximum-price" class="sr-only">Maximum price in pesos</label><input id="maximum-price" x-ref="maxPrice" x-on:change="$nextTick(() => { if (document.activeElement !== $refs.minPrice && document.activeElement !== $refs.maxPrice) $el.form.requestSubmit() })" x-on:keydown.enter.prevent="$el.form.requestSubmit()" type="number" name="max_price" min="0" step="0.01" value="{{ $activeFilters['max_price'] ?? '' }}" placeholder="Max ₱" class="h-[33px] w-full rounded-[10px] border-line px-2 text-sm focus:border-brand focus:ring-brand"></div>
                                </div>
                            </div>

                            <div role="group" aria-labelledby="catalog-rating-heading" class="border-t border-line py-[14px]">
                                <h3 id="catalog-rating-heading" class="mb-2.5 text-xs font-semibold uppercase leading-[1.55] tracking-[0.06em] text-muted">Rating</h3>
                                <div class="space-y-0">
                                    @foreach ([4 => '4★ & up'] as $value => $label)
                                    <label class="flex min-h-8 cursor-pointer items-center gap-[9px] rounded-lg py-[5px] pl-[5px] text-sm leading-[1.55] transition hover:bg-canvas">
                                        <input type="checkbox" name="min_rating" value="{{ $value }}" @checked(($activeFilters['min_rating'] ?? null) == $value) x-on:change="$el.form.requestSubmit()" class="size-4 appearance-auto accent-brand focus:ring-brand">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                                    <label class="flex min-h-8 cursor-pointer items-center gap-[9px] rounded-lg py-[5px] pl-[5px] text-sm leading-[1.55] transition hover:bg-canvas">
                                        <input type="checkbox" name="with_photos" value="1" @checked(! empty($activeFilters['with_photos'])) x-on:change="$el.form.requestSubmit()" class="size-4 appearance-auto accent-brand focus:ring-brand">
                                        <span>With photos</span>
                                    </label>
                                </div>
                            </div>

                            @if ($brands->isNotEmpty())
                                <div role="group" aria-labelledby="catalog-brand-heading" class="border-t border-line py-[14px]">
                                    <h3 id="catalog-brand-heading" class="mb-2.5 text-xs font-semibold uppercase leading-[1.55] tracking-[0.06em] text-muted">Brand</h3>
                                    <div class="space-y-0">
                                        @foreach ($brands as $brand)
                                        <label class="flex min-h-8 cursor-pointer items-center gap-[9px] rounded-lg py-[5px] pl-[5px] text-sm leading-[1.55] transition hover:bg-canvas">
                                            <input type="checkbox" name="brands[]" value="{{ $brand }}" @checked(in_array($brand, $activeFilters['brands'] ?? [], true)) x-on:change="$el.form.requestSubmit()" class="size-4 appearance-auto accent-brand focus:ring-brand">
                                            <span>{{ $brand }}</span>
                                        </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div role="group" aria-labelledby="catalog-availability-heading" class="border-t border-line py-[14px]">
                                <h3 id="catalog-availability-heading" class="mb-2.5 text-xs font-semibold uppercase leading-[1.55] tracking-[0.06em] text-muted">Availability</h3>
                                <label class="flex min-h-8 cursor-pointer items-center gap-[9px] rounded-lg py-[5px] pl-[5px] text-sm leading-[1.55] transition hover:bg-canvas">
                                    <input type="checkbox" name="in_stock" value="1" @checked(! empty($activeFilters['in_stock'])) x-on:change="$el.form.requestSubmit()" class="size-4 appearance-auto accent-brand focus:ring-brand">
                                    <span>In stock only</span>
                                </label>
                                <label class="flex min-h-8 cursor-pointer items-center gap-[9px] rounded-lg py-[5px] pl-[5px] text-sm leading-[1.55] transition hover:bg-canvas">
                                    <input type="checkbox" name="on_sale" value="1" @checked(! empty($activeFilters['on_sale'])) x-on:change="$el.form.requestSubmit()" class="size-4 appearance-auto accent-brand focus:ring-brand">
                                    <span>On sale</span>
                                </label>
                            </div>

                            <div role="group" aria-labelledby="catalog-condition-heading" class="border-t border-line py-[14px]">
                                <h3 id="catalog-condition-heading" class="mb-2.5 text-xs font-semibold uppercase leading-[1.55] tracking-[0.06em] text-muted">Condition</h3>
                                <label class="flex min-h-8 cursor-pointer items-center gap-[9px] rounded-lg py-[5px] pl-[5px] text-sm leading-[1.55] transition hover:bg-canvas">
                                    <input type="checkbox" name="conditions[]" value="new" @checked(in_array('new', $activeFilters['conditions'] ?? [], true)) x-on:change="$el.form.requestSubmit()" class="size-4 appearance-auto accent-brand focus:ring-brand">
                                    <span>New</span>
                                </label>
                                <label class="flex min-h-8 cursor-pointer items-center gap-[9px] rounded-lg py-[5px] pl-[5px] text-sm leading-[1.55] transition hover:bg-canvas">
                                    <input type="checkbox" name="conditions[]" value="refurbished" @checked(in_array('refurbished', $activeFilters['conditions'] ?? [], true)) x-on:change="$el.form.requestSubmit()" class="size-4 appearance-auto accent-brand focus:ring-brand">
                                    <span>Refurbished</span>
                                </label>
                            </div>

                            @if (! $sellerStore && $filterStores->isNotEmpty())
                                <div class="border-t border-line py-[14px]">
                                    <h3 class="text-xs font-semibold uppercase leading-[1.55] tracking-[0.06em] text-muted">Stores</h3>
                                    <ul class="mt-2 space-y-0.5">
                                        @foreach ($filterStores->take(5) as $filterStore)
                                            <x-marketplace.filter-option :item="$filterStore" :href="route('catalog.index', [...request()->except(['seller', 'page']), 'seller' => $filterStore->slug])" :clear-href="route('catalog.index', request()->except(['seller', 'page']))" :selected="request('seller') === $filterStore->slug" />
                                        @endforeach
                                    </ul>
                                    @if ($filterStores->count() > 5)
                                        <details class="mt-1 text-sm" @if ($filterStores->skip(5)->contains('slug', request('seller'))) open @endif>
                                            <summary class="cursor-pointer py-1.5 font-medium text-brand hover:text-brand-hover">More sellers</summary>
                                            <ul class="space-y-0.5 pb-1">
                                                @foreach ($filterStores->skip(5) as $filterStore)
                                                    <x-marketplace.filter-option :item="$filterStore" :href="route('catalog.index', [...request()->except(['seller', 'page']), 'seller' => $filterStore->slug])" :clear-href="route('catalog.index', request()->except(['seller', 'page']))" :selected="request('seller') === $filterStore->slug" />
                                                @endforeach
                                            </ul>
                                        </details>
                                    @endif
                                </div>
                            @endif

                        </form>

                    </aside>
                    @endif

                    {{-- Results --}}
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            @if ($isListing && ! $sellerStore)
                                <div>
                                    <h1 id="product-results-heading" class="text-[28px] font-bold leading-[1.55] tracking-[-0.02em]">{{ filled($search) ? 'Results for “'.Str::limit($search, 40).'”' : (request('category') ? ($categories->firstWhere('slug', request('category'))?->name ?? 'Marketplace') : 'Marketplace') }}</h1>
                                    <p class="text-sm leading-[1.55] text-muted">Discover products from trusted sellers · {{ number_format($products->total()) }} {{ Str::plural('result', $products->total()) }}</p>
                                </div>
                            @else
                                <p class="self-center text-sm text-muted" role="status">{{ number_format($products->total()) }} {{ Str::plural('result', $products->total()) }}</p>
                            @endif
                            <div class="flex flex-wrap items-center gap-3 sm:ml-auto">
                                @if ($showFiltersSidebar)<button type="button" @click="toggleFilters($event)" :aria-expanded="filtersOpen.toString()" aria-controls="catalog-filters" class="inline-flex min-h-11 items-center gap-2 rounded-[10px] border border-line bg-white px-4 text-sm font-semibold {{ $isListing ? 'min-[1021px]:hidden' : 'lg:hidden' }}">Filters</button>@endif
                                @if ($sellerStore && $categories->count() > 1)
                                    <button id="categories" type="button" @click="openCategories($event)" class="inline-flex min-h-11 scroll-mt-40 items-center rounded-[10px] border border-line bg-white px-4 text-sm font-semibold lg:hidden">Categories</button>
                                @endif
                                <form method="GET" action="{{ $catalogUrl }}" @submit="loading = true" class="flex items-center gap-2">
                                    @foreach (request()->except(['sort', 'page']) as $name => $value) @if (is_scalar($value) && filled($value))<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif @endforeach
                                    <select id="sort" name="sort" aria-label="Sort products" onchange="this.form.submit()" class="h-[39px] w-[172px] rounded-[10px] border-line bg-white text-sm font-medium focus:border-brand focus:ring-brand">
                                        <option value="relevance" @selected(request('sort', 'relevance') === 'relevance')>Sort: Most relevant</option>
                                        <option value="price_asc" @selected(request('sort') === 'price_asc')>Price: low to high</option>
                                        <option value="price_desc" @selected(request('sort') === 'price_desc')>Price: high to low</option>
                                        <option value="top_rated" @selected(request('sort') === 'top_rated')>Top rated</option>
                                        <option value="newest" @selected(request('sort') === 'newest')>Newest</option>
                                    </select>
                                </form>
                                <div class="flex items-center gap-3" role="group" aria-label="Product view">
                                    <button type="button" @click="viewMode = 'grid'" :aria-pressed="viewMode === 'grid'" aria-label="Grid view" :class="viewMode === 'grid' ? 'border-ink bg-ink text-white' : 'bg-white text-muted hover:text-ink'" class="grid size-[38px] place-items-center rounded-[9px] border border-line transition"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></button>
                                    <button type="button" @click="viewMode = 'list'" :aria-pressed="viewMode === 'list'" aria-label="List view" :class="viewMode === 'list' ? 'border-ink bg-ink text-white' : 'bg-white text-muted hover:text-ink'" class="grid size-[38px] place-items-center rounded-[9px] border border-line transition"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5h12M9 12h12M9 19h12"/><path d="M3 5h2M3 12h2M3 19h2"/></svg></button>
                                </div>
                            </div>
                        </div>
                        @if ($isListing && ! $sellerStore && ! empty($filterChips))
                            <div class="mt-[14px] flex flex-wrap items-center gap-2" aria-label="Active filters">
                                @foreach ($filterChips as $chip)
                                    <a href="{{ $chip['url'] }}" class="inline-flex min-h-[34px] items-center gap-[7px] rounded-full border border-line bg-white py-[6px] pl-3 pr-1.5 text-[13px] leading-[20px] font-medium hover:border-brand hover:text-brand">{{ $chip['label'] }} <span aria-hidden="true" class="grid size-[22px] shrink-0 place-items-center rounded-full bg-canvas text-muted">×</span><span class="sr-only">Remove filter {{ $chip['label'] }}</span></a>
                                @endforeach

                            </div>
                        @endif
                        <div x-show="loading" x-cloak class="{{ $isListing && ! $sellerStore && empty($filterChips) ? 'mt-7' : 'mt-[14px]' }} grid grid-cols-2 gap-[14px] min-[641px]:grid-cols-3 min-[1021px]:grid-cols-4" aria-hidden="true">
                            @for ($i = 0; $i < 8; $i++)
                                <div class="overflow-hidden rounded-[14px] border border-line bg-white"><div class="aspect-square animate-pulse bg-slate-200"></div><div class="space-y-2 p-[13px]"><div class="h-4 animate-pulse rounded bg-slate-200"></div><div class="h-4 w-2/3 animate-pulse rounded bg-slate-200"></div></div></div>
                            @endfor
                        </div>
                        <p x-show="loading" x-cloak class="mt-3 text-[13px] text-muted" role="status" aria-live="polite">Loading curated results&hellip;</p>

                        <div x-show="!loading" class="{{ $isListing && ! $sellerStore && empty($filterChips) ? 'mt-7' : 'mt-[14px]' }}">
                            @if ($products->isNotEmpty())
                                <div id="catalog-products" :data-view="viewMode" class="grid grid-cols-2 gap-[14px] min-[641px]:grid-cols-3 min-[1021px]:grid-cols-4">
                                    @foreach ($products as $product)
                                        <x-marketplace.top-product-card :product="$product" />
                                    @endforeach
                                </div>
                                <div class="mt-10">{{ $products->onEachSide(1)->links('components.marketplace.pagination') }}</div>
                            @else
                                @php($hasActiveFilters = ! empty($activeFilters) || filled(request('category')) || filled(request('seller')))
                                <div class="rounded-[14px] border border-dashed border-line bg-white p-12 text-center">
                                    <h3 class="text-lg font-semibold text-ink">@if (filled($search))No results for &ldquo;{{ Str::limit($search, 60) }}&rdquo;@elseif ($hasActiveFilters)Nothing matched these filters.@else No products yet.@endif</h3>
                                    <p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-muted">@if (filled($search))Try fewer filters, check spelling, or browse trending categories.@elseif ($hasActiveFilters)Try removing a filter or browse available products.@else Products from approved sellers will appear here.@endif</p>
                                    <a href="{{ $clearAllUrl }}" class="mt-6 inline-flex min-h-11 items-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white hover:bg-brand-hover">{{ filled($search) || $hasActiveFilters ? 'Clear filters' : 'Browse marketplace' }}</a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </section>

            {{-- All categories dialog --}}
            @if ($categories->count() > 1)
                <div x-show="categoriesOpen" x-cloak x-on:keydown.escape.stop="closeCategories()" x-on:keydown.tab="trapCategories($event)" class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="all-categories-heading">
                    <div @click="closeCategories()" class="absolute inset-0 bg-slate-950/35"></div>
                    <div x-ref="categoryDialog" class="absolute inset-x-0 bottom-0 max-h-[88vh] overflow-y-auto rounded-t-[16px] bg-canvas p-5 shadow-[0_-18px_40px_-24px_rgba(15,23,42,0.45)] lg:inset-x-auto lg:bottom-auto lg:left-1/2 lg:top-1/2 lg:max-h-[80vh] lg:w-[min(58rem,calc(100vw-3rem))] lg:-translate-x-1/2 lg:-translate-y-1/2 lg:rounded-[16px] lg:p-7">
                        <div class="mx-auto mb-5 h-1.5 w-12 rounded-full bg-slate-300 lg:hidden"></div>
                        <div class="flex items-start justify-between gap-4"><div><h2 id="all-categories-heading" class="text-2xl font-bold tracking-[-0.02em]">All categories</h2><p class="mt-1 text-sm text-muted">Browse products by category.</p></div><button type="button" data-category-focus @click="closeCategories()" class="inline-flex size-11 shrink-0 items-center justify-center rounded-[10px] border border-line bg-white text-muted transition hover:text-ink" aria-label="Close categories"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg></button></div>
                        <div class="mt-7 space-y-7">
                            @foreach ($categoryGroups as $groupName => $groupCategories)
                                <section aria-labelledby="category-group-{{ Str::slug($groupName) }}"><h3 id="category-group-{{ Str::slug($groupName) }}" class="text-sm font-semibold">{{ $groupName }}</h3><div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">@foreach ($groupCategories as $category)<x-marketplace.category-card :category="$category" :href="route($catalogRouteName, [...$catalogRouteParameters, ...array_diff_key($catalogQueryParams, ['category' => true]), 'category' => $category->slug])" :selected="request('category') === $category->slug" />@endforeach</div></section>
                            @endforeach
                            @if ($uncategorizedCategories->isNotEmpty())
                                <section aria-labelledby="category-group-more"><h3 id="category-group-more" class="text-sm font-semibold">More categories</h3><div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">@foreach ($uncategorizedCategories as $category)<x-marketplace.category-card :category="$category" :href="route($catalogRouteName, [...$catalogRouteParameters, ...array_diff_key($catalogQueryParams, ['category' => true]), 'category' => $category->slug])" :selected="request('category') === $category->slug" />@endforeach</div></section>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </main>

    </div>
<x-cookie-consent />
@livewireScripts
</body>
</html>
