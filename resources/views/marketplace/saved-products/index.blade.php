<x-app-layout>
    <x-marketplace.account-shell active="saved">
        <header class="min-w-0">
            <h1 class="text-3xl font-semibold tracking-tight text-ink">Saved items</h1>
            <p class="mt-1 text-meta text-muted">@if ($products->isNotEmpty()){{ $products->count() }} saved<span aria-hidden="true" class="px-1.5">·</span>@endif Medicines and products you want to buy later. Prices are today's, nothing is reserved.</p>
        </header>

        @if (session('status'))
            <p role="status" class="mt-4 rounded-md border border-[var(--color-success-line)] bg-[var(--color-success-wash)] px-3.5 py-2.5 text-meta font-medium text-success">{{ session('status') }}</p>
        @endif
        @error('cart')
            <p role="alert" class="mt-4 rounded-md border border-[var(--color-danger-line)] bg-[var(--color-danger-wash)] px-3.5 py-2.5 text-meta font-medium text-danger">{{ $message }}</p>
        @enderror

        <section class="mt-6" aria-label="Saved items">
            @if ($products->isEmpty())
                <div class="account-card px-6 py-10 text-center">
                    <span class="mx-auto grid size-12 place-items-center rounded-full bg-[var(--color-sunken)] text-muted ring-1 ring-inset ring-[var(--color-line-soft)]" aria-hidden="true"><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10Z" /></svg></span>
                    <p class="mt-4 text-lg font-semibold text-ink">Nothing saved yet</p>
                    <p class="mx-auto mt-2 max-w-sm text-meta text-muted">Tap the heart on a product to keep it here for later.</p>
                    <a href="{{ route('catalog.index') }}" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-md bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover">Browse the shop</a>
                </div>
            @else
                <ul class="grid gap-4 sm:grid-cols-2">
                    @foreach ($products as $product)
                        @php($productUrl = route('catalog.show', [$product->store->slug, $product->slug]))
                        <li class="account-card flex min-w-0 flex-col">
                            <a href="{{ $productUrl }}" class="flex flex-1 items-center gap-3 px-4 py-4">
                                <span class="size-16 shrink-0 overflow-hidden rounded-md bg-slate-100">
                                    @if ($product->thumbnail)
                                        <img src="{{ Storage::disk('product_images')->url($product->thumbnail->path) }}" alt="" width="64" height="64" loading="lazy" class="h-full w-full object-cover" />
                                    @endif
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold text-ink">{{ $product->name }}</span>
                                    <span class="mt-1 block text-meta font-semibold tabular-nums text-ink">₱{{ number_format((float) $product->price, 2) }}</span>
                                    <span class="mt-0.5 block text-xs {{ $product->stock_quantity > 0 ? 'text-success' : 'text-danger' }}">{{ $product->stock_quantity > 0 ? 'In stock' : 'Out of stock' }}@if ($product->requires_prescription) · Prescription needed @endif</span>
                                </span>
                            </a>
                            <div class="flex items-center justify-end gap-1 border-t border-line-soft bg-[#fbfcfe] px-2.5 py-1">
                                <form method="POST" action="{{ route('saved-products.destroy', $product) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="inline-flex min-h-11 items-center rounded-md px-2.5 text-xs font-semibold text-danger transition-colors hover:bg-[var(--color-danger-wash)] lg:min-h-10" aria-label="Remove {{ $product->name }} from saved items">Remove</button>
                                </form>
                                @if ($product->variants_exists)
                                    {{-- A product with options has to be chosen on its page before it can go in the cart. --}}
                                    <a href="{{ $productUrl }}" class="inline-flex min-h-11 items-center rounded-md px-2.5 text-xs font-semibold text-brand transition-colors hover:bg-[var(--color-info-wash)] hover:text-brand-hover lg:min-h-10">Choose option</a>
                                @elseif ($product->stock_quantity > 0)
                                    <form method="POST" action="{{ route('cart.store', $product) }}">
                                        @csrf
                                        <input type="hidden" name="quantity" value="1" />
                                        <button type="submit" class="inline-flex min-h-11 items-center rounded-md px-2.5 text-xs font-semibold text-brand transition-colors hover:bg-[var(--color-info-wash)] hover:text-brand-hover lg:min-h-10" aria-label="Add {{ $product->name }} to cart">Add to cart</button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </x-marketplace.account-shell>
</x-app-layout>
