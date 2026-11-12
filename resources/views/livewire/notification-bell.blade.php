<div data-notification-bell x-data="notificationBell('{{ auth()->id() }}')" wire:key="notification-bell" {{ $attributes }}>
    <div
        x-data="{
            open: false,
            pushConfigured: @js($pushConfigured),
            serverEndpointHashes: @js($pushEndpointHashes),
            push: {
                supported: true,
                secure: true,
                permission: 'checking',
                subscribed: @js($hasPushSubscription),
                synced: @js($hasPushSubscription),
                error: false,
                optedOut: false,
            },
            pushBusy: false,
            checkPush(wire) {
                return window.ensureWebPushSubscribed(wire, this.serverEndpointHashes)
                    .then((state) => { this.push = state; })
                    .catch(() => { this.push = { ...this.push, error: true }; });
            },
            enablePush(wire) {
                if (this.pushBusy) return;
                this.pushBusy = true;
                window.enableWebPush(wire, this.serverEndpointHashes)
                    .then((state) => { this.push = state; })
                    .catch(() => { this.push = { ...this.push, error: true }; })
                    .finally(() => { this.pushBusy = false; });
            },
            disablePush(wire) {
                if (this.pushBusy) return;
                this.pushBusy = true;
                window.unsubscribeWebPush(wire, { remember: true })
                    .then((state) => { this.push = state; })
                    .catch(() => { this.push = { ...this.push, error: true }; })
                    .finally(() => { this.pushBusy = false; });
            },
            retryPush(wire) {
                this.push = { ...this.push, error: false };
                this.checkPush(wire);
            },
        }"
        x-init="if (pushConfigured) { checkPush($wire) }"
        x-on:click.outside="if (open) { open = false; $wire.closeNotifications() }"
        x-on:keydown.escape.window="if (open) { open = false; $wire.closeNotifications() }"
        x-on:jmmart:pushsubscriptionchange.window="if (pushConfigured) { checkPush($wire) }"
        class="relative"
    >
        <button
            type="button"
            @click="open = !open; open ? $wire.openNotifications() : $wire.closeNotifications()"
            class="relative grid size-11 place-items-center rounded-md text-ink transition hover:bg-canvas focus-visible:ring-2 focus-visible:ring-brand"
            aria-label="Notifications{{ $unreadCount ? ', '.$unreadCount.' unread' : '' }}"
            :aria-expanded="open"
        >
            <svg class="size-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                <path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" />
                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
            </svg>
            @if ($unreadCount > 0)
                <span class="absolute -right-1 -top-1 grid h-[19px] min-w-[19px] place-items-center rounded-full border-2 border-white bg-brand px-[5px] text-xs font-semibold leading-none text-white">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
            @endif
        </button>

        <div
            x-cloak
            x-show="open"
            x-transition.origin.top.right
            class="absolute right-0 z-50 mt-2 w-80 max-w-[calc(100vw-1rem)] overflow-hidden rounded-lg border border-line bg-white shadow-[0_16px_36px_rgba(15,23,42,.14)]"
        >
            <div class="flex items-center justify-between border-b border-line px-4 py-3">
                <strong class="text-sm font-semibold text-ink">Notifications</strong>
                @if ($notifications->isNotEmpty())
                    <button type="button" wire:click="markAllRead" class="text-xs font-medium text-brand hover:text-brand-hover">Mark all read</button>
                @endif
            </div>

            <div class="max-h-96 overflow-y-auto">
                @forelse ($notifications as $notification)
                    <button
                        type="button"
                        wire:key="notification-{{ $notification->id }}"
                        wire:click="goTo('{{ $notification->id }}')"
                        class="flex w-full items-start gap-2 border-b border-line px-4 py-3 text-left transition hover:bg-canvas {{ $notification->read_at === null ? 'bg-blue-50/40' : '' }}"
                    >
                        @if ($notification->read_at === null)
                            <span class="mt-1.5 size-2 shrink-0 rounded-full bg-brand" aria-hidden="true"></span>
                        @else
                            <span class="mt-1.5 size-2 shrink-0" aria-hidden="true"></span>
                        @endif
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-ink">{{ $notification->data['title'] ?? 'Notification' }}</span>
                            <span class="mt-0.5 block truncate text-xs text-muted">{{ $notification->data['body'] ?? '' }}</span>
                            <span class="mt-1 block text-[11px] text-muted">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                        @if (! empty($notification->data['image_url']))
                            <img
                                src="{{ $notification->data['image_url'] }}"
                                alt=""
                                width="44"
                                height="44"
                                loading="lazy"
                                referrerpolicy="no-referrer"
                                x-on:error="$el.remove()"
                                class="mt-0.5 size-11 shrink-0 rounded-md border border-line object-cover"
                            >
                        @endif
                    </button>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-muted">You're all caught up.</p>
                @endforelse
            </div>

            @if ($pushConfigured)
                <div data-desktop-alerts class="border-t border-line px-4 py-2" x-cloak>
                    <div class="flex min-h-11 items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-2">
                            <svg class="size-4 shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8m-4-4v4"/></svg>
                            <span class="text-xs font-medium text-ink">Desktop alerts</span>
                            <span
                                x-show="push.supported && push.secure && push.permission === 'granted' && !push.optedOut && !push.error && push.synced"
                                class="text-xs font-medium text-success"
                                role="status"
                            >On</span>
                        </div>

                        <button
                            x-show="push.supported && push.secure && push.permission === 'granted' && !push.optedOut && !push.error && push.synced"
                            type="button"
                            @click="disablePush($wire)"
                            :disabled="pushBusy"
                            class="min-h-11 shrink-0 rounded-md px-2 text-xs font-semibold text-muted hover:bg-canvas hover:text-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand disabled:cursor-wait disabled:opacity-60"
                            x-text="pushBusy ? 'Turning off...' : 'Turn off'"
                        ></button>
                        <button
                            x-show="push.supported && push.secure && push.permission !== 'checking' && push.permission !== 'denied' && (push.optedOut || push.permission === 'default' || (!push.error && !push.synced))"
                            type="button"
                            @click="enablePush($wire)"
                            :disabled="pushBusy"
                            class="min-h-11 shrink-0 rounded-md px-2 text-xs font-semibold text-brand hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand disabled:cursor-wait disabled:opacity-60"
                            x-text="pushBusy ? 'Turning on...' : 'Turn on'"
                        ></button>
                        <button
                            x-show="push.supported && push.secure && push.permission === 'denied'"
                            type="button"
                            @click="checkPush($wire)"
                            class="min-h-11 shrink-0 rounded-md px-2 text-xs font-semibold text-brand hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand"
                        >Check again</button>
                        <button
                            x-show="push.supported && push.secure && push.permission === 'granted' && !push.optedOut && push.error"
                            type="button"
                            @click="retryPush($wire)"
                            :disabled="pushBusy"
                            class="min-h-11 shrink-0 rounded-md px-2 text-xs font-semibold text-brand hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand disabled:cursor-wait disabled:opacity-60"
                            x-text="pushBusy ? 'Retrying...' : 'Retry'"
                        ></button>
                    </div>

                    <div class="text-xs leading-relaxed text-muted" role="status">
                        <p x-show="push.permission === 'checking'">Checking browser...</p>
                        <p x-show="!push.supported">Unavailable in this browser.</p>
                        <p x-show="push.supported && !push.secure">Use HTTPS to enable alerts.</p>
                        <p x-show="push.supported && push.secure && push.permission === 'denied'">Blocked. Allow notifications in your browser settings.</p>
                        <div x-show="push.supported && push.secure && push.permission === 'granted' && !push.optedOut && push.error" class="pb-2">
                            <p>Couldn't connect. Try again.</p>
                            <details x-show="push.isBrave" class="mt-1">
                                <summary class="cursor-pointer text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Brave browser help</summary>
                                <p class="mt-1">In <code>brave://settings/system</code>, enable "Use Google services for push messaging", then retry.</p>
                            </details>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
