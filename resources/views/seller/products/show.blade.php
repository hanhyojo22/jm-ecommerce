@php
    $isOnSale = $product->compare_at_price !== null && (float) $product->compare_at_price > (float) $product->price;
    $canSubmit = in_array($product->status, ['draft', 'changes_required', 'rejected', 'inactive'], true);
    $stage = match ($product->status) { 'pending' => 1, 'active' => 2, default => 0 };
    $stageNote = match ($product->status) {
        'draft' => 'Only you can see this listing. Submit it when the checklist is complete.',
        'changes_required' => 'The administrator asked for changes. Edit the listing, then submit it again.',
        'rejected' => 'This listing was rejected. Edit it to address the feedback, then submit it again.',
        'pending' => 'An administrator is reviewing this listing. Editing is paused until they decide, or until you withdraw the submission.',
        'active' => 'Buyers can find and order this listing. Changes go live right away; edits to the title, description, category or photos are re-checked by an administrator.',
        'unlisted' => 'You unlisted this listing, so buyers cannot see it. Relist it from Products whenever you are ready.',
        'inactive' => 'An administrator suspended this listing. Fix what the feedback asks for, then submit it for review again.',
        default => null,
    };
    $checks = [
        'At least one photo' => $product->images->isNotEmpty(),
        'Category' => $product->category_id !== null,
        'Description' => filled($product->description),
        'Price' => (float) $product->price > 0,
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="sc-page-head">
            <div>
                <a href="{{ route('seller.products.index') }}" class="sc-back"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>Back to products</a>
                <div class="flex flex-wrap items-center gap-3"><h1 class="sc-title">{{ $product->name }}</h1><x-marketplace.product-status :status="$product->status" /></div>
            </div>
            <div class="sc-page-actions">
                @if ($product->status === 'active')<a href="{{ route('catalog.show', [$product->store->slug, $product->slug]) }}" target="_blank" rel="noopener noreferrer" class="seller-outline-button" aria-label="View {{ $product->name }} in your store (opens in a new tab)">View in store <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9"/><path d="M18 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5"/></svg></a>@endif
                @if ($canManageProducts && $product->isEditableBySeller())<a href="{{ route('seller.products.edit', $product) }}" class="seller-primary-button">Edit listing</a>@endif
            </div>
        </div>
    </x-slot>
    <div class="page-shell"><div class="page-container seller-workspace-page sc-stack">
        @if ($product->moderation_reason)<div class="sc-notice !block" data-tone="warning"><strong>Administrator feedback</strong><p class="mt-1 whitespace-pre-line">{{ $product->moderation_reason }}</p></div>@endif
        <div class="pe-layout">
            <div class="pe-sheet">
                <section class="pe-section pd-hero" aria-label="Listing">
                    <div class="pd-gallery" x-data="{ shown: 0 }">
                        <div class="pd-stage">
                            @forelse ($product->images as $photo)
                                <img src="{{ Storage::disk('product_images')->url($photo->path) }}" alt="{{ $product->name }} photo {{ $loop->iteration }}" width="640" height="640" @if (! $loop->first) loading="lazy" x-cloak @endif x-show="shown === {{ $loop->index }}">
                            @empty
                                <div class="pd-stage-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.5"/><path d="m4 18 5-5 3 3 3-3 5 5"/></svg><p>No photos yet</p></div>
                            @endforelse
                            @if ($isOnSale)<span class="pe-card-sale">-{{ (int) round((1 - (float) $product->price / (float) $product->compare_at_price) * 100) }}%</span>@endif
                        </div>
                        @if ($product->images->count() > 1)
                            <div class="pd-thumbs">
                                @foreach ($product->images as $photo)
                                    <button type="button" @click="shown = {{ $loop->index }}" :aria-current="shown === {{ $loop->index }}" aria-label="Show photo {{ $loop->iteration }}"><img src="{{ Storage::disk('product_images')->url($photo->path) }}" alt="" width="72" height="72" loading="lazy"></button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="pd-summary">
                        <p class="pd-price">₱{{ number_format($product->price, 2) }}@if ($isOnSale)<s>₱{{ number_format($product->compare_at_price, 2) }}</s>@endif</p>
                        <dl class="pd-facts">
                            <div><dt>Inventory</dt><dd>{{ number_format($product->stock_quantity) }} in stock</dd></div>
                            <div><dt>SKU</dt><dd class="break-all">{{ $product->sku }}</dd></div>
                            <div><dt>Category</dt><dd>{{ $product->category?->name ?? 'Uncategorized' }}</dd></div>
                            <div><dt>Condition</dt><dd>{{ ucfirst($product->condition ?? 'new') }}</dd></div>
                            @if ($product->brand)<div><dt>Brand</dt><dd>{{ $product->brand }}</dd></div>@endif
                            @if ($product->variants->isNotEmpty())<div><dt>Options</dt><dd>{{ $product->variants->count() }} {{ Str::plural('option', $product->variants->count()) }}</dd></div>@endif
                        </dl>
                        <div class="pd-about">
                            <h2 id="description-heading">Description</h2>
                            @if ($product->description)
                                <p class="pd-description">{{ $product->description }}</p>
                            @else
                                <p class="pe-empty">No description yet. Buyers look for materials, size and what is in the box.</p>
                            @endif
                        </div>
                    </div>
                </section>

                @if ($product->variants->isNotEmpty())
                    <section class="pe-section" aria-labelledby="variants-heading">
                        <div class="pe-section-head"><div><h2 id="variants-heading">Color and size options</h2><p>Options without their own price sell at ₱{{ number_format($product->price, 2) }}.</p></div></div>
                        <div class="sc-table-wrap mt-4">
                            <table class="sc-table">
                                <thead><tr><th scope="col">Option</th><th scope="col">SKU</th><th scope="col" class="is-num">Price</th><th scope="col" class="is-num">Stock</th></tr></thead>
                                <tbody>
                                    @foreach ($product->variants as $variant)
                                        <tr>
                                            <th scope="row"><span class="sc-items"><span class="sc-thumb-stack"><span class="sc-thumb">@if ($colorPhoto = $product->colorPhotos($variant->color)->first())<img src="{{ Storage::disk('product_images')->url($colorPhoto->path) }}" alt="" loading="lazy">@endif</span></span><span class="font-semibold text-ink">{{ $variant->label() }}</span></span></th>
                                            <td class="break-all sc-meta">{{ $variant->sku }}</td>
                                            <td class="is-num">@if ($variant->price)₱{{ number_format($variant->price, 2) }}@else<span class="sc-meta">Base price</span>@endif</td>
                                            <td class="is-num">@if ($variant->stock_quantity > 0){{ number_format($variant->stock_quantity) }}@else<span class="font-semibold text-danger-text">Out of stock</span>@endif</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif
            </div>

            <aside class="pe-rail" aria-label="Review status">
                <section class="pe-ready" aria-labelledby="approval-heading">
                    <h2 id="approval-heading">Review status</h2>
                    {{-- Where the listing sits on its way to buyers; returned and rejected listings are back at the draft stage. --}}
                    <ol class="pd-track" aria-label="Listing progress, step {{ $stage + 1 }} of 3">
                        @foreach (['Draft', 'In review', 'Live'] as $index => $label)
                            <li @class(['is-done' => $index < $stage, 'is-current' => $index === $stage]) @if ($index === $stage) aria-current="step" @endif><span class="pd-track-bar"></span><span class="pd-track-label">{{ $label }}</span></li>
                        @endforeach
                    </ol>
                    @if ($stageNote)<p class="pd-stage-note">{{ $stageNote }}</p>@endif
                    @if ($canSubmit)
                        <ul>
                            @foreach ($checks as $label => $done)
                                <li @class(['is-done' => $done])><span class="pe-tick" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg></span>{{ $label }}<span class="sr-only">, {{ $done ? 'done' : 'missing' }}</span></li>
                            @endforeach
                        </ul>
                        @if ($canManageProducts)<form method="POST" action="{{ route('seller.products.submit', $product) }}" class="pe-actions">@csrf<button type="submit" class="seller-primary-button w-full">Submit for review</button></form>@endif
                    @endif
                    <x-input-error :messages="$errors->get('status')" class="mt-3" />
                </section>
            </aside>
        </div>
    </div></div>
</x-app-layout>
