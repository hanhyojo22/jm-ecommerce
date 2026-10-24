@php
    $sharedImages = $product->images->whereNull('product_variant_id');
    $fallbackImages = $sharedImages->isNotEmpty() ? $sharedImages : $product->images;
    $media = fn ($images) => $images->map(fn ($image) => ['url' => Storage::disk('product_images')->url($image->path), 'alt' => $product->name])->values()->all();
    $variantOptions = $product->variants->map(function ($variant) use ($product, $media, $fallbackImages) {
        $images = $product->images->where('product_variant_id', $variant->id);
        $price = $variant->price ?? $product->price;
        return ['id' => $variant->id, 'stock' => min($variant->stock_quantity, $product->stock_quantity), 'discount' => $product->compare_at_price ? max(0, (int) round((1 - (float) $price / (float) $product->compare_at_price) * 100)) : 0, 'label' => $variant->label(), 'sku' => $variant->sku, 'price' => '₱'.number_format($price, 2), 'onSale' => $product->compare_at_price !== null && bccomp((string) $product->compare_at_price, (string) $price, 2) > 0, 'images' => $media($images->isNotEmpty() ? $images : $fallbackImages)];
    });
    $initialVariant = $variantOptions->first(fn ($variant) => $variant['stock'] > 0) ?? $variantOptions->first();
    $detailState = ['discount' => $initialVariant['discount'] ?? ($product->compare_at_price ? max(0, (int) round((1 - (float) $product->price / (float) $product->compare_at_price) * 100)) : 0), 'label' => $initialVariant['label'] ?? '', 'sku' => $initialVariant['sku'] ?? $product->sku, 'selected' => 0, 'quantity' => 1, 'variantId' => $initialVariant['id'] ?? '', 'max' => $initialVariant['stock'] ?? $product->stock_quantity, 'price' => $initialVariant['price'] ?? '₱'.number_format($product->price, 2), 'onSale' => $initialVariant['onSale'] ?? ($product->compare_at_price !== null && bccomp((string) $product->compare_at_price, (string) $product->price, 2) > 0), 'gallery' => $initialVariant['images'] ?? $media($fallbackImages), 'variants' => $variantOptions->all()];
    $productUrl = route('catalog.show', [$product->store->slug, $product->slug]);
    $canBuy = Auth::check() && in_array(Auth::user()->account_type, ['buyer', 'seller'], true) && Auth::user()->hasVerifiedClerkEmail() && ! Auth::user()->isMemberOfTenant($product->tenant_id);
    $isGuest = ! Auth::check();
    // Suggestions: the store's other products first, then similar products from elsewhere, never twice.
    $moreFromStore = $sellerProducts->take(4);
    $similarProducts = $relatedProducts->reject(fn ($related) => $moreFromStore->contains('id', $related->id))->take(4);
    $metaDescription = str(strip_tags($product->description ?: $product->name.' from '.$product->store->name.' on JMMart'))->squish()->limit(155)->toString();
    $productSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product->name,
        'description' => $metaDescription,
        'image' => $product->images->map(fn ($image) => url(Storage::disk('product_images')->url($image->path)))->all(),
        'sku' => $product->sku,
        'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand] : null,
        'offers' => [
            '@type' => 'Offer',
            'url' => $productUrl,
            'priceCurrency' => 'PHP',
            'price' => $product->price,
            'availability' => $product->stock_quantity > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'seller' => ['@type' => 'Organization', 'name' => $product->store->name],
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}"><meta name="clerk-publishable-key" content="{{ config('services.clerk.publishable_key') }}"><meta name="clerk-sign-in-url" content="{{ config('services.clerk.sign_in_url') }}"><meta name="clerk-sign-up-url" content="{{ config('services.clerk.sign_up_url') }}">@auth('clerk')<meta name="clerk-session-refresh-endpoint" content="{{ route('clerk.session.refresh') }}">@endauth<title>{{ $product->name }} | JMMart</title><meta name="description" content="{{ $metaDescription }}"><link rel="canonical" href="{{ $productUrl }}">@if($product->images->isNotEmpty())<script type="application/ld+json">{!! Illuminate\Support\Js::encode($productSchema) !!}</script>@endif@include('partials.fonts')@vite(['resources/css/product-detail.css', 'resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-dvh bg-canvas font-sans text-ink antialiased">
    <x-marketplace.header />
    <div class="prototype-product">
    @php($average = (float) ($product->published_reviews_avg_rating ?? 0))
    <main id="main-content" x-data="{ ...@js($detailState), zoomTrigger: null, buyBar: false, chooseVariant(id) { const variant = this.variants.find(option => option.id === id); if (!variant) return; this.variantId = id; this.discount = variant.discount; this.label = variant.label; this.sku = variant.sku; this.max = variant.stock; this.price = variant.price; this.onSale = variant.onSale; this.gallery = variant.images; this.selected = 0; this.quantity = 1; } }" class="pd-container pd-page">
        <nav aria-label="Breadcrumb" class="marketplace-breadcrumb"><a href="{{ route('home') }}">Home</a><span aria-hidden="true">/</span><a href="{{ route('catalog.index') }}">Marketplace</a>@if ($product->category)<span aria-hidden="true">/</span><a href="{{ route('catalog.index', ['category' => $product->category->slug]) }}">{{ $product->category->name }}</a>@endif</nav>
        @if($errors->any())<div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">{{ $errors->first() }} <a href="#purchase" class="font-semibold underline">Try again</a></div>@endif
        <div class="pd-grid">
            <x-marketplace.product-gallery :product="$product" :initial-images="$detailState['gallery']" />
            <section class="pd-info" aria-labelledby="product-heading">
                <a href="{{ route('catalog.store', $product->store->slug) }}" class="pd-store-line"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#5985d0" stroke-width="1.7" aria-hidden="true"><path d="m12 3 8 3v6c0 5-5 8-8 10-3-2-8-5-8-10V6l8-3Z"/><path d="m8 12 3 3 5-6"/></svg>VERIFIED LOCAL STORE @if ($product->store->city)&middot; {{ Str::upper($product->store->city) }}@endif</a>
                <h1 id="product-heading">{{ $product->name }}</h1>
                <div class="pd-rating"><span class="pd-stars" role="img" aria-label="{{ number_format($average, 1) }} out of 5 stars">@foreach(range(1,5) as $star)<span @class(['text-slate-300' => $star > round($average)])>&#9733;</span>@endforeach</span><b>{{ $product->published_reviews_count ? number_format($average, 1) : 'New' }}</b><a href="#reviews">({{ $product->published_reviews_count }} {{ Str::plural('review', $product->published_reviews_count) }})</a><span aria-hidden="true">&middot;</span><span>{{ number_format((int) $product->sold_quantity) }} sold</span></div>
                <div class="pd-price-panel"><span data-selected-price class="pd-price" x-text="price">{{ $detailState['price'] }}</span>@if ($product->compare_at_price)<del x-show="onSale" class="pd-old-price">&#8369;{{ number_format($product->compare_at_price, 2) }}</del><span x-show="onSale" class="pd-sale" x-text="'Save ' + discount + '%'">Save {{ $detailState['discount'] }}%</span>@endif<p>Price per item @if ($product->sku || $product->variants->isNotEmpty())&middot; SKU <span x-text="sku">{{ $detailState['sku'] }}</span>@endif</p></div>
                @if ($product->variants->isNotEmpty())
                    <fieldset><legend class="pd-label"><strong>Choose a variant</strong><span class="text-muted" x-text="label">{{ $detailState['label'] }}</span></legend><div class="pd-variants">
                        @foreach ($product->variants as $variant)
                            <label @class(['pd-variant', 'is-empty' => $variant->stock_quantity < 1])><input type="radio" name="product-option" value="{{ $variant->id }}" @checked($detailState['variantId'] === $variant->id) :checked="variantId === @js($variant->id)" @change="chooseVariant(@js($variant->id))"><i aria-hidden="true" style="background:{{ match (strtolower($variant->color)) { 'black', 'midnight black' => '#292d34', 'white', 'cloud white' => '#e8e4dc', 'navy' => '#263b60', 'blue' => '#4d7fc4', 'red' => '#b74e57', 'green' => '#62856b', 'pink' => '#ddb2bf', default => '#97a0af' } }}"></i><span>{{ $variant->label() }}@if ($variant->stock_quantity < 1) &middot; Sold out @endif</span></label>
                        @endforeach
                    </div></fieldset>
                @endif
                <p data-selected-stock class="pd-stock" :class="{ sold: max < 1 }" aria-live="polite"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m4 12 5 5L20 6"/></svg><span x-text="max > 0 ? max + (variants.length ? ' available in this option' : ' available') + ' · Ready to ship' : 'This option is sold out'"></span></p>
                <div id="purchase" x-ref="purchase">
                    @if ($canBuy)
                        <form id="pd-purchase-form" method="POST" action="{{ route('cart.store', $product) }}" data-add-to-cart data-clerk-session-refresh="{{ route('clerk.session.refresh') }}">
                            @csrf<input name="quantity" type="hidden" x-model="quantity">@if ($product->variants->isNotEmpty())<input type="hidden" name="variant_id" value="{{ $detailState['variantId'] }}" :value="variantId">@endif
                            <div class="pd-buy-row">
                                <div class="pd-qty" role="group" aria-label="Quantity"><button type="button" @click="quantity = Math.max(1, quantity - 1)" :disabled="quantity <= 1 || max < 1" aria-label="Reduce quantity">&#8722;</button><output x-text="quantity">1</output><button type="button" @click="quantity = Math.min(100, max, quantity + 1)" :disabled="quantity >= Math.min(100, max) || max < 1" aria-label="Increase quantity">+</button></div>
                                <button @disabled($product->stock_quantity < 1) :disabled="max < 1" class="pd-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M5 7h14l1 15H4L5 7ZM8 8V5a4 4 0 0 1 8 0v3"/></svg><span x-text="max > 0 ? 'Add to cart' : 'Sold out'">{{ $product->stock_quantity > 0 ? 'Add to cart' : 'Sold out' }}</span></button>
                                <button @disabled($product->stock_quantity < 1) :disabled="max < 1" data-checkout-url="{{ route('checkout.create') }}" class="pd-btn secondary">Buy now</button>
                            </div>
                        </form>
                    @elseif ($isGuest)
                        <div class="pd-buy-row"><a x-show="max > 0" href="{{ route('login', ['redirect' => request()->getPathInfo()] + ($product->variants->isEmpty() ? ['add_to_cart' => $product->id] : [])) }}" class="pd-btn" aria-label="Sign in to add to cart">Add to cart</a><a x-show="max > 0" href="{{ route('login', ['redirect' => request()->getPathInfo()]) }}" class="pd-btn secondary">Buy now</a><button x-show="max < 1" disabled class="pd-btn secondary">Sold out</button></div>
                    @elseif (Auth::user()->isMemberOfTenant($product->tenant_id))
                        <p class="pd-help">You cannot buy products from a store you manage.</p>
                    @elseif (in_array(Auth::user()->account_type, ['buyer', 'seller'], true))
                        <a href="{{ route('profile.edit') }}" class="pd-btn secondary mt-5">Verify your email to add to cart</a>
                    @endif
                </div>
                <div class="pd-meta">@if ($product->free_shipping)<span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M2 5h13v13H2zM15 10h4l3 4v4h-7"/><circle cx="6" cy="19" r="2"/><circle cx="18" cy="19" r="2"/></svg>Free shipping</span>@endif<span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/></svg>7-day returns</span><span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="12" cy="12" r="3"/></svg>Cash on delivery</span><span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m12 3 8 3v6c0 5-5 8-8 10-3-2-8-5-8-10V6l8-3Z"/><path d="m8 12 3 3 5-6"/></svg>Seller approved</span></div>
                <div class="pd-store-card"><span class="pd-store-avatar">@if ($product->store->logo_path)<img src="{{ Storage::disk('public')->url($product->store->logo_path) }}" alt="" class="size-full object-cover">@else{{ Str::upper(Str::substr($product->store->name, 0, 2)) }}@endif</span><div><b>{{ $product->store->name }}</b><p>Approved store @if ($product->store->city)&middot; {{ $product->store->city }}@endif</p></div><div class="pd-store-actions">@if (Auth::check() && in_array(Auth::user()->account_type, ['buyer', 'seller'], true) && Auth::user()->hasVerifiedClerkEmail() && ! Auth::user()->isMemberOfTenant($product->tenant_id))<form method="POST" action="{{ route('messages.products.start', $product) }}">@csrf<button type="submit" class="pd-btn secondary small">Message seller</button></form>@endif<a href="{{ route('catalog.store', $product->store->slug) }}" class="pd-btn secondary small">Visit store <span aria-hidden="true">&#8594;</span></a></div></div>
            </section>
        </div>
        <div class="pd-below">
            <div class="min-w-0 space-y-6">
            @if (filled($product->description))
                {{-- The description sits below the buy box so price and Add to cart stay above the fold. --}}
                <section class="pd-panel" aria-labelledby="about-heading">
                    <h2 id="about-heading">About this product</h2>
                    @if (Str::length($product->description) > 240)
                        <details class="pd-description-disclosure">
                            <summary>
                                <span class="pd-description-more">Read product description</span>
                                <span class="pd-description-less">Hide product description</span>
                            </summary>
                            <p class="pd-description">{{ $product->description }}</p>
                        </details>
                    @else
                        <p class="pd-description">{{ $product->description }}</p>
                    @endif
                </section>
            @endif
            <section class="pd-panel pd-conversations" aria-labelledby="conversations-heading">
                @php($conversationCount = $product->published_reviews_count + $discussions->total())
                <div class="pd-panel-head"><h2 id="conversations-heading">Reviews &amp; conversations</h2><span class="pd-small">{{ $conversationCount }} {{ Str::plural('conversation', $conversationCount) }}</span></div>
                <p class="pd-small">Ask a question, share your experience, or reply to the store.</p>
                <section id="reviews" aria-labelledby="reviews-heading">
                    <h3 id="reviews-heading" class="sr-only">Customer reviews</h3>
                    @if ($product->published_reviews_count)
                        <div class="pd-summary"><span class="pd-score">{{ number_format($average, 1) }}</span><div><span class="pd-stars" aria-hidden="true">@foreach(range(1,5) as $star)<span @class(['text-slate-300' => $star > round($average)])>&#9733;</span>@endforeach</span><p class="pd-small">{{ $product->published_reviews_count }} product reviews</p></div><div class="pd-bars">@foreach(range(5,1) as $star)@php($count=(int)$distribution->get($star,0))<a href="{{ route('catalog.show', [$product->store->slug, $product->slug, 'rating'=>$star]) }}#reviews" class="pd-bar" aria-label="{{ $star }} star reviews: {{ $count }}"><span>{{ $star }}</span><span class="pd-bar-track"><span class="pd-bar-fill" style="width: {{ $count > 0 ? $star * 20 : 0 }}%"></span></span></a>@endforeach</div></div>
                    @else
                        <div class="pd-summary pd-summary-empty"><div><p class="font-semibold text-ink">No ratings yet</p><p class="pd-small">{{ $product->published_reviews_count }} product reviews · Buyers can review this product after it’s delivered.</p></div></div>
                    @endif
                    @if ($product->published_reviews_count)<details class="mb-5 pd-small" @if (request()->hasAny(['rating', 'photos', 'sort_reviews'])) open @endif><summary class="cursor-pointer py-2">Filter reviews</summary><form method="GET" class="mt-2 flex flex-wrap items-end gap-3"><div><label for="review-rating" class="field-label">Rating</label><select id="review-rating" name="rating" class="field-control"><option value="">All ratings</option>@foreach(range(5,1) as $star)<option value="{{ $star }}" @selected(($reviewFilters['rating'] ?? null)===$star)>{{ $star }} stars</option>@endforeach</select></div><label class="inline-flex min-h-11 items-center gap-2"><input type="checkbox" name="photos" value="1" @checked($reviewFilters['photos'] ?? false) class="rounded border-line text-brand">With photos</label><div><label for="sort-reviews" class="field-label">Sort</label><select id="sort-reviews" name="sort_reviews" class="field-control"><option value="recent" @selected(($reviewFilters['sort_reviews'] ?? 'recent')==='recent')>Most recent</option><option value="highest" @selected(($reviewFilters['sort_reviews'] ?? '')==='highest')>Highest rating</option><option value="lowest" @selected(($reviewFilters['sort_reviews'] ?? '')==='lowest')>Lowest rating</option></select></div><button class="pd-btn small">Apply</button></form></details>@endif
                    @forelse($reviews as $review)<livewire:reviews.thread :review="$review" :key="'catalog-review-'.$review->id" />@empty<p class="pd-small py-3">{{ $product->published_reviews_count ? 'No reviews match those filters.' : 'No customer reviews yet. Delivered buyers can be the first to share their experience.' }}</p>@endforelse
                    {{ $reviews->fragment('reviews')->links() }}
                </section>
                @include('marketplace.catalog._product-discussions')
            </section>
            </div>
            <aside class="min-w-0 space-y-6">
                <section class="pd-panel" aria-labelledby="details-heading"><h2 id="details-heading">The details</h2><dl id="specifications" class="pd-specs">@if ($product->category)<div><dt>Category</dt><dd>{{ $product->category->name }}</dd></div>@endif @if ($product->brand)<div><dt>Brand</dt><dd>{{ $product->brand }}</dd></div>@endif @if ($product->condition)<div><dt>Condition</dt><dd>{{ ucfirst($product->condition) }}</dd></div>@endif @if ($product->sku)<div><dt>SKU</dt><dd>{{ $product->sku }}</dd></div>@endif<div><dt>Sold by</dt><dd><a href="{{ route('catalog.store', $product->store->slug) }}">{{ $product->store->name }}</a></dd></div><div><dt>Availability</dt><dd>{{ $product->stock_quantity > 0 ? 'In stock ('.number_format($product->stock_quantity).' available)' : 'Out of stock' }}</dd></div></dl><div class="pd-help"><b id="shipping-heading">Good to know</b>{{ $product->free_shipping ? 'Free shipping on this item.' : 'Shipping is added at checkout.' }} Items from different stores are prepared separately. Pay the courier on delivery. You can request a return within 7 days of delivery.</div></section>
            </aside>
        </div>

        {{-- Suggestions as full product cards: photo, name, price and rating, so they can be compared. --}}
        @foreach ([['id' => 'more-from-store', 'title' => 'More from '.$product->store->name, 'link' => route('catalog.store', $product->store->slug), 'products' => $moreFromStore], ['id' => 'similar-products', 'title' => 'You might also like', 'link' => $product->category ? route('catalog.index', ['category' => $product->category->slug]) : route('catalog.index'), 'products' => $similarProducts]] as $row)
            @if ($row['products']->isNotEmpty())
                <section class="pd-suggestions" aria-labelledby="{{ $row['id'] }}-heading">
                    <div class="pd-suggestions-head"><h2 id="{{ $row['id'] }}-heading">{{ $row['title'] }}</h2><a href="{{ $row['link'] }}" class="pd-suggestions-link">See all <span aria-hidden="true">&rarr;</span></a></div>
                    <div class="pd-suggestions-grid">@foreach ($row['products'] as $suggestion)<x-marketplace.top-product-card :product="$suggestion" />@endforeach</div>
                </section>
            @endif
        @endforeach

        {{-- Sticky buy bar: slides up once the main buttons scroll out of view. It submits the same
             form, so the chosen option and quantity carry over. --}}
        @if ($canBuy || $isGuest)
            <div class="pd-buybar" x-cloak :class="buyBar && 'is-visible'" :aria-hidden="(! buyBar).toString()" x-init="const sync = () => buyBar = $refs.purchase.getBoundingClientRect().bottom < 0; sync(); window.addEventListener('scroll', () => requestAnimationFrame(sync), { passive: true })">
                <div class="pd-buybar-inner">
                    <span class="pd-buybar-thumb">@if ($detailState['gallery'][0]['url'] ?? null)<img :src="gallery[0]?.url" src="{{ $detailState['gallery'][0]['url'] }}" alt="">@endif</span>
                    <span class="pd-buybar-text"><strong>{{ $product->name }}</strong><span><b x-text="price">{{ $detailState['price'] }}</b>@if ($product->variants->isNotEmpty()) <span x-text="'· ' + label"></span>@endif</span></span>
                    @if ($canBuy)
                        <button type="submit" form="pd-purchase-form" :disabled="max < 1" :tabindex="buyBar ? 0 : -1" class="pd-btn secondary pd-buybar-buy" data-checkout-url="{{ route('checkout.create') }}">Buy now</button>
                        <button type="submit" form="pd-purchase-form" :disabled="max < 1" :tabindex="buyBar ? 0 : -1" class="pd-btn"><span x-text="max > 0 ? 'Add to cart' : 'Sold out'">Add to cart</span></button>
                    @else
                        <a x-show="max > 0" href="{{ route('login', ['redirect' => request()->getPathInfo()]) }}" :tabindex="buyBar ? 0 : -1" class="pd-btn">Sign in to buy</a>
                        <button x-show="max < 1" disabled class="pd-btn secondary">Sold out</button>
                    @endif
                </div>
            </div>
        @endif
    </main>
    <x-marketplace.footer />
    </div>
    <x-cookie-consent />
    @livewireScripts
</body>
</html>
