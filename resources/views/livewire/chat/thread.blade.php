<div data-conversation-thread>
    <div class="page-shell py-0 sm:py-4">
        <div class="mx-auto h-[calc(100dvh-5rem)] min-h-[28rem] w-full max-w-6xl sm:h-[min(78vh,58rem)] sm:min-h-[34rem] sm:px-6 lg:px-8">
            <section class="flex h-full min-h-0 flex-col overflow-hidden bg-white sm:rounded-2xl sm:border sm:border-slate-200 sm:shadow-sm">
                <header class="flex min-w-0 shrink-0 items-center justify-between gap-2 border-b border-slate-200 px-3 py-3 sm:gap-4 sm:px-5">
                <div class="flex min-w-0 items-center gap-2.5 sm:gap-3">
                    <a href="{{ route('messages.index') }}" class="grid size-10 shrink-0 place-items-center rounded-full text-slate-600 transition hover:bg-slate-100 hover:text-slate-950 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700" aria-label="Back to messages"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6M9 12h11"/></svg></a>
                    <div class="flex min-w-0 items-center gap-3">
                        @if (auth()->user()->account_type === 'buyer' && $conversation->store->logo_path)
                            <img src="{{ Storage::disk('public')->url($conversation->store->logo_path) }}" alt="" class="size-11 shrink-0 rounded-full object-cover">
                        @else
                            <span class="inline-flex size-11 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-900" aria-hidden="true">{{ Str::upper(Str::substr(auth()->user()->account_type === 'buyer' ? $conversation->store->name : $conversation->buyer->name, 0, 1)) }}</span>
                        @endif
                        <div class="min-w-0"><h1 class="truncate text-sm font-semibold text-slate-950 sm:text-base">{{ auth()->user()->account_type === 'buyer' ? $conversation->store->name : $conversation->buyer->name }}</h1><p id="chat-presence" x-data="{ online: false }" x-on:chat-presence-status.window="if ($event.detail.conversationId === '{{ $conversation->id }}') online = $event.detail.online" class="flex items-center gap-1.5 truncate text-xs"><span data-presence-dot class="size-1.5 shrink-0 rounded-full" x-bind:class="{ 'bg-emerald-500': online, 'bg-slate-400': ! online }"></span><span data-presence-label data-context="{{ $conversation->product ? 'Product inquiry' : 'Order conversation' }}" x-text="(online ? 'Online' : 'Offline') + ' · ' + $el.dataset.context">Offline · {{ $conversation->product ? 'Product inquiry' : 'Order conversation' }}</span></p><p id="chat-typing" class="hidden truncate text-xs font-medium text-emerald-800" aria-live="polite">Typing…</p></div>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    @if (auth()->user()->account_type === 'buyer')
                        <a href="{{ route('catalog.store', $conversation->store->slug) }}" aria-label="Open store" class="inline-flex min-h-10 items-center gap-2 rounded-full px-3 text-xs font-semibold text-emerald-900 transition hover:bg-emerald-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700 sm:text-sm"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.25h16.5l-1.5 10h-13.5l-1.5-10ZM5 9.25l2-5h10l2 5M9 19.25v-6h6v6"/></svg><span class="hidden sm:inline">Store</span></a>
                    @endif
                    <form method="POST" action="{{ route('messages.block', $conversation) }}">@csrf<button type="submit" class="inline-flex min-h-10 items-center rounded-full px-3 text-xs font-medium text-slate-500 transition hover:bg-rose-50 hover:text-rose-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-rose-700 sm:text-sm" onclick="return confirm('Block this conversation?')">Block</button></form>
                </div>
            </header>
            @if ($conversation->product)
                <div class="mx-3 mt-2 flex min-w-0 shrink-0 items-center gap-3 rounded-xl bg-slate-50 p-2.5 sm:mx-5">
                    @if ($conversation->product->images->first())<img src="{{ Storage::disk('product_images')->url($conversation->product->images->first()->path) }}" alt="" class="size-12 shrink-0 rounded-lg object-cover">@else<div class="grid size-12 shrink-0 place-items-center rounded-lg bg-slate-200 text-slate-500" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9ZM4.5 7.75 12 12l7.5-4.25M12 12v8.5"/></svg></div>@endif
                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-900">{{ $conversation->product->name }}</p><p class="mt-0.5 truncate text-xs text-slate-600">₱{{ number_format($conversation->product->price, 2) }} · {{ $conversation->product->stock_quantity > 0 ? 'In stock' : 'Currently unavailable' }}</p></div>
                    <a href="{{ route('catalog.show', [$conversation->store->slug, $conversation->product->slug]) }}" class="shrink-0 rounded-full px-3 py-2 text-xs font-semibold text-emerald-900 transition hover:bg-emerald-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700 sm:text-sm">View item</a>
                </div>
            @elseif ($conversation->sellerOrder)
                <div class="mx-3 mt-2 flex min-w-0 shrink-0 items-center gap-3 rounded-xl bg-amber-50/70 p-2.5 sm:mx-5">
                    <span class="grid size-12 shrink-0 place-items-center rounded-lg bg-amber-100 text-amber-800" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 7.5h14l1 13H4l1-13ZM9 8V6a3 3 0 0 1 6 0v2"/></svg></span>
                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-900">Order {{ $conversation->sellerOrder->order->number }}</p><p class="mt-0.5 truncate text-xs text-slate-600">{{ $conversation->sellerOrder->created_at->format('M j, Y') }} · ₱{{ number_format($conversation->sellerOrder->total, 2) }} · {{ Str::headline($conversation->sellerOrder->status) }}</p></div>
                    @if (auth()->user()->account_type === 'buyer')<a href="{{ route('orders.show', $conversation->sellerOrder->order) }}" class="shrink-0 rounded-full px-3 py-2 text-xs font-semibold text-emerald-900 transition hover:bg-amber-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700 sm:text-sm">View order</a>@else<a href="{{ route('seller.orders.show', $conversation->sellerOrder) }}" class="shrink-0 rounded-full px-3 py-2 text-xs font-semibold text-emerald-900 transition hover:bg-amber-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700 sm:text-sm">View order</a>@endif
                </div>
            @endif

            <div wire:offline class="mx-3 mt-2 shrink-0 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 sm:mx-5" role="status">You’re offline. Messages will send when your connection returns.</div>
            <div id="conversation-messages" class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-5 sm:px-6" role="log" aria-live="polite" aria-label="Conversation messages" tabindex="0">
                @if ($hasMoreMessages)<div class="mb-5 text-center"><button wire:click="loadOlderMessages" wire:loading.attr="disabled" type="button" class="rounded-full bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700 disabled:opacity-60">Load earlier messages</button></div>@endif
                @php($previousDate = null)
                @php($previousSenderId = null)
                @forelse ($messages as $message)
                    @if ($previousDate?->toDateString() !== $message->created_at->toDateString())<div class="my-5 flex items-center gap-3 text-xs font-medium text-slate-500"><span class="h-px flex-1 bg-slate-200"></span><span>{{ $message->created_at->isToday() ? 'Today' : ($message->created_at->isYesterday() ? 'Yesterday' : $message->created_at->format('F j, Y')) }}</span><span class="h-px flex-1 bg-slate-200"></span></div>@endif
                    <article wire:key="message-{{ $message->id }}" class="mb-1 flex items-end gap-2 {{ $message->sender_id === auth()->id() ? 'justify-end' : 'justify-start' }}">
                        @if ($message->sender_id !== auth()->id())
                            <span class="grid size-7 shrink-0 place-items-center rounded-full bg-emerald-100 text-[11px] font-bold text-emerald-900 {{ $message->sender_id === $previousSenderId ? 'invisible' : '' }}" aria-hidden="true">{{ Str::upper(Str::substr($message->sender->name, 0, 1)) }}</span>
                        @endif
                        <div class="min-w-0 max-w-[82%] sm:max-w-[70%] {{ $message->sender_id === $previousSenderId ? '' : 'mt-3' }}">
                            <div class="rounded-[20px] px-4 py-2.5 text-sm leading-6 {{ $message->sender_id === auth()->id() ? 'rounded-br-md bg-emerald-700 text-white' : 'rounded-bl-md bg-slate-100 text-slate-800' }}">
                                @if ($message->type === 'file' && $message->attachments->isNotEmpty())
                                    @php($attachment = $message->attachments->first())
                                    @if (in_array($attachment->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true))
                                        <a href="{{ route('messages.attachments.show', $attachment) }}" target="_blank" rel="noopener" class="block">
                                            <img src="{{ route('messages.attachments.show', $attachment) }}" alt="{{ $attachment->original_name }}" loading="lazy" decoding="async" class="max-h-80 max-w-full rounded-lg object-contain">
                                            <span class="mt-1 block text-xs underline underline-offset-2">{{ $attachment->original_name }}</span>
                                        </a>
                                    @else
                                        <a href="{{ route('messages.attachments.show', $attachment) }}" class="flex w-56 max-w-full min-w-0 items-center gap-3 rounded-xl border border-slate-200 bg-white/80 p-3 text-left transition hover:bg-white">
                                            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg {{ $message->sender_id === auth()->id() ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600' }}" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3.75h6l5 5v11.5H7a2 2 0 0 1-2-2v-12a2 2 0 0 1 2-2Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13 4v5h5M8.5 14h7M8.5 17h5"/></svg>
                                            </span>
                                            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold">{{ $attachment->original_name }}</span><span class="mt-0.5 block text-xs opacity-75">PDF document · Download</span></span>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4 shrink-0 opacity-75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75v11m0 0 4-4m-4 4-4-4M5 16.75v3.5h14v-3.5"/></svg>
                                        </a>
                                    @endif
                                @else
                                    <p class="whitespace-pre-wrap break-words [overflow-wrap:anywhere]">{{ $message->body }}</p>
                                @endif
                                <time datetime="{{ $message->created_at->toIso8601String() }}" class="mt-1 block text-[11px] {{ $message->sender_id === auth()->id() ? 'text-emerald-100' : 'text-slate-500' }}">{{ $message->created_at->format('g:i A') }}</time>
                                @if ($message->id === $lastMessage?->id && $message->sender_id === auth()->id())
                                    <span class="mt-0.5 block text-right text-[11px] text-emerald-100">{{ $receipt }}</span>
                                @endif
                            </div>
                            @if ($message->sender_id !== auth()->id())<details class="mt-1 px-1 text-xs"><summary class="cursor-pointer text-slate-500">Report message</summary><form method="POST" action="{{ route('messages.report', [$conversation, $message]) }}" class="mt-2 space-y-2">@csrf<select name="reason" required class="rounded-lg border-slate-300 text-xs"><option value="Spam">Spam</option><option value="Abuse">Abusive content</option><option value="Other">Other</option></select><textarea name="details" maxlength="1000" rows="2" aria-label="Report details" class="block w-full rounded-lg border-slate-300 text-xs"></textarea><button class="rounded-lg bg-slate-900 px-3 py-2 font-semibold text-white">Submit report</button></form></details>@endif
                        </div>
                    </article>
                    @php($previousDate = $message->created_at)
                    @php($previousSenderId = $message->sender_id)
                @empty
                    <div class="flex min-h-64 flex-col items-center justify-center px-6 text-center"><div class="grid size-14 place-items-center rounded-full bg-emerald-50 text-emerald-800" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H5l-1 2v-6.5a7.5 7.5 0 1 1 16-3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 11.5h8M8 14.5h5"/></svg></div><p class="mt-3 text-sm font-semibold text-slate-900">Start the conversation</p><p class="mt-1 text-sm text-slate-600">Send a message about this {{ $conversation->product ? 'product' : 'order' }}.</p></div>
                @endforelse
            </div>
            <div class="shrink-0 border-t border-slate-200 bg-white p-3 sm:p-4">
            <form wire:submit="send" class="space-y-3">
                @cannot('send', $conversation)<p class="mb-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900" role="status">Messaging is unavailable for this conversation.</p>@endcannot
                @if (auth()->user()->account_type !== 'buyer')
                    <div class="mb-3 flex gap-2 overflow-x-auto pb-1" role="group" aria-label="Quick replies">@foreach (['Yes, this item is available.', 'Your order is being prepared.', 'We’ll update you once your order ships.', 'Could you provide more details?'] as $reply)<button wire:click='useQuickReply(@js($reply))' type="button" class="shrink-0 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-900 hover:bg-emerald-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700">{{ $reply }}</button>@endforeach</div>
                @endif
                <label for="message-body" class="sr-only">Write a message</label>
                <div class="flex items-end gap-2 sm:gap-3"><textarea wire:model="body" x-on:input.debounce.500ms="$wire.updateTypingStatus(true); clearTimeout(window.chatTypingTimeout); window.chatTypingTimeout = setTimeout(() => $wire.updateTypingStatus(false), 3000)" x-on:blur="$wire.updateTypingStatus(false)" x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.send(); }" id="message-body" rows="2" maxlength="2000" class="min-h-12 w-full resize-y rounded-2xl border-transparent bg-slate-100 text-sm text-slate-900 placeholder:text-slate-500 focus:border-emerald-700 focus:bg-white focus:ring-emerald-700" placeholder="Write a message…" aria-describedby="message-help" @disabled(! auth()->user()->can('send', $conversation))></textarea><button type="submit" aria-label="Send message" class="grid size-12 shrink-0 place-items-center rounded-full bg-emerald-800 text-white transition hover:bg-emerald-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700 disabled:cursor-not-allowed disabled:opacity-50" wire:loading.attr="disabled" wire:target="send" @disabled(! auth()->user()->can('send', $conversation))><svg wire:loading.remove wire:target="send" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4 11 16-7-7 16-2-7-7-2ZM11 13l4-4"/></svg><span wire:loading wire:target="send" class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span></button></div>
                <div id="message-help" class="flex items-center justify-between gap-3 text-xs text-slate-500"><span class="hidden sm:inline">Enter to send · Shift + Enter for a new line</span><span class="ml-auto tabular-nums">{{ Str::length($body) }}/2000</span></div>
                @error('body')<p class="mt-2 text-sm font-medium text-red-700" role="alert">{{ $message }}</p>@enderror
            </form>
            @can('send', $conversation)
                <div class="mt-3 flex flex-wrap items-center gap-2" role="group" aria-label="Add an attachment">
                    <div class="flex flex-wrap items-center gap-2">
                        <form method="POST" action="{{ route('messages.attachments.store', $conversation) }}" enctype="multipart/form-data" x-data="{ preview: null, name: '' }" class="relative">
                            @csrf<input type="hidden" name="client_message_id" value="{{ $clientMessageId }}">
                            <label for="chat-photo" class="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-full border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-900 focus-within:ring-2 focus-within:ring-emerald-600">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4" aria-hidden="true"><rect x="3.75" y="4.75" width="16.5" height="14.5" rx="2.5"/><circle cx="9" cy="10" r="1.5"/><path stroke-linecap="round" stroke-linejoin="round" d="m5 17 4.5-4 3 2.5 2.5-2 4 3.5"/></svg>
                                Photo
                                <input id="chat-photo" name="attachment" type="file" accept="image/jpeg,image/png,image/webp" required class="sr-only" x-on:change="const file = $event.target.files[0]; if (preview) URL.revokeObjectURL(preview); preview = file ? URL.createObjectURL(file) : null; name = file?.name ?? ''">
                            </label>
                            <div x-cloak x-show="preview" class="absolute bottom-12 left-0 z-10 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-lg">
                                <img x-bind:src="preview" x-bind:alt="name" class="max-h-36 w-full rounded-lg object-contain">
                                <p x-text="name" class="mt-2 truncate text-xs font-medium text-slate-700"></p>
                                <button type="submit" class="mt-2 w-full rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Send photo</button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('messages.attachments.store', $conversation) }}" enctype="multipart/form-data" x-data="{ name: '' }" class="relative">
                            @csrf<input type="hidden" name="client_message_id" value="{{ $clientMessageId }}">
                            <label for="chat-document" class="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-full border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-900 focus-within:ring-2 focus-within:ring-emerald-600">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3.75h6l5 5v11.5H7a2 2 0 0 1-2-2v-12a2 2 0 0 1 2-2Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13 4v5h5M8.5 14h7M8.5 17h5"/></svg>
                                Document
                                <input id="chat-document" name="attachment" type="file" accept="application/pdf" required class="sr-only" x-on:change="name = $event.target.files[0]?.name ?? ''">
                            </label>
                            <div x-cloak x-show="name" class="absolute bottom-12 left-0 z-10 w-64 rounded-xl border border-slate-200 bg-white p-3 shadow-lg">
                                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">PDF document</p>
                                <p x-text="name" class="mt-1 truncate text-sm font-semibold text-slate-800"></p>
                                <button type="submit" class="mt-3 w-full rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Send document</button>
                            </div>
                        </form>
                        <span class="text-xs text-slate-500">Photos: JPG, PNG, WebP · Documents: PDF · Up to 5 MB</span>
                    </div>
                </div>
            @endcan
            </div>
        </section>
        </div>
    </div>
</div>

@script
<script>
    const messageList = document.getElementById('conversation-messages');
    let previousMessageScroll = null;

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

    requestAnimationFrame(() => scrollToLatestMessage('auto'));

    Livewire.hook('morphed', ({ el }) => {
        if (! messageList || (el !== messageList && ! el.contains(messageList))) {
            return;
        }

        requestAnimationFrame(() => {
            if (previousMessageScroll) {
                messageList.scrollTop = previousMessageScroll.top + messageList.scrollHeight - previousMessageScroll.height;
                previousMessageScroll = null;

                return;
            }

            scrollToLatestMessage();
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
