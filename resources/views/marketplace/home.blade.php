<x-app-layout>
    @section('title', 'Marketplace · JMMart')
    <nav class="hidden border-b border-line bg-white sm:block" aria-label="Marketplace sections">
        <div class="page-container flex min-h-12 items-center gap-6 overflow-x-auto text-sm font-medium text-muted">
            <a href="#shop-by-category" class="inline-flex min-h-12 shrink-0 items-center border-b-2 border-brand text-ink">Categories</a>
            <a href="{{ route('catalog.index', ['sort' => 'newest']) }}" class="inline-flex min-h-12 shrink-0 items-center hover:text-ink">New arrivals</a>
            <a href="{{ route('catalog.top-products') }}" class="inline-flex min-h-12 shrink-0 items-center hover:text-ink">Best sellers</a>
            <a href="{{ route('catalog.stores') }}" class="inline-flex min-h-12 shrink-0 items-center hover:text-ink">Stores</a>
            @if (auth()->user()?->account_type === 'seller')
                <a href="{{ route('seller.dashboard') }}" class="inline-flex min-h-12 shrink-0 items-center hover:text-ink">Seller Center</a>
            @elseif (! auth()->user()?->isPlatformAdministrator())
                <a href="{{ auth()->check() ? route('seller-application.create') : route('login', ['redirect' => '/start-selling']) }}" class="inline-flex min-h-12 shrink-0 items-center hover:text-ink">Start selling</a>
            @endif
        </div>
    </nav>

    <div class="page-container">
        <section class="grid gap-8 py-9 sm:py-12 lg:grid-cols-[1.05fr_.95fr] lg:items-center lg:gap-12 lg:pb-[3px] lg:pt-[44px]" aria-labelledby="marketplace-home-heading">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[.14em] text-brand">JMMart marketplace</p>
                <h1 id="marketplace-home-heading" class="mt-3 max-w-[12ch] text-4xl font-bold leading-[1.12] tracking-[-0.035em] text-ink sm:text-5xl lg:text-[54px] lg:leading-[1.15]">Find everyday favorites from stores you trust.</h1>
                <p class="mt-5 max-w-xl text-base leading-7 text-muted">Explore products from independent stores, with free shipping and cash on delivery.</p>
                <div class="mt-7 flex flex-wrap gap-3">
                    <a href="{{ route('catalog.index') }}" class="inline-flex min-h-12 items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">Shop the marketplace</a>
                    <a href="{{ route('catalog.stores') }}" class="inline-flex min-h-12 items-center justify-center rounded-[10px] border border-line bg-white px-5 text-sm font-semibold text-ink transition hover:border-brand hover:text-brand focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">Explore stores</a>
                </div>
                <div class="mt-7 flex flex-wrap gap-x-8 gap-y-3 text-sm">
                    <p><span class="block font-semibold text-ink">Independent stores</span><span class="mt-0.5 block text-muted">Discover local sellers</span></p>
                    <p><span class="block font-semibold text-ink">Cash on delivery</span><span class="mt-0.5 block text-muted">Pay when it arrives</span></p>
                    <p><span class="block font-semibold text-ink">Free shipping</span><span class="mt-0.5 block text-muted">On every order</span></p>
                </div>
            </div>

            @if ($featuredProduct)
                <article class="grid min-h-[22rem] overflow-hidden rounded-[20px] border border-line bg-ink sm:grid-cols-[.9fr_1.1fr] lg:min-h-[508px]">
                    <div class="flex flex-col items-start justify-center p-6 text-white sm:px-8 sm:pb-8 sm:pt-12">
                        <p class="text-xs font-semibold uppercase tracking-[.12em] text-slate-300">Featured product</p>
                        <h2 class="mt-3 text-2xl font-bold leading-tight tracking-[-0.025em]">{{ $featuredProduct->name }}</h2>
                        <a href="{{ route('catalog.store', $featuredProduct->store->slug) }}" class="mt-2 text-sm text-slate-300 underline underline-offset-4 hover:text-white">{{ $featuredProduct->store->name }}</a>
                        <p class="mt-5 text-2xl font-bold tabular-nums">₱{{ number_format($featuredProduct->price, 2) }}</p>
                        <a href="{{ route('catalog.show', [$featuredProduct->store->slug, $featuredProduct->slug]) }}" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-[10px] bg-white px-4 text-sm font-semibold text-ink transition hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-ink">View product</a>
                    </div>
                    <a href="{{ route('catalog.show', [$featuredProduct->store->slug, $featuredProduct->slug]) }}" class="relative min-h-64 overflow-hidden bg-slate-100 sm:min-h-full" aria-label="View {{ $featuredProduct->name }}">
                        @if ($featuredProduct->thumbnail)
                            <img src="{{ Storage::disk('product_images')->url($featuredProduct->thumbnail->path) }}" alt="{{ $featuredProduct->name }}" width="640" height="640" fetchpriority="high" class="absolute inset-0 h-full w-full object-contain p-6 sm:p-8" />
                        @else
                            <div class="absolute inset-0 grid place-items-center text-muted"><svg viewBox="0 0 24 24" class="size-16" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 10h8M8 14h5"/></svg><span class="sr-only">No product image available</span></div>
                        @endif
                        <span class="absolute right-5 top-5 rounded-xl bg-white px-4 py-3 shadow-lg shadow-ink/10">
                            <span class="block text-sm font-semibold text-ink">{{ $featuredProduct->stock_quantity > 0 ? 'In stock' : 'Sold out' }}</span>
                            <span class="block text-xs text-muted">{{ $featuredProduct->stock_quantity > 0 ? number_format($featuredProduct->stock_quantity).' available' : 'Currently unavailable' }}</span>
                        </span>
                        @if ($featuredProduct->published_reviews_count > 0)
                            <span class="absolute bottom-5 left-5 right-5 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-white px-4 py-3 shadow-lg shadow-ink/10">
                                <span class="text-sm font-semibold text-ink">{{ $featuredProduct->store->name }}</span>
                                <span class="inline-flex items-center gap-1 text-xs text-muted"><svg viewBox="0 0 20 20" class="size-4 fill-amber-400" aria-hidden="true"><path d="m10 1.7 2.55 5.17 5.7.83-4.12 4.02 0.98 5.68L10 14.72l-5.11 2.68.98-5.68L1.75 7.7l5.7-.83L10 1.7Z" /></svg>{{ number_format((float) $featuredProduct->published_reviews_avg_rating, 1) }} · {{ number_format($featuredProduct->published_reviews_count) }} reviews</span>
                            </span>
                        @endif
                    </a>
                </article>
            @else
                <div class="grid min-h-64 place-items-center rounded-[16px] border border-line bg-white p-8 text-center">
                    <div><p class="text-lg font-semibold text-ink">Your next favorite starts here.</p><p class="mt-2 text-sm text-muted">Browse the marketplace or explore independent stores.</p><a href="{{ route('catalog.stores') }}" class="mt-4 inline-flex min-h-11 items-center font-semibold text-brand hover:text-brand-hover">Explore stores <span aria-hidden="true">→</span></a></div>
                </div>
            @endif
        </section>

        @if ($categories->isNotEmpty())
            <section id="shop-by-category" class="scroll-mt-24 border-t border-line py-9 sm:pb-12 sm:pt-[51px]" aria-labelledby="home-categories-heading">
                <div class="mb-5 flex items-end justify-between gap-4">
                    <div><h2 id="home-categories-heading" class="text-2xl font-bold tracking-[-0.025em] text-ink">Shop by category</h2><p class="mt-1 text-sm text-muted">Browse products by what you need.</p></div>
                    <a href="{{ route('catalog.index') }}#categories" class="shrink-0 text-sm font-semibold text-brand hover:text-brand-hover">View all <span aria-hidden="true">→</span></a>
                </div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-6">
                    @foreach ($categories as $category)
                        <x-marketplace.category-card :category="$category" :show-count="true" :href="route('catalog.index', ['category' => $category->slug])" />
                    @endforeach
                </div>
            </section>
        @endif

        @if ($bestSellers->isNotEmpty())
            <section class="border-t border-line py-9 sm:py-12" aria-labelledby="home-bestsellers-heading">
                <div class="mb-5 flex items-end justify-between gap-4">
                    <div><h2 id="home-bestsellers-heading" class="text-2xl font-bold tracking-[-0.025em] text-ink">Popular products</h2><p class="mt-1 text-sm text-muted">Top sellers based on completed orders.</p></div>
                    <a href="{{ route('catalog.top-products') }}" class="shrink-0 text-sm font-semibold text-brand hover:text-brand-hover">View best sellers <span aria-hidden="true">→</span></a>
                </div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
                    @foreach ($bestSellers as $product)
                        <x-marketplace.top-product-card :product="$product" />
                    @endforeach
                </div>
            </section>
        @endif

        @if ($newProducts->isNotEmpty())
            <section class="border-t border-line py-9 sm:py-12" aria-labelledby="home-new-heading">
                <div class="mb-5 flex items-end justify-between gap-4">
                    <div><h2 id="home-new-heading" class="text-2xl font-bold tracking-[-0.025em] text-ink">New arrivals</h2><p class="mt-1 text-sm text-muted">Recently added by marketplace sellers.</p></div>
                    <a href="{{ route('catalog.index', ['sort' => 'newest']) }}" class="shrink-0 text-sm font-semibold text-brand hover:text-brand-hover">View all <span aria-hidden="true">→</span></a>
                </div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
                    @foreach ($newProducts as $product)
                        <x-marketplace.top-product-card :product="$product" />
                    @endforeach
                </div>
            </section>
        @endif

        @if ($stores->isNotEmpty())
            <section class="border-t border-line py-9 sm:py-12" aria-labelledby="home-stores-heading">
                <div class="mb-5 flex items-end justify-between gap-4">
                    <div><h2 id="home-stores-heading" class="text-2xl font-bold tracking-[-0.025em] text-ink">Popular stores</h2><p class="mt-1 text-sm text-muted">Meet the independent sellers behind the products.</p></div>
                    <a href="{{ route('catalog.stores') }}" class="shrink-0 text-sm font-semibold text-brand hover:text-brand-hover">All stores <span aria-hidden="true">→</span></a>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($stores as $store)
                        <a href="{{ route('catalog.store', $store->slug) }}" class="flex min-h-24 items-center justify-between gap-4 rounded-[14px] border border-line bg-white p-4 transition hover:border-brand/50 hover:shadow-[0_12px_28px_rgba(15,23,42,.10)] focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">
                            <span class="min-w-0"><span class="block truncate font-semibold text-ink">{{ $store->name }}</span><span class="mt-1 block text-sm text-muted">{{ number_format($store->public_products_count) }} {{ Str::plural('product', $store->public_products_count) }}@if($store->city) · {{ $store->city }}@endif</span></span>
                            <span class="inline-flex min-h-10 shrink-0 items-center rounded-[10px] border border-line px-3 text-sm font-semibold text-ink">Visit store</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if (! auth()->user()?->isPlatformAdministrator())
            <section class="mb-10 grid gap-5 rounded-[14px] border border-line bg-white px-6 py-8 sm:grid-cols-[1fr_auto] sm:items-center sm:px-9 sm:py-10" aria-labelledby="home-seller-heading">
                <div><h2 id="home-seller-heading" class="text-2xl font-bold tracking-[-0.025em]">Have products to sell?</h2><p class="mt-2 max-w-2xl text-sm leading-6 text-muted">Open a store on JMMart and manage your products and orders in Seller Center.</p></div>
                @auth
                    @if (auth()->user()->account_type === 'seller')
                        <a href="{{ route('seller.dashboard') }}" class="inline-flex min-h-11 items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white hover:bg-brand-hover">Open Seller Center</a>
                    @else
                        <a href="{{ route('seller-application.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white hover:bg-brand-hover">Start selling</a>
                    @endif
                @else
                    <a href="{{ route('login', ['redirect' => '/start-selling']) }}" class="inline-flex min-h-11 items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white hover:bg-brand-hover">Start selling</a>
                @endauth
            </section>
        @endif
    </div>
</x-app-layout>
