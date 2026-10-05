<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <meta name="clerk-publishable-key" content="{{ config('services.clerk.publishable_key') }}">
    <meta name="clerk-sign-in-url" content="{{ config('services.clerk.sign_in_url') }}">
    <meta name="clerk-sign-up-url" content="{{ config('services.clerk.sign_up_url') }}">
    <title>{{ $sellerStore ? $sellerStore->name.' storefront' : 'Explore Products' }} · JMMart</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="marketplace-catalog bg-[#f7f7f2] font-sans text-slate-900 antialiased">
    @php
        $isBuyer = Auth::check() && Auth::user()->account_type === 'buyer' && Auth::user()->hasVerifiedClerkEmail();
        $catalogQueryParams = array_filter(request()->only(['q', 'category']), fn ($value) => filled($value));
        $catalogRouteName = $sellerStore ? 'catalog.store' : 'catalog.index';
        $catalogRouteParameters = $sellerStore ? ['store' => $sellerStore] : [];
        $catalogUrl = route($catalogRouteName, $catalogRouteParameters);
        $clearSearchUrl = route($catalogRouteName, [...$catalogRouteParameters, ...request()->except(['q', 'page'])]);
    @endphp

    <x-marketplace.header :search="$search" />

    <div x-data="{
        loading: false,
        accountOpen: false,
        menuOpen: false,
        categoriesOpen: false,
        categoryTrigger: null,
        categoryCanScrollBackward: false,
        categoryCanScrollForward: false,
        updateCategoryControls() {
            const rail = this.$refs.categoryRail;

            if (!rail) {
                return;
            }

            this.categoryCanScrollBackward = rail.scrollLeft > 2;
            this.categoryCanScrollForward = rail.scrollLeft + rail.clientWidth < rail.scrollWidth - 2;
        },
        scrollCategories(direction) {
            const rail = this.$refs.categoryRail;

            if (!rail) {
                return;
            }

            rail.scrollBy({ left: direction * Math.max(rail.clientWidth * 0.75, 280), behavior: 'smooth' });
            window.setTimeout(() => this.updateCategoryControls(), 350);
        },
        openCategories(event) {
            this.categoryTrigger = event.currentTarget;
            this.categoriesOpen = true;
            this.$nextTick(() => this.$refs.categoryDialog.querySelector('[data-category-focus]').focus());
        },
        closeCategories() {
            this.categoriesOpen = false;
            this.$nextTick(() => this.categoryTrigger?.focus());
        },
    }" x-init="$watch('categoriesOpen', value => document.body.classList.toggle('overflow-hidden', value)); $nextTick(() => updateCategoryControls())" @resize.window.debounce.150ms="updateCategoryControls()" class="min-h-screen">
        <header class="hidden border-b border-slate-200/80 bg-[#f7f7f2]/95 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-3 px-4 sm:px-6 lg:grid lg:grid-cols-[auto_minmax(17rem,22rem)_1fr_auto] lg:gap-7 lg:px-8">
                <a href="{{ route('catalog.index') }}" class="shrink-0 text-base font-extrabold uppercase tracking-[0.16em] text-slate-950" aria-label="JMMart products">
                    JMMart
                </a>
                <form method="GET" action="{{ request()->routeIs('catalog.store') ? route('catalog.store', $sellerStore) : route('catalog.index') }}" @submit="loading = true" class="hidden lg:block">
                    <label for="header-product-search" class="flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-slate-500 shadow-sm transition focus-within:border-slate-500 focus-within:ring-2 focus-within:ring-slate-900/15">
                        <svg class="h-4 w-4 shrink-0 text-slate-800" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                        <input id="header-product-search" name="q" value="{{ $search }}" placeholder="Search products, sellers, or categories..." class="min-w-0 flex-1 border-0 bg-transparent p-0 text-xs text-slate-900 placeholder:text-slate-500 focus:ring-0">
                    </label>
                </form>
                <nav class="hidden items-center justify-end gap-6 text-sm font-medium text-slate-700 xl:flex" aria-label="Marketplace navigation"><a href="#categories" class="transition hover:text-emerald-800">Categories</a><a href="{{ route('catalog.index') }}" class="transition hover:text-emerald-800">Browse</a>@if ($isBuyer)<a href="{{ route('orders.index') }}" class="transition hover:text-emerald-800">Orders</a>@endif</nav>
                <div class="flex items-center gap-3 text-sm font-medium">
                    <button type="button" @click="menuOpen = ! menuOpen" :aria-expanded="menuOpen.toString()" aria-controls="marketplace-menu" class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-emerald-700 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 lg:hidden" aria-label="Open marketplace menu"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
                    @auth
                        @if ($isBuyer)
                            <a href="{{ route('cart.index') }}" class="relative inline-flex min-h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-slate-700 shadow-sm transition hover:border-emerald-700 hover:text-emerald-800" aria-label="Cart, {{ $cartItemCount }} items">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 3h2l2.4 12.1a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 2-1.6L21 7H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
                                <span class="hidden sm:inline">Cart</span>
                                @if ($cartItemCount > 0)<span class="absolute -right-2 -top-2 inline-flex min-w-5 items-center justify-center rounded-full bg-emerald-700 px-1.5 py-0.5 text-xs font-bold text-white shadow-sm">{{ $cartItemCount }}</span>@endif
                            </a>
                        @endif
                        <div class="relative" @click.outside="accountOpen = false">
                            <button type="button" @click="accountOpen = ! accountOpen" :aria-expanded="accountOpen.toString()" aria-controls="account-menu" class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-emerald-700 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2" aria-label="Open account menu">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="3.25"/><path d="M4.5 20c.8-3.45 3.35-5.25 7.5-5.25s6.7 1.8 7.5 5.25"/></svg>
                            </button>
                            <div id="account-menu" x-show="accountOpen" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute right-0 z-30 mt-2 w-48 overflow-hidden rounded-xl border border-slate-200 bg-white p-1 shadow-lg">
                                <a href="{{ route('profile.edit') }}" class="flex min-h-10 items-center rounded-lg px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-emerald-700">My account</a>
                                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="flex min-h-10 w-full items-center rounded-lg px-3 text-left text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-emerald-700">Log out</button></form>
                            </div>
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="hidden min-h-11 items-center text-slate-600 hover:text-slate-950 sm:inline-flex">Log in</a>
                        <a href="{{ route('register') }}" class="inline-flex min-h-11 items-center rounded-xl bg-slate-950 px-4 text-white shadow-sm transition hover:bg-slate-800">Sign up</a>
                    @endauth
                </div>
            </div>
            <nav id="marketplace-menu" x-show="menuOpen" x-cloak @keydown.escape.window="menuOpen = false" class="border-t border-slate-200 bg-[#f7f7f2] px-4 py-3 lg:hidden" aria-label="Marketplace menu">
                <div class="mx-auto flex max-w-7xl flex-wrap gap-2"><a href="#categories" @click="menuOpen = false" class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-semibold text-slate-700 hover:bg-white">Categories</a><a href="{{ route('catalog.index') }}" class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-semibold text-slate-700 hover:bg-white">Browse</a>@if ($isBuyer)<a href="{{ route('orders.index') }}" class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-semibold text-slate-700 hover:bg-white">Orders</a>@endif</div>
            </nav>
        </header>

        @if ($isSellerPreview)
            <div class="border-b border-emerald-200 bg-emerald-50 px-4 py-2.5">
                <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 text-sm text-emerald-950">
                    <p class="font-semibold">You’re viewing your public storefront</p>
                    <div class="flex items-center gap-4">
                        <a href="{{ route('seller.dashboard') }}" class="font-semibold underline decoration-emerald-400 underline-offset-4 focus:outline-none focus:ring-2 focus:ring-emerald-700">Back to Seller Center</a>
                        <a href="{{ route('seller.products.index') }}" class="font-semibold underline decoration-emerald-400 underline-offset-4 focus:outline-none focus:ring-2 focus:ring-emerald-700">Manage products</a>
                    </div>
                </div>
            </div>
        @endif

        <main class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div role="alert" class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900">
                    {{ $errors->first() }}
                </div>
            @endif
            @if ($sellerStore)
                <section class="py-8 lg:py-12">
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        <div class="h-20 bg-[linear-gradient(115deg,_#e6f1e9,_#f9f6ef_58%,_#e7eee9)] sm:h-24"></div>
                        <div class="px-5 pb-5 sm:px-7 sm:pb-6">
                            <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                                <div class="min-w-0">
                                    <div class="-mt-10 flex h-20 w-20 items-center justify-center overflow-hidden rounded-2xl border-4 border-white bg-emerald-800 text-2xl font-bold text-white shadow-sm">
                                        @if ($sellerStore->logo_path)<img src="{{ Storage::disk('public')->url($sellerStore->logo_path) }}" alt="{{ $sellerStore->name }} logo" class="h-full w-full object-cover">@else{{ Str::upper(Str::substr($sellerStore->name, 0, 1)) }}@endif
                                    </div>
                                    <div class="mt-3 flex flex-wrap items-center gap-2"><h1 class="break-words text-3xl font-bold tracking-[-0.03em] text-slate-950 sm:text-4xl">{{ $sellerStore->name }}</h1><span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-900 ring-1 ring-inset ring-emerald-200"><span class="h-1.5 w-1.5 rounded-full bg-emerald-600" aria-hidden="true"></span>Open</span></div>
                                    @if (filled($sellerStore->description))<p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">{{ $sellerStore->description }}</p>@endif
                                    <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-sm text-slate-600"><span class="font-medium text-slate-800">{{ number_format($sellerStore->public_products_count) }} {{ Str::plural('product', $sellerStore->public_products_count) }}</span>@if (filled($sellerStore->city))<span>{{ $sellerStore->city }}</span>@endif@if ($sellerStore->created_at)<span>Joined {{ $sellerStore->created_at->format('M Y') }}</span>@endif</div>
                                </div>
                                <div class="flex shrink-0 flex-wrap gap-2"><a href="#product-results" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-emerald-700 bg-emerald-50 px-4 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">Ask about a product</a><a href="{{ route('catalog.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-stone-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">Browse marketplace</a></div>
                            </div>
                            <form method="GET" action="{{ $catalogUrl }}" @submit="loading = true" class="mt-6 flex w-full gap-2"><label for="store-product-search" class="flex min-h-12 min-w-0 flex-1 items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 text-slate-600 transition focus-within:border-emerald-600 focus-within:bg-white focus-within:ring-2 focus-within:ring-emerald-700/20"><svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input id="store-product-search" name="q" value="{{ $search }}" placeholder="Search this store" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-slate-950 placeholder:text-slate-500 focus:ring-0"></label>@if ($search)<a href="{{ $clearSearchUrl }}" class="inline-flex min-h-12 items-center rounded-xl px-3 text-sm font-semibold text-slate-600 hover:text-slate-950">Clear</a>@endif<button class="min-h-12 rounded-xl bg-slate-950 px-5 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">Search</button></form>
                        </div>
                    </div>
                </section>
            @endif

            <form method="GET" action="{{ request()->routeIs('catalog.store') ? route('catalog.store', $sellerStore) : route('catalog.index') }}" @submit="loading = true" class="sticky top-0 z-20 hidden -mx-4 border-y border-slate-200 bg-[#f7f7f2]/95 px-4 py-3 backdrop-blur lg:hidden">
                <div class="flex gap-2 rounded-2xl border border-slate-300 bg-white p-1.5 shadow-[0_10px_30px_-20px_rgba(15,23,42,0.35)]">
                    <label class="flex min-w-0 flex-1 items-center gap-3 rounded-xl px-3 focus-within:ring-2 focus-within:ring-emerald-700" for="product-search">
                        <svg class="h-5 w-5 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                        <input id="product-search" name="q" value="{{ $search }}" placeholder="Search products, sellers, or categories" class="min-w-0 flex-1 border-0 bg-transparent px-0 text-sm text-slate-900 placeholder:text-slate-400 focus:ring-0 sm:text-base">
                    </label>
                    <button class="min-h-11 shrink-0 rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white transition hover:bg-slate-800">Search</button>
                </div>
            </form>

            @if (! $sellerStore)
                <section class="relative left-1/2 mt-5 w-screen -translate-x-1/2 overflow-hidden bg-[#e9e2d5] sm:mt-7" aria-labelledby="marketplace-banner-heading">
                    <img src="{{ asset('images/banners/marketplace-discovery.png') }}" alt="A collection of marketplace products" class="absolute inset-0 h-full w-full object-cover object-[70%_center] sm:object-center" />
                    <div class="absolute inset-0 bg-[linear-gradient(90deg,rgba(255,252,246,0.97)_0%,rgba(255,252,246,0.9)_42%,rgba(255,252,246,0.18)_74%,rgba(255,252,246,0)_100%)]"></div>
                    <div class="relative mx-auto flex min-h-72 max-w-[90rem] items-center px-4 py-10 sm:min-h-80 sm:px-6 lg:min-h-96 lg:px-8">
                        <div class="max-w-md">
                            <h1 id="marketplace-banner-heading" class="text-4xl font-bold tracking-[-0.035em] text-slate-950 sm:text-5xl">Find something made for you.</h1>
                            <p class="mt-4 max-w-sm text-base leading-7 text-slate-700">Explore trusted local shops, fresh finds, and useful essentials in one place.</p>
                            <a href="#categories" class="mt-6 inline-flex min-h-11 items-center rounded-xl bg-slate-950 px-5 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">Browse categories</a>
                        </div>
                    </div>
                </section>
            @endif

            @if ($categories->count() > 1)
                <section id="categories" class="relative left-1/2 mt-9 w-screen -translate-x-1/2 bg-white py-8 sm:py-10" aria-labelledby="category-heading">
                    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div><h2 id="category-heading" class="text-2xl font-bold tracking-[-0.02em] text-slate-950">Shop by category</h2><p class="mt-1 text-sm text-slate-600">Find what you need faster.</p></div>
                        @if ($categories->count() > 8)<button type="button" @click="openCategories($event)" class="inline-flex min-h-11 items-center rounded-xl px-3 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-50 hover:text-emerald-950 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">View all categories</button>@endif
                    </div>
                    <div class="relative mt-5">
                        <div x-ref="categoryRail" @scroll.debounce.50ms="updateCategoryControls()" class="flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                            @foreach ($categories as $category)
                                <div class="w-[calc((100%-0.75rem)/2)] shrink-0 snap-start sm:w-[calc((100%-2.25rem)/4)] lg:w-[calc((100%-3.75rem)/6)] xl:w-[calc((100%-5.25rem)/8)]">
                                    <x-marketplace.category-card :category="$category" :href="route($catalogRouteName, [...$catalogRouteParameters, ...array_diff_key($catalogQueryParams, ['category' => true]), 'category' => $category->slug])" :selected="request('category') === $category->slug" />
                                </div>
                            @endforeach
                        </div>
                        <button type="button" @click="scrollCategories(-1)" :disabled="!categoryCanScrollBackward" :class="categoryCanScrollBackward ? 'opacity-100' : 'cursor-not-allowed opacity-35'" class="absolute left-0 top-1/2 z-10 inline-flex h-12 w-12 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-white text-slate-500 shadow-[0_7px_18px_rgba(15,23,42,0.15)] transition hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2" aria-label="Show previous categories">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" aria-hidden="true"><path d="m14.5 5-7 7 7 7"/></svg>
                        </button>
                        <button type="button" @click="scrollCategories(1)" :disabled="!categoryCanScrollForward" :class="categoryCanScrollForward ? 'opacity-100' : 'cursor-not-allowed opacity-35'" class="absolute right-0 top-1/2 z-10 inline-flex h-12 w-12 translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-white text-slate-500 shadow-[0_7px_18px_rgba(15,23,42,0.15)] transition hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2" aria-label="Show next categories">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" aria-hidden="true"><path d="m9.5 5 7 7-7 7"/></svg>
                        </button>
                    </div>
                    </div>
                </section>

            @endif

            @if ($topProducts->isNotEmpty())
                <section class="mt-10" aria-labelledby="top-products-heading">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div><h2 id="top-products-heading" class="text-2xl font-bold tracking-[-0.02em] text-slate-950">Top Products</h2><p class="mt-1 text-sm text-slate-600">Popular picks from our stores.</p></div>
                        <a href="{{ route('catalog.top-products') }}" class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-50 hover:text-emerald-950 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700">View all</a>
                    </div>
                    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-6">
                        @foreach ($topProducts as $product)
                            <x-marketplace.top-product-card :product="$product" />
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="mt-7" aria-labelledby="product-results">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0"><h2 id="product-results" class="text-2xl font-bold tracking-[-0.02em] text-slate-950">Products</h2><p class="mt-1 text-sm text-slate-600">{{ number_format($products->total()) }} {{ Str::plural('product', $products->total()) }}@if ($search) matching “{{ Str::limit($search, 32) }}”@endif</p></div>
                        <form method="GET" action="{{ request()->routeIs('catalog.store') ? route('catalog.store', $sellerStore) : route('catalog.index') }}" @submit="loading = true" class="flex items-center gap-2">
                            @foreach ($catalogQueryParams as $name => $value) @if (is_scalar($value))<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif @endforeach
                            <label for="sort" class="text-sm font-medium text-slate-600">Sort</label><select id="sort" name="sort" onchange="this.form.submit()" class="min-h-11 rounded-xl border-slate-300 bg-white text-sm font-medium focus:border-emerald-700 focus:ring-emerald-700"><option value="relevance" @selected(request('sort', 'relevance') === 'relevance')>Relevance</option><option value="newest" @selected(request('sort') === 'newest')>Newest</option><option value="price_asc" @selected(request('sort') === 'price_asc')>Price: low to high</option><option value="price_desc" @selected(request('sort') === 'price_desc')>Price: high to low</option></select>
                        </form>
                    </div>

                    <div x-show="loading" x-cloak class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-6"><template x-for="item in 12"><div class="bg-white"><div class="aspect-square animate-pulse rounded-md bg-slate-200"></div><div class="space-y-2 px-2 pb-3 pt-2"><div class="h-4 animate-pulse rounded bg-slate-200"></div><div class="h-4 w-2/3 animate-pulse rounded bg-slate-200"></div><div class="h-3 w-4/5 animate-pulse rounded bg-slate-200"></div></div></div></template></div>
                    <div x-show="!loading" class="mt-6">
                        @if ($products->isNotEmpty())
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-6">
                                @foreach ($products as $product)
                                    <article class="group min-w-0 bg-white">
                                        <a href="{{ route('catalog.show', [$product->store->slug, $product->slug]) }}" class="block aspect-square overflow-hidden rounded-md bg-slate-50 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-inset">
                                            @if ($product->thumbnail)<img src="{{ Storage::disk('product_images')->url($product->thumbnail->path) }}" alt="{{ $product->name }}" width="320" height="320" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">@else<div class="flex h-full flex-col items-center justify-center gap-2 px-4 text-center text-slate-500"><span class="grid h-10 w-10 place-items-center rounded-full bg-white text-base font-semibold">{{ Str::upper(Str::substr($product->store->name, 0, 1)) }}</span><span class="text-xs font-medium">Product image</span></div>@endif
                                        </a>
                                        <div class="px-2 pb-3 pt-2">
                                            <h3 class="min-h-10 break-words text-sm font-normal leading-5 text-slate-900"><a href="{{ route('catalog.show', [$product->store->slug, $product->slug]) }}" title="{{ $product->name }}" class="[display:-webkit-box] overflow-hidden transition [-webkit-box-orient:vertical] [-webkit-line-clamp:2] hover:text-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">{{ $product->name }}</a></h3>
                                            <p class="mt-1 whitespace-nowrap text-lg font-semibold leading-5 tabular-nums text-rose-600">₱{{ number_format($product->price, 2) }}</p>
                                            <div class="mt-2 flex items-center gap-1.5 whitespace-nowrap text-xs leading-4 text-slate-500">
                                                @if ($product->published_reviews_count)
                                                    <svg viewBox="0 0 20 20" class="size-3.5 shrink-0 fill-amber-400" aria-hidden="true"><path d="m10 1.6 2.5 5.1 5.6.8-4 3.9.9 5.5-5-2.7-5 2.7.9-5.5-4-3.9 5.6-.8L10 1.6Z" /></svg><span>{{ number_format($product->published_reviews_avg_rating, 1) }}</span><span class="text-slate-400">({{ number_format($product->published_reviews_count) }})</span>
                                                @else
                                                    <span>No ratings yet</span>
                                                @endif
                                                <span class="h-3.5 border-l border-slate-300" aria-hidden="true"></span><span>{{ number_format((int) ($product->sold_quantity ?? 0)) }} Sold</span>
                                            </div>
                                        </div>
                                        @if ($isBuyer)
                                            <form method="POST" action="{{ route('cart.store', $product) }}" @submit="loading = true" class="px-2 pb-3 pt-1.5">
                                                @csrf
                                                <input type="hidden" name="quantity" value="1">
                                                @if ($product->stock_quantity > 0)
                                                    <button class="min-h-10 w-full rounded-lg bg-emerald-700 px-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">Add to cart</button>
                                                @else
                                                    <button disabled class="min-h-10 w-full cursor-not-allowed rounded-lg bg-slate-100 px-3 text-sm font-semibold text-slate-500">Sold out</button>
                                                @endif
                                            </form>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                            <div class="mt-10">{{ $products->onEachSide(1)->links() }}</div>
                        @else
                            <div class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center"><div class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-emerald-50 text-emerald-800"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg></div><h3 class="mt-5 text-lg font-semibold text-slate-950">Nothing matched this search.</h3><p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-slate-500">Try a broader search or choose a different category to explore available products.</p><a href="{{ $catalogUrl }}" class="mt-6 inline-flex min-h-11 items-center rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">View all products</a></div>
                        @endif
                    </div>
                </section>
            @if ($categories->count() > 1)
                <div x-show="categoriesOpen" x-cloak @keydown.escape.window="closeCategories()" class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="all-categories-heading">
                    <div @click="closeCategories()" class="absolute inset-0 bg-slate-950/35"></div>
                    <div x-ref="categoryDialog" class="absolute inset-x-0 bottom-0 max-h-[88vh] overflow-y-auto rounded-t-2xl bg-[#f7f7f2] p-5 shadow-[0_-18px_40px_-24px_rgba(15,23,42,0.45)] lg:inset-x-auto lg:bottom-auto lg:left-1/2 lg:top-1/2 lg:max-h-[80vh] lg:w-[min(58rem,calc(100vw-3rem))] lg:-translate-x-1/2 lg:-translate-y-1/2 lg:rounded-2xl lg:p-7">
                        <div class="mx-auto mb-5 h-1.5 w-12 rounded-full bg-slate-300 lg:hidden"></div>
                        <div class="flex items-start justify-between gap-4"><div><h2 id="all-categories-heading" class="text-2xl font-bold tracking-[-0.02em] text-slate-950">All categories</h2><p class="mt-1 text-sm text-slate-600">Browse public marketplace products by category.</p></div><button type="button" data-category-focus @click="closeCategories()" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-300 bg-white text-slate-700 transition hover:border-emerald-500 hover:text-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2" aria-label="Close categories"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg></button></div>
                        <div class="mt-7 space-y-7">
                            @foreach ($categoryGroups as $groupName => $groupCategories)
                                <section aria-labelledby="category-group-{{ Str::slug($groupName) }}"><h3 id="category-group-{{ Str::slug($groupName) }}" class="text-sm font-semibold text-slate-900">{{ $groupName }}</h3><div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">@foreach ($groupCategories as $category)<x-marketplace.category-card :category="$category" :href="route($catalogRouteName, [...$catalogRouteParameters, ...array_diff_key($catalogQueryParams, ['category' => true]), 'category' => $category->slug])" :selected="request('category') === $category->slug" />@endforeach</div></section>
                            @endforeach
                            @if ($uncategorizedCategories->isNotEmpty())
                                <section aria-labelledby="category-group-more"><h3 id="category-group-more" class="text-sm font-semibold text-slate-900">More categories</h3><div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">@foreach ($uncategorizedCategories as $category)<x-marketplace.category-card :category="$category" :href="route($catalogRouteName, [...$catalogRouteParameters, ...array_diff_key($catalogQueryParams, ['category' => true]), 'category' => $category->slug])" :selected="request('category') === $category->slug" />@endforeach</div></section>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </main>
    </div>
@livewireScripts
</body>
</html>
