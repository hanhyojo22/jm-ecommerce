<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div><a href="{{ route('seller.products.index') }}" class="inline-flex min-h-10 items-center text-sm font-semibold text-brand">← Back to products</a><h1 class="mt-1 text-[28px] font-bold leading-tight text-ink">{{ $product->name }}</h1><p class="mt-1 text-sm text-muted">Listing details and approval status</p></div>
            @if ($canManageProducts && ! in_array($product->status, ['pending', 'inactive'], true))<a href="{{ route('seller.products.edit', $product) }}" class="seller-primary-button inline-flex min-h-11 items-center px-4 text-sm">Edit listing</a>@endif
        </div>
    </x-slot>
    <div class="page-shell"><div class="page-container max-w-6xl space-y-5">
        @if ($product->moderation_reason)<div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950"><strong>Administrator feedback</strong><p class="mt-1 whitespace-pre-line">{{ $product->moderation_reason }}</p></div>@endif
        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
            <section class="rounded-xl border border-line bg-white p-5" aria-labelledby="listing-information-heading">
                <div class="flex flex-wrap items-center justify-between gap-3"><h2 id="listing-information-heading" class="text-lg font-semibold">Product information</h2><x-marketplace.product-status :status="$product->status" /></div>
                @if ($product->images->isNotEmpty())<div class="mt-5 flex flex-wrap gap-3">@foreach ($product->images as $photo)<img src="{{ Storage::disk('product_images')->url($photo->path) }}" alt="{{ $product->name }} photo {{ $loop->iteration }}" loading="lazy" width="128" height="128" class="size-32 rounded-lg border border-line object-cover">@endforeach</div>@endif
                <dl class="mt-5 grid gap-4 border-t border-line pt-5 text-sm sm:grid-cols-2">
                    <div><dt class="text-muted">Category</dt><dd class="mt-1 font-semibold">{{ $product->category?->name ?? 'Uncategorized' }}</dd></div>
                    <div><dt class="text-muted">SKU</dt><dd class="mt-1 font-semibold">{{ $product->sku }}</dd></div>
                    <div><dt class="text-muted">Price</dt><dd class="mt-1 font-semibold">₱{{ number_format($product->price, 2) }}</dd></div>
                    <div><dt class="text-muted">Inventory</dt><dd class="mt-1 font-semibold">{{ number_format($product->stock_quantity) }} in stock</dd></div>
                    @if ($product->brand)<div><dt class="text-muted">Brand</dt><dd class="mt-1 font-semibold">{{ $product->brand }}</dd></div>@endif
                    @if ($product->condition)<div><dt class="text-muted">Condition</dt><dd class="mt-1 font-semibold">{{ ucfirst($product->condition) }}</dd></div>@endif
                </dl>
                @if ($product->description)<div class="mt-5 border-t border-line pt-5"><h3 class="text-sm font-semibold">Description</h3><p class="mt-2 whitespace-pre-line text-sm leading-6 text-muted">{{ $product->description }}</p></div>@endif
            </section>
            <aside class="space-y-5">
                <section class="rounded-xl border border-line bg-white p-5" aria-labelledby="approval-heading"><h2 id="approval-heading" class="text-base font-semibold">Approval</h2><div class="mt-4"><x-marketplace.product-status :status="$product->status" /></div><p class="mt-3 text-sm text-muted">Inventory and approval are separate states.</p>
                    @if ($canManageProducts && in_array($product->status, ['draft', 'changes_required', 'rejected'], true))<form method="POST" action="{{ route('seller.products.submit', $product) }}" class="mt-4">@csrf<button type="submit" class="seller-primary-button inline-flex min-h-11 items-center px-4 text-sm">Submit for review</button></form>@endif
                </section>
                @if ($product->variants->isNotEmpty())<section class="rounded-xl border border-line bg-white p-5" aria-labelledby="variants-heading"><h2 id="variants-heading" class="text-base font-semibold">Variants</h2><ul class="mt-4 divide-y divide-line">@foreach ($product->variants as $variant)<li class="flex items-start justify-between gap-3 py-3 text-sm"><span><strong class="block">{{ $variant->label() }}</strong><small class="text-muted">{{ $variant->sku }}</small></span><span class="shrink-0 text-right">{{ number_format($variant->stock_quantity) }} in stock @if ($variant->price)<small class="block text-muted">₱{{ number_format($variant->price, 2) }}</small>@endif</span></li>@endforeach</ul></section>@endif
            </aside>
        </div>
    </div></div>
</x-app-layout>
