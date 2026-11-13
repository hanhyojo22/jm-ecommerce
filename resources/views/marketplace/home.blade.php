<x-app-layout>
    @section('title', 'Marketplace · JMMart')

    <div class="page-container marketplace-home" data-homepage>
        <section class="home-intro" aria-labelledby="home-discovery-heading">
            <div>
                <h1 id="home-discovery-heading">Good finds. Great local stores.</h1>
                <p>Independent sellers, one cart. Pay when your order arrives.</p>
            </div>
            <a href="{{ route('catalog.index') }}" class="home-browse-button">Browse marketplace <x-returns.icon name="chevron-right" class="size-4" /></a>
            <nav class="home-shortcuts" aria-label="Homepage shortcuts">
                @if ($newProducts->isNotEmpty())<a href="#new-arrivals">New arrivals</a>@endif
                @if ($flashDeals->isNotEmpty())<a href="#flash-deals">Price drops</a>@endif
                @if ($bestSellers->isNotEmpty())<a href="#trending-products">Trending</a>@endif
                @if ($stores->isNotEmpty())<a href="#marketplace-home-heading">Local stores</a>@endif
            </nav>
        </section>

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

        @if ($stores->isNotEmpty())
            <section class="marketplace-section home-stores" aria-labelledby="marketplace-home-heading"
                x-data="{ active: 0, count: {{ $stores->count() }}, show(index) { this.active = (index + this.count) % this.count; this.$refs['storeTab' + this.active].scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' }); }, select(index) { this.show(index); this.$refs['storeTab' + this.active].focus({ preventScroll: true }); } }">
                <div class="marketplace-section-head">
                    <div>
                        <h2 id="marketplace-home-heading" class="marketplace-section-title">Shop local stores</h2>
                        <p class="marketplace-section-subtitle">Independent sellers, one cart. Pay when your order arrives.</p>
                    </div>
                    <a href="{{ route('catalog.stores') }}" class="marketplace-section-link inline-flex shrink-0 items-center gap-1">All stores <x-returns.icon name="chevron-right" class="size-4" /></a>
                </div>

                <div class="store-window-tabs" role="tablist" aria-label="Featured stores">
                    @foreach ($stores as $index => $store)
                        <button type="button" role="tab" id="store-tab-{{ $store->id }}" aria-controls="store-panel-{{ $store->id }}"
                            x-ref="storeTab{{ $index }}"
                            :aria-selected="(active === {{ $index }}).toString()" aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                            :tabindex="active === {{ $index }} ? 0 : -1" tabindex="{{ $index === 0 ? 0 : -1 }}"
                            @click="show({{ $index }})"
                            @keydown.arrow-right.prevent="select({{ $index }} + 1)" @keydown.arrow-left.prevent="select({{ $index }} - 1)"
                            @keydown.home.prevent="select(0)" @keydown.end.prevent="select(count - 1)"
                            class="store-tab">
                            {{ $store->name }} <span class="store-tab-count">{{ number_format($store->public_products_count) }}</span>
                        </button>
                    @endforeach
                </div>

                @foreach ($stores as $index => $store)
                    @php
                        $windowProducts = $storeWindowProducts->get($store->id, collect());
                    @endphp
                    <div role="tabpanel" id="store-panel-{{ $store->id }}" aria-labelledby="store-tab-{{ $store->id }}" tabindex="0"
                        x-show="active === {{ $index }}" @if ($index > 0) x-cloak @endif
                        x-transition:enter="store-window-enter" x-transition:enter-start="store-window-enter-start"
                        class="store-window">
                        <div class="store-window-copy">
                            <div class="store-window-id">
                                <span class="store-window-mark">
                                    @if ($store->logo_path)
                                        <img src="{{ Storage::disk('public')->url($store->logo_path) }}" alt="" class="size-full object-cover">
                                    @else
                                        {{ Str::upper(Str::substr($store->name, 0, 1)) }}
                                    @endif
                                </span>
                                <h2 class="store-window-name">{{ $store->name }}</h2>
                            </div>
                            <p class="store-window-about">{{ $store->description ? Str::limit($store->description, 150) : 'An independent seller on JMMart. Every order is cash on delivery.' }}</p>
                            <dl class="store-window-facts">
                                <div><dt>Products</dt><dd>{{ number_format($store->public_products_count) }}</dd></div>
                                @if ($store->on_sale_products_count > 0)
                                    <div><dt>On sale</dt><dd>{{ number_format($store->on_sale_products_count) }}</dd></div>
                                @endif
                            </dl>
                            <a href="{{ route('catalog.store', $store->slug) }}" class="store-window-visit">Visit {{ $store->name }} <x-returns.icon name="chevron-right" class="size-4" /></a>
                        </div>

                        <ul class="store-window-shelf" aria-label="Products from {{ $store->name }}">
                            @foreach ($windowProducts as $product)
                                @php
                                    $displayPrice = (string) $product->price;
                                    if ($product->variants_min_price !== null && (! $product->variants_using_base_price_exists || bccomp((string) $product->variants_min_price, $displayPrice, 2) < 0)) {
                                        $displayPrice = (string) $product->variants_min_price;
                                    }
                                    $isOnSale = $product->compare_at_price !== null && bccomp((string) $product->compare_at_price, $displayPrice, 2) > 0;
                                @endphp
                                <li>
                                    <a href="{{ route('catalog.show', [$store->slug, $product->slug]) }}" class="store-window-tile">
                                        @if ($product->thumbnail)
                                            <img src="{{ Storage::disk('product_images')->url($product->thumbnail->path) }}" alt="" width="480" height="480" loading="{{ $index === 0 ? 'eager' : 'lazy' }}" @if ($index === 0 && $loop->first) fetchpriority="high" @endif>
                                        @else
                                            <span class="store-window-tile-empty" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 10h8M8 14h5"/></svg></span>
                                        @endif
                                        @if ($product->stock_quantity < 1)
                                            <span class="store-window-flag">Sold out</span>
                                        @elseif ($isOnSale)
                                            <span class="store-window-flag store-window-flag-sale">On sale</span>
                                        @endif
                                        <span class="store-window-label">
                                            <span class="store-window-product">{{ $product->name }}</span>
                                            <span class="store-window-price">₱{{ number_format((float) $displayPrice, fmod((float) $displayPrice, 1) === 0.0 ? 0 : 2) }}@if ($isOnSale) <s>₱{{ number_format((float) $product->compare_at_price, fmod((float) $product->compare_at_price, 1) === 0.0 ? 0 : 2) }}</s>@endif</span>
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('catalog.store', $store->slug) }}" class="store-window-visit store-window-visit-end">Visit {{ $store->name }} <x-returns.icon name="chevron-right" class="size-4" /></a>
                    </div>
                @endforeach
            </section>
        @else
            <section class="marketplace-section home-stores" aria-labelledby="marketplace-home-heading">
                <h2 id="marketplace-home-heading" class="text-2xl font-bold tracking-[-.02em] text-ink sm:text-[28px]">Shop local stores</h2>
                <p class="mt-1 text-sm text-muted">Independent sellers, one cart. Pay when your order arrives.</p>
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
            <section class="my-10 seller-window" aria-labelledby="home-seller-heading">
                <div>
                    <h2 id="home-seller-heading" class="marketplace-section-title">Open the next shop window on JMMart.</h2>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-muted">List your products, take cash-on-delivery orders, and answer customer reviews from Seller Center.</p>
                </div>
                <ol class="seller-window-steps">
                    <li><strong>Apply</strong><span>Tell us about your store. We review every seller.</span></li>
                    <li><strong>List products</strong><span>Add photos, prices, variants and stock.</span></li>
                    <li><strong>Fulfill orders</strong><span>Deliver orders and collect payment on delivery.</span></li>
                </ol>
                <a href="{{ auth()->check() ? route('seller-application.create') : route('login', ['redirect' => '/start-selling']) }}" class="inline-flex min-h-11 items-center justify-center rounded-md bg-brand px-5 text-sm font-semibold text-white hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Start selling</a>
            </section>
        @endif
    </div>
</x-app-layout>
