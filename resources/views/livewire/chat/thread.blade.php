<div data-conversation-thread>
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl min-w-0 items-center gap-3 px-4 py-5 sm:px-6 sm:py-6 lg:px-8">
            <a href="{{ route('messages.index') }}" class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-emerald-200 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 sm:hidden" aria-label="Back to messages"><span aria-hidden="true">←</span></a>
            <div class="min-w-0"><a href="{{ route('messages.index') }}" class="hidden text-sm font-medium text-emerald-800 hover:text-emerald-950 sm:inline-flex">← Back to messages</a><h1 class="truncate text-xl font-semibold tracking-tight text-slate-950 sm:mt-1 sm:text-2xl">{{ auth()->user()->account_type === 'buyer' ? $conversation->store->name : $conversation->buyer->name }}</h1></div>
        </div>
    </header>

    <div class="mx-auto max-w-5xl py-4 sm:px-6 sm:py-6 lg:px-8">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <header class="flex min-w-0 items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 sm:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    @if (auth()->user()->account_type === 'buyer' && $conversation->store->logo_path)
                        <img src="{{ Storage::disk('public')->url($conversation->store->logo_path) }}" alt="" class="size-10 shrink-0 rounded-xl object-cover">
                    @else
                        <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-sm font-bold text-emerald-800" aria-hidden="true">{{ Str::upper(Str::substr(auth()->user()->account_type === 'buyer' ? $conversation->store->name : $conversation->buyer->name, 0, 1)) }}</span>
                    @endif
                    <div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-900">{{ auth()->user()->account_type === 'buyer' ? $conversation->store->name : $conversation->buyer->name }}</p><p id="chat-presence" class="truncate text-xs text-slate-500">Offline · {{ $conversation->product ? 'Product inquiry' : 'Order conversation' }}</p><p id="chat-typing" class="hidden truncate text-xs text-emerald-700" aria-live="polite">Typing…</p></div>
                </div>
                @if (auth()->user()->account_type === 'buyer')
                    <a href="{{ route('catalog.store', $conversation->store->slug) }}" class="shrink-0 text-sm font-semibold text-emerald-800 hover:text-emerald-950">Storefront</a>
                @endif
                <form method="POST" action="{{ route('messages.block', $conversation) }}">@csrf<button type="submit" class="shrink-0 text-xs font-medium text-slate-500 hover:text-red-700" onclick="return confirm('Block this conversation?')">Block</button></form>
            </header>

            @if ($conversation->product)
                <div class="mx-4 mt-4 flex min-w-0 items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 sm:mx-6">
                    @if ($conversation->product->images->first())<img src="{{ Storage::disk('product_images')->url($conversation->product->images->first()->path) }}" alt="" class="size-14 shrink-0 rounded-lg object-cover">@else<div class="flex size-14 shrink-0 items-center justify-center rounded-lg bg-slate-200 text-slate-500" aria-hidden="true">□</div>@endif
                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-900">{{ $conversation->product->name }}</p><p class="mt-0.5 text-xs text-slate-600">₱{{ number_format($conversation->product->price, 2) }} · {{ $conversation->product->stock_quantity > 0 ? 'In stock' : 'Currently unavailable' }}</p></div>
                    <a href="{{ route('catalog.show', [$conversation->store->slug, $conversation->product->slug]) }}" class="shrink-0 text-sm font-semibold text-emerald-800 hover:text-emerald-950">View</a>
                </div>
            @elseif ($conversation->sellerOrder)
                <div class="mx-4 mt-4 flex min-w-0 items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 sm:mx-6">
                    <span class="inline-flex size-11 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700" aria-hidden="true">#</span>
                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-900">Order {{ $conversation->sellerOrder->order->number }}</p><p class="mt-0.5 text-xs text-slate-600">{{ $conversation->sellerOrder->created_at->format('M j, Y') }} · ₱{{ number_format($conversation->sellerOrder->total, 2) }} · {{ Str::headline($conversation->sellerOrder->status) }}</p></div>
                    @if (auth()->user()->account_type === 'buyer')<a href="{{ route('orders.show', $conversation->sellerOrder->order) }}" class="shrink-0 text-sm font-semibold text-emerald-800 hover:text-emerald-950">View</a>@else<a href="{{ route('seller.orders.show', $conversation->sellerOrder) }}" class="shrink-0 text-sm font-semibold text-emerald-800 hover:text-emerald-950">View</a>@endif
                </div>
            @endif

            <div wire:offline class="mx-4 mt-4 rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800 sm:mx-6" role="status">You’re offline. Messages will send when your connection returns.</div>
            <div id="conversation-messages" class="max-h-[52vh] min-h-80 overflow-y-auto px-4 py-5 sm:max-h-[58vh] sm:px-6" role="log" aria-live="polite" aria-label="Conversation messages">
                @if ($hasMoreMessages)<div class="mb-5 text-center"><button wire:click="loadOlderMessages" wire:loading.attr="disabled" type="button" class="rounded-lg px-3 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-600">Load older messages</button></div>@endif
                @php($previousDate = null)
                @php($previousSenderId = null)
                @forelse ($messages as $message)
                    @if ($previousDate?->toDateString() !== $message->created_at->toDateString())<div class="my-5 flex items-center gap-3 text-xs font-medium text-slate-500"><span class="h-px flex-1 bg-slate-200"></span><span>{{ $message->created_at->isToday() ? 'Today' : ($message->created_at->isYesterday() ? 'Yesterday' : $message->created_at->format('F j, Y')) }}</span><span class="h-px flex-1 bg-slate-200"></span></div>@endif
                    <article wire:key="message-{{ $message->id }}" class="mb-2 flex {{ $message->sender_id === auth()->id() ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[85%] sm:max-w-[70%] {{ $message->sender_id === $previousSenderId ? '' : 'mt-2' }}">
                            @if ($message->sender_id !== auth()->id() && $message->sender_id !== $previousSenderId)<p class="mb-1 px-1 text-xs font-medium text-slate-500">{{ $message->sender->name }}</p>@endif
                            <div class="rounded-2xl px-4 py-2.5 text-sm leading-6 {{ $message->sender_id === auth()->id() ? 'rounded-br-md bg-emerald-700 text-white' : 'rounded-bl-md bg-slate-100 text-slate-800' }}">@if ($message->type === 'file' && $message->attachments->isNotEmpty())<a href="{{ route('messages.attachments.show', $message->attachments->first()) }}" class="underline underline-offset-2">{{ $message->attachments->first()->original_name }}</a>@else<p class="whitespace-pre-wrap break-words [overflow-wrap:anywhere]">{{ $message->body }}</p>@endif<time datetime="{{ $message->created_at->toIso8601String() }}" class="mt-1 block text-[11px] {{ $message->sender_id === auth()->id() ? 'text-emerald-100' : 'text-slate-500' }}">{{ $message->created_at->format('g:i A') }}</time>@if ($message->id === $lastMessage?->id && $message->sender_id === auth()->id())<span class="mt-0.5 block text-right text-[11px] text-emerald-100">{{ $receipt }}</span>@endif</div>
                            @if ($message->sender_id !== auth()->id())<details class="mt-1 px-1 text-xs"><summary class="cursor-pointer text-slate-500">Report message</summary><form method="POST" action="{{ route('messages.report', [$conversation, $message]) }}" class="mt-2 space-y-2">@csrf<select name="reason" required class="rounded-lg border-slate-300 text-xs"><option value="Spam">Spam</option><option value="Abuse">Abusive content</option><option value="Other">Other</option></select><textarea name="details" maxlength="1000" rows="2" aria-label="Report details" class="block w-full rounded-lg border-slate-300 text-xs"></textarea><button class="rounded-lg bg-slate-900 px-3 py-2 font-semibold text-white">Submit report</button></form></details>@endif
                        </div>
                    </article>
                    @php($previousDate = $message->created_at)
                    @php($previousSenderId = $message->sender_id)
                @empty
                    <div class="flex min-h-64 flex-col items-center justify-center px-6 text-center"><div class="flex size-12 items-center justify-center rounded-2xl bg-emerald-50 text-xl text-emerald-700" aria-hidden="true">✦</div><p class="mt-3 text-sm font-semibold text-slate-900">Start the conversation</p><p class="mt-1 text-sm text-slate-500">Write a message to get in touch.</p></div>
                @endforelse
            </div>
            <form wire:submit="send" class="border-t border-slate-200 bg-white p-4 sm:px-6">
                @cannot('send', $conversation)<p class="mb-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900" role="status">Messaging is unavailable for this conversation.</p>@endcannot
                @if (auth()->user()->account_type !== 'buyer')
                    <div class="mb-3 flex gap-2 overflow-x-auto pb-1" aria-label="Quick replies">@foreach (['Yes, this item is available.', 'Your order is being prepared.', 'We’ll update you once your order ships.', 'Could you provide more details?'] as $reply)<button wire:click='useQuickReply(@js($reply))' type="button" class="shrink-0 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-900 hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-600">{{ $reply }}</button>@endforeach</div>
                @endif
                <label for="message-body" class="sr-only">Write a message</label>
                <div class="flex items-end gap-2 sm:gap-3"><textarea wire:model="body" x-on:input.debounce.500ms="$wire.updateTypingStatus(true); clearTimeout(window.chatTypingTimeout); window.chatTypingTimeout = setTimeout(() => $wire.updateTypingStatus(false), 3000)" x-on:blur="$wire.updateTypingStatus(false)" x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.send(); }" id="message-body" rows="2" maxlength="2000" class="min-h-12 w-full resize-y rounded-xl border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:border-emerald-700 focus:ring-emerald-700" placeholder="Write a message…" aria-describedby="message-help" @disabled(! auth()->user()->can('send', $conversation))></textarea><button type="submit" class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-xl bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" wire:loading.attr="disabled" wire:target="send" @disabled(! auth()->user()->can('send', $conversation))><span wire:loading.remove wire:target="send">Send</span><span wire:loading wire:target="send">Sending…</span></button></div>
                <div id="message-help" class="mt-2 flex items-center justify-between gap-3 text-xs text-slate-500"><span>Enter to send · Shift + Enter for a new line</span><span>{{ Str::length($body) }}/2000</span></div>
                @error('body')<p class="mt-2 text-sm font-medium text-red-700" role="alert">{{ $message }}</p>@enderror
            </form>
            @can('send', $conversation)<form method="POST" action="{{ route('messages.attachments.store', $conversation) }}" enctype="multipart/form-data" class="border-t border-slate-100 px-4 py-3 sm:px-6">@csrf<input type="hidden" name="client_message_id" value="{{ $clientMessageId }}"><label class="sr-only" for="chat-attachment">Attach image or PDF</label><div class="flex items-center gap-3"><input id="chat-attachment" name="attachment" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required class="min-w-0 flex-1 text-xs"><button type="submit" class="min-h-10 rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700">Attach</button></div><p class="mt-1 text-xs text-slate-500">JPG, PNG, WebP, or PDF up to 5 MB.</p></form>@endcan
        </section>
    </div>
</div>

@script
<script>
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
        const presence = document.getElementById('chat-presence');
        if (presence) {
            presence.textContent = `${online ? 'Online' : 'Offline'} · {{ $conversation->product ? 'Product inquiry' : 'Order conversation' }}`;
        }
    };

    listenForMessages();
    window.addEventListener('reverb:ready', listenForMessages, { once: true });
</script>
@endscript
