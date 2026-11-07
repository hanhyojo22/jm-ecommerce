<div wire:poll.15s.visible>
    <a href="{{ $messagesUrl }}" class="relative grid size-11 place-items-center rounded-md text-ink transition hover:bg-canvas focus-visible:ring-2 focus-visible:ring-brand" aria-label="Messages{{ $unreadCount ? ', '.$unreadCount.' unread' : '' }}">
        <svg class="size-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M21 12a8 8 0 0 1-8 8H4l2-3a8 8 0 1 1 15-5z" /></svg>
        @if ($unreadCount > 0)
            <span class="absolute -right-1 -top-1 grid h-[19px] min-w-[19px] place-items-center rounded-full border-2 border-white bg-brand px-[5px] text-xs font-semibold leading-none text-white">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
        @endif
    </a>
</div>
