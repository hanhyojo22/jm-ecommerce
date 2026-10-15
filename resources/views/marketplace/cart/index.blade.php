<x-app-layout>
    @php
        $items = $cart?->items ?? collect();
        $itemsByStore = $items->groupBy(fn ($item) => $item->product->store_id);
        $availableItems = $items->filter(fn ($item) => $item->isAvailable());
        $unavailableCount = $items->count() - $availableItems->count();
        $selectionState = $items->map(fn ($item) => [
            'id' => $item->id,
            'storeId' => $item->product->store_id,
            'quantity' => $item->quantity,
            'available' => $item->isAvailable(),
            'centavos' => (int) bcmul((string) $item->line_total, '100', 0),
        ])->values();
        $selectedIds = $items->where('selected', true)->pluck('id')->values();
        $allSelected = $availableItems->isNotEmpty() && $selectedIds->count() === $availableItems->count();
    @endphp

    <div class="page-shell min-h-[calc(100dvh-4.5rem)]">
        <div class="page-container">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="customer-page-title text-3xl font-bold tracking-[-0.03em] text-ink">Shopping cart</h1>
                    <p class="mt-1 text-sm text-muted">{{ $items->sum('quantity') }} {{ Str::plural('item', $items->sum('quantity')) }} · Grouped by seller</p>
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

            @if ($items->isNotEmpty())
                <div x-data="cartSelection({{ Js::from(['items' => $selectionState, 'selected' => $selectedIds, 'endpoint' => route('cart.selection'), 'csrf' => csrf_token()]) }})" class="pb-28 lg:pb-0">
                    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_21rem]">
                        <section aria-labelledby="cart-items">
                            <h2 id="cart-items" class="sr-only">Cart items grouped by seller</h2>

                            <div class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-3 rounded-[14px] border border-line bg-surface px-4 py-3 sm:px-5">
                                <label class="inline-flex min-h-11 cursor-pointer items-center gap-3 text-sm font-semibold text-ink">
                                    <input type="checkbox" data-select-all @checked($allSelected) x-bind:checked="allSelected" x-on:change="toggleAll()" @disabled($availableItems->isEmpty()) class="size-5 shrink-0 rounded border-line text-brand focus:ring-brand disabled:opacity-40">
                                    Select all
                                </label>
                                <p class="text-sm text-muted" aria-live="polite"><span x-text="selectedCount">{{ $selectedIds->count() }}</span> of {{ $availableItems->count() }} selected</p>
                                <form method="POST" action="{{ route('cart.destroy-selected') }}" class="ml-auto" x-on:submit="if (selectedCount === 0) $event.preventDefault()">
                                    @csrf
                                    @method('DELETE')
                                    <button x-bind:disabled="selectedCount === 0" class="inline-flex min-h-11 items-center rounded-[10px] px-3 text-sm font-semibold text-muted transition hover:bg-red-50 hover:text-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700 disabled:cursor-not-allowed disabled:opacity-40">Remove selected</button>
                                </form>
                            </div>
                            <p x-cloak x-show="error" x-text="error" class="mb-4 rounded-[10px] bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert"></p>

                            <div class="space-y-6">
                                @foreach ($itemsByStore as $storeId => $storeItems)
                                    @php
                                        $store = $storeItems->first()->product->store;
                                        $storeSelectable = $storeItems->filter(fn ($item) => $item->isAvailable());
                                        $storeAllSelected = $storeSelectable->isNotEmpty()
                                            && $storeSelectable->where('selected', true)->count() === $storeSelectable->count();
                                    @endphp

                                    <section class="overflow-hidden rounded-[14px] border border-line bg-surface" aria-labelledby="store-{{ $store->id }}">
                                        <div class="flex items-center justify-between gap-4 border-b border-line bg-canvas/70 px-4 py-3 sm:px-5">
                                            <div class="flex min-w-0 items-center gap-3">
                                                <input type="checkbox" data-store-checkbox="{{ $storeId }}" @checked($storeAllSelected) x-on:change="toggleStore('{{ $storeId }}')" @disabled($storeSelectable->isEmpty()) aria-label="Select all items from {{ $store->name }}" class="size-5 shrink-0 rounded border-line text-brand focus:ring-brand disabled:opacity-40">
                                                <div class="min-w-0">
                                                    <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-muted">Sold by</p>
                                                    <a id="store-{{ $store->id }}" href="{{ route('catalog.store', $store->slug) }}" class="mt-0.5 block truncate text-sm font-semibold text-brand transition hover:text-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">{{ $store->name }}</a>
                                                </div>
                                            </div>
                                            <p class="shrink-0 text-xs font-semibold text-muted">{{ $storeItems->sum('quantity') }} {{ Str::plural('item', $storeItems->sum('quantity')) }}</p>
                                        </div>

                                        <div class="divide-y divide-line">
                                            @foreach ($storeItems as $item)
                                                @php($availableStock = $item->variant?->stock_quantity ?? $item->product->stock_quantity)
                                                @php($isUnavailable = ! $item->isAvailable())
                                                <article class="grid grid-cols-[auto_5rem_minmax(0,1fr)] gap-x-3 gap-y-4 p-4 sm:grid-cols-[auto_7rem_minmax(0,1fr)_auto] sm:gap-x-4 sm:p-5 {{ $isUnavailable ? 'bg-canvas/40' : '' }}">
                                                    <div class="flex items-center">
                                                        <input type="checkbox" id="select-{{ $item->id }}" @checked($item->selected) x-bind:checked="isSelected('{{ $item->id }}')" x-on:change="toggleItem('{{ $item->id }}')" @disabled($isUnavailable) aria-label="Select {{ $item->product->name }}" class="size-5 shrink-0 rounded border-line text-brand focus:ring-brand disabled:opacity-40">
                                                    </div>

                                                    <a href="{{ route('catalog.show', [$item->product->store->slug, $item->product->slug]) }}" aria-label="View {{ $item->product->name }}" class="aspect-square overflow-hidden rounded-xl bg-slate-100 focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2 {{ $isUnavailable ? 'opacity-60' : '' }}">
                                                        @if ($item->product->thumbnail)
                                                            <img src="{{ Storage::disk('product_images')->url($item->product->thumbnail->path) }}" alt="{{ $item->product->name }}" width="96" height="96" loading="lazy" class="h-full w-full object-cover">
                                                        @else
                                                            <div class="grid h-full place-items-center text-lg font-bold text-brand" aria-hidden="true">{{ Str::upper(Str::substr($store->name, 0, 1)) }}</div>
                                                        @endif
                                                    </a>

                                                    <div class="min-w-0">
                                                        <h4 class="break-words text-sm font-semibold leading-5 text-ink sm:text-base"><a href="{{ route('catalog.show', [$item->product->store->slug, $item->product->slug]) }}" class="transition hover:text-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">{{ $item->product->name }}</a></h4>
                                                        @if ($item->variant)<p class="mt-1 text-xs font-medium text-brand">{{ $item->variant->label() }}</p>@endif
                                                        <p class="mt-1 text-sm text-muted">₱{{ number_format($item->product->price, 2) }} each</p>
                                                        @if ($isUnavailable)
                                                            <p class="mt-2 inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-900">{{ $item->product_variant_id === null && $item->product->variants->isNotEmpty() ? 'Choose a color and size again' : ($item->product->status !== 'active' || ($item->product_variant_id !== null && $item->variant === null) ? 'No longer available' : ($availableStock < 1 ? 'Sold out' : 'Only '.$availableStock.' available')) }}</p>
                                                        @else
                                                            <p class="mt-2 text-xs font-semibold text-success">In stock</p>
                                                        @endif

                                                        <div class="mt-4 flex items-center gap-2 sm:hidden">
                                                            <form method="POST" action="{{ route('cart.update', $item) }}" class="inline-flex min-h-11 items-center overflow-hidden rounded-[10px] border border-line bg-surface">
                                                                @csrf
                                                                @method('PATCH')
                                                                <button name="quantity" value="{{ $item->quantity - 1 }}" @disabled($item->quantity <= 1) class="grid h-11 w-11 place-items-center text-lg text-muted transition hover:bg-canvas disabled:text-slate-300" aria-label="Reduce quantity">−</button>
                                                                <span class="grid h-11 min-w-10 place-items-center border-x border-line text-sm font-semibold">{{ $item->quantity }}</span>
                                                                <button name="quantity" value="{{ $item->quantity + 1 }}" @disabled($item->quantity >= $availableStock || $isUnavailable) class="grid h-11 w-11 place-items-center text-lg text-muted transition hover:bg-canvas disabled:text-slate-300" aria-label="Increase quantity">+</button>
                                                            </form>
                                                            <p class="ml-auto shrink-0 whitespace-nowrap text-sm font-bold text-ink">₱{{ number_format($item->line_total, 2) }}</p>
                                                            <form method="POST" action="{{ route('cart.destroy', $item) }}">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" title="Remove" aria-label="Remove {{ $item->product->name }} from your cart" class="grid size-11 shrink-0 place-items-center rounded-[10px] text-muted transition hover:bg-red-50 hover:text-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">
                                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9.5 7V5.6a1.6 1.6 0 0 1 1.6-1.6h1.8a1.6 1.6 0 0 1 1.6 1.6V7M6.6 7l.8 12.1a2 2 0 0 0 2 1.9h5.2a2 2 0 0 0 2-1.9L17.4 7"/><path stroke-linecap="round" d="M10.4 11v6M13.6 11v6"/></svg>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>

                                                    <div class="hidden min-w-36 items-end justify-between gap-4 sm:flex sm:flex-col">
                                                        <p class="text-right text-base font-bold text-ink">₱{{ number_format($item->line_total, 2) }}</p>
                                                        <div class="flex items-center gap-1.5">
                                                        <form method="POST" action="{{ route('cart.update', $item) }}" class="inline-flex min-h-11 items-center overflow-hidden rounded-[10px] border border-line bg-surface">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button name="quantity" value="{{ $item->quantity - 1 }}" @disabled($item->quantity <= 1) class="grid h-11 w-11 place-items-center text-lg text-muted transition hover:bg-canvas disabled:text-slate-300" aria-label="Reduce quantity">−</button>
                                                            <span class="grid h-11 min-w-10 place-items-center border-x border-line text-sm font-semibold">{{ $item->quantity }}</span>
                                                            <button name="quantity" value="{{ $item->quantity + 1 }}" @disabled($item->quantity >= $availableStock || $isUnavailable) class="grid h-11 w-11 place-items-center text-lg text-muted transition hover:bg-canvas disabled:text-slate-300" aria-label="Increase quantity">+</button>
                                                        </form>
                                                        <form method="POST" action="{{ route('cart.destroy', $item) }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" title="Remove" aria-label="Remove {{ $item->product->name }} from your cart" class="grid size-11 place-items-center rounded-[10px] text-muted transition hover:bg-red-50 hover:text-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9.5 7V5.6a1.6 1.6 0 0 1 1.6-1.6h1.8a1.6 1.6 0 0 1 1.6 1.6V7M6.6 7l.8 12.1a2 2 0 0 0 2 1.9h5.2a2 2 0 0 0 2-1.9L17.4 7"/><path stroke-linecap="round" d="M10.4 11v6M13.6 11v6"/></svg>
                                                            </button>
                                                        </form>
                                                        </div>
                                                    </div>
                                                </article>
                                            @endforeach
                                        </div>
                                    </section>
                                @endforeach
                            </div>
                        </section>

                        <aside class="hidden lg:sticky lg:top-24 lg:block lg:self-start">
                            <div class="rounded-[14px] border border-line bg-surface p-5 sm:p-6">
                                <h3 class="text-lg font-semibold tracking-[-0.02em] text-ink">Order summary</h3>
                                <div class="mt-5 space-y-3 border-b border-line pb-5 text-sm">
                                    @php($selectedQuantity = $items->where('selected', true)->sum('quantity'))
                                    <div class="flex justify-between text-muted"><span>Subtotal (<span x-text="selectedQuantityLabel">{{ $selectedQuantity }} {{ Str::plural('item', $selectedQuantity) }}</span>)</span><span class="tabular-nums">₱<span x-text="subtotalLabel">{{ number_format($subtotal, 2) }}</span></span></div>
                                    <div class="flex justify-between text-muted"><span>Shipping</span><span class="font-semibold text-success">Free</span></div>
                                </div>
                                <div class="mt-5 flex items-end justify-between"><span class="font-semibold text-ink">Total</span><span class="text-2xl font-bold tabular-nums tracking-[-0.04em] text-ink">₱<span x-text="subtotalLabel">{{ number_format($subtotal, 2) }}</span></span></div>

                                <a href="{{ route('checkout.create') }}" x-bind:aria-disabled="selectedCount === 0 ? 'true' : 'false'" x-bind:tabindex="selectedCount === 0 ? '-1' : '0'" x-on:click="if (selectedCount === 0) $event.preventDefault()" x-bind:class="selectedCount === 0 ? 'pointer-events-none opacity-50' : ''" class="mt-6 inline-flex min-h-12 w-full items-center justify-center rounded-[10px] bg-brand px-4 text-sm font-semibold text-white transition duration-200 hover:bg-brand-hover active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Check out (<span x-text="selectedCount">{{ $selectedIds->count() }}</span>)</a>
                                <p x-cloak x-show="selectedCount === 0" class="mt-3 text-center text-xs font-medium text-muted" role="status">Select an item to check out.</p>

                                @if ($unavailableCount > 0)
                                    <p class="mt-5 rounded-[10px] border border-amber-200 bg-amber-50 px-3 py-3 text-sm leading-5 text-amber-950">{{ $unavailableCount }} {{ Str::plural('item', $unavailableCount) }} can’t be ordered right now and {{ $unavailableCount === 1 ? 'has' : 'have' }} been left out of your selection.</p>
                                @endif
                                <p class="mt-4 text-center text-xs leading-5 text-muted">Cash on delivery · Free shipping · No hidden fees</p>
                            </div>
                        </aside>
                    </div>

                    {{-- Phone: the summary follows the buyer instead of hiding below a long list. --}}
                    <div class="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-surface/95 px-4 py-3 shadow-[0_-4px_16px_-8px_rgb(15_23_42/0.25)] backdrop-blur lg:hidden" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px));">
                        <div class="mx-auto flex max-w-3xl items-center gap-3">
                            <label class="inline-flex min-h-11 shrink-0 cursor-pointer items-center gap-2 text-sm font-semibold text-ink">
                                <input type="checkbox" data-select-all @checked($allSelected) x-bind:checked="allSelected" x-on:change="toggleAll()" @disabled($availableItems->isEmpty()) class="size-5 shrink-0 rounded border-line text-brand focus:ring-brand disabled:opacity-40" aria-label="Select all items">
                                All
                            </label>
                            <div class="min-w-0 flex-1 text-right">
                                <p class="text-[11px] font-medium text-muted">Total (<span x-text="selectedCount">{{ $selectedIds->count() }}</span>)</p>
                                <p class="truncate text-lg font-bold tabular-nums tracking-[-0.03em] text-ink">₱<span x-text="subtotalLabel">{{ number_format($subtotal, 2) }}</span></p>
                            </div>
                            <a href="{{ route('checkout.create') }}" x-bind:aria-disabled="selectedCount === 0 ? 'true' : 'false'" x-on:click="if (selectedCount === 0) $event.preventDefault()" x-bind:class="selectedCount === 0 ? 'pointer-events-none opacity-50' : ''" class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Check out</a>
                        </div>
                    </div>
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
