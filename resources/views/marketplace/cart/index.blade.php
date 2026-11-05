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

    <style>
        /*
         * The cart's checks are quiet fills, not outlined boxes: a soft slate square when empty,
         * the brand fill and a drawn tick when chosen. The native input stays for keyboard and form use.
         */
        .cart-check {
            -webkit-appearance: none;
            appearance: none;
            display: inline-grid;
            place-items: center;
            flex: none;
            width: 20px;
            height: 20px;
            margin: 0;
            border: 0;
            border-radius: 6px;
            background-color: var(--color-line-soft);
            cursor: pointer;
            transition: background-color .15s ease;
        }

        .cart-check:checked {
            background-color: var(--color-accent);
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23fff' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m5 12.5 4.5 4.5L19 7'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: center;
            background-size: 12px 12px;
        }

        .cart-check:focus-visible {
            outline: 2px solid var(--color-accent);
            outline-offset: 2px;
        }

        .cart-check:disabled {
            cursor: not-allowed;
            opacity: .4;
        }
    </style>

    <div class="page-shell min-h-[calc(100dvh-4.5rem)]">
        <div class="page-container">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="customer-page-title">Shopping cart</h1>
                    <p class="mt-1 text-sm text-muted">{{ $items->sum('quantity') }} {{ Str::plural('item', $items->sum('quantity')) }} · Grouped by seller</p>
                </div>
                <a href="{{ route('catalog.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand transition hover:text-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Continue shopping</a>
            </div>

            @if (session('status'))
                <div class="mb-6 flex flex-wrap items-center gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-ink" role="status">
                    <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-success text-white" aria-hidden="true">✓</span>
                    <span class="flex-1">{{ session('status') }}</span>
                    @if (session('removed_cart_item'))
                        <form method="POST" action="{{ route('cart.restore') }}">
                            @csrf
                            <button class="inline-flex min-h-11 items-center rounded-md border border-brand px-3 text-sm font-semibold text-brand transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Undo</button>
                        </form>
                    @endif
                </div>
            @endif

            @if ($items->isNotEmpty())
                <div x-data="cartSelection({{ Js::from(['items' => $selectionState, 'selected' => $selectedIds, 'endpoint' => route('cart.selection'), 'csrf' => csrf_token()]) }})" class="pb-28 lg:pb-0">
                    <div class="mt-5 grid gap-7 lg:grid-cols-[minmax(0,1fr)_21rem] lg:items-start">
                        <section aria-labelledby="cart-items" class="min-w-0">
                            <h2 id="cart-items" class="sr-only">Cart items grouped by seller</h2>
                            <div x-cloak x-show="error" class="mb-3 rounded-md bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">
                                <p x-text="error"></p>
                                <button type="button" @click="send()" :disabled="saving" class="mt-2 min-h-12 rounded-md border border-red-300 px-4 font-semibold disabled:opacity-50">Retry saving selection</button>
                            </div>

                            <div class="overflow-hidden rounded-[var(--radius-surface)] border border-line bg-surface">
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-3 border-b border-line p-3 sm:px-4 sm:py-3.5">
                                    <label class="inline-flex min-h-11 cursor-pointer items-center gap-3 text-sm font-semibold text-ink">
                                        <input type="checkbox" data-select-all @checked($allSelected) x-bind:checked="allSelected" x-on:change="toggleAll()" @disabled($availableItems->isEmpty()) class="cart-check">
                                        Select all
                                    </label>
                                    <p class="text-sm text-muted" aria-live="polite"><span x-text="selectedCount">{{ $selectedIds->count() }}</span> of {{ $availableItems->count() }} selected</p>
                                    <form method="POST" action="{{ route('cart.destroy-selected') }}" class="ml-auto" x-on:submit="if (selectedCount === 0) $event.preventDefault()">
                                        @csrf
                                        @method('DELETE')
                                        <button x-bind:disabled="selectedCount === 0" class="inline-flex min-h-11 items-center rounded-md px-3 text-sm font-semibold text-muted transition hover:bg-red-50 hover:text-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700 disabled:cursor-not-allowed disabled:opacity-40">Remove selected</button>
                                    </form>
                                </div>

                                <div class="hidden border-b border-line bg-canvas px-4 py-2 sm:grid sm:grid-cols-[20px_48px_minmax(0,1fr)_84px_84px_28px] sm:gap-x-3.5" aria-hidden="true">
                                    <span></span>
                                    <span></span>
                                    <span class="text-[10px] font-semibold uppercase tracking-[0.11em] text-muted">Product</span>
                                    <span class="text-right text-[10px] font-semibold uppercase tracking-[0.11em] text-muted">Qty</span>
                                    <span class="text-right text-[10px] font-semibold uppercase tracking-[0.11em] text-muted">Total</span>
                                    <span></span>
                                </div>

                                @foreach ($itemsByStore as $storeId => $storeItems)
                                    @php
                                        $store = $storeItems->first()->product->store;
                                        $storeSelectable = $storeItems->filter(fn ($item) => $item->isAvailable());
                                        $storeAllSelected = $storeSelectable->isNotEmpty()
                                            && $storeSelectable->where('selected', true)->count() === $storeSelectable->count();
                                    @endphp

                                    <div class="flex items-center justify-between gap-3 border-b border-line bg-canvas/70 px-4 py-2.5">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <input type="checkbox" data-store-checkbox="{{ $storeId }}" @checked($storeAllSelected) x-on:change="toggleStore('{{ $storeId }}')" @disabled($storeSelectable->isEmpty()) aria-label="Select all items from {{ $store->name }}" class="cart-check">
                                            <a id="store-{{ $store->id }}" href="{{ route('catalog.store', $store->slug) }}" class="min-w-0 truncate text-[12.5px] font-semibold text-brand transition hover:text-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">{{ $store->name }}</a>
                                        </div>
                                        <p class="shrink-0 text-[11.5px] text-muted">{{ $storeItems->sum('quantity') }} {{ Str::plural('item', $storeItems->sum('quantity')) }}</p>
                                    </div>

                                    @foreach ($storeItems as $item)
                                        @php($availableStock = $item->variant?->stock_quantity ?? $item->product->stock_quantity)
                                        @php($isUnavailable = ! $item->isAvailable())
                                        <article class="grid grid-cols-[20px_48px_minmax(0,1fr)] gap-x-3.5 gap-y-2 border-b border-line-soft px-4 py-3.5 last:border-b-0 sm:grid-cols-[20px_48px_minmax(0,1fr)_84px_84px_28px] sm:items-start">
                                            <div class="flex items-start">
                                                <input type="checkbox" id="select-{{ $item->id }}" @checked($item->selected) x-bind:checked="isSelected('{{ $item->id }}')" x-on:change="toggleItem('{{ $item->id }}')" @disabled($isUnavailable) aria-label="Select {{ $item->product->name }}" class="cart-check mt-3.5 {{ $isUnavailable ? 'opacity-40' : '' }}">
                                            </div>

                                            <a href="{{ route('catalog.show', [$item->product->store->slug, $item->product->slug]) }}" aria-label="View {{ $item->product->name }}" class="size-12 overflow-hidden rounded-md border border-line-soft bg-slate-100 focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2 {{ $isUnavailable ? 'opacity-60' : '' }}">
                                                @if ($item->imagePath())
                                                    <img src="{{ Storage::disk('product_images')->url($item->imagePath()) }}" alt="{{ $item->product->name }}" width="48" height="48" loading="lazy" class="h-full w-full object-cover">
                                                @else
                                                    <span class="grid h-full place-items-center text-base font-semibold text-brand" aria-hidden="true">{{ Str::upper(Str::substr($store->name, 0, 1)) }}</span>
                                                @endif
                                            </a>

                                            <div class="min-w-0">
                                                <h3 class="truncate text-[13px] font-semibold leading-[17px] text-ink"><a href="{{ route('catalog.show', [$item->product->store->slug, $item->product->slug]) }}" class="transition hover:text-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">{{ $item->product->name }}</a></h3>
                                                @if ($item->variant)<p class="mt-0.5 truncate text-[11.5px] font-medium leading-[15px] text-brand">{{ $item->variant->label() }}</p>@endif
                                                @if ($isUnavailable)
                                                    <p class="mt-2 inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-900">{{ $item->product_variant_id === null && $item->product->variants->isNotEmpty() ? 'Choose a color and size again' : ($item->product->status !== 'active' || ($item->product_variant_id !== null && $item->variant === null) ? 'No longer available' : ($availableStock < 1 ? 'Sold out' : 'Only '.$availableStock.' available')) }}</p>
                                                @endif

                                                <div class="mt-2 flex items-center justify-end gap-2 sm:hidden">
                                                    <form method="POST" action="{{ route('cart.update', $item) }}" class="inline-flex items-center overflow-hidden rounded-md border border-line bg-surface">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button name="quantity" value="{{ $item->quantity - 1 }}" @disabled($item->quantity <= 1) class="grid size-7 place-items-center text-sm text-muted transition hover:bg-canvas disabled:text-slate-300" aria-label="Reduce quantity">−</button>
                                                        <span class="grid h-7 min-w-7 place-items-center border-x border-line text-xs font-semibold">{{ $item->quantity }}</span>
                                                        <button name="quantity" value="{{ $item->quantity + 1 }}" @disabled($item->quantity >= $availableStock || $isUnavailable) class="grid size-7 place-items-center text-sm text-muted transition hover:bg-canvas disabled:text-slate-300" aria-label="Increase quantity">+</button>
                                                    </form>
                                                    <p class="text-[13px] font-semibold tabular-nums text-ink">₱{{ number_format($item->line_total, 2) }}</p>
                                                    <form method="POST" action="{{ route('cart.destroy', $item) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" title="Remove" aria-label="Remove {{ $item->product->name }} from your cart" class="grid size-7 place-items-center rounded-md text-muted transition hover:bg-red-50 hover:text-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9.5 7V5.6a1.6 1.6 0 0 1 1.6-1.6h1.8a1.6 1.6 0 0 1 1.6 1.6V7M6.6 7l.8 12.1a2 2 0 0 0 2 1.9h5.2a2 2 0 0 0 2-1.9L17.4 7"/><path stroke-linecap="round" d="M10.4 11v6M13.6 11v6"/></svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>

                                            <div class="hidden sm:flex sm:justify-end">
                                                <form method="POST" action="{{ route('cart.update', $item) }}" class="inline-flex items-center overflow-hidden rounded-md border border-line bg-surface">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button name="quantity" value="{{ $item->quantity - 1 }}" @disabled($item->quantity <= 1) class="grid size-7 place-items-center text-sm text-muted transition hover:bg-canvas disabled:text-slate-300" aria-label="Reduce quantity">−</button>
                                                    <span class="grid h-7 min-w-7 place-items-center border-x border-line text-xs font-semibold">{{ $item->quantity }}</span>
                                                    <button name="quantity" value="{{ $item->quantity + 1 }}" @disabled($item->quantity >= $availableStock || $isUnavailable) class="grid size-7 place-items-center text-sm text-muted transition hover:bg-canvas disabled:text-slate-300" aria-label="Increase quantity">+</button>
                                                </form>
                                            </div>

                                            <p class="hidden pt-1 text-right text-[13px] font-semibold tabular-nums text-ink sm:block">₱{{ number_format($item->line_total, 2) }}</p>

                                            <form method="POST" action="{{ route('cart.destroy', $item) }}" class="hidden sm:block">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" title="Remove" aria-label="Remove {{ $item->product->name }} from your cart" class="grid size-7 place-items-center rounded-md text-muted transition hover:bg-red-50 hover:text-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9.5 7V5.6a1.6 1.6 0 0 1 1.6-1.6h1.8a1.6 1.6 0 0 1 1.6 1.6V7M6.6 7l.8 12.1a2 2 0 0 0 2 1.9h5.2a2 2 0 0 0 2-1.9L17.4 7"/><path stroke-linecap="round" d="M10.4 11v6M13.6 11v6"/></svg>
                                                </button>
                                            </form>
                                        </article>
                                    @endforeach
                                @endforeach
                            </div>
                        </section>

                        <aside class="hidden lg:sticky lg:top-24 lg:block lg:self-start">
                            <div class="rounded-[var(--radius-surface)] border border-line bg-surface p-4">
                                <h2 class="text-[15px] font-semibold tracking-[-0.02em] text-ink">Order summary</h2>
                                <div class="mt-4 border-b border-line pb-3.5 text-sm">
                                    @php($selectedQuantity = $items->where('selected', true)->sum('quantity'))
                                    <div class="flex justify-between text-muted"><span>Subtotal (<span x-text="selectedQuantityLabel">{{ $selectedQuantity }} {{ Str::plural('item', $selectedQuantity) }}</span>)</span><span class="tabular-nums">₱<span x-text="subtotalLabel">{{ number_format($subtotal, 2) }}</span></span></div>
                                    <div class="mt-2.5 flex justify-between text-muted"><span>Shipping</span><span class="font-semibold text-success">Free</span></div>
                                </div>
                                <div class="mt-3.5 flex items-end justify-between"><span class="font-semibold text-ink">Total</span><span class="text-2xl font-semibold tabular-nums tracking-[-0.04em] text-ink">₱<span x-text="subtotalLabel">{{ number_format($subtotal, 2) }}</span></span></div>

                                <a href="{{ route('checkout.create') }}" x-bind:aria-disabled="checkoutReady ? 'false' : 'true'" x-bind:tabindex="checkoutReady ? '0' : '-1'" x-on:click="openCheckout($event)" x-bind:class="checkoutReady ? '' : 'pointer-events-none opacity-50'" class="mt-4 inline-flex min-h-12 w-full items-center justify-center rounded-md bg-brand px-4 text-sm font-semibold text-white transition duration-200 hover:bg-brand-hover active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Check out (<span x-text="selectedCount">{{ $selectedIds->count() }}</span>)</a>
                                <p x-cloak x-show="selectedCount === 0" class="mt-3 text-center text-xs font-medium text-muted" role="status">Select an item to check out.</p>

                                @if ($unavailableCount > 0)
                                    <p class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-3 text-sm leading-5 text-amber-950">{{ $unavailableCount }} {{ Str::plural('item', $unavailableCount) }} can’t be ordered right now and {{ $unavailableCount === 1 ? 'has' : 'have' }} been left out of your selection.</p>
                                @endif
                                <p class="mt-3 text-center text-xs leading-5 text-muted">Cash on delivery · Free shipping · No hidden fees</p>
                            </div>
                        </aside>
                    </div>

                    {{-- Phone: the summary follows the buyer instead of hiding below a long list. --}}
                    <div class="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-surface/95 px-4 py-3 shadow-[0_-4px_16px_-8px_rgb(15_23_42/0.25)] backdrop-blur lg:hidden" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px));">
                        <div class="mx-auto flex max-w-3xl items-center gap-3">
                            <label class="inline-flex min-h-11 shrink-0 cursor-pointer items-center gap-2 text-sm font-semibold text-ink">
                                <input type="checkbox" data-select-all @checked($allSelected) x-bind:checked="allSelected" x-on:change="toggleAll()" @disabled($availableItems->isEmpty()) class="cart-check" aria-label="Select all items">
                                All
                            </label>
                            <div class="min-w-0 flex-1 text-right">
                                <p class="text-xs font-medium text-muted">Total (<span x-text="selectedCount">{{ $selectedIds->count() }}</span>)</p>
                                <p class="truncate text-lg font-semibold tabular-nums tracking-[-0.03em] text-ink">₱<span x-text="subtotalLabel">{{ number_format($subtotal, 2) }}</span></p>
                            </div>
                            <a href="{{ route('checkout.create') }}" x-bind:aria-disabled="checkoutReady ? 'false' : 'true'" x-bind:tabindex="checkoutReady ? '0' : '-1'" x-on:click="openCheckout($event)" x-bind:class="checkoutReady ? '' : 'pointer-events-none opacity-50'" class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-md bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Check out</a>
                        </div>
                    </div>
                </div>
            @else
                <div class="mx-auto max-w-md rounded-lg border border-dashed border-line bg-surface px-6 py-16 text-center">
                    <div class="mx-auto grid h-14 w-14 place-items-center rounded-md bg-blue-50 text-brand" aria-hidden="true"><svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h2l2.4 12.1a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 2-1.6L21 7H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg></div>
                    <h3 class="mt-5 text-xl font-semibold tracking-[-0.02em] text-ink">Your cart is waiting.</h3>
                    <p class="mt-2 text-sm leading-6 text-muted">Choose something from an independent store and it will appear here.</p>
                    <a href="{{ route('catalog.index') }}" class="mt-6 inline-flex min-h-11 items-center rounded-md bg-brand px-4 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Explore products</a>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
