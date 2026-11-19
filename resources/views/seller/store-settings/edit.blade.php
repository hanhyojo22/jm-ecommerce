<x-app-layout :error-summary="false">
    <x-slot name="header">
        <div class="sc-page-head">
            <div>
                <h1 class="sc-title">Store settings</h1>
                <p class="sc-subtitle">{{ $store->name }}</p>
            </div>
            @if ($canManageBranches)<a href="{{ route('seller.branches.index') }}" class="seller-outline-button">All branches</a>@endif
        </div>
    </x-slot>

    <div class="page-shell">
        <div class="page-container seller-workspace-page">
            @if (session('status') || $canUndo)
                <div class="sc-notice mb-5" data-tone="success" role="status">
                    <p class="font-medium">{{ session('status', 'A recent text-only save can still be undone.') }}</p>
                    @if ($canUndo)
                        <form method="POST" action="{{ route('seller.store-settings.undo', ['branch' => $store->id]) }}">
                            @csrf
                            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-success/30 bg-white px-4 text-sm font-semibold text-ink transition hover:bg-success/10 focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Undo last save</button>
                        </form>
                    @endif
                </div>
            @endif

            <div class="sc-settings-layout">
            <x-marketplace.seller-settings-nav :branch="$store->id" />
            <form method="POST" action="{{ route('seller.store-settings.update', ['branch' => $store->id]) }}" enctype="multipart/form-data" class="min-w-0"
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
                <div class="sc-settings-actions">
                <p class="flex items-center gap-2 text-sm font-medium" :class="dirty ? 'text-warning-text' : 'text-muted'" role="status" aria-live="polite">
                    <span class="grid size-4 shrink-0 place-items-center rounded-full text-xs font-semibold text-white" :class="dirty ? 'bg-amber-600' : 'bg-emerald-600'" x-text="saving ? '…' : dirty ? '!' : '✓'" aria-hidden="true">{{ $errors->any() ? '!' : '✓' }}</span>
                    <span x-text="saving ? 'Saving store settings…' : dirty ? 'Unsaved changes' : 'All changes saved'">{{ $errors->any() ? 'Unsaved changes' : 'All changes saved' }}</span>
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('seller.dashboard') }}" class="seller-outline-button min-h-11">Cancel</a>
                    <button type="submit" :disabled="saving || !dirty" :aria-busy="saving" aria-label="Save store settings" class="seller-primary-button min-h-11 disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="!saving">Save changes</span>
                        <span x-cloak x-show="saving">Saving…</span>
                    </button>
                </div>
                </div>
                <div class="min-w-0 space-y-6">
                <section class="seller-form-card min-w-0 p-5 sm:p-6" aria-labelledby="store-profile-heading">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="store-profile-heading" class="sc-card-title">Store details</h2>
                        <a href="{{ route('catalog.store', $store->slug) }}" target="_blank" rel="noopener noreferrer" class="text-sm font-medium text-brand underline-offset-4 hover:underline">Visit storefront</a>
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

                        <dl class="grid gap-4 border-t border-line pt-5 sm:grid-cols-2">
                            <div><dt class="text-xs text-muted">Store name</dt><dd class="mt-2 text-sm font-medium text-ink">{{ $store->name }}</dd></div>
                            <div class="min-w-0"><dt class="text-xs text-muted">Store URL</dt><dd class="mt-2 break-all text-sm text-ink">/stores/{{ $store->slug }}</dd></div>
                        </dl>
                        <div class="min-w-0">
                            <label for="description" class="block text-sm font-semibold text-ink">Store description</label>
                            <textarea id="description" name="description" x-model="description" rows="3" maxlength="2000" aria-describedby="description-count @error('description') description-error @enderror" @error('description') aria-invalid="true" @enderror @input="descriptionLength = Array.from($event.target.value).length" class="field-control mt-2 resize-y text-base lg:text-sm @error('description') border-rose-500 @enderror">{{ old('description', $store->description) }}</textarea>
                            <div class="mt-2 flex justify-end">
                                <p id="description-count" class="text-xs text-muted"><span x-text="descriptionLength">{{ mb_strlen(old('description', $store->description) ?? '') }}</span> / 2,000 characters</p>
                            </div>
                            <x-input-error id="description-error" :messages="$errors->get('description')" class="mt-2" />
                        </div>
                    </div>
                </section>

                <section class="seller-form-card min-w-0 p-5 sm:p-6" aria-labelledby="contact-details-heading">
                    <h2 id="contact-details-heading" class="sc-card-title">Contact &amp; location</h2>

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
                        @if ($store->status !== 'suspended' && $canManageBranches)
                            <div class="min-w-0">
                                <label for="status" class="block text-sm font-semibold text-ink">Branch status</label>
                                <select id="status" name="status" class="field-control mt-2 text-base lg:text-sm">
                                    <option value="active" @selected(old('status', $store->status) === 'active')>Open: shown to customers</option>
                                    <option value="inactive" @selected(old('status', $store->status) === 'inactive')>Closed: hidden from customers</option>
                                </select>
                                <x-input-error :messages="$errors->get('status')" class="mt-2" />
                            </div>
                        @endif
                        <div class="min-w-0 max-w-3xl sm:col-span-2">
                            <label for="address_line" class="block text-sm font-semibold text-ink">Street address <span class="font-normal text-muted">(required)</span></label>
                            <input id="address_line" name="address_line" x-model="addressLine" autocomplete="street-address" maxlength="255" required @error('address_line') aria-describedby="address-line-error" aria-invalid="true" @enderror value="{{ old('address_line', $store->address_line) }}" class="field-control mt-2 text-base lg:text-sm @error('address_line') border-rose-500 @enderror">
                            <x-input-error id="address-line-error" :messages="$errors->get('address_line')" class="mt-2" />
                        </div>
                    </div>
                </section>

                @php
                    $shippingFields = [
                        'region' => ['label' => 'Region', 'autocomplete' => 'address-level1', 'maxlength' => 100],
                        'province' => ['label' => 'Province', 'autocomplete' => 'address-level1', 'maxlength' => 100],
                        'barangay' => ['label' => 'Barangay', 'autocomplete' => 'address-level3', 'maxlength' => 100],
                        'postal_code' => ['label' => 'Postal code', 'autocomplete' => 'postal-code', 'maxlength' => 4, 'inputmode' => 'numeric'],
                        'pickup_address' => ['label' => 'Pickup address', 'maxlength' => 255, 'wide' => true, 'help' => 'Where couriers collect your orders.'],
                        'return_address' => ['label' => 'Return address', 'maxlength' => 255, 'wide' => true, 'help' => 'Where buyer returns are sent.'],
                        'processing_days' => ['label' => 'Processing days', 'type' => 'number', 'min' => 1, 'max' => 30, 'help' => 'Business days before you hand orders to the courier.'],
                        'fulfillment_methods' => ['label' => 'Fulfillment methods', 'maxlength' => 120, 'help' => 'For example: courier pickup, drop-off'],
                        'delivery_areas' => ['label' => 'Supported delivery areas', 'maxlength' => 1000, 'wide' => true, 'help' => 'For example: Metro Manila, Luzon'],
                    ];
                @endphp
                <details class="seller-form-card sc-settings-disclosure" @if ($errors->hasAny(array_keys($shippingFields))) open @endif>
                    <summary id="shipping-details-heading">Pickup &amp; shipping<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
                    <div class="sc-disclosure-body">
                    <p class="mt-0.5 text-sm text-muted">Only you and JMMart see your pickup and return addresses.</p>
                    <div class="mt-5 grid min-w-0 max-w-3xl gap-4 sm:grid-cols-2">
                        @foreach ($shippingFields as $name => $field)
                            <div @class(['min-w-0', 'sm:col-span-2' => $field['wide'] ?? false])>
                                <label for="{{ $name }}" class="block text-sm font-semibold text-ink">{{ $field['label'] }}</label>
                                <input id="{{ $name }}" name="{{ $name }}" type="{{ $field['type'] ?? 'text' }}" value="{{ old($name, $store->{$name}) }}"
                                    @isset($field['autocomplete']) autocomplete="{{ $field['autocomplete'] }}" @endisset
                                    @isset($field['maxlength']) maxlength="{{ $field['maxlength'] }}" @endisset
                                    @isset($field['inputmode']) inputmode="{{ $field['inputmode'] }}" @endisset
                                    @isset($field['min']) min="{{ $field['min'] }}" max="{{ $field['max'] }}" @endisset
                                    @isset($field['help']) aria-describedby="{{ $name }}-help" @endisset
                                    @error($name) aria-invalid="true" @enderror
                                    class="field-control mt-2 text-base lg:text-sm @error($name) border-rose-500 @enderror">
                                @isset($field['help'])<p id="{{ $name }}-help" class="mt-1.5 text-xs text-muted">{{ $field['help'] }}</p>@endisset
                                <x-input-error :messages="$errors->get($name)" class="mt-2" />
                            </div>
                        @endforeach
                    </div>
                    </div>
                </details>

                <details class="seller-form-card sc-settings-disclosure" @if ($errors->hasAny(['latitude', 'longitude', 'delivery_radius_km'])) open @endif
                    x-data="{ locating: false, locationError: '', useCurrentLocation() { if (! navigator.geolocation) { this.locationError = 'This browser cannot share its location. Enter the coordinates instead.'; return; } this.locating = true; this.locationError = ''; navigator.geolocation.getCurrentPosition((position) => { this.$refs.latitude.value = position.coords.latitude.toFixed(7); this.$refs.longitude.value = position.coords.longitude.toFixed(7); this.locating = false; dirty = true; }, () => { this.locating = false; this.locationError = 'We could not get your location. Allow location access or enter the coordinates instead.'; }, { enableHighAccuracy: true, timeout: 15000 }); } }">
                    <summary id="map-location-heading">Map location<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
                    <div class="sc-disclosure-body">
                    <p class="mt-0.5 text-sm text-muted">Buyers see this pin and your delivery radius when the JMMart app suggests nearby stores. Leave it empty to stay out of nearby suggestions.</p>
                    <div class="mt-5 grid min-w-0 max-w-3xl gap-4 sm:grid-cols-3">
                        <div class="min-w-0">
                            <label for="latitude" class="block text-sm font-semibold text-ink">Latitude</label>
                            <input id="latitude" name="latitude" x-ref="latitude" type="number" step="any" min="-90" max="90" inputmode="decimal" value="{{ old('latitude', $store->latitude) }}" @error('latitude') aria-invalid="true" @enderror class="field-control mt-2 text-base lg:text-sm @error('latitude') border-rose-500 @enderror">
                            <x-input-error :messages="$errors->get('latitude')" class="mt-2" />
                        </div>
                        <div class="min-w-0">
                            <label for="longitude" class="block text-sm font-semibold text-ink">Longitude</label>
                            <input id="longitude" name="longitude" x-ref="longitude" type="number" step="any" min="-180" max="180" inputmode="decimal" value="{{ old('longitude', $store->longitude) }}" @error('longitude') aria-invalid="true" @enderror class="field-control mt-2 text-base lg:text-sm @error('longitude') border-rose-500 @enderror">
                            <x-input-error :messages="$errors->get('longitude')" class="mt-2" />
                        </div>
                        <div class="min-w-0">
                            <label for="delivery_radius_km" class="block text-sm font-semibold text-ink">Delivery radius (km)</label>
                            <input id="delivery_radius_km" name="delivery_radius_km" type="number" min="1" max="500" inputmode="numeric" value="{{ old('delivery_radius_km', $store->delivery_radius_km) }}" @error('delivery_radius_km') aria-invalid="true" @enderror class="field-control mt-2 text-base lg:text-sm @error('delivery_radius_km') border-rose-500 @enderror">
                            <x-input-error :messages="$errors->get('delivery_radius_km')" class="mt-2" />
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <button type="button" class="seller-outline-button min-h-11" @click="useCurrentLocation()" :disabled="locating" :aria-busy="locating">
                            <span x-show="!locating">Use my current location</span>
                            <span x-cloak x-show="locating">Finding location…</span>
                        </button>
                        <p x-cloak x-show="locationError" x-text="locationError" role="alert" class="text-sm text-rose-700"></p>
                    </div>
                    </div>
                </details>


                </div>
            </form>
            </div>

        </div>
    </div>
</x-app-layout>
