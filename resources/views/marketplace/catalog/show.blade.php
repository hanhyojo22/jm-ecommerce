<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}"><meta name="clerk-publishable-key" content="{{ config('services.clerk.publishable_key') }}"><meta name="clerk-sign-in-url" content="{{ config('services.clerk.sign_in_url') }}"><meta name="clerk-sign-up-url" content="{{ config('services.clerk.sign_up_url') }}">@auth('clerk')<meta name="clerk-session-refresh-endpoint" content="{{ route('clerk.session.refresh') }}">@endauth<title>{{ $product->name }} | JMMart</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-dvh bg-canvas font-sans text-ink antialiased">
    <x-marketplace.header />
    @php($average = (float) ($product->published_reviews_avg_rating ?? 0))
    @php($isOnSale = $product->compare_at_price !== null && (float) $product->compare_at_price > (float) $product->price)
    <main class="mx-auto max-w-[1320px] px-[18px] py-6 sm:px-8 sm:py-10">
        <nav aria-label="Breadcrumb" class="mb-6 flex min-w-0 items-center gap-2 overflow-x-auto whitespace-nowrap text-sm text-muted sm:mb-8"><a href="{{ route('catalog.index') }}" class="shrink-0 font-medium transition hover:text-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Marketplace</a><span aria-hidden="true">/</span><a href="{{ route('catalog.store', $product->store->slug) }}" class="max-w-40 shrink-0 truncate font-medium transition hover:text-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">{{ $product->store->name }}</a><span aria-hidden="true">/</span><span class="min-w-0 truncate text-muted">{{ $product->name }}</span></nav>
        @if($errors->any())<div class="mb-6 flex items-start justify-between gap-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert"><span>{{ $errors->first() }}</span><a href="#purchase" class="shrink-0 font-semibold underline underline-offset-4">Try again</a></div>@endif

        <div x-data="{ selected: 0, zoomTrigger: null, quantity: 1, max: {{ max($product->stock_quantity, 1) }} }" class="grid items-start gap-8 lg:grid-cols-[minmax(0,1.1fr)_minmax(22rem,.9fr)] lg:gap-14">
            <section class="min-w-0" aria-label="Product images">
                <div class="overflow-hidden rounded-[14px] border border-line bg-white">
                    <div class="relative aspect-[4/3.4] bg-canvas">
                        @if ($product->images->isNotEmpty())
                            @foreach ($product->images as $image)
                                <button type="button" x-show="selected === {{ $loop->index }}" @if (! $loop->first) x-cloak @endif @click="zoomTrigger = $event.currentTarget; $refs.zoomDialog.showModal()" class="absolute inset-0 h-full w-full focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand focus-visible:outline-offset-[-2px]" aria-label="Zoom image {{ $loop->iteration }} of {{ $product->images->count() }}">
                                    <img src="{{ Storage::disk('product_images')->url($image->path) }}" alt="{{ $product->name }}{{ $product->images->count() > 1 ? ', image '.$loop->iteration : '' }}" width="800" height="680" loading="{{ $loop->first ? 'eager' : 'lazy' }}" @if ($loop->first) fetchpriority="high" @endif class="h-full w-full object-contain">
                                </button>
                            @endforeach
                            @if ($product->images->count() > 1)
                                <button type="button" @click="selected = (selected - 1 + {{ $product->images->count() }}) % {{ $product->images->count() }}" class="absolute left-3 top-1/2 z-10 grid size-10 -translate-y-1/2 place-items-center rounded-full border border-line bg-white text-xl shadow-sm hover:border-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand" aria-label="Previous product image">‹</button>
                                <button type="button" @click="selected = (selected + 1) % {{ $product->images->count() }}" class="absolute right-3 top-1/2 z-10 grid size-10 -translate-y-1/2 place-items-center rounded-full border border-line bg-white text-xl shadow-sm hover:border-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand" aria-label="Next product image">›</button>
                            @endif
                            <span class="pointer-events-none absolute bottom-3 right-3 rounded-lg border border-line bg-white/95 px-2.5 py-1 text-xs text-muted"><span x-text="selected + 1"></span> / {{ $product->images->count() }} · Click to zoom</span>
                        @else
                            <div class="flex h-full items-center justify-center px-8 text-center text-sm font-semibold leading-6 text-muted">Product image coming soon from {{ $product->store->name }}</div>
                        @endif
                    </div>
                    @if ($product->images->count() > 1)
                        <div class="flex items-center gap-2 overflow-x-auto border-t border-line bg-white p-3" role="group" aria-label="Choose a product image">
                            @foreach ($product->images as $image)
                                <button type="button" @click="selected = {{ $loop->index }}" @keydown.right.prevent="selected = Math.min(selected + 1, {{ $product->images->count() - 1 }})" @keydown.left.prevent="selected = Math.max(selected - 1, 0)" :aria-pressed="selected === {{ $loop->index }}" class="size-16 shrink-0 overflow-hidden rounded-lg ring-offset-2 transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand" :class="selected === {{ $loop->index }} ? 'ring-2 ring-brand' : 'ring-1 ring-line hover:ring-brand/50'" aria-label="Show image {{ $loop->iteration }}"><img src="{{ Storage::disk('product_images')->url($image->path) }}" alt="" width="64" height="64" loading="lazy" class="h-full w-full object-cover"></button>
                            @endforeach
                        </div>
                    @endif
                </div>
                @if ($product->images->isNotEmpty())
                    <dialog x-ref="zoomDialog" @close="zoomTrigger?.focus()" @keydown.left.prevent="selected = (selected - 1 + {{ $product->images->count() }}) % {{ $product->images->count() }}" @keydown.right.prevent="selected = (selected + 1) % {{ $product->images->count() }}" aria-label="Enlarged product image" class="max-h-[95dvh] w-[min(95vw,72rem)] max-w-none rounded-[14px] border border-line bg-white p-4 shadow-xl backdrop:bg-slate-950/80 sm:p-6">
                        <div class="flex justify-end"><button type="button" @click="$refs.zoomDialog.close()" class="min-h-10 rounded-lg border border-line px-4 text-sm font-semibold hover:border-brand">Close</button></div>
                        @foreach ($product->images as $image)
                            <img x-show="selected === {{ $loop->index }}" @if (! $loop->first) x-cloak @endif src="{{ Storage::disk('product_images')->url($image->path) }}" alt="Enlarged image {{ $loop->iteration }} of {{ $product->name }}" width="1200" height="1020" class="mx-auto max-h-[75dvh] w-full object-contain">
                        @endforeach
                        @if ($product->images->count() > 1)
                            <div class="mt-3 flex items-center justify-center gap-4 text-sm text-muted">
                                <button type="button" @click="selected = (selected - 1 + {{ $product->images->count() }}) % {{ $product->images->count() }}" class="min-h-10 rounded-lg border border-line px-3 hover:border-brand" aria-label="Previous enlarged image">Previous</button>
                                <span><span x-text="selected + 1"></span> / {{ $product->images->count() }}</span>
                                <button type="button" @click="selected = (selected + 1) % {{ $product->images->count() }}" class="min-h-10 rounded-lg border border-line px-3 hover:border-brand" aria-label="Next enlarged image">Next</button>
                            </div>
                        @endif
                    </dialog>
                @endif
                <div class="mt-4 rounded-[14px] border border-line bg-white p-5 sm:p-6">
                    <h2 class="text-base font-semibold">Shopping with {{ $product->store->name }}</h2>
                    <p class="mt-2 text-sm leading-6 text-muted">Free shipping on this item · Cash on delivery · Message the seller for order and return help.</p>
                </div>
            </section>
            <section class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-[0.03em] {{ $product->stock_quantity > 0 ? 'text-success' : 'text-danger' }}">{{ $product->stock_quantity > 0 ? 'In stock — '.number_format($product->stock_quantity).' available' : 'Currently sold out' }}</p>
                <h1 class="mt-4 text-balance text-3xl font-bold leading-tight tracking-[-0.03em] sm:text-4xl">{{ $product->name }}</h1>
                <div class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted">
                    @if ($product->published_reviews_count)
                        <a href="#reviews" class="font-semibold text-ink hover:text-brand"><span class="text-amber-500" aria-hidden="true">★</span> {{ number_format($average, 1) }} <span class="font-normal text-muted">· {{ number_format($product->published_reviews_count) }} {{ Str::plural('review', $product->published_reviews_count) }}</span></a>
                    @else
                        <a href="#reviews" class="hover:text-brand">No reviews yet</a>
                    @endif
                    <span aria-hidden="true">·</span>
                    <span>Sold by <a href="{{ route('catalog.store', $product->store->slug) }}" class="font-semibold text-brand hover:text-brand-hover">{{ $product->store->name }}</a></span>
                </div>
                @if ($product->brand || $product->condition)<p class="mt-2 text-sm text-muted">{{ $product->brand }}@if ($product->brand && $product->condition) · @endif{{ ucfirst($product->condition ?? '') }}</p>@endif
                <div class="mt-5 flex flex-wrap items-baseline gap-x-3"><p class="text-3xl font-bold tabular-nums tracking-[-0.03em]">₱{{ number_format($product->price, 2) }}</p>@if ($isOnSale)<del class="text-base tabular-nums text-muted">₱{{ number_format($product->compare_at_price, 2) }}</del>@endif</div>
                <p class="mt-2 text-sm font-medium text-success">Free shipping · Cash on delivery</p>
                @if (! Auth::check() || Auth::user()->account_type === 'buyer')
                <div id="purchase" class="sticky bottom-0 z-20 -mx-[18px] mt-6 scroll-mt-24 border-t border-line bg-canvas/95 px-[18px] py-3 backdrop-blur sm:-mx-8 sm:px-8 lg:static lg:mx-0 lg:border-0 lg:bg-transparent lg:px-0 lg:py-0">
                    @if (Auth::check() && Auth::user()->account_type === 'buyer' && Auth::user()->hasVerifiedClerkEmail())
                        <form method="POST" action="{{ route('cart.store', $product) }}" data-add-to-cart data-clerk-session-refresh="{{ route('clerk.session.refresh') }}" class="space-y-4">
                            @csrf
                            <input name="quantity" type="hidden" x-model="quantity">
                            <div>
                                <p class="mb-2 text-sm font-semibold">Quantity</p>
                                <div class="inline-flex min-h-12 items-center rounded-[10px] border border-line bg-white">
                                    <button type="button" @click="quantity = Math.max(1, quantity - 1)" :disabled="quantity <= 1 || {{ $product->stock_quantity < 1 ? 'true' : 'false' }}" class="grid size-11 place-items-center text-lg disabled:text-slate-300" aria-label="Reduce quantity">−</button>
                                    <output class="min-w-10 text-center text-sm font-semibold tabular-nums" x-text="quantity"></output>
                                    <button type="button" @click="quantity = Math.min(max, quantity + 1)" :disabled="quantity >= max || {{ $product->stock_quantity < 1 ? 'true' : 'false' }}" class="grid size-11 place-items-center text-lg disabled:text-slate-300" aria-label="Increase quantity">+</button>
                                </div>
                            </div>
                            <button @disabled($product->stock_quantity < 1) class="min-h-12 w-full rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover disabled:cursor-not-allowed disabled:bg-slate-300">{{ $product->stock_quantity > 0 ? 'Add to cart' : 'Sold out' }}</button>
                        </form>
                    @elseif (! Auth::check())
                        <a href="{{ route('login', ['redirect' => request()->getPathInfo(), 'add_to_cart' => $product->id]) }}" class="flex min-h-12 w-full items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover">Sign in to add to cart</a>
                    @elseif (Auth::user()->account_type === 'buyer')
                        <a href="{{ route('profile.edit') }}" class="flex min-h-12 w-full items-center justify-center rounded-[10px] border border-brand px-5 text-sm font-semibold text-brand hover:bg-blue-50">Verify your email to add to cart</a>
                    @endif
                </div>
                @endif
                <ul class="mt-5 space-y-2 border-t border-line pt-4 text-sm text-muted">
                    <li>Secure checkout with cash on delivery</li>
                    <li>Free shipping on this item</li>
                    <li>Message the seller for order and return help</li>
                </ul>
                <div class="mt-5 rounded-[14px] border border-line bg-white p-4">
                    <div class="flex items-center gap-3">
                        <span class="grid size-11 shrink-0 place-items-center rounded-[10px] bg-brand text-lg font-bold text-white">{{ Str::upper(Str::substr($product->store->name, 0, 1)) }}</span>
                        <div class="min-w-0"><p class="truncate font-semibold">{{ $product->store->name }}</p><p class="text-xs text-muted">Independent JMMart seller</p></div>
                        <a href="{{ route('catalog.store', $product->store->slug) }}" class="ml-auto shrink-0 text-sm font-semibold text-brand hover:text-brand-hover">Visit store</a>
                    </div>
                    @if (Auth::check() && Auth::user()->account_type === 'buyer' && Auth::user()->hasVerifiedClerkEmail())
                        <form method="POST" action="{{ route('messages.products.start', $product) }}" class="mt-3 border-t border-line pt-3">@csrf<button class="text-sm font-semibold text-brand hover:text-brand-hover">Message seller</button></form>
                    @endif
                </div>
            </section>
        </div>

        <nav x-data="{ active: ({ '#specifications': 'specifications', '#reviews': 'reviews', '#shipping-heading': 'shipping' })[location.hash] || 'description' }" aria-label="Product information" class="mt-14 flex gap-6 overflow-x-auto border-b border-line text-sm font-semibold sm:mt-20">
            <a href="#details-heading" x-on:click="active = 'description'" :aria-current="active === 'description' ? 'location' : null" :class="active === 'description' ? 'border-brand text-ink' : 'border-transparent text-muted hover:border-brand hover:text-ink'" class="flex min-h-11 shrink-0 items-center border-b-2 transition">Description</a>
            <a href="#specifications" x-on:click="active = 'specifications'" :aria-current="active === 'specifications' ? 'location' : null" :class="active === 'specifications' ? 'border-brand text-ink' : 'border-transparent text-muted hover:border-brand hover:text-ink'" class="flex min-h-11 shrink-0 items-center border-b-2 transition">Specifications</a>
            <a href="#reviews" x-on:click="active = 'reviews'" :aria-current="active === 'reviews' ? 'location' : null" :class="active === 'reviews' ? 'border-brand text-ink' : 'border-transparent text-muted hover:border-brand hover:text-ink'" class="flex min-h-11 shrink-0 items-center border-b-2 transition">Reviews ({{ number_format($product->published_reviews_count) }})</a>
            <a href="#shipping-heading" x-on:click="active = 'shipping'" :aria-current="active === 'shipping' ? 'location' : null" :class="active === 'shipping' ? 'border-brand text-ink' : 'border-transparent text-muted hover:border-brand hover:text-ink'" class="flex min-h-11 shrink-0 items-center border-b-2 transition">Shipping &amp; returns</a>
        </nav>

        <section class="mt-5 grid gap-6 lg:grid-cols-2" aria-labelledby="details-heading">
            <div class="rounded-[14px] border border-line bg-surface p-5 sm:p-6">
                <h2 id="details-heading" class="scroll-mt-40 text-xl font-semibold tracking-[-0.02em]">Product details</h2>
                @if (filled($product->description))
                    <p class="mt-3 whitespace-pre-line text-sm leading-6 text-muted">{{ $product->description }}</p>
                @endif
                <dl id="specifications" class="mt-5 scroll-mt-40 divide-y divide-line border-t border-line text-sm">
                    @if ($product->category)
                        <div class="grid grid-cols-[10rem_1fr] gap-3 py-3"><dt class="font-medium text-muted">Category</dt><dd class="font-medium text-ink">{{ $product->category->name }}</dd></div>
                    @endif
                    @if ($product->brand)<div class="grid grid-cols-[10rem_1fr] gap-3 py-3"><dt class="font-medium text-muted">Brand</dt><dd class="font-medium text-ink">{{ $product->brand }}</dd></div>@endif
                    @if ($product->condition)<div class="grid grid-cols-[10rem_1fr] gap-3 py-3"><dt class="font-medium text-muted">Condition</dt><dd class="font-medium text-ink">{{ ucfirst($product->condition) }}</dd></div>@endif
                    <div class="grid grid-cols-[10rem_1fr] gap-3 py-3"><dt class="font-medium text-muted">Sold by</dt><dd><a href="{{ route('catalog.store', $product->store->slug) }}" class="font-semibold text-brand hover:text-brand-hover">{{ $product->store->name }}</a></dd></div>
                    @if (filled($product->sku))
                        <div class="grid grid-cols-[10rem_1fr] gap-3 py-3"><dt class="font-medium text-muted">SKU</dt><dd class="tabular-nums text-ink">{{ $product->sku }}</dd></div>
                    @endif
                    <div class="grid grid-cols-[10rem_1fr] gap-3 py-3"><dt class="font-medium text-muted">Availability</dt><dd class="font-medium {{ $product->stock_quantity > 0 ? 'text-success' : 'text-danger' }}">{{ $product->stock_quantity > 0 ? 'In stock ('.number_format($product->stock_quantity).' available)' : 'Out of stock' }}</dd></div>
                </dl>
            </div>
            <div class="space-y-6">
                <div class="rounded-[14px] border border-line bg-surface p-5 sm:p-6" aria-labelledby="shipping-heading">
                    <h2 id="shipping-heading" class="scroll-mt-40 text-xl font-semibold tracking-[-0.02em]">Shipping &amp; returns</h2>
                    <ul class="mt-3 space-y-2.5 text-sm leading-6 text-muted">
                        <li class="flex gap-2.5"><span class="text-success" aria-hidden="true">✓</span>Free shipping on this item</li>
                        <li class="flex gap-2.5"><span class="text-success" aria-hidden="true">✓</span>Cash on delivery — pay when your order arrives</li>
                        <li class="flex gap-2.5"><span class="text-success" aria-hidden="true">✓</span>Message the seller directly for order or return help</li>
                    </ul>
                </div>
            </div>
        </section>

        <section id="reviews" class="mt-10 scroll-mt-40 rounded-[14px] border border-line bg-white p-5 sm:p-6" aria-labelledby="reviews-heading"><div class="grid gap-10 lg:grid-cols-[18rem_minmax(0,1fr)]"><aside class="lg:sticky lg:top-24 lg:h-fit"><h2 id="reviews-heading" class="text-2xl font-semibold tracking-[-0.02em] text-ink">Customer reviews</h2>@if($product->published_reviews_count)<div class="mt-5 flex items-baseline gap-3"><p class="text-5xl font-semibold tabular-nums tracking-[-0.04em] text-ink">{{ number_format($average, 1) }}</p><p class="text-sm text-muted">out of 5</p></div><div class="mt-2 flex gap-0.5 text-amber-500" role="img" aria-label="{{ number_format($average, 1) }} out of 5 stars">@foreach(range(1,5) as $star)<svg viewBox="0 0 20 20" class="size-5 {{ $star <= round($average) ? 'fill-current' : 'fill-slate-200' }}"><path d="m10 1.6 2.5 5.1 5.6.8-4 3.9.9 5.5-5-2.7-5 2.7.9-5.5-4-3.9 5.6-.8L10 1.6Z"/></svg>@endforeach</div><p class="mt-2 text-sm leading-6 text-muted">From {{ $product->published_reviews_count }} verified {{ Str::plural('purchase', $product->published_reviews_count) }}</p><div class="mt-7 space-y-2">@foreach(range(5,1) as $star)@php($count=(int)$distribution->get($star,0))<a href="{{ route('catalog.show', [$product->store->slug, $product->slug, 'rating'=>$star]) }}#reviews" class="grid min-h-9 grid-cols-[2rem_1fr_2rem] items-center gap-2 rounded-md text-xs font-medium text-muted transition hover:bg-amber-50 hover:text-amber-950 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand"><span>{{ $star }} star</span><span class="h-1.5 overflow-hidden rounded-full bg-slate-200"><span class="block h-full rounded-full bg-amber-400" style="width: {{ ($count / $product->published_reviews_count) * 100 }}%"></span></span><span class="text-right tabular-nums">{{ $count }}</span></a>@endforeach</div>@else<p class="mt-3 max-w-xs text-sm leading-6 text-muted">There are no customer reviews yet. Delivered buyers can be the first to share their experience.</p>@endif</aside>
                <div><form method="GET" x-data="{ loading:false }" @submit="loading=true" class="flex flex-wrap items-end gap-3 border-y border-line py-4"><div><label for="review-rating" class="field-label">Rating</label><select id="review-rating" name="rating" class="field-control w-36"><option value="">All ratings</option>@foreach(range(5,1) as $star)<option value="{{ $star }}" @selected(($reviewFilters['rating'] ?? null)===$star)>{{ $star }} stars</option>@endforeach</select></div><label class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-muted"><input type="checkbox" name="photos" value="1" @checked($reviewFilters['photos'] ?? false) class="rounded border-line text-brand focus:ring-brand">With photos</label><div><label for="sort-reviews" class="field-label">Sort</label><select id="sort-reviews" name="sort_reviews" class="field-control w-40"><option value="recent" @selected(($reviewFilters['sort_reviews'] ?? 'recent')==='recent')>Most recent</option><option value="highest" @selected(($reviewFilters['sort_reviews'] ?? '')==='highest')>Highest rating</option><option value="lowest" @selected(($reviewFilters['sort_reviews'] ?? '')==='lowest')>Lowest rating</option></select></div><button class="min-h-11 rounded-lg bg-brand px-4 text-sm font-semibold text-white transition hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-wait disabled:bg-slate-300" :disabled="loading"><span x-text="loading ? 'Loading…' : 'Apply'"></span></button></form>
                    <div class="mt-7 space-y-8">@forelse($reviews as $review)<article class="border-b border-line pb-8 last:border-0"><div class="flex flex-wrap items-start justify-between gap-3"><div><p class="font-semibold text-ink">{{ Str::of($review->user->name)->explode(' ')->first() }}{{ Str::of($review->user->name)->explode(' ')->count() > 1 ? ' '.Str::upper(Str::substr(Str::of($review->user->name)->explode(' ')->last(),0,1)).'.' : '' }}</p><div class="mt-2 flex gap-0.5 text-amber-500" role="img" aria-label="{{ $review->rating }} out of 5 stars">@foreach(range(1,5) as $star)<svg viewBox="0 0 20 20" class="size-4 {{ $star <= $review->rating ? 'fill-current' : 'fill-slate-200' }}"><path d="m10 1.6 2.5 5.1 5.6.8-4 3.9.9 5.5-5-2.7-5 2.7.9-5.5-4-3.9 5.6-.8L10 1.6Z"/></svg>@endforeach</div></div><p class="text-sm text-muted">{{ $review->created_at->format('M j, Y') }}</p></div><p class="mt-3 text-xs font-semibold uppercase tracking-wide text-success">Verified purchase</p>@if($review->title)<h3 class="mt-4 font-semibold text-ink">{{ $review->title }}</h3>@endif @if($review->body)<p class="mt-2 max-w-2xl whitespace-pre-line text-sm leading-6 text-muted">{{ $review->body }}</p>@endif @if($review->photos->isNotEmpty())<div x-data="{ open:false, source:'' }" class="mt-4 flex flex-wrap gap-2">@foreach($review->photos as $photo)<button type="button" @click="source='{{ Storage::disk('public')->url($photo->path) }}';open=true" class="size-20 overflow-hidden rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand"><img src="{{ Storage::disk('public')->url($photo->path) }}" alt="Review photo {{ $loop->iteration }}" width="80" height="80" loading="lazy" class="h-full w-full object-cover"></button>@endforeach<div x-show="open" x-cloak @keydown.escape.window="open=false" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/80 p-4" role="dialog" aria-modal="true" aria-label="Expanded review photo"><button type="button" @click="open=false" class="absolute right-5 top-5 min-h-11 min-w-11 rounded-lg bg-surface px-3 py-2 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Close</button><img :src="source" alt="Expanded review photo" class="max-h-full max-w-full rounded-xl"></div></div>@endif @if($review->response)<div class="mt-5 border-t border-line pt-4"><p class="text-xs font-semibold uppercase tracking-wide text-muted">Response from seller</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-muted">{{ $review->response->body }}</p></div>@endif @auth @if(auth()->id()===$review->user_id)<div class="mt-5"><a href="{{ route('orders.show', $review->orderItem->sellerOrder->order_id) }}" class="text-sm font-semibold text-brand hover:text-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Manage your review</a></div>@elseif(auth()->user()->account_type==='buyer')<form method="POST" action="{{ route('reviews.report',$review) }}" class="mt-5 flex flex-wrap gap-2">@csrf<label class="sr-only" for="report-{{ $review->id }}">Report reason</label><input id="report-{{ $review->id }}" name="reason" required maxlength="100" placeholder="Report reason" class="field-control max-w-xs"><button class="min-h-11 text-sm font-semibold text-muted transition hover:text-danger focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger">Report</button></form>@endif @endauth</article>@empty<div class="py-8 text-center"><p class="font-semibold text-ink">{{ $product->published_reviews_count ? 'No reviews match those filters.' : 'No customer reviews yet.' }}</p>@if ($product->published_reviews_count)<a href="{{ route('catalog.show', [$product->store->slug,$product->slug]) }}#reviews" class="mt-2 inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:text-brand-hover">Clear filters</a>@endif</div>@endforelse</div><div class="mt-8">{{ $reviews->fragment('reviews')->links() }}</div></div>
            </div>
        </section>

        @if ($sellerProducts->isNotEmpty())
            <section class="mt-14 sm:mt-20" aria-labelledby="from-seller-heading">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div><h2 id="from-seller-heading" class="text-2xl font-bold tracking-[-0.02em]">More from {{ $product->store->name }}</h2></div>
                    <a href="{{ route('catalog.store', $product->store->slug) }}" class="text-sm font-semibold text-brand hover:text-brand-hover">Visit store →</a>
                </div>
                <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
                    @foreach ($sellerProducts as $sellerProduct)
                        <x-marketplace.top-product-card :product="$sellerProduct" />
                    @endforeach
                </div>
            </section>
        @endif

        @if ($relatedProducts->isNotEmpty())
            <section class="mt-14 sm:mt-20" aria-labelledby="related-heading">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div><h2 id="related-heading" class="text-2xl font-bold tracking-[-0.02em]">Related products</h2><p class="mt-1 text-sm text-muted">More to explore across the marketplace.</p></div>
                    <a href="{{ route('catalog.index') }}" class="text-sm font-semibold text-brand hover:text-brand-hover">View all →</a>
                </div>
                <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
                    @foreach ($relatedProducts as $relatedProduct)
                        <x-marketplace.top-product-card :product="$relatedProduct" />
                    @endforeach
                </div>
            </section>
        @endif
    </main>
    <x-marketplace.footer />
@livewireScripts
</body>
</html>
