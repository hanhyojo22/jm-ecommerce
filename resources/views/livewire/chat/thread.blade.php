@php($isBuyerMessages = auth()->user()->account_type === 'buyer')
<div data-conversation-thread>
@if ($isBuyerMessages)
    <x-marketplace.account-shell active="messages" title="Conversation">
        <div class="grid h-[calc(100dvh-7rem)] min-h-0 min-w-0 overflow-hidden rounded-[14px] border border-line bg-surface lg:h-[calc(100dvh-12rem)] lg:min-h-[32rem] lg:grid-cols-[minmax(18rem,34%)_minmax(0,1fr)]">
            <div class="hidden min-h-0 border-r border-line lg:block"><livewire:chat.inbox :embedded="true" :active-conversation-id="$conversation->id" /></div>
            @include('livewire.chat.thread-content')
        </div>
    </x-marketplace.account-shell>
@else
    <div class="page-shell"><div class="page-container max-w-6xl">
        <div class="mb-5"><h1 class="text-[28px] font-bold tracking-tight text-ink">Messages</h1><p class="mt-1 text-sm text-muted">Respond to customers about your products and orders.</p></div>
        <div class="grid h-[calc(100dvh-16rem)] min-h-[28rem] min-w-0 overflow-hidden rounded-[14px] border border-line bg-surface shadow-sm lg:h-[calc(100dvh-14rem)] lg:min-h-[32rem] lg:grid-cols-[minmax(18rem,34%)_minmax(0,1fr)]">
            <div class="hidden min-h-0 border-r border-line lg:block"><livewire:chat.inbox :embedded="true" :active-conversation-id="$conversation->id" /></div>
            @include('livewire.chat.thread-content')
        </div>
    </div></div>
@endif
</div>

@script
<script>
    const messageList = document.getElementById('conversation-messages');
    let previousMessageScroll = null;
    let followLatestMessage = true;

    const localTimeFormatter = new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    const localDateFormatter = new Intl.DateTimeFormat('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    const localizeMessageTimes = () => {
        const today = new Date();
        const yesterday = new Date(today);
        yesterday.setDate(today.getDate() - 1);
        let previousLocalDate = null;

        messageList?.querySelectorAll('[data-chat-date-separator]').forEach((separator) => {
            const date = new Date(separator.dataset.chatDate);
            const localDate = date.toDateString();
            separator.classList.toggle('hidden', localDate === previousLocalDate);
            separator.querySelector('[data-chat-date-label]').textContent = localDate === today.toDateString()
                ? 'Today'
                : localDate === yesterday.toDateString() ? 'Yesterday' : localDateFormatter.format(date);
            previousLocalDate = localDate;
        });

        messageList?.querySelectorAll('time[data-chat-local-time]').forEach((time) => {
            time.textContent = localTimeFormatter.format(new Date(time.dateTime));
        });
    };

    const scrollToLatestMessage = (behavior = 'smooth') => {
        if (messageList) {
            messageList.scrollTo({ top: messageList.scrollHeight, behavior });
        }
    };

    messageList?.addEventListener('click', (event) => {
        if (event.target.closest('button')?.getAttribute('wire:click') === 'loadOlderMessages') {
            previousMessageScroll = {
                top: messageList.scrollTop,
                height: messageList.scrollHeight,
            };
        }
    });
    messageList?.addEventListener('scroll', () => {
        followLatestMessage = messageList.scrollHeight - messageList.clientHeight - messageList.scrollTop < 120;
    });

    requestAnimationFrame(() => {
        localizeMessageTimes();
        scrollToLatestMessage('auto');
    });

    Livewire.hook('morphed', ({ el }) => {
        if (! messageList || (el !== messageList && ! el.contains(messageList))) {
            return;
        }

        requestAnimationFrame(() => {
            localizeMessageTimes();
            if (previousMessageScroll) {
                messageList.scrollTop = previousMessageScroll.top + messageList.scrollHeight - previousMessageScroll.height;
                previousMessageScroll = null;

                return;
            }

            if (followLatestMessage) {
                scrollToLatestMessage();
            }
        });
    });

    const listenForMessages = () => {
        if (! window.Echo || window.chatConversationSubscribed) {
            return;
        }
        window.chatConversationSubscribed = true;

        const channel = window.Echo.private('conversation.{{ $conversation->id }}');
        channel.listen('.message.created', () => {
            $wire.syncLatestMessages();
            $wire.markDelivered();
            $wire.markRead();
            $wire.$refresh();
        });
        channel.listen('.message.read', () => $wire.$refresh());
        channel.listen('.conversation.typing', (event) => {
            const indicator = document.getElementById('chat-typing');
            if (indicator && event.user_id !== '{{ auth()->id() }}') {
                indicator.classList.toggle('hidden', ! event.typing);
                if (event.typing) {
                    setTimeout(() => indicator.classList.add('hidden'), 4000);
                }
            }
        });
        window.Echo.connector.pusher.connection.bind('connected', () => $wire.syncLatestMessages());
        let onlineUsers = [];
        window.Echo.join('conversation.{{ $conversation->id }}')
            .here((users) => {
                onlineUsers = users.filter((user) => user.id !== '{{ auth()->id() }}');
                setPresenceStatus(onlineUsers.length > 0);
            })
            .joining((user) => {
                if (user.id !== '{{ auth()->id() }}') onlineUsers.push(user);
                setPresenceStatus(onlineUsers.length > 0);
            })
            .leaving((user) => {
                onlineUsers = onlineUsers.filter((onlineUser) => onlineUser.id !== user.id);
                setPresenceStatus(onlineUsers.length > 0);
            });
    };

    const setPresenceStatus = (online) => {
        window.dispatchEvent(new CustomEvent('chat-presence-status', {
            detail: { conversationId: '{{ $conversation->id }}', online },
        }));
    };

    listenForMessages();
    window.addEventListener('reverb:ready', listenForMessages, { once: true });
</script>
@endscript
