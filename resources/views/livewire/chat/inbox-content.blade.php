<section data-conversation-list class="relative flex h-full min-h-0 min-w-0 flex-col bg-surface" aria-label="Your conversations">
    <header class="shrink-0 border-b border-line px-4 pb-3 pt-4 sm:px-5">
        @if ($isBuyerMessages)<h1 class="text-xl font-semibold tracking-tight text-ink">Messages</h1>@else<h2 class="text-base font-semibold tracking-tight text-ink">Inbox</h2>@endif
        <p class="mt-0.5 text-xs text-muted">{{ $isBuyerMessages ? 'Contact stores about products and orders.' : 'Your customer conversations.' }}</p>
        <label for="conversation-search" class="mt-4 flex min-h-11 items-center gap-2.5 rounded-[10px] border border-line bg-canvas px-3 text-muted transition focus-within:border-brand focus-within:bg-white focus-within:ring-2 focus-within:ring-brand/15">
            <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.75" cy="10.75" r="6.75"/><path stroke-linecap="round" d="m16 16 4.5 4.5"/></svg>
            <span class="sr-only">Search conversations</span>
            <input wire:model.live.debounce.300ms="search" id="conversation-search" type="search" placeholder="Search conversations" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-ink placeholder:text-muted focus:ring-0">
        </label>
        <div class="mt-3 flex gap-1 overflow-x-auto" role="group" aria-label="Conversation filters">
            @foreach (['all' => 'All', 'unread' => 'Unread', 'product' => 'Products', 'order' => 'Orders'] as $value => $label)
                <button wire:key="filter-{{ $value }}" wire:click="$set('filter', '{{ $value }}')" type="button" class="min-h-11 shrink-0 rounded-lg px-3 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand {{ $filter === $value ? 'bg-blue-50 text-brand-hover' : 'text-muted hover:bg-canvas hover:text-ink' }}" aria-pressed="{{ $filter === $value ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </div>
    </header>

    <div wire:loading.flex wire:target="search,filter" class="absolute inset-x-0 bottom-0 top-44 z-10 flex-col gap-4 bg-white/95 px-5 py-5" role="status" aria-label="Loading conversations">
        @for ($row = 0; $row < 3; $row++)
            <div class="flex animate-pulse items-center gap-3" aria-hidden="true"><span class="size-11 shrink-0 rounded-full bg-slate-100"></span><span class="flex-1 space-y-2"><span class="block h-3 w-2/3 rounded-full bg-slate-100"></span><span class="block h-3 w-full rounded-full bg-slate-100"></span></span></div>
        @endfor
    </div>
    <div class="chat-inbox-list min-h-0 flex-1 divide-y divide-line overflow-y-auto overscroll-contain" aria-live="polite">
        @forelse ($conversations as $conversation)
            @php($latest = $conversation->messages->first())
            @php($isUnread = $conversation->unread_messages_count > 0)
            @php($conversationIsBuying = $conversation->buyer_id === auth()->id())
            @php($name = $conversationIsBuying ? $conversation->store->name : $conversation->buyer->name)
            @php($context = $conversation->product ? 'Product · '.$conversation->product->name : ($conversation->sellerOrder ? 'Order · #'.$conversation->sellerOrder->order->number : 'Marketplace chat'))
            <a wire:key="conversation-{{ $conversation->id }}" data-presence-conversation="{{ $conversation->id }}" href="{{ route('messages.show', $conversation) }}" @if ($activeConversationId === $conversation->id) aria-current="page" @endif class="group flex min-w-0 items-start gap-3 px-4 py-3.5 transition hover:bg-canvas focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-brand sm:px-5 {{ $activeConversationId === $conversation->id ? 'bg-blue-50/70' : '' }}">
                <span class="grid size-11 shrink-0 place-items-center overflow-hidden rounded-full bg-blue-50 text-sm font-semibold text-brand-hover">
                    @if ($conversationIsBuying && $conversation->store->logo_path)
                        <img src="{{ Storage::disk('public')->url($conversation->store->logo_path) }}" alt="" class="size-full object-cover">
                    @else
                        {{ Str::upper(Str::substr($name, 0, 1)) }}
                    @endif
                </span>
                <span class="min-w-0 flex-1">
                    <span class="flex min-w-0 items-baseline gap-2"><span class="truncate text-sm {{ $isUnread ? 'font-bold' : 'font-semibold' }} text-ink">{{ $name }}</span><time @if ($conversation->latest_message_at) datetime="{{ $conversation->latest_message_at->toIso8601String() }}" @endif class="ml-auto shrink-0 text-[11px] {{ $isUnread ? 'font-semibold text-brand-hover' : ($activeConversationId === $conversation->id ? 'text-slate-600' : 'text-muted') }}">{{ $conversation->latest_message_at?->diffForHumans() }}</time></span>
                    <span class="mt-1 flex min-w-0 items-center gap-2"><span class="min-w-0 flex-1 truncate text-sm {{ $isUnread ? 'font-medium text-ink' : ($activeConversationId === $conversation->id ? 'text-slate-600' : 'text-muted') }}">{{ $latest === null ? 'No messages yet' : (filled($latest->body) ? $latest->body : 'Attachment') }}</span>@if ($isUnread)<span class="inline-flex min-w-5 shrink-0 justify-center rounded-full bg-brand px-1.5 py-0.5 text-[11px] font-bold leading-none text-white" aria-label="{{ $conversation->unread_messages_count }} unread messages">{{ $conversation->unread_messages_count > 99 ? '99+' : $conversation->unread_messages_count }}</span>@endif</span>
                    <span class="mt-1 block truncate text-xs {{ $activeConversationId === $conversation->id ? 'text-slate-600' : 'text-muted' }}">{{ $context }}</span>
                </span>
            </a>
        @empty
            <div class="flex min-h-64 flex-col items-center justify-center px-6 py-12 text-center">
                <span class="grid size-12 place-items-center rounded-full bg-blue-50 text-brand-hover" aria-hidden="true"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H5l-1 2v-6.5a7.5 7.5 0 1 1 16-3Z"/><path stroke-linecap="round" d="M8 11.5h8M8 14.5h5"/></svg></span>
                <h2 class="mt-3 text-sm font-semibold text-ink">{{ $search !== '' || $filter !== 'all' ? 'No matching conversations' : ($isBuyerMessages ? 'No messages yet' : 'No customer messages yet') }}</h2>
                <p class="mt-1 max-w-xs text-sm text-muted">{{ $search !== '' || $filter !== 'all' ? 'Try another search or filter.' : 'Conversations about products and orders will appear here.' }}</p>
            </div>
        @endforelse
    </div>
    @if ($conversations->hasPages())<div class="shrink-0 border-t border-line px-4 py-3">{{ $conversations->links() }}</div>@endif
</section>
