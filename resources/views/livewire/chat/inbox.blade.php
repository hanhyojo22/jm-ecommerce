<div data-conversation-inbox>
    @php($isBuyerMessages = auth()->user()->account_type === 'buyer')
    @if ($embedded)
        @include('livewire.chat.inbox-content')
    @elseif ($isBuyerMessages)
        <x-marketplace.account-shell active="messages" title="Messages">
            <div class="grid h-[calc(100dvh-16rem)] min-h-0 min-w-0 overflow-hidden rounded-[14px] border border-line bg-surface sm:h-[calc(100dvh-14rem)] lg:h-[calc(100dvh-12rem)] lg:min-h-[32rem] lg:grid-cols-[minmax(18rem,34%)_minmax(0,1fr)]">
                @include('livewire.chat.inbox-content')
                <div class="hidden min-w-0 flex-col items-center justify-center border-l border-line bg-canvas px-8 text-center lg:flex"><span class="grid size-14 place-items-center rounded-full bg-blue-50 text-brand-hover" aria-hidden="true"><svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H5l-1 2v-6.5a7.5 7.5 0 1 1 16-3Z"/><path stroke-linecap="round" d="M8 11.5h8M8 14.5h5"/></svg></span><h2 class="mt-4 text-base font-semibold text-ink">Select a conversation</h2><p class="mt-1 max-w-xs text-sm text-muted">Choose a customer or store to view your messages.</p></div>
            </div>
        </x-marketplace.account-shell>
    @else
        <div class="page-shell"><div class="page-container max-w-6xl">
            <div class="mb-5"><h1 class="text-[28px] font-bold tracking-tight text-ink">Messages</h1><p class="mt-1 text-sm text-muted">Respond to customers about your products and orders.</p></div>
            <div class="grid h-[calc(100dvh-16rem)] min-h-[28rem] min-w-0 overflow-hidden rounded-[14px] border border-line bg-surface shadow-sm lg:h-[calc(100dvh-14rem)] lg:min-h-[32rem] lg:grid-cols-[minmax(18rem,34%)_minmax(0,1fr)]">
                @include('livewire.chat.inbox-content')
                <div class="hidden min-w-0 flex-col items-center justify-center border-l border-line bg-canvas px-8 text-center lg:flex"><span class="grid size-14 place-items-center rounded-full bg-blue-50 text-brand-hover" aria-hidden="true"><svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H5l-1 2v-6.5a7.5 7.5 0 1 1 16-3Z"/><path stroke-linecap="round" d="M8 11.5h8M8 14.5h5"/></svg></span><h2 class="mt-4 text-base font-semibold text-ink">Select a conversation</h2><p class="mt-1 max-w-xs text-sm text-muted">Choose a customer or store to view your messages.</p></div>
            </div>
        </div></div>
    @endif
</div>

@script
<script>
    const inbox = document.querySelector('[data-conversation-inbox]');
    const joinedConversations = new Set();

    const syncConversationChannels = () => {
        if (! inbox || ! window.Echo) {
            return;
        }

        const visibleConversations = new Set(
            [...inbox.querySelectorAll('[data-presence-conversation]')]
                .map((conversation) => conversation.dataset.presenceConversation),
        );

        for (const conversationId of joinedConversations) {
            if (! visibleConversations.has(conversationId)) {
                window.Echo.leave(`conversation.${conversationId}`);
                joinedConversations.delete(conversationId);
            }
        }

        for (const conversationId of visibleConversations) {
            if (! joinedConversations.has(conversationId)) {
                window.Echo.join(`conversation.${conversationId}`);
                window.Echo.private(`conversation.${conversationId}`)
                    .listen('.message.created', () => $wire.$refresh())
                    .listen('.message.read', () => $wire.$refresh());
                joinedConversations.add(conversationId);
            }
        }
    };

    syncConversationChannels();
    window.addEventListener('reverb:ready', syncConversationChannels, { once: true });

    if (inbox) {
        new MutationObserver(syncConversationChannels).observe(inbox, { childList: true, subtree: true });
    }
</script>
@endscript
