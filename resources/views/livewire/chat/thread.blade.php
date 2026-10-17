@php($isBuyerMessages = $conversation->buyer_id === auth()->id())
<div data-conversation-thread>
@if ($isBuyerMessages)
    <x-marketplace.account-shell active="messages" title="Conversation">
        <div class="grid h-[calc(100dvh-17.5rem)] min-h-0 min-w-0 overflow-hidden rounded-[14px] border border-line bg-surface sm:h-[calc(100dvh-14.5rem)] lg:h-[calc(100dvh-10rem)] lg:min-h-[32rem] lg:grid-cols-[clamp(380px,30%,420px)_minmax(0,1fr)]">
            <div class="hidden min-h-0 border-r border-line lg:block"><livewire:chat.inbox :embedded="true" :active-conversation-id="$conversation->id" :context="$isBuyerMessages ? 'buying' : 'selling'" /></div>
            @include('livewire.chat.thread-content')
        </div>
    </x-marketplace.account-shell>
@else
    <div class="page-shell seller-messages-page"><div class="page-container seller-messages-container seller-workspace-page">
        <div class="mb-5"><h1 class="text-[28px] font-bold tracking-tight text-ink">Messages</h1><p class="mt-1 text-sm text-muted">Respond to customers about your products and orders.</p></div>
        <div class="grid h-[calc(100dvh-15rem)] min-h-[28rem] min-w-0 overflow-hidden rounded-[14px] border border-line bg-surface shadow-sm sm:h-[calc(100dvh-10rem)] lg:min-h-[32rem] lg:grid-cols-[minmax(18rem,34%)_minmax(0,1fr)]">
            <div class="hidden min-h-0 border-r border-line lg:block"><livewire:chat.inbox :embedded="true" :active-conversation-id="$conversation->id" :context="$isBuyerMessages ? 'buying' : 'selling'" /></div>
            @include('livewire.chat.thread-content')
        </div>
    </div></div>
@endif
</div>
