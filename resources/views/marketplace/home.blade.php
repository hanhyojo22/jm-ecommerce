<x-app-layout>
    @section('title', 'Marketplace · JMMart')

    <div class="page-container marketplace-home" data-homepage>
        <h1 class="sr-only">Marketplace</h1>

        <x-marketplace.hero-carousel />

        @if ($categories->isNotEmpty())
            <section id="shop-by-category" class="marketplace-section scroll-mt-[136px]" aria-labelledby="home-categories-heading">
                <div class="marketplace-section-head"><div><h2 id="home-categories-heading" class="marketplace-section-title">Shop by category</h2><p class="marketplace-section-subtitle">Every category with products in it today.</p></div><a href="{{ route('catalog.index') }}" class="marketplace-section-link inline-flex items-center gap-1">View all <x-returns.icon name="chevron-right" class="size-4" /></a></div>
                <div class="home-category-grid">
                    @foreach ($categories as $category)
                        <x-marketplace.category-card :category="$category" :show-count="true" :href="route('catalog.index', ['category' => $category->slug])" />
                    @endforeach
                </div>
            </section>
        @endif

        @if ($newProducts->isNotEmpty())
            <section id="new-arrivals" class="marketplace-section scroll-mt-24" aria-labelledby="home-new-heading">
                <div class="marketplace-section-head"><div><h2 id="home-new-heading" class="marketplace-section-title">New arrivals</h2><p class="marketplace-section-subtitle">The latest listings from every store.</p></div><a href="{{ route('catalog.index', ['sort' => 'newest']) }}" class="marketplace-section-link inline-flex items-center gap-1">All new arrivals <x-returns.icon name="chevron-right" class="size-4" /></a></div>
                <div class="marketplace-product-grid">
                    @include('marketplace.catalog._new-arrival-cards', ['products' => $newProducts])
                </div>
            </section>
        @endif

        @if ($flashDeals->isNotEmpty())
            @php
                $largestDiscount = $flashDeals->map(fn ($product): int => (int) round((1 - ((float) $product->price / (float) $product->compare_at_price)) * 100))->max();
            @endphp
            <section id="flash-deals" class="marketplace-section home-deals scroll-mt-24" aria-labelledby="home-deals-heading">
                <div class="marketplace-section-head">
                    <div>
                        <div class="home-deals-title"><h2 id="home-deals-heading" class="marketplace-section-title">Biggest price drops</h2><span>Up to {{ $largestDiscount }}% off</span></div>
                        <p class="marketplace-section-subtitle">Real savings from independent stores.</p>
                    </div>
                    <a href="{{ route('catalog.index', ['on_sale' => 1]) }}" class="marketplace-section-link inline-flex items-center gap-1">All deals <x-returns.icon name="chevron-right" class="size-4" /></a>
                </div>
                <div class="marketplace-product-grid">
                    @foreach ($flashDeals as $product)
                        <x-marketplace.top-product-card :product="$product" />
                    @endforeach
                </div>
            </section>
        @endif

        @if ($bestSellers->isNotEmpty())
            <section id="trending-products" class="marketplace-section scroll-mt-24" aria-labelledby="home-trending-heading">
                <div class="marketplace-section-head"><div><h2 id="home-trending-heading" class="marketplace-section-title">Trending products</h2><p class="marketplace-section-subtitle">Ranked by how many buyers ordered them.</p></div><a href="{{ route('catalog.top-products') }}" class="marketplace-section-link inline-flex items-center gap-1">View all <x-returns.icon name="chevron-right" class="size-4" /></a></div>
                <ol class="marketplace-product-grid">
                    @foreach ($bestSellers->take(6) as $product)
                        <li class="flex min-w-0 flex-col">
                            <p class="trending-rank"><span>{{ $loop->iteration }}</span> {{ number_format((int) ($product->sold_quantity ?? $product->net_quantity_sold ?? 0)) }} sold</p>
                            <x-marketplace.top-product-card :product="$product" />
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        @if ($recommendedProducts->isNotEmpty())
            <section class="marketplace-section" aria-labelledby="home-recommended-heading">
                <div class="marketplace-section-head"><div><h2 id="home-recommended-heading" class="marketplace-section-title">Recommended for you</h2><p class="marketplace-section-subtitle">Based on what you browse, plus popular picks you haven't seen above.</p></div><a href="{{ route('catalog.index') }}" class="marketplace-section-link inline-flex items-center gap-1">Explore marketplace <x-returns.icon name="chevron-right" class="size-4" /></a></div>
                <div class="marketplace-product-grid">
                    @foreach ($recommendedProducts as $product)
                        <x-marketplace.top-product-card :product="$product" />
                    @endforeach
                </div>
            </section>
        @endif

        @if ($recentlyViewed->isNotEmpty())
            <section class="marketplace-section" aria-labelledby="home-recent-heading">
                <div class="marketplace-section-head"><div><h2 id="home-recent-heading" class="marketplace-section-title">Recently viewed</h2><p class="marketplace-section-subtitle">Pick up where you left off.</p></div><form method="POST" action="{{ route('catalog.recently-viewed.clear') }}">@csrf @method('DELETE')<button type="submit" class="marketplace-section-link shrink-0 hover:text-brand-hover focus-visible:ring-2 focus-visible:ring-brand">Clear history</button></form></div>
                <div class="marketplace-product-grid">
                    @foreach ($recentlyViewed->take(6) as $product)
                        <x-marketplace.top-product-card :product="$product" />
                    @endforeach
                </div>
            </section>
        @endif

        @if (! auth()->user()?->isPlatformAdministrator() && ! (auth()->user()?->account_type === 'seller' && auth()->user()->sellerTenant()))
            <section class="seller-window" aria-labelledby="home-seller-heading" data-seller-invitation>
                <div class="seller-window-main">
                    <div class="seller-window-copy">
                        <h2 id="home-seller-heading" class="seller-window-title"><span>Your products.</span><span>Your next chapter.</span></h2>
                        <p class="seller-window-description">Bring your shop to JMMart. Share your products and manage your orders, all from Seller Center.</p>
                        <a href="{{ auth()->check() ? route('seller-application.create') : route('login', ['redirect' => '/start-selling']) }}" class="seller-window-action">Start selling <x-returns.icon name="chevron-right" class="size-4" /></a>
                    </div>
                    <div class="seller-window-visual">
                        <img src="{{ asset('images/banners/seller-invitation.jpg') }}" alt="" width="1536" height="1024" loading="lazy" decoding="async">
                    </div>
                </div>
                <ol class="seller-window-steps" aria-label="How selling on JMMart works">
                    <li>
                        <span class="seller-window-step-icon"><x-returns.icon name="shield" class="size-5" /></span>
                        <div><h3>Apply for a shop</h3><p>Tell us about your business. We review every seller application.</p></div>
                    </li>
                    <li>
                        <span class="seller-window-step-icon"><x-returns.icon name="package" class="size-5" /></span>
                        <div><h3>Make it yours</h3><p>Add your products, photos, prices, and variants once approved.</p></div>
                    </li>
                    <li>
                        <span class="seller-window-step-icon"><x-returns.icon name="truck" class="size-5" /></span>
                        <div><h3>Fulfill your orders</h3><p>Prepare deliveries and collect payment with cash on delivery.</p></div>
                    </li>
                </ol>
            </section>
        @endif
    </div>
</x-app-layout>
