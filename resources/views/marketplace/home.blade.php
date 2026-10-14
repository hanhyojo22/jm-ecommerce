<x-app-layout>
    @section('title', 'Marketplace · JMMart')

    <div class="page-container">
        <section class="py-8 sm:py-10 lg:pb-2 lg:pt-10" aria-labelledby="marketplace-home-heading">
            <div class="grid grid-cols-1 overflow-hidden rounded-[24px] bg-ink text-white lg:grid-cols-[.9fr_1.1fr]">
                <div class="flex flex-col justify-center px-6 py-10 sm:px-10 sm:py-12 lg:px-12 lg:py-14">
                    <p class="mb-4 text-xs font-semibold uppercase tracking-[.16em] text-blue-200">JMMart · Independent stores</p>
                    <h1 id="marketplace-home-heading" class="max-w-[13ch] text-4xl font-bold leading-[1.08] tracking-[-.04em] sm:text-5xl lg:text-[54px]">Good things, great finds.</h1>
                    <p class="mt-5 max-w-lg text-base leading-7 text-slate-300">Discover products for everyday life from independent sellers. Shipping is free, and you can pay when your order arrives.</p>
                    <div class="mt-7 flex flex-wrap gap-3">
                        <a href="{{ route('catalog.index') }}" class="inline-flex min-h-12 items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-ink">Shop products <span class="ml-2" aria-hidden="true">→</span></a>
                        <a href="{{ route('catalog.stores') }}" class="inline-flex min-h-12 items-center justify-center rounded-[10px] border border-white/30 px-5 text-sm font-semibold text-white transition hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-ink">Explore stores</a>
                    </div>
                    <div class="mt-9 flex flex-wrap gap-x-6 gap-y-2 border-t border-white/15 pt-5 text-sm text-slate-300">
                        <span>Free shipping</span>
                        <span>Cash on delivery</span>
                        <span>Independent sellers</span>
                    </div>
                </div>

                <div class="relative bg-[#dce9f7] lg:min-h-[480px]">
                    <div class="relative aspect-[3/2] lg:absolute lg:inset-0 lg:aspect-auto">
                        <img src="{{ asset('images/home-product-collection.jpg') }}" alt="" width="1536" height="1024" fetchpriority="high" class="absolute inset-0 h-full w-full object-cover object-center" />
                    </div>
                    @if ($featuredProduct)
                        <a href="{{ route('catalog.show', [$featuredProduct->store->slug, $featuredProduct->slug]) }}" class="relative mx-4 -mt-5 mb-4 flex min-h-20 items-center gap-3 rounded-2xl bg-white/95 p-3 text-ink shadow-xl shadow-ink/15 transition hover:bg-white focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 focus-visible:ring-offset-ink sm:mx-6 sm:mb-6 sm:p-4 lg:absolute lg:bottom-6 lg:left-6 lg:right-6 lg:m-0">
                            @if ($featuredProduct->thumbnail)
                                <img src="{{ Storage::disk('product_images')->url($featuredProduct->thumbnail->path) }}" alt="" width="64" height="64" class="size-14 shrink-0 rounded-xl bg-slate-100 object-contain sm:size-16" />
                            @endif
                            <span class="min-w-0 flex-1">
                                <span class="block text-[11px] font-semibold uppercase tracking-[.1em] text-brand">Featured listing</span>
                                <span class="mt-0.5 block truncate text-sm font-semibold sm:text-base">{{ $featuredProduct->name }}</span>
                                <span class="block text-sm font-semibold tabular-nums">₱{{ number_format($featuredProduct->price, fmod((float) $featuredProduct->price, 1) === 0.0 ? 0 : 2) }}@if ($featuredProduct->stock_quantity < 1) <span class="ml-2 font-medium text-muted">Sold out</span>@endif</span>
                            </span>
                            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-brand text-white" aria-hidden="true">→</span>
                        </a>
                    @endif
                </div>
            </div>
        </section>

        @if ($categories->isNotEmpty())
            <section id="shop-by-category" class="scroll-mt-[168px] border-t border-line py-10 min-[641px]:scroll-mt-[118px] sm:py-12" aria-labelledby="home-categories-heading">
                <div class="mb-5 flex items-end justify-between gap-4"><div><h2 id="home-categories-heading" class="text-2xl font-bold tracking-[-.03em] text-ink">Shop by category</h2><p class="mt-1 text-sm text-muted">Browse products by what you need.</p></div><a href="{{ route('catalog.index') }}" class="shrink-0 text-sm font-semibold text-brand hover:text-brand-hover">View all <span aria-hidden="true">→</span></a></div>
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-3 sm:gap-4 md:grid-cols-4 lg:grid-cols-6">
                    @foreach ($categories as $category)
                        <x-marketplace.category-card :category="$category" :show-count="true" :href="route('catalog.index', ['category' => $category->slug])" />
                    @endforeach
                </div>
            </section>
        @endif

        @if ($bestSellers->isNotEmpty())
            <section class="border-t border-line py-10 sm:py-12" aria-labelledby="home-trending-heading">
                <div class="mb-5 flex items-end justify-between gap-4"><div><h2 id="home-trending-heading" class="text-2xl font-bold tracking-[-.03em] text-ink">Trending products</h2><p class="mt-1 text-sm text-muted">Popular with buyers across the marketplace.</p></div><a href="{{ route('catalog.top-products') }}" class="shrink-0 text-sm font-semibold text-brand hover:text-brand-hover">View all <span aria-hidden="true">→</span></a></div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
                    @foreach ($bestSellers->take(4) as $product)
                        <x-marketplace.top-product-card :product="$product" />
                    @endforeach
                </div>
            </section>
        @endif

        @if ($newProducts->isNotEmpty())
            <section id="new-arrivals" data-new-arrivals data-next-url="{{ $newProducts->nextPageUrl() ?? '' }}" class="scroll-mt-24 border-t border-line py-10 sm:py-12" aria-labelledby="home-new-heading">
                <div class="mb-5 flex items-end justify-between gap-4"><div><h2 id="home-new-heading" class="text-2xl font-bold tracking-[-.03em] text-ink">New arrivals</h2><p class="mt-1 text-sm text-muted">Recently added by marketplace sellers.</p></div><a href="{{ route('catalog.index', ['sort' => 'newest']) }}" class="shrink-0 text-sm font-semibold text-brand hover:text-brand-hover">View all <span aria-hidden="true">→</span></a></div>
                <div id="new-arrivals-products" data-new-arrivals-grid class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
                    @include('marketplace.catalog._new-arrival-cards', ['products' => $newProducts])
                </div>
                @if ($newProducts->hasMorePages())
                    <div class="mt-7 flex flex-col items-center gap-3">
                        <a href="{{ $newProducts->nextPageUrl() }}" data-new-arrivals-more aria-controls="new-arrivals-products" class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-line bg-white px-6 text-sm font-semibold text-ink transition hover:border-brand hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand aria-disabled:cursor-wait aria-disabled:opacity-60">Load More</a>
                        <p data-new-arrivals-status role="status" aria-live="polite" class="text-sm text-muted"></p>
                    </div>
                @endif
            </section>
        @endif

        @if ($flashDeals->isNotEmpty())
            <section id="flash-deals" class="scroll-mt-[168px] py-8 min-[641px]:scroll-mt-[118px] sm:py-10" aria-labelledby="home-deals-heading">
                <div class="grid gap-6 rounded-[20px] bg-ink p-5 text-white sm:p-7 lg:grid-cols-[230px_minmax(0,1fr)] lg:gap-7">
                    <div class="flex flex-col items-start justify-center"><p class="text-xs font-semibold uppercase tracking-[.12em] text-blue-300">Flash deals</p><h2 id="home-deals-heading" class="mt-2 text-[25px] font-bold leading-tight tracking-[-.03em] text-white">A good find at a better price.</h2><p class="mt-2 text-sm leading-6 text-slate-300">Browse products currently discounted by their sellers.</p><a href="{{ route('catalog.index', ['on_sale' => 1]) }}" class="mt-4 inline-flex min-h-11 items-center rounded-[10px] bg-white px-4 text-sm font-semibold text-ink transition hover:bg-slate-100">View all deals <span class="ml-2" aria-hidden="true">→</span></a></div>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($flashDeals as $product)
                            <x-marketplace.top-product-card :product="$product" />
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($recentlyViewed->isNotEmpty() && $recommendedProducts->isNotEmpty())
            <section class="border-t border-line py-10 sm:py-12" aria-labelledby="home-recommended-heading">
                <div class="mb-5 flex items-end justify-between gap-4"><div><h2 id="home-recommended-heading" class="text-2xl font-bold tracking-[-.03em] text-ink">Recommended for you</h2><p class="mt-1 text-sm text-muted">More to explore from categories you recently viewed.</p></div><a href="{{ route('catalog.index') }}" class="shrink-0 text-sm font-semibold text-brand hover:text-brand-hover">Explore marketplace <span aria-hidden="true">→</span></a></div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
                    @foreach ($recommendedProducts as $product)
                        <x-marketplace.top-product-card :product="$product" />
                    @endforeach
                </div>
            </section>
        @endif

        @if ($recentlyViewed->isNotEmpty())
            <section class="border-t border-line py-10 sm:py-12" aria-labelledby="home-recent-heading">
                <div class="mb-5 flex items-end justify-between gap-4"><div><h2 id="home-recent-heading" class="text-2xl font-bold tracking-[-.03em] text-ink">Recently viewed</h2><p class="mt-1 text-sm text-muted">Pick up where you left off.</p></div><form method="POST" action="{{ route('catalog.recently-viewed.clear') }}">@csrf @method('DELETE')<button type="submit" class="shrink-0 text-sm font-semibold text-brand hover:text-brand-hover focus-visible:ring-2 focus-visible:ring-brand">Clear history</button></form></div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
                    @foreach ($recentlyViewed->take(4) as $product)
                        <x-marketplace.top-product-card :product="$product" />
                    @endforeach
                </div>
            </section>
        @endif

        @if (! auth()->user()?->isPlatformAdministrator() && ! (auth()->user()?->account_type === 'seller' && auth()->user()->sellerTenant()))
            <section class="my-8 grid overflow-hidden rounded-[20px] border border-line bg-white sm:grid-cols-[1.1fr_.9fr]" aria-labelledby="home-seller-heading">
                <div class="p-6 sm:p-9"><p class="text-xs font-semibold uppercase tracking-[.12em] text-brand">Sell on JMMart</p><h2 id="home-seller-heading" class="mt-2 max-w-[20ch] text-2xl font-bold leading-tight tracking-[-.03em] text-ink">Open a storefront and grow your business.</h2><p class="mt-3 max-w-xl text-sm leading-6 text-muted">Reach shoppers and manage your products and orders from Seller Center.</p>
                    @auth
                        <a href="{{ route('seller-application.create') }}" class="mt-5 inline-flex min-h-11 items-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white hover:bg-brand-hover">Start selling</a>
                    @else
                        <a href="{{ route('login', ['redirect' => '/start-selling']) }}" class="mt-5 inline-flex min-h-11 items-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white hover:bg-brand-hover">Start selling</a>
                    @endauth
                </div>
                <div class="grid min-h-56 place-items-center bg-gradient-to-br from-blue-50 to-indigo-100 p-6"><div class="w-full max-w-[300px] rounded-2xl bg-white p-5 shadow-[0_20px_44px_rgba(15,23,42,.12)]"><p class="text-sm font-semibold text-ink">Your seller workspace</p><p class="mt-1 text-xs text-muted">Products · Orders · Store settings</p><div class="mt-5 grid grid-cols-3 gap-2"><span class="h-12 rounded-lg bg-blue-100"></span><span class="h-12 rounded-lg bg-blue-200"></span><span class="h-12 rounded-lg bg-brand"></span></div></div></div>
            </section>
        @endif

        <section class="grid grid-cols-2 border-y border-line py-5 sm:grid-cols-4 sm:py-7" aria-label="Marketplace benefits">
            <div class="flex gap-3 p-3 sm:px-4"><svg class="mt-0.5 size-6 shrink-0 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m12 3 7 3v5c0 5-3.5 8-7 10-3.5-2-7-5-7-10V6l7-3Z"/><path d="m9 12 2 2 4-4"/></svg><p><strong class="block text-sm text-ink">Secure checkout</strong><span class="text-xs leading-5 text-muted">Your order details stay protected</span></p></div>
            <div class="flex gap-3 border-line p-3 sm:border-l sm:px-4"><svg class="mt-0.5 size-6 shrink-0 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="9" r="5"/><path d="m8.5 13.5-1.5 7.5 5-2.5 5 2.5-1.5-7.5"/></svg><p><strong class="block text-sm text-ink">Independent sellers</strong><span class="text-xs leading-5 text-muted">Discover unique local stores</span></p></div>
            <div class="flex gap-3 border-line p-3 sm:border-l sm:px-4"><svg class="mt-0.5 size-6 shrink-0 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 7h13v10H3zM16 10h4l2 3v4h-4"/><circle cx="7" cy="19" r="1.5"/><circle cx="18" cy="19" r="1.5"/></svg><p><strong class="block text-sm text-ink">Free shipping</strong><span class="text-xs leading-5 text-muted">On every marketplace order</span></p></div>
            <div class="flex gap-3 border-line p-3 sm:border-l sm:px-4"><svg class="mt-0.5 size-6 shrink-0 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 21C7 17 3 13.5 3 9.5 3 7 5 5 7.5 5c1.7 0 3.3.9 4.5 2.6C13.2 5.9 15 5 16.5 5 19 5 21 7 21 9.5c0 4-4 7.5-9 11.5Z"/></svg><p><strong class="block text-sm text-ink">Cash on delivery</strong><span class="text-xs leading-5 text-muted">Pay when your order arrives</span></p></div>
        </section>

        @if ($stores->isNotEmpty())
            <section class="border-t border-line py-10 sm:py-12" aria-labelledby="home-stores-heading">
                <div class="mb-5 flex items-end justify-between gap-4"><div><h2 id="home-stores-heading" class="text-2xl font-bold tracking-[-.03em] text-ink">Popular stores</h2><p class="mt-1 text-sm text-muted">Meet the independent sellers behind the products.</p></div><a href="{{ route('catalog.stores') }}" class="shrink-0 text-sm font-semibold text-brand hover:text-brand-hover">Browse stores <span aria-hidden="true">→</span></a></div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($stores as $store)
                        <a href="{{ route('catalog.store', $store->slug) }}" class="rounded-[14px] border border-line bg-white p-4 transition hover:-translate-y-0.5 hover:border-brand/50 hover:shadow-[0_12px_28px_rgba(15,23,42,.10)] focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">
                            <span class="flex items-center gap-3">
                                <span class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-xl bg-blue-50 text-lg font-bold text-brand">@if ($store->logo_path)<img src="{{ Storage::disk('public')->url($store->logo_path) }}" alt="" class="size-full object-cover">@else{{ Str::upper(Str::substr($store->name, 0, 1)) }}@endif</span>
                                <span class="min-w-0"><span class="block truncate text-sm font-semibold text-ink">{{ $store->name }}</span><span class="mt-1 block text-xs text-muted">{{ number_format($store->public_products_count) }} {{ Str::plural('product', $store->public_products_count) }}</span></span>
                            </span>
                            <span class="mt-4 flex min-h-10 items-center justify-between border-t border-line pt-3 text-sm font-semibold text-ink">Visit store <span class="text-brand" aria-hidden="true">→</span></span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>

</x-app-layout>
