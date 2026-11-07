<div data-notification-bell x-data="notificationBell('{{ auth()->id() }}')" wire:key="notification-bell" {{ $attributes }}>
    <div
        x-data="{ open: false, pushStatus: (window.Notification && Notification.permission) || 'unsupported' }"
        x-on:click.outside="open = false"
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

            <div x-show="pushStatus === 'default'" x-cloak class="border-t border-line px-4 py-3">
                <button
                    type="button"
                    @click="pushStatus = 'pending'; window.enableWebPush($wire).then((ok) => { pushStatus = ok ? 'granted' : (window.Notification?.permission ?? 'default') })"
                    class="w-full rounded-md border border-line px-3 py-2 text-xs font-semibold text-ink transition hover:bg-canvas"
                >
                    Get notified even when this tab is closed
                </button>
            </div>
        </div>
    </div>
</div>
