<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand">Store catalog</p>
                <h1 class="mt-1 text-[28px] font-bold leading-tight tracking-[-0.03em] text-ink">Products</h1>
                <p class="mt-2 text-sm text-muted">Manage the listings customers see in your store.</p>
            </div>
            @if ($canManageProducts)
                <a href="{{ route('seller.products.create') }}" data-seller-shortcut="new-product" aria-keyshortcuts="N" class="seller-primary-button inline-flex min-h-11 items-center justify-center gap-2 px-4 text-sm">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    Add product
                </a>
            @endif
        </div>
    </x-slot>

    @php
        $statusStyles = ['active' => 'bg-success/10 text-success ring-success/20', 'draft' => 'bg-amber-50 text-amber-800 ring-amber-200', 'inactive' => 'bg-slate-100 text-muted ring-line'];
        $totalProducts = array_sum($productCounts->all());
        $hasFilters = collect($filters)->except('sort')->filter()->isNotEmpty();
    @endphp

    <div class="page-shell">
        <div class="page-container space-y-5">
            @if (session('status'))
                <div class="flex items-center gap-3 rounded-xl border border-success/20 bg-success/10 px-4 py-3 text-sm font-medium text-success" role="status">
                    <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>{{ session('status') }}
                </div>
            @endif

            <div class="overflow-hidden rounded-[14px] border border-line bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3 sm:px-5">
                    <p class="text-sm font-semibold text-ink">Catalog overview</p>
                    <p class="text-xs text-muted">{{ number_format($totalProducts) }} {{ Str::plural('listing', $totalProducts) }} in your store</p>
                </div>
                <nav class="flex gap-1 overflow-x-auto px-3 sm:px-4" aria-label="Product summaries">
                    @foreach (['' => 'All products', 'active' => 'Active', 'draft' => 'Draft', 'inactive' => 'Inactive'] as $value => $label)
                        @php($selected = ($filters['status'] ?? '') === $value)
                        <a href="{{ route('seller.products.index', [...request()->except('status', 'page'), ...($value ? ['status' => $value] : [])]) }}" @if ($selected) aria-current="page" @endif class="inline-flex min-h-12 shrink-0 items-center gap-2 border-b-2 px-3 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-inset {{ $selected ? 'border-brand text-brand' : 'border-transparent text-muted hover:border-line hover:text-ink' }}">
                            {{ $label }} <span class="rounded-full px-2 py-0.5 text-xs tabular-nums {{ $selected ? 'bg-blue-50 text-brand' : 'bg-slate-100 text-muted' }}">{{ number_format($value ? ($productCounts[$value] ?? 0) : $totalProducts) }}</span>
                        </a>
                    @endforeach
                    <a href="{{ route('seller.products.index', [...request()->except('stock', 'page'), 'stock' => 'out_of_stock']) }}" @if (($filters['stock'] ?? '') === 'out_of_stock') aria-current="page" @endif class="inline-flex min-h-12 shrink-0 items-center gap-2 border-b-2 px-3 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-inset {{ ($filters['stock'] ?? '') === 'out_of_stock' ? 'border-rose-600 text-rose-700' : 'border-transparent text-muted hover:border-line hover:text-rose-700' }}">
                        Out of stock <span class="rounded-full bg-rose-50 px-2 py-0.5 text-xs tabular-nums text-rose-700">{{ number_format($outOfStockCount) }}</span>
                    </a>
                </nav>
            </div>

            <section class="rounded-[14px] border border-line bg-white p-4 shadow-sm sm:p-5" aria-label="Product filters">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <div><h2 class="text-sm font-semibold text-ink">Find a listing</h2><p class="mt-1 text-xs text-muted">Search by product or SKU, then narrow the results.</p></div>
                    @if ($hasFilters)<a href="{{ route('seller.products.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand">Reset filters</a>@endif
                </div>
                <form method="GET" action="{{ route('seller.products.index') }}" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-12 xl:items-end">
                    <div class="sm:col-span-2 xl:col-span-4">
                        <label for="search" class="mb-1.5 block text-xs font-semibold text-ink">Search catalog</label>
                        <div class="relative"><svg class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input id="search" name="search" value="{{ $filters['search'] ?? '' }}" type="search" placeholder="Product name or SKU" class="block h-11 w-full rounded-[10px] border-line pl-10 text-sm placeholder:text-muted focus:border-brand focus:ring-brand"></div>
                    </div>
                    <div class="xl:col-span-2">
                        <label for="category_id" class="mb-1.5 block text-xs font-semibold text-ink">Category</label>
                        <select id="category_id" name="category_id" class="block h-11 w-full rounded-[10px] border-line text-sm focus:border-brand focus:ring-brand"><option value="">All categories</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected(($filters['category_id'] ?? null) === $category->id)>{{ $category->name }}</option>@endforeach</select>
                    </div>
                    <div class="xl:col-span-2">
                        <label for="status" class="mb-1.5 block text-xs font-semibold text-ink">Status</label>
                        <select id="status" name="status" class="block h-11 w-full rounded-[10px] border-line text-sm focus:border-brand focus:ring-brand"><option value="">All statuses</option>@foreach (['active', 'draft', 'inactive'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
                    </div>
                    <div class="xl:col-span-2">
                        <label for="stock" class="mb-1.5 block text-xs font-semibold text-ink">Stock</label>
                        <select id="stock" name="stock" class="block h-11 w-full rounded-[10px] border-line text-sm focus:border-brand focus:ring-brand"><option value="">All stock levels</option><option value="in_stock" @selected(($filters['stock'] ?? null) === 'in_stock')>In stock</option><option value="out_of_stock" @selected(($filters['stock'] ?? null) === 'out_of_stock')>Out of stock</option></select>
                    </div>
                    <div class="xl:col-span-2">
                        <label for="sort" class="mb-1.5 block text-xs font-semibold text-ink">Sort by</label>
                        <select id="sort" name="sort" class="block h-11 w-full rounded-[10px] border-line text-sm focus:border-brand focus:ring-brand"><option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Newest</option><option value="name" @selected(($filters['sort'] ?? null) === 'name')>Name</option><option value="price" @selected(($filters['sort'] ?? null) === 'price')>Price</option><option value="stock" @selected(($filters['sort'] ?? null) === 'stock')>Stock</option></select>
                    </div>
                    <div class="sm:col-span-2 xl:col-span-12 xl:flex xl:justify-end"><button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 sm:w-auto">Apply filters</button></div>
                </form>
            </section>

            <section class="overflow-hidden rounded-[14px] border border-line bg-white shadow-sm" aria-label="Product catalog">
                <div class="flex flex-col gap-3 border-b border-line px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <div><h2 class="text-base font-semibold text-ink">Listings</h2><p class="mt-0.5 text-xs text-muted">Showing {{ $products->firstItem() ?? 0 }}–{{ $products->lastItem() ?? 0 }} of {{ number_format($products->total()) }}</p></div>
                    @if ($canManageProducts)
                        <form id="bulk-product-deactivate" method="POST" action="{{ route('seller.products.bulk-deactivate') }}" data-confirm-message="Deactivate the selected active listings? They will no longer appear in your storefront." class="flex flex-wrap items-center gap-3">
                            @csrf <span class="text-xs text-muted"><span class="font-semibold text-ink">Deactivate active listings</span> · Select up to 20</span>
                            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-rose-200 bg-white px-4 text-sm font-semibold text-rose-700 transition hover:bg-rose-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-700">Deactivate selected</button>
                        </form>
                    @endif
                </div>
                @if ($canManageProducts)<x-input-error :messages="$errors->get('product_ids')" class="mx-4 mt-3 sm:mx-5" />@endif

                @if ($products->isNotEmpty())
                    <div class="hidden overflow-x-auto lg:block">
                        <table class="w-full min-w-[900px] divide-y divide-line text-left">
                            <thead class="bg-canvas text-xs font-semibold uppercase tracking-[0.1em] text-muted"><tr>
                                @if ($canManageProducts)<th scope="col" class="w-12 px-4 py-3"><span class="sr-only">Select listing</span></th>@endif
                                <th scope="col" class="px-5 py-3">Product</th><th scope="col" class="px-5 py-3 text-right">Price</th><th scope="col" class="px-5 py-3">Stock</th><th scope="col" class="px-5 py-3">Status</th><th scope="col" class="px-5 py-3">Updated</th><th scope="col" class="px-5 py-3 text-right"><span class="sr-only">Actions</span></th>
                            </tr></thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($products as $product)
                                    <tr class="transition hover:bg-blue-50/40">
                                        @if ($canManageProducts)<td class="px-4 py-4">@if ($product->status === 'active')<input type="checkbox" form="bulk-product-deactivate" name="product_ids[]" value="{{ $product->id }}" aria-label="Select listing {{ $product->name }}" class="size-4 rounded border-line text-brand focus:ring-brand">@endif</td>@endif
                                        <th scope="row" class="px-5 py-3.5 font-normal"><div class="flex min-w-0 items-center gap-3">
                                            @if ($product->images->first())
                                                <img src="{{ Storage::disk('product_images')->url($product->images->first()->path) }}" alt="" loading="lazy" class="size-14 shrink-0 rounded-xl border border-line object-cover">
                                            @else
                                                <span class="grid size-14 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-400" aria-hidden="true"><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 4h16v16H4z"/><circle cx="9" cy="9" r="1.5"/><path d="m4 17 5-5 3 3 3-4 5 6"/></svg></span>
                                            @endif
                                            <span class="min-w-0"><span class="block max-w-64 truncate text-sm font-semibold text-ink" title="{{ $product->name }}">{{ $product->name }}</span><span class="mt-0.5 block text-xs text-muted">{{ $product->category?->name ?? 'Uncategorized' }} · SKU {{ $product->sku }}</span></span>
                                        </div></th>
                                        <td class="whitespace-nowrap px-5 py-4 text-right text-sm font-semibold tabular-nums text-ink">₱{{ number_format($product->price, 2) }}</td>
                                        <td class="px-5 py-4"><span class="block text-sm font-semibold tabular-nums {{ $product->stock_quantity === 0 ? 'text-rose-700' : 'text-ink' }}">{{ number_format($product->stock_quantity) }}</span><span class="text-xs {{ $product->stock_quantity === 0 ? 'text-rose-700' : 'text-muted' }}">{{ $product->stock_quantity === 0 ? 'Out of stock' : 'Available' }}</span></td>
                                        <td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusStyles[$product->status] }}">{{ ucfirst($product->status) }}</span></td>
                                        <td class="whitespace-nowrap px-5 py-4 text-xs text-muted">{{ $product->updated_at->format('M j, Y') }}</td>
                                        <td class="px-5 py-4 text-right">@if ($canManageProducts)<a href="{{ route('seller.products.edit', $product) }}" class="inline-flex min-h-11 items-center rounded-[10px] border border-line px-3.5 text-sm font-semibold text-brand transition hover:border-brand hover:bg-blue-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand">Edit listing</a>@else<span class="text-xs text-muted">View only</span>@endif</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="divide-y divide-line lg:hidden">
                        @foreach ($products as $product)
                            <article class="p-4 sm:p-5">
                                <div class="flex items-start gap-3">
                                    @if ($canManageProducts && $product->status === 'active')<input type="checkbox" form="bulk-product-deactivate" name="product_ids[]" value="{{ $product->id }}" aria-label="Select listing {{ $product->name }}" class="mt-5 size-4 shrink-0 rounded border-line text-brand focus:ring-brand">@endif
                                    @if ($product->images->first())
                                        <img src="{{ Storage::disk('product_images')->url($product->images->first()->path) }}" alt="" loading="lazy" class="size-16 shrink-0 rounded-xl border border-line object-cover">
                                    @else
                                        <span class="grid size-16 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-400" aria-hidden="true"><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 4h16v16H4z"/><circle cx="9" cy="9" r="1.5"/><path d="m4 17 5-5 3 3 3-4 5 6"/></svg></span>
                                    @endif
                                    <div class="min-w-0 flex-1"><h3 class="truncate text-sm font-semibold text-ink" title="{{ $product->name }}">{{ $product->name }}</h3><p class="mt-1 truncate text-xs text-muted">{{ $product->category?->name ?? 'Uncategorized' }} · SKU {{ $product->sku }}</p></div>
                                </div>
                                <div class="mt-4 grid grid-cols-2 gap-3 rounded-xl bg-canvas p-3">
                                    <div><p class="text-xs text-muted">Price</p><p class="mt-1 text-sm font-semibold tabular-nums text-ink">₱{{ number_format($product->price, 2) }}</p></div>
                                    <div><p class="text-xs text-muted">Stock</p><p class="mt-1 text-sm font-semibold tabular-nums {{ $product->stock_quantity === 0 ? 'text-rose-700' : 'text-ink' }}">{{ number_format($product->stock_quantity) }} <span class="text-xs font-medium">{{ $product->stock_quantity === 0 ? 'out' : 'available' }}</span></p></div>
                                </div>
                                <div class="mt-4 flex items-center justify-between gap-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusStyles[$product->status] }}">{{ ucfirst($product->status) }}</span>@if ($canManageProducts)<a href="{{ route('seller.products.edit', $product) }}" class="inline-flex min-h-11 items-center rounded-[10px] border border-line px-4 text-sm font-semibold text-brand transition hover:border-brand hover:bg-blue-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand">Edit listing</a>@else<span class="text-xs text-muted">View only</span>@endif</div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center px-6 py-14 text-center">
                        <span class="grid size-14 place-items-center rounded-2xl bg-blue-50 text-brand" aria-hidden="true"><svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 4h16v16H4z"/><path d="M8 9h8M8 13h8M8 17h5"/></svg></span>
                        <h3 class="mt-4 text-base font-semibold text-ink">{{ $hasFilters ? 'No products match your filters' : 'Your catalog is ready' }}</h3>
                        <p class="mt-1 max-w-sm text-sm text-muted">{{ $hasFilters ? 'Try a different search or clear the filters to see more listings.' : 'Add your first product to start building your store catalog.' }}</p>
                        @if ($hasFilters)<a href="{{ route('seller.products.index') }}" class="mt-5 inline-flex min-h-11 items-center text-sm font-semibold text-brand underline underline-offset-4">Reset filters</a>@elseif ($canManageProducts)<a href="{{ route('seller.products.create') }}" class="seller-primary-button mt-5 inline-flex min-h-11 items-center px-4 text-sm">Add your first product</a>@endif
                    </div>
                @endif
                @if ($products->hasPages())<div class="border-t border-line bg-canvas px-4 py-4 sm:px-5">{{ $products->links() }}</div>@endif
            </section>
        </div>
    </div>
</x-app-layout>
