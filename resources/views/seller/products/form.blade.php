@php
    $editing = $product !== null;
    $existingVariants = $product?->variants?->map(fn ($variant) => $variant->only(['id', 'color', 'size', 'sku', 'stock_quantity']))->all() ?? [];
    $variantRows = collect(old('variants', $existingVariants))
        ->values()
        ->map(fn ($variant, $index) => ['key' => $index, ...$variant])
        ->all();
@endphp

<div class="page-shell">
    <div class="page-container">
        <form method="POST" enctype="multipart/form-data" action="{{ $formAction }}"
            x-data="{ dirty: false, saving: false, previews: [], variants: @js($variantRows), nextKey: {{ count($variantRows) }}, addVariant() { this.variants.push({ key: this.nextKey++, color: '', size: '', sku: '', stock_quantity: 0 }); this.dirty = true } }"
            x-init="window.addEventListener('beforeunload', e => { if (dirty && !saving) { e.preventDefault(); e.returnValue = ''; } })"
            @submit="saving = true; dirty = false"
            class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
            @csrf
            @if ($formMethod === 'PATCH') @method('PATCH') @endif

            <div class="min-w-0 space-y-6">
                <section class="rounded-[14px] border border-line bg-white p-5 shadow-sm sm:p-6" aria-labelledby="product-details-heading">
                    <div class="flex items-start gap-3">
                        <span class="grid size-8 shrink-0 place-items-center rounded-full bg-blue-50 text-sm font-bold text-brand" aria-hidden="true">1</span>
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

                <section class="rounded-[14px] border border-line bg-white p-5 shadow-sm sm:p-6" aria-labelledby="product-photos-heading">
                    <div class="flex items-start gap-3">
                        <span class="grid size-8 shrink-0 place-items-center rounded-full bg-blue-50 text-sm font-bold text-brand" aria-hidden="true">2</span>
                        <div><h2 id="product-photos-heading" class="text-lg font-semibold text-ink">Product photos</h2><p class="mt-1 text-sm text-muted">Show the front, back, details, and available colors.</p></div>
                    </div>
                    @if ($editing && $product->images->isNotEmpty())
                        <div class="mt-5"><p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">Current photos</p><div class="flex gap-3 overflow-x-auto">@foreach ($product->images as $image)<img src="{{ Storage::disk('product_images')->url($image->path) }}" alt="Existing product image {{ $loop->iteration }}" class="size-20 shrink-0 rounded-[10px] border border-line object-cover">@endforeach</div></div>
                    @endif
                    <div class="mt-5 rounded-[14px] border-2 border-dashed border-line bg-canvas p-5 text-center transition focus-within:border-brand">
                        <label for="images" class="block cursor-pointer text-sm font-semibold text-brand">Choose product photos</label>
                        <input id="images" name="images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple
                            @change="dirty = true; previews.forEach(url => URL.revokeObjectURL(url)); previews = [...$event.target.files].map(file => URL.createObjectURL(file))"
                            class="mt-3 block w-full cursor-pointer rounded-lg text-sm text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:font-semibold file:text-brand focus-visible:outline-2 focus-visible:outline-brand">
                        <p class="mt-2 text-xs text-muted">Up to 8 photos, 5 MB each · JPEG, PNG, or WebP.</p>
                    </div>
                    <div x-show="previews.length" x-cloak class="mt-4 flex gap-3 overflow-x-auto" aria-label="Selected photo previews"><template x-for="url in previews" :key="url"><img :src="url" alt="Selected product photo preview" class="size-20 shrink-0 rounded-[10px] border border-line object-cover"></template></div>
                    @if ($editing)<p class="mt-3 text-xs text-muted">New photos are added after the current photos.</p>@endif
                    <x-input-error :messages="$errors->get('images')" class="mt-2" />
                    @foreach ($errors->get('images.*') as $messages) @foreach ($messages as $message)<p class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p>@endforeach @endforeach
                </section>

                <section class="rounded-[14px] border border-line bg-white p-5 shadow-sm sm:p-6" aria-labelledby="product-pricing-heading">
                    <div class="flex items-start gap-3">
                        <span class="grid size-8 shrink-0 place-items-center rounded-full bg-blue-50 text-sm font-bold text-brand" aria-hidden="true">3</span>
                        <div><h2 id="product-pricing-heading" class="text-lg font-semibold text-ink">Price and inventory</h2><p class="mt-1 text-sm text-muted">All color and size options share this price.</p></div>
                    </div>
                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <div><label for="price" class="text-sm font-semibold">Price (PHP) *</label><input id="price" name="price" type="number" min="0.01" step="0.01" value="{{ old('price', $product?->price) }}" required @input="dirty = true" class="mt-1.5 block h-11 w-full rounded-[10px] border-line focus:border-brand focus:ring-brand"><x-input-error :messages="$errors->get('price')" class="mt-2" /></div>
                        <div><label for="compare_at_price" class="text-sm font-semibold">Regular price <span class="font-normal text-muted">(optional)</span></label><input id="compare_at_price" name="compare_at_price" type="number" min="0.01" step="0.01" value="{{ old('compare_at_price', $product?->compare_at_price) }}" @input="dirty = true" class="mt-1.5 block h-11 w-full rounded-[10px] border-line focus:border-brand focus:ring-brand"><p class="mt-1 text-xs text-muted">Set above the selling price to show a sale.</p><x-input-error :messages="$errors->get('compare_at_price')" class="mt-2" /></div>
                        <div><label for="sku" class="text-sm font-semibold">Product SKU *</label><input id="sku" name="sku" value="{{ old('sku', $product?->sku) }}" required @input="dirty = true" class="mt-1.5 block h-11 w-full rounded-[10px] border-line focus:border-brand focus:ring-brand"><x-input-error :messages="$errors->get('sku')" class="mt-2" /></div>
                        <div x-show="variants.length === 0"><label for="stock_quantity" class="text-sm font-semibold">Stock quantity *</label><input id="stock_quantity" name="stock_quantity" type="number" min="0" value="{{ old('stock_quantity', $product?->stock_quantity ?? 0) }}" :disabled="variants.length > 0" :required="variants.length === 0" @input="dirty = true" class="mt-1.5 block h-11 w-full rounded-[10px] border-line focus:border-brand focus:ring-brand"><p class="mt-1 text-xs text-muted">Use 0 when unavailable.</p><x-input-error :messages="$errors->get('stock_quantity')" class="mt-2" /></div>
                    </div>
                </section>

                <section class="rounded-[14px] border border-line bg-white p-5 shadow-sm sm:p-6" aria-labelledby="product-variants-heading">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex items-start gap-3"><span class="grid size-8 shrink-0 place-items-center rounded-full bg-blue-50 text-sm font-bold text-brand" aria-hidden="true">4</span><div><h2 id="product-variants-heading" class="text-lg font-semibold text-ink">Color and size options</h2><p class="mt-1 max-w-xl text-sm text-muted">Add one row for each combination customers can buy. Each needs its own SKU and stock count.</p></div></div>
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
                                </div>
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
                <section class="rounded-[14px] border border-line bg-white p-5 shadow-sm">
                    <h2 class="font-semibold text-ink">Listing settings</h2>
                    <p class="mt-1 text-sm text-muted">{{ $editing ? 'Administrators control whether a listing can be activated.' : 'New listings are saved as drafts and require administrator approval before they appear in the marketplace.' }} Add a category, description, and photo before requesting activation.</p>
                    <div class="mt-4 space-y-2">
                        @foreach (['draft' => 'Draft', 'active' => 'Active', 'inactive' => 'Inactive'] as $value => $label)
                            @if ($editing && ($value !== 'active' || $product->status === 'active'))
                                <label class="flex min-h-11 items-center gap-3 rounded-[10px] border p-3 has-[:checked]:border-brand has-[:checked]:bg-blue-50"><input type="radio" name="status" value="{{ $value }}" @checked(old('status', $product?->status ?? 'draft') === $value) @change="dirty = true" class="text-brand focus:ring-brand"><span class="text-sm font-semibold">{{ $label }}</span></label>
                            @endif
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('status')" class="mt-2" />
                </section>
                <div class="rounded-[14px] border border-line bg-white p-4 shadow-sm">
                    <a href="{{ route('seller.products.index') }}" class="mb-3 flex min-h-11 items-center justify-center rounded-[10px] text-sm font-semibold text-muted hover:bg-canvas focus-visible:ring-2 focus-visible:ring-brand">Cancel</a>
                    <button :disabled="saving" class="flex min-h-11 w-full items-center justify-center rounded-[10px] bg-brand px-4 text-sm font-semibold text-white transition hover:bg-brand-hover focus-visible:ring-2 focus-visible:ring-brand disabled:opacity-70"><span x-show="!saving">{{ $submitLabel }}</span><span x-show="saving" x-cloak>Saving…</span></button>
                </div>
            </aside>
        </form>
    </div>
</div>
