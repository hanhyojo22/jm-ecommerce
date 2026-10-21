@php($isBuyerMessages = $conversation->buyer_id === auth()->id())
<div data-conversation-thread>
    <x-marketplace.account-shell active="messages" title="Conversation" :workspace="$isBuyerMessages ? null : 'seller'">
        <div class="grid h-[calc(100dvh-17.5rem)] min-h-0 min-w-0 overflow-hidden rounded-2xl border border-line bg-surface sm:h-[calc(100dvh-14.5rem)] lg:h-[calc(100dvh-7.5rem)] lg:min-h-[32rem] lg:grid-cols-[clamp(380px,30%,420px)_minmax(0,1fr)]">
            <div class="hidden min-h-0 border-r border-line lg:block"><livewire:chat.inbox :embedded="true" :active-conversation-id="$conversation->id" :context="$isBuyerMessages ? 'buying' : 'selling'" /></div>
            @include('livewire.chat.thread-content')
        </div>
    </x-marketplace.account-shell>
</div>
