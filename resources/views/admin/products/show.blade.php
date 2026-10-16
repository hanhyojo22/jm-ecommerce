<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div><a href="{{ route('admin.products.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand">Marketplace Products / Product review</a><h1 class="text-3xl font-bold tracking-tight text-ink">{{ $product->name }}</h1><p class="mt-2 text-sm text-muted">Review listing details, variants, and seller eligibility.</p></div>
            <x-marketplace.product-status :status="$product->status" />
        </div>
    </x-slot>
    <div class="page-shell"><div class="page-container">
        @if (session('status'))<div role="status" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('status') }}</div>@endif
        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="min-w-0 space-y-6">
                <section class="section-card"><div class="grid gap-3 sm:grid-cols-2">@forelse ($product->images as $image)<img src="{{ Storage::disk('product_images')->url($image->path) }}" alt="{{ $product->name }}" class="aspect-square w-full rounded-xl bg-canvas object-contain">@empty<p class="py-12 text-center text-sm text-muted">No product images provided</p>@endforelse</div></section>
                <section class="section-card"><h2 class="text-lg font-semibold">Listing details</h2><dl class="mt-5 grid gap-5 sm:grid-cols-2"><div><dt class="field-label">Base price</dt><dd class="text-xl font-bold tabular-nums">&#8369;{{ number_format($product->price, 2) }}</dd></div><div><dt class="field-label">Stock</dt><dd class="text-xl font-bold tabular-nums">{{ number_format($product->stock_quantity) }} <span class="text-sm font-medium text-muted">{{ $product->stock_quantity ? 'available' : 'out of stock' }}</span></dd></div><div><dt class="field-label">Category</dt><dd>{{ $product->category?->name ?? 'Uncategorized' }}</dd></div><div><dt class="field-label">SKU</dt><dd>{{ $product->sku }}</dd></div></dl><h3 class="mt-6 border-t border-line pt-5 font-semibold">Description</h3><p class="mt-2 whitespace-pre-line text-sm leading-6">{{ $product->description ?: 'No description provided.' }}</p></section>
                <section class="section-card"><h2 class="text-lg font-semibold">Variants</h2><div class="mt-4 grid gap-3 sm:grid-cols-2">@forelse ($product->variants as $variant)<article class="rounded-xl border border-line p-4"><h3 class="font-semibold">{{ $variant->label() }}</h3><p class="mt-1 text-sm text-muted">SKU {{ $variant->sku }}</p><p class="mt-3 font-semibold tabular-nums">&#8369;{{ number_format($variant->price ?? $product->price, 2) }}</p><p class="mt-1 text-sm {{ $variant->stock_quantity ? 'text-success' : 'text-danger' }}">{{ $variant->stock_quantity ? number_format($variant->stock_quantity).' available' : 'Sold out' }}</p></article>@empty<p class="text-sm text-muted">No color or size options</p>@endforelse</div></section>
                <section class="section-card"><h2 class="text-lg font-semibold">Listing history</h2><div class="mt-4 divide-y divide-line">@forelse ($auditLogs as $log)<article class="py-4 first:pt-0"><p class="text-sm font-semibold">{{ str($log->action)->replace('.', ' ')->headline() }}</p>@if (isset($log->new_values['status']))<p class="mt-1 text-sm">{{ \App\Models\Product::STATUSES[$log->old_values['status'] ?? 'draft'] }} &middot; {{ \App\Models\Product::STATUSES[$log->new_values['status']] }}</p>@endif @if (isset($log->new_values['reason']))<p class="mt-2 whitespace-pre-line text-sm text-muted">{{ $log->new_values['reason'] }}</p>@endif<p class="mt-2 text-xs text-muted">{{ $log->actor?->name ?? 'System' }} &middot; {{ $log->created_at->format('M j, Y - g:i A') }}</p></article>@empty<p class="text-sm text-muted">No product moderation decisions have been recorded.</p>@endforelse</div></section>
            </div>
            <aside class="space-y-5 lg:sticky lg:top-24">
                <section class="section-card"><h2 class="text-lg font-semibold">Moderate listing</h2><p class="mt-2 text-sm leading-6 text-muted">Only approved stores can publish. Stock availability is separate from the review decision.</p>
                    @if ($product->moderationOptions())
                        <form method="POST" action="{{ route('admin.products.moderate', $product) }}" class="mt-5 space-y-4" x-data="{ saving: false }" @submit="saving = true">@csrf @method('PATCH')
                            <div><label for="status" class="field-label">Decision</label><select id="status" name="status" required class="field-control">@foreach ($product->moderationOptions() as $value => $label)<option value="{{ $value }}" @selected(old('status') === $value)>{{ $label }}</option>@endforeach</select><x-input-error :messages="$errors->get('status')" class="mt-2" /></div>
                            <div><label for="reason" class="field-label">Reason</label><textarea id="reason" name="reason" rows="4" minlength="10" maxlength="1000" required class="field-control" placeholder="Explain the decision and any changes the seller needs to make.">{{ old('reason') }}</textarea><x-input-error :messages="$errors->get('reason')" class="mt-2" /></div>
                            <button :disabled="saving" class="min-h-11 w-full rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-hover disabled:opacity-60" x-text="saving ? 'Saving...' : 'Save decision'">Save decision</button>
                        </form>
                    @else
                        <p class="mt-4 rounded-xl bg-canvas p-3 text-sm text-muted">{{ $product->status === 'draft' ? 'Waiting for the seller to submit this draft.' : 'The seller must update and resubmit this listing before a new decision.' }}</p>
                    @endif
                </section>
                <section class="section-card"><h2 class="font-semibold">Seller information</h2><dl class="mt-4 space-y-4 text-sm"><div><dt class="field-label">Store</dt><dd>{{ $product->store->name }}</dd></div><div><dt class="field-label">Seller business</dt><dd>{{ $product->store->tenant->name }}</dd></div><div><dt class="field-label">Store status</dt><dd>{{ ucfirst($product->store->status) }}</dd></div></dl></section>
                @if ($product->moderation_reason)<section class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950"><h2 class="font-semibold">Latest decision</h2><p class="mt-2 whitespace-pre-line">{{ $product->moderation_reason }}</p></section>@endif
            </aside>
        </div>
    </div></div>
</x-app-layout>
