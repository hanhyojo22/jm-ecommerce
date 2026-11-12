<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="mt-1 marketplace-page-title">Notifications</h1>
                <p class="mt-1.5 max-w-2xl text-sm text-muted">Push alerts to the mobile app, browsers with notifications on, and the in-app bell.</p>
            </div>

            <nav aria-label="Notifications" class="flex gap-1 self-start rounded-lg bg-[var(--color-selected)] p-1 lg:self-auto">
                <a
                    href="{{ route('admin.announcements.create') }}"
                    @if ($tab === 'compose') aria-current="page" @endif
                    class="inline-flex min-h-10 items-center rounded-md px-4 text-sm font-semibold transition {{ $tab === 'compose' ? 'bg-surface text-ink shadow-[0_1px_2px_rgb(24_44_72/8%)]' : 'text-muted hover:text-ink' }}"
                >New notification</a>
                <a
                    href="{{ route('admin.announcements.create', ['tab' => 'history']) }}"
                    @if ($tab === 'history') aria-current="page" @endif
                    class="inline-flex min-h-10 items-center gap-1.5 rounded-md px-4 text-sm font-semibold transition {{ $tab === 'history' ? 'bg-surface text-ink shadow-[0_1px_2px_rgb(24_44_72/8%)]' : 'text-muted hover:text-ink' }}"
                >History</a>
            </nav>
        </div>
    </x-slot>

    @php
        $customersCount = $audienceCounts['customers'] ?? 0;
        $storesCount = $audienceCounts['stores'] ?? 0;
        $audiences = [
            'customers' => ['label' => 'All customers', 'meta' => number_format($customersCount).' active'],
            'stores' => ['label' => 'All stores', 'meta' => number_format($storesCount).' '.Str::plural('member', $storesCount)],
            'customer' => ['label' => 'One customer', 'meta' => 'By email address'],
        ];
        $opensChoices = [
            'home' => 'Home',
            'store' => 'A store',
            'product' => 'A product',
            'other' => 'Other page',
        ];
        $quickLinks = [
            ['label' => 'Top products', 'url' => route('catalog.top-products')],
            ['label' => 'Browse catalog', 'url' => route('catalog.index')],
            ['label' => 'All stores', 'url' => route('catalog.stores')],
            ['label' => 'Cart', 'url' => route('cart.index')],
        ];
        $templates = [
            ['key' => 'product-ad', 'label' => 'Product ad', 'title' => 'Fresh finds just landed', 'body' => 'Discover new arrivals from JMMart sellers. Browse the latest products and find something to love.', 'url' => route('catalog.index')],
            ['key' => 'flash-sale', 'label' => 'Flash sale', 'title' => 'Flash sale is live', 'body' => 'Limited-time deals on top products. Shop now before your favorite items sell out.', 'url' => route('catalog.top-products')],
            ['key' => 'store-spotlight', 'label' => 'Store spotlight', 'title' => 'Support local sellers', 'body' => 'Explore trusted JMMart stores and shop their latest offers, all in one place.', 'url' => route('catalog.stores')],
            ['key' => 'cart-reminder', 'label' => 'Cart reminder', 'title' => 'Still thinking it over?', 'body' => 'Your cart is waiting. Complete your order before the items you picked sell out.', 'url' => route('cart.index')],
            ['key' => 'announcement', 'label' => 'Announcement', 'title' => 'A quick update from JMMart', 'body' => 'We have made a few improvements to make shopping at JMMart easier. Take a look around and see what is new.', 'url' => route('home')],
        ];
        $oldUrl = (string) old('url', '');
        $initialOpens = $initialLinkTarget['kind'] ?? ($oldUrl === '' || $oldUrl === route('home') ? 'home' : 'other');
        $initialAutoImage = ($initialLinkTarget['kind'] ?? null) === 'product' && filled($initialLinkTarget['image']) && $initialLinkTarget['image'] === old('image_url') ? $initialLinkTarget['image'] : '';
        $initialIsProduct = ($initialLinkTarget['kind'] ?? null) === 'product';
        $initialTargetTitle = match ($initialLinkTarget['kind'] ?? null) {
            'product' => mb_substr($initialLinkTarget['name'].' · '.$initialLinkTarget['price'], 0, 60),
            'store' => mb_substr('New at '.$initialLinkTarget['name'], 0, 60),
            default => '',
        };
        $initialAutoTitle = $initialTargetTitle !== '' && $initialTargetTitle === old('title') ? $initialTargetTitle : '';
        $initialTargetBody = $initialIsProduct ? mb_substr('From '.$initialLinkTarget['store'].'.', 0, 200) : '';
        $initialAutoBody = $initialTargetBody !== '' && $initialTargetBody === old('body') ? $initialTargetBody : '';
    @endphp

    <script>
        // One search box per link type; the page's x-data owns what a pick does.
        window.jmmartLinkSearch = (type, onPick, id) => ({
            type,
            query: '',
            results: [],
            open: false,
            loading: false,
            failed: false,
            active: -1,
            request: 0,
            async search() {
                const request = ++this.request;
                this.open = true;
                this.loading = true;
                this.failed = false;
                const params = new URLSearchParams({ type: this.type, q: this.query.trim() });

                if (this.type === 'products' && this.storeFilter) {
                    params.set('store', this.storeFilter.slug);
                }

                try {
                    const response = await fetch(this.linkEndpoint + '?' + params, { headers: { Accept: 'application/json' } });

                    if (! response.ok) {
                        throw new Error(response.statusText);
                    }

                    const { results } = await response.json();

                    // A slower earlier search must not overwrite a newer one.
                    if (request === this.request) {
                        this.results = results;
                        this.active = results.length > 0 ? 0 : -1;
                    }
                } catch {
                    if (request === this.request) {
                        this.failed = true;
                    }
                } finally {
                    if (request === this.request) {
                        this.loading = false;
                    }
                }
            },
            move(step) {
                if (! this.open) {
                    this.search();
                    return;
                }

                if (this.results.length > 0) {
                    this.active = (this.active + step + this.results.length) % this.results.length;
                    this.$nextTick(() => document.getElementById(id + '-option-' + this.active)?.scrollIntoView({ block: 'nearest' }));
                }
            },
            chooseActive(event) {
                const option = this.open ? this.results[this.active] : null;

                if (option) {
                    event.preventDefault();
                    this.choose(option);
                }
            },
            choose(option) {
                this.open = false;
                this.query = '';
                this[onPick](option);
            },
            initials(name) {
                return name.split(/\s+/).filter(Boolean).slice(0, 2).map((word) => word[0]).join('').toUpperCase();
            },
            emptyText() {
                const query = this.query.trim();

                if (this.type === 'stores') {
                    return query === '' ? 'No active stores yet.' : 'No active store matches “' + query + '”.';
                }

                if (this.storeFilter) {
                    return 'No product in ' + this.storeFilter.name + (query === '' ? ' yet.' : ' matches “' + query + '”.');
                }

                return query === '' ? 'No published products yet.' : 'No published product matches “' + query + '”.';
            },
        });
    </script>

    <div class="page-shell">
        <div class="page-container space-y-6 {{ $tab === 'compose' ? 'pb-24 xl:pb-0' : '' }}">
            @if (session('status'))
                <div class="flex items-center gap-3 rounded-lg border border-[var(--color-success-line)] bg-[var(--color-success-wash)] px-4 py-3 text-sm font-medium text-success" role="status">
                    <svg class="size-5 shrink-0 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5" /></svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if ($tab === 'compose')
                <div
                    x-data="{
                        audience: @js(old('audience', 'customers')),
                        email: @js(old('email', '')),
                        title: @js(old('title', '')),
                        body: @js(old('body', '')),
                        url: @js($oldUrl),
                        imageUrl: @js(old('image_url', '')),
                        imagePreview: @js(old('image_url', '')),
                        imageName: '',
                        imageFailed: false,
                        imageError: '',
                        imageDrag: false,
                        submitting: false,
                        defaultUrl: @js(route('home')),
                        activeTemplate: null,
                        opens: @js($initialOpens),
                        target: @js($initialLinkTarget),
                        storeFilter: null,
                        linkError: '',
                        autoImage: @js($initialAutoImage),
                        autoTitle: @js($initialAutoTitle),
                        autoBody: @js($initialAutoBody),
                        linkEndpoint: @js(route('admin.announcements.link-targets')),
                        previewDevice: 'phone',
                        reviewChecked: false,
                        recipientCounts: @js(['customers' => $customersCount, 'stores' => $storesCount]),
                        recipientLabel() {
                            if (this.audience === 'customer') {
                                return this.email.trim() || 'one customer';
                            }

                            const count = this.recipientCounts[this.audience] ?? 0;
                            const noun = this.audience === 'customers' ? 'customer' : 'store member';

                            return count.toLocaleString() + ' ' + noun + (count === 1 ? '' : 's');
                        },
                        opensLabel() {
                            if (this.target) {
                                return (this.target.kind === 'product' ? 'Product · ' : 'Store · ') + this.target.name;
                            }

                            if (this.opens === 'other' && this.url.trim() !== '') {
                                return this.url.trim().replace(/^https?:\/\/[^/]+/, '') || 'Home';
                            }

                            return 'Home';
                        },
                        actionLabel() {
                            if (this.target) {
                                return this.target.kind === 'product' ? 'View product' : 'Open store';
                            }

                            return /\/cart\/?$/.test(this.url.trim()) ? 'View cart' : 'Open JMMart';
                        },
                        setOpens(kind) {
                            if (kind === this.opens) {
                                return;
                            }

                            this.clearTarget();
                            this.storeFilter = null;
                            this.opens = kind;
                            this.url = '';
                            this.linkError = '';
                        },
                        selectTarget(option) {
                            const followsTarget = ! this.imagePreview || this.usingProductPhoto();
                            this.target = option;
                            this.url = option.url;
                            this.linkError = '';

                            // A custom image always wins; only an empty or automatic image follows the pick.
                            if (followsTarget) {
                                this.useProductPhoto();
                            }

                            // Like the image, an empty or automatic title and message follow the pick; typed ones stay.
                            const isProduct = option.kind === 'product';

                            if (this.title.trim() === '' || this.title === this.autoTitle) {
                                this.title = Array.from(isProduct ? option.name + ' · ' + option.price : 'New at ' + option.name).slice(0, 60).join('');
                                this.autoTitle = this.title;
                                this.activeTemplate = null;
                            }

                            if (isProduct && (this.body.trim() === '' || this.body === this.autoBody)) {
                                this.body = Array.from('From ' + option.store + '.').slice(0, 200).join('');
                                this.autoBody = this.body;
                                this.activeTemplate = null;
                            }
                        },
                        clearTarget() {
                            if (this.usingProductPhoto()) {
                                this.removeImage();
                            }

                            if (this.autoTitle !== '' && this.title === this.autoTitle) {
                                this.title = '';
                            }

                            this.autoTitle = '';

                            if (this.autoBody !== '' && this.body === this.autoBody) {
                                this.body = '';
                            }

                            this.autoBody = '';

                            this.target = null;
                        },
                        setStoreFilter(option) {
                            this.storeFilter = option;
                        },
                        clearStoreFilter() {
                            this.storeFilter = null;
                        },
                        applyTemplate(template) {
                            this.title = template.title;
                            this.body = template.body;
                            this.activeTemplate = template.key;

                            // A chosen store or product is a deliberate pick; templates never replace it.
                            if (this.opens === 'home' || this.opens === 'other') {
                                this.opens = template.url === this.defaultUrl ? 'home' : 'other';
                                this.url = template.url === this.defaultUrl ? '' : template.url;
                            }
                        },
                        openReview() {
                            if (! this.$refs.form.reportValidity()) {
                                return;
                            }

                            if ((this.opens === 'store' || this.opens === 'product') && ! this.target) {
                                this.linkError = this.opens === 'store' ? 'Choose a store, or switch to Home.' : 'Choose a product, or switch to Home.';
                                this.$refs.opens.scrollIntoView({ block: 'center' });
                                return;
                            }

                            this.reviewChecked = false;
                            this.$refs.review.showModal();
                        },
                        usingProductPhoto() {
                            return this.autoImage !== '' && this.imageUrl === this.autoImage && this.imageName === '';
                        },
                        productPhoto() {
                            return this.target?.kind === 'product' ? (this.target.image ?? '') : '';
                        },
                        useProductPhoto() {
                            const image = this.productPhoto();

                            if (image === '') {
                                this.removeImage();
                                return;
                            }

                            this.imageUrl = image;
                            this.useExternalImage();
                            this.autoImage = image;
                        },
                        stageImage(file) {
                            if (! file) {
                                return;
                            }

                            if (! ['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                                this.$refs.imageInput.value = '';
                                this.imageError = 'Choose a JPEG, PNG, or WebP image.';
                                return;
                            }

                            if (file.size > 2 * 1024 * 1024) {
                                this.$refs.imageInput.value = '';
                                this.imageError = 'Choose an image smaller than 2 MB.';
                                return;
                            }

                            if (this.imagePreview.startsWith('blob:')) {
                                URL.revokeObjectURL(this.imagePreview);
                            }

                            this.imagePreview = URL.createObjectURL(file);
                            this.imageName = file.name;
                            this.imageUrl = '';
                            this.imageError = '';
                            this.imageFailed = false;
                        },
                        handleImageInput(event) {
                            this.stageImage(event.target.files?.[0] ?? null);
                        },
                        handleImageDrop(event) {
                            this.imageDrag = false;
                            const file = event.dataTransfer?.files?.[0] ?? null;

                            if (! file) {
                                return;
                            }

                            const transfer = new DataTransfer();
                            transfer.items.add(file);
                            this.$refs.imageInput.files = transfer.files;
                            this.stageImage(file);
                        },
                        useExternalImage() {
                            if (this.$refs.imageInput) {
                                this.$refs.imageInput.value = '';
                            }

                            if (this.imagePreview.startsWith('blob:')) {
                                URL.revokeObjectURL(this.imagePreview);
                            }

                            this.imagePreview = this.imageUrl.trim();
                            this.imageName = '';
                            this.imageError = '';
                            this.imageFailed = false;
                        },
                        removeImage() {
                            if (this.imagePreview.startsWith('blob:')) {
                                URL.revokeObjectURL(this.imagePreview);
                            }

                            if (this.$refs.imageInput) {
                                this.$refs.imageInput.value = '';
                            }

                            this.imagePreview = '';
                            this.imageName = '';
                            this.imageUrl = '';
                            this.imageError = '';
                            this.imageFailed = false;
                            this.autoImage = '';
                        },
                    }"
                >
                    <form x-ref="form" method="POST" action="{{ route('admin.announcements.store') }}" enctype="multipart/form-data" @submit="submitting = true" class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_23rem]">
                        @csrf
                        <input type="hidden" name="url" :value="url">

                        <div class="content-card min-w-0 divide-y divide-line-soft">
                            {{-- Step 1 --}}
                            <section class="flex gap-4 px-5 py-5 sm:gap-5 sm:px-7 sm:py-6">
                                <span class="grid size-7 shrink-0 place-items-center rounded-full bg-brand text-xs font-bold text-white" aria-hidden="true">1</span>
                                <fieldset class="min-w-0 flex-1">
                                    <legend id="step-audience" class="text-[15px] font-semibold text-ink">Who receives it</legend>
                                    <div class="mt-3 grid gap-2.5 sm:grid-cols-3">
                                        @foreach ($audiences as $value => $option)
                                            <label
                                                for="audience-{{ $value }}"
                                                :class="audience === '{{ $value }}'
                                                    ? 'border-brand bg-[var(--color-info-wash)]/70 ring-1 ring-inset ring-brand'
                                                    : 'border-line bg-surface hover:border-[var(--color-border-strong)]'"
                                                class="flex min-h-11 cursor-pointer items-start gap-3 rounded-lg border p-3.5 transition"
                                            >
                                                <input
                                                    id="audience-{{ $value }}"
                                                    type="radio"
                                                    name="audience"
                                                    value="{{ $value }}"
                                                    x-model="audience"
                                                    aria-labelledby="audience-{{ $value }}-label"
                                                    aria-describedby="audience-{{ $value }}-meta"
                                                    class="mt-0.5 border-line text-brand focus:ring-brand"
                                                >
                                                <span class="min-w-0 flex-1">
                                                    <span id="audience-{{ $value }}-label" class="block text-sm font-semibold text-ink">{{ $option['label'] }}</span>
                                                    <span id="audience-{{ $value }}-meta" class="mt-0.5 block text-xs tabular-nums text-muted">{{ $option['meta'] }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <x-input-error :messages="$errors->get('audience')" class="mt-2" />

                                    <div x-show="audience === 'customer'" x-cloak class="mt-3">
                                        <x-input-label for="email" :value="__('Customer email')" class="!text-ink" />
                                        <x-text-input
                                            id="email"
                                            class="mt-1.5"
                                            type="email"
                                            name="email"
                                            :value="old('email')"
                                            x-model="email"
                                            placeholder="customer@example.com"
                                            autocomplete="off"
                                            x-bind:required="audience === 'customer'"
                                            aria-describedby="email-hint"
                                        />
                                        <p id="email-hint" class="mt-1.5 text-xs text-muted">The email address of an active customer account.</p>
                                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                                    </div>
                                </fieldset>
                            </section>

                            {{-- Step 2 --}}
                            <section x-ref="opens" class="flex gap-4 px-5 py-5 sm:gap-5 sm:px-7 sm:py-6">
                                <span class="grid size-7 shrink-0 place-items-center rounded-full bg-brand text-xs font-bold text-white" aria-hidden="true">2</span>
                                <div class="min-w-0 flex-1 space-y-4">
                                    <h2 id="step-opens" class="text-[15px] font-semibold text-ink">What it opens on tap</h2>

                                    <div role="radiogroup" aria-labelledby="step-opens" class="grid grid-cols-2 gap-1 rounded-lg bg-[var(--color-paper)] p-1 sm:grid-cols-4">
                                        @foreach ($opensChoices as $kind => $label)
                                            <button
                                                type="button"
                                                role="radio"
                                                :aria-checked="(opens === @js($kind)).toString()"
                                                @click="setOpens(@js($kind))"
                                                :class="opens === @js($kind) ? 'bg-surface text-brand shadow-[0_1px_3px_rgb(24_44_72/12%)]' : 'text-muted hover:text-ink'"
                                                class="min-h-10 rounded-md text-[13px] font-semibold transition"
                                            >{{ $label }}</button>
                                        @endforeach
                                    </div>

                                    <div x-show="opens === 'home'" class="flex items-center gap-3 rounded-lg border border-line-soft bg-[var(--color-sunken)] p-3.5">
                                        <span class="grid size-9 shrink-0 place-items-center rounded-md bg-[var(--color-info-wash)] text-brand" aria-hidden="true">
                                            <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1Z" /></svg>
                                        </span>
                                        <p class="text-sm leading-6 text-muted">Opens the JMMart home screen in the app and the home page in browsers.</p>
                                    </div>

                                    <div x-show="opens === 'store' && ! target" x-cloak>
                                        <x-marketplace.link-search type="stores" id="store_search" label="Store" placeholder="Search active stores by name" on-pick="selectTarget" />
                                    </div>

                                    <div x-show="opens === 'product' && ! target" x-cloak class="space-y-3">
                                        <div x-show="! storeFilter">
                                            <x-marketplace.link-search type="stores" id="store_filter_search" label="From store (optional)" placeholder="Any store" on-pick="setStoreFilter" />
                                        </div>
                                        <div x-show="storeFilter" class="flex flex-wrap items-center gap-2">
                                            <span class="text-xs font-semibold text-ink">From store</span>
                                            <span class="inline-flex min-h-8 items-center gap-1 rounded-full border border-[var(--color-info-line)] bg-[var(--color-info-wash)] pl-3 pr-1 text-xs font-semibold text-brand">
                                                <span x-text="storeFilter?.name"></span>
                                                <button type="button" @click="clearStoreFilter()" class="grid size-6 place-items-center rounded-full hover:bg-surface" aria-label="Show products from every store">
                                                    <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12" /></svg>
                                                </button>
                                            </span>
                                        </div>
                                        <x-marketplace.link-search type="products" id="product_search" label="Product" placeholder="Search published products by name" on-pick="selectTarget" />
                                    </div>

                                    <div x-show="target" x-cloak class="flex items-center gap-3.5 rounded-lg border border-[var(--color-info-line)] bg-[var(--color-info-wash)]/60 p-3">
                                        <template x-if="target?.image">
                                            <img :src="target.image" alt="" class="size-14 shrink-0 rounded-md border border-line bg-white object-contain" referrerpolicy="no-referrer">
                                        </template>
                                        <template x-if="target && ! target.image">
                                            <span class="grid size-14 shrink-0 place-items-center rounded-md bg-surface text-sm font-bold text-brand ring-1 ring-inset ring-[var(--color-info-line)]" aria-hidden="true" x-text="target.name.split(/\s+/).slice(0, 2).map((word) => word[0]).join('').toUpperCase()"></span>
                                        </template>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-ink" x-text="target?.name"></p>
                                            <p class="truncate text-[13px] text-muted" x-text="target?.kind === 'product' ? target.store + ' · ' + target.price : (target?.city || 'Store page')"></p>
                                            <p class="mt-0.5 text-xs font-semibold text-brand" x-text="target?.kind === 'product' ? 'Opens the product screen' : 'Opens the store screen'"></p>
                                        </div>
                                        <button type="button" @click="clearTarget(); url = ''" class="inline-flex min-h-10 shrink-0 items-center rounded-md px-3 text-[13px] font-semibold text-muted transition hover:bg-surface hover:text-ink" :aria-label="target?.kind === 'product' ? 'Change product' : 'Change store'">Change</button>
                                    </div>

                                    <div x-show="opens === 'other'" x-cloak class="space-y-3">
                                        <div class="flex flex-wrap gap-1.5" aria-label="Common pages">
                                            @foreach ($quickLinks as $preset)
                                                <button
                                                    type="button"
                                                    @click="url = @js($preset['url']); activeTemplate = null"
                                                    :class="url === @js($preset['url'])
                                                        ? 'border-brand bg-[var(--color-info-wash)] text-brand'
                                                        : 'border-line bg-surface text-ink hover:border-[var(--color-border-strong)]'"
                                                    class="inline-flex min-h-9 items-center rounded-md border px-3 text-[13px] font-semibold transition"
                                                >{{ $preset['label'] }}</button>
                                            @endforeach
                                        </div>
                                        <div>
                                            <x-input-label for="other_link" :value="__('JMMart link')" class="!text-ink" />
                                            <x-text-input
                                                id="other_link"
                                                class="mt-1.5"
                                                type="url"
                                                x-model="url"
                                                @input="activeTemplate = null"
                                                placeholder="{{ route('catalog.top-products') }}"
                                                aria-describedby="other-link-hint"
                                            />
                                            <p id="other-link-hint" class="mt-1.5 text-xs text-muted">Pages without an app screen open in the browser.</p>
                                        </div>
                                    </div>

                                    <p x-show="linkError" x-cloak class="text-sm font-medium text-danger" role="alert" x-text="linkError"></p>
                                    <x-input-error :messages="$errors->get('url')" class="mt-2" />
                                </div>
                            </section>

                            {{-- Step 3 --}}
                            <section class="flex gap-4 px-5 py-5 sm:gap-5 sm:px-7 sm:py-6">
                                <span class="grid size-7 shrink-0 place-items-center rounded-full bg-brand text-xs font-bold text-white" aria-hidden="true">3</span>
                                <div class="min-w-0 flex-1 space-y-4">
                                    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                                        <h2 id="step-message" class="text-[15px] font-semibold text-ink">Message</h2>
                                        <div class="flex flex-wrap items-center gap-1.5" aria-label="Start from a template">
                                            <span class="mr-0.5 text-xs text-muted">Start from</span>
                                            @foreach ($templates as $template)
                                                <button
                                                    type="button"
                                                    @click="applyTemplate(@js($template))"
                                                    :aria-pressed="(activeTemplate === @js($template['key'])).toString()"
                                                    :class="activeTemplate === @js($template['key']) ? 'border-brand bg-[var(--color-info-wash)] text-brand' : 'border-line bg-surface text-ink hover:border-[var(--color-border-strong)]'"
                                                    class="inline-flex min-h-8 items-center rounded-full border px-3 text-xs font-semibold transition"
                                                >{{ $template['label'] }}</button>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div>
                                        <div class="flex items-baseline justify-between gap-2">
                                            <x-input-label for="title" :value="__('Title')" class="!text-ink" />
                                            <span class="text-xs tabular-nums text-muted" :class="Array.from(title).length > 55 ? '!text-[var(--color-warning-text)]' : ''" aria-hidden="true"><span x-text="Array.from(title).length">{{ mb_strlen((string) old('title')) }}</span>/60</span>
                                        </div>
                                        <x-text-input
                                            id="title"
                                            class="mt-1.5"
                                            name="title"
                                            :value="old('title')"
                                            x-model="title"
                                            @input="activeTemplate = null"
                                            maxlength="60"
                                            placeholder="e.g. Weekend flash sale is live"
                                            required
                                        />
                                        <p x-show="target" x-cloak class="mt-1.5 text-xs text-muted" x-text="target?.kind === 'product' ? 'An empty title and message are filled from the product and its store. Edit freely.' : 'An empty title is filled from the store. Edit freely.'"></p>
                                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                                    </div>

                                    <div>
                                        <div class="flex items-baseline justify-between gap-2">
                                            <x-input-label for="body" :value="__('Message')" class="!text-ink" />
                                            <span class="text-xs tabular-nums text-muted" :class="Array.from(body).length > 180 ? '!text-[var(--color-warning-text)]' : ''" aria-hidden="true"><span x-text="Array.from(body).length">{{ mb_strlen((string) old('body')) }}</span>/200</span>
                                        </div>
                                        <textarea
                                            id="body"
                                            name="body"
                                            rows="3"
                                            maxlength="200"
                                            required
                                            x-model="body"
                                            @input="activeTemplate = null"
                                            placeholder="Write a concise update for the lock screen and notification bell…"
                                            class="field-control mt-1.5 resize-y"
                                        >{{ old('body') }}</textarea>
                                        <x-input-error :messages="$errors->get('body')" class="mt-2" />
                                    </div>
                                </div>
                            </section>

                            {{-- Step 4 --}}
                            <section class="flex gap-4 px-5 py-5 sm:gap-5 sm:px-7 sm:py-6">
                                <span class="grid size-7 shrink-0 place-items-center rounded-full bg-brand text-xs font-bold text-white" aria-hidden="true">4</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                        <div class="flex items-center gap-2">
                                            <h2 id="step-image" class="text-[15px] font-semibold text-ink">Image</h2>
                                            <span x-cloak x-show="usingProductPhoto()" class="rounded-full bg-[var(--color-success-wash)] px-2 py-0.5 text-[11px] font-semibold text-success ring-1 ring-inset ring-[var(--color-success-line)]">Using product photo</span>
                                            <span x-cloak x-show="imagePreview && ! usingProductPhoto()" class="rounded-full bg-[var(--color-info-wash)] px-2 py-0.5 text-[11px] font-semibold text-brand ring-1 ring-inset ring-[var(--color-info-line)]">Custom image</span>
                                        </div>
                                        <span class="text-xs text-muted">Optional · JPEG, PNG, or WebP · 2 MB max</span>
                                    </div>
                                    <p class="mt-1 text-xs leading-5 text-muted">Choosing a product uses its main photo. Upload or paste an image to use your own instead; switching product keeps it. Wide images fill the notification best: 1024×512 (2:1), text away from the edges.</p>

                                    <input
                                        x-ref="imageInput"
                                        id="campaign_image"
                                        type="file"
                                        name="campaign_image"
                                        accept="image/jpeg,image/png,image/webp"
                                        class="hidden"
                                        @change="handleImageInput($event)"
                                    >

                                    <div class="mt-2.5 grid gap-3 sm:grid-cols-[minmax(0,1fr)_15rem]">
                                        <div>
                                            <button
                                                type="button"
                                                @click="$refs.imageInput.click()"
                                                @dragover.prevent="imageDrag = true"
                                                @dragleave.prevent="imageDrag = false"
                                                @drop.prevent="handleImageDrop($event)"
                                                :class="imageDrag ? 'border-brand bg-[var(--color-info-wash)]' : 'border-[var(--color-border-strong)] bg-[var(--color-sunken)]/70 hover:border-brand hover:bg-[var(--color-info-wash)]/50'"
                                                class="relative flex min-h-40 w-full flex-col items-center justify-center overflow-hidden rounded-lg border border-dashed text-center transition"
                                            >
                                                <template x-if="! imagePreview || imageFailed">
                                                    <span class="flex flex-col items-center px-4 py-6">
                                                        <span class="grid size-10 place-items-center rounded-full bg-surface text-brand ring-1 ring-inset ring-line" aria-hidden="true">
                                                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2" /><circle cx="8.5" cy="9.5" r="1.5" /><path d="m21 15-5-5L5 20" /></svg>
                                                        </span>
                                                        <span class="mt-2 text-sm font-semibold text-ink">Drop a campaign image</span>
                                                        <span class="mt-0.5 text-xs text-muted">or click to browse · 2:1 works best</span>
                                                    </span>
                                                </template>
                                                <template x-if="imagePreview && ! imageFailed">
                                                    <span class="block w-full">
                                                        <img
                                                            :src="imagePreview"
                                                            alt="Campaign image preview"
                                                            class="aspect-[2/1] w-full object-cover"
                                                            x-on:error="imageFailed = true"
                                                            referrerpolicy="no-referrer"
                                                        >
                                                        <span class="absolute inset-x-0 bottom-0 flex items-end justify-between gap-3 bg-gradient-to-t from-[#0b1526]/90 via-[#0b1526]/45 to-transparent px-3.5 pb-3 pt-10 text-left">
                                                            <span class="min-w-0 text-white">
                                                                <span class="block truncate text-xs font-semibold" x-text="imageName || (usingProductPhoto() ? 'Product photo' : 'External image')"></span>
                                                                <span class="block text-[11px] text-white/75">Replace or remove below</span>
                                                            </span>
                                                        </span>
                                                    </span>
                                                </template>
                                            </button>

                                            <div x-show="imagePreview && ! imageFailed" x-cloak class="mt-2 flex flex-wrap items-center gap-2">
                                                <button type="button" @click="$refs.imageInput.click()" class="inline-flex min-h-8 items-center rounded-md border border-line bg-surface px-2.5 text-xs font-semibold text-ink transition hover:border-[var(--color-border-strong)] hover:bg-canvas">Replace</button>
                                                <button type="button" @click="removeImage()" class="inline-flex min-h-8 items-center rounded-md px-2 py-1 text-xs font-semibold text-danger transition hover:bg-[var(--color-danger-wash)]">Remove</button>
                                                <span class="text-xs text-muted">Uploads take priority over a pasted URL.</span>
                                            </div>
                                            <button
                                                type="button"
                                                x-cloak
                                                x-show="productPhoto() && ! usingProductPhoto()"
                                                @click="useProductPhoto()"
                                                class="mt-2 inline-flex min-h-8 items-center rounded-md border border-[var(--color-info-line)] bg-[var(--color-info-wash)] px-2.5 text-xs font-semibold text-brand transition hover:border-brand"
                                            >Use product photo</button>

                                            <p x-show="imageFailed" x-cloak class="mt-2 text-xs font-medium text-danger">That image could not load. Check the URL or upload a file instead.</p>
                                            <p x-show="imageError" x-cloak class="mt-2 text-xs font-medium text-danger" x-text="imageError"></p>
                                            <x-input-error :messages="$errors->get('campaign_image')" class="mt-2" />
                                        </div>

                                        <div>
                                            <x-input-label for="image_url" :value="__('Or paste an image URL')" class="!text-ink" />
                                            <x-text-input
                                                id="image_url"
                                                class="mt-1.5"
                                                type="url"
                                                name="image_url"
                                                :value="old('image_url')"
                                                x-model="imageUrl"
                                                @input="useExternalImage()"
                                                placeholder="https://…/campaign.jpg"
                                                aria-describedby="image-url-hint"
                                            />
                                            <p id="image-url-hint" class="mt-1.5 text-xs leading-5 text-muted">Use a public HTTPS image on a host that allows hotlinking.</p>
                                            <x-input-error :messages="$errors->get('image_url')" class="mt-2" />
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <aside class="space-y-4 xl:sticky xl:top-24" aria-label="Preview and send">
                            <div class="content-card p-5">
                                <div class="flex items-center justify-between gap-3">
                                    <h2 class="text-sm font-semibold text-ink">Preview</h2>
                                    <div role="tablist" aria-label="Preview on" class="flex gap-0.5 rounded-md bg-[var(--color-paper)] p-0.5">
                                        @foreach (['phone' => 'Phone', 'browser' => 'Browser', 'bell' => 'Bell'] as $device => $label)
                                            <button
                                                type="button"
                                                role="tab"
                                                :aria-selected="(previewDevice === @js($device)).toString()"
                                                @click="previewDevice = @js($device)"
                                                :class="previewDevice === @js($device) ? 'bg-surface text-ink shadow-[0_1px_2px_rgb(24_44_72/8%)]' : 'text-muted hover:text-ink'"
                                                class="min-h-8 rounded px-2.5 text-xs font-semibold transition"
                                            >{{ $label }}</button>
                                        @endforeach
                                    </div>
                                </div>

                                <template x-if="previewDevice === 'phone'">
                                    <div class="mt-4 space-y-2.5 rounded-[1.75rem] bg-[#1b2433] px-3 pb-5 pt-4">
                                        <p class="pb-1 text-center text-3xl font-light tabular-nums text-[#e9eef5]" aria-hidden="true">9:41</p>
                                        <div class="flex items-center gap-2.5 rounded-2xl bg-[#2a3446] px-3.5 py-3 text-[#f1f5f9]">
                                            <div class="min-w-0 flex-1">
                                                <p class="text-[11px] text-[#b8c6d8]">JMMart · now</p>
                                                <p class="truncate text-[13px] font-semibold" x-text="title.trim() || 'Notification title'"></p>
                                                <p class="truncate text-xs text-[#d5dee9]" x-text="body.trim() || 'Your message appears here as you type.'"></p>
                                            </div>
                                            <template x-if="imagePreview && ! imageFailed">
                                                <img :src="imagePreview" alt="" class="size-10 shrink-0 rounded-lg bg-white object-cover" referrerpolicy="no-referrer">
                                            </template>
                                        </div>
                                        <div class="space-y-2 rounded-2xl bg-[#2a3446] px-3.5 py-3 text-[#f1f5f9]">
                                            <p class="text-[11px] text-[#b8c6d8]">JMMart · expanded</p>
                                            <p class="break-words text-[13px] font-semibold" x-text="title.trim() || 'Notification title'"></p>
                                            <p class="break-words text-xs leading-5 text-[#d5dee9]" x-text="body.trim() || 'Your message appears here as you type.'"></p>
                                            <template x-if="imagePreview && ! imageFailed">
                                                <img :src="imagePreview" alt="Campaign image preview" class="aspect-[2/1] w-full rounded-xl bg-white object-contain" x-on:error="imageFailed = true" referrerpolicy="no-referrer">
                                            </template>
                                            <p class="text-xs font-semibold text-[#8fb7ff]" x-text="actionLabel()"></p>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="previewDevice === 'browser'">
                                    <div class="mt-4 space-y-2.5 rounded-lg bg-[#2b2b2b] p-4 text-[#f3f3f3]">
                                        <p class="text-[11px] text-[#cfcfcf]">Browser · {{ parse_url(config('app.url'), PHP_URL_HOST) }}</p>
                                        <p class="break-words text-sm font-semibold" x-text="title.trim() || 'Notification title'"></p>
                                        <p class="break-words text-[13px] text-[#d4d4d4]" x-text="body.trim() || 'Your message appears here as you type.'"></p>
                                        <template x-if="imagePreview && ! imageFailed">
                                            <img :src="imagePreview" alt="" class="aspect-[2/1] w-full rounded bg-white object-cover" referrerpolicy="no-referrer">
                                        </template>
                                        <p class="grid min-h-9 place-items-center rounded bg-[#3d3d3d] text-[13px] font-semibold" x-text="actionLabel()"></p>
                                        <p class="text-[11px] leading-4 text-[#bdbdbd]">Windows crops images to 2:1. macOS shows text only.</p>
                                    </div>
                                </template>

                                <template x-if="previewDevice === 'bell'">
                                    <div class="mt-4 flex items-start gap-2.5 rounded-lg border border-line bg-white px-3.5 py-3">
                                        <span class="mt-1.5 size-2 shrink-0 rounded-full bg-brand" aria-hidden="true"></span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-ink" x-text="title.trim() || 'Notification title'"></p>
                                            <p class="mt-0.5 line-clamp-2 break-words text-xs text-muted" x-text="body.trim() || 'Your message appears here as you type.'"></p>
                                            <p class="mt-1 text-[11px] text-muted">Just now</p>
                                        </div>
                                        <template x-if="imagePreview && ! imageFailed">
                                            <img :src="imagePreview" alt="" class="size-11 shrink-0 rounded-md border border-line object-cover" referrerpolicy="no-referrer">
                                        </template>
                                    </div>
                                </template>
                            </div>

                            <div class="content-card p-5">
                                <dl class="divide-y divide-line-soft text-[13px]">
                                    <div class="flex items-center justify-between gap-3 py-2">
                                        <dt class="text-muted">Goes to</dt>
                                        <dd class="truncate font-semibold text-ink" x-text="recipientLabel()"></dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3 py-2">
                                        <dt class="text-muted">Opens</dt>
                                        <dd class="max-w-[13rem] truncate font-semibold" :class="target ? 'text-brand' : 'text-ink'" x-text="opensLabel()"></dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3 py-2">
                                        <dt class="text-muted">Image</dt>
                                        <dd class="font-semibold text-ink" x-text="! imagePreview || imageFailed ? 'None' : (usingProductPhoto() ? 'Product photo' : 'Custom image')"></dd>
                                    </div>
                                </dl>
                                <button type="button" @click="openReview()" class="mt-3 hidden min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-brand text-[15px] font-semibold text-white transition hover:bg-[var(--color-accent-hover)] xl:flex">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z" /><path d="M22 2 11 13" /></svg>
                                    Review and send
                                </button>
                                <p class="mt-2.5 text-xs leading-5 text-muted">Goes out in batches. Unsent batches can be stopped from History; delivered alerts stay.</p>
                            </div>
                        </aside>

                        <div class="fixed inset-x-0 bottom-0 z-30 flex items-center gap-3 border-t border-line bg-surface px-4 pt-3 shadow-[0_-6px_18px_rgb(24_44_72/8%)] lg:left-[232px] xl:hidden" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px));">
                            <p class="min-w-0 flex-1 truncate text-xs text-muted">To <span class="font-semibold text-ink" x-text="recipientLabel()"></span></p>
                            <button type="button" @click="openReview()" class="inline-flex min-h-12 shrink-0 items-center rounded-lg bg-brand px-5 text-sm font-semibold text-white">Review and send</button>
                        </div>

                        <dialog
                            x-ref="review"
                            @close="reviewChecked = false"
                            aria-labelledby="review-title"
                            class="w-[min(40rem,calc(100vw-2rem))] rounded-2xl border border-line bg-surface p-0 text-ink shadow-[0_24px_60px_rgb(11_21_38/30%)] backdrop:bg-[#0b1526]/55"
                        >
                            <div class="space-y-5 p-6 sm:p-7">
                                <div>
                                    <h2 id="review-title" class="!text-[20px] !font-bold !leading-7 text-ink">Review before sending</h2>
                                    <p class="mt-1 text-sm text-muted">Delivered notifications can't be recalled.</p>
                                </div>

                                <div class="grid gap-5 sm:grid-cols-2">
                                    <div class="space-y-2 self-start rounded-2xl bg-[#1b2433] p-3 text-[#f1f5f9]">
                                        <div class="space-y-2 rounded-xl bg-[#2a3446] p-3">
                                            <p class="text-[11px] text-[#b8c6d8]">JMMart · now</p>
                                            <p class="break-words text-[13px] font-semibold" x-text="title.trim()"></p>
                                            <p class="break-words text-xs leading-5 text-[#d5dee9]" x-text="body.trim()"></p>
                                            <template x-if="imagePreview && ! imageFailed">
                                                <img :src="imagePreview" alt="" class="aspect-[2/1] w-full rounded-lg bg-white object-contain" referrerpolicy="no-referrer">
                                            </template>
                                            <p class="text-xs font-semibold text-[#8fb7ff]" x-text="actionLabel()"></p>
                                        </div>
                                    </div>
                                    <dl class="divide-y divide-line-soft text-sm">
                                        <div class="py-2.5"><dt class="text-xs text-muted">Goes to</dt><dd class="mt-0.5 font-semibold" x-text="recipientLabel()"></dd></div>
                                        <div class="py-2.5"><dt class="text-xs text-muted">Opens</dt><dd class="mt-0.5 break-words font-semibold" x-text="opensLabel()"></dd></div>
                                        <div class="py-2.5"><dt class="text-xs text-muted">Image</dt><dd class="mt-0.5 font-semibold" x-text="! imagePreview || imageFailed ? 'None' : (usingProductPhoto() ? 'Product photo' : 'Custom image')"></dd></div>
                                        <div class="py-2.5"><dt class="text-xs text-muted">Delivery</dt><dd class="mt-0.5 font-semibold">App, browsers and in-app bell</dd></div>
                                    </dl>
                                </div>

                                <label class="flex items-start gap-3 rounded-lg border border-[var(--color-warning-line)] bg-[var(--color-warning-wash)] px-4 py-3 text-sm leading-6 text-[var(--color-warning-text)]">
                                    <input type="checkbox" x-model="reviewChecked" class="mt-1 rounded border-[var(--color-warning-line)] text-[var(--color-warning-text)] focus:ring-[var(--color-warning)]">
                                    I checked the preview and want to send this now.
                                </label>

                                <div class="flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-end">
                                    <button type="button" @click="$refs.review.close()" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-line bg-surface px-4 text-sm font-semibold text-ink hover:bg-canvas">Back to editing</button>
                                    <button type="submit" x-bind:disabled="! reviewChecked || submitting" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand px-5 text-sm font-semibold text-white transition hover:bg-[var(--color-accent-hover)] disabled:cursor-not-allowed disabled:opacity-50">
                                        <span x-show="! submitting">Send notification</span>
                                        <span x-cloak x-show="submitting">Sending…</span>
                                    </button>
                                </div>
                            </div>
                        </dialog>
                    </form>
                </div>
            @else
                <section class="content-card" aria-labelledby="recent-notifications">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-5 py-4 sm:px-6">
                        <div>
                            <h2 id="recent-notifications" class="text-lg font-semibold text-ink">Recent notifications</h2>
                            <p class="mt-0.5 text-xs text-muted">Latest 10 broadcasts and their delivery status.</p>
                        </div>
                        <a href="{{ route('admin.announcements.create') }}" class="inline-flex min-h-10 items-center rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-[var(--color-accent-hover)]">New notification</a>
                    </div>

                    <ul class="divide-y divide-line">
                        @forelse ($announcements as $announcement)
                            @php
                                $status = $announcement->cancelled_at
                                    ? ['Cancelled', 'bg-canvas text-muted ring-line', 'bg-muted']
                                    : ($announcement->isFinished()
                                        ? ['Sent', 'bg-success/10 text-success ring-success/20', 'bg-success']
                                        : ['Sending', 'bg-blue-50 text-brand ring-brand/20', 'bg-brand']);
                                $audienceLabel = [
                                    'customers' => 'All customers',
                                    'stores' => 'All stores',
                                    'customer' => $announcement->user?->email ?? 'One customer',
                                ][$announcement->audience];
                                $sentCount = min($announcement->sent_count, $announcement->recipient_count);
                                $progress = $announcement->recipient_count > 0 ? min(100, $sentCount / $announcement->recipient_count * 100) : 0;
                                $linkPath = parse_url($announcement->url, PHP_URL_PATH) ?: '/';
                            @endphp
                            <li class="flex flex-col gap-4 px-5 py-4 transition-colors hover:bg-canvas/40 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                                <div class="flex min-w-0 flex-1 items-start gap-3.5">
                                    @if ($announcement->image_url)
                                        <img
                                            src="{{ $announcement->image_url }}"
                                            alt=""
                                            width="48"
                                            height="48"
                                            loading="lazy"
                                            referrerpolicy="no-referrer"
                                            class="size-12 shrink-0 rounded-lg border border-line object-cover"
                                        >
                                    @else
                                        <span class="mt-0.5 grid size-10 shrink-0 place-items-center rounded-md bg-[var(--color-sunken)] text-muted ring-1 ring-inset ring-[var(--color-line-soft)]" aria-hidden="true">
                                            @if ($announcement->audience === 'stores')
                                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10v10h16V10M3 10l2-6h14l2 6M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0" /></svg>
                                            @elseif ($announcement->audience === 'customer')
                                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.5" /><path d="M5 20v-1a7 7 0 0 1 14 0v1" /></svg>
                                            @else
                                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3" /><path d="M3.5 20v-1a5.5 5.5 0 0 1 11 0v1zM16 5.5a3 3 0 0 1 0 5.8M17 14a5 5 0 0 1 3.5 4.8v1" /></svg>
                                            @endif
                                        </span>
                                    @endif

                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-semibold text-ink">{{ $announcement->title }}</p>
                                            <span class="status-badge gap-1.5 {{ $status[1] }}">
                                                <span class="size-1.5 rounded-full {{ $status[2] }}" aria-hidden="true"></span>
                                                {{ $status[0] }}
                                            </span>
                                        </div>
                                        <p class="mt-0.5 max-w-3xl text-sm leading-6 text-muted">{{ $announcement->body }}</p>
                                        <p class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted">
                                            <span class="font-medium text-ink">{{ $audienceLabel }}</span>
                                            <span aria-hidden="true">·</span>
                                            <span class="tabular-nums">{{ $sentCount }}/{{ $announcement->recipient_count }} sent</span>
                                            @if ($linkPath !== '/')
                                                <span aria-hidden="true">·</span>
                                                <span class="truncate font-mono text-[11px] text-muted" title="{{ $announcement->url }}">{{ $linkPath }}</span>
                                            @endif
                                            <span aria-hidden="true">·</span>
                                            <span>{{ $announcement->sender?->name ?? 'Deleted admin' }}, {{ $announcement->created_at->diffForHumans() }}</span>
                                        </p>
                                        @if ($announcement->cancelled_at === null && ! $announcement->isFinished())
                                            <div class="mt-2.5 flex items-center gap-2">
                                                <div class="h-1 w-36 overflow-hidden rounded-full bg-[var(--color-sunken)]" aria-hidden="true">
                                                    <div class="h-full rounded-full bg-brand transition-[width]" style="width: {{ $progress }}%"></div>
                                                </div>
                                                <span class="text-[11px] text-muted">Sending…</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                @unless ($announcement->isFinished())
                                    <div class="shrink-0 sm:pl-4">
                                        <form method="POST" action="{{ route('admin.announcements.cancel', $announcement) }}" onsubmit="return confirm('Stop sending this notification? People already reached keep it.')">
                                            @csrf
                                            @method('PATCH')
                                            <x-danger-button>{{ __('Cancel') }}</x-danger-button>
                                        </form>
                                    </div>
                                @endunless
                            </li>
                        @empty
                            <li class="px-6 py-12 text-center">
                                <span class="mx-auto grid size-11 place-items-center rounded-full bg-[var(--color-sunken)] text-muted ring-1 ring-inset ring-[var(--color-line-soft)]" aria-hidden="true">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" /></svg>
                                </span>
                                <p class="mt-3 text-sm font-semibold text-ink">No notifications sent yet.</p>
                                <p class="mx-auto mt-1 max-w-sm text-xs text-muted">Broadcasts you send to customers or stores will be listed here with their delivery count.</p>
                            </li>
                        @endforelse
                    </ul>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
