<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $sellerStore ? $sellerStore->name.' storefront' : 'Explore Products' }} · JMMart</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="marketplace-catalog bg-[#f7f7f2] font-sans text-slate-900 antialiased">
    @php
        $isBuyer = Auth::check() && Auth::user()->account_type === 'buyer' && Auth::user()->hasVerifiedEmail();
        $hasFilters = $search !== '' || request('category') || request('seller') || request('price_min') || request('price_max') || request()->boolean('available');
        $filterParams = request()->except(['page', 'sort']);
    @endphp

    <div x-data="{ filtersOpen: false, loading: false, accountOpen: false, menuOpen: false }" class="min-h-screen">
        <header class="border-b border-slate-200/80 bg-[#f7f7f2]/95 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-3 px-4 sm:px-6 lg:grid lg:grid-cols-[auto_minmax(17rem,22rem)_1fr_auto] lg:gap-7 lg:px-8">
                <a href="{{ route('catalog.index') }}" class="shrink-0 font-serif text-lg font-bold uppercase tracking-[0.22em] text-slate-950" aria-label="JMMart products">
                    JMMart
                </a>
                <form method="GET" action="{{ request()->routeIs('catalog.store') ? route('catalog.store', $sellerStore) : route('catalog.index') }}" @submit="loading = true" class="hidden lg:block">
                    @if ($sellerStore)<input type="hidden" name="seller" value="{{ $sellerStore->slug }}">@endif
                    <label for="header-product-search" class="flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-slate-500 shadow-sm transition focus-within:border-slate-500 focus-within:ring-2 focus-within:ring-slate-900/15">
                        <svg class="h-4 w-4 shrink-0 text-slate-800" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                        <input id="header-product-search" name="q" value="{{ $search }}" placeholder="Search for products, stores, or ideas..." class="min-w-0 flex-1 border-0 bg-transparent p-0 text-xs text-slate-900 placeholder:text-slate-500 focus:ring-0">
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

        <main class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
            <section class="grid gap-7 py-8 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-end lg:py-12">
                <div>
                    <h1 class="max-w-2xl font-serif text-4xl font-semibold tracking-[-0.04em] text-slate-950 sm:text-5xl">{{ $sellerStore ? $sellerStore->name : 'Discover products' }}</h1>
                    <p class="mt-4 max-w-xl text-base leading-7 text-slate-600">{{ $sellerStore ? 'Everything currently available from this independent seller.' : 'Considered goods from independent stores, all in one calm, searchable place.' }}</p>
                </div>
                @if ($sellerStore)
                    <a href="{{ route('catalog.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-950">Browse every seller</a>
                @endif
            </section>

            <form method="GET" action="{{ request()->routeIs('catalog.store') ? route('catalog.store', $sellerStore) : route('catalog.index') }}" @submit="loading = true" class="sticky top-0 z-20 -mx-4 border-y border-slate-200 bg-[#f7f7f2]/95 px-4 py-3 backdrop-blur lg:hidden">
                @if ($sellerStore)<input type="hidden" name="seller" value="{{ $sellerStore->slug }}">@endif
                <div class="flex gap-2 rounded-2xl border border-slate-300 bg-white p-1.5 shadow-[0_10px_30px_-20px_rgba(15,23,42,0.35)]">
                    <label class="flex min-w-0 flex-1 items-center gap-3 rounded-xl px-3 focus-within:ring-2 focus-within:ring-emerald-700" for="product-search">
                        <svg class="h-5 w-5 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                        <input id="product-search" name="q" value="{{ $search }}" placeholder="Search products, stores, or ideas" class="min-w-0 flex-1 border-0 bg-transparent px-0 text-sm text-slate-900 placeholder:text-slate-400 focus:ring-0 sm:text-base">
                    </label>
                    <button class="hidden min-h-11 rounded-xl bg-emerald-700 px-5 text-sm font-semibold text-white transition hover:bg-emerald-800 sm:block">Search</button>
                    <button class="inline-flex min-h-11 items-center rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white sm:hidden">Search</button>
                </div>
            </form>

            <nav id="categories" aria-label="Product categories" class="mt-8 flex gap-2 overflow-x-auto border-b border-slate-200 pb-4 [scrollbar-width:none]">
                <a href="{{ route('catalog.index', request()->except(['category', 'page'])) }}" class="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-full px-4 text-sm font-semibold {{ !request('category') ? 'bg-slate-950 text-white' : 'border border-slate-300 bg-white text-slate-600 hover:border-slate-950' }}"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></svg>All products</a>
                @foreach ($categories as $category)
                    <a href="{{ route('catalog.index', [...request()->except(['category', 'page']), 'category' => $category->slug]) }}" class="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-full px-4 text-sm font-semibold {{ request('category') === $category->slug ? 'bg-slate-950 text-white' : 'border border-slate-300 bg-white text-slate-600 hover:border-slate-950' }}"><x-category-icon :category="$category" />{{ $category->name }}</a>
                @endforeach
            </nav>

            <div class="mt-6">
                <aside class="hidden">
                    <div class="sticky top-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_12px_30px_-24px_rgba(15,23,42,0.45)]">
                        <div class="flex items-center justify-between"><h2 class="font-semibold text-slate-950">Filters</h2>@if ($hasFilters)<a href="{{ route('catalog.index') }}" class="text-xs font-semibold text-emerald-700 hover:text-emerald-900">Clear all</a>@endif</div>
                        <form method="GET" action="{{ request()->routeIs('catalog.store') ? route('catalog.store', $sellerStore) : route('catalog.index') }}" @submit="loading = true" class="mt-5 space-y-6">
                            @if ($sellerStore)<input type="hidden" name="seller" value="{{ $sellerStore->slug }}">@endif
                            <input type="hidden" name="q" value="{{ $search }}">
                            <fieldset><legend class="text-sm font-semibold text-slate-800">Price</legend><div class="mt-3 grid grid-cols-2 gap-2"><input name="price_min" value="{{ request('price_min') }}" type="number" min="0" inputmode="decimal" placeholder="{{ $priceBounds?->minimum ? 'From ₱'.number_format($priceBounds->minimum) : 'Min' }}" class="min-w-0 rounded-lg border-slate-300 text-sm focus:border-emerald-700 focus:ring-emerald-700"><input name="price_max" value="{{ request('price_max') }}" type="number" min="0" inputmode="decimal" placeholder="{{ $priceBounds?->maximum ? 'To ₱'.number_format($priceBounds->maximum) : 'Max' }}" class="min-w-0 rounded-lg border-slate-300 text-sm focus:border-emerald-700 focus:ring-emerald-700"></div></fieldset>
                            <fieldset><legend class="text-sm font-semibold text-slate-800">Seller</legend><select name="seller" class="mt-3 w-full rounded-lg border-slate-300 text-sm focus:border-emerald-700 focus:ring-emerald-700" @disabled($sellerStore)><option value="">All sellers</option>@foreach ($sellers as $seller)<option value="{{ $seller->slug }}" @selected(($sellerStore?->slug ?? request('seller')) === $seller->slug)>{{ $seller->name }}</option>@endforeach</select></fieldset>
                            <fieldset><legend class="text-sm font-semibold text-slate-800">Availability</legend><label class="mt-3 flex min-h-10 items-center gap-3 text-sm text-slate-600"><input type="checkbox" name="available" value="1" @checked(request()->boolean('available')) class="rounded border-slate-300 text-emerald-700 focus:ring-emerald-700">In stock only</label></fieldset>
                            <button class="min-h-11 w-full rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">Apply filters</button>
                        </form>
                    </div>
                </aside>

                <section aria-labelledby="product-results">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div><h2 id="product-results" class="text-xl font-semibold tracking-[-0.02em] text-slate-950">Products</h2><p class="mt-1 text-sm text-slate-500">{{ $products->total() }} {{ Str::plural('product', $products->total()) }} from {{ $sellers->count() }} {{ Str::plural('seller', $sellers->count()) }}</p></div>
                        <form method="GET" action="{{ request()->routeIs('catalog.store') ? route('catalog.store', $sellerStore) : route('catalog.index') }}" @submit="loading = true" class="flex items-center gap-2">
                            @foreach ($filterParams as $name => $value) @if (is_scalar($value))<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif @endforeach
                            <label for="sort" class="text-sm font-medium text-slate-600">Sort</label><select id="sort" name="sort" onchange="this.form.submit()" class="min-h-11 rounded-xl border-slate-300 bg-white text-sm font-medium focus:border-emerald-700 focus:ring-emerald-700"><option value="relevance" @selected(request('sort', 'relevance') === 'relevance')>Relevance</option><option value="newest" @selected(request('sort') === 'newest')>Newest</option><option value="price_asc" @selected(request('sort') === 'price_asc')>Price: low to high</option><option value="price_desc" @selected(request('sort') === 'price_desc')>Price: high to low</option></select>
                        </form>
                    </div>

                    @if ($hasFilters)
                        <div class="mt-4 flex flex-wrap items-center gap-2" aria-label="Active filters">
                            @if ($search)<a href="{{ route('catalog.index', request()->except(['q', 'page'])) }}" class="inline-flex min-h-9 items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 text-xs font-semibold text-emerald-900">“{{ Str::limit($search, 22) }}” <span aria-hidden="true">×</span></a>@endif
                            @if (request('category'))<a href="{{ route('catalog.index', request()->except(['category', 'page'])) }}" class="inline-flex min-h-9 items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 text-xs font-semibold text-emerald-900">{{ optional($categories->firstWhere('slug', request('category')))->name ?? 'Category' }} <span aria-hidden="true">×</span></a>@endif
                            @if (request('seller') && ! $sellerStore)<a href="{{ route('catalog.index', request()->except(['seller', 'page'])) }}" class="inline-flex min-h-9 items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 text-xs font-semibold text-emerald-900">{{ optional($sellers->firstWhere('slug', request('seller')))->name ?? 'Seller' }} <span aria-hidden="true">×</span></a>@endif
                            @if (request('price_min') || request('price_max'))<a href="{{ route('catalog.index', request()->except(['price_min', 'price_max', 'page'])) }}" class="inline-flex min-h-9 items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 text-xs font-semibold text-emerald-900">Price range <span aria-hidden="true">×</span></a>@endif
                            @if (request()->boolean('available'))<a href="{{ route('catalog.index', request()->except(['available', 'page'])) }}" class="inline-flex min-h-9 items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 text-xs font-semibold text-emerald-900">In stock <span aria-hidden="true">×</span></a>@endif
                            <a href="{{ route('catalog.index') }}" class="px-2 text-xs font-semibold text-slate-500 hover:text-slate-950">Clear all</a>
                        </div>
                    @endif

                    <div x-show="loading" x-cloak class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6"><template x-for="item in 12"><div class="overflow-hidden rounded-2xl border border-slate-200 bg-white"><div class="aspect-square animate-pulse bg-slate-200"></div><div class="space-y-3 p-4"><div class="h-3 w-2/3 animate-pulse rounded bg-slate-200"></div><div class="h-4 animate-pulse rounded bg-slate-200"></div><div class="h-4 w-1/2 animate-pulse rounded bg-slate-200"></div></div></div></template></div>
                    <div x-show="!loading" class="mt-6">
                        @if ($products->isNotEmpty())
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4 xl:grid-cols-6">
                                @foreach ($products as $product)
                                    <article class="group min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_12px_25px_-24px_rgba(15,23,42,0.55)] transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_20px_35px_-24px_rgba(15,23,42,0.45)]">
                                        <a href="{{ route('catalog.show', [$product->store->slug, $product->slug]) }}" class="relative block aspect-square overflow-hidden bg-[#e8eee8] focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-inset">
                                            @if ($product->images->first())<img src="{{ Storage::disk('public')->url($product->images->first()->path) }}" alt="{{ $product->name }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">@else<div class="flex h-full flex-col items-center justify-center gap-2 px-4 text-center text-emerald-900"><span class="grid h-12 w-12 place-items-center rounded-2xl bg-white/70 text-lg font-bold">{{ Str::upper(Str::substr($product->store->name, 0, 1)) }}</span><span class="text-xs font-semibold">{{ $product->store->name }}</span></div>@endif
                                            <div class="absolute left-3 top-3 flex flex-wrap gap-1.5">
                                                @if ($product->created_at?->greaterThan(now()->subDays(14)))<span class="rounded-full bg-slate-950 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-white">New</span>@endif
                                                @if ($product->stock_quantity > 0 && $product->stock_quantity <= 3)<span class="rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-amber-950">{{ $product->stock_quantity }} left</span>@endif
                                            </div>
                                        </a>
                                        <div class="p-3 sm:p-4"><a href="{{ route('catalog.store', $product->store->slug) }}" class="block truncate text-xs font-semibold text-emerald-800 transition hover:text-emerald-950 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">{{ $product->store->name }}</a><h3 class="mt-1 min-h-10 break-words text-sm font-semibold leading-5 text-slate-950 sm:text-base"><a href="{{ route('catalog.show', [$product->store->slug, $product->slug]) }}" class="transition hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">{{ $product->name }}</a></h3><div class="mt-2 flex items-center justify-between gap-2"><p class="text-base font-bold tracking-tight text-slate-950">₱{{ number_format($product->price, 2) }}</p><p class="text-xs font-medium {{ $product->stock_quantity > 0 ? 'text-slate-500' : 'text-red-700' }}">{{ $product->stock_quantity > 0 ? 'In stock' : 'Sold out' }}</p></div>
                                            @if ($isBuyer)<form method="POST" action="{{ route('cart.store', $product) }}" @submit="loading = true" class="mt-3">@csrf <input type="hidden" name="quantity" value="1">@if ($product->stock_quantity > 0)<button class="min-h-10 w-full rounded-xl bg-emerald-700 px-3 text-sm font-semibold text-white transition duration-200 hover:bg-emerald-800 active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">Add to cart</button>@else<button disabled class="min-h-10 w-full cursor-not-allowed rounded-xl bg-slate-100 px-3 text-sm font-semibold text-slate-500">Sold out</button>@endif</form>@else<a href="{{ route('catalog.show', [$product->store->slug, $product->slug]) }}" class="mt-3 inline-flex min-h-10 w-full items-center justify-center rounded-xl border border-slate-200 px-3 text-sm font-semibold text-slate-700 transition duration-200 hover:border-slate-950 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">View product</a>@endif
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                            <div class="mt-10">{{ $products->onEachSide(1)->links() }}</div>
                        @else
                            <div class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center"><div class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-emerald-50 text-emerald-800">⌕</div><h3 class="mt-5 text-lg font-semibold text-slate-950">Nothing matched this search.</h3><p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-slate-500">Try a broader search or reset your filters to explore every available product.</p><a href="{{ route('catalog.index') }}" class="mt-6 inline-flex min-h-11 items-center rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">Reset filters</a></div>
                        @endif
                    </div>
                </section>
            </div>

            <div x-show="filtersOpen" x-cloak class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="Filters">
                <div @click="filtersOpen = false" class="absolute inset-0 bg-slate-950/35"></div>
                <div class="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-[2rem] bg-[#f7f7f2] p-5 shadow-2xl">
                    <div class="mx-auto mb-5 h-1.5 w-12 rounded-full bg-slate-300"></div><div class="flex items-center justify-between"><h2 class="text-lg font-semibold">Filters</h2><button type="button" @click="filtersOpen = false" class="grid h-11 w-11 place-items-center rounded-full bg-white text-lg" aria-label="Close filters">×</button></div>
                    <form method="GET" action="{{ request()->routeIs('catalog.store') ? route('catalog.store', $sellerStore) : route('catalog.index') }}" @submit="loading = true" class="mt-6 space-y-5">
                        @if ($sellerStore)<input type="hidden" name="seller" value="{{ $sellerStore->slug }}">@endif
                        <input type="hidden" name="q" value="{{ $search }}">
                        <div><label class="text-sm font-semibold">Price range</label><div class="mt-2 grid grid-cols-2 gap-3"><input name="price_min" value="{{ request('price_min') }}" type="number" min="0" inputmode="decimal" placeholder="Minimum price" class="rounded-xl border-slate-300"><input name="price_max" value="{{ request('price_max') }}" type="number" min="0" inputmode="decimal" placeholder="Maximum price" class="rounded-xl border-slate-300"></div></div>
                        <div><label for="mobile-seller" class="text-sm font-semibold">Seller</label><select id="mobile-seller" name="seller" class="mt-2 w-full rounded-xl border-slate-300" @disabled($sellerStore)><option value="">All sellers</option>@foreach ($sellers as $seller)<option value="{{ $seller->slug }}" @selected(($sellerStore?->slug ?? request('seller')) === $seller->slug)>{{ $seller->name }}</option>@endforeach</select></div>
                        <label class="flex min-h-12 items-center justify-between rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold"><span>In stock only</span><input type="checkbox" name="available" value="1" @checked(request()->boolean('available')) class="rounded border-slate-300 text-emerald-700 focus:ring-emerald-700"></label>
                        <div class="sticky bottom-0 grid grid-cols-2 gap-3 bg-[#f7f7f2] pt-3"><a href="{{ route('catalog.index') }}" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-slate-300 bg-white text-sm font-semibold">Clear all</a><button class="min-h-12 rounded-xl bg-slate-950 text-sm font-semibold text-white">Show products</button></div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
