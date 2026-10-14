<x-app-layout>
    <x-slot name="header">
        <div class="mx-auto w-full max-w-[1096px]">
            <h1 class="text-[28px] font-bold leading-[1.35] tracking-[-0.02em] text-ink">Store settings</h1>
            <p class="mt-1 text-sm text-muted">Manage the identity and contact details shoppers see on your storefront.</p>
        </div>
    </x-slot>

    <div class="page-shell">
        <div class="page-container max-w-6xl">
            @if (session('status') || $canUndo)
                <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-success/20 bg-success/10 px-4 py-3" role="status">
                    <p class="text-sm font-medium text-success">{{ session('status', 'A recent text-only save can still be undone.') }}</p>
                    @if ($canUndo)
                        <form method="POST" action="{{ route('seller.store-settings.undo') }}">
                            @csrf
                            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-success/30 bg-white px-4 text-sm font-semibold text-ink transition hover:bg-success/10 focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Undo last save</button>
                        </form>
                    @endif
                </div>
            @endif

            <div class="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] xl:items-start">
            <section class="flex min-w-0 flex-col gap-4 rounded-[14px] border border-line bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-5 xl:col-start-2 xl:row-start-1 xl:items-stretch xl:flex-col" aria-labelledby="store-preview-heading">
                <div class="flex min-w-0 items-center gap-4">
                    @if ($store->logo_path)
                        <img src="{{ Storage::disk('public')->url($store->logo_path) }}" alt="" class="size-14 shrink-0 rounded-xl border border-line bg-canvas object-cover">
                    @else
                        <div class="grid size-14 shrink-0 place-items-center rounded-xl bg-blue-50 text-xl font-bold text-brand" aria-hidden="true">{{ mb_strtoupper(mb_substr($store->name, 0, 1)) }}</div>
                    @endif
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted">Live storefront</p>
                        <h2 id="store-preview-heading" class="mt-0.5 truncate text-base font-semibold text-ink">{{ $store->name }}</h2>
                        <p class="truncate text-sm text-muted">/stores/{{ $store->slug }}</p>
                    </div>
                </div>
                <a href="{{ route('catalog.store', $store->slug) }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 self-start rounded-[10px] border border-line bg-white px-4 text-sm font-semibold text-ink transition hover:border-brand hover:text-brand focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2 sm:self-auto xl:self-start">
                    View storefront
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9"/><path d="M18 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5"/></svg>
                </a>
            </section>

            <form method="POST" action="{{ route('seller.store-settings.update') }}" enctype="multipart/form-data" class="min-w-0 space-y-5 xl:col-start-1 xl:row-start-1"
                x-data="{ dirty: {{ $errors->any() ? 'true' : 'false' }}, saving: false, logoPreview: null, logoName: '', removeLogo: {{ old('remove_logo') ? 'true' : 'false' }}, descriptionLength: {{ mb_strlen(old('description', $store->description) ?? '') }} }"
                x-init="window.addEventListener('beforeunload', event => { if (dirty && !saving) { event.preventDefault(); event.returnValue = ''; } })"
                @input="dirty = true" @change="dirty = true"
                @submit="if (saving) { $event.preventDefault(); } else { saving = true; dirty = false; }">
                @csrf
                @method('PATCH')
                @if ($errors->any())
                    <div role="alert" tabindex="-1" x-init="$nextTick(() => $el.focus())" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">We could not save your changes. Review the errors below and try again.</div>
                @endif
                <x-input-error :messages="$errors->get('settings')" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3" />
                <x-input-error :messages="$errors->get('undo')" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3" />
                <p class="text-sm font-medium" :class="dirty ? 'text-warning-text' : 'text-muted'" role="status" aria-live="polite"
                    x-text="saving ? 'Saving store settings…' : dirty ? 'Unsaved changes' : 'All changes saved'">{{ $errors->any() ? 'Unsaved changes' : 'All changes saved' }}</p>

                <section class="rounded-[14px] border border-line bg-white p-5 shadow-sm sm:p-6" aria-labelledby="store-profile-heading">
                    <div class="flex items-start gap-3">
                        <span class="grid size-10 shrink-0 place-items-center rounded-[10px] bg-blue-50 text-brand" aria-hidden="true">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9.5 5 4h14l2 5.5M4 10h16v10H4zM9 20v-6h6v6M3 9.5a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/></svg>
                        </span>
                        <div>
                            <h2 id="store-profile-heading" class="text-lg font-semibold text-ink">Store identity</h2>
                            <p class="mt-1 text-sm text-muted">Choose a recognizable logo and describe what your store offers.</p>
                        </div>
                    </div>

                    <div class="mt-6 grid min-w-0 gap-6 border-t border-line pt-6">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-ink">Store logo</p>
                            <p id="logo-help" class="mt-1 text-sm text-muted">Square JPEG, PNG, or WebP. Maximum 2 MB.</p>
                            <div class="mt-3 flex min-w-0 flex-wrap items-center gap-4 rounded-[10px] border border-line bg-canvas p-4">
                                <div class="grid size-20 shrink-0 place-items-center overflow-hidden rounded-[10px] border border-line bg-white">
                                    @if ($store->logo_path)
                                        <img src="{{ Storage::disk('public')->url($store->logo_path) }}" alt="Current {{ $store->name }} logo" class="size-full object-cover" x-show="!logoPreview && !removeLogo">
                                        <span x-cloak x-show="removeLogo" class="text-center text-xs font-medium text-muted">No logo</span>
                                    @else
                                        <span class="text-3xl font-bold text-muted" aria-hidden="true" x-show="!logoPreview">{{ mb_strtoupper(mb_substr($store->name, 0, 1)) }}</span>
                                    @endif
                                    <img x-cloak x-show="logoPreview" :src="logoPreview" alt="Selected store logo preview" class="size-full object-cover">
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-ink" x-text="logoPreview ? 'New logo preview' : removeLogo ? 'Logo will be removed' : '{{ $store->logo_path ? 'Current logo' : 'No logo uploaded yet' }}'">{{ $store->logo_path ? 'Current logo' : 'No logo uploaded yet' }}</p>
                                    <input id="logo" x-ref="logoInput" name="logo" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="logo-help @error('logo') logo-error @enderror" @error('logo') aria-invalid="true" @enderror
                                        @change="if (logoPreview) URL.revokeObjectURL(logoPreview); logoPreview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null; logoName = $event.target.files[0]?.name || ''; if (logoPreview) removeLogo = false"
                                        class="peer sr-only">
                                    <label for="logo" class="mt-2 inline-flex min-h-11 cursor-pointer items-center justify-center rounded-[10px] border border-line bg-white px-4 text-sm font-semibold text-brand transition hover:border-brand hover:bg-blue-50 peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand @error('logo') border-rose-500 @enderror">Change logo</label>
                                    <p x-cloak x-show="logoName" x-text="logoName" class="mt-2 truncate text-xs text-muted"></p>
                                </div>
                            </div>
                            <x-input-error id="logo-error" :messages="$errors->get('logo')" class="mt-2" />
                            @if ($store->logo_path)
                                <label class="mt-3 inline-flex min-h-11 items-center gap-3 text-sm font-medium text-ink">
                                    <input x-model="removeLogo" type="checkbox" name="remove_logo" value="1" @checked(old('remove_logo')) aria-describedby="remove-logo-help @error('remove_logo') remove-logo-error @enderror" @error('remove_logo') aria-invalid="true" @enderror
                                        @change="if ($event.target.checked) { $refs.logoInput.value = ''; if (logoPreview) URL.revokeObjectURL(logoPreview); logoPreview = null; logoName = ''; }"
                                        class="size-4 rounded border-line text-brand focus:ring-brand @error('remove_logo') border-rose-500 @enderror">
                                    Remove current logo on save
                                </label>
                                <p id="remove-logo-help" class="text-xs text-muted">This removes the current image only after you save. It cannot be restored here.</p>
                                <x-input-error id="remove-logo-error" :messages="$errors->get('remove_logo')" class="mt-2" />
                            @endif
                        </div>

                        <div class="min-w-0">
                            <label for="description" class="block text-sm font-semibold text-ink">Store description</label>
                            <p id="description-help" class="mt-1 text-sm text-muted">Tell shoppers what you sell and what makes your store distinct.</p>
                            <textarea id="description" name="description" rows="5" maxlength="2000" aria-describedby="description-help description-count @error('description') description-error @enderror" @error('description') aria-invalid="true" @enderror @input="descriptionLength = Array.from($event.target.value).length" class="field-control mt-3 resize-y @error('description') border-rose-500 @enderror">{{ old('description', $store->description) }}</textarea>
                            <div class="mt-2 flex justify-end">
                                <p id="description-count" class="text-xs text-muted"><span x-text="descriptionLength">{{ mb_strlen(old('description', $store->description) ?? '') }}</span> / 2,000 characters</p>
                            </div>
                            <x-input-error id="description-error" :messages="$errors->get('description')" class="mt-2" />
                        </div>
                    </div>
                </section>

                <section class="rounded-[14px] border border-line bg-white p-5 shadow-sm sm:p-6" aria-labelledby="contact-details-heading">
                    <div class="flex items-start gap-3">
                        <span class="grid size-10 shrink-0 place-items-center rounded-[10px] bg-blue-50 text-brand" aria-hidden="true">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-5.5 7-12a7 7 0 0 0-14 0c0 6.5 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/></svg>
                        </span>
                        <div>
                            <h2 id="contact-details-heading" class="text-lg font-semibold text-ink">Contact and address</h2>
                            <p class="mt-1 text-sm text-muted">Keep these details current for customer and order follow-up.</p>
                        </div>
                    </div>

                    <div class="mt-6 grid min-w-0 gap-5 border-t border-line pt-6 sm:grid-cols-2">
                        <div class="min-w-0">
                            <label for="phone" class="block text-sm font-semibold text-ink">Phone <span class="font-normal text-muted">(required)</span></label>
                            <input id="phone" name="phone" type="tel" autocomplete="tel" maxlength="30" required @error('phone') aria-describedby="phone-error" aria-invalid="true" @enderror value="{{ old('phone', $store->phone) }}" class="field-control mt-2 @error('phone') border-rose-500 @enderror">
                            <x-input-error id="phone-error" :messages="$errors->get('phone')" class="mt-2" />
                        </div>
                        <div class="min-w-0">
                            <label for="city" class="block text-sm font-semibold text-ink">City <span class="font-normal text-muted">(required)</span></label>
                            <input id="city" name="city" autocomplete="address-level2" maxlength="100" required @error('city') aria-describedby="city-error" aria-invalid="true" @enderror value="{{ old('city', $store->city) }}" class="field-control mt-2 @error('city') border-rose-500 @enderror">
                            <x-input-error id="city-error" :messages="$errors->get('city')" class="mt-2" />
                        </div>
                        <div class="min-w-0 sm:col-span-2">
                            <label for="address_line" class="block text-sm font-semibold text-ink">Street address <span class="font-normal text-muted">(required)</span></label>
                            <input id="address_line" name="address_line" autocomplete="street-address" maxlength="255" required @error('address_line') aria-describedby="address-line-error" aria-invalid="true" @enderror value="{{ old('address_line', $store->address_line) }}" class="field-control mt-2 @error('address_line') border-rose-500 @enderror">
                            <x-input-error id="address-line-error" :messages="$errors->get('address_line')" class="mt-2" />
                        </div>
                    </div>
                </section>

                <div class="flex flex-col gap-4 rounded-[14px] border border-line bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-muted">Changes appear on your storefront after you save.</p>
                    <div class="flex flex-col-reverse gap-3 sm:flex-row">
                        <a href="{{ route('seller.dashboard') }}" class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-line bg-white px-5 text-sm font-semibold text-ink transition hover:border-brand focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Cancel</a>
                        <button type="submit" :disabled="saving || !dirty" :aria-busy="saving" class="inline-flex min-h-11 items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">
                            <span x-show="!saving">Save store settings</span>
                            <span x-cloak x-show="saving">Saving…</span>
                        </button>
                    </div>
                </div>
            </form>
            </div>
        </div>
    </div>
</x-app-layout>
