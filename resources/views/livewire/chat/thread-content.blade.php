            <section x-data="chatThread(@js($conversation->id), @js(auth()->id()))" x-on:chat-upload-progress="attachmentProgress($event.detail.progress)" x-on:chat-upload-error="attachmentFailed($event.detail.message)" x-on:chat-upload-sent="attachmentSent($event.detail)" class="flex h-full min-h-0 min-w-0 flex-col overflow-hidden bg-surface">
                <header class="flex min-w-0 shrink-0 items-center justify-between gap-2 border-b border-line px-3 py-3 sm:gap-4 sm:px-5">
                <div class="flex min-w-0 items-center gap-2.5 sm:gap-3">
                    <a href="{{ auth()->user()->account_type === 'seller' ? route('messages.index', ['context' => $isBuyerMessages ? 'buying' : 'selling']) : route('messages.index') }}" class="grid size-11 shrink-0 place-items-center rounded-full text-muted transition hover:bg-canvas hover:text-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand lg:hidden" aria-label="Back to messages"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6M9 12h11"/></svg></a>
                    <div class="flex min-w-0 items-center gap-3">
                        @if ($isBuyerMessages && $conversation->store->logo_path)
                            <img src="{{ Storage::disk('public')->url($conversation->store->logo_path) }}" alt="" class="size-11 shrink-0 rounded-full object-cover">
                        @else
                            <span class="inline-flex size-11 shrink-0 items-center justify-center rounded-full bg-blue-50 text-sm font-bold text-brand-hover" aria-hidden="true">{{ Str::upper(Str::substr($isBuyerMessages ? $conversation->store->name : $conversation->buyer->name, 0, 1)) }}</span>
                        @endif
                        <div class="min-w-0">@if ($isBuyerMessages)<h1 class="truncate text-sm font-semibold text-ink sm:text-base">{{ $conversation->store->name }}</h1>@else<h2 class="truncate text-sm font-semibold text-ink sm:text-base">{{ $conversation->buyer->name }}</h2>@endif<p id="chat-presence" x-data="{ online: false }" x-on:chat-presence-status.window="if ($event.detail.conversationId === '{{ $conversation->id }}') online = $event.detail.online" class="flex items-center gap-1.5 truncate text-xs"><span data-presence-dot x-cloak x-show="online" class="size-1.5 shrink-0 rounded-full bg-blue-500"></span><span data-presence-label data-context="{{ $conversation->product ? 'Product inquiry' : 'Order conversation' }}" class="min-w-0 truncate" x-text="online ? 'Online · ' + $el.dataset.context : $el.dataset.context">{{ $conversation->product ? 'Product inquiry' : 'Order conversation' }}</span></p><p id="chat-typing" x-ref="typingIndicator" class="hidden truncate text-xs font-medium text-brand-hover" aria-live="polite">Typing…</p></div>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    @if ($isBuyerMessages)
                        <a href="{{ route('catalog.store', $conversation->store->slug) }}" aria-label="Open store" class="inline-flex min-h-10 items-center gap-2 rounded-[10px] px-3 text-xs font-semibold text-brand-hover transition hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand sm:text-sm"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.25h16.5l-1.5 10h-13.5l-1.5-10ZM5 9.25l2-5h10l2 5M9 19.25v-6h6v6"/></svg><span class="hidden sm:inline">Store</span></a>
                    @endif
                    {{-- Blocking is rare and destructive, so it sits behind a menu rather than beside the store link. --}}
                    <div class="relative" x-data="{ menuOpen: false }" x-on:click.outside="menuOpen = false" x-on:keydown.escape.window="menuOpen = false">
                        <button type="button" x-on:click="menuOpen = ! menuOpen" x-bind:aria-expanded="menuOpen" aria-haspopup="true" aria-label="Conversation options" class="grid size-11 place-items-center rounded-[10px] text-muted transition hover:bg-canvas hover:text-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
                            <svg viewBox="0 0 24 24" fill="currentColor" class="size-5" aria-hidden="true"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                        </button>
                        <div x-cloak x-show="menuOpen" class="absolute right-0 top-12 z-30 w-56 rounded-[12px] border border-line bg-white p-1.5 shadow-lg">
                            <form method="POST" action="{{ route('messages.block', $conversation) }}">@csrf<button type="submit" class="flex min-h-11 w-full items-center gap-2.5 rounded-[10px] px-3 text-left text-sm font-medium text-rose-700 transition hover:bg-rose-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-rose-700" onclick="return confirm('Block this contact? This stops messages between you in every conversation, until you unblock them.')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4 shrink-0" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path stroke-linecap="round" d="m6.2 6.2 11.6 11.6"/></svg>Block this contact</button></form>
                        </div>
                    </div>
                </div>
            </header>
            @if ($conversation->product)
                <div class="mx-3 mt-2 flex min-w-0 shrink-0 items-center gap-3 rounded-[14px] bg-canvas p-2.5 sm:mx-5">
                    @if ($conversation->product->images->first())<img src="{{ Storage::disk('product_images')->url($conversation->product->images->first()->path) }}" alt="" class="size-12 shrink-0 rounded-lg object-cover">@else<div class="grid size-12 shrink-0 place-items-center rounded-lg bg-slate-100 text-muted" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9ZM4.5 7.75 12 12l7.5-4.25M12 12v8.5"/></svg></div>@endif
                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-ink">{{ $conversation->product->name }}</p><p class="mt-0.5 truncate text-xs text-muted">₱{{ number_format($conversation->product->price, 2) }} · {{ $conversation->product->stock_quantity > 0 ? 'In stock' : 'Currently unavailable' }}</p></div>
                    <a href="{{ route('catalog.show', [$conversation->store->slug, $conversation->product->slug]) }}" class="shrink-0 rounded-[10px] px-3 py-2 text-xs font-semibold text-brand-hover transition hover:bg-blue-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand sm:text-sm">View item</a>
                </div>
            @elseif ($conversation->sellerOrder)
                <div class="mx-3 mt-2 flex min-w-0 shrink-0 items-center gap-3 rounded-[14px] bg-amber-50/70 p-2.5 sm:mx-5">
                    <span class="grid size-12 shrink-0 place-items-center rounded-lg bg-amber-100 text-amber-800" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 7.5h14l1 13H4l1-13ZM9 8V6a3 3 0 0 1 6 0v2"/></svg></span>
                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-ink">Order {{ $conversation->sellerOrder->order->number }}</p><p class="mt-0.5 truncate text-xs text-muted">{{ $conversation->sellerOrder->created_at->format('M j, Y') }} · ₱{{ number_format($conversation->sellerOrder->total, 2) }} · {{ Str::headline($conversation->sellerOrder->status) }}</p></div>
                    @if ($isBuyerMessages)<a href="{{ route('orders.show', $conversation->sellerOrder->order) }}" class="shrink-0 rounded-[10px] px-3 py-2 text-xs font-semibold text-brand-hover transition hover:bg-amber-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand sm:text-sm">View order</a>@else<a href="{{ route('seller.orders.show', $conversation->sellerOrder) }}" class="shrink-0 rounded-[10px] px-3 py-2 text-xs font-semibold text-brand-hover transition hover:bg-amber-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand sm:text-sm">View order</a>@endif
                </div>
            @endif

            <div x-cloak x-show="!online" class="mx-3 mt-2 shrink-0 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 sm:mx-5" role="status">You’re offline. Sending is paused until your connection returns.</div>
            <div x-cloak x-show="online && !liveConnected" class="mx-3 mt-2 shrink-0 rounded-lg bg-blue-50 px-3 py-2 text-sm text-brand-hover sm:mx-5" role="status">Reconnecting live updates. You can still send messages.</div>
            <div id="conversation-messages" x-ref="messageList" class="chat-message-list min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-5 sm:px-6 sm:py-6" role="log" aria-live="polite" aria-label="Conversation messages" tabindex="0">
                @if ($hasMoreMessages)<div class="mb-5 text-center"><button x-on:click="loadOlder()" x-bind:disabled="loadingOlder" type="button" class="inline-flex min-h-11 items-center rounded-[10px] bg-white px-4 text-sm font-semibold text-ink shadow-sm transition hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand disabled:opacity-60">Load earlier messages</button><span x-show="loadingOlder" class="ml-2 text-xs text-muted" role="status">Loading messages…</span><p x-cloak x-show="historyError" x-text="historyError" class="mt-2 text-sm text-red-700" role="alert"></p></div>@endif
                @php($previousDate = null)
                @php($previousSenderId = null)
                @forelse ($messages as $message)
                    <div wire:key="message-date-{{ $message->id }}" data-chat-date-separator data-chat-date="{{ $message->created_at->toIso8601String() }}" class="{{ $previousDate?->toDateString() === $message->created_at->toDateString() ? 'hidden ' : '' }}my-6 flex items-center gap-3"><span class="h-px flex-1 bg-line"></span><span data-chat-date-label class="shrink-0 rounded-full border border-line bg-white px-3 py-1 text-[11px] font-semibold text-muted shadow-sm">{{ $message->created_at->isToday() ? 'Today' : ($message->created_at->isYesterday() ? 'Yesterday' : $message->created_at->format('F j, Y')) }}</span><span class="h-px flex-1 bg-line"></span></div>
                    <article wire:key="message-{{ $message->id }}" data-client-message-id="{{ $message->client_message_id }}" class="mb-1 flex items-end gap-2 {{ $message->sender_id === auth()->id() ? 'justify-end' : 'justify-start' }}">
                        @if ($message->sender_id !== auth()->id())
                            <span class="grid size-8 shrink-0 place-items-center rounded-full border border-line bg-white text-xs font-bold text-brand-hover shadow-sm {{ $message->sender_id === $previousSenderId ? 'invisible' : '' }}" aria-hidden="true">{{ Str::upper(Str::substr($message->sender->name, 0, 1)) }}</span>
                        @endif
                        <div class="min-w-0 w-[85%] sm:w-[72%] lg:w-[36rem] {{ $message->sender_id === $previousSenderId ? '' : 'mt-4' }}">
                            @if ($message->sender_id !== auth()->id() && $message->sender_id !== $previousSenderId)<p class="mb-1 ml-1 text-xs font-semibold text-muted">{{ $message->sender->name }}</p>@endif
                            <div class="chat-message-bubble w-fit max-w-full rounded-[18px] border px-4 py-3 text-sm leading-[1.6] {{ $message->sender_id === auth()->id() ? 'ml-auto rounded-br-[6px] border-brand bg-brand text-white' : 'rounded-bl-[6px] border-line bg-white text-ink' }}">
                                @if ($message->type === 'file' && $message->attachments->isNotEmpty())
                                    @php($attachment = $message->attachments->first())
                                    @if (in_array($attachment->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true))
                                        <a href="{{ route('messages.attachments.show', $attachment) }}" target="_blank" rel="noopener" aria-label="Open {{ $attachment->original_name }} in a larger view" class="block">
                                            <img src="{{ route('messages.attachments.show', $attachment) }}" alt="{{ $attachment->original_name }}" loading="lazy" decoding="async" class="max-h-80 max-w-full rounded-lg object-contain">
                                            <span class="mt-1 block text-xs underline underline-offset-2">{{ $attachment->original_name }}</span>
                                        </a>
                                    @else
                                        <div class="w-64 max-w-full min-w-0 rounded-[12px] border border-line bg-white p-3 text-ink">
                                            <div class="flex min-w-0 items-center gap-3">
                                            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg {{ $message->sender_id === auth()->id() ? 'bg-brand text-white' : 'bg-slate-100 text-muted' }}" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3.75h6l5 5v11.5H7a2 2 0 0 1-2-2v-12a2 2 0 0 1 2-2Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13 4v5h5M8.5 14h7M8.5 17h5"/></svg>
                                            </span>
                                            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold">{{ $attachment->original_name }}</span><span class="mt-0.5 block text-xs text-muted">PDF · {{ $attachment->byte_size < 1024 ? $attachment->byte_size.' B' : number_format($attachment->byte_size / 1024, 1).' KB' }}</span></span>
                                            </div>
                                            <div class="mt-2 flex gap-3 border-t border-line pt-2 text-xs font-semibold text-brand-hover"><a href="{{ route('messages.attachments.show', $attachment) }}?preview=1" target="_blank" rel="noopener" class="rounded underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Open</a><a href="{{ route('messages.attachments.show', $attachment) }}" class="rounded underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Download</a></div>
                                        </div>
                                    @endif
                                    @if (filled($message->body))
                                        <p class="mt-2 whitespace-pre-wrap break-words [overflow-wrap:anywhere]">{{ $message->body }}</p>
                                    @endif
                                @else
                                    <p class="whitespace-pre-wrap break-words [overflow-wrap:anywhere]">{{ $message->body }}</p>
                                @endif
                            </div>
                            @php($nextMessage = $messages->get($loop->index + 1))
                            @php($endsRun = $nextMessage === null
                                || $nextMessage->sender_id !== $message->sender_id
                                || ! $nextMessage->created_at->isSameMinute($message->created_at))
                            @if ($endsRun)
                                <div class="mt-1 flex items-center gap-1.5 px-1 text-[11px] font-medium text-muted {{ $message->sender_id === auth()->id() ? 'justify-end' : 'justify-start' }}">
                                    <time datetime="{{ $message->created_at->toIso8601String() }}" data-chat-local-time>{{ $message->created_at->format('g:i A') }}</time>
                                    @if ($message->id === $lastMessage?->id && $message->sender_id === auth()->id())
                                        <span aria-hidden="true">·</span><span>{{ $receipt }}</span>
                                    @endif
                                </div>
                            @endif
                            @if ($message->sender_id !== auth()->id())<details x-on:toggle="if ($el.open) $nextTick(() => $el.querySelector('form').scrollIntoView({ block: 'nearest' }))" class="mt-0.5 px-1 text-xs"><summary class="inline-flex min-h-8 cursor-pointer list-none items-center gap-1.5 rounded-md px-1.5 text-[11px] font-medium text-muted transition hover:bg-white hover:text-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand [&::-webkit-details-marker]:hidden"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-3.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 21V4m0 1c4-3 7 3 14 0v11c-7 3-10-3-14 0"/></svg>Report</summary><form method="POST" action="{{ route('messages.report', [$conversation, $message]) }}" class="mt-2 w-64 max-w-full space-y-2 rounded-[12px] border border-line bg-white p-3 shadow-sm">@csrf<select name="reason" required aria-label="Report reason" class="min-h-10 w-full rounded-lg border-line text-sm"><option value="Spam">Spam</option><option value="Abuse">Abusive content</option><option value="Other">Other</option></select><textarea name="details" maxlength="1000" rows="2" aria-label="Report details" class="block w-full rounded-lg border-line text-sm"></textarea><button class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-3 text-sm font-semibold text-white transition hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Submit report</button></form></details>@endif
                        </div>
                    </article>
                    @php($previousDate = $message->created_at)
                    @php($previousSenderId = $message->sender_id)
                @empty
                    <div x-show="pending.length === 0" class="flex min-h-64 flex-col items-center justify-center px-6 text-center"><div class="grid size-14 place-items-center rounded-full bg-blue-50 text-brand-hover" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H5l-1 2v-6.5a7.5 7.5 0 1 1 16-3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 11.5h8M8 14.5h5"/></svg></div><p class="mt-3 text-sm font-semibold text-ink">Start the conversation</p><p class="mt-1 text-sm text-muted">Send a message about this {{ $conversation->product ? 'product' : 'order' }}.</p></div>
                @endforelse
                <div wire:ignore aria-live="polite">
                    <template x-for="item in pending" :key="item.id">
                        <article class="mb-1 flex justify-end" :data-pending-message-id="item.id">
                            <div class="mt-4 min-w-0 w-[85%] sm:w-[72%] lg:w-[36rem]">
                                <div class="chat-message-bubble ml-auto w-fit max-w-full rounded-[18px] rounded-br-[6px] border border-brand bg-brand px-4 py-3 text-sm leading-[1.6] text-white">
                                    <template x-if="item.previewUrl"><img :src="item.previewUrl" alt="Pending photo" class="mb-2 max-h-80 max-w-full rounded-lg object-contain"></template>
                                    <p x-show="item.fileName" x-text="item.fileName" class="truncate text-xs font-semibold"></p>
                                    <p x-show="item.body" x-text="item.body" class="whitespace-pre-wrap break-words [overflow-wrap:anywhere]"></p>
                                </div>
                                <div class="mt-1 flex items-center justify-end gap-2 px-1 text-[11px] font-medium" :class="item.status === 'Failed' ? 'text-red-700' : 'text-muted'">
                                    <time x-text="item.time"></time><span aria-hidden="true">·</span>
                                    <span x-text="item.status === 'Sending' && item.progress !== null ? 'Uploading ' + item.progress + '%' : item.status"></span>
                                    <button x-show="item.status === 'Failed'" type="button" x-on:click="retry(item)" class="min-h-10 rounded-lg px-2 font-semibold text-brand-hover underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Retry</button>
                                </div>
                            </div>
                        </article>
                    </template>
                </div>
                @if ($hasNewerMessages)
                    <div class="mt-4 flex justify-center gap-2">
                        <button wire:click="loadNewerMessages" wire:loading.attr="disabled" type="button" class="min-h-11 rounded-full bg-canvas px-4 text-sm font-semibold text-ink hover:bg-line focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Load newer messages</button>
                        <button wire:click="jumpToLatest" wire:loading.attr="disabled" type="button" class="min-h-11 rounded-full bg-blue-50 px-4 text-sm font-semibold text-brand-hover hover:bg-blue-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Jump to latest</button>
                    </div>
                @endif
            </div>
            <div wire:cloak @if ($canSend) x-on:dragover.prevent x-on:drop.prevent="chooseFile($event.dataTransfer.files[0])" @endif class="shrink-0 border-t border-line bg-white px-3 pb-3 pt-3 shadow-[0_-8px_24px_rgb(15_23_42_/_0.04)] sm:px-5 sm:pb-4 sm:pt-4">
                @unless ($canSend)
                    <div class="mb-3 flex flex-wrap items-center gap-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900" role="status">
                        <p class="min-w-0 flex-1">{{ $canUnblock ? 'You blocked this contact, so messaging is paused for both of you.' : 'Messaging is unavailable for this conversation.' }}</p>
                        @if ($canUnblock)
                            <form method="POST" action="{{ route('messages.unblock', $conversation) }}" class="shrink-0">@csrf @method('DELETE')<button type="submit" class="inline-flex min-h-10 items-center rounded-[10px] bg-amber-900 px-3 text-xs font-semibold text-white transition hover:bg-amber-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-900 sm:text-sm">Unblock</button></form>
                        @endif
                    </div>
                @endunless
                @if (! $isBuyerMessages && $canSend)
                    <div class="mb-3 flex max-w-full gap-2 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:overflow-visible" role="group" aria-label="Quick replies">@foreach (['Yes, this item is available.', 'Your order is being prepared.', 'We’ll update you once your order ships.', 'Could you provide more details?'] as $reply)<button x-on:click="useQuickReply(@js($reply))" type="button" class="inline-flex min-h-11 shrink-0 items-center rounded-[10px] border border-blue-200 bg-blue-50 px-3 text-xs font-medium text-brand-hover hover:bg-blue-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand sm:min-w-0 sm:flex-1 sm:shrink sm:justify-center sm:text-center">{{ $reply }}</button>@endforeach</div>
                @endif
                @if ($canSend)
                    <div x-cloak x-show="selectedType" class="mb-3 flex max-w-full items-center gap-2 rounded-[10px] border border-line bg-canvas px-2 py-1.5 text-sm text-ink" role="status">
                        <img x-cloak x-show="previewUrl" x-bind:src="previewUrl" alt="Selected photo preview" class="size-10 shrink-0 rounded-md object-cover">
                        <span x-show="selectedType === 'document'" class="grid size-10 shrink-0 place-items-center rounded-md bg-blue-50 text-xs font-bold text-brand-hover" aria-hidden="true">PDF</span>
                        <span class="min-w-0 flex-1"><span x-text="fileName" class="block truncate font-medium"></span><span x-text="fileSize" class="block text-xs text-muted"></span></span>
                        <button type="button" x-on:click="clearFile()" x-bind:disabled="uploading" class="grid size-10 shrink-0 place-items-center rounded-lg text-muted hover:bg-line hover:text-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand disabled:opacity-50" aria-label="Remove attachment"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
                    </div>
                @endif
                {{-- The placeholder carries the prompt; the label stays as the field's name. --}}
                <label for="message-body" class="sr-only">Write a message</label>
                <form x-ref="messageForm" x-on:submit.prevent="submitMessage()">
                <div class="flex min-w-0 items-end gap-2">
                    @if ($canSend)
                        <div class="shrink-0">
                            <button type="button" x-on:click="$refs.attachmentInput.click()" x-bind:disabled="uploading" aria-label="Add attachment" title="Attach a JPG, PNG, WebP or PDF (up to 5 MB)" class="grid size-11 place-items-center rounded-[10px] border border-line bg-white text-ink transition hover:border-blue-300 hover:bg-blue-50 hover:text-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand disabled:cursor-not-allowed disabled:opacity-50"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 10.5 12 18.5a5 5 0 0 1-7-7L13 3.5a3.5 3.5 0 0 1 5 5l-8 8a2 2 0 0 1-3-3l7.5-7.5"/></svg></button>
                            <div wire:ignore><input id="chat-attachment" x-ref="attachmentInput" form="chat-attachment-form" name="attachment" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" aria-label="Image or PDF document" tabindex="-1" required class="sr-only" x-on:change="chooseFile($event.target.files[0])"></div>
                        </div>
                    @endif
                    <textarea wire:model="body" x-ref="messageBody" x-on:input="$wire.body = $el.value; fitComposer()" x-on:input.debounce.500ms="$wire.startTyping()" x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $refs.messageForm.requestSubmit(); }" id="message-body" rows="1" maxlength="2000" class="min-h-11 min-w-0 flex-1 resize-none overflow-y-auto rounded-[10px] border-line bg-canvas px-3 py-2 text-sm leading-6 text-ink placeholder:text-muted focus:border-brand focus:bg-white focus:ring-brand" placeholder="Type your message..." aria-describedby="message-help" @disabled(! $canSend)></textarea>
                    <button type="submit" x-bind:aria-label="selectedType === 'photo' ? 'Send photo' : selectedType === 'document' ? 'Send document' : 'Send message'" x-bind:disabled="!online || uploading" class="grid size-11 shrink-0 place-items-center rounded-[10px] bg-brand text-white transition hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-not-allowed disabled:opacity-50" @disabled(! $canSend)><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4 11 16-7-7 16-2-7-7-2ZM11 13l4-4"/></svg></button>
                </div>
                <div id="message-help" class="flex items-center justify-end text-xs text-muted"><span x-cloak x-show="Array.from($wire.body).length > 1800" class="mt-1 tabular-nums" x-text="Array.from($wire.body).length + '/2000'"></span></div>
                @error('body')<p class="mt-2 text-sm font-medium text-red-700" role="alert">{{ $message }}</p>@enderror
                @error('attachment')<p class="mt-2 text-sm font-medium text-red-700" role="alert">{{ $message }}</p>@enderror
                <div x-cloak x-show="uploading" class="mt-2 text-xs text-muted" role="status"><span>Uploading attachment</span><span class="ml-1 tabular-nums" x-text="uploadProgress + '%'">0%</span><div class="mt-1 h-1.5 overflow-hidden rounded-full bg-line" role="progressbar" aria-label="Attachment upload progress" aria-valuemin="0" aria-valuemax="100" x-bind:aria-valuenow="uploadProgress"><div class="h-full rounded-full bg-brand transition-[width]" x-bind:style="'width: ' + uploadProgress + '%'"></div></div></div>
                <p x-cloak x-show="uploadError" x-text="uploadError" class="mt-2 text-sm font-medium text-red-700" role="alert"></p>
                </form>
                @if ($canSend)
                    <form id="chat-attachment-form" x-ref="attachmentForm" wire:ignore method="POST" action="{{ route('messages.attachments.store', $conversation) }}" enctype="multipart/form-data" data-clerk-session-refresh="{{ route('clerk.session.refresh') }}" data-chat-upload class="hidden">@csrf<input type="hidden" name="client_message_id" value="{{ $clientMessageId }}"><input type="hidden" name="body" value=""></form>
                @endif
            </div>
        </section>
