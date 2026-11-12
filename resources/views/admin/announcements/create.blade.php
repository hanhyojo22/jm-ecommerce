<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="mt-1 marketplace-page-title">Send a notification</h1>
            <p class="mt-1.5 max-w-2xl text-sm text-muted">Broadcast a push alert and in-app notice to customers, active stores, or a single buyer account.</p>
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
                imageFailed: false,
                submitting: false,
                defaultUrl: @js(route('home')),
            }"
        >
            @if (session('status'))
                <div class="flex items-center gap-3 rounded-lg border border-[var(--color-success-line)] bg-[var(--color-success-wash)] px-4 py-3 text-sm font-medium text-success" role="status">
                    <svg class="size-5 shrink-0 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5" /></svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <section class="section-card" aria-labelledby="push-notification-heading">
                    <div class="flex items-start gap-3 border-b border-line-soft pb-4">
                        <span class="grid size-10 shrink-0 place-items-center rounded-md bg-[var(--color-info-wash)] text-brand ring-1 ring-inset ring-[var(--color-info-line)]" aria-hidden="true">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" /></svg>
                        </span>
                        <div class="min-w-0">
                            <h2 id="push-notification-heading" class="text-lg font-semibold text-ink">Push notification</h2>
                            <p class="mt-0.5 max-w-2xl text-sm leading-6 text-muted">Sent to the mobile app and to browsers with notifications turned on, and kept in each recipient's notifications. It cannot be recalled once sent.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.announcements.store') }}" @submit="submitting = true" class="mt-5 space-y-5">
                        @csrf

                        <fieldset>
                            <legend class="text-sm font-semibold text-ink">Send to</legend>
                            <div class="mt-2 grid gap-3 sm:grid-cols-3">
                                @foreach ($audiences as $value => $option)
                                    <label
                                        for="audience-{{ $value }}"
                                        :class="audience === '{{ $value }}'
                                            ? 'border-brand bg-[var(--color-info-wash)]/60 ring-1 ring-inset ring-brand'
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

                        <div>
                            <div class="flex items-baseline justify-between gap-2">
                                <x-input-label for="title" :value="__('Title')" class="!text-ink" />
                                <span class="text-xs tabular-nums text-muted" aria-hidden="true"><span x-text="Array.from(title).length">{{ mb_strlen((string) old('title')) }}</span>/60</span>
                            </div>
                            <x-text-input
                                id="title"
                                class="mt-1.5"
                                name="title"
                                :value="old('title')"
                                x-model="title"
                                maxlength="60"
                                placeholder="e.g. Weekend flash sale is live"
                                required
                            />
                            <x-input-error :messages="$errors->get('title')" class="mt-2" />
                        </div>

                        <div>
                            <div class="flex items-baseline justify-between gap-2">
                                <x-input-label for="body" :value="__('Message')" class="!text-ink" />
                                <span class="text-xs tabular-nums text-muted" aria-hidden="true"><span x-text="Array.from(body).length">{{ mb_strlen((string) old('body')) }}</span>/200</span>
                            </div>
                            <textarea
                                id="body"
                                name="body"
                                rows="3"
                                maxlength="200"
                                required
                                x-model="body"
                                placeholder="Write a concise update for the lock screen and notification bell…"
                                class="field-control mt-1.5 resize-y"
                            >{{ old('body') }}</textarea>
                            <x-input-error :messages="$errors->get('body')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="image_url" :value="__('Campaign image (optional)')" class="!text-ink" />
                            <x-text-input id="image_url" class="mt-1.5" type="url" name="image_url" :value="old('image_url')" x-model="imageUrl" @input="imageFailed = false" placeholder="https://…/campaign.jpg" aria-describedby="image-url-hint" />
                            <p id="image-url-hint" class="mt-1.5 text-xs text-muted">Use a public HTTPS JPEG, PNG, or WebP under 1 MB. Landscape artwork works best in expanded Android notifications.</p>
                            <x-input-error :messages="$errors->get('image_url')" class="mt-2" />
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
                                placeholder="{{ route('home') }}"
                                aria-describedby="url-hint"
                            />
                            <p id="url-hint" class="mt-1.5 text-xs text-muted">A JMMart page the notification opens on the web. Leave empty for the home page.</p>
                            <div class="mt-2.5 flex flex-wrap items-center gap-1.5" aria-label="Quick link presets">
                                <span class="mr-1 text-xs font-medium text-muted">Quick fill:</span>
                                @foreach ($quickLinks as $preset)
                                    <button
                                        type="button"
                                        @click="url = @js($preset['url'])"
                                        :class="url === @js($preset['url'])
                                            ? 'border-brand bg-[var(--color-info-wash)] text-brand'
                                            : 'border-line bg-canvas text-muted hover:border-[var(--color-border-strong)] hover:text-ink'"
                                        class="inline-flex min-h-8 items-center rounded-md border px-2.5 py-1 text-xs font-semibold transition"
                                    >{{ $preset['label'] }}</button>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('url')" class="mt-2" />
                        </div>

                        <div class="flex flex-col gap-3 border-t border-line-soft pt-4 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-xs text-muted">
                                <span x-show="audience === 'customers'">Queues immediately for <strong class="font-semibold tabular-nums text-ink">{{ number_format($customersCount) }}</strong> active {{ Str::plural('customer', $customersCount) }}.</span>
                                <span x-cloak x-show="audience === 'stores'">Queues immediately for <strong class="font-semibold tabular-nums text-ink">{{ number_format($storesCount) }}</strong> active store {{ Str::plural('member', $storesCount) }}.</span>
                                <span x-cloak x-show="audience === 'customer'">Queues immediately for <strong class="font-semibold text-ink" x-text="email.trim() || 'the selected customer'"></strong>.</span>
                            </p>
                            <x-primary-button x-bind:disabled="submitting" class="w-full sm:w-auto">
                                <span x-show="!submitting">Send notification</span>
                                <span x-cloak x-show="submitting">Sending…</span>
                            </x-primary-button>
                        </div>
                    </form>
                </section>

                <aside class="content-card" aria-label="Notification preview">
                    <div class="flex items-center justify-between gap-2 border-b border-line px-5 py-3.5">
                        <h2 class="text-sm font-semibold text-ink">Live preview</h2>
                        <span class="rounded-full bg-[var(--color-sunken)] px-2.5 py-0.5 text-[11px] font-semibold text-muted ring-1 ring-inset ring-[var(--color-line-soft)]">Recipient view</span>
                    </div>

                    <div class="space-y-4 p-5">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">Push alert</p>
                            <div class="mt-2 rounded-lg border border-line bg-canvas/70 p-3.5">
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
                                <template x-if="imageUrl.startsWith('https://') && !imageFailed">
                                    <img :src="imageUrl" alt="Campaign image preview" class="mt-3 aspect-video w-full rounded-md object-cover" x-on:error="imageFailed = true" referrerpolicy="no-referrer">
                                </template>
                                <p x-cloak x-show="imageFailed" class="mt-2 text-xs text-muted">Image unavailable. The notification will still show its title and message.</p>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">In-app notification bell</p>
                            <div class="mt-2 overflow-hidden rounded-lg border border-line bg-white">
                                <div class="flex items-start gap-2.5 bg-blue-50/40 px-3.5 py-3">
                                    <span class="mt-1.5 size-2 shrink-0 rounded-full bg-brand" aria-hidden="true"></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-ink" x-text="title.trim() || 'Notification title'">Notification title</p>
                                        <p class="mt-0.5 line-clamp-2 break-words text-xs text-muted" x-text="body.trim() || 'Your message preview will appear here as you type.'">Your message preview will appear here as you type.</p>
                                        <p class="mt-1 text-[11px] text-muted">Just now</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <dl class="divide-y divide-line-soft border-t border-line-soft pt-3 text-xs">
                            <div class="flex items-center justify-between gap-3 py-2">
                                <dt class="text-muted">Audience</dt>
                                <dd class="truncate font-semibold text-ink" x-text="audience === 'customers' ? 'All customers' : (audience === 'stores' ? 'All stores' : (email.trim() || 'One customer'))">All customers</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 py-2">
                                <dt class="text-muted">Opens on tap</dt>
                                <dd class="max-w-[13rem] truncate font-medium text-brand" x-text="url.trim() || defaultUrl">{{ route('home') }}</dd>
                            </div>
                        </dl>
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
                            $linkPath = parse_url($announcement->url, PHP_URL_PATH) ?: '/';
                        @endphp
                        <li class="flex flex-col gap-4 px-5 py-4 transition-colors hover:bg-canvas/40 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                            <div class="flex min-w-0 flex-1 items-start gap-3.5">
                                <span class="mt-0.5 grid size-9 shrink-0 place-items-center rounded-md bg-[var(--color-sunken)] text-muted ring-1 ring-inset ring-[var(--color-line-soft)]" aria-hidden="true">
                                    @if ($announcement->audience === 'stores')
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10v10h16V10M3 10l2-6h14l2 6M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0" /></svg>
                                    @elseif ($announcement->audience === 'customer')
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.5" /><path d="M5 20v-1a7 7 0 0 1 14 0v1" /></svg>
                                    @else
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3" /><path d="M3.5 20v-1a5.5 5.5 0 0 1 11 0v1zM16 5.5a3 3 0 0 1 0 5.8M17 14a5 5 0 0 1 3.5 4.8v1" /></svg>
                                    @endif
                                </span>

                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-ink">{{ $announcement->title }}</p>
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
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center gap-3 sm:pl-4">
                                <span class="status-badge gap-1.5 {{ $status[1] }}">
                                    <span class="size-1.5 rounded-full {{ $status[2] }}" aria-hidden="true"></span>
                                    {{ $status[0] }}
                                </span>
                                @unless ($announcement->isFinished())
                                    <form method="POST" action="{{ route('admin.announcements.cancel', $announcement) }}" onsubmit="return confirm('Stop sending this notification? People already reached keep it.')">
                                        @csrf
                                        @method('PATCH')
                                        <x-danger-button>{{ __('Cancel') }}</x-danger-button>
                                    </form>
                                @endunless
                            </div>
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
