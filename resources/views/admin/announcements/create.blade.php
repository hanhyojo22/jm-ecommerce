<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="mt-1 marketplace-page-title">Send a notification</h1>
                <p class="mt-1.5 max-w-2xl text-sm text-muted">Broadcast a push alert and in-app notice to customers, active stores, or a single buyer account.</p>
            </div>

            <dl class="flex flex-wrap gap-2">
                <div class="flex min-w-[8.5rem] items-center gap-3 rounded-lg border border-line bg-surface px-3.5 py-2.5">
                    <span class="grid size-8 shrink-0 place-items-center rounded-md bg-[var(--color-info-wash)] text-brand" aria-hidden="true">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3" /><path d="M3.5 20v-1a5.5 5.5 0 0 1 11 0v1zM16 5.5a3 3 0 0 1 0 5.8M17 14a5 5 0 0 1 3.5 4.8v1" /></svg>
                    </span>
                    <div>
                        <dt class="text-[11px] font-medium text-muted">Customers</dt>
                        <dd class="text-sm font-semibold tabular-nums text-ink">{{ number_format($audienceCounts['customers'] ?? 0) }}</dd>
                    </div>
                </div>
                <div class="flex min-w-[8.5rem] items-center gap-3 rounded-lg border border-line bg-surface px-3.5 py-2.5">
                    <span class="grid size-8 shrink-0 place-items-center rounded-md bg-[var(--color-info-wash)] text-brand" aria-hidden="true">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10v10h16V10M3 10l2-6h14l2 6M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0" /></svg>
                    </span>
                    <div>
                        <dt class="text-[11px] font-medium text-muted">Store members</dt>
                        <dd class="text-sm font-semibold tabular-nums text-ink">{{ number_format($audienceCounts['stores'] ?? 0) }}</dd>
                    </div>
                </div>
            </dl>
        </div>
    </x-slot>

    @php
        $customersCount = $audienceCounts['customers'] ?? 0;
        $storesCount = $audienceCounts['stores'] ?? 0;
        $audiences = [
            'customers' => [
                'label' => 'All customers',
                'meta' => number_format($customersCount).' active '.Str::plural('customer', $customersCount),
            ],
            'stores' => [
                'label' => 'All stores',
                'meta' => number_format($storesCount).' active '.Str::plural('member', $storesCount),
            ],
            'customer' => [
                'label' => 'One customer',
                'meta' => 'By email address',
            ],
        ];
        $quickLinks = [
            ['label' => 'Home', 'url' => ''],
            ['label' => 'Browse catalog', 'url' => route('catalog.index')],
            ['label' => 'Top products', 'url' => route('catalog.top-products')],
            ['label' => 'Stores', 'url' => route('catalog.stores')],
            ['label' => 'Cart', 'url' => route('cart.index')],
        ];
        $templates = [
            [
                'key' => 'announcement',
                'label' => 'Announcement',
                'description' => 'A general platform update or news for everyone.',
                'title' => 'A quick update from JMMart',
                'body' => 'We have made a few improvements to make shopping at JMMart easier. Take a look around and see what is new.',
                'url' => route('home'),
            ],
            [
                'key' => 'product-ad',
                'label' => 'Product ad',
                'description' => 'Promote new arrivals and fresh finds from sellers.',
                'title' => 'Fresh finds just landed',
                'body' => 'Discover new arrivals from JMMart sellers. Browse the latest products and find something to love.',
                'url' => route('catalog.index'),
            ],
            [
                'key' => 'flash-sale',
                'label' => 'Flash sale',
                'description' => 'Create urgency for limited-time deals.',
                'title' => 'Flash sale is live',
                'body' => 'Limited-time deals on top products. Shop now before your favorite items sell out.',
                'url' => route('catalog.top-products'),
            ],
            [
                'key' => 'store-spotlight',
                'label' => 'Store spotlight',
                'description' => 'Drive buyers to trusted local sellers.',
                'title' => 'Support local sellers',
                'body' => 'Explore trusted JMMart stores and shop their latest offers, all in one place.',
                'url' => route('catalog.stores'),
            ],
            [
                'key' => 'cart-reminder',
                'label' => 'Cart reminder',
                'description' => 'Nudge buyers back to an unfinished cart.',
                'title' => 'Still thinking it over?',
                'body' => 'Your cart is waiting. Complete your order before the items you picked sell out.',
                'url' => route('cart.index'),
            ],
        ];
    @endphp

    <div class="page-shell">
        <div
            class="page-container space-y-6"
            x-data="{
                audience: @js(old('audience', 'customers')),
                email: @js(old('email', '')),
                title: @js(old('title', '')),
                body: @js(old('body', '')),
                url: @js(old('url', '')),
                imageUrl: @js(old('image_url', '')),
                imagePreview: @js(old('image_url', '')),
                imageName: '',
                imageFailed: false,
                imageError: '',
                imageDrag: false,
                submitting: false,
                defaultUrl: @js(route('home')),
                activeTemplate: null,
                stageImage(file) {
                    if (!file) {
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
                },
            }"
        >
            @if (session('status'))
                <div class="flex items-center gap-3 rounded-lg border border-[var(--color-success-line)] bg-[var(--color-success-wash)] px-4 py-3 text-sm font-medium text-success" role="status">
                    <svg class="size-5 shrink-0 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5" /></svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_23rem]">
                <div class="min-w-0">
                    <form method="POST" action="{{ route('admin.announcements.store') }}" enctype="multipart/form-data" @submit="submitting = true" class="content-card">
                        @csrf

                        <div class="flex items-center gap-3 border-b border-line px-5 py-4 sm:px-6">
                            <span class="grid size-9 shrink-0 place-items-center rounded-md bg-[var(--color-info-wash)] text-brand ring-1 ring-inset ring-[var(--color-info-line)]" aria-hidden="true">
                                <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" /></svg>
                            </span>
                            <div class="min-w-0">
                                <h2 class="text-lg font-semibold text-ink">Compose broadcast</h2>
                                <p class="mt-0.5 text-sm text-muted">It is delivered to the mobile app, browsers with notifications on, and the in-app bell. It cannot be recalled once sent.</p>
                            </div>
                        </div>

                        <div class="space-y-6 px-5 py-5 sm:px-6 sm:py-6">
                            <fieldset>
                                <legend class="flex items-baseline justify-between gap-3">
                                    <span class="text-sm font-semibold text-ink">Who should receive it?</span>
                                    <span class="text-xs text-muted">Choose one audience</span>
                                </legend>
                                <div class="mt-2.5 grid gap-2.5 sm:grid-cols-3">
                                    @foreach ($audiences as $value => $option)
                                        <label
                                            for="audience-{{ $value }}"
                                            :class="audience === '{{ $value }}'
                                                ? 'border-brand bg-[var(--color-info-wash)]/70 ring-1 ring-inset ring-brand'
                                                : 'border-line bg-surface hover:border-[var(--color-border-strong)] hover:bg-canvas/60'"
                                            class="relative flex min-h-11 cursor-pointer items-start gap-3 rounded-lg border p-3.5 transition"
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
                                            <svg x-cloak x-show="audience === '{{ $value }}'" class="mt-0.5 size-4 shrink-0 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4.5 4.5L19 7" /></svg>
                                        </label>
                                    @endforeach
                                </div>
                                <x-input-error :messages="$errors->get('audience')" class="mt-2" />
                            </fieldset>

                            <div x-show="audience === 'customer'" x-cloak class="rounded-lg border border-line bg-canvas/60 p-4">
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
                                <p id="email-hint" class="mt-1.5 text-xs text-muted">Enter the email address of an active customer account.</p>
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>

                            <div class="border-t border-line-soft pt-6">
                                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                    <p class="text-sm font-semibold text-ink">Start with a template</p>
                                    <p class="text-xs text-muted">Fills the title, message, and link. Edit anything before sending.</p>
                                </div>
                                <div class="mt-2.5 grid gap-2 sm:grid-cols-2">
                                    @foreach ($templates as $template)
                                        <button
                                            type="button"
                                            @click="title = @js($template['title']); body = @js($template['body']); url = @js($template['url']); activeTemplate = @js($template['key'])"
                                            :aria-pressed="activeTemplate === @js($template['key'])"
                                            :class="activeTemplate === @js($template['key'])
                                                ? 'border-brand bg-[var(--color-info-wash)]/70 ring-1 ring-inset ring-brand'
                                                : 'border-line bg-surface hover:border-[var(--color-border-strong)] hover:bg-canvas/60'"
                                            class="group flex min-h-[4.5rem] items-start gap-3 rounded-lg border p-3 text-left transition {{ $loop->last ? 'sm:col-span-2' : '' }}"
                                        >
                                            <span
                                                :class="activeTemplate === @js($template['key'])
                                                    ? 'bg-brand text-white ring-brand'
                                                    : 'bg-[var(--color-sunken)] text-brand ring-[var(--color-line-soft)]'"
                                                class="grid size-9 shrink-0 place-items-center rounded-md ring-1 ring-inset transition-colors"
                                                aria-hidden="true"
                                            >
                                                @switch($template['key'])
                                                    @case('announcement')
                                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m3 11 18-5v12L3 14v-3Z" /><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6" /></svg>
                                                        @break
                                                    @case('product-ad')
                                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M20.6 13.4 12 22 2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8Z" /><circle cx="7" cy="7" r="1" /></svg>
                                                        @break
                                                    @case('flash-sale')
                                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8Z" /></svg>
                                                        @break
                                                    @case('store-spotlight')
                                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10v10h16V10M3 10l2-6h14l2 6M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0" /></svg>
                                                        @break
                                                    @default
                                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="20" r="1" /><circle cx="18" cy="20" r="1" /><path d="M2 3h2l2.4 11.2a2 2 0 0 0 2 1.6h8.7a2 2 0 0 0 2-1.6L21 7H6" /></svg>
                                                @endswitch
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="flex items-center gap-1.5">
                                                    <span class="text-sm font-semibold text-ink">{{ $template['label'] }}</span>
                                                    <svg x-cloak x-show="activeTemplate === @js($template['key'])" class="size-3.5 shrink-0 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4.5 4.5L19 7" /></svg>
                                                </span>
                                                <span class="mt-0.5 block text-xs leading-5 text-muted">{{ $template['description'] }}</span>
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <div class="border-t border-line-soft pt-6">
                                <div class="flex items-baseline justify-between gap-2">
                                    <x-input-label for="title" :value="__('Title')" class="!text-ink" />
                                    <span
                                        class="text-xs tabular-nums text-muted"
                                        :class="Array.from(title).length > 55 ? '!text-[var(--color-warning-text)]' : ''"
                                        aria-hidden="true"
                                    ><span x-text="Array.from(title).length">{{ mb_strlen((string) old('title')) }}</span>/60</span>
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
                                <div class="mt-2 h-1 overflow-hidden rounded-full bg-[var(--color-sunken)]" aria-hidden="true">
                                    <div
                                        class="h-full rounded-full transition-[width] duration-200"
                                        :class="Array.from(title).length > 55 ? 'bg-[var(--color-warning)]' : 'bg-brand'"
                                        :style="'width: ' + Math.min(100, Array.from(title).length / 60 * 100) + '%'"
                                    ></div>
                                </div>
                                <x-input-error :messages="$errors->get('title')" class="mt-2" />
                            </div>

                            <div>
                                <div class="flex items-baseline justify-between gap-2">
                                    <x-input-label for="body" :value="__('Message')" class="!text-ink" />
                                    <span
                                        class="text-xs tabular-nums text-muted"
                                        :class="Array.from(body).length > 180 ? '!text-[var(--color-warning-text)]' : ''"
                                        aria-hidden="true"
                                    ><span x-text="Array.from(body).length">{{ mb_strlen((string) old('body')) }}</span>/200</span>
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
                                <div class="mt-2 h-1 overflow-hidden rounded-full bg-[var(--color-sunken)]" aria-hidden="true">
                                    <div
                                        class="h-full rounded-full transition-[width] duration-200"
                                        :class="Array.from(body).length > 180 ? 'bg-[var(--color-warning)]' : 'bg-brand'"
                                        :style="'width: ' + Math.min(100, Array.from(body).length / 200 * 100) + '%'"
                                    ></div>
                                </div>
                                <x-input-error :messages="$errors->get('body')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="url" :value="__('Link (optional)')" class="!text-ink" />
                                <x-text-input
                                    id="url"
                                    class="mt-1.5"
                                    type="url"
                                    name="url"
                                    :value="old('url')"
                                    x-model="url"
                                    @input="activeTemplate = null"
                                    placeholder="{{ route('home') }}"
                                    aria-describedby="url-hint"
                                />
                                <p id="url-hint" class="mt-1.5 text-xs text-muted">A JMMart page the notification opens. Leave empty for the home page.</p>
                                <div class="mt-2.5 flex flex-wrap items-center gap-1.5" aria-label="Quick link presets">
                                    <span class="mr-1 text-xs font-medium text-muted">Quick fill:</span>
                                    @foreach ($quickLinks as $preset)
                                        <button
                                            type="button"
                                            @click="url = @js($preset['url']); activeTemplate = null"
                                            :class="url === @js($preset['url'])
                                                ? 'border-brand bg-[var(--color-info-wash)] text-brand'
                                                : 'border-line bg-canvas text-muted hover:border-[var(--color-border-strong)] hover:text-ink'"
                                            class="inline-flex min-h-8 items-center rounded-md border px-2.5 py-1 text-xs font-semibold transition"
                                        >{{ $preset['label'] }}</button>
                                    @endforeach
                                </div>
                                <x-input-error :messages="$errors->get('url')" class="mt-2" />
                            </div>

                            <div class="border-t border-line-soft pt-6">
                                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                    <x-input-label for="campaign_image" :value="__('Campaign image')" class="!mb-0 !text-ink" />
                                    <span class="text-xs text-muted">Optional · JPEG, PNG, or WebP · 2 MB max</span>
                                </div>
                                <p class="mt-1 text-xs leading-5 text-muted">A landscape image appears inside expanded Android notifications and adds a thumbnail in the in-app bell.</p>

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
                                                    <span class="mt-0.5 text-xs text-muted">or click to browse · 16:9 works best</span>
                                                </span>
                                            </template>
                                            <template x-if="imagePreview && ! imageFailed">
                                                <span class="block w-full">
                                                    <img
                                                        :src="imagePreview"
                                                        alt="Campaign image preview"
                                                        class="aspect-[16/9] w-full object-cover"
                                                        x-on:error="imageFailed = true"
                                                        referrerpolicy="no-referrer"
                                                    >
                                                    <span class="absolute inset-x-0 bottom-0 flex items-end justify-between gap-3 bg-gradient-to-t from-[#0b1526]/90 via-[#0b1526]/45 to-transparent px-3.5 pb-3 pt-10 text-left">
                                                        <span class="min-w-0 text-white">
                                                            <span class="block truncate text-xs font-semibold" x-text="imageName || 'External image'"></span>
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
                        </div>

                        <div class="flex flex-col gap-3 border-t border-line bg-canvas/50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <p class="text-xs leading-5 text-muted">
                                <span x-show="audience === 'customers'">Queues immediately for <strong class="font-semibold tabular-nums text-ink">{{ number_format($customersCount) }}</strong> active {{ Str::plural('customer', $customersCount) }}.</span>
                                <span x-cloak x-show="audience === 'stores'">Queues immediately for <strong class="font-semibold tabular-nums text-ink">{{ number_format($storesCount) }}</strong> active store {{ Str::plural('member', $storesCount) }}.</span>
                                <span x-cloak x-show="audience === 'customer'">Queues immediately for <strong class="font-semibold text-ink" x-text="email.trim() || 'the selected customer'"></strong>.</span>
                            </p>
                            <x-primary-button x-bind:disabled="submitting" class="w-full sm:w-auto">
                                <svg x-show="!submitting" class="-ml-0.5 mr-2 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z" /><path d="M22 2 11 13" /></svg>
                                <span x-show="!submitting">Send notification</span>
                                <span x-cloak x-show="submitting">Sending…</span>
                            </x-primary-button>
                        </div>
                    </form>
                </div>

                <aside class="space-y-4 xl:sticky xl:top-24" aria-label="Notification preview">
                    <div class="content-card">
                        <div class="flex items-center justify-between gap-2 border-b border-line px-5 py-3.5">
                            <h2 class="text-sm font-semibold text-ink">Live preview</h2>
                            <span class="rounded-full bg-[var(--color-sunken)] px-2.5 py-0.5 text-[11px] font-semibold text-muted ring-1 ring-inset ring-[var(--color-line-soft)]">Recipient view</span>
                        </div>

                        <div class="space-y-5 p-5">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">Push alert</p>
                                <div class="mt-2 rounded-xl border border-line bg-[var(--color-paper)] p-2.5">
                                    <div class="overflow-hidden rounded-lg border border-line bg-white p-3.5 shadow-[0_2px_8px_rgb(24_44_72/6%)]">
                                        <div class="flex items-start gap-3">
                                            <img src="{{ asset('images/notifications/announcement.png') }}" alt="" width="36" height="36" class="size-9 shrink-0 rounded-md border border-line bg-white object-contain p-1">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-xs font-semibold text-ink">JMMart</span>
                                                    <span class="text-[11px] text-muted">now</span>
                                                </div>
                                                <p class="mt-0.5 break-words text-sm font-semibold text-ink" x-text="title.trim() || 'Notification title'">Notification title</p>
                                                <p class="mt-0.5 break-words text-xs leading-5 text-muted" x-text="body.trim() || 'Your message preview will appear here as you type.'">Your message preview will appear here as you type.</p>
                                            </div>
                                        </div>
                                        <template x-if="imagePreview && ! imageFailed">
                                            <img :src="imagePreview" alt="Campaign image preview" class="mt-3 aspect-[16/9] w-full rounded-md object-cover" x-on:error="imageFailed = true" referrerpolicy="no-referrer">
                                        </template>
                                        <p x-cloak x-show="imagePreview && imageFailed" class="mt-2 rounded-md bg-[var(--color-warning-wash)] px-2.5 py-2 text-[11px] leading-4 text-[var(--color-warning-text)]">Image unavailable. The notification still shows its title and message.</p>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">In-app notification bell</p>
                                <div class="mt-2 overflow-hidden rounded-lg border border-line bg-white">
                                    <div class="flex items-start gap-2.5 bg-blue-50/40 px-3.5 py-3">
                                        <span class="mt-1.5 size-2 shrink-0 rounded-full bg-brand" aria-hidden="true"></span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-ink" x-text="title.trim() || 'Notification title'">Notification title</p>
                                            <p class="mt-0.5 line-clamp-2 break-words text-xs text-muted" x-text="body.trim() || 'Your message preview will appear here as you type.'">Your message preview will appear here as you type.</p>
                                            <p class="mt-1 text-[11px] text-muted">Just now</p>
                                        </div>
                                        <template x-if="imagePreview && ! imageFailed">
                                            <img :src="imagePreview" alt="" class="size-11 shrink-0 rounded-md border border-line object-cover" x-on:error="imageFailed = true" referrerpolicy="no-referrer">
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <dl class="divide-y divide-line-soft border-t border-line-soft pt-2 text-xs">
                                <div class="flex items-center justify-between gap-3 py-2">
                                    <dt class="text-muted">Audience</dt>
                                    <dd class="truncate font-semibold text-ink" x-text="audience === 'customers' ? 'All customers' : (audience === 'stores' ? 'All stores' : (email.trim() || 'One customer'))">All customers</dd>
                                </div>
                                <div class="flex items-center justify-between gap-3 py-2">
                                    <dt class="text-muted">Reaches</dt>
                                    <dd class="font-semibold tabular-nums text-ink" x-text="audience === 'customers' ? '{{ number_format($customersCount) }}' : (audience === 'stores' ? '{{ number_format($storesCount) }}' : '1')">{{ number_format($customersCount) }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-3 py-2">
                                    <dt class="text-muted">Opens on tap</dt>
                                    <dd class="max-w-[12rem] truncate font-medium text-brand" x-text="url.trim() || defaultUrl">{{ route('home') }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-3 py-2">
                                    <dt class="text-muted">Campaign image</dt>
                                    <dd class="font-medium text-ink" x-text="imagePreview && ! imageFailed ? 'Included' : 'None'">None</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <div class="rounded-lg border border-[var(--color-info-line)] bg-[var(--color-info-wash)] px-4 py-3.5">
                        <p class="flex items-center gap-2 text-xs font-semibold text-[var(--color-info)]">
                            <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 11v5M12 8h.01" /></svg>
                            Before you send
                        </p>
                        <p class="mt-1.5 text-xs leading-5 text-muted">Notifications go out in small batches. You can cancel the remaining batches from the list below, but anything already delivered stays with the recipient.</p>
                    </div>
                </aside>
            </div>

            <section class="content-card" aria-labelledby="recent-notifications">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-5 py-4 sm:px-6">
                    <div>
                        <h2 id="recent-notifications" class="text-lg font-semibold text-ink">Recent notifications</h2>
                        <p class="mt-0.5 text-xs text-muted">Latest 10 broadcasts and their delivery status.</p>
                    </div>
                    @if ($announcements->isNotEmpty())
                        <span class="rounded-full bg-[var(--color-sunken)] px-2.5 py-1 text-xs font-semibold tabular-nums text-muted ring-1 ring-inset ring-[var(--color-line-soft)]">{{ $announcements->count() }} {{ Str::plural('broadcast', $announcements->count()) }}</span>
                    @endif
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
        </div>
    </div>
</x-app-layout>
