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
        x-on:click.outside="open = false"
        x-on:jmmart:pushsubscriptionchange.window="if (pushConfigured) { checkPush($wire) }"
        class="relative"
    >
        <button
            type="button"
            @click="open = !open"
            class="relative grid size-11 place-items-center rounded-md text-ink transition hover:bg-canvas focus-visible:ring-2 focus-visible:ring-brand"
            aria-label="Notifications{{ $unreadCount ? ', '.$unreadCount.' unread' : '' }}"
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
                @if ($unreadCount > 0)
                    <button type="button" wire:click="markAllRead" class="text-xs font-medium text-brand hover:text-brand-hover">Mark all read</button>
                @endif
            </div>

            <div class="max-h-96 overflow-y-auto">
                @forelse ($notifications as $notification)
                    <button
                        type="button"
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
                    </button>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-muted">No notifications yet.</p>
                @endforelse
            </div>

            @if ($pushConfigured)
                <div class="border-t border-line px-4 py-3" x-cloak aria-live="polite">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-brand">Desktop notifications</p>

                    <div x-show="!push.supported" class="mt-2">
                        <p class="text-xs text-muted">This browser does not support desktop notifications.</p>
                    </div>

                    <div x-show="push.supported && !push.secure" class="mt-2">
                        <p class="text-xs text-muted">Desktop notifications require HTTPS. Open JMMart on a secure connection to turn them on.</p>
                    </div>

                    <div x-show="push.supported && push.secure && push.permission === 'denied'" class="mt-2">
                        <p class="text-xs font-semibold text-ink">Desktop notifications are blocked</p>
                        <p class="mt-0.5 text-xs text-muted">Allow notifications for this site in your browser settings, then check again.</p>
                        <button type="button" @click="checkPush($wire)" class="mt-2 text-xs font-semibold text-brand hover:text-brand-hover">Check again</button>
                    </div>

                    <div x-show="push.supported && push.secure && push.permission !== 'denied' && push.optedOut" class="mt-2">
                        <p class="text-xs font-semibold text-ink">Desktop notifications are off</p>
                        <p class="mt-0.5 text-xs text-muted">You turned them off for this browser.</p>
                        <button
                            type="button"
                            @click="enablePush($wire)"
                            :disabled="pushBusy"
                            class="mt-2 rounded-md border border-line px-3 py-2 text-xs font-semibold text-ink transition hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-60"
                            x-text="pushBusy ? 'Turning on…' : 'Turn on desktop notifications'"
                        ></button>
                    </div>

                    <div x-show="push.supported && push.secure && push.permission === 'default' && !push.optedOut" class="mt-2">
                        <p class="text-xs font-semibold text-ink">Stay updated while JMMart is closed</p>
                        <p class="mt-0.5 text-xs text-muted">Get an alert on this device for new orders and messages.</p>
                        <button
                            type="button"
                            @click="enablePush($wire)"
                            :disabled="pushBusy"
                            class="mt-2 w-full rounded-md border border-line px-3 py-2 text-xs font-semibold text-ink transition hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-60"
                            x-text="pushBusy ? 'Turning on…' : 'Turn on desktop notifications'"
                        ></button>
                    </div>

                    <div x-show="push.supported && push.secure && push.permission === 'granted' && !push.optedOut && push.error" class="mt-2">
                        <p class="text-xs font-semibold text-ink">Desktop notifications could not be connected</p>
                        <p class="mt-0.5 text-xs text-muted">Check this browser's notification settings and try again.</p>
                        <button
                            type="button"
                            @click="retryPush($wire)"
                            :disabled="pushBusy"
                            class="mt-2 rounded-md border border-line px-3 py-2 text-xs font-semibold text-ink transition hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-60"
                            x-text="pushBusy ? 'Trying again…' : 'Try again'"
                        ></button>
                    </div>

                    <div x-show="push.supported && push.secure && push.permission === 'granted' && !push.optedOut && !push.error && push.synced" class="mt-2">
                        <p class="text-xs font-semibold text-success">Desktop notifications are on</p>
                        <p class="mt-0.5 text-xs text-muted">New orders and messages appear in your computer's notification center.</p>
                        <button
                            type="button"
                            @click="disablePush($wire)"
                            :disabled="pushBusy"
                            class="mt-2 text-xs font-semibold text-muted underline underline-offset-4 transition hover:text-ink disabled:cursor-not-allowed disabled:opacity-60"
                        >Turn off for this browser</button>
                    </div>

                    <div x-show="push.supported && push.secure && push.permission === 'granted' && !push.optedOut && !push.error && !push.synced" class="mt-2">
                        <p class="text-xs font-semibold text-ink">Connect this browser</p>
                        <p class="mt-0.5 text-xs text-muted">Notifications are allowed, but this browser is not connected to your account.</p>
                        <button
                            type="button"
                            @click="enablePush($wire)"
                            :disabled="pushBusy"
                            class="mt-2 rounded-md border border-line px-3 py-2 text-xs font-semibold text-ink transition hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-60"
                            x-text="pushBusy ? 'Connecting…' : 'Connect this browser'"
                        ></button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
