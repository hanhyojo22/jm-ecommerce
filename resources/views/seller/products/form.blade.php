@php
    $editing = $product !== null;
    $existingVariants = $product?->variants?->map(fn ($variant) => [...$variant->only(['id', 'color', 'size', 'sku', 'stock_quantity', 'price']), 'photos' => $variant->images->map(fn ($image) => ['id' => $image->id, 'url' => Storage::disk('product_images')->url($image->path)])->values()->all()])->all() ?? [];
    $existingPhotos = $product?->images?->whereNull('product_variant_id')->map(fn ($image) => ['id' => $image->id, 'url' => Storage::disk('product_images')->url($image->path)])->values()->all() ?? [];
    $variantRows = collect(old('variants', $existingVariants))
        ->values()
        ->map(fn ($variant, $index) => ['key' => $index, ...$variant])
        ->all();
    $listing = [
        'name' => (string) old('name', $product?->name),
        'categoryId' => (string) old('category_id', $product?->category_id),
        'condition' => (string) old('condition', $product?->condition ?? 'new'),
        'description' => (string) old('description', $product?->description),
        'price' => (string) old('price', $product?->price),
        'comparePrice' => (string) old('compare_at_price', $product?->compare_at_price),
        'stock' => (string) old('stock_quantity', $product?->stock_quantity ?? 0),
    ];
@endphp

<div class="page-shell">
    <div class="page-container seller-workspace-page">
        @if ($editing && $product->moderation_reason)<div class="sc-notice mb-5 !block" data-tone="warning"><strong>Administrator feedback</strong><p class="mt-1 whitespace-pre-line">{{ $product->moderation_reason }}</p></div>@endif
        @if ($editing && in_array($product->status, ['pending', 'inactive'], true))
            <div class="sc-card sc-card-pad mb-5"><x-marketplace.product-status :status="$product->status" /><p class="mt-3 text-sm text-muted">{{ $product->status === 'pending' ? 'Your submission is under review. Editing is paused until the administrator records a decision.' : 'This listing is suspended. Contact the platform administrator before making changes.' }}</p><a href="{{ route('seller.products.index') }}" class="mt-3 inline-flex min-h-11 items-center font-semibold text-brand">Back to products</a></div>
        @else
        <form method="POST" enctype="multipart/form-data" action="{{ $formAction }}"
            x-data="{
                dirty: false, saving: false, variants: @js($variantRows), nextKey: {{ count($variantRows) }},
                ...@js($listing), categories: @js($categories->pluck('name', 'id')),
                addVariant() { this.variants.push({ key: this.nextKey++, color: '', size: '', sku: '', stock_quantity: 0, price: '' }); this.dirty = true },
                {{-- Photo manager: saved photos can be reordered, removed (undoable until save) and set as cover; new files can be dropped, previewed and removed before upload. --}}
                photos: @js($existingPhotos), removed: [], newFiles: [], previews: [], maxPhotos: 8, photoNotice: '',
                get keptPhotos() { return this.photos.filter(photo => ! this.removed.includes(photo.id)) },
                get photoCount() { return this.keptPhotos.length + this.newFiles.length },
                get coverUrl() { return this.keptPhotos[0]?.url ?? this.previews[0] ?? null },
                get onSale() { return Number(this.comparePrice) > Number(this.price) && Number(this.price) > 0 },
                get discount() { return Math.round((1 - Number(this.price) / Number(this.comparePrice)) * 100) },
                get stockTotal() { return this.variants.length ? this.variants.reduce((sum, variant) => sum + (Number(variant.stock_quantity) || 0), 0) : Number(this.stock) || 0 },
                peso(amount) { return '₱' + Number(amount).toLocaleString('en-PH', { minimumFractionDigits: Number(amount) % 1 ? 2 : 0, maximumFractionDigits: 2 }) },
                {{-- The same requirements the review submission checks, so the seller sees them before saving. --}}
                get checks() { return [
                    { label: 'At least one photo', done: this.photoCount > 0 },
                    { label: 'Product name', done: this.name.trim() !== '' },
                    { label: 'Category', done: this.categoryId !== '' },
                    { label: 'Description', done: this.description.trim() !== '' },
                    { label: 'Price', done: Number(this.price) > 0 },
                ] },
                move(index, offset) { const target = index + offset; if (target < 0 || target >= this.photos.length) return; const list = [...this.photos]; [list[index], list[target]] = [list[target], list[index]]; this.photos = list; this.dirty = true },
                makeCover(index) { const list = [...this.photos]; list.unshift(list.splice(index, 1)[0]); this.photos = list; this.dirty = true; this.photoNotice = 'Cover photo updated.' },
                toggleRemove(id) { this.removed = this.removed.includes(id) ? this.removed.filter(other => other !== id) : [...this.removed, id]; this.dirty = true },
                addFiles(fileList) {
                    const room = this.maxPhotos - this.photoCount;
                    const files = [...fileList].filter(file => ['image/jpeg', 'image/png', 'image/webp'].includes(file.type));
                    this.newFiles = [...this.newFiles, ...files.slice(0, Math.max(0, room))];
                    this.photoNotice = files.length > room ? `Only ${this.maxPhotos} photos fit. Remove one to add more.` : (files.length < fileList.length ? 'Only JPEG, PNG and WebP photos can be added.' : '');
                    this.syncFiles();
                },
                removeNew(index) { this.newFiles.splice(index, 1); this.syncFiles() },
                syncFiles() {
                    const transfer = new DataTransfer(); this.newFiles.forEach(file => transfer.items.add(file)); this.$refs.images.files = transfer.files;
                    this.previews.forEach(url => URL.revokeObjectURL(url)); this.previews = this.newFiles.map(file => URL.createObjectURL(file)); this.dirty = true;
                },
            }"
            x-init="window.addEventListener('beforeunload', e => { if (dirty && !saving) { e.preventDefault(); e.returnValue = ''; } })"
            @submit="saving = true; dirty = false"
            @input="dirty = true" @change="dirty = true"
            class="pe-layout">
            @csrf
            @if ($formMethod === 'PATCH') @method('PATCH') @endif
            {{-- Saving any edit returns the listing to draft for a fresh review. --}}
            @if ($editing)<input type="hidden" name="status" value="draft">@endif

            <div class="pe-sheet">
                <section class="pe-section" aria-labelledby="product-photos-heading">
                    <div class="pe-section-head">
                        <div><h2 id="product-photos-heading">Photos</h2><p>The first photo is the cover buyers see in search and on your storefront.</p></div>
                        <p class="pe-count" x-text="photoCount + ' of ' + maxPhotos">{{ count($existingPhotos) }} of 8</p>
                    </div>

                    <ul class="sc-photo-grid is-main mt-5" aria-label="Product photos" x-show="photos.length || previews.length">
                        <template x-for="(photo, index) in photos" :key="photo.id">
                            <li class="sc-photo" :class="{ 'is-removed': removed.includes(photo.id), 'is-cover': keptPhotos[0]?.id === photo.id }">
                                <img :src="photo.url" :alt="'Product photo ' + (index + 1)">
                                <span class="sc-photo-cover" x-show="! removed.includes(photo.id) && keptPhotos[0]?.id === photo.id">Cover</span>
                                <span class="sc-photo-removed" x-show="removed.includes(photo.id)">Will be removed</span>
                                <div class="sc-photo-tools">
                                    <template x-if="! removed.includes(photo.id)"><span class="contents">
                                        <button type="button" @click="move(index, -1)" :disabled="index === 0" :aria-label="'Move photo ' + (index + 1) + ' left'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg></button>
                                        <button type="button" @click="makeCover(index)" x-show="keptPhotos[0]?.id !== photo.id" :aria-label="'Make photo ' + (index + 1) + ' the cover'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/></svg></button>
                                        <button type="button" @click="move(index, 1)" :disabled="index === photos.length - 1" :aria-label="'Move photo ' + (index + 1) + ' right'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></button>
                                    </span></template>
                                    <button type="button" @click="toggleRemove(photo.id)" :aria-label="(removed.includes(photo.id) ? 'Keep photo ' : 'Remove photo ') + (index + 1)" :class="removed.includes(photo.id) ? 'is-undo' : 'is-remove'"><span x-text="removed.includes(photo.id) ? 'Undo' : ''"></span><svg x-show="! removed.includes(photo.id)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
                                </div>
                                <input x-show="false" x-bind:disabled="removed.includes(photo.id)" type="hidden" name="image_order[]" :value="photo.id">
                            </li>
                        </template>
                        <template x-for="(url, index) in previews" :key="url">
                            <li class="sc-photo is-new" :class="keptPhotos.length === 0 && index === 0 && 'is-cover'">
                                <img :src="url" :alt="'New photo ' + (index + 1)">
                                <span class="sc-photo-cover" x-show="keptPhotos.length === 0 && index === 0">Cover</span>
                                <span class="sc-photo-new">New</span>
                                <div class="sc-photo-tools"><button type="button" class="is-remove" @click="removeNew(index)" :aria-label="'Remove new photo ' + (index + 1)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button></div>
                            </li>
                        </template>
                    </ul>
                    <template x-for="id in removed" :key="id"><input type="hidden" name="remove_images[]" :value="id"></template>

                    <label for="images" class="sc-dropzone mt-4" x-data="{ over: false }" :class="over && 'is-over'" x-show="photoCount < maxPhotos" @dragover.prevent="over = true" @dragleave.prevent="over = false" @drop.prevent="over = false; addFiles($event.dataTransfer.files)">
                        <svg class="size-6 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.5"/><path d="m4 18 5-5 3 3 3-3 5 5"/><path d="M17 2v5M14.5 4.5h5"/></svg>
                        <span class="text-sm font-semibold text-brand">Choose product photos</span>
                        <span class="text-xs text-muted">or drag them here. JPEG, PNG or WebP, up to 5 MB each. Square, well-lit shots work best.</span>
                        <input id="images" x-ref="images" name="images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" @change="const picked = [...$event.target.files]; $event.target.value = ''; addFiles(picked)">
                    </label>
                    <p x-show="photoCount >= maxPhotos" x-cloak class="mt-3 text-sm text-muted">You’ve reached 8 photos. Remove one to add another.</p>
                    <p class="mt-2 text-sm text-muted" x-show="photoNotice" x-text="photoNotice" role="status" aria-live="polite"></p>
                    <x-input-error :messages="$errors->get('images')" class="mt-2" />
                    @foreach ($errors->get('images.*') as $messages) @foreach ($messages as $message)<p class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p>@endforeach @endforeach
                </section>

                <section class="pe-section" aria-labelledby="product-details-heading">
                    <div class="pe-section-head"><div><h2 id="product-details-heading">Details</h2><p>Describe the item customers will receive.</p></div></div>
                    <div class="pe-fields">
                        <div><label for="name" class="pe-label">Product name</label><input id="name" name="name" x-model="name" value="{{ $listing['name'] }}" required autofocus maxlength="255" placeholder="e.g. Canvas tote bag, navy" class="pe-input is-title"><x-input-error :messages="$errors->get('name')" class="mt-2" /></div>
                        <div class="pe-grid">
                            <div><label for="category_id" class="pe-label">Category</label><select id="category_id" name="category_id" x-model="categoryId" class="pe-input"><option value="">Uncategorized</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected($listing['categoryId'] === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                            <div><label for="brand" class="pe-label">Brand <span class="pe-optional">optional</span></label><input id="brand" name="brand" maxlength="100" value="{{ old('brand', $product?->brand) }}" class="pe-input"><x-input-error :messages="$errors->get('brand')" class="mt-2" /></div>
                        </div>
                        <fieldset>
                            <legend class="pe-label">Condition</legend>
                            <div class="pe-segmented">
                                @foreach (['new' => 'New', 'refurbished' => 'Refurbished'] as $value => $label)
                                    <label><input type="radio" name="condition" value="{{ $value }}" x-model="condition" @checked($listing['condition'] === $value)><span>{{ $label }}</span></label>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('condition')" class="mt-2" />
                        </fieldset>
                        <div>
                            <div class="flex items-baseline justify-between gap-3"><label for="description" class="pe-label">Description</label><span class="pe-count" x-text="description.length.toLocaleString() + ' / 5,000'"></span></div>
                            <textarea id="description" name="description" rows="6" maxlength="5000" x-model="description" placeholder="Materials, size and fit, and what is in the box." class="pe-input">{{ $listing['description'] }}</textarea>
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>
                    </div>
                </section>

                <section class="pe-section" aria-labelledby="product-pricing-heading">
                    <div class="pe-section-head"><div><h2 id="product-pricing-heading">Price and stock</h2><p>Options below can have their own price and stock.</p></div></div>
                    <div class="pe-fields pe-grid">
                        <div><label for="price" class="pe-label">Price</label><div class="pe-affix"><span aria-hidden="true">₱</span><input id="price" name="price" type="number" min="0.01" step="0.01" x-model="price" value="{{ $listing['price'] }}" required placeholder="0.00" class="pe-input"></div><x-input-error :messages="$errors->get('price')" class="mt-2" /></div>
                        <div><label for="compare_at_price" class="pe-label">Regular price <span class="pe-optional">optional</span></label><div class="pe-affix"><span aria-hidden="true">₱</span><input id="compare_at_price" name="compare_at_price" type="number" min="0.01" step="0.01" x-model="comparePrice" value="{{ $listing['comparePrice'] }}" placeholder="0.00" aria-describedby="compare-price-hint" class="pe-input"></div><p id="compare-price-hint" class="pe-hint">Higher than the price to show it as a sale.</p><x-input-error :messages="$errors->get('compare_at_price')" class="mt-2" /></div>
                        <div><label for="sku" class="pe-label">SKU</label><input id="sku" name="sku" value="{{ old('sku', $product?->sku) }}" required placeholder="e.g. TOTE-NAVY" class="pe-input"><x-input-error :messages="$errors->get('sku')" class="mt-2" /></div>
                        <div x-show="variants.length === 0"><label for="stock_quantity" class="pe-label">Stock quantity</label><input id="stock_quantity" name="stock_quantity" type="number" min="0" x-model="stock" value="{{ $listing['stock'] }}" :disabled="variants.length > 0" :required="variants.length === 0" aria-describedby="stock-hint" class="pe-input"><p id="stock-hint" class="pe-hint">Use 0 when unavailable.</p><x-input-error :messages="$errors->get('stock_quantity')" class="mt-2" /></div>
                    </div>
                </section>

                <section class="pe-section" aria-labelledby="product-variants-heading">
                    <div class="pe-section-head">
                        <div><h2 id="product-variants-heading">Color and size options</h2><p>One row for each combination customers can buy, each with its own SKU and stock.</p></div>
                        <button type="button" @click="addVariant()" class="seller-outline-button shrink-0"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Add option</button>
                    </div>
                    <p x-show="variants.length === 0" class="pe-empty">No options. Buyers order this product from the single stock quantity above.</p>
                    <div class="mt-5 space-y-3" x-show="variants.length">
                        <template x-for="(variant, index) in variants" :key="variant.key">
                            <div class="pe-option">
                                <div class="pe-option-head">
                                    <h3 x-text="[variant.color, variant.size].filter(Boolean).join(' / ') || 'Option ' + (index + 1)"></h3>
                                    <button x-show="!variant.id" type="button" @click="variants.splice(index, 1); dirty = true" class="pe-remove">Remove</button>
                                    <span x-show="variant.id" class="pe-hint !mt-0">Set stock to 0 to stop selling</span>
                                </div>
                                <input x-show="false" :disabled="!variant.id" type="hidden" :name="'variants[' + index + '][id]'" :value="variant.id">
                                <div class="pe-option-grid">
                                    <label><span class="pe-label">Color</span><input :name="'variants[' + index + '][color]'" x-model="variant.color" required maxlength="60" placeholder="e.g. Navy" class="pe-input"></label>
                                    <label><span class="pe-label">Size</span><input :name="'variants[' + index + '][size]'" x-model="variant.size" required maxlength="60" placeholder="e.g. Medium" class="pe-input"></label>
                                    <label><span class="pe-label">SKU</span><input :name="'variants[' + index + '][sku]'" x-model="variant.sku" required maxlength="100" placeholder="e.g. TOTE-NAVY-M" class="pe-input"></label>
                                    <label><span class="pe-label">Stock</span><input :name="'variants[' + index + '][stock_quantity]'" x-model="variant.stock_quantity" required type="number" min="0" class="pe-input"></label>
                                    <label><span class="pe-label">Price <span class="pe-optional">optional</span></span><span class="pe-affix"><span aria-hidden="true">₱</span><input :name="'variants[' + index + '][price]'" x-model="variant.price" type="number" min="0.01" step="0.01" :placeholder="price || 'Base price'" class="pe-input"></span></label>
                                    <label class="min-w-0"><span class="pe-label">Photos <span class="pe-optional">up to 4</span></span><input :name="'variants[' + index + '][images][]'" type="file" multiple accept="image/jpeg,image/png,image/webp" class="pe-file"></label>
                                </div>
                                <ul x-show="variant.photos?.length" class="sc-photo-grid is-small mt-3" :aria-label="'Photos for option ' + (index + 1)"><template x-for="(photo, photoIndex) in (variant.photos || [])" :key="photo.id"><li class="sc-photo" :class="removed.includes(photo.id) && 'is-removed'"><img :src="photo.url" :alt="'Option ' + (index + 1) + ' photo ' + (photoIndex + 1)"><span class="sc-photo-removed" x-show="removed.includes(photo.id)">Removed</span><div class="sc-photo-tools"><button type="button" @click="toggleRemove(photo.id)" :aria-label="(removed.includes(photo.id) ? 'Keep' : 'Remove') + ' option ' + (index + 1) + ' photo ' + (photoIndex + 1)" :class="removed.includes(photo.id) ? 'is-undo' : 'is-remove'"><span x-text="removed.includes(photo.id) ? 'Undo' : ''"></span><svg x-show="! removed.includes(photo.id)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button></div></li></template></ul>
                            </div>
                        </template>
                        <p class="pe-hint">Options without their own photos use the product photos.</p>
                    </div>
                    <x-input-error :messages="$errors->get('variants')" class="mt-3" />
                    @foreach ($errors->getMessages() as $field => $messages)
                        @if (str_starts_with($field, 'variants.'))
                            @foreach ($messages as $message)<p class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p>@endforeach
                        @endif
                    @endforeach
                </section>
            </div>

            <aside class="pe-rail" aria-label="Listing summary">
                {{-- A live copy of the card buyers see in search, built from what is typed so far. --}}
                <section class="pe-preview" aria-labelledby="preview-heading">
                    <h2 id="preview-heading">How buyers see it</h2>
                    <div class="pe-card" aria-hidden="true">
                        <div class="pe-card-media">
                            <img x-show="coverUrl" :src="coverUrl" alt="">
                            <svg x-show="! coverUrl" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.5"/><path d="m4 18 5-5 3 3 3-3 5 5"/></svg>
                            <span class="pe-card-sale" x-show="onSale" x-text="'-' + discount + '%'"></span>
                        </div>
                        <div class="pe-card-body">
                            <p class="pe-card-store">{{ $store->name }}</p>
                            <p class="pe-card-name" :class="! name.trim() && 'is-placeholder'" x-text="name.trim() || 'Your product name'"></p>
                            <p class="pe-card-price" :class="Number(price) > 0 ? '' : 'is-placeholder'"><span x-text="Number(price) > 0 ? peso(price) : '₱0'"></span><s x-show="onSale" x-text="peso(comparePrice)"></s></p>
                            <p class="pe-card-meta"><span x-text="categories[categoryId] ?? 'Uncategorized'"></span><span x-show="condition === 'refurbished'">Refurbished</span><span x-text="stockTotal.toLocaleString() + ' in stock'"></span></p>
                        </div>
                    </div>
                </section>

                <section class="pe-ready" aria-labelledby="ready-heading">
                    <h2 id="ready-heading" x-text="checks.every(check => check.done) ? 'Ready for review' : 'Needed for review'">Needed for review</h2>
                    <ul>
                        <template x-for="check in checks" :key="check.label">
                            <li :class="check.done && 'is-done'"><span class="pe-tick" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg></span><span x-text="check.label"></span><span class="sr-only" x-text="check.done ? ', done' : ', missing'"></span></li>
                        </template>
                    </ul>
                    <p class="pe-note">{{ $editing && $product->status === 'active' ? 'Saving changes moves this listing back to draft until it is reviewed again.' : 'Save as a draft, then submit it for review from Products.' }}</p>
                    <x-input-error :messages="$errors->get('status')" class="mt-2" />
                    <div class="pe-actions">
                        <button :disabled="saving" class="seller-primary-button w-full disabled:opacity-70"><span x-show="!saving">{{ $submitLabel }}</span><span x-show="saving" x-cloak>Saving…</span></button>
                        <a href="{{ route('seller.products.index') }}" class="seller-outline-button w-full">Cancel</a>
                    </div>
                </section>
            </aside>
        </form>
        @endif
    </div>
</div>
