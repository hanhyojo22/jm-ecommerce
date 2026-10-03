<x-app-layout>
    <x-slot name="header">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">One cart, many independent sellers</p>
                <h2 class="mt-1 text-2xl font-semibold tracking-[-0.03em] text-slate-950">Your cart</h2>
            </div>
            <a href="{{ route('catalog.index') }}" class="hidden min-h-11 items-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition duration-200 hover:border-slate-950 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 sm:inline-flex">Continue shopping</a>
        </div>
    </x-slot>

    @php
        $itemsByStore = $cart?->items->groupBy(fn ($item) => $item->product->store_id) ?? collect();
        $hasUnavailableItems = $cart?->items->contains(fn ($item) => $item->product->status !== 'active' || $item->product->stock_quantity < $item->quantity) ?? false;
    @endphp

    <div class="min-h-screen bg-slate-50 py-8 sm:py-12">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 flex flex-wrap items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900" role="status">
                    <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-emerald-700 text-white" aria-hidden="true">✓</span>
                    <span class="flex-1">{{ session('status') }}</span>
                    @if (session('removed_cart_item'))
                        <form method="POST" action="{{ route('cart.restore') }}">
                            @csrf
                            <button class="inline-flex min-h-10 items-center rounded-lg border border-emerald-700 px-3 text-sm font-bold text-emerald-900 transition hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">Undo</button>
                        </form>
                    @endif
                </div>
            @endif

            @if ($cart?->items->isNotEmpty())
                <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_21rem]">
                    <section aria-labelledby="cart-items">
                        <div class="mb-5 flex items-end justify-between gap-4">
                            <div>
                                <h3 id="cart-items" class="text-xl font-semibold tracking-[-0.02em] text-slate-950">{{ $cart->items->sum('quantity') }} {{ Str::plural('item', $cart->items->sum('quantity')) }}</h3>
                                <p class="mt-1 text-sm text-slate-500">Organized by seller for a clearer checkout.</p>
                            </div>
                            <a href="{{ route('catalog.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-emerald-800 transition duration-200 hover:text-emerald-950 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 sm:hidden">Keep shopping</a>
                        </div>

                        <div class="space-y-6">
                            @foreach ($itemsByStore as $storeItems)
                                @php
                                    $store = $storeItems->first()->product->store;
                                    $storeSubtotal = $storeItems->reduce(fn (string $total, $item): string => bcadd($total, (string) $item->line_total, 2), '0.00');
                                @endphp

                                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_16px_32px_-28px_rgba(15,23,42,0.48)]" aria-labelledby="store-{{ $store->id }}">
                                    <div class="flex items-center justify-between gap-4 border-b border-slate-100 bg-slate-50/70 px-4 py-3 sm:px-5">
                                        <div class="min-w-0">
                                            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Sold by</p>
                                            <a id="store-{{ $store->id }}" href="{{ route('catalog.store', $store->slug) }}" class="mt-0.5 block truncate text-sm font-semibold text-emerald-800 transition hover:text-emerald-950 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">{{ $store->name }}</a>
                                        </div>
                                        <p class="shrink-0 text-xs font-semibold text-slate-500">{{ $storeItems->sum('quantity') }} {{ Str::plural('item', $storeItems->sum('quantity')) }}</p>
                                    </div>

                                    <div class="divide-y divide-slate-100">
                                        @foreach ($storeItems as $item)
                                            @php($isUnavailable = $item->product->status !== 'active' || $item->product->stock_quantity < $item->quantity)
                                            <article class="grid grid-cols-[5.5rem_minmax(0,1fr)] gap-4 p-4 sm:grid-cols-[7rem_minmax(0,1fr)_auto] sm:p-5">
                                                <a href="{{ route('catalog.show', [$item->product->store->slug, $item->product->slug]) }}" class="aspect-square overflow-hidden rounded-xl bg-[#e8eee8] focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">
                                                    @if ($item->product->thumbnail)
                                                        <img src="{{ Storage::disk('product_images')->url($item->product->thumbnail->path) }}" alt="{{ $item->product->name }}" width="96" height="96" loading="lazy" class="h-full w-full object-cover">
                                                    @else
                                                        <div class="grid h-full place-items-center text-lg font-bold text-emerald-800" aria-hidden="true">{{ Str::upper(Str::substr($store->name, 0, 1)) }}</div>
                                                    @endif
                                                </a>

                                                <div class="min-w-0">
                                                    <h4 class="break-words text-sm font-semibold leading-5 text-slate-950 sm:text-base"><a href="{{ route('catalog.show', [$item->product->store->slug, $item->product->slug]) }}" class="transition hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">{{ $item->product->name }}</a></h4>
                                                    <p class="mt-1 text-sm text-slate-500">₱{{ number_format($item->product->price, 2) }} each</p>
                                                    @if ($isUnavailable)
                                                        <p class="mt-2 inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-900">{{ $item->product->status !== 'active' ? 'No longer available' : 'Only '.$item->product->stock_quantity.' available' }}</p>
                                                    @endif

                                                    <div class="mt-4 flex items-center justify-between gap-3 sm:hidden">
                                                        <form method="POST" action="{{ route('cart.update', $item) }}" class="inline-flex min-h-11 items-center overflow-hidden rounded-xl border border-slate-200 bg-white">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button name="quantity" value="{{ $item->quantity - 1 }}" @disabled($item->quantity <= 1) class="grid h-11 w-11 place-items-center text-lg text-slate-600 transition hover:bg-slate-50 disabled:text-slate-300" aria-label="Reduce quantity">−</button>
                                                            <span class="grid h-11 min-w-10 place-items-center border-x border-slate-200 text-sm font-semibold">{{ $item->quantity }}</span>
                                                            <button name="quantity" value="{{ $item->quantity + 1 }}" @disabled($item->quantity >= $item->product->stock_quantity || $item->product->status !== 'active') class="grid h-11 w-11 place-items-center text-lg text-slate-600 transition hover:bg-slate-50 disabled:text-slate-300" aria-label="Increase quantity">+</button>
                                                        </form>
                                                        <p class="text-sm font-bold text-slate-950">₱{{ number_format($item->line_total, 2) }}</p>
                                                    </div>
                                                </div>

                                                <div class="hidden min-w-36 items-end justify-between gap-4 sm:flex sm:flex-col">
                                                    <p class="text-right text-base font-bold text-slate-950">₱{{ number_format($item->line_total, 2) }}</p>
                                                    <form method="POST" action="{{ route('cart.update', $item) }}" class="inline-flex min-h-11 items-center overflow-hidden rounded-xl border border-slate-200 bg-white">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button name="quantity" value="{{ $item->quantity - 1 }}" @disabled($item->quantity <= 1) class="grid h-11 w-11 place-items-center text-lg text-slate-600 transition hover:bg-slate-50 disabled:text-slate-300" aria-label="Reduce quantity">−</button>
                                                        <span class="grid h-11 min-w-10 place-items-center border-x border-slate-200 text-sm font-semibold">{{ $item->quantity }}</span>
                                                        <button name="quantity" value="{{ $item->quantity + 1 }}" @disabled($item->quantity >= $item->product->stock_quantity || $item->product->status !== 'active') class="grid h-11 w-11 place-items-center text-lg text-slate-600 transition hover:bg-slate-50 disabled:text-slate-300" aria-label="Increase quantity">+</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('cart.destroy', $item) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="min-h-10 text-xs font-semibold text-slate-500 transition duration-200 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2">Remove</button>
                                                    </form>
                                                </div>

                                                <form method="POST" action="{{ route('cart.destroy', $item) }}" class="col-span-2 sm:hidden">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="min-h-10 text-xs font-semibold text-slate-500 transition duration-200 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2">Remove</button>
                                                </form>
                                            </article>
                                        @endforeach
                                    </div>

                                    <div class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm sm:px-5">
                                        <span class="font-medium text-slate-600">{{ $store->name }} subtotal</span>
                                        <span class="font-bold text-slate-950">₱{{ number_format($storeSubtotal, 2) }}</span>
                                    </div>
                                </section>
                            @endforeach
                        </div>
                    </section>

                    <aside class="lg:sticky lg:top-6 lg:self-start">
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_18px_35px_-28px_rgba(15,23,42,0.48)] sm:p-6">
                            <h3 class="text-lg font-semibold tracking-[-0.02em] text-slate-950">Order summary</h3>
                            <div class="mt-5 space-y-3 border-b border-slate-100 pb-5 text-sm">
                                <div class="flex justify-between text-slate-600"><span>Subtotal</span><span>₱{{ number_format($subtotal, 2) }}</span></div>
                                <div class="flex justify-between text-slate-600"><span>Shipping</span><span class="font-semibold text-emerald-700">Free</span></div>
                            </div>
                            <div class="mt-5 flex items-end justify-between"><span class="font-semibold text-slate-950">Total</span><span class="text-2xl font-bold tracking-[-0.04em] text-slate-950">₱{{ number_format($subtotal, 2) }}</span></div>

                            @if ($hasUnavailableItems)
                                <p class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-3 py-3 text-sm leading-5 text-amber-950">Update or remove unavailable items before checkout.</p>
                            @else
                                <a href="{{ route('checkout.create') }}" class="mt-6 inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition duration-200 hover:bg-emerald-800 active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">Continue to checkout</a>
                            @endif
                            <p class="mt-4 text-center text-xs leading-5 text-slate-500">Cash on delivery · Free shipping · No hidden fees</p>
                        </div>
                    </aside>
                </div>
            @else
                <div class="mx-auto max-w-md rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center shadow-[0_12px_25px_-24px_rgba(15,23,42,0.35)]">
                    <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-emerald-50 text-2xl text-emerald-800" aria-hidden="true">⌑</div>
                    <h3 class="mt-5 text-xl font-semibold tracking-[-0.02em] text-slate-950">Your cart is waiting.</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Choose something from an independent store and it will appear here.</p>
                    <a href="{{ route('catalog.index') }}" class="mt-6 inline-flex min-h-11 items-center rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white transition duration-200 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-950 focus:ring-offset-2">Explore products</a>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
