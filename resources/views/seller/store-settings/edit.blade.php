<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-[28px] font-bold leading-[1.35] tracking-[-0.02em] text-ink">Store settings</h1>
            <p class="mt-1 text-sm text-muted">Shape how shoppers see and contact your store.</p>
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

            <section class="overflow-hidden rounded-2xl border border-line bg-white shadow-sm" aria-labelledby="store-identity-heading">
                <div class="bg-ink px-5 py-6 text-white sm:px-8 sm:py-8">
                    <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex min-w-0 items-center gap-4">
                            @if ($store->logo_path)
                                <img src="{{ Storage::disk('public')->url($store->logo_path) }}" alt="" class="size-16 shrink-0 rounded-2xl border-2 border-white/30 bg-white object-cover sm:size-20">
                            @else
                                <div class="grid size-16 shrink-0 place-items-center rounded-2xl bg-white text-2xl font-bold text-brand sm:size-20" aria-hidden="true">{{ mb_strtoupper(mb_substr($store->name, 0, 1)) }}</div>
                            @endif
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-blue-200">Your storefront</p>
                                <h2 id="store-identity-heading" class="mt-1 truncate text-xl font-bold text-white sm:text-2xl">{{ $store->name }}</h2>
                                <p class="mt-1 truncate text-sm text-slate-300">/stores/{{ $store->slug }}</p>
                            </div>
                        </div>
                        <a href="{{ route('catalog.store', $store->slug) }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 self-start rounded-lg bg-white px-4 text-sm font-semibold text-ink transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-ink sm:self-auto">
                            Visit storefront
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9"/><path d="M18 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5"/></svg>
                        </a>
                    </div>
                </div>
                <p class="px-5 py-3 text-sm text-muted sm:px-8">Your store name and URL are set. Update the details below to keep your storefront current.</p>
            </section>

            <form method="POST" action="{{ route('seller.store-settings.update') }}" enctype="multipart/form-data" class="mt-6 space-y-5" x-data="{ logoPreview: null, descriptionLength: {{ mb_strlen(old('description', $store->description) ?? '') }} }">
                @csrf
                @method('PATCH')
                <x-input-error :messages="$errors->get('settings')" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3" />
                <x-input-error :messages="$errors->get('undo')" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3" />

                <section class="grid min-w-0 gap-6 rounded-2xl border border-line bg-white p-5 shadow-sm md:grid-cols-[minmax(0,12rem)_minmax(0,1fr)] md:gap-8 md:p-7" aria-labelledby="store-profile-heading">
                    <div>
                        <span class="grid size-10 place-items-center rounded-xl bg-blue-50 text-brand" aria-hidden="true">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9.5 5 4h14l2 5.5M4 10h16v10H4zM9 20v-6h6v6M3 9.5a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/></svg>
                        </span>
                        <h2 id="store-profile-heading" class="mt-4 text-lg font-semibold text-ink">Store profile</h2>
                        <p class="mt-2 text-sm leading-6 text-muted">Give shoppers a familiar image and a clear introduction.</p>
                    </div>

                    <div class="min-w-0 space-y-7">
                        <div>
                            <label for="logo" class="block text-sm font-semibold text-ink">Store logo</label>
                            <p id="logo-help" class="mt-1 text-sm text-muted">A square JPEG, PNG, or WebP image, up to 2 MB.</p>
                            <div class="mt-4 flex min-w-0 flex-col gap-4 rounded-xl border border-dashed border-line bg-canvas p-4 sm:flex-row sm:items-center">
                                <div class="grid size-24 shrink-0 place-items-center overflow-hidden rounded-xl border border-line bg-white">
                                    @if ($store->logo_path)
                                        <img src="{{ Storage::disk('public')->url($store->logo_path) }}" alt="Current {{ $store->name }} logo" class="size-full object-cover" x-show="!logoPreview">
                                    @else
                                        <span class="text-3xl font-bold text-muted" aria-hidden="true" x-show="!logoPreview">{{ mb_strtoupper(mb_substr($store->name, 0, 1)) }}</span>
                                    @endif
                                    <img x-cloak x-show="logoPreview" :src="logoPreview" alt="Selected store logo preview" class="size-full object-cover">
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-ink" x-text="logoPreview ? 'New logo selected' : '{{ $store->logo_path ? 'Current logo' : 'No logo uploaded yet' }}'">{{ $store->logo_path ? 'Current logo' : 'No logo uploaded yet' }}</p>
                                    <input id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="logo-help @error('logo') logo-error @enderror" @error('logo') aria-invalid="true" @enderror
                                        @change="if (logoPreview) URL.revokeObjectURL(logoPreview); logoPreview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null; if (logoPreview && $refs.removeLogo) $refs.removeLogo.checked = false"
                                        class="mt-3 block min-h-11 w-full text-sm text-muted file:mr-3 file:min-h-11 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:font-semibold file:text-brand hover:file:bg-blue-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
                                </div>
                            </div>
                            <x-input-error id="logo-error" :messages="$errors->get('logo')" class="mt-2" />
                            @if ($store->logo_path)
                                <label class="mt-3 inline-flex min-h-11 items-center gap-3 text-sm text-ink">
                                    <input x-ref="removeLogo" type="checkbox" name="remove_logo" value="1" @checked(old('remove_logo')) @error('remove_logo') aria-describedby="remove-logo-error" aria-invalid="true" @enderror class="size-4 rounded border-line text-brand focus:ring-brand">
                                    Remove current logo when saved
                                </label>
                                <x-input-error id="remove-logo-error" :messages="$errors->get('remove_logo')" class="mt-2" />
                            @endif
                            <p class="mt-1 text-xs text-muted">Logo changes cannot be undone here.</p>
                        </div>

                        <div class="border-t border-line pt-6">
                            <label for="description" class="block text-sm font-semibold text-ink">Store description</label>
                            <p id="description-help" class="mt-1 text-sm text-muted">Tell shoppers what you sell and what makes your store distinct.</p>
                            <textarea id="description" name="description" rows="5" maxlength="2000" aria-describedby="description-help description-count @error('description') description-error @enderror" @error('description') aria-invalid="true" @enderror @input="descriptionLength = Array.from($event.target.value).length" class="field-control mt-3 resize-y">{{ old('description', $store->description) }}</textarea>
                            <div class="mt-2 flex justify-end">
                                <p id="description-count" class="text-xs text-muted"><span x-text="descriptionLength">{{ mb_strlen(old('description', $store->description) ?? '') }}</span> / 2,000 characters</p>
                            </div>
                            <x-input-error id="description-error" :messages="$errors->get('description')" class="mt-2" />
                        </div>
                    </div>
                </section>

                <section class="grid min-w-0 gap-6 rounded-2xl border border-line bg-white p-5 shadow-sm md:grid-cols-[minmax(0,12rem)_minmax(0,1fr)] md:gap-8 md:p-7" aria-labelledby="contact-details-heading">
                    <div>
                        <span class="grid size-10 place-items-center rounded-xl bg-blue-50 text-brand" aria-hidden="true">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-5.5 7-12a7 7 0 0 0-14 0c0 6.5 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/></svg>
                        </span>
                        <h2 id="contact-details-heading" class="mt-4 text-lg font-semibold text-ink">Contact and location</h2>
                        <p class="mt-2 text-sm leading-6 text-muted">Keep your contact details accurate for order follow-up.</p>
                    </div>

                    <div class="grid min-w-0 gap-5 sm:grid-cols-2">
                        <div class="min-w-0">
                            <label for="phone" class="block text-sm font-semibold text-ink">Phone <span class="font-normal text-muted">(required)</span></label>
                            <input id="phone" name="phone" type="tel" autocomplete="tel" maxlength="30" required @error('phone') aria-describedby="phone-error" aria-invalid="true" @enderror value="{{ old('phone', $store->phone) }}" class="field-control mt-2">
                            <x-input-error id="phone-error" :messages="$errors->get('phone')" class="mt-2" />
                        </div>
                        <div class="min-w-0">
                            <label for="city" class="block text-sm font-semibold text-ink">City <span class="font-normal text-muted">(required)</span></label>
                            <input id="city" name="city" autocomplete="address-level2" maxlength="100" required @error('city') aria-describedby="city-error" aria-invalid="true" @enderror value="{{ old('city', $store->city) }}" class="field-control mt-2">
                            <x-input-error id="city-error" :messages="$errors->get('city')" class="mt-2" />
                        </div>
                        <div class="min-w-0 sm:col-span-2">
                            <label for="address_line" class="block text-sm font-semibold text-ink">Street address <span class="font-normal text-muted">(required)</span></label>
                            <input id="address_line" name="address_line" autocomplete="street-address" maxlength="255" required @error('address_line') aria-describedby="address-line-error" aria-invalid="true" @enderror value="{{ old('address_line', $store->address_line) }}" class="field-control mt-2">
                            <x-input-error id="address-line-error" :messages="$errors->get('address_line')" class="mt-2" />
                        </div>
                    </div>
                </section>

                <div class="flex flex-col gap-4 rounded-2xl border border-line bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-muted">Changes appear on your storefront after you save.</p>
                    <div class="flex flex-col-reverse gap-3 sm:flex-row">
                        <a href="{{ route('seller.dashboard') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-line bg-white px-5 text-sm font-semibold text-ink transition hover:border-brand focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Cancel</a>
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Save store settings</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
