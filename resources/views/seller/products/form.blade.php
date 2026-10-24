@php
    $editing = $product !== null;
    $existingVariants = $product?->variants?->map(fn ($variant) => [...$variant->only(['id', 'color', 'size', 'sku', 'stock_quantity', 'price']), 'photos' => $variant->images->map(fn ($image) => ['id' => $image->id, 'url' => Storage::disk('product_images')->url($image->path)])->values()->all()])->all() ?? [];
    $existingPhotos = $product?->images?->whereNull('product_variant_id')->map(fn ($image) => ['id' => $image->id, 'url' => Storage::disk('product_images')->url($image->path)])->values()->all() ?? [];
    $variantRows = collect(old('variants', $existingVariants))
        ->values()
        ->map(fn ($variant, $index) => ['key' => $index, ...$variant])
        ->all();
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
                addVariant() { this.variants.push({ key: this.nextKey++, color: '', size: '', sku: '', stock_quantity: 0, price: '' }); this.dirty = true },
                {{-- Photo manager: saved photos can be reordered, removed (undoable until save) and set as cover; new files can be dropped, previewed and removed before upload. --}}
                photos: @js($existingPhotos), removed: [], newFiles: [], previews: [], maxPhotos: 8, photoNotice: '',
                get keptPhotos() { return this.photos.filter(photo => ! this.removed.includes(photo.id)) },
                get photoCount() { return this.keptPhotos.length + this.newFiles.length },
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
            class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
            @csrf
            @if ($formMethod === 'PATCH') @method('PATCH') @endif

            <div class="min-w-0 space-y-6">
                <section class="seller-form-card p-5 sm:p-6" aria-labelledby="product-details-heading">
                    <div class="flex items-start gap-3">
                        <span class="grid size-7 shrink-0 place-items-center rounded-full border border-line text-[13px] font-semibold text-muted" aria-hidden="true">1</span>
                        <div><h2 id="product-details-heading" class="text-lg font-semibold text-ink">Product details</h2><p class="mt-1 text-sm text-muted">Describe the item customers will receive.</p></div>
                    </div>
                    <div class="mt-6 space-y-5">
                        <div><label for="name" class="text-sm font-semibold">Product name <span class="text-rose-700">*</span></label><input id="name" name="name" value="{{ old('name', $product?->name) }}" required autofocus @input="dirty = true" class="mt-1.5 block h-11 w-full rounded-[10px] border-line focus:border-brand focus:ring-brand"><x-input-error :messages="$errors->get('name')" class="mt-2" /></div>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div><label for="category_id" class="text-sm font-semibold">Category</label><select id="category_id" name="category_id" @change="dirty = true" class="mt-1.5 block h-11 w-full rounded-[10px] border-line focus:border-brand focus:ring-brand"><option value="">Uncategorized</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id', $product?->category_id) === $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                            <div><label for="brand" class="text-sm font-semibold">Brand <span class="font-normal text-muted">(optional)</span></label><input id="brand" name="brand" maxlength="100" value="{{ old('brand', $product?->brand) }}" @input="dirty = true" class="mt-1.5 block h-11 w-full rounded-[10px] border-line focus:border-brand focus:ring-brand"><x-input-error :messages="$errors->get('brand')" class="mt-2" /></div>
                        </div>
                        <div><label for="condition" class="text-sm font-semibold">Condition</label><select id="condition" name="condition" @change="dirty = true" class="mt-1.5 block h-11 w-full rounded-[10px] border-line focus:border-brand focus:ring-brand"><option value="new" @selected(old('condition', $product?->condition ?? 'new') === 'new')>New</option><option value="refurbished" @selected(old('condition', $product?->condition ?? 'new') === 'refurbished')>Refurbished</option></select><x-input-error :messages="$errors->get('condition')" class="mt-2" /></div>
                        <div><label for="description" class="text-sm font-semibold">Description</label><textarea id="description" name="description" rows="5" maxlength="5000" @input="dirty = true" placeholder="Describe the materials, fit, and what is included." class="mt-1.5 block w-full rounded-[10px] border-line focus:border-brand focus:ring-brand">{{ old('description', $product?->description) }}</textarea><p class="mt-1 text-xs text-muted">Up to 5,000 characters.</p><x-input-error :messages="$errors->get('description')" class="mt-2" /></div>
                    </div>
                </section>

                <section class="seller-form-card p-5 sm:p-6" aria-labelledby="product-photos-heading">
                    <div class="flex items-start gap-3">
                        <span class="grid size-7 shrink-0 place-items-center rounded-full border border-line text-[13px] font-semibold text-muted" aria-hidden="true">2</span>
                        <div><h2 id="product-photos-heading" class="text-lg font-semibold text-ink">Product photos</h2><p class="mt-1 text-sm text-muted">Show the front, back, details, and available colors.</p></div>
                    </div>
                    <div class="mt-5 flex items-baseline justify-between gap-3"><p class="text-sm text-muted">The first photo is the cover buyers see in search and on your storefront.</p><p class="shrink-0 text-sm font-semibold tabular-nums text-ink" x-text="photoCount + ' of ' + maxPhotos">{{ count($existingPhotos) }} of 8</p></div>

                    <ul class="sc-photo-grid mt-3" aria-label="Product photos" x-show="photos.length || previews.length">
                        <template x-for="(photo, index) in photos" :key="photo.id">
                            <li class="sc-photo" :class="removed.includes(photo.id) && 'is-removed'">
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
                            <li class="sc-photo is-new">
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
                        <span class="text-xs text-muted">or drag them here · up to 8 photos, 5 MB each · JPEG, PNG or WebP · square, well-lit shots work best</span>
                        <input id="images" x-ref="images" name="images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" @change="const picked = [...$event.target.files]; $event.target.value = ''; addFiles(picked)">
                    </label>
                    <p x-show="photoCount >= maxPhotos" x-cloak class="mt-3 text-sm text-muted">You’ve reached 8 photos. Remove one to add another.</p>
                    <p class="mt-2 text-sm text-muted" x-show="photoNotice" x-text="photoNotice" role="status" aria-live="polite"></p>
                    @if ($editing)<p class="mt-2 text-xs text-muted">Photo changes are saved with the rest of the listing.</p>@endif
                    <x-input-error :messages="$errors->get('images')" class="mt-2" />
                    @foreach ($errors->get('images.*') as $messages) @foreach ($messages as $message)<p class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p>@endforeach @endforeach
                </section>

                <section class="seller-form-card p-5 sm:p-6" aria-labelledby="product-pricing-heading">
                    <div class="flex items-start gap-3">
                        <span class="grid size-7 shrink-0 place-items-center rounded-full border border-line text-[13px] font-semibold text-muted" aria-hidden="true">3</span>
                        <div><h2 id="product-pricing-heading" class="text-lg font-semibold text-ink">Price and inventory</h2><p class="mt-1 text-sm text-muted">Set the base price. Each variant can use a different price and its own photos.</p></div>
                    </div>
                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <div><label for="price" class="text-sm font-semibold">Price (PHP) *</label><input id="price" name="price" type="number" min="0.01" step="0.01" value="{{ old('price', $product?->price) }}" required @input="dirty = true" class="mt-1.5 block h-11 w-full rounded-[10px] border-line focus:border-brand focus:ring-brand"><x-input-error :messages="$errors->get('price')" class="mt-2" /></div>
                        <div><label for="compare_at_price" class="text-sm font-semibold">Regular price <span class="font-normal text-muted">(optional)</span></label><input id="compare_at_price" name="compare_at_price" type="number" min="0.01" step="0.01" value="{{ old('compare_at_price', $product?->compare_at_price) }}" @input="dirty = true" class="mt-1.5 block h-11 w-full rounded-[10px] border-line focus:border-brand focus:ring-brand"><p class="mt-1 text-xs text-muted">Set above the selling price to show a sale.</p><x-input-error :messages="$errors->get('compare_at_price')" class="mt-2" /></div>
                        <div><label for="sku" class="text-sm font-semibold">Product SKU *</label><input id="sku" name="sku" value="{{ old('sku', $product?->sku) }}" required @input="dirty = true" class="mt-1.5 block h-11 w-full rounded-[10px] border-line focus:border-brand focus:ring-brand"><x-input-error :messages="$errors->get('sku')" class="mt-2" /></div>
                        <div x-show="variants.length === 0"><label for="stock_quantity" class="text-sm font-semibold">Stock quantity *</label><input id="stock_quantity" name="stock_quantity" type="number" min="0" value="{{ old('stock_quantity', $product?->stock_quantity ?? 0) }}" :disabled="variants.length > 0" :required="variants.length === 0" @input="dirty = true" class="mt-1.5 block h-11 w-full rounded-[10px] border-line focus:border-brand focus:ring-brand"><p class="mt-1 text-xs text-muted">Use 0 when unavailable.</p><x-input-error :messages="$errors->get('stock_quantity')" class="mt-2" /></div>
                    </div>
                </section>

                <section class="seller-form-card p-5 sm:p-6" aria-labelledby="product-variants-heading">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex items-start gap-3"><span class="grid size-7 shrink-0 place-items-center rounded-full border border-line text-[13px] font-semibold text-muted" aria-hidden="true">4</span><div><h2 id="product-variants-heading" class="text-lg font-semibold text-ink">Color and size options</h2><p class="mt-1 max-w-xl text-sm text-muted">Add one row for each combination customers can buy. Each needs its own SKU and stock count.</p></div></div>
                        <button type="button" @click="addVariant()" class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-brand px-4 text-sm font-semibold text-brand transition hover:bg-blue-50 focus-visible:ring-2 focus-visible:ring-brand">+ Add option</button>
                    </div>
                    <p x-show="variants.length === 0" class="mt-5 rounded-[10px] bg-canvas p-4 text-sm text-muted">No options added. Customers will buy this product using the single stock quantity above.</p>
                    <div class="mt-5 space-y-3">
                        <template x-for="(variant, index) in variants" :key="variant.key">
                            <div class="rounded-[12px] border border-line bg-canvas p-4">
                                <div class="mb-4 flex items-center justify-between gap-3"><h3 class="text-sm font-semibold text-ink" x-text="'Option ' + (index + 1)"></h3><button x-show="!variant.id" type="button" @click="variants.splice(index, 1); dirty = true" class="min-h-11 rounded-lg px-3 text-sm font-semibold text-rose-700 hover:bg-rose-50 focus-visible:ring-2 focus-visible:ring-rose-700">Remove</button><span x-show="variant.id" class="text-xs text-muted">Set stock to 0 to stop selling</span></div>
                                <input x-show="variant.id" :disabled="!variant.id" type="hidden" :name="'variants[' + index + '][id]'" :value="variant.id">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <label class="text-sm font-semibold text-ink">Color *<input :name="'variants[' + index + '][color]'" x-model="variant.color" required maxlength="60" @input="dirty = true" placeholder="e.g. Navy" class="mt-1.5 block h-11 w-full rounded-[10px] border-line focus:border-brand focus:ring-brand"></label>
                                    <label class="text-sm font-semibold text-ink">Size *<input :name="'variants[' + index + '][size]'" x-model="variant.size" required maxlength="60" @input="dirty = true" placeholder="e.g. Medium" class="mt-1.5 block h-11 w-full rounded-[10px] border-line focus:border-brand focus:ring-brand"></label>
                                    <label class="text-sm font-semibold text-ink">Option SKU *<input :name="'variants[' + index + '][sku]'" x-model="variant.sku" required maxlength="100" @input="dirty = true" placeholder="e.g. TOTE-NAVY-M" class="mt-1.5 block h-11 w-full rounded-[10px] border-line focus:border-brand focus:ring-brand"></label>
                                    <label class="text-sm font-semibold text-ink">Stock *<input :name="'variants[' + index + '][stock_quantity]'" x-model="variant.stock_quantity" required type="number" min="0" @input="dirty = true" class="mt-1.5 block h-11 w-full rounded-[10px] border-line focus:border-brand focus:ring-brand"></label>
                                    <label class="text-sm font-semibold text-ink">Option price (PHP)<input :name="'variants[' + index + '][price]'" x-model="variant.price" type="number" min="0.01" step="0.01" @input="dirty = true" placeholder="Use base price" class="field-control mt-1.5"><span class="mt-1 block text-xs font-normal text-muted">Leave empty to use the base price.</span></label>
                                    <label class="min-w-0 text-sm font-semibold text-ink">Option photos<input :name="'variants[' + index + '][images][]'" type="file" multiple accept="image/jpeg,image/png,image/webp" @change="dirty = true" class="mt-2 block w-full text-xs"><span class="mt-2 block text-xs font-normal text-muted">Up to 4 photos per option, 5 MB each. Shared product photos are used when no option photo is uploaded.</span></label>
                                </div>
                                <ul x-show="variant.photos?.length" class="sc-photo-grid is-small mt-3" :aria-label="'Photos for option ' + (index + 1)"><template x-for="(photo, photoIndex) in (variant.photos || [])" :key="photo.id"><li class="sc-photo" :class="removed.includes(photo.id) && 'is-removed'"><img :src="photo.url" :alt="'Option ' + (index + 1) + ' photo ' + (photoIndex + 1)"><span class="sc-photo-removed" x-show="removed.includes(photo.id)">Removed</span><div class="sc-photo-tools"><button type="button" @click="toggleRemove(photo.id)" :aria-label="(removed.includes(photo.id) ? 'Keep' : 'Remove') + ' option ' + (index + 1) + ' photo ' + (photoIndex + 1)" :class="removed.includes(photo.id) ? 'is-undo' : 'is-remove'"><span x-text="removed.includes(photo.id) ? 'Undo' : ''"></span><svg x-show="! removed.includes(photo.id)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button></div></li></template></ul>
                            </div>
                        </template>
                    </div>
                    <x-input-error :messages="$errors->get('variants')" class="mt-3" />
                    @foreach ($errors->getMessages() as $field => $messages)
                        @if (str_starts_with($field, 'variants.'))
                            @foreach ($messages as $message)<p class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p>@endforeach
                        @endif
                    @endforeach
                </section>
            </div>

            <aside class="space-y-6 lg:sticky lg:top-6 lg:h-fit">
                <section class="seller-form-card p-5">
                    <h2 class="font-semibold text-ink">Listing settings</h2>
                    <p class="mt-1 text-sm leading-6 text-muted">Save your draft, then submit it for review from Products. Add a category, description, and photo before submitting. Saving changes to a published listing returns it to Draft for a fresh review.</p>
                    <div class="mt-4 space-y-2">
                        @foreach (['draft' => 'Save as draft'] as $value => $label)
                            @if ($editing && ($value !== 'active' || $product->status === 'active'))
                                <label class="flex min-h-11 items-center gap-3 rounded-[10px] border p-3 has-[:checked]:border-brand has-[:checked]:bg-blue-50"><input type="radio" name="status" value="{{ $value }}" checked @change="dirty = true" class="text-brand focus:ring-brand"><span class="text-sm font-semibold">{{ $label }}</span></label>
                            @endif
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('status')" class="mt-2" />
                    <div class="mt-5 grid gap-2 border-t border-line pt-5">
                        <button :disabled="saving" class="seller-primary-button min-h-11 w-full disabled:opacity-70"><span x-show="!saving">{{ $submitLabel }}</span><span x-show="saving" x-cloak>Saving…</span></button>
                        <a href="{{ route('seller.products.index') }}" class="seller-outline-button min-h-11 w-full">Cancel</a>
                    </div>
                </section>
            </aside>
        </form>
        @endif
    </div>
</div>
