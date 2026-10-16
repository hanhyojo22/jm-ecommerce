<x-app-layout>
    <x-slot name="header">
        <div class="seller-products-header flex flex-wrap items-center justify-between gap-4">
            <div><h1 class="text-[28px] font-bold leading-tight tracking-[-0.03em] text-ink">Your products</h1><p class="mt-1 text-sm text-muted">A little care for every listing goes a long way.</p></div>
            @if ($canManageProducts)<a href="{{ route('seller.products.create') }}" data-seller-shortcut="new-product" aria-keyshortcuts="N" class="seller-primary-button inline-flex min-h-11 items-center gap-2 px-4 text-sm"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Add product</a>@endif
        </div>
    </x-slot>
    @php
        $totalProducts = array_sum($productCounts->all());
        $hasFilters = collect($filters)->filter()->isNotEmpty();
        $hasAdvancedFilters = filled($filters['category_id'] ?? null) || filled($filters['stock'] ?? null) || ($filters['sort'] ?? 'newest') !== 'newest';
        $tabs = ['' => 'All', 'pending' => 'Pending review', 'active' => 'Published', 'draft' => 'Draft', 'changes_required' => 'Changes required', 'rejected' => 'Rejected', 'inactive' => 'Suspended'];
    @endphp
    <div class="page-shell"><div class="page-container seller-products-page seller-workspace-page space-y-4">
        @if ($errors->has('status'))<div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">{{ $errors->first('status') }}</div>@endif
        @if (session('status'))<div role="status" class="rounded-xl border border-success/20 bg-success/10 px-4 py-3 text-sm font-medium text-success">{{ session('status') }}</div>@endif
        <section class="seller-products-catalog overflow-hidden rounded-xl border border-line bg-white" aria-labelledby="product-catalog-heading">
            <div class="seller-products-panel-head flex items-center justify-between gap-4"><h2 id="product-catalog-heading" class="text-base font-semibold text-ink">Product catalog</h2><p class="text-xs text-muted">{{ number_format($totalProducts) }} {{ Str::plural('listing', $totalProducts) }}</p></div>
            <div class="seller-products-toolbar flex flex-wrap items-center justify-between gap-3">
                <nav class="seller-products-tabs flex min-w-0 flex-wrap gap-1" aria-label="Product status">
                    @foreach ($tabs as $value => $label)
                        @php($selected = ($filters['status'] ?? '') === $value)
                        <a href="{{ route('seller.products.index', [...request()->except('status', 'page'), ...($value ? ['status' => $value] : [])]) }}" @if ($selected) aria-current="page" @endif class="inline-flex min-h-11 items-center rounded-md px-3 text-xs font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand {{ $selected ? 'bg-blue-50 text-brand' : 'text-muted hover:bg-canvas hover:text-ink' }}">{{ $label }}</a>
                    @endforeach
                </nav>
                <form method="GET" action="{{ route('seller.products.index') }}" role="search" class="seller-products-search flex items-center rounded-lg border border-line bg-canvas focus-within:border-brand focus-within:ring-1 focus-within:ring-brand">
                    @foreach (['status', 'category_id', 'stock', 'sort'] as $field)@if (filled($filters[$field] ?? null))<input type="hidden" name="{{ $field }}" value="{{ $filters[$field] }}">@endif @endforeach
                    <label for="product-search" class="sr-only">Search products</label>
                    <input id="product-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search products..." class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-sm focus:ring-0">
                    <button type="submit" class="grid size-10 shrink-0 place-items-center text-muted hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand" aria-label="Search products"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg></button>
                </form>
            </div>
            @if ($hasAdvancedFilters)<div class="flex flex-wrap items-center gap-3 px-5 pb-4 text-xs text-muted"><span>A saved product view is active.</span><a href="{{ route('seller.products.index') }}" class="font-semibold text-brand underline underline-offset-4">Show all products</a></div>@endif
            @if ($canManageProducts)<form id="bulk-product-deactivate" method="POST" action="{{ route('seller.products.bulk-deactivate') }}" data-confirm-message="Deactivate the selected active listings? They will no longer appear in your storefront.">@csrf</form><x-input-error :messages="$errors->get('product_ids')" class="mx-5 my-3" />@endif
            @if ($products->isNotEmpty())
                <div class="hidden overflow-x-auto xl:block">
                    <table class="w-full min-w-[820px] text-left">
                        <thead class="border-y border-line bg-canvas text-[10px] font-semibold uppercase tracking-[0.08em] text-muted"><tr><th scope="col" class="pl-5 pr-2 py-3">Product</th><th scope="col" class="px-3 py-3">Price</th><th scope="col" class="px-3 py-3">Inventory</th><th scope="col" class="px-3 py-3">Approval</th><th scope="col" class="px-3 py-3">Actions</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($products as $product)
                                <tr class="seller-products-row transition hover:bg-blue-50/30">
                                    <th scope="row" class="pl-5 pr-2 py-3 font-normal"><div class="flex min-w-0 items-center gap-3">
                                        @if ($canManageProducts && $product->status === 'active')<input type="checkbox" form="bulk-product-deactivate" name="product_ids[]" value="{{ $product->id }}" aria-label="Select listing {{ $product->name }}" class="size-4 shrink-0 rounded border-line text-brand focus:ring-brand">@endif
                                        @if ($product->images->first())<img src="{{ Storage::disk('product_images')->url($product->images->first()->path) }}" alt="" loading="lazy" width="40" height="40" class="size-10 shrink-0 rounded-md border border-line object-cover">@else<span class="grid size-10 shrink-0 place-items-center rounded-md bg-slate-100 text-slate-400" aria-hidden="true"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 4h16v16H4z"/><circle cx="9" cy="9" r="1.5"/><path d="m4 17 5-5 3 3 3-4 5 6"/></svg></span>@endif
                                        <span class="min-w-0"><a href="{{ route('seller.products.show', $product) }}" class="block max-w-72 truncate text-sm font-semibold text-ink hover:text-brand" title="{{ $product->name }}">{{ $product->name }}</a><span class="mt-0.5 block text-xs text-muted">{{ $product->category?->name ?? 'Uncategorized' }}@if ($product->variants_count) · {{ $product->variants_count }} {{ Str::plural('variant', $product->variants_count) }}@endif</span></span>
                                    </div></th>
                                    <td class="whitespace-nowrap px-3 py-3 text-sm font-semibold tabular-nums text-ink">₱{{ number_format($product->price, 2) }}</td>
                                    <td class="px-3 py-3 text-xs text-muted">@if ($product->stock_quantity < 1)<span class="rounded bg-slate-100 px-2 py-1 font-medium text-slate-600">Sold out</span>@else{{ number_format($product->stock_quantity) }} in stock @endif</td>
                                    <td class="px-3 py-3"><x-marketplace.product-status :status="$product->status" /></td>
                                    <td class="px-3 py-3">@include('seller.products.actions')</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="divide-y divide-line xl:hidden">
                    @foreach ($products as $product)
                        <article class="p-4 sm:p-5">
                            <div class="flex min-w-0 items-start gap-3">
                                @if ($canManageProducts && $product->status === 'active')<input type="checkbox" form="bulk-product-deactivate" name="product_ids[]" value="{{ $product->id }}" aria-label="Select listing {{ $product->name }}" class="mt-3 size-4 shrink-0 rounded border-line text-brand focus:ring-brand">@endif
                                @if ($product->images->first())<img src="{{ Storage::disk('product_images')->url($product->images->first()->path) }}" alt="" loading="lazy" width="52" height="52" class="size-[52px] shrink-0 rounded-md border border-line object-cover">@else<span class="grid size-[52px] shrink-0 place-items-center rounded-md bg-slate-100 text-slate-400" aria-hidden="true"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 4h16v16H4z"/><circle cx="9" cy="9" r="1.5"/><path d="m4 17 5-5 3 3 3-4 5 6"/></svg></span>@endif
                                <div class="min-w-0 flex-1"><a href="{{ route('seller.products.show', $product) }}" class="block truncate text-sm font-semibold text-ink" title="{{ $product->name }}">{{ $product->name }}</a><p class="mt-1 truncate text-xs text-muted">{{ $product->category?->name ?? 'Uncategorized' }}@if ($product->variants_count) · {{ $product->variants_count }} {{ Str::plural('variant', $product->variants_count) }}@endif</p></div>
                            </div>
                            <dl class="mt-4 grid grid-cols-2 gap-3 text-xs"><div><dt class="text-muted">Price</dt><dd class="mt-1 font-semibold text-ink">₱{{ number_format($product->price, 2) }}</dd></div><div><dt class="text-muted">Inventory</dt><dd class="mt-1 font-semibold text-ink">{{ $product->stock_quantity < 1 ? 'Sold out' : number_format($product->stock_quantity).' in stock' }}</dd></div></dl>
                            <div class="mt-4 flex flex-wrap items-center justify-between gap-3"><x-marketplace.product-status :status="$product->status" />@include('seller.products.actions')</div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="px-6 py-14 text-center"><h3 class="text-base font-semibold text-ink">{{ $hasFilters ? 'No products match this view' : 'Your catalog is ready' }}</h3><p class="mt-2 text-sm text-muted">{{ $hasFilters ? 'Try a different search or status.' : 'Add your first product to start building your store catalog.' }}</p>@if ($hasFilters)<a href="{{ route('seller.products.index') }}" class="mt-4 inline-flex min-h-11 items-center font-semibold text-brand">Show all products</a>@elseif ($canManageProducts)<a href="{{ route('seller.products.create') }}" class="seller-primary-button mt-4 inline-flex min-h-11 items-center px-4 text-sm">Add your first product</a>@endif</div>
            @endif
            <div class="seller-products-footer flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-4 text-xs text-muted"><span>Showing {{ $products->firstItem() ?? 0 }}–{{ $products->lastItem() ?? 0 }} of {{ number_format($products->total()) }} {{ Str::plural('listing', $products->total()) }} · Inventory and approval are separate states</span>@if ($canManageProducts)<button type="submit" form="bulk-product-deactivate" title="Deactivate active listings" class="inline-flex min-h-11 items-center rounded-lg border border-rose-200 px-3 font-semibold text-rose-700 hover:bg-rose-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-rose-700">Deactivate selected</button>@endif</div>
            @if ($products->hasPages())<div class="border-t border-line px-5 py-4">{{ $products->links() }}</div>@endif
        </section>
    </div></div>
</x-app-layout>
