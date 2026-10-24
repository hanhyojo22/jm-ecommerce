<x-app-layout>
    <x-slot name="header">
        <div class="sc-page-head">
            <div>
                <a href="{{ route('seller.products.index') }}" class="sc-back"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>Back to products</a>
                <div class="flex flex-wrap items-center gap-3"><h1 class="sc-title">{{ $product->name }}</h1><x-marketplace.product-status :status="$product->status" /></div>            </div>
            <div class="sc-page-actions">
                @if ($product->status === 'active')<a href="{{ route('catalog.show', [$product->store->slug, $product->slug]) }}" target="_blank" rel="noopener noreferrer" class="seller-outline-button" aria-label="View {{ $product->name }} in your store (opens in a new tab)">View in store <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9"/><path d="M18 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5"/></svg></a>@endif
                @if ($canManageProducts && ! in_array($product->status, ['pending', 'inactive'], true))<a href="{{ route('seller.products.edit', $product) }}" class="seller-primary-button">Edit listing</a>@endif
            </div>
        </div>
    </x-slot>
    <div class="page-shell"><div class="page-container seller-workspace-page sc-stack">
        @if ($product->moderation_reason)<div class="sc-notice !block" data-tone="warning"><strong>Administrator feedback</strong><p class="mt-1 whitespace-pre-line">{{ $product->moderation_reason }}</p></div>@endif
        <div class="sc-grid-detail grid gap-5">
            <section class="sc-card sc-card-pad" aria-labelledby="listing-information-heading">
                <h2 id="listing-information-heading" class="sc-card-title">Product information</h2>
                @if ($product->images->isNotEmpty())<div class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-5">@foreach ($product->images as $photo)<img src="{{ Storage::disk('product_images')->url($photo->path) }}" alt="{{ $product->name }} photo {{ $loop->iteration }}" loading="lazy" width="160" height="160" class="aspect-square w-full rounded-lg border border-line object-cover">@endforeach</div>@endif
                <dl class="sc-dl sc-dl-grid mt-5 border-t border-line pt-5">
                    <div><dt>Category</dt><dd class="font-medium">{{ $product->category?->name ?? 'Uncategorized' }}</dd></div>
                    <div><dt>SKU</dt><dd class="font-medium">{{ $product->sku }}</dd></div>
                    <div><dt>Price</dt><dd class="font-medium tabular-nums">₱{{ number_format($product->price, 2) }}</dd></div>
                    <div><dt>Inventory</dt><dd class="font-medium">{{ number_format($product->stock_quantity) }} in stock</dd></div>
                    @if ($product->brand)<div><dt>Brand</dt><dd class="font-medium">{{ $product->brand }}</dd></div>@endif
                    @if ($product->condition)<div><dt>Condition</dt><dd class="font-medium">{{ ucfirst($product->condition) }}</dd></div>@endif
                </dl>
                @if ($product->description)<div class="mt-5 border-t border-line pt-5"><h3 class="text-sm font-semibold text-ink">Description</h3><p class="mt-2 max-w-prose whitespace-pre-line text-sm leading-6 text-muted">{{ $product->description }}</p></div>@endif
            </section>
            <aside class="sc-stack min-w-0">
                <section class="sc-card sc-card-pad" aria-labelledby="approval-heading">
                    <h2 id="approval-heading" class="sc-card-title">Approval</h2>
                    <div class="mt-3"><x-marketplace.product-status :status="$product->status" /></div>
                    <p class="mt-3 sc-meta">Inventory and approval are separate states.</p>
                    @if ($canManageProducts && in_array($product->status, ['draft', 'changes_required', 'rejected'], true))<form method="POST" action="{{ route('seller.products.submit', $product) }}" class="mt-4">@csrf<button type="submit" class="seller-primary-button w-full">Submit for review</button></form>@endif
                </section>
                @if ($product->variants->isNotEmpty())
                    <section class="sc-card" aria-labelledby="variants-heading">
                        <div class="sc-card-head"><h2 id="variants-heading" class="sc-card-title">Variants</h2><span class="sc-meta">{{ $product->variants->count() }}</span></div>
                        <ul class="sc-list">@foreach ($product->variants as $variant)<li class="sc-list-item sc-row items-start text-sm"><span class="sc-grow"><strong class="block font-semibold text-ink">{{ $variant->label() }}</strong><span class="sc-meta">{{ $variant->sku }}</span></span><span class="shrink-0 text-right"><span class="tabular-nums">{{ number_format($variant->stock_quantity) }}</span> <span class="sc-meta">in stock</span>@if ($variant->price)<span class="block sc-meta tabular-nums">₱{{ number_format($variant->price, 2) }}</span>@endif</span></li>@endforeach</ul>
                    </section>
                @endif
            </aside>
        </div>
    </div></div>
</x-app-layout>
