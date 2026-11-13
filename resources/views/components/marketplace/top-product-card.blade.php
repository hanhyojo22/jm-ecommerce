@props(['product', 'eager' => false])

@php
    $shopper = Auth::user();
    $canShop = $shopper && in_array($shopper->account_type, ['buyer', 'seller'], true);
    $isOwnStore = $canShop && $shopper->tenantMemberships->contains('tenant_id', $product->tenant_id);
    $isBuyer = $canShop && $shopper->hasVerifiedClerkEmail() && ! $isOwnStore;
    $displayPrice = (string) $product->price;
    if ($product->variants_min_price !== null && (! $product->variants_using_base_price_exists || bccomp((string) $product->variants_min_price, $displayPrice, 2) < 0)) {
        $displayPrice = (string) $product->variants_min_price;
    }
    $isOnSale = $product->compare_at_price !== null && (float) $product->compare_at_price > (float) $displayPrice;
    $discountPercent = $isOnSale ? (int) round((1 - ((float) $displayPrice / (float) $product->compare_at_price)) * 100) : 0;
    $priceDecimals = fmod((float) $displayPrice, 1) === 0.0 ? 0 : 2;
    $compareAtPriceDecimals = $product->compare_at_price !== null && fmod((float) $product->compare_at_price, 1) !== 0.0 ? 2 : 0;
    $soldCount = (int) ($product->sold_quantity ?? $product->net_quantity_sold ?? 0);
    $formatCount = static fn (int $count): string => $count < 1000 ? number_format($count) : number_format($count / 1000, $count % 1000 === 0 ? 0 : 1).'k';
@endphp

<article class="marketplace-product-card group relative flex min-w-0 flex-col overflow-hidden rounded-[var(--radius-surface)] border border-line bg-surface shadow-[0_1px_2px_rgba(24,44,72,.04)] transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_14px_30px_rgba(15,23,42,.10)]">
    <a href="{{ route('catalog.show', [$product->store->slug, $product->slug]) }}" @if ($product->stock_quantity < 1) aria-label="{{ $product->name }}, sold out" @endif class="relative block aspect-square overflow-hidden rounded-t-[var(--radius-surface)] bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand focus-visible:outline-offset-[-2px]">
        @if ($product->thumbnail)
            <img src="{{ Storage::disk('product_images')->url($product->thumbnail->path) }}" alt="{{ $product->name }}" width="320" height="320" loading="{{ $eager ? 'eager' : 'lazy' }}" class="h-full w-full object-cover" />
        @else
            <div class="grid h-full place-items-center bg-slate-100 text-muted">
                <span class="sr-only">Product image coming soon</span>
                <svg class="size-16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 10h8M8 14h5"/></svg>
            </div>
        @endif
        @if ($product->stock_quantity < 1)
            <x-marketplace.sold-out-overlay compact />
        @endif
    </a>
    <div class="product-card-body flex flex-1 flex-col gap-1.5 p-3.5 sm:p-4">
        <div class="flex flex-wrap items-center gap-1 text-[11px] font-bold leading-none text-white">
            <span class="inline-flex h-5 items-center whitespace-nowrap rounded bg-warning-text px-1.5"><span aria-hidden="true">COD</span><span class="sr-only">Cash on delivery</span></span>
            @if ($product->free_shipping)
                <span class="inline-flex h-5 items-center whitespace-nowrap rounded bg-brand px-1.5">FREE SHIPPING</span>
            @endif
        </div>
        <h3 class="product-card-title min-h-12 text-[16px] font-medium leading-6 text-ink"><a href="{{ route('catalog.show', [$product->store->slug, $product->slug]) }}" class="line-clamp-2 hover:text-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">{{ $product->name }}</a></h3>
        <div class="flex flex-wrap items-center gap-2">
            <p class="product-card-price text-[20px] font-bold leading-6 tabular-nums {{ $isOnSale ? 'text-danger' : 'text-ink' }}">₱{{ number_format($displayPrice, $priceDecimals) }}</p>
            @if ($isOnSale)<span class="rounded bg-red-50 px-1.5 py-1 text-xs font-semibold leading-none text-danger">-{{ $discountPercent }}%</span>@endif
        </div>
        @if ($isOnSale)<del class="text-meta font-medium tabular-nums text-muted line-through decoration-[1.5px] decoration-muted/70" aria-label="Original price ₱{{ number_format($product->compare_at_price, $compareAtPriceDecimals) }}">₱{{ number_format($product->compare_at_price, $compareAtPriceDecimals) }}</del>@endif
        <div class="flex min-h-4 flex-wrap items-center gap-x-1 gap-y-0.5 text-xs leading-[1.55] text-muted">
            @if ($product->published_reviews_count)<svg viewBox="0 0 20 20" class="size-3.5 shrink-0 fill-amber-400" aria-hidden="true"><path d="m10 1.6 2.5 5.1 5.6.8-4 3.9.9 5.5-5-2.7-5 2.7.9-5.5-4-3.9 5.6-.8L10 1.6Z" /></svg><span class="shrink-0">{{ number_format((float) $product->published_reviews_avg_rating, 1) }}</span><span class="shrink-0 text-muted">({{ $formatCount((int) $product->published_reviews_count) }})</span>@else<span class="shrink-0">No reviews yet</span>@endif
            @if ($soldCount > 0)<span class="h-3 w-px shrink-0 bg-line" aria-hidden="true"></span><span class="shrink-0">{{ $formatCount($soldCount) }} Sold</span>@endif
        </div>
        <a href="{{ route('catalog.store', $product->store->slug) }}" title="{{ $product->store->city ?: 'Location unavailable' }}" aria-label="Visit {{ $product->store->name }}, {{ $product->store->city ?: 'location unavailable' }}" data-product-location class="mb-0.5 flex min-h-6 min-w-0 items-center gap-1.5 text-xs font-medium text-muted hover:text-brand"><svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg><span class="truncate">{{ $product->store->city ?: 'Location unavailable' }}</span></a>
        @if ($product->variants_exists && $product->stock_quantity > 0 && ! $isOwnStore)
            <a href="{{ route('catalog.show', [$product->store->slug, $product->slug]) }}#purchase" class="mt-auto inline-flex min-h-11 items-center justify-center rounded-md border border-line px-3 text-sm font-semibold text-brand hover:border-brand hover:bg-blue-50">Choose options</a>
        @elseif ($isBuyer)
            <form method="POST" action="{{ route('cart.store', $product) }}" data-add-to-cart data-clerk-session-refresh="{{ route('clerk.session.refresh') }}" class="mt-auto">@csrf<input type="hidden" name="quantity" value="1"><button @disabled($product->stock_quantity < 1) class="min-h-11 w-full rounded-md border border-line bg-white px-3 text-sm font-semibold text-ink transition hover:border-ink hover:bg-ink hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand disabled:cursor-not-allowed disabled:text-muted">{{ $product->stock_quantity > 0 ? 'Add to cart' : 'Sold out' }}</button></form>
        @elseif (! Auth::check() && $product->stock_quantity > 0)
            <a href="{{ route('login', ['redirect' => request()->getPathInfo(), 'add_to_cart' => $product->id]) }}" class="mt-auto flex min-h-11 w-full items-center justify-center rounded-md border border-line bg-white px-3 text-sm font-semibold text-ink transition hover:border-ink hover:bg-ink hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand" aria-label="Sign in to add {{ $product->name }} to cart">Add to cart</a>
        @elseif ($canShop && $product->stock_quantity > 0 && ! $isOwnStore)
            <a href="{{ route('profile.edit') }}" class="mt-auto flex min-h-11 w-full items-center justify-center rounded-md border border-line bg-white px-3 text-sm font-semibold text-ink">Verify email to add to cart</a>
        @elseif ($isOwnStore)
            <span class="mt-auto flex min-h-11 w-full items-center justify-center rounded-md border border-line px-3 text-center text-sm font-semibold text-muted">You cannot buy from your store</span>
        @elseif ($product->stock_quantity > 0)
            <span class="mt-auto flex min-h-11 w-full items-center justify-center rounded-md border border-line px-3 text-center text-sm font-semibold text-muted">Shopping unavailable for this account</span>
        @else
            <span class="mt-auto flex min-h-11 w-full items-center justify-center rounded-md border border-line px-3 text-sm font-semibold text-muted">Sold out</span>
        @endif
    </div>
</article>
