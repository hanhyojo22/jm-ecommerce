<x-app-layout>
    <x-slot name="header">
        <div class="sc-page-head">
            <div>
                <h1 class="sc-title">Store settings</h1>            </div>
        </div>
    </x-slot>

    <div class="page-shell">
        <div class="page-container seller-workspace-page">
            @if (session('status') || $canUndo)
                <div class="sc-notice mb-5" data-tone="success" role="status">
                    <p class="font-medium">{{ session('status', 'A recent text-only save can still be undone.') }}</p>
                    @if ($canUndo)
                        <form method="POST" action="{{ route('seller.store-settings.undo') }}">
                            @csrf
                            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-success/30 bg-white px-4 text-sm font-semibold text-ink transition hover:bg-success/10 focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Undo last save</button>
                        </form>
                    @endif
                </div>
            @endif

            <form method="POST" action="{{ route('seller.store-settings.update') }}" enctype="multipart/form-data" class="min-w-0"
                x-data="{ dirty: {{ $errors->any() ? 'true' : 'false' }}, saving: false, logoPreview: null, logoName: '', removeLogo: {{ old('remove_logo') ? 'true' : 'false' }}, description: @js(old('description', $store->description) ?? ''), phone: @js(old('phone', $store->phone) ?? ''), city: @js(old('city', $store->city) ?? ''), addressLine: @js(old('address_line', $store->address_line) ?? ''), descriptionLength: {{ mb_strlen(old('description', $store->description) ?? '') }} }"
                x-init="window.addEventListener('beforeunload', event => { if (dirty && !saving) { event.preventDefault(); event.returnValue = ''; } })"
                @input="dirty = true" @change="dirty = true"
                @submit="if (saving) { $event.preventDefault(); } else { saving = true; dirty = false; }">
                @csrf
                @method('PATCH')
                @if ($errors->any())
                    <div role="alert" tabindex="-1" x-init="$nextTick(() => $el.focus())" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">We could not save your changes. Review the errors below and try again.</div>
                @endif
                <x-input-error :messages="$errors->get('settings')" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3" />
                <x-input-error :messages="$errors->get('undo')" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3" />
                <p class="mb-3 flex items-center gap-2 text-sm font-medium" :class="dirty ? 'text-warning-text' : 'text-muted'" role="status" aria-live="polite">
                    <span class="grid size-4 shrink-0 place-items-center rounded-full text-xs font-semibold text-white" :class="dirty ? 'bg-amber-600' : 'bg-emerald-600'" x-text="saving ? '…' : dirty ? '!' : '✓'" aria-hidden="true">{{ $errors->any() ? '!' : '✓' }}</span>
                    <span x-text="saving ? 'Saving store settings…' : dirty ? 'Unsaved changes' : 'All changes saved'">{{ $errors->any() ? 'Unsaved changes' : 'All changes saved' }}</span>
                </p>
                <div class="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <div class="min-w-0 space-y-5">
                <section class="seller-form-card min-w-0 p-5 sm:p-6" aria-labelledby="store-profile-heading">
                    <div class="flex items-start gap-3">
                        <span class="grid size-10 shrink-0 place-items-center rounded-md bg-slate-100 text-slate-700" aria-hidden="true">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9.5 5 4h14l2 5.5M4 10h16v10H4zM9 20v-6h6v6M3 9.5a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/></svg>
                        </span>
                        <div>
                            <h2 id="store-profile-heading" class="text-lg font-semibold text-ink">Store identity</h2>
                            <p class="mt-0.5 text-sm text-muted">Choose a recognizable logo and describe what your store offers.</p>
                        </div>
                    </div>

                    <div class="mt-5 min-w-0 max-w-3xl space-y-5">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-ink">Store logo</p>
                            <p id="logo-help" class="mt-1 text-sm text-muted">Square JPEG, PNG, or WebP. Maximum 2 MB.</p>
                            <div class="mt-3 flex min-w-0 flex-wrap items-center gap-4">
                                <div class="grid size-20 shrink-0 place-items-center overflow-hidden rounded-lg border border-line bg-white">
                                    @if ($store->logo_path)
                                        <img src="{{ Storage::disk('public')->url($store->logo_path) }}" alt="Current {{ $store->name }} logo" class="size-full object-cover" x-show="!logoPreview && !removeLogo">
                                        <span x-cloak x-show="removeLogo" class="text-center text-xs font-medium text-muted">No logo</span>
                                    @else
                                        <span class="text-3xl font-semibold text-muted" aria-hidden="true" x-show="!logoPreview">{{ mb_strtoupper(mb_substr($store->name, 0, 1)) }}</span>
                                    @endif
                                    <img x-cloak x-show="logoPreview" :src="logoPreview" alt="Selected store logo preview" class="size-full object-cover">
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-ink" x-text="logoPreview ? 'New logo preview' : removeLogo ? 'Logo will be removed' : '{{ $store->logo_path ? 'Current logo' : 'No logo uploaded yet' }}'">{{ $store->logo_path ? 'Current logo' : 'No logo uploaded yet' }}</p>
                                    <input id="logo" x-ref="logoInput" name="logo" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="logo-help @error('logo') logo-error @enderror" @error('logo') aria-invalid="true" @enderror
                                        @change="if (logoPreview) URL.revokeObjectURL(logoPreview); logoPreview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null; logoName = $event.target.files[0]?.name || ''; if (logoPreview) removeLogo = false"
                                        class="peer sr-only">
                                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1">
                                        <label for="logo" class="inline-flex min-h-11 cursor-pointer items-center justify-center rounded-lg border border-brand/50 bg-white px-4 text-sm font-semibold text-brand transition hover:bg-blue-50 peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand @error('logo') border-rose-500 @enderror">Change logo</label>
                                        @if ($store->logo_path)
                                            <label class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-rose-600">
                                                <input x-model="removeLogo" type="checkbox" name="remove_logo" value="1" @checked(old('remove_logo')) aria-label="Remove current logo on save" aria-describedby="remove-logo-help @error('remove_logo') remove-logo-error @enderror" @error('remove_logo') aria-invalid="true" @enderror
                                                    @change="if ($event.target.checked) { $refs.logoInput.value = ''; if (logoPreview) URL.revokeObjectURL(logoPreview); logoPreview = null; logoName = ''; }"
                                                    class="size-4 rounded-md border-rose-300 text-rose-600 focus:ring-rose-500 @error('remove_logo') border-rose-500 @enderror">
                                                Remove logo
                                            </label>
                                        @endif
                                    </div>
                                    <p x-cloak x-show="logoName" x-text="logoName" class="mt-2 truncate text-xs text-muted"></p>
                                </div>
                            </div>
                            <x-input-error id="logo-error" :messages="$errors->get('logo')" class="mt-2" />
                            @if ($store->logo_path)
                                <p id="remove-logo-help" class="text-xs text-muted">This removes the current image only after you save. It cannot be restored here.</p>
                                <x-input-error id="remove-logo-error" :messages="$errors->get('remove_logo')" class="mt-2" />
                            @endif
                        </div>

                        <div class="min-w-0 border-t border-line pt-5">
                            <label for="description" class="block text-sm font-semibold text-ink">Store description</label>
                            <p id="description-help" class="mt-1 text-sm text-muted">Tell shoppers what you sell and what makes your store distinct.</p>
                            <textarea id="description" name="description" x-model="description" rows="3" maxlength="2000" aria-describedby="description-help description-count @error('description') description-error @enderror" @error('description') aria-invalid="true" @enderror @input="descriptionLength = Array.from($event.target.value).length" class="field-control mt-2 resize-y text-base lg:text-sm @error('description') border-rose-500 @enderror">{{ old('description', $store->description) }}</textarea>
                            <div class="mt-2 flex justify-end">
                                <p id="description-count" class="text-xs text-muted"><span x-text="descriptionLength">{{ mb_strlen(old('description', $store->description) ?? '') }}</span> / 2,000 characters</p>
                            </div>
                            <x-input-error id="description-error" :messages="$errors->get('description')" class="mt-2" />
                        </div>
                    </div>
                </section>

                <section class="seller-form-card min-w-0 p-5 sm:p-6" aria-labelledby="contact-details-heading">
                    <div class="flex items-start gap-3">
                        <span class="grid size-10 shrink-0 place-items-center rounded-md bg-slate-100 text-slate-700" aria-hidden="true">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-5.5 7-12a7 7 0 0 0-14 0c0 6.5 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/></svg>
                        </span>
                        <div>
                            <h2 id="contact-details-heading" class="text-lg font-semibold text-ink">Contact &amp; location</h2>
                            <p class="mt-0.5 text-sm text-muted">Keep your saved contact and address details current.</p>
                        </div>
                    </div>

                    <div class="mt-5 grid min-w-0 max-w-3xl gap-4 sm:grid-cols-2">
                        <div class="min-w-0">
                            <label for="phone" class="block text-sm font-semibold text-ink">Phone <span class="font-normal text-muted">(required)</span></label>
                            <input id="phone" name="phone" x-model="phone" type="tel" autocomplete="tel" maxlength="30" required @error('phone') aria-describedby="phone-error" aria-invalid="true" @enderror value="{{ old('phone', $store->phone) }}" class="field-control mt-2 text-base lg:text-sm @error('phone') border-rose-500 @enderror">
                            <x-input-error id="phone-error" :messages="$errors->get('phone')" class="mt-2" />
                        </div>
                        <div class="min-w-0">
                            <label for="city" class="block text-sm font-semibold text-ink">City <span class="font-normal text-muted">(required)</span></label>
                            <input id="city" name="city" x-model="city" autocomplete="address-level2" maxlength="100" required @error('city') aria-describedby="city-error" aria-invalid="true" @enderror value="{{ old('city', $store->city) }}" class="field-control mt-2 text-base lg:text-sm @error('city') border-rose-500 @enderror">
                            <x-input-error id="city-error" :messages="$errors->get('city')" class="mt-2" />
                        </div>
                        <div class="min-w-0 max-w-3xl sm:col-span-2">
                            <label for="address_line" class="block text-sm font-semibold text-ink">Street address <span class="font-normal text-muted">(required)</span></label>
                            <input id="address_line" name="address_line" x-model="addressLine" autocomplete="street-address" maxlength="255" required @error('address_line') aria-describedby="address-line-error" aria-invalid="true" @enderror value="{{ old('address_line', $store->address_line) }}" class="field-control mt-2 text-base lg:text-sm @error('address_line') border-rose-500 @enderror">
                            <x-input-error id="address-line-error" :messages="$errors->get('address_line')" class="mt-2" />
                        </div>
                    </div>
                </section>

                <div class="sticky bottom-0 z-10 -mx-1 flex flex-wrap items-center justify-end gap-2 border-t border-line bg-[#f5f7fa]/95 px-1 py-3 backdrop-blur">
                    <p class="mr-auto text-sm text-muted" x-show="dirty" x-cloak>You have unsaved changes.</p>
                    <a href="{{ route('seller.dashboard') }}" class="seller-outline-button min-h-11">Cancel</a>
                    <button type="submit" :disabled="saving || !dirty" :aria-busy="saving" aria-label="Save store settings" class="seller-primary-button min-h-11 disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="!saving">Save changes</span>
                        <span x-cloak x-show="saving">Saving…</span>
                    </button>
                </div>
                </div>
                <div class="min-w-0">
                    <section class="seller-form-card min-w-0 overflow-hidden" aria-labelledby="store-preview-heading">
                        <div class="flex items-center justify-between gap-2 px-3 py-3">
                            <h2 id="store-preview-heading" class="flex min-w-0 items-center gap-1.5 text-base font-semibold leading-5 text-ink">
                                <span class="grid size-6 shrink-0 place-items-center rounded-md bg-slate-100 text-slate-600" aria-hidden="true"><svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></span>
                                Store details
                            </h2>
                            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold {{ $store->status === 'active' ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-muted' }}">
                                <span class="size-1.5 rounded-full {{ $store->status === 'active' ? 'bg-emerald-600' : 'bg-slate-500' }}" aria-hidden="true"></span>{{ ucfirst($store->status) }}
                            </span>
                        </div>
                        <div class="relative overflow-hidden bg-ink px-5 py-6 text-white">
                            <div class="relative flex min-w-0 items-center gap-4">
                                @if ($store->logo_path)
                                    <img src="{{ Storage::disk('public')->url($store->logo_path) }}" alt="{{ $store->name }} logo" x-show="!logoPreview && !removeLogo" class="size-16 shrink-0 rounded-lg border-2 border-white/60 bg-white object-cover">
                                    <div x-cloak x-show="removeLogo" class="grid size-16 shrink-0 place-items-center rounded-lg bg-white text-2xl font-semibold text-brand" aria-hidden="true">{{ mb_strtoupper(mb_substr($store->name, 0, 1)) }}</div>
                                @else
                                    <div x-show="!logoPreview" class="grid size-16 shrink-0 place-items-center rounded-lg bg-white text-2xl font-semibold text-brand" aria-hidden="true">{{ mb_strtoupper(mb_substr($store->name, 0, 1)) }}</div>
                                @endif
                                <img x-cloak x-show="logoPreview" :src="logoPreview" alt="Selected storefront logo preview" class="size-16 shrink-0 rounded-lg border-2 border-white/60 bg-white object-cover">
                                <div class="min-w-0">
                                    <p class="truncate text-xl font-semibold">{{ $store->name }}</p>
                                    <p class="mt-0.5 truncate text-xs text-slate-200">/stores/{{ $store->slug }}</p>
                                    <a href="{{ route('catalog.store', $store->slug) }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex min-h-11 items-center gap-1.5 rounded-lg bg-white px-3 text-sm font-semibold text-ink transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-ink">
                                        Visit storefront
                                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9"/><path d="M18 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5"/></svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <dl class="divide-y divide-line px-4 py-2 text-sm sm:px-5">
                            <div class="grid min-w-0 grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] gap-3 py-3"><dt class="flex items-center gap-2 text-muted"><svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 3h9l4 4v14H6zM15 3v5h4M9 12h7M9 16h7"/></svg>Description</dt><dd class="min-w-0 break-words font-medium text-ink" x-text="description.trim() || 'Not added yet'">{{ old('description', $store->description) ?: 'Not added yet' }}</dd></div>
                            <div class="grid min-w-0 grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] gap-3 py-3"><dt class="flex items-center gap-2 text-muted"><svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 3h4l2 5-2.5 2.5a17 17 0 0 0 5 5L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 5a2 2 0 0 1 2-2Z"/></svg>Phone</dt><dd class="min-w-0 break-words font-medium text-ink" x-text="phone.trim() || '—'">{{ old('phone', $store->phone) ?: '—' }}</dd></div>
                            <div class="grid min-w-0 grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] gap-3 py-3"><dt class="flex items-center gap-2 text-muted"><svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 21s7-5.5 7-12a7 7 0 0 0-14 0c0 6.5 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/></svg>City</dt><dd class="min-w-0 break-words font-medium text-ink" x-text="city.trim() || '—'">{{ old('city', $store->city) ?: '—' }}</dd></div>
                            <div class="grid min-w-0 grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] gap-3 py-3"><dt class="flex items-center gap-2 text-muted"><svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-7h6v7"/></svg>Address</dt><dd class="min-w-0 break-words font-medium text-ink" x-text="addressLine.trim() || '—'">{{ old('address_line', $store->address_line) ?: '—' }}</dd></div>
                        </dl>
                        <p x-cloak x-show="dirty" class="border-t border-line bg-blue-50/60 px-4 py-3 text-xs font-medium text-brand">Previewing unsaved changes. Save to update your store.</p>
                        <p class="border-t border-line px-4 py-3 text-xs leading-5 text-muted">Your logo, name, URL, description, and city appear publicly. Phone and street address stay in your saved store details.</p>
                    </section>
                </div>
                </div>
            </form>

            @include('seller.store-settings._payment-methods')
        </div>
    </div>
</x-app-layout>
