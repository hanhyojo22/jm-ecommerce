<div data-conversation-inbox>
    @php($isBuyerMessages = auth()->user()->account_type === 'buyer')
    @if ($isBuyerMessages)
        <x-marketplace.account-shell active="messages" title="Messages">
            @include('livewire.chat.inbox-content')
        </x-marketplace.account-shell>
    @else
        @include('livewire.chat.inbox-content')
    @endif
</div>

@script
<script>
    const inbox = document.querySelector('[data-conversation-inbox]');
    const joinedConversations = new Set();

    const syncPresenceChannels = () => {
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
                joinedConversations.add(conversationId);
            }
        }
    };

    syncPresenceChannels();
    window.addEventListener('reverb:ready', syncPresenceChannels, { once: true });

    if (inbox) {
        new MutationObserver(syncPresenceChannels).observe(inbox, { childList: true, subtree: true });
    }
</script>
@endscript
