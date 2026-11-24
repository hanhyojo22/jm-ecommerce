@php
    $storeTone = $store->status === 'active' ? 'success' : ($store->status === 'suspended' ? 'danger' : 'neutral');
    $storeStatusLabel = ['active' => 'Open to customers', 'inactive' => 'Closed', 'suspended' => 'Suspended by Everyday Pharmacy'][$store->status] ?? $store->status;
    $shippingNote = collect([$store->barangay, $store->province])->filter()->implode(', ');
    if ($store->processing_days) {
        $processingText = $store->processing_days.' '.Str::plural('business day', $store->processing_days).' processing';
        $shippingNote = $shippingNote ? $shippingNote.' · '.$processingText : $processingText;
    }
    $hoursSummary = ($store->opening_time && $store->closing_time)
        ? substr((string) $store->opening_time, 0, 5).' – '.substr((string) $store->closing_time, 0, 5)
        : 'Open 24 hours';

    $addressFields = [
        'region' => ['label' => 'Region', 'autocomplete' => 'address-level1', 'maxlength' => 100],
        'province' => ['label' => 'Province', 'autocomplete' => 'address-level1', 'maxlength' => 100],
        'barangay' => ['label' => 'Barangay', 'autocomplete' => 'address-level3', 'maxlength' => 100],
        'postal_code' => ['label' => 'Postal code', 'autocomplete' => 'postal-code', 'maxlength' => 4, 'inputmode' => 'numeric'],
    ];

    $logisticsFields = [
        'pickup_address' => ['label' => 'Courier pickup address', 'maxlength' => 255, 'wide' => true, 'help' => 'Where riders collect packed orders. Leave blank if same as street address.'],
        'return_address' => ['label' => 'Return address', 'maxlength' => 255, 'wide' => true, 'help' => 'Where buyer returns are sent.'],
        'processing_days' => ['label' => 'Processing days', 'type' => 'number', 'min' => 1, 'max' => 30, 'help' => 'Business days before you hand orders to the courier.'],
        'fulfillment_methods' => ['label' => 'Fulfillment methods', 'maxlength' => 120, 'help' => 'For example: same-day rider, store pickup'],
        'delivery_areas' => ['label' => 'Supported delivery areas', 'maxlength' => 1000, 'wide' => true, 'help' => 'For example: Metro Manila, Luzon'],
    ];

    $allShippingFieldKeys = array_merge(array_keys($addressFields), array_keys($logisticsFields));
    $linkableErrorFields = [
        'name', 'logo', 'remove_logo', 'description', 'status', 'phone', 'city', 'address_line',
        'latitude', 'longitude', 'delivery_radius_km', ...$allShippingFieldKeys,
    ];
    $storeUrlPath = '/stores/'.$store->slug;
    $existingLogoUrl = $store->logo_path ? Storage::disk('public')->url($store->logo_path) : null;
    $nameInitials = collect(preg_split('/\s+/', trim($store->name)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('') ?: 'EP';
@endphp

<x-app-layout :error-summary="false">
    <x-slot name="header">
        <div class="sc-settings-header">
            @if ($canManageBranches)
                <nav class="sc-settings-breadcrumb" aria-label="Breadcrumb">
                    <a href="{{ route('seller.branches.index') }}">Branches</a>
                    <span aria-hidden="true">/</span>
                    <span>{{ $store->name }}</span>
                </nav>
            @endif
            <div class="sc-page-head">
                <div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="sc-title">Store settings</h1>
                        <span class="sc-badge" data-tone="{{ $storeTone }}">{{ $storeStatusLabel }}</span>
                    </div>
                    <p class="sc-subtitle">Manage how customers find, contact, and order from {{ $store->name }}.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($canManageBranches)
                        <a href="{{ route('seller.branches.index') }}" class="seller-outline-button min-h-10 text-xs sm:text-sm">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                            All branches
                        </a>
                    @endif
                    @if (config('marketplace.web_store.enabled'))
                        <a href="{{ route('catalog.store', $store->slug) }}" target="_blank" rel="noopener noreferrer" class="seller-outline-button min-h-10 text-xs sm:text-sm">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg>
                            Visit storefront
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </x-slot>

    <div class="page-shell">
        <div class="page-container seller-workspace-page">
            <div class="sc-settings-stack">
                <x-marketplace.seller-settings-nav active="details" :branch="$store->id">
                    <a href="#section-identity" class="sc-settings-jump">Identity</a>
                    <a href="#section-contact" class="sc-settings-jump">Status &amp; contact</a>
                    <a href="#section-map" class="sc-settings-jump">Map &amp; radius</a>
                    <a href="#section-shipping" class="sc-settings-jump">Pickup &amp; shipping</a>
                </x-marketplace.seller-settings-nav>

                @if (session('status') || $canUndo)
                    <div class="sc-notice" data-tone="success" role="status">
                        <div class="flex items-start gap-2.5">
                            <svg class="mt-0.5 size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
                            <div>
                                <p class="font-semibold">{{ session('status', 'A recent text-only save can still be undone.') }}</p>
                                @if ($canUndo)
                                    <p class="mt-0.5 text-xs opacity-90">You can restore the previous text values within 5 minutes.</p>
                                @endif
                            </div>
                        </div>
                        @if ($canUndo)
                            <form method="POST" action="{{ route('seller.store-settings.undo', ['branch' => $store->id]) }}">
                                @csrf
                                <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-lg border border-success/30 bg-white px-4 text-sm font-semibold text-ink transition hover:bg-success/10 focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">
                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>
                                    Undo last save
                                </button>
                            </form>
                        @endif
                    </div>
                @endif

                @if ($store->status === 'suspended')
                    <div class="sc-notice" data-tone="danger" role="alert">
                        <div class="flex items-start gap-2.5">
                            <svg class="mt-0.5 size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg>
                            <div>
                                <p class="font-semibold">This branch is suspended by Everyday Pharmacy.</p>
                                <p class="mt-0.5 text-xs opacity-90">It is hidden from customers and cannot be reopened here. You can still update contact and address details while resolving the review.</p>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="sc-settings-grid"
                    x-data="{
                        dirty: {{ $errors->any() ? 'true' : 'false' }},
                        saving: false,
                        copiedUrl: false,
                        storeName: @js(old('name', $store->name) ?? ''),
                        description: @js(old('description', $store->description) ?? ''),
                        phone: @js(old('phone', $store->phone) ?? ''),
                        city: @js(old('city', $store->city) ?? ''),
                        addressLine: @js(old('address_line', $store->address_line) ?? ''),
                        status: @js(old('status', $store->status) ?? 'active'),
                        isSuspended: @js($store->status === 'suspended'),
                        hasExistingLogo: @js((bool) $store->logo_path),
                        existingLogoUrl: @js($existingLogoUrl),
                        logoPreview: null,
                        logoName: '',
                        removeLogo: {{ old('remove_logo') ? 'true' : 'false' }},
                        locating: false,
                        mapFullscreen: false,
                        locationError: '',
                        searchQuery: '',
                        searching: false,
                        searched: false,
                        searchResults: [],
                        searchAbort: null,
                        cityLookupAbort: null,
                        cityLookupRequest: 0,
                        activeIndex: -1,
                        pinLat: @js((string) old('latitude', $store->latitude ?? '')),
                        pinLng: @js((string) old('longitude', $store->longitude ?? '')),
                        radiusKm: @js((string) old('delivery_radius_km', $store->delivery_radius_km ?? '')),
                        get descriptionLength() {
                            return Array.from(this.description || '').length;
                        },
                        get storeInitials() {
                            const parts = (this.storeName || '').trim().split(/\s+/).filter(Boolean);
                            if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
                            return parts[0] ? parts[0].slice(0, 2).toUpperCase() : 'EP';
                        },
                        get previewLogoSrc() {
                            if (this.logoPreview) return this.logoPreview;
                            if (this.hasExistingLogo && ! this.removeLogo) return this.existingLogoUrl;
                            return null;
                        },
                        get hasActiveLogo() {
                            return this.previewLogoSrc !== null;
                        },
                        get hasContactReady() {
                            return (this.phone || '').trim() !== '' && (this.addressLine || '').trim() !== '';
                        },
                        get hasPin() {
                            const lat = Number.parseFloat(this.pinLat);
                            const lng = Number.parseFloat(this.pinLng);
                            return this.pinLat.trim() !== '' && this.pinLng.trim() !== '' && Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180;
                        },
                        get readinessDoneCount() {
                            return 1 + (this.hasActiveLogo ? 1 : 0) + (this.hasContactReady ? 1 : 0) + (this.hasPin ? 1 : 0);
                        },
                        get readinessPercent() {
                            return Math.round((this.readinessDoneCount / 4) * 100);
                        },
                        get formattedCoords() {
                            return this.hasPin ? `${Number(this.pinLat).toFixed(5)}, ${Number(this.pinLng).toFixed(5)}` : '';
                        },
                        get googleMapsUrl() {
                            return 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(this.pinLat + ',' + this.pinLng);
                        },
                        copyStoreUrl(path) {
                            navigator.clipboard?.writeText(path).catch(() => {});
                            this.copiedUrl = true;
                            window.setTimeout(() => { this.copiedUrl = false; }, 1800);
                        },
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
                        async updateCityFromPin({ lat, lng }) {
                            this.cityLookupAbort?.abort();
                            const controller = new AbortController();
                            const request = ++this.cityLookupRequest;
                            this.cityLookupAbort = controller;
                            try {
                                const url = 'https://photon.komoot.io/reverse?' + new URLSearchParams({ lat: String(lat), lon: String(lng), lang: 'en' });
                                const response = await fetch(url, { signal: controller.signal });
                                if (! response.ok) return;
                                const data = await response.json();
                                if (request !== this.cityLookupRequest) return;
                                const properties = data.features?.[0]?.properties;
                                const city = properties?.city ?? properties?.county ?? properties?.locality;
                                if (typeof city !== 'string' || city.trim() === '') return;
                                this.city = city.trim();
                                const cityInput = document.getElementById('city');
                                if (cityInput !== null) {
                                    cityInput.value = city.trim();
                                    cityInput.dispatchEvent(new Event('input', { bubbles: true }));
                                }
                            } catch (error) {
                                if (error?.name !== 'AbortError') return;
                            } finally {
                                if (this.cityLookupAbort === controller) {
                                    this.cityLookupAbort = null;
                                }
                            }
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
                                    return [{ title: parts[0], subtitle: parts.slice(1).join(', '), label, city: p.city ?? p.county ?? null, lat: Number(coords[1]), lng: Number(coords[0]) }];
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
                            if (typeof place.city === 'string' && place.city.trim() !== '') {
                                this.city = place.city.trim();
                                const cityInput = document.getElementById('city');
                                if (cityInput !== null) {
                                    cityInput.value = place.city.trim();
                                    cityInput.dispatchEvent(new Event('input', { bubbles: true }));
                                }
                            }
                            this.$refs.pinMap.dispatchEvent(new Event('branch-pin:moved'));
                            if (typeof place.city !== 'string' || place.city.trim() === '') {
                                this.$refs.pinMap.dispatchEvent(new CustomEvent('branch-pin-changed', { bubbles: true, detail: { lat: Number(lat), lng: Number(lng) } }));
                            }
                            this.searchQuery = place.label;
                            this.searchResults = [];
                            this.searched = false;
                            this.activeIndex = -1;
                            this.locationError = '';
                            this.dirty = true;
                        },
                        setRadius(km) {
                            this.radiusKm = String(km);
                            this.$refs.radius.value = String(km);
                            this.$refs.pinMap.dispatchEvent(new Event('branch-pin:radius'));
                            this.dirty = true;
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
                            this.dirty = true;
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
                                this.dirty = true;
                            }, () => {
                                this.locating = false;
                                this.locationError = 'We could not get your location. Allow location access or enter the coordinates instead.';
                            }, { enableHighAccuracy: true, timeout: 15000 });
                        }
                    }"
                    x-init="window.addEventListener('beforeunload', event => { if (dirty && !saving) { event.preventDefault(); event.returnValue = ''; } })"
                    @branch-pin-changed="updateCityFromPin($event.detail)">

                    {{-- Left Column: Settings Form --}}
                    <form method="POST" action="{{ route('seller.store-settings.update', ['branch' => $store->id]) }}" enctype="multipart/form-data" class="min-w-0 space-y-5"
                        @input="dirty = true" @change="dirty = true"
                        @submit="if (saving) { $event.preventDefault(); } else { saving = true; dirty = false; }">
                        @csrf
                        @method('PATCH')

                        @if ($errors->any())
                            <div role="alert" tabindex="-1" x-init="$nextTick(() => $el.focus())" class="sc-error-summary">
                                <div class="sc-error-summary-head">
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                                    <span>We could not save your changes. Review the issues below and try again.</span>
                                </div>
                                <ul class="sc-error-summary-list">
                                    @foreach ($errors->getMessages() as $field => $messages)
                                        @foreach ($messages as $message)
                                            <li>
                                                @if (in_array($field, $linkableErrorFields, true))
                                                    <a href="#{{ $field }}">{{ $message }}</a>
                                                @else
                                                    <span>{{ $message }}</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        {{-- SECTION 1: Store Identity --}}
                        <section id="section-identity" class="seller-form-card sc-settings-card min-w-0" aria-labelledby="store-profile-heading">
                            <div class="sc-settings-card-head">
                                <div class="flex items-start gap-3">
                                    <span class="sc-settings-card-icon" aria-hidden="true">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg>
                                    </span>
                                    <div>
                                        <h2 id="store-profile-heading" class="sc-card-title">Store identity</h2>
                                        <p class="mt-0.5 text-sm text-muted">Your logo, public pharmacy name, and storefront bio shown across the Everyday Pharmacy app.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="sc-settings-card-body space-y-5">
                                {{-- Logo Uploader --}}
                                <div class="sc-logo-uploader min-w-0">
                                    <div class="sc-logo-preview-box">
                                        @if ($store->logo_path)
                                            <img src="{{ $existingLogoUrl }}" alt="Current {{ $store->name }} logo" class="size-full object-cover" x-show="!logoPreview && !removeLogo">
                                            <span x-cloak x-show="removeLogo" class="sc-logo-removed-badge">Will be removed</span>
                                        @else
                                            <span class="sc-logo-fallback" aria-hidden="true" x-show="!logoPreview" x-text="storeInitials">{{ $nameInitials }}</span>
                                        @endif
                                        <img x-cloak x-show="logoPreview" :src="logoPreview" alt="Selected store logo preview" class="size-full object-cover">
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-ink" x-text="logoPreview ? 'New logo preview' : removeLogo ? 'Logo will be removed on save' : '{{ $store->logo_path ? 'Current branch logo' : 'Branch logo' }}'">{{ $store->logo_path ? 'Current branch logo' : 'Branch logo' }}</p>
                                        <p id="logo-help" class="mt-0.5 text-xs text-muted">Square JPEG, PNG, or WebP up to 2 MB. Recommended 400 × 400 px.</p>
                                        <input id="logo" x-ref="logoInput" name="logo" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="logo-help @error('logo') logo-error @enderror" @error('logo') aria-invalid="true" @enderror
                                            @change="if (logoPreview) URL.revokeObjectURL(logoPreview); logoPreview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null; logoName = $event.target.files[0]?.name || ''; if (logoPreview) removeLogo = false"
                                            class="peer sr-only">
                                        <div class="mt-3 flex flex-wrap items-center gap-3">
                                            <label for="logo" class="inline-flex min-h-10 cursor-pointer items-center justify-center gap-1.5 rounded-md border border-line-strong bg-white px-3.5 text-xs font-semibold text-ink transition hover:border-brand hover:bg-blue-50/70 hover:text-brand peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand @error('logo') border-rose-500 @enderror">
                                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                                Change logo
                                            </label>
                                            @if ($store->logo_path)
                                                <label class="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-md px-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-50">
                                                    <input id="remove_logo" x-model="removeLogo" type="checkbox" name="remove_logo" value="1" @checked(old('remove_logo')) aria-label="Remove current logo on save" aria-describedby="remove-logo-help @error('remove_logo') remove-logo-error @enderror" @error('remove_logo') aria-invalid="true" @enderror
                                                        @change="if ($event.target.checked) { $refs.logoInput.value = ''; if (logoPreview) URL.revokeObjectURL(logoPreview); logoPreview = null; logoName = ''; }"
                                                        class="size-4 rounded border-rose-300 text-rose-600 focus:ring-rose-500 @error('remove_logo') border-rose-500 @enderror">
                                                    Remove logo
                                                </label>
                                            @endif
                                        </div>
                                        <p x-cloak x-show="logoName" x-text="logoName" class="mt-2 truncate text-xs text-muted"></p>
                                        <x-input-error id="logo-error" :messages="$errors->get('logo')" class="mt-2" />
                                        @if ($store->logo_path)
                                            <p id="remove-logo-help" class="mt-2 text-xs text-muted">Removing deletes the current image after you save and cannot be undone.</p>
                                            <x-input-error id="remove-logo-error" :messages="$errors->get('remove_logo')" class="mt-2" />
                                        @endif
                                    </div>
                                </div>

                                {{-- Branch Name & Store URL --}}
                                <div class="grid min-w-0 gap-4 sm:grid-cols-2">
                                    @if ($canManageBranches)
                                        <div class="min-w-0">
                                            <label for="name" class="block text-sm font-semibold text-ink">Branch name <span class="font-normal text-muted">(required)</span></label>
                                            <input id="name" name="name" x-model="storeName" maxlength="120" required @error('name') aria-describedby="name-error" aria-invalid="true" @enderror value="{{ old('name', $store->name) }}" class="field-control mt-2 text-base lg:text-sm @error('name') border-rose-500 @enderror">
                                            <p class="mt-1.5 text-xs text-muted">Customers see this name in the app. The store URL stays the same.</p>
                                            <x-input-error id="name-error" :messages="$errors->get('name')" class="mt-2" />
                                        </div>
                                    @else
                                        <div class="min-w-0">
                                            <div class="flex items-baseline justify-between gap-2">
                                                <span class="text-sm font-semibold text-ink">Branch name</span>
                                                <span class="text-xs text-muted">Managed by head office</span>
                                            </div>
                                            <div class="sc-field-readonly mt-2">
                                                <span class="truncate">{{ $store->name }}</span>
                                                <svg class="size-4 shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                            </div>
                                            <p class="mt-1.5 text-xs text-muted">Only head office owners and managers can rename a branch.</p>
                                        </div>
                                    @endif

                                    <div class="min-w-0">
                                        <div class="flex items-baseline justify-between gap-2">
                                            <span class="text-sm font-semibold text-ink">Store URL</span>
                                            <span class="text-xs text-muted">Permanent link</span>
                                        </div>
                                        <div class="sc-url-chip mt-2">
                                            <code class="min-w-0 break-all font-mono text-xs text-ink">{{ $storeUrlPath }}</code>
                                            <button type="button" @click="copyStoreUrl(@js($storeUrlPath))" :class="copiedUrl && 'is-copied'" class="sc-copy-btn">
                                                <svg x-show="!copiedUrl" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                                <svg x-cloak x-show="copiedUrl" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                                                <span x-text="copiedUrl ? 'Copied' : 'Copy'">Copy</span>
                                            </button>
                                        </div>
                                        <p class="mt-1.5 text-xs text-muted">Preserved even if this branch is renamed.</p>
                                    </div>
                                </div>

                                {{-- Description --}}
                                <div class="min-w-0">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <label for="description" class="block text-sm font-semibold text-ink">Store description</label>
                                        <p id="description-count" class="text-xs tabular-nums text-muted"><span x-text="descriptionLength">{{ mb_strlen(old('description', $store->description) ?? '') }}</span> / 2,000 characters</p>
                                    </div>
                                    <textarea id="description" name="description" x-model="description" rows="3" maxlength="2000" placeholder="e.g. Neighborhood pharmacy carrying prescription medicines, vitamins, and daily health essentials." aria-describedby="description-count @error('description') description-error @enderror" @error('description') aria-invalid="true" @enderror class="field-control mt-2 resize-y text-base lg:text-sm @error('description') border-rose-500 @enderror">{{ old('description', $store->description) }}</textarea>
                                    <p class="mt-1.5 text-xs text-muted">A short 1–2 sentence bio builds trust on your storefront and mobile branch card.</p>
                                    <x-input-error id="description-error" :messages="$errors->get('description')" class="mt-2" />
                                </div>
                            </div>
                        </section>

                        {{-- SECTION 2: Branch Availability & Contact --}}
                        <section id="section-contact" class="seller-form-card sc-settings-card min-w-0" aria-labelledby="contact-details-heading">
                            <div class="sc-settings-card-head">
                                <div class="flex items-start gap-3">
                                    <span class="sc-settings-card-icon" aria-hidden="true">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                    </span>
                                    <div>
                                        <h2 id="contact-details-heading" class="sc-card-title">Branch availability &amp; contact</h2>
                                        <p class="mt-0.5 text-sm text-muted">Control whether this branch accepts orders and how customers and couriers reach your counter.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="sc-settings-card-body space-y-5">
                                @if ($store->status !== 'suspended')
                                    @if ($canManageBranches)
                                        <fieldset class="min-w-0" id="status">
                                            <div class="flex items-baseline justify-between gap-2">
                                                <legend class="text-sm font-semibold text-ink">Branch status</legend>
                                                <span class="text-xs text-muted">Controls customer visibility</span>
                                            </div>
                                            <div class="sc-status-grid mt-2" role="radiogroup" aria-label="Branch status">
                                                <label class="sc-status-option" data-choice="active" :data-selected="(status === 'active').toString()">
                                                    <input type="radio" name="status" value="active" x-model="status" @checked(old('status', $store->status) === 'active')>
                                                    <span class="sc-status-radio" aria-hidden="true"></span>
                                                    <span class="min-w-0">
                                                        <span class="sc-status-option-title">Open to customers</span>
                                                        <span class="sc-status-option-desc">Visible in search and nearby pharmacies. Customers can place orders during opening hours.</span>
                                                    </span>
                                                </label>
                                                <label class="sc-status-option" data-choice="inactive" :data-selected="(status === 'inactive').toString()">
                                                    <input type="radio" name="status" value="inactive" x-model="status" @checked(old('status', $store->status) === 'inactive')>
                                                    <span class="sc-status-radio" aria-hidden="true"></span>
                                                    <span class="min-w-0">
                                                        <span class="sc-status-option-title">Temporarily closed</span>
                                                        <span class="sc-status-option-desc">Hidden from customers and search. Existing orders stay accessible in Seller Center.</span>
                                                    </span>
                                                </label>
                                            </div>
                                            <x-input-error :messages="$errors->get('status')" class="mt-2" />
                                        </fieldset>
                                    @else
                                        <div class="min-w-0">
                                            <div class="flex items-baseline justify-between gap-2">
                                                <span class="text-sm font-semibold text-ink">Store availability</span>
                                                <span class="text-xs text-muted">Managed by head office</span>
                                            </div>
                                            <div class="sc-field-readonly mt-2">
                                                <span class="inline-flex items-center gap-2">
                                                    <span class="size-2 rounded-full {{ $store->status === 'active' ? 'bg-emerald-600' : 'bg-slate-400' }}" aria-hidden="true"></span>
                                                    <span>{{ $storeStatusLabel }}</span>
                                                </span>
                                                <span class="text-xs text-muted">Contact head office to close or reopen</span>
                                            </div>
                                        </div>
                                    @endif
                                @endif

                                <div class="grid min-w-0 gap-4 sm:grid-cols-2">
                                    <div class="min-w-0">
                                        <label for="phone" class="block text-sm font-semibold text-ink">Branch phone <span class="font-normal text-muted">(required)</span></label>
                                        <input id="phone" name="phone" x-model="phone" type="tel" autocomplete="tel" maxlength="30" required @error('phone') aria-describedby="phone-error" aria-invalid="true" @enderror value="{{ old('phone', $store->phone) }}" class="field-control mt-2 text-base lg:text-sm @error('phone') border-rose-500 @enderror">
                                        <p class="mt-1.5 text-xs text-muted">Used for order questions and courier pickup coordination.</p>
                                        <x-input-error id="phone-error" :messages="$errors->get('phone')" class="mt-2" />
                                    </div>
                                    <div class="min-w-0">
                                        <label for="city" class="block text-sm font-semibold text-ink">City / Municipality <span class="font-normal text-muted">(required)</span></label>
                                        <input id="city" name="city" x-model="city" autocomplete="address-level2" maxlength="100" required @error('city') aria-describedby="city-error" aria-invalid="true" @enderror value="{{ old('city', $store->city) }}" class="field-control mt-2 text-base lg:text-sm @error('city') border-rose-500 @enderror">
                                        <p class="mt-1.5 text-xs text-muted">Updates automatically when you pick a map search result or move the pin.</p>
                                        <x-input-error id="city-error" :messages="$errors->get('city')" class="mt-2" />
                                    </div>
                                    <div class="min-w-0 sm:col-span-2">
                                        <label for="address_line" class="block text-sm font-semibold text-ink">Street address <span class="font-normal text-muted">(required)</span></label>
                                        <input id="address_line" name="address_line" x-model="addressLine" autocomplete="street-address" maxlength="255" required @error('address_line') aria-describedby="address-line-error" aria-invalid="true" @enderror value="{{ old('address_line', $store->address_line) }}" class="field-control mt-2 text-base lg:text-sm @error('address_line') border-rose-500 @enderror">
                                        <x-input-error id="address-line-error" :messages="$errors->get('address_line')" class="mt-2" />
                                    </div>
                                </div>
                            </div>
                        </section>

                        {{-- SECTION 3: Map Pin & Delivery Radius (Prominent & Open by Default) --}}
                        <section id="section-map" class="seller-form-card sc-settings-card min-w-0" aria-labelledby="map-location-heading">
                            <div class="sc-settings-card-head">
                                <div class="flex items-start gap-3">
                                    <span class="sc-settings-card-icon" aria-hidden="true">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s-7-5.7-7-11a7 7 0 1 1 14 0c0 5.3-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                                    </span>
                                    <div>
                                        <h2 id="map-location-heading" class="sc-card-title">Map pin &amp; delivery radius</h2>
                                        <p class="mt-0.5 text-sm text-muted">Powers "Nearby pharmacies" in the Everyday Pharmacy app and defines how far this branch delivers.</p>
                                    </div>
                                </div>
                                <span x-show="hasPin" @if (! ($store->latitude && $store->longitude)) x-cloak @endif class="sc-badge" data-tone="success">
                                    <span>Pinned</span><span x-show="radiusKm" x-text="' · ' + radiusKm + ' km'"></span>
                                </span>
                                <span x-show="!hasPin" @if ($store->latitude && $store->longitude) x-cloak @endif class="sc-badge" data-tone="neutral">No map pin yet</span>
                            </div>

                            <div class="sc-settings-card-body">
                                <div class="sc-map-shell" :class="mapFullscreen && 'is-fullscreen'" @keydown.escape.window="if (mapFullscreen) { handleEscape(); }">
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

                                    {{-- isolate keeps Leaflet's stacked layers under sticky bars. --}}
                                    <div data-branch-pin-map data-latitude-input="latitude" data-longitude-input="longitude" data-radius-input="delivery_radius_km" x-ref="pinMap" role="region" aria-label="Branch location map" aria-describedby="branch-pin-map-help" class="sc-map-canvas isolate h-72 w-full bg-surface sm:h-[340px]"></div>

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

                                <p id="branch-pin-map-help" class="mt-2.5 text-xs text-muted">Search for a place above, tap the map, or drag the pin to your pharmacy's door. Leave empty to stay out of nearby suggestions.</p>

                                {{-- Prominent Delivery Radius Bar --}}
                                <div class="sc-radius-bar mt-4">
                                    <div>
                                        <label for="delivery_radius_km" class="block text-sm font-semibold text-ink">Delivery radius</label>
                                        <p class="mt-0.5 text-xs text-muted">How far from this pin customers can order delivery</p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <div class="sc-radius-presets !mt-0" role="group" aria-label="Radius presets">
                                            @foreach ([3, 5, 10, 15, 25] as $km)
                                                <button type="button" @click="setRadius({{ $km }})" :aria-pressed="(String(radiusKm) === '{{ $km }}').toString()" :class="String(radiusKm) === '{{ $km }}' && 'is-active'" class="sc-radius-preset">{{ $km }} km</button>
                                            @endforeach
                                        </div>
                                        <div class="sc-radius-custom">
                                            <input id="delivery_radius_km" name="delivery_radius_km" x-ref="radius" x-model="radiusKm" type="number" min="1" max="500" inputmode="numeric" value="{{ old('delivery_radius_km', $store->delivery_radius_km) }}" aria-label="Delivery radius in kilometers" @error('delivery_radius_km') aria-invalid="true" @enderror class="field-control w-20 text-right text-base tabular-nums lg:text-sm @error('delivery_radius_km') border-rose-500 @enderror">
                                            <span class="text-xs font-semibold text-muted">km</span>
                                        </div>
                                    </div>
                                </div>
                                <x-input-error :messages="$errors->get('delivery_radius_km')" class="mt-2" />

                                {{-- Progressive Disclosure for Manual Coordinates --}}
                                <details class="sc-subtle-disclosure mt-4" @if ($errors->hasAny(['latitude', 'longitude'])) open @endif>
                                    <summary>
                                        <svg class="size-3.5 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                        <span>Enter exact latitude &amp; longitude manually</span>
                                    </summary>
                                    <div class="mt-3 grid min-w-0 gap-4 sm:grid-cols-2">
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
                                    </div>
                                </details>
                            </div>
                        </section>

                        {{-- SECTION 4: Pickup, Return & Fulfillment Details --}}
                        <details id="section-shipping" class="seller-form-card sc-settings-disclosure min-w-0" @if ($errors->hasAny($allShippingFieldKeys)) open @endif>
                            <summary id="shipping-details-heading">
                                <span class="flex min-w-0 items-center gap-3">
                                    <span class="sc-settings-card-icon" aria-hidden="true">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/></svg>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm font-semibold text-ink">Pickup, return &amp; fulfillment details</span>
                                            <span class="sc-privacy-tag">
                                                <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                                Private
                                            </span>
                                        </span>
                                        <span class="mt-0.5 block text-xs font-normal text-muted">{{ $shippingNote ?: 'Optional barangay, courier pickup, and return addresses' }}</span>
                                    </span>
                                </span>
                                <svg class="shrink-0 text-muted transition-transform" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                            </summary>
                            <div class="sc-disclosure-body">
                                <p class="text-sm text-muted">Only your pharmacy team and Everyday Pharmacy see your pickup and return addresses.</p>
                                <div class="mt-4 grid min-w-0 gap-4 sm:grid-cols-2">
                                    @foreach ($addressFields as $name => $field)
                                        <div class="min-w-0">
                                            <label for="{{ $name }}" class="block text-sm font-semibold text-ink">{{ $field['label'] }}</label>
                                            <input id="{{ $name }}" name="{{ $name }}" type="text" value="{{ old($name, $store->{$name}) }}"
                                                @isset($field['autocomplete']) autocomplete="{{ $field['autocomplete'] }}" @endisset
                                                @isset($field['maxlength']) maxlength="{{ $field['maxlength'] }}" @endisset
                                                @isset($field['inputmode']) inputmode="{{ $field['inputmode'] }}" @endisset
                                                @error($name) aria-invalid="true" @enderror
                                                class="field-control mt-2 text-base lg:text-sm @error($name) border-rose-500 @enderror">
                                            <x-input-error :messages="$errors->get($name)" class="mt-2" />
                                        </div>
                                    @endforeach
                                </div>

                                <p class="sc-fieldset-divider">Logistics &amp; dispatch</p>
                                <div class="grid min-w-0 gap-4 sm:grid-cols-2">
                                    @foreach ($logisticsFields as $name => $field)
                                        <div @class(['min-w-0', 'sm:col-span-2' => $field['wide'] ?? false])>
                                            <label for="{{ $name }}" class="block text-sm font-semibold text-ink">{{ $field['label'] }}</label>
                                            <input id="{{ $name }}" name="{{ $name }}" type="{{ $field['type'] ?? 'text' }}" value="{{ old($name, $store->{$name}) }}"
                                                @isset($field['maxlength']) maxlength="{{ $field['maxlength'] }}" @endisset
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

                        {{-- Contextual Sticky Bottom Save Dock --}}
                        <div class="sc-save-dock" x-show="dirty || saving" @unless ($errors->any()) x-cloak @endunless role="region" aria-label="Unsaved store settings">
                            <p class="sc-save-dock-status" role="status" aria-live="polite">
                                <span class="sc-dirty-pulse" aria-hidden="true"></span>
                                <span x-text="saving ? 'Saving store settings…' : 'You have unsaved changes'">You have unsaved changes</span>
                            </p>
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('seller.store-settings.edit', ['branch' => $store->id]) }}" class="sc-dock-discard">Discard</a>
                                <button type="submit" :disabled="saving || !dirty" :aria-busy="saving" aria-label="Save store settings" class="sc-dock-save">
                                    <svg x-show="!saving" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                                    <span x-show="!saving">Save changes</span>
                                    <span x-cloak x-show="saving">Saving…</span>
                                </button>
                            </div>
                        </div>
                    </form>

                    {{-- Right Sticky Context Rail: Live Customer Preview + Readiness --}}
                    <aside class="sc-settings-rail" aria-label="Live customer preview and branch setup readiness">
                        <div class="sc-preview-card">
                            <div class="sc-preview-head">
                                <span class="sc-preview-label">
                                    <span class="sc-live-dot" aria-hidden="true"></span>
                                    Customer app preview
                                </span>
                                <span class="text-[11px] text-muted">Updates live</span>
                            </div>
                            <div class="sc-preview-stage">
                                <div class="sc-buyer-card">
                                    <div class="sc-buyer-card-banner">
                                        <div class="sc-buyer-card-logo">
                                            <img x-cloak x-show="previewLogoSrc" :src="previewLogoSrc" alt="" class="size-full object-cover">
                                            <span x-show="!previewLogoSrc" x-text="storeInitials">{{ $nameInitials }}</span>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-bold text-white" x-text="storeName.trim() || @js($store->name)">{{ $store->name }}</p>
                                            <p class="mt-0.5 flex items-center gap-1 text-xs text-slate-300">
                                                <svg class="size-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s-7-5.7-7-11a7 7 0 1 1 14 0c0 5.3-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                                                <span class="truncate" x-text="city.trim() || 'City not set'">{{ $store->city ?: 'City not set' }}</span>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="sc-buyer-card-body">
                                        <p class="sc-buyer-card-desc" x-text="description.trim() || 'Add a short description so customers recognize your pharmacy.'">{{ $store->description ?: 'Add a short description so customers recognize your pharmacy.' }}</p>
                                        <div class="mt-2.5 flex flex-wrap gap-1.5">
                                            <span class="sc-buyer-chip" :data-tone="isSuspended ? 'danger' : status === 'active' ? 'success' : 'neutral'"
                                                x-text="isSuspended ? 'Suspended' : status === 'active' ? 'Open now' : 'Temporarily closed'">{{ $store->status === 'suspended' ? 'Suspended' : ($store->status === 'active' ? 'Open now' : 'Temporarily closed') }}</span>
                                            <span class="sc-buyer-chip" x-text="hasPin ? (radiusKm ? 'Delivers within ' + radiusKm + ' km' : 'Pinned on map') : 'Not in nearby search'">{{ ($store->latitude && $store->longitude) ? ($store->delivery_radius_km ? 'Delivers within '.$store->delivery_radius_km.' km' : 'Pinned on map') : 'Not in nearby search' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p class="sc-preview-caption">How customers see this branch in Everyday Pharmacy search and nearby listings.</p>
                        </div>

                        <div class="sc-checklist-card">
                            <div class="flex items-center justify-between gap-2">
                                <h2 class="text-sm font-semibold text-ink">Branch setup readiness</h2>
                                <span class="text-xs font-bold tabular-nums text-brand" x-text="readinessDoneCount + ' / 4 ready'"></span>
                            </div>
                            <div class="sc-progress-track" aria-hidden="true">
                                <div class="sc-progress-fill" :style="'width:' + readinessPercent + '%'"></div>
                            </div>
                            <ul class="mt-3 space-y-1">
                                <li>
                                    <a href="#section-identity" class="sc-check-item" :class="hasActiveLogo && 'is-done'">
                                        <span class="sc-check-item-left">
                                            <span class="sc-check-dot" aria-hidden="true"><svg class="size-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg></span>
                                            <span>Store logo uploaded</span>
                                        </span>
                                        <span x-show="!hasActiveLogo" class="text-xs font-semibold text-brand">Add</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="#section-contact" class="sc-check-item" :class="hasContactReady && 'is-done'">
                                        <span class="sc-check-item-left">
                                            <span class="sc-check-dot" aria-hidden="true"><svg class="size-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg></span>
                                            <span>Phone &amp; street address</span>
                                        </span>
                                        <span x-show="!hasContactReady" class="text-xs font-semibold text-brand">Add</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="#section-map" class="sc-check-item" :class="hasPin && 'is-done'">
                                        <span class="sc-check-item-left">
                                            <span class="sc-check-dot" aria-hidden="true"><svg class="size-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg></span>
                                            <span>Map pin &amp; delivery radius</span>
                                        </span>
                                        <span x-show="!hasPin" class="text-xs font-semibold text-brand">Set pin</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('seller.opening-hours.edit', ['branch' => $store->id]) }}" class="sc-check-item is-done">
                                        <span class="sc-check-item-left">
                                            <span class="sc-check-dot" aria-hidden="true"><svg class="size-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg></span>
                                            <span class="truncate">{{ $hoursSummary }}</span>
                                        </span>
                                        <span class="text-xs font-semibold text-muted">Edit</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
