<x-app-layout>
    @php
        $itemsByStore = $cart?->items->groupBy(fn ($item) => $item->product->store_id) ?? collect();
        $hasUnavailableItems = $cart?->items->contains(fn ($item) => $item->product->status !== 'active' || $item->product->stock_quantity < $item->quantity) ?? false;
    @endphp

    <div class="page-shell min-h-[calc(100dvh-4.5rem)]">
        <div class="page-container">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="customer-page-title text-3xl font-bold tracking-[-0.03em] text-ink">Shopping cart</h1>
                    <p class="mt-1 text-sm text-muted">{{ $cart?->items->sum('quantity') ?? 0 }} {{ Str::plural('item', $cart?->items->sum('quantity') ?? 0) }} · Grouped by seller</p>
                </div>
                <a href="{{ route('catalog.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand transition hover:text-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Continue shopping</a>
            </div>
            @if (session('status'))
                <div class="mb-6 flex flex-wrap items-center gap-3 rounded-[14px] border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-ink" role="status">
                    <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-success text-white" aria-hidden="true">✓</span>
                    <span class="flex-1">{{ session('status') }}</span>
                    @if (session('removed_cart_item'))
                        <form method="POST" action="{{ route('cart.restore') }}">
                            @csrf
                            <button class="inline-flex min-h-11 items-center rounded-[10px] border border-brand px-3 text-sm font-semibold text-brand transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Undo</button>
                        </form>
                    @endif
                </div>
            @endif

            @if ($cart?->items->isNotEmpty())
                <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_21rem]">
                    <section aria-labelledby="cart-items">
                        <h2 id="cart-items" class="sr-only">Cart items grouped by seller</h2>
                        <div class="space-y-6">
                            @foreach ($itemsByStore as $storeItems)
                                @php
                                    $store = $storeItems->first()->product->store;
                                    $storeSubtotal = $storeItems->reduce(fn (string $total, $item): string => bcadd($total, (string) $item->line_total, 2), '0.00');
                                @endphp

                                <section class="overflow-hidden rounded-[14px] border border-line bg-surface" aria-labelledby="store-{{ $store->id }}">
                                    <div class="flex items-center justify-between gap-4 border-b border-line bg-canvas/70 px-4 py-3 sm:px-5">
                                        <div class="min-w-0">
                                            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-muted">Sold by</p>
                                            <a id="store-{{ $store->id }}" href="{{ route('catalog.store', $store->slug) }}" class="mt-0.5 block truncate text-sm font-semibold text-brand transition hover:text-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">{{ $store->name }}</a>
                                        </div>
                                        <p class="shrink-0 text-xs font-semibold text-muted">{{ $storeItems->sum('quantity') }} {{ Str::plural('item', $storeItems->sum('quantity')) }}</p>
                                    </div>

                                    <div class="divide-y divide-line">
                                        @foreach ($storeItems as $item)
                                            @php($isUnavailable = $item->product->status !== 'active' || $item->product->stock_quantity < $item->quantity)
                                            <article class="grid grid-cols-[5.5rem_minmax(0,1fr)] gap-4 p-4 sm:grid-cols-[7rem_minmax(0,1fr)_auto] sm:p-5">
                                                <a href="{{ route('catalog.show', [$item->product->store->slug, $item->product->slug]) }}" aria-label="View {{ $item->product->name }}" class="aspect-square overflow-hidden rounded-xl bg-slate-100 focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">
                                                    @if ($item->product->thumbnail)
                                                        <img src="{{ Storage::disk('product_images')->url($item->product->thumbnail->path) }}" alt="{{ $item->product->name }}" width="96" height="96" loading="lazy" class="h-full w-full object-cover">
                                                    @else
                                                        <div class="grid h-full place-items-center text-lg font-bold text-brand" aria-hidden="true">{{ Str::upper(Str::substr($store->name, 0, 1)) }}</div>
                                                    @endif
                                                </a>

                                                <div class="min-w-0">
                                                    <h4 class="break-words text-sm font-semibold leading-5 text-ink sm:text-base"><a href="{{ route('catalog.show', [$item->product->store->slug, $item->product->slug]) }}" class="transition hover:text-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">{{ $item->product->name }}</a></h4>
                                                    <p class="mt-1 text-sm text-muted">₱{{ number_format($item->product->price, 2) }} each</p>
                                                    @if ($isUnavailable)
                                                        <p class="mt-2 inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-900">{{ $item->product->status !== 'active' ? 'No longer available' : 'Only '.$item->product->stock_quantity.' available' }}</p>
                                                    @else
                                                        <p class="mt-2 text-xs font-semibold text-success">In stock</p>
                                                    @endif

                                                    <div class="mt-4 flex items-center justify-between gap-3 sm:hidden">
                                                        <form method="POST" action="{{ route('cart.update', $item) }}" class="inline-flex min-h-11 items-center overflow-hidden rounded-[10px] border border-line bg-surface">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button name="quantity" value="{{ $item->quantity - 1 }}" @disabled($item->quantity <= 1) class="grid h-11 w-11 place-items-center text-lg text-muted transition hover:bg-canvas disabled:text-slate-300" aria-label="Reduce quantity">−</button>
                                                            <span class="grid h-11 min-w-10 place-items-center border-x border-line text-sm font-semibold">{{ $item->quantity }}</span>
                                                            <button name="quantity" value="{{ $item->quantity + 1 }}" @disabled($item->quantity >= $item->product->stock_quantity || $item->product->status !== 'active') class="grid h-11 w-11 place-items-center text-lg text-muted transition hover:bg-canvas disabled:text-slate-300" aria-label="Increase quantity">+</button>
                                                        </form>
                                                        <p class="text-sm font-bold text-ink">₱{{ number_format($item->line_total, 2) }}</p>
                                                    </div>
                                                </div>

                                                <div class="hidden min-w-36 items-end justify-between gap-4 sm:flex sm:flex-col">
                                                    <p class="text-right text-base font-bold text-ink">₱{{ number_format($item->line_total, 2) }}</p>
                                                    <form method="POST" action="{{ route('cart.update', $item) }}" class="inline-flex min-h-11 items-center overflow-hidden rounded-[10px] border border-line bg-surface">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button name="quantity" value="{{ $item->quantity - 1 }}" @disabled($item->quantity <= 1) class="grid h-11 w-11 place-items-center text-lg text-muted transition hover:bg-canvas disabled:text-slate-300" aria-label="Reduce quantity">−</button>
                                                        <span class="grid h-11 min-w-10 place-items-center border-x border-line text-sm font-semibold">{{ $item->quantity }}</span>
                                                        <button name="quantity" value="{{ $item->quantity + 1 }}" @disabled($item->quantity >= $item->product->stock_quantity || $item->product->status !== 'active') class="grid h-11 w-11 place-items-center text-lg text-muted transition hover:bg-canvas disabled:text-slate-300" aria-label="Increase quantity">+</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('cart.destroy', $item) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="min-h-11 text-xs font-semibold text-muted transition duration-200 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2">Remove</button>
                                                    </form>
                                                </div>

                                                <form method="POST" action="{{ route('cart.destroy', $item) }}" class="col-span-2 sm:hidden">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="min-h-11 text-xs font-semibold text-muted transition duration-200 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2">Remove</button>
                                                </form>
                                            </article>
                                        @endforeach
                                    </div>

                                    <div class="flex items-center justify-between border-t border-line px-4 py-3 text-sm sm:px-5">
                                        <span class="font-medium text-muted">{{ $store->name }} subtotal</span>
                                        <span class="font-bold text-ink">₱{{ number_format($storeSubtotal, 2) }}</span>
                                    </div>
                                </section>
                            @endforeach
                        </div>
                    </section>

                    <aside class="lg:sticky lg:top-24 lg:self-start">
                        <div class="rounded-[14px] border border-line bg-surface p-5 sm:p-6">
                            <h3 class="text-lg font-semibold tracking-[-0.02em] text-ink">Order summary</h3>
                            <div class="mt-5 space-y-3 border-b border-line pb-5 text-sm">
                                <div class="flex justify-between text-muted"><span>Subtotal</span><span>₱{{ number_format($subtotal, 2) }}</span></div>
                                <div class="flex justify-between text-muted"><span>Shipping</span><span class="font-semibold text-success">Free</span></div>
                            </div>
                            <div class="mt-5 flex items-end justify-between"><span class="font-semibold text-ink">Total</span><span class="text-2xl font-bold tracking-[-0.04em] text-ink">₱{{ number_format($subtotal, 2) }}</span></div>

                            @if ($hasUnavailableItems)
                                <p class="mt-5 rounded-[10px] border border-amber-200 bg-amber-50 px-3 py-3 text-sm leading-5 text-amber-950">Update or remove unavailable items before checkout.</p>
                            @else
                                <a href="{{ route('checkout.create') }}" class="mt-6 inline-flex min-h-12 w-full items-center justify-center rounded-[10px] bg-brand px-4 text-sm font-semibold text-white transition duration-200 hover:bg-brand-hover active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Continue to checkout</a>
                            @endif
                            <p class="mt-4 text-center text-xs leading-5 text-muted">Cash on delivery · Free shipping · No hidden fees</p>
                            <a href="{{ route('catalog.index') }}" class="mt-4 inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:text-brand-hover">← Continue shopping</a>
                        </div>
                    </aside>
                </div>
            @else
                <div class="mx-auto max-w-md rounded-[14px] border border-dashed border-line bg-surface px-6 py-16 text-center">
                    <div class="mx-auto grid h-14 w-14 place-items-center rounded-[10px] bg-blue-50 text-brand" aria-hidden="true"><svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h2l2.4 12.1a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 2-1.6L21 7H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg></div>
                    <h3 class="mt-5 text-xl font-semibold tracking-[-0.02em] text-ink">Your cart is waiting.</h3>
                    <p class="mt-2 text-sm leading-6 text-muted">Choose something from an independent store and it will appear here.</p>
                    <a href="{{ route('catalog.index') }}" class="mt-6 inline-flex min-h-11 items-center rounded-[10px] bg-brand px-4 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Explore products</a>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
