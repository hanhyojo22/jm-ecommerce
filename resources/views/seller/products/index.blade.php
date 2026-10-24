<x-app-layout>
    <x-slot name="header">
        <div class="sc-page-head seller-products-header">
            <div><h1 class="sc-title">Your products</h1></div>
            @if ($canManageProducts)<div class="sc-page-actions"><a href="{{ route('seller.products.create') }}" data-seller-shortcut="new-product" aria-keyshortcuts="N" class="seller-primary-button"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Add product</a></div>@endif
        </div>
    </x-slot>
    @php
        $totalProducts = array_sum($productCounts->all());
        $hasFilters = collect($filters)->filter()->isNotEmpty();
        $hasAdvancedFilters = filled($filters['category_id'] ?? null) || filled($filters['stock'] ?? null) || ($filters['sort'] ?? 'newest') !== 'newest';
        $tabs = ['' => 'All', 'active' => 'Published', 'pending' => 'Pending review', 'draft' => 'Draft', 'changes_required' => 'Changes required', 'rejected' => 'Rejected', 'inactive' => 'Suspended'];
    @endphp
    <div class="page-shell"><div class="page-container seller-products-page seller-workspace-page sc-stack">
        @if ($errors->has('status'))<div role="alert" class="sc-notice" data-tone="danger">{{ $errors->first('status') }}</div>@endif
        @if (session('status'))<div role="status" class="sc-notice" data-tone="success">{{ session('status') }}</div>@endif

        <section class="sc-card seller-products-catalog" aria-labelledby="product-catalog-heading">
            <div class="sc-card-head is-flush"><h2 id="product-catalog-heading" class="sc-card-title">Product catalog</h2><p class="sc-meta">{{ number_format($totalProducts) }} {{ Str::plural('listing', $totalProducts) }}</p></div>
            <nav class="sc-tabs" aria-label="Product status">
                @foreach ($tabs as $value => $label)
                    @php($selected = ($filters['status'] ?? '') === $value)
                    <a href="{{ route('seller.products.index', [...request()->except('status', 'page'), ...($value ? ['status' => $value] : [])]) }}" @if ($selected) aria-current="page" @endif class="sc-tab">{{ $label }} <span class="sc-tab-count" aria-hidden="true">{{ number_format($value === '' ? $totalProducts : ($productCounts[$value] ?? 0)) }}</span></a>
                @endforeach
            </nav>

            <div class="sc-toolbar !items-center">
                <form method="GET" action="{{ route('seller.products.index') }}" role="search" class="seller-products-search">
                    @foreach (['status', 'category_id', 'stock', 'sort'] as $field)@if (filled($filters[$field] ?? null))<input type="hidden" name="{{ $field }}" value="{{ $filters[$field] }}">@endif @endforeach
                    <label for="product-search" class="sr-only">Search products</label>
                    <input id="product-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search by name or SKU" class="sc-input pr-10">
                    <button type="submit" class="absolute inset-y-0 right-0 grid w-10 place-items-center text-muted hover:text-brand" aria-label="Search products"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg></button>
                </form>
                @if ($hasAdvancedFilters)<p class="sc-meta">A saved product view is active. <a href="{{ route('seller.products.index') }}" class="font-semibold text-brand underline underline-offset-4">Show all products</a></p>@endif
                @if ($canManageProducts)<button type="submit" form="bulk-product-deactivate" title="Deactivate active listings" class="sc-btn sc-btn-danger ml-auto">Deactivate selected</button>@endif
            </div>
            @if ($canManageProducts)<form id="bulk-product-deactivate" method="POST" action="{{ route('seller.products.bulk-deactivate') }}" data-confirm-message="Deactivate the selected active listings? They will no longer appear in your storefront.">@csrf</form><x-input-error :messages="$errors->get('product_ids')" class="mx-5 mb-3" />@endif

            @if ($products->isNotEmpty())
                <div class="sc-table-wrap hidden xl:block">
                    <table class="sc-table">
                        <thead><tr>@if ($canManageProducts)<th scope="col" class="is-tight"><span class="sr-only">Select</span></th>@endif<th scope="col">Product</th><th scope="col" class="is-num">Price</th><th scope="col">Inventory</th><th scope="col">Approval</th><th scope="col" class="is-tight"><span class="sr-only">Actions</span></th></tr></thead>
                        <tbody>
                            @foreach ($products as $product)
                                <tr class="seller-products-row">
                                    @if ($canManageProducts)<td class="is-tight">@if ($product->status === 'active')<input type="checkbox" form="bulk-product-deactivate" name="product_ids[]" value="{{ $product->id }}" aria-label="Select listing {{ $product->name }}" class="size-4 rounded border-line text-brand focus:ring-brand">@endif</td>@endif
                                    <th scope="row"><div class="sc-row">
                                        <span class="sc-thumb">@if ($product->images->first())<img src="{{ Storage::disk('product_images')->url($product->images->first()->path) }}" alt="" loading="lazy" width="40" height="40">@else<svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 4h16v16H4z"/><circle cx="9" cy="9" r="1.5"/><path d="m4 17 5-5 3 3 3-4 5 6"/></svg>@endif</span>
                                        <span class="min-w-0"><a href="{{ route('seller.products.show', $product) }}" class="block max-w-80 truncate font-semibold text-ink hover:text-brand" title="{{ $product->name }}">{{ $product->name }}</a><span class="block sc-meta">{{ $product->category?->name ?? 'Uncategorized' }}@if ($product->variants_count) · {{ $product->variants_count }} {{ Str::plural('variant', $product->variants_count) }}@endif</span></span>
                                    </div></th>
                                    <td class="is-num font-semibold">₱{{ number_format($product->price, 2) }}</td>
                                    <td>@if ($product->stock_quantity < 1)<span class="sc-badge" data-tone="neutral">Sold out</span>@else<span class="sc-num">{{ number_format($product->stock_quantity) }}</span> <span class="sc-meta">in stock</span>@endif</td>
                                    <td><x-marketplace.product-status :status="$product->status" /></td>
                                    <td class="is-tight">@include('seller.products.actions')</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="sc-list xl:hidden">
                    @foreach ($products as $product)
                        <article class="sc-list-item">
                            <div class="sc-row items-start">
                                @if ($canManageProducts && $product->status === 'active')<input type="checkbox" form="bulk-product-deactivate" name="product_ids[]" value="{{ $product->id }}" aria-label="Select listing {{ $product->name }}" class="mt-3 size-4 shrink-0 rounded border-line text-brand focus:ring-brand">@endif
                                <span class="sc-thumb is-lg">@if ($product->images->first())<img src="{{ Storage::disk('product_images')->url($product->images->first()->path) }}" alt="" loading="lazy" width="56" height="56">@else<svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 4h16v16H4z"/><circle cx="9" cy="9" r="1.5"/><path d="m4 17 5-5 3 3 3-4 5 6"/></svg>@endif</span>
                                <div class="sc-grow"><a href="{{ route('seller.products.show', $product) }}" class="block truncate sc-strong" title="{{ $product->name }}">{{ $product->name }}</a><p class="truncate sc-meta">{{ $product->category?->name ?? 'Uncategorized' }}@if ($product->variants_count) · {{ $product->variants_count }} {{ Str::plural('variant', $product->variants_count) }}@endif</p><p class="mt-1 text-sm"><span class="font-semibold tabular-nums text-ink">₱{{ number_format($product->price, 2) }}</span> <span class="sc-meta">· {{ $product->stock_quantity < 1 ? 'Sold out' : number_format($product->stock_quantity).' in stock' }}</span></p></div>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center justify-between gap-3"><x-marketplace.product-status :status="$product->status" />@include('seller.products.actions')</div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="sc-empty"><h3>{{ $hasFilters ? 'No products match this view' : 'Your catalog is ready' }}</h3><p>{{ $hasFilters ? 'Try a different search or status.' : 'Add your first product to start building your store catalog.' }}</p>@if ($hasFilters)<a href="{{ route('seller.products.index') }}" class="seller-outline-button">Show all products</a>@elseif ($canManageProducts)<a href="{{ route('seller.products.create') }}" class="seller-primary-button">Add your first product</a>@endif</div>
            @endif

            <div class="sc-card-foot"><span>Showing {{ $products->firstItem() ?? 0 }}–{{ $products->lastItem() ?? 0 }} of {{ number_format($products->total()) }} {{ Str::plural('listing', $products->total()) }} · Inventory and approval are separate states</span></div>
            @if ($products->hasPages())<div class="sc-card-foot">{{ $products->links() }}</div>@endif
        </section>
    </div></div>
</x-app-layout>
