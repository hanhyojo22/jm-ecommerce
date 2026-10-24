@php
    $editing = $product !== null;
    $existingVariants = $product?->variants?->sortBy('id')->map(fn ($variant) => [...$variant->only(['id', 'color', 'size', 'sku', 'stock_quantity', 'price']), 'photos' => $variant->images->map(fn ($image) => ['id' => $image->id, 'url' => Storage::disk('product_images')->url($image->path)])->values()->all()])->all() ?? [];
    $existingPhotos = $product?->images?->whereNull('product_variant_id')->map(fn ($image) => ['id' => $image->id, 'url' => Storage::disk('product_images')->url($image->path)])->values()->all() ?? [];
    $savedVariantPhotos = collect($existingVariants)->pluck('photos', 'id');
    $variantRows = collect(old('variants', $existingVariants))
        ->values()
        ->map(fn ($variant) => [...collect($variant)->except('images')->all(), 'photos' => $savedVariantPhotos[$variant['id'] ?? ''] ?? []])
        ->all();
    $listing = [
        'name' => (string) old('name', $product?->name),
        'sku' => (string) old('sku', $product?->sku),
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
        @if ($editing && $product->moderation_reason)<div class="sc-notice mx-auto mb-5 max-w-[1120px] !block" data-tone="warning"><strong>Administrator feedback</strong><p class="mt-1 whitespace-pre-line">{{ $product->moderation_reason }}</p></div>@endif
        @if ($editing && in_array($product->status, ['pending', 'inactive'], true))
            <div class="sc-card sc-card-pad mb-5"><x-marketplace.product-status :status="$product->status" /><p class="mt-3 text-sm text-muted">{{ $product->status === 'pending' ? 'Your submission is under review. Editing is paused until the administrator records a decision.' : 'This listing is suspended. Contact the platform administrator before making changes.' }}</p><a href="{{ route('seller.products.index') }}" class="mt-3 inline-flex min-h-11 items-center font-semibold text-brand">Back to products</a></div>
        @else
        <form method="POST" enctype="multipart/form-data" action="{{ $formAction }}"
            x-data="{
                dirty: false, saving: false,
                ...@js($listing), categories: @js($categories->pluck('name', 'id')),
                {{-- Photo manager: saved photos can be reordered, removed (undoable until save) and set as cover; new files can be dropped, previewed and removed before upload. --}}
                photos: @js($existingPhotos), removed: [], newFiles: [], previews: [], maxPhotos: 8, photoNotice: '',
                get keptPhotos() { return this.photos.filter(photo => ! this.removed.includes(photo.id)) },
                get photoCount() { return this.keptPhotos.length + this.newFiles.length },
                {{-- Variants: up to two options (Color, Size). Every combination of their values becomes a row; rows are kept by combination so edits survive option changes. --}}
                options: [], rows: {}, excluded: [], collapsed: [], expanded: [], rowPreviews: {}, maxVariants: 30,
                keyOf(color, size) { return (color || '').trim().toLowerCase() + '|' + (size || '').trim().toLowerCase() },
                distinct(values) { const seen = new Set(); return values.map(value => (value ?? '').trim()).filter(value => value && ! seen.has(value.toLowerCase()) && seen.add(value.toLowerCase())) },
                valuesOf(name) { return this.distinct(this.options.find(option => option.name === name)?.values ?? []) },
                get hasSavedVariants() { return Object.values(this.rows).some(row => row.id) },
                combos() {
                    const colors = this.valuesOf('Color'), sizes = this.valuesOf('Size');
                    if (! colors.length && ! sizes.length) return [];
                    const colorFirst = this.options[0]?.name !== 'Size';
                    const outer = colorFirst ? colors : sizes, inner = colorFirst ? sizes : colors;
                    return (outer.length ? outer : ['']).flatMap(first => (inner.length ? inner : ['']).map(second => {
                        const color = colorFirst ? first : second, size = colorFirst ? second : first;
                        return { key: this.keyOf(color, size), color, size, group: first, rest: second };
                    }));
                },
                syncRows() {
                    this.combos().forEach(combo => {
                        if (this.rows[combo.key]) return;
                        const suffix = [combo.color, combo.size].filter(Boolean).join('-');
                        this.rows[combo.key] = { id: null, color: combo.color, size: combo.size, sku: this.sku.trim() ? (this.sku.trim() + '-' + suffix).toUpperCase().replace(/\s+/g, '-') : '', stock_quantity: 0, price: '', photos: [] };
                    });
                },
                get grouped() { return this.valuesOf('Color').length > 0 && this.valuesOf('Size').length > 0 },
                get offered() { return this.combos().filter(combo => ! this.excluded.includes(combo.key) && this.rows[combo.key]) },
                get variantCount() { return this.offered.length },
                get removedCount() { const keys = this.combos().map(combo => combo.key); return this.excluded.filter(key => keys.includes(key)).length },
                get totalAvailable() { return this.offered.reduce((sum, combo) => sum + (Number(this.rows[combo.key].stock_quantity) || 0), 0) },
                get tableItems() {
                    const items = []; let current = null;
                    this.offered.forEach((combo, index) => {
                        if (this.grouped && combo.group !== current) {
                            current = combo.group;
                            const members = this.offered.filter(other => other.group === combo.group);
                            items.push({ type: 'group', key: 'group:' + combo.key, label: combo.group, count: members.length, stock: members.reduce((sum, other) => sum + (Number(this.rows[other.key].stock_quantity) || 0), 0) });
                        }
                        items.push({ type: 'row', key: combo.key, index, group: combo.group, row: this.rows[combo.key], label: this.grouped ? combo.rest : (combo.color || combo.size), fullLabel: [combo.color, combo.size].filter(Boolean).join(' / ') });
                    });
                    return items;
                },
                isLocked(option, value) { const field = option.name.toLowerCase(), wanted = value.trim().toLowerCase(); return wanted !== '' && Object.values(this.rows).some(row => row.id && (row[field] || '').toLowerCase() === wanted) },
                isDuplicate(option, index) { const value = option.values[index].trim().toLowerCase(); return value !== '' && option.values.slice(0, index).some(other => other.trim().toLowerCase() === value) },
                addOption() { const name = ['Color', 'Size'].find(candidate => ! this.options.some(option => option.name === candidate)); this.options.push({ name, values: [''], open: true }); this.dirty = true },
                typedValue(option, index) { if (index === option.values.length - 1 && option.values[index].trim()) option.values.push(''); this.syncRows() },
                removeValue(option, index) { option.values.splice(index, 1); this.syncRows(); this.dirty = true },
                removeOption(index) { this.options.splice(index, 1); this.syncRows(); this.dirty = true },
                finishOption(index) {
                    const option = this.options[index];
                    option.values = [...this.distinct(option.values), ''];
                    if (option.values.length === 1) { this.removeOption(index); return }
                    option.open = false; this.syncRows();
                },
                previewRow(key, event) { (this.rowPreviews[key] || []).forEach(url => URL.revokeObjectURL(url)); this.rowPreviews[key] = [...event.target.files].slice(0, 4).map(file => URL.createObjectURL(file)) },
                rowThumb(item) { return item.row.photos.find(photo => ! this.removed.includes(photo.id))?.url ?? this.rowPreviews[item.key]?.[0] ?? null },
                rowPhotoCount(item) { return item.row.photos.filter(photo => ! this.removed.includes(photo.id)).length + (this.rowPreviews[item.key]?.length || 0) },
                initVariants(list) {
                    list.forEach(variant => { this.rows[this.keyOf(variant.color, variant.size)] = { id: variant.id ?? null, color: variant.color ?? '', size: variant.size ?? '', sku: variant.sku ?? '', stock_quantity: variant.stock_quantity ?? 0, price: variant.price ?? '', photos: variant.photos ?? [] } });
                    ['Color', 'Size'].forEach(name => {
                        const values = this.distinct(list.map(variant => variant[name.toLowerCase()]));
                        if (values.length) this.options.push({ name, values: [...values, ''], open: false });
                    });
                    this.excluded = this.combos().map(combo => combo.key).filter(key => ! this.rows[key]);
                },
                {{-- The same requirements the review submission checks, so the seller sees them before saving. --}}
                get checks() { return [
                    { label: 'At least one photo', done: this.photoCount > 0 },
                    { label: 'Title', done: this.name.trim() !== '' },
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
                init() {
                    this.initVariants(@js($variantRows));
                    window.addEventListener('beforeunload', event => { if (this.dirty && ! this.saving) { event.preventDefault(); event.returnValue = '' } });
                },
            }"
            @submit="saving = true; dirty = false"
            @input="dirty = true" @change="dirty = true"
            class="ps-layout">
            @csrf
            @if ($formMethod === 'PATCH') @method('PATCH') @endif
            {{-- Saving any edit returns the listing to draft for a fresh review. --}}
            @if ($editing)<input type="hidden" name="status" value="draft">@endif

            {{-- Contextual save bar, shown once anything changes. --}}
            <div class="ps-savebar" x-show="dirty" x-cloak x-transition.opacity.duration.150ms role="region" aria-label="Unsaved changes">
                <p><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>{{ $editing ? 'Unsaved changes' : 'Unsaved product' }}</p>
                <div>
                    <button type="button" class="ps-savebar-discard" @click="dirty = false; window.location.reload()">Discard</button>
                    <button type="submit" class="ps-savebar-save" :disabled="saving">Save</button>
                </div>
            </div>

            <div class="ps-main">
                <section class="ps-card" aria-label="Title and description">
                    <div><label for="name" class="pe-label">Title</label><input id="name" name="name" x-model="name" value="{{ $listing['name'] }}" required autofocus maxlength="255" placeholder="Short sleeve t-shirt" class="pe-input"><x-input-error :messages="$errors->get('name')" class="mt-2" /></div>
                    <div class="mt-4">
                        <div class="flex items-baseline justify-between gap-3"><label for="description" class="pe-label">Description</label><span class="pe-count" x-text="description.length.toLocaleString() + ' / 5,000'"></span></div>
                        <textarea id="description" name="description" rows="7" maxlength="5000" x-model="description" placeholder="Materials, size and fit, and what is in the box." class="pe-input">{{ $listing['description'] }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>
                </section>

                <section class="ps-card" aria-labelledby="product-photos-heading">
                    <div class="ps-card-head"><h2 id="product-photos-heading">Photos</h2><p class="pe-count" x-text="photoCount + ' of ' + maxPhotos">{{ count($existingPhotos) }} of 8</p></div>

                    <ul class="sc-photo-grid is-main mt-3" aria-label="Product photos" x-show="photos.length || previews.length">
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

                    <label for="images" class="sc-dropzone mt-3" x-data="{ over: false }" :class="over && 'is-over'" x-show="photoCount < maxPhotos" @dragover.prevent="over = true" @dragleave.prevent="over = false" @drop.prevent="over = false; addFiles($event.dataTransfer.files)">
                        <span class="ps-upload">Upload photos</span>
                        <span class="text-xs text-muted">or drag them here. JPEG, PNG or WebP, up to 5 MB each. The first photo is the cover.</span>
                        <input id="images" x-ref="images" name="images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" @change="const picked = [...$event.target.files]; $event.target.value = ''; addFiles(picked)">
                    </label>
                    <p x-show="photoCount >= maxPhotos" x-cloak class="mt-3 text-sm text-muted">You’ve reached 8 photos. Remove one to add another.</p>
                    <p class="mt-2 text-sm text-muted" x-show="photoNotice" x-text="photoNotice" role="status" aria-live="polite"></p>
                    <x-input-error :messages="$errors->get('images')" class="mt-2" />
                    @foreach ($errors->get('images.*') as $messages) @foreach ($messages as $message)<p class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p>@endforeach @endforeach
                </section>

                <section class="ps-card" aria-labelledby="product-pricing-heading">
                    <div class="ps-card-head"><h2 id="product-pricing-heading">Pricing</h2></div>
                    <div class="pe-grid mt-4">
                        <div><label for="price" class="pe-label">Price</label><div class="pe-affix"><span aria-hidden="true">₱</span><input id="price" name="price" type="number" min="0.01" step="0.01" x-model="price" value="{{ $listing['price'] }}" required placeholder="0.00" class="pe-input"></div><x-input-error :messages="$errors->get('price')" class="mt-2" /></div>
                        <div><label for="compare_at_price" class="pe-label">Compare-at price</label><div class="pe-affix"><span aria-hidden="true">₱</span><input id="compare_at_price" name="compare_at_price" type="number" min="0.01" step="0.01" x-model="comparePrice" value="{{ $listing['comparePrice'] }}" placeholder="0.00" aria-describedby="compare-price-hint" class="pe-input"></div><x-input-error :messages="$errors->get('compare_at_price')" class="mt-2" /></div>
                    </div>
                    <p id="compare-price-hint" class="pe-hint">To show a sale, enter the regular price as the compare-at price. <span x-show="variantCount">Variants without their own price use this price.</span></p>
                </section>

                <section class="ps-card" aria-labelledby="product-inventory-heading">
                    <div class="ps-card-head"><h2 id="product-inventory-heading">Inventory</h2></div>
                    <div class="pe-grid mt-4">
                        <div><label for="sku" class="pe-label">SKU (Stock Keeping Unit)</label><input id="sku" name="sku" x-model="sku" value="{{ $listing['sku'] }}" required maxlength="100" placeholder="TSHIRT-BASIC" class="pe-input"><x-input-error :messages="$errors->get('sku')" class="mt-2" /></div>
                        <div x-show="variantCount === 0"><label for="stock_quantity" class="pe-label">Stock quantity</label><input id="stock_quantity" name="stock_quantity" type="number" min="0" x-model="stock" value="{{ $listing['stock'] }}" :disabled="variantCount > 0" :required="variantCount === 0" aria-describedby="stock-hint" class="pe-input"><p id="stock-hint" class="pe-hint">Use 0 when unavailable.</p><x-input-error :messages="$errors->get('stock_quantity')" class="mt-2" /></div>
                    </div>
                    <p x-show="variantCount > 0" x-cloak class="pe-hint">Stock is set for each variant below.</p>
                </section>

                <section class="ps-card is-flush" aria-labelledby="product-variants-heading">
                    <div class="ps-card-head ps-card-pad"><h2 id="product-variants-heading">Variants</h2></div>

                    <button type="button" x-show="options.length === 0" @click="addOption()" class="ps-add-row"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg>Add options like size or color</button>

                    <div class="ps-options" x-show="options.length" x-cloak>
                        <template x-for="(option, optionIndex) in options" :key="optionIndex">
                            <div class="ps-option">
                                <template x-if="option.open">
                                    <div>
                                        <label class="pe-label" :for="'option-name-' + optionIndex">Option name</label>
                                        <select :id="'option-name-' + optionIndex" x-model="option.name" @change="syncRows()" :disabled="option.values.some(value => isLocked(option, value))" class="pe-input">
                                            <template x-for="name in ['Color', 'Size']" :key="name"><option :value="name" :disabled="options.some((other, otherIndex) => otherIndex !== optionIndex && other.name === name)" x-text="name" :selected="option.name === name"></option></template>
                                        </select>
                                        <p class="pe-label mt-4" :id="'option-values-' + optionIndex">Option values</p>
                                        <div class="ps-values" role="group" :aria-labelledby="'option-values-' + optionIndex">
                                            <template x-for="(value, valueIndex) in option.values" :key="optionIndex + '-' + valueIndex">
                                                <div>
                                                    <div class="ps-value">
                                                        <input x-model="option.values[valueIndex]" @input="typedValue(option, valueIndex)" @keydown.enter.prevent="$event.target.closest('.ps-values').querySelectorAll('input')[valueIndex + 1]?.focus()" :readonly="isLocked(option, value)" maxlength="60" :aria-label="option.name + ' value ' + (valueIndex + 1)" :aria-invalid="isDuplicate(option, valueIndex)" :placeholder="valueIndex === option.values.length - 1 ? (valueIndex === 0 ? (option.name === 'Color' ? 'Navy' : 'Medium') : 'Add another value') : ''" class="pe-input">
                                                        <button type="button" x-show="valueIndex < option.values.length - 1 && ! isLocked(option, value)" @click="removeValue(option, valueIndex)" :aria-label="'Delete ' + value" class="ps-icon-button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></svg></button>
                                                    </div>
                                                    <p x-show="isDuplicate(option, valueIndex)" class="mt-1 text-sm text-danger" x-text="'You’ve already used the option value “' + value.trim() + '”.'"></p>
                                                </div>
                                            </template>
                                        </div>
                                        <p x-show="option.values.some(value => isLocked(option, value))" class="pe-hint">Values used by saved variants can’t be changed. Set their stock to 0 to stop selling them.</p>
                                        <div class="ps-option-actions">
                                            <button type="button" x-show="! option.values.some(value => isLocked(option, value))" @click="removeOption(optionIndex)" class="pe-remove">Delete</button>
                                            <button type="button" @click="finishOption(optionIndex)" :disabled="option.values.some((value, valueIndex) => isDuplicate(option, valueIndex))" class="seller-outline-button !min-h-9">Done</button>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="! option.open">
                                    <button type="button" class="ps-option-summary" @click="option.open = true" :aria-label="'Edit ' + option.name + ' option'">
                                        <span class="ps-option-name" x-text="option.name"></span>
                                        <span class="ps-pills"><template x-for="value in distinct(option.values)" :key="value"><span class="ps-pill" x-text="value"></span></template></span>
                                    </button>
                                </template>
                            </div>
                        </template>
                        <button type="button" x-show="options.length < 2 && ! hasSavedVariants" @click="addOption()" class="ps-add-row is-inline"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg>Add another option</button>
                    </div>

                    <div class="ps-variants" x-show="variantCount > 0" x-cloak>
                        <div class="ps-variant-head" aria-hidden="true"><span>Variant</span><span>Price</span><span>Available</span><span>SKU</span><span></span></div>
                        <template x-for="item in tableItems" :key="item.key">
                            <div>
                                <template x-if="item.type === 'group'">
                                    <button type="button" class="ps-group" @click="collapsed = collapsed.includes(item.label) ? collapsed.filter(label => label !== item.label) : [...collapsed, item.label]" :aria-expanded="! collapsed.includes(item.label)">
                                        <span class="ps-group-name"><span x-text="item.label"></span><span class="pe-count" x-text="item.count + (item.count === 1 ? ' variant' : ' variants')"></span></span>
                                        <span class="ps-group-stock" x-text="item.stock.toLocaleString() + ' available'"></span>
                                        <svg :class="collapsed.includes(item.label) ? '' : 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                    </button>
                                </template>
                                <template x-if="item.type === 'row'">
                                    <div class="ps-variant" :class="grouped && 'is-child'" x-show="! collapsed.includes(item.group)">
                                        <input type="hidden" :name="'variants[' + item.index + '][id]'" :value="item.row.id" :disabled="! item.row.id">
                                        <input type="hidden" :name="'variants[' + item.index + '][color]'" :value="item.row.color">
                                        <input type="hidden" :name="'variants[' + item.index + '][size]'" :value="item.row.size">
                                        <div class="ps-variant-line">
                                            <label class="ps-variant-media" :title="'Add photos for ' + item.fullLabel">
                                                <img x-show="rowThumb(item)" :src="rowThumb(item)" alt="">
                                                <svg x-show="! rowThumb(item)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.5"/><path d="m4 18 5-5 3 3 3-3 5 5"/></svg>
                                                <span class="ps-variant-media-count" x-show="rowPhotoCount(item) > 1" x-text="rowPhotoCount(item)"></span>
                                                <span class="sr-only" x-text="'Add photos for ' + item.fullLabel"></span>
                                                <input type="file" :name="'variants[' + item.index + '][images][]'" multiple accept="image/jpeg,image/png,image/webp" class="sr-only" @change="previewRow(item.key, $event)">
                                            </label>
                                            <span class="ps-variant-name"><span x-text="item.label"></span><button type="button" x-show="item.row.photos.length" @click="expanded = expanded.includes(item.key) ? expanded.filter(key => key !== item.key) : [...expanded, item.key]" class="ps-link" x-text="expanded.includes(item.key) ? 'Hide photos' : 'Manage photos'"></button></span>
                                            <label class="ps-cell"><span class="ps-cell-label" aria-hidden="true">Price</span><span class="pe-affix"><span aria-hidden="true">₱</span><input type="number" :name="'variants[' + item.index + '][price]'" x-model="item.row.price" min="0.01" step="0.01" :placeholder="price || '0.00'" :aria-label="'Price for ' + item.fullLabel" class="pe-input"></span></label>
                                            <label class="ps-cell"><span class="ps-cell-label" aria-hidden="true">Available</span><input type="number" :name="'variants[' + item.index + '][stock_quantity]'" x-model="item.row.stock_quantity" required min="0" :aria-label="'Available for ' + item.fullLabel" class="pe-input"></label>
                                            <label class="ps-cell"><span class="ps-cell-label" aria-hidden="true">SKU</span><input :name="'variants[' + item.index + '][sku]'" x-model="item.row.sku" required maxlength="100" placeholder="SKU" :aria-label="'SKU for ' + item.fullLabel" class="pe-input"></label>
                                            <button type="button" x-show="! item.row.id" @click="excluded.push(item.key); dirty = true" :aria-label="'Remove ' + item.fullLabel" class="ps-icon-button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></svg></button>
                                            <span x-show="item.row.id" class="ps-icon-spacer" aria-hidden="true"></span>
                                        </div>
                                        <ul x-show="expanded.includes(item.key)" class="sc-photo-grid is-small ps-variant-photos" :aria-label="'Photos for ' + item.fullLabel"><template x-for="(photo, photoIndex) in item.row.photos" :key="photo.id"><li class="sc-photo" :class="removed.includes(photo.id) && 'is-removed'"><img :src="photo.url" :alt="item.fullLabel + ' photo ' + (photoIndex + 1)"><span class="sc-photo-removed" x-show="removed.includes(photo.id)">Removed</span><div class="sc-photo-tools"><button type="button" @click="toggleRemove(photo.id)" :aria-label="(removed.includes(photo.id) ? 'Keep ' : 'Remove ') + item.fullLabel + ' photo ' + (photoIndex + 1)" :class="removed.includes(photo.id) ? 'is-undo' : 'is-remove'"><span x-text="removed.includes(photo.id) ? 'Undo' : ''"></span><svg x-show="! removed.includes(photo.id)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button></div></li></template></ul>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <div class="ps-variant-foot">
                            <span x-text="'Total available: ' + totalAvailable.toLocaleString()"></span>
                            <button type="button" x-show="removedCount" @click="excluded = []; dirty = true" class="ps-link" x-text="'Restore ' + removedCount + ' removed'"></button>
                        </div>
                    </div>
                    <p x-show="variantCount > maxVariants" x-cloak class="ps-card-pad pt-3 text-sm text-danger" role="alert" x-text="'Products can have up to ' + maxVariants + ' variants. Remove some values or variants.'"></p>
                    <div class="ps-card-pad ps-card-errors">
                        <x-input-error :messages="$errors->get('variants')" class="mt-3" />
                        @foreach ($errors->getMessages() as $field => $messages)
                            @if (str_starts_with($field, 'variants.'))
                                @foreach ($messages as $message)<p class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p>@endforeach
                            @endif
                        @endforeach
                    </div>
                </section>
            </div>

            <aside class="ps-side" aria-label="Status and organization">
                <section class="ps-card" aria-labelledby="status-heading">
                    <div class="ps-card-head"><h2 id="status-heading">Status</h2><x-marketplace.product-status :status="$editing ? $product->status : 'draft'" /></div>
                    <p class="pe-hint">{{ $editing && $product->status === 'active' ? 'Saving changes moves this listing back to draft until it is reviewed again.' : 'Saved as a draft. Submit it for review from Products when it’s ready.' }}</p>
                    <p class="ps-side-label">Needed for review</p>
                    <ul class="ps-checks">
                        <template x-for="check in checks" :key="check.label">
                            <li :class="check.done && 'is-done'"><span class="pe-tick" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg></span><span x-text="check.label"></span><span class="sr-only" x-text="check.done ? ', done' : ', missing'"></span></li>
                        </template>
                    </ul>
                    <x-input-error :messages="$errors->get('status')" class="mt-2" />
                </section>

                <section class="ps-card" aria-labelledby="organization-heading">
                    <div class="ps-card-head"><h2 id="organization-heading">Product organization</h2></div>
                    <div class="mt-4 grid gap-4">
                        <div><label for="category_id" class="pe-label">Category</label><select id="category_id" name="category_id" x-model="categoryId" class="pe-input"><option value="">Uncategorized</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected($listing['categoryId'] === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                        <div><label for="brand" class="pe-label">Brand</label><input id="brand" name="brand" maxlength="100" value="{{ old('brand', $product?->brand) }}" class="pe-input"><x-input-error :messages="$errors->get('brand')" class="mt-2" /></div>
                        <fieldset>
                            <legend class="pe-label">Condition</legend>
                            <div class="pe-segmented ps-segmented">
                                @foreach (['new' => 'New', 'refurbished' => 'Refurbished'] as $value => $label)
                                    <label><input type="radio" name="condition" value="{{ $value }}" x-model="condition" @checked($listing['condition'] === $value)><span>{{ $label }}</span></label>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('condition')" class="mt-2" />
                        </fieldset>
                    </div>
                </section>
            </aside>

            <div class="ps-footer">
                <a href="{{ route('seller.products.index') }}" class="seller-outline-button">Cancel</a>
                <button :disabled="saving" class="seller-primary-button disabled:opacity-70"><span x-show="!saving">{{ $submitLabel }}</span><span x-show="saving" x-cloak>Saving…</span></button>
            </div>
        </form>
        @endif
    </div>
</div>
