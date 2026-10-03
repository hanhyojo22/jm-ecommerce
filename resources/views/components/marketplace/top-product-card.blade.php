@props(['product'])

@php
    $isBuyer = Auth::check() && Auth::user()->account_type === 'buyer' && Auth::user()->hasVerifiedClerkEmail();
    $isBestSeller = (int) ($product->net_quantity_sold ?? 0) >= 3 && (int) ($product->qualifying_order_count ?? 0) >= 2;
@endphp

<article class="group relative min-w-0 bg-white">
    <a href="{{ route('catalog.show', [$product->store->slug, $product->slug]) }}" class="block aspect-square overflow-hidden rounded-md bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700 focus-visible:outline-offset-2">
        @if ($product->thumbnail)
            <img src="{{ Storage::disk('product_images')->url($product->thumbnail->path) }}" alt="{{ $product->name }}" width="320" height="320" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-105" />
        @else
            <div class="flex h-full items-center justify-center px-5 text-center text-sm font-semibold text-slate-500">Product image coming soon</div>
        @endif
    </a>
    @if ($isBestSeller)<span class="absolute left-3 top-3 rounded-full bg-emerald-700 px-2.5 py-1 text-xs font-semibold text-white">Best seller</span>@endif
    <div class="px-2 pb-3 pt-2">
        <a href="{{ route('catalog.store', $product->store->slug) }}" class="block truncate text-xs font-semibold text-emerald-800 hover:text-emerald-950 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700">{{ $product->store->name }}</a>
        <h3 class="mt-1 text-sm font-semibold leading-5 text-slate-950"><a href="{{ route('catalog.show', [$product->store->slug, $product->slug]) }}" class="[display:-webkit-box] overflow-hidden [-webkit-box-orient:vertical] [-webkit-line-clamp:2] hover:text-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700">{{ $product->name }}</a></h3>
        <p class="mt-2 text-lg font-bold tabular-nums text-rose-600">₱{{ number_format($product->price, 2) }}</p>
        @if ($product->published_reviews_count)<p class="mt-2 flex items-center gap-1 text-xs text-slate-600"><svg viewBox="0 0 20 20" class="size-3.5 fill-amber-400" aria-hidden="true"><path d="m10 1.6 2.5 5.1 5.6.8-4 3.9.9 5.5-5-2.7-5 2.7.9-5.5-4-3.9 5.6-.8L10 1.6Z" /></svg>{{ number_format((float) $product->published_reviews_avg_rating, 1) }} <span class="text-slate-400">({{ number_format($product->published_reviews_count) }})</span></p>@else<p class="mt-2 text-xs text-slate-500">No reviews yet</p>@endif
        @if ($isBuyer)<form method="POST" action="{{ route('cart.store', $product) }}" class="mt-3">@csrf<input type="hidden" name="quantity" value="1"><button class="min-h-10 w-full rounded-lg border border-emerald-700 px-3 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700">Add to cart</button></form>@endif
    </div>
</article>
