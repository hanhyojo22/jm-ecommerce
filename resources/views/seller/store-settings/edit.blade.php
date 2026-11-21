@php
    $storeTone = $store->status === 'active' ? 'success' : ($store->status === 'suspended' ? 'danger' : 'neutral');
    $storeStatusLabel = ['active' => 'Open', 'inactive' => 'Closed', 'suspended' => 'Suspended by Everyday Pharmacy'][$store->status] ?? $store->status;
    $shippingNote = collect([$store->barangay, $store->province])->filter()->implode(', ');
    if (! $shippingNote && $store->processing_days) {
        $shippingNote = $store->processing_days.' '.Str::plural('day', $store->processing_days).' processing';
    }
@endphp

<x-app-layout :error-summary="false">
    <x-slot name="header">
        <div class="sc-page-head">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="sc-title">Store settings</h1>
                    <span class="sc-badge" data-tone="{{ $storeTone }}">{{ $storeStatusLabel }}</span>
                </div>
                <p class="sc-subtitle">{{ $store->name }}</p>
            </div>
            @if ($canManageBranches)
                <a href="{{ route('seller.branches.index') }}" class="seller-outline-button">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                    All branches
                </a>
            @endif
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
                    <div role="alert" tabindex="-1" x-init="$nextTick(() => $el.focus())" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">We could not save your changes. Review the errors below and try again.</div>
                @endif
                <x-input-error :messages="$errors->get('settings')" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3" />
                <x-input-error :messages="$errors->get('undo')" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3" />
                <div class="sc-settings-actions is-sticky" :class="dirty && 'is-dirty'">
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
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 id="store-profile-heading" class="sc-card-title">Store details</h2>
                            <p class="mt-0.5 text-sm text-muted">Your logo, public name, and how customers recognize this branch.</p>
                        </div>
                        @if (config('marketplace.web_store.enabled'))
                            <a href="{{ route('catalog.store', $store->slug) }}" target="_blank" rel="noopener noreferrer" class="text-sm font-medium text-brand underline-offset-4 hover:underline">Visit storefront</a>
                        @endif
                    </div>

                    <div class="mt-5 min-w-0 max-w-3xl space-y-5">
                        <div class="sc-logo-box min-w-0">
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
                                <p id="remove-logo-help" class="mt-2 text-xs text-muted">This removes the current image only after you save. It cannot be restored here.</p>
                                <x-input-error id="remove-logo-error" :messages="$errors->get('remove_logo')" class="mt-2" />
                            @endif
                        </div>

                        <dl class="grid gap-4 border-t border-line pt-5 sm:grid-cols-2">
                            @unless ($canManageBranches)
                                <div><dt class="text-xs text-muted">Branch name</dt><dd class="mt-2 text-sm font-medium text-ink">{{ $store->name }}</dd></div>
                            @endunless
                            <div class="min-w-0"><dt class="text-xs text-muted">Store URL</dt><dd class="mt-2 break-all"><code class="font-mono text-xs text-ink">/stores/{{ $store->slug }}</code></dd></div>
                        </dl>
                        @if ($canManageBranches)
                            <div class="min-w-0">
                                <label for="name" class="block text-sm font-semibold text-ink">Branch name <span class="font-normal text-muted">(required)</span></label>
                                <input id="name" name="name" maxlength="120" required @error('name') aria-describedby="name-error" aria-invalid="true" @enderror value="{{ old('name', $store->name) }}" class="field-control mt-2 text-base lg:text-sm @error('name') border-rose-500 @enderror">
                                <p class="mt-1 text-xs text-muted">Customers see this name in the app. The store URL stays the same.</p>
                                <x-input-error id="name-error" :messages="$errors->get('name')" class="mt-2" />
                            </div>
                        @endif
                        <div class="min-w-0">
                            <label for="description" class="block text-sm font-semibold text-ink">Store description</label>
                            <textarea id="description" name="description" x-model="description" rows="3" maxlength="2000" placeholder="e.g. Neighborhood pharmacy carrying prescription medicines, vitamins, and daily health essentials." aria-describedby="description-count @error('description') description-error @enderror" @error('description') aria-invalid="true" @enderror @input="descriptionLength = Array.from($event.target.value).length" class="field-control mt-2 resize-y text-base lg:text-sm @error('description') border-rose-500 @enderror">{{ old('description', $store->description) }}</textarea>
                            <div class="mt-2 flex justify-end">
                                <p id="description-count" class="text-xs tabular-nums text-muted"><span x-text="descriptionLength">{{ mb_strlen(old('description', $store->description) ?? '') }}</span> / 2,000 characters</p>
                            </div>
                            <x-input-error id="description-error" :messages="$errors->get('description')" class="mt-2" />
                        </div>
                    </div>
                </section>

                <section class="seller-form-card min-w-0 p-5 sm:p-6" aria-labelledby="contact-details-heading">
                    <h2 id="contact-details-heading" class="sc-card-title">Contact &amp; location</h2>
                    <p class="mt-0.5 text-sm text-muted">Where customers reach the branch and whether it is open for orders.</p>

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
                    <summary id="shipping-details-heading">
                        <span class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                            <span class="inline-flex items-center gap-2.5">
                                <svg class="size-4 shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/></svg>
                                <span>Pickup &amp; shipping</span>
                            </span>
                            <span class="text-xs font-normal text-muted">{{ $shippingNote ?: 'Optional addresses & delivery details' }}</span>
                        </span>
                        <svg class="shrink-0 transition-transform" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    </summary>
                    <div class="sc-disclosure-body">
                    <p class="mt-0.5 text-sm text-muted">Only you and Everyday Pharmacy see your pickup and return addresses.</p>
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
                    @toggle="if (!$el.open && mapFullscreen) toggleFullscreen(false)"
                    x-data="{
                        locating: false,
                        mapFullscreen: false,
                        locationError: '',
                        searchQuery: '',
                        searching: false,
                        searched: false,
                        searchResults: [],
                        searchAbort: null,
                        activeIndex: -1,
                        pinLat: @js((string) old('latitude', $store->latitude ?? '')),
                        pinLng: @js((string) old('longitude', $store->longitude ?? '')),
                        radiusKm: @js((string) old('delivery_radius_km', $store->delivery_radius_km ?? '')),
                        toggleFullscreen(force = null) {
                            const next = force === null ? ! this.mapFullscreen : Boolean(force);
                            if (next === this.mapFullscreen) return;
                            this.mapFullscreen = next;
                            document.documentElement.classList.toggle('sc-map-lock', next);
                            this.$nextTick(() => this.$refs.pinMap?.dispatchEvent(new Event('branch-pin:resize')));
                        },
                        handleEscape() {
                            if (this.searchResults.length > 0 || this.searched) {
                                this.searchResults = [];
                                this.searched = false;
                                this.activeIndex = -1;
                                return;
                            }
                            if (this.mapFullscreen) {
                                this.toggleFullscreen(false);
                            }
                        },
                        destroy() {
                            document.documentElement.classList.remove('sc-map-lock');
                        },
                        get hasPin() {
                            const lat = Number.parseFloat(this.pinLat);
                            const lng = Number.parseFloat(this.pinLng);
                            return this.pinLat.trim() !== '' && this.pinLng.trim() !== '' && Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180;
                        },
                        get formattedCoords() {
                            return this.hasPin ? `${Number(this.pinLat).toFixed(5)}, ${Number(this.pinLng).toFixed(5)}` : '';
                        },
                        get googleMapsUrl() {
                            return 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(this.pinLat + ',' + this.pinLng);
                        },
                        async searchPlaces(pickFirst = false) {
                            const q = this.searchQuery.trim();
                            if (this.searchAbort) this.searchAbort.abort();
                            if (q.length < 2) {
                                this.searchResults = [];
                                this.searched = false;
                                this.searching = false;
                                this.activeIndex = -1;
                                return;
                            }
                            const controller = new AbortController();
                            this.searchAbort = controller;
                            this.searching = true;
                            this.locationError = '';
                            try {
                                const url = 'https://photon.komoot.io/api?' + new URLSearchParams({
                                    q,
                                    limit: '6',
                                    lang: 'en',
                                    bbox: '116.9,4.5,126.7,21.2',
                                });
                                const response = await fetch(url, { signal: controller.signal });
                                if (! response.ok) throw new Error('Search failed');
                                const data = await response.json();
                                const seen = new Set();
                                this.searchResults = (data.features ?? []).flatMap((f) => {
                                    const coords = f?.geometry?.coordinates;
                                    if (! Array.isArray(coords) || coords.length < 2) return [];
                                    const p = f.properties ?? {};
                                    const street = p.street ? [p.housenumber, p.street].filter(Boolean).join(' ') : null;
                                    const parts = [];
                                    for (const part of [p.name, street, p.district ?? p.locality, p.city ?? p.county, p.state]) {
                                        if (part && ! parts.includes(part)) parts.push(part);
                                    }
                                    const label = parts.join(', ');
                                    if (! label || seen.has(label)) return [];
                                    seen.add(label);
                                    return [{ title: parts[0], subtitle: parts.slice(1).join(', '), label, lat: Number(coords[1]), lng: Number(coords[0]) }];
                                });
                                this.searched = true;
                                this.activeIndex = -1;
                                if (pickFirst && this.searchResults.length > 0) {
                                    this.selectPlace(this.searchResults[0]);
                                }
                            } catch (error) {
                                if (error?.name !== 'AbortError') {
                                    this.locationError = 'We could not search for that location. Try again or tap the map instead.';
                                }
                            } finally {
                                if (this.searchAbort === controller) {
                                    this.searching = false;
                                }
                            }
                        },
                        moveSelection(delta) {
                            if (this.searchResults.length === 0) return;
                            const total = this.searchResults.length;
                            this.activeIndex = (this.activeIndex + delta + total) % total;
                        },
                        selectPlace(place) {
                            const lat = Number(place.lat).toFixed(7);
                            const lng = Number(place.lng).toFixed(7);
                            this.pinLat = lat;
                            this.pinLng = lng;
                            this.$refs.latitude.value = lat;
                            this.$refs.longitude.value = lng;
                            this.$refs.pinMap.dispatchEvent(new Event('branch-pin:moved'));
                            this.searchQuery = place.label;
                            this.searchResults = [];
                            this.searched = false;
                            this.activeIndex = -1;
                            this.locationError = '';
                            dirty = true;
                        },
                        setRadius(km) {
                            this.radiusKm = String(km);
                            this.$refs.radius.value = String(km);
                            this.$refs.pinMap.dispatchEvent(new Event('branch-pin:radius'));
                            dirty = true;
                        },
                        clearPin() {
                            this.pinLat = '';
                            this.pinLng = '';
                            this.$refs.latitude.value = '';
                            this.$refs.longitude.value = '';
                            this.searchQuery = '';
                            this.searchResults = [];
                            this.searched = false;
                            this.activeIndex = -1;
                            this.$refs.pinMap.dispatchEvent(new Event('branch-pin:cleared'));
                            dirty = true;
                        },
                        useCurrentLocation() {
                            if (! navigator.geolocation) {
                                this.locationError = 'This browser cannot share its location. Enter the coordinates instead.';
                                return;
                            }
                            this.locating = true;
                            this.locationError = '';
                            navigator.geolocation.getCurrentPosition((position) => {
                                const lat = position.coords.latitude.toFixed(7);
                                const lng = position.coords.longitude.toFixed(7);
                                this.pinLat = lat;
                                this.pinLng = lng;
                                this.$refs.latitude.value = lat;
                                this.$refs.longitude.value = lng;
                                this.$refs.pinMap.dispatchEvent(new Event('branch-pin:moved'));
                                this.locating = false;
                                dirty = true;
                            }, () => {
                                this.locating = false;
                                this.locationError = 'We could not get your location. Allow location access or enter the coordinates instead.';
                            }, { enableHighAccuracy: true, timeout: 15000 });
                        }
                    }">
                    <summary id="map-location-heading">
                        <span class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                            <span class="inline-flex items-center gap-2.5">
                                <svg class="size-4 shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 21s-7-5.7-7-11a7 7 0 1 1 14 0c0 5.3-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                                <span>Map location</span>
                            </span>
                            <span x-show="hasPin" @if (! ($store->latitude && $store->longitude)) x-cloak @endif class="sc-branch-pin is-pinned text-xs font-medium">
                                <span>On the map</span><span x-show="radiusKm" x-text="' · ' + radiusKm + ' km'"></span>
                            </span>
                            <span x-show="!hasPin" @if ($store->latitude && $store->longitude) x-cloak @endif class="sc-branch-pin is-missing text-xs font-medium">No map pin yet</span>
                        </span>
                        <svg class="shrink-0 transition-transform" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    </summary>
                    <div class="sc-disclosure-body">
                    <p class="mt-0.5 text-sm text-muted">Buyers see this pin and your delivery radius when the Everyday Pharmacy app suggests nearby stores. Leave it empty to stay out of nearby suggestions.</p>
                    <div class="sc-map-shell mt-4" :class="mapFullscreen && 'is-fullscreen'" @keydown.escape.window="if (mapFullscreen) { handleEscape(); }">
                        <div class="sc-map-toolbar" @click.outside="searchResults = []; searched = false; activeIndex = -1">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <label for="branch-location-search" class="block text-sm font-semibold text-ink">Search location</label>
                                <button type="button" @click="toggleFullscreen()"
                                    :aria-pressed="mapFullscreen.toString()"
                                    :aria-label="mapFullscreen ? 'Exit full screen map' : 'Expand map to full screen'"
                                    class="sc-map-expand-btn">
                                    <svg x-show="!mapFullscreen" class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
                                    <svg x-cloak x-show="mapFullscreen" class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 14h6v6M20 10h-6V4M14 10l7-7M10 14l-7 7"/></svg>
                                    <span x-show="!mapFullscreen">Full screen</span>
                                    <span x-cloak x-show="mapFullscreen">Exit full screen</span>
                                </button>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <div class="sc-map-search-wrap min-w-[200px] flex-1">
                                    <svg class="sc-map-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                                    <input id="branch-location-search" type="search" x-model="searchQuery"
                                        placeholder="Search street, barangay, landmark, or city…"
                                        autocomplete="off"
                                        @input.stop.debounce.300ms="searchPlaces(false)"
                                        @change.stop
                                        @keydown.arrow-down.prevent="moveSelection(1)"
                                        @keydown.arrow-up.prevent="moveSelection(-1)"
                                        @keydown.enter.prevent="searchResults.length > 0 ? selectPlace(searchResults[activeIndex >= 0 ? activeIndex : 0]) : searchPlaces(true)"
                                        @keydown.escape.stop="if (searchResults.length > 0 || searched || mapFullscreen) { $event.preventDefault(); handleEscape(); }"
                                        class="field-control w-full text-base lg:text-sm">
                                </div>
                                <button type="button" class="seller-outline-button min-h-11 shrink-0"
                                    @click="searchPlaces(true)" :disabled="searching" :aria-busy="searching">
                                    <span x-show="!searching">Find on map</span>
                                    <span x-cloak x-show="searching">Searching…</span>
                                </button>
                                <button type="button" class="seller-outline-button min-h-11 shrink-0"
                                    @click="useCurrentLocation()" :disabled="locating" :aria-busy="locating">
                                    <svg class="size-4 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3"/></svg>
                                    <span x-show="!locating">Use my current location</span>
                                    <span x-cloak x-show="locating">Finding location…</span>
                                </button>
                            </div>
                            <ul x-cloak x-show="searchResults.length > 0" role="listbox" aria-label="Matching locations"
                                class="sc-map-results">
                                <template x-for="(place, index) in searchResults" :key="index">
                                    <li role="none">
                                        <button type="button" role="option"
                                            :aria-selected="(activeIndex === index).toString()"
                                            :class="activeIndex === index && 'is-active'"
                                            @click="selectPlace(place)"
                                            class="sc-map-option">
                                            <svg class="size-4 shrink-0 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 21s-7-5.7-7-11a7 7 0 1 1 14 0c0 5.3-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                                            <span class="min-w-0 truncate"><span class="font-medium text-ink" x-text="place.title"></span><span x-show="place.subtitle" class="text-muted" x-text="', ' + place.subtitle"></span></span>
                                        </button>
                                    </li>
                                </template>
                            </ul>
                            <p x-cloak x-show="searched && !searching && searchResults.length === 0" role="status" class="mt-2 text-xs text-muted">No matching places found. Try a nearby street, barangay, or city.</p>
                            <p x-cloak x-show="locationError" x-text="locationError" role="alert" class="mt-2 text-sm text-rose-700"></p>
                        </div>

                        {{-- isolate keeps Leaflet's stacked layers under the page's sticky bars. --}}
                        <div data-branch-pin-map data-latitude-input="latitude" data-longitude-input="longitude" data-radius-input="delivery_radius_km" x-ref="pinMap" role="region" aria-label="Branch location map" aria-describedby="branch-pin-map-help" class="sc-map-canvas isolate h-80 w-full bg-surface sm:h-[420px]"></div>

                        <div class="sc-map-status">
                            <p class="min-w-0 text-xs text-muted">
                                <span x-show="hasPin" class="inline-flex flex-wrap items-center gap-1.5 font-medium text-ink">
                                    <span class="sc-branch-pin is-pinned">Pinned at <span class="font-mono tabular-nums" x-text="formattedCoords"></span></span>
                                    <span x-show="radiusKm" class="text-muted" x-text="'· ' + radiusKm + ' km radius'"></span>
                                </span>
                                <span x-show="!hasPin">No pin placed yet — search above or click the map.</span>
                            </p>
                            <div x-cloak x-show="mapFullscreen" class="sc-radius-presets !mt-0" role="group" aria-label="Delivery radius presets">
                                @foreach ([3, 5, 10, 15, 25] as $km)
                                    <button type="button" @click="setRadius({{ $km }})" :class="String(radiusKm) === '{{ $km }}' && 'is-active'" class="sc-radius-preset">{{ $km }} km</button>
                                @endforeach
                            </div>
                            <div x-cloak x-show="hasPin" class="flex shrink-0 flex-wrap items-center gap-3">
                                <a :href="googleMapsUrl" target="_blank" rel="noopener noreferrer" class="sc-link !min-h-0 text-xs">
                                    Open in Google Maps
                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9"/><path d="M18 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5"/></svg>
                                </a>
                                <button type="button" @click="clearPin()" class="text-xs font-semibold text-rose-700 underline-offset-3 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Clear pin</button>
                            </div>
                        </div>
                    </div>
                    <p id="branch-pin-map-help" class="mt-3 text-xs text-muted">Search for a place above, tap the map, or drag the pin to the branch's door. The latitude and longitude below fill in automatically.</p>
                    <div class="mt-4 grid min-w-0 max-w-3xl gap-4 sm:grid-cols-3">
                        <div class="min-w-0">
                            <label for="latitude" class="block text-sm font-semibold text-ink">Latitude</label>
                            <input id="latitude" name="latitude" x-ref="latitude" x-model="pinLat" type="number" step="any" min="-90" max="90" inputmode="decimal" value="{{ old('latitude', $store->latitude) }}" @error('latitude') aria-invalid="true" @enderror class="field-control mt-2 font-mono text-base tabular-nums lg:text-sm @error('latitude') border-rose-500 @enderror">
                            <x-input-error :messages="$errors->get('latitude')" class="mt-2" />
                        </div>
                        <div class="min-w-0">
                            <label for="longitude" class="block text-sm font-semibold text-ink">Longitude</label>
                            <input id="longitude" name="longitude" x-ref="longitude" x-model="pinLng" type="number" step="any" min="-180" max="180" inputmode="decimal" value="{{ old('longitude', $store->longitude) }}" @error('longitude') aria-invalid="true" @enderror class="field-control mt-2 font-mono text-base tabular-nums lg:text-sm @error('longitude') border-rose-500 @enderror">
                            <x-input-error :messages="$errors->get('longitude')" class="mt-2" />
                        </div>
                        <div class="min-w-0">
                            <label for="delivery_radius_km" class="block text-sm font-semibold text-ink">Delivery radius (km)</label>
                            <input id="delivery_radius_km" name="delivery_radius_km" x-ref="radius" x-model="radiusKm" type="number" min="1" max="500" inputmode="numeric" value="{{ old('delivery_radius_km', $store->delivery_radius_km) }}" @error('delivery_radius_km') aria-invalid="true" @enderror class="field-control mt-2 text-base tabular-nums lg:text-sm @error('delivery_radius_km') border-rose-500 @enderror">
                            <div class="sc-radius-presets" role="group" aria-label="Radius presets">
                                @foreach ([3, 5, 10, 15, 25] as $km)
                                    <button type="button" @click="setRadius({{ $km }})" :class="String(radiusKm) === '{{ $km }}' && 'is-active'" class="sc-radius-preset">{{ $km }} km</button>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('delivery_radius_km')" class="mt-2" />
                        </div>
                    </div>
                    </div>
                </details>


                </div>
            </form>
            </div>

        </div>
    </div>
</x-app-layout>
