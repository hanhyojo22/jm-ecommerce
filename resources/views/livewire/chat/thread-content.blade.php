            <section x-data="chatThread(@js($conversation->id), @js(auth()->id()))" x-on:chat-upload-progress="attachmentProgress($event.detail.progress)" x-on:chat-upload-error="attachmentFailed($event.detail.message)" x-on:chat-upload-sent="attachmentSent($event.detail)" class="flex h-full min-h-0 min-w-0 flex-col overflow-hidden bg-surface">
                <header class="flex min-w-0 shrink-0 items-center justify-between gap-2 border-b border-line bg-white px-3 py-3 sm:gap-4 sm:px-5">
                <div class="flex min-w-0 items-center gap-2 sm:gap-3">
                    <a href="{{ auth()->user()->account_type === 'seller' ? route('messages.index', ['context' => $isBuyerMessages ? 'buying' : 'selling']) : route('messages.index') }}" class="grid size-11 shrink-0 place-items-center rounded-full text-muted transition hover:bg-canvas hover:text-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand lg:hidden" aria-label="Back to messages"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6M9 12h11"/></svg></a>
                    <div class="flex min-w-0 items-center gap-2.5 sm:gap-3" x-data="{ online: false }" x-on:chat-presence-status.window="if ($event.detail.conversationId === @js($conversation->id)) online = $event.detail.online">
                        <span class="relative shrink-0">
                            <span class="grid size-11 place-items-center overflow-hidden rounded-full border border-line bg-blue-50 text-sm font-semibold leading-none text-brand-hover">
                                @if ($isBuyerMessages && $conversation->store->logo_path)
                                    <img src="{{ Storage::disk('public')->url($conversation->store->logo_path) }}" alt="" class="block size-full object-cover object-center">
                                @else
                                    {{ Str::upper(Str::substr($isBuyerMessages ? $conversation->store->name : $conversation->buyer->name, 0, 1)) }}
                                @endif
                            </span>
                            <span data-presence-dot x-cloak x-show="online" class="absolute bottom-0 right-0 size-3 rounded-full border-2 border-white bg-emerald-500" aria-hidden="true"></span>
                        </span>
                        <div class="min-w-0"><h1 class="truncate text-sm font-semibold text-ink sm:text-base">{{ $isBuyerMessages ? $conversation->store->name : $conversation->buyer->name }}</h1><p id="chat-presence" class="truncate text-xs text-muted"><span data-presence-label data-context="{{ $headerContextLabel }}" class="block truncate" x-text="online ? 'Online · ' + $el.dataset.context : $el.dataset.context">{{ $headerContextLabel }}</span></p><p id="chat-typing" x-ref="typingIndicator" class="hidden truncate text-xs font-medium text-brand-hover" aria-live="polite">Typing…</p></div>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    @if ($isBuyerMessages)
                        <a href="{{ route('catalog.store', $conversation->store->slug) }}" aria-label="Open store" class="inline-flex min-h-10 items-center gap-2 rounded-md px-3 text-xs font-semibold text-brand-hover transition hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand sm:text-sm"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.25h16.5l-1.5 10h-13.5l-1.5-10ZM5 9.25l2-5h10l2 5M9 19.25v-6h6v6"/></svg><span class="hidden sm:inline">Store</span></a>
                    @endif
                    {{-- Blocking is rare and destructive, so it sits behind a menu rather than beside the store link. --}}
                    <div class="relative" x-data="{ menuOpen: false }" x-on:click.outside="menuOpen = false" x-on:keydown.escape.window="menuOpen = false">
                        <button type="button" x-on:click="menuOpen = ! menuOpen" x-bind:aria-expanded="menuOpen" aria-haspopup="true" aria-label="Conversation options" class="grid size-11 place-items-center rounded-md text-muted transition hover:bg-canvas hover:text-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
                            <svg viewBox="0 0 24 24" fill="currentColor" class="size-5" aria-hidden="true"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                        </button>
                        <div x-cloak x-show="menuOpen" class="absolute right-0 top-12 z-30 w-56 rounded-lg border border-line bg-white p-1.5 shadow-lg">
                            <form method="POST" action="{{ route('messages.block', $conversation) }}">@csrf<button type="submit" class="flex min-h-11 w-full items-center gap-2.5 rounded-md px-3 text-left text-sm font-medium text-rose-700 transition hover:bg-rose-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-rose-700" onclick="return confirm('Block this contact? This stops messages between you in every conversation, until you unblock them.')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4 shrink-0" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path stroke-linecap="round" d="m6.2 6.2 11.6 11.6"/></svg>Block this contact</button></form>
                        </div>
                    </div>
                </div>
            </header>
            <div x-cloak x-show="!online" class="mx-3 mt-2 shrink-0 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 sm:mx-5" role="status">You’re offline. Sending is paused until your connection returns.</div>
            <div x-cloak x-show="online && !liveConnected" class="mx-3 mt-2 shrink-0 rounded-lg bg-blue-50 px-3 py-2 text-sm text-brand-hover sm:mx-5" role="status">Reconnecting live updates. You can still send messages.</div>
            <div wire:loading.flex wire:target="jumpToLatest" class="min-h-0 flex-1 flex-col justify-end gap-4 overflow-hidden px-3 py-5 sm:px-6 sm:py-6" role="status" aria-label="Loading latest messages">
                @foreach (['w-2/3', 'ml-auto w-1/2', 'w-3/5', 'ml-auto w-2/5'] as $bubble)
                    <div class="h-12 animate-pulse motion-reduce:animate-none rounded-lg bg-slate-100 {{ $bubble }}" aria-hidden="true"></div>
                @endforeach
            </div>
            <div wire:loading.remove wire:target="jumpToLatest" id="conversation-messages" x-ref="messageList" class="chat-message-list min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-5 sm:px-6 sm:py-6" role="log" aria-live="polite" aria-label="Conversation messages" tabindex="0">
                @if ($hasMoreMessages)<div class="mb-5 text-center"><button x-on:click="loadOlder()" x-bind:disabled="loadingOlder" type="button" class="inline-flex min-h-11 items-center rounded-md bg-white px-4 text-sm font-semibold text-ink shadow-sm transition hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand disabled:opacity-60">Load earlier messages</button><span x-show="loadingOlder" class="ml-2 text-xs text-muted" role="status">Loading messages…</span><p x-cloak x-show="historyError" x-text="historyError" class="mt-2 text-sm text-red-700" role="alert"></p></div>@endif
                @php($previousDate = null)
                @php($previousSenderId = null)
                @forelse ($messages as $message)
                    <div wire:key="message-date-{{ $message->id }}" data-chat-date-separator data-chat-date="{{ $message->created_at->toIso8601String() }}" class="{{ $previousDate?->toDateString() === $message->created_at->toDateString() ? 'hidden ' : '' }}my-6 flex items-center gap-3"><span class="h-px flex-1 bg-line"></span><span data-chat-date-label class="shrink-0 rounded-full border border-line bg-white px-3 py-1 text-xs font-semibold text-muted shadow-sm">{{ $message->created_at->isToday() ? 'Today' : ($message->created_at->isYesterday() ? 'Yesterday' : $message->created_at->format('F j, Y')) }}</span><span class="h-px flex-1 bg-line"></span></div>
                    <article wire:key="message-{{ $message->id }}" id="message-{{ $message->id }}" data-client-message-id="{{ $message->client_message_id }}" x-data="{ menuOpen: false, menuUp: true, reporting: false }" class="group mb-1 scroll-mt-4">
                        @php($nextMessage = $messages->get($loop->index + 1))
                        @php($endsRun = $nextMessage === null
                            || $nextMessage->sender_id !== $message->sender_id
                            || ! $nextMessage->created_at->isSameMinute($message->created_at))
                        @if ($message->context_snapshot)
                            <div class="flex {{ $message->sender_id === auth()->id() ? 'justify-end' : 'justify-start' }} {{ $message->sender_id === $previousSenderId ? '' : 'mt-4' }}">
                                <div class="min-w-0 w-[85%] sm:w-[72%] lg:w-[36rem]">
                                    @if ($message->context_snapshot['type'] === 'product')
                                        <a @if (filled($message->context_snapshot['slug'] ?? null)) href="{{ route('catalog.show', [$message->context_snapshot['store_slug'] ?? $conversation->store->slug, $message->context_snapshot['slug']]) }}" @endif class="flex items-center gap-3 rounded-lg border border-blue-200 bg-blue-50/60 p-3 transition hover:bg-blue-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
                                            @if (filled($message->context_snapshot['image_path'] ?? null))
                                                <img src="{{ Storage::disk('product_images')->url($message->context_snapshot['image_path']) }}" alt="" class="size-12 shrink-0 rounded-md object-cover">
                                            @else
                                                <span class="grid size-12 shrink-0 place-items-center rounded-md bg-blue-100 text-brand-hover" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9ZM4.5 7.75 12 12l7.5-4.25M12 12v8.5"/></svg></span>
                                            @endif
                                            <span class="min-w-0 flex-1"><span class="block truncate font-semibold text-ink">{{ $message->context_snapshot['title'] ?? 'Product' }}</span><span class="block text-sm text-muted">₱{{ $message->context_snapshot['price'] ?? '0.00' }}</span></span>
                                        </a>
                                    @elseif ($message->context_snapshot['type'] === 'order')
                                        <a @if (filled($message->context_snapshot['order_id'] ?? null)) href="{{ $isBuyerMessages ? route('orders.show', $message->context_snapshot['order_id']) : route('seller.orders.show', $message->seller_order_id) }}" @endif class="flex items-center gap-3 rounded-lg border border-line bg-canvas p-3 transition hover:bg-line/50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
                                            <span class="grid size-12 shrink-0 place-items-center rounded-md bg-white text-muted" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 7.5h14l1 13H4l1-13ZM9 8V6a3 3 0 0 1 6 0v2"/></svg></span>
                                            <span class="min-w-0 flex-1"><span class="block truncate font-semibold text-ink">Order {{ $message->context_snapshot['number'] ?? '' }}</span><span class="block text-sm text-muted">₱{{ $message->context_snapshot['total'] ?? '0.00' }} · {{ Str::headline($message->context_snapshot['status'] ?? '') }}</span></span>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endif
                        <div class="{{ $message->sender_id === auth()->id() ? 'flex justify-end' : 'flex items-end gap-2' }} {{ $message->context_snapshot ? 'mt-2' : ($message->sender_id === $previousSenderId ? '' : 'mt-4') }}">
                            @if ($message->sender_id !== auth()->id())
                                <span class="grid size-8 shrink-0 place-items-center overflow-hidden rounded-full border border-line bg-white text-xs font-semibold leading-none text-brand-hover shadow-sm {{ $endsRun ? '' : 'invisible' }}" aria-hidden="true">
                                    @if ($isBuyerMessages && $conversation->store->logo_path)
                                        <img src="{{ Storage::disk('public')->url($conversation->store->logo_path) }}" alt="" class="block size-full object-cover object-center">
                                    @else
                                        {{ Str::upper(Str::substr($message->sender->name, 0, 1)) }}
                                    @endif
                                </span>
                            @endif
                            <div class="min-w-0 w-[85%] sm:w-[72%] lg:w-[36rem]">
                                @php($attachment = $message->type === 'file' ? $message->attachments->first() : null)
                                @php($isImageAttachment = $attachment !== null && in_array($attachment->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true))
                                @php($usesFlushAttachmentLayout = $attachment !== null && ($isImageAttachment || blank($message->body)))
                                @if ($message->replyTo)
                                    @php($quote = $message->replyTo->quote())
                                    @php($otherName = $isBuyerMessages ? $conversation->store->name : $conversation->buyer->name)
                                    <div class="flex flex-col {{ $message->sender_id === auth()->id() ? 'items-end' : 'items-start' }}">
                                        <p class="mb-1 flex items-center gap-1 px-1 text-xs text-muted"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-3.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14 4 9l5-5M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/></svg>{{ $message->sender_id === auth()->id() ? 'You' : $otherName }} replied to {{ $quote['sender_id'] === auth()->id() ? ($message->sender_id === auth()->id() ? 'yourself' : 'you') : ($quote['sender_id'] === $message->sender_id ? 'themselves' : $otherName) }}</p>
                                        <a href="#message-{{ $quote['id'] }}" data-reply-quote class="-mb-2 block max-w-full min-w-0 rounded-lg bg-slate-100 px-4 pb-4 pt-2 text-sm leading-5 text-muted transition hover:bg-slate-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
                                            @if ($quote['context_label'])<span class="block truncate font-medium text-ink/70">{{ $quote['context_label'] }}</span>@endif
                                            @if ($quote['body'] !== '')<span class="line-clamp-2 break-words">{{ $quote['body'] }}</span>@endif
                                        </a>
                                    </div>
                                @endif
                                {{-- Messenger-style: actions sit beside the bubble and reveal on hover. --}}
                                <div class="relative flex min-w-0 items-center gap-1 {{ $message->sender_id === auth()->id() ? 'flex-row-reverse' : '' }}">
                                <div class="chat-message-bubble min-w-0 w-fit max-w-full rounded-lg border text-sm leading-[1.6] {{ $usesFlushAttachmentLayout ? 'overflow-hidden p-0' : 'px-4 py-3' }} {{ $message->sender_id === auth()->id() ? 'rounded-br-[6px] border-brand bg-brand text-white' : 'rounded-bl-[6px] border-line bg-white text-ink' }}">
                                @if ($message->type === 'file' && $message->attachments->isNotEmpty())
                                    @if ($isImageAttachment)
                                        <a href="{{ route('messages.attachments.show', $attachment) }}" target="_blank" rel="noopener" aria-label="Open {{ $attachment->original_name }} in a larger view" class="block overflow-hidden">
                                            <img src="{{ route('messages.attachments.show', $attachment) }}" alt="{{ $attachment->original_name }}" loading="lazy" decoding="async" class="block max-h-80 max-w-full object-contain">
                                        </a>
                                    @else
                                        <div class="w-64 max-w-full min-w-0 bg-white p-3 text-ink {{ $usesFlushAttachmentLayout ? '' : 'rounded-lg border border-line' }}">
                                            <div class="flex min-w-0 items-center gap-3">
                                            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg {{ $message->sender_id === auth()->id() ? 'bg-brand text-white' : 'bg-slate-100 text-muted' }}" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3.75h6l5 5v11.5H7a2 2 0 0 1-2-2v-12a2 2 0 0 1 2-2Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13 4v5h5M8.5 14h7M8.5 17h5"/></svg>
                                            </span>
                                            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold">{{ $attachment->original_name }}</span><span class="mt-0.5 block text-xs text-muted">PDF · {{ $attachment->byte_size < 1024 ? $attachment->byte_size.' B' : number_format($attachment->byte_size / 1024, 1).' KB' }}</span></span>
                                            </div>
                                            <div class="mt-2 flex gap-3 border-t border-line pt-2 text-xs font-semibold text-brand-hover"><a href="{{ route('messages.attachments.show', $attachment) }}?preview=1" target="_blank" rel="noopener" class="rounded-md underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Open</a><a href="{{ route('messages.attachments.show', $attachment) }}" class="rounded-md underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Download</a></div>
                                        </div>
                                    @endif
                                    @if (filled($message->body))
                                        <p class="whitespace-pre-wrap break-words [overflow-wrap:anywhere] {{ $usesFlushAttachmentLayout ? 'px-4 pb-3 pt-2' : 'mt-2' }}">{{ $message->body }}</p>
                                    @endif
                                @else
                                    <p class="whitespace-pre-wrap break-words [overflow-wrap:anywhere]">{{ $message->body }}</p>
                                @endif
                                </div>
                                @if ($canSend || $message->sender_id !== auth()->id())
                                    <div class="relative shrink-0" x-on:click.outside="menuOpen = false" x-on:keydown.escape="menuOpen = false">
                                        <button type="button" x-on:click="menuUp = $el.getBoundingClientRect().top > 200; menuOpen = ! menuOpen" x-bind:aria-expanded="menuOpen" aria-haspopup="menu" aria-label="Message actions" x-bind:class="menuOpen && 'bg-line text-ink sm:opacity-100'" class="grid size-8 place-items-center rounded-full text-muted transition hover:bg-line hover:text-ink focus-visible:opacity-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand sm:opacity-0 sm:group-hover:opacity-100"><svg viewBox="0 0 24 24" fill="currentColor" class="size-4" aria-hidden="true"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg></button>
                                        <div x-cloak x-show="menuOpen" x-transition.opacity.duration.100ms role="menu" x-bind:class="menuUp ? 'bottom-full mb-1' : 'top-full mt-1'" class="absolute z-30 w-44 rounded-xl border border-line bg-white p-1.5 shadow-lg {{ $message->sender_id === auth()->id() ? 'right-0' : 'left-0' }}">
                                            @if ($canSend)<button type="button" role="menuitem" wire:click="replyTo(@js($message->id))" x-on:click="menuOpen = false; $nextTick(() => document.getElementById('message-body')?.focus())" class="flex min-h-10 w-full items-center gap-2.5 rounded-md px-3 text-left text-sm font-medium text-ink transition hover:bg-canvas focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-4 shrink-0" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14 4 9l5-5M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/></svg>Reply</button>@endif
                                            @if ($message->sender_id !== auth()->id())<button type="button" role="menuitem" x-on:click="menuOpen = false; reporting = true; $nextTick(() => $refs.reportForm.scrollIntoView({ block: 'nearest' }))" class="flex min-h-10 w-full items-center gap-2.5 rounded-md px-3 text-left text-sm font-medium text-rose-700 transition hover:bg-rose-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-rose-700"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-4 shrink-0" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 21V4m0 1c4-3 7 3 14 0v11c-7 3-10-3-14 0"/></svg>Report</button>@endif
                                        </div>
                                    </div>
                                @endif
                                </div>
                            </div>
                        </div>
                        @if ($endsRun)
                            <div class="mt-1 flex items-center gap-1.5 text-xs font-medium text-muted {{ $message->sender_id === auth()->id() ? 'justify-end px-1' : 'justify-start pl-11 pr-1' }}">
                                <time datetime="{{ $message->created_at->toIso8601String() }}" data-chat-local-time>{{ $message->created_at->format('g:i A') }}</time>
                                @if ($message->id === $lastOutgoing?->id)
                                    <span aria-hidden="true">·</span><span>{{ $receipt }}</span>
                                @endif
                            </div>
                        @endif
                        @if ($message->sender_id !== auth()->id())
                            <form x-cloak x-show="reporting" x-ref="reportForm" method="POST" action="{{ route('messages.report', [$conversation, $message]) }}" class="ml-11 mt-2 w-64 max-w-[calc(100%-2.75rem)] space-y-2 rounded-lg border border-line bg-white p-3 shadow-sm">@csrf<p class="text-sm font-semibold text-ink">Report message</p><select name="reason" required aria-label="Report reason" class="min-h-10 w-full rounded-lg border-line text-sm"><option value="Spam">Spam</option><option value="Abuse">Abusive content</option><option value="Other">Other</option></select><textarea name="details" maxlength="1000" rows="2" aria-label="Report details" class="block w-full rounded-lg border-line text-sm"></textarea><div class="flex gap-2"><button class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-3 text-sm font-semibold text-white transition hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Submit report</button><button type="button" x-on:click="reporting = false" class="inline-flex min-h-11 items-center justify-center rounded-lg px-3 text-sm font-semibold text-muted transition hover:bg-canvas hover:text-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Cancel</button></div></form>
                        @endif
                    </article>
                    @php($previousDate = $message->created_at)
                    @php($previousSenderId = $message->sender_id)
                @empty
                    <div x-show="pending.length === 0" class="flex min-h-64 flex-col items-center justify-center px-6 text-center"><div class="grid size-14 place-items-center rounded-full bg-blue-50 text-brand-hover" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H5l-1 2v-6.5a7.5 7.5 0 1 1 16-3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 11.5h8M8 14.5h5"/></svg></div><p class="mt-3 text-sm font-semibold text-ink">Start the conversation</p><p class="mt-1 text-sm text-muted">{{ $pendingContext ? 'Send a message about '.($pendingContext['type'] === 'product' ? 'this product.' : 'this order.') : 'Send a message about this store.' }}</p></div>
                @endforelse
                <div wire:ignore aria-live="polite">
                    <template x-for="item in pending" :key="item.id">
                        <article class="mb-1 flex justify-end" :data-pending-message-id="item.id">
                            <div class="mt-4 min-w-0 w-[85%] sm:w-[72%] lg:w-[36rem]">
                                <div class="chat-message-bubble ml-auto w-fit max-w-full rounded-lg rounded-br-[6px] border border-brand bg-brand text-sm leading-[1.6] text-white" :class="item.previewUrl || item.fileName ? 'overflow-hidden p-0' : 'px-4 py-3'">
                                    <template x-if="item.previewUrl"><img :src="item.previewUrl" alt="Pending photo" class="block max-h-80 max-w-full object-contain"></template>
                                    <template x-if="item.fileName && !item.previewUrl">
                                        <div class="w-64 max-w-full min-w-0 bg-white p-3 text-ink">
                                            <div class="flex min-w-0 items-center gap-3">
                                                <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-brand-hover" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3.75h6l5 5v11.5H7a2 2 0 0 1-2-2v-12a2 2 0 0 1 2-2Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13 4v5h5M8.5 14h7M8.5 17h5"/></svg></span>
                                                <span class="min-w-0 flex-1"><span x-text="item.fileName" class="block truncate text-sm font-semibold"></span><span class="mt-0.5 block text-xs text-muted">PDF document</span></span>
                                            </div>
                                        </div>
                                    </template>
                                    <p x-show="item.body" x-text="item.body" class="whitespace-pre-wrap break-words [overflow-wrap:anywhere]" :class="item.previewUrl || item.fileName ? 'px-4 pb-3 pt-2' : ''"></p>
                                </div>
                                <div class="mt-1 flex items-center justify-end gap-2 px-1 text-xs font-medium" :class="item.status === 'Failed' ? 'text-red-700' : 'text-muted'">
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
                        <button wire:click="jumpToLatest" x-on:click="followLatestMessage = true" wire:loading.attr="disabled" type="button" class="min-h-11 rounded-full bg-blue-50 px-4 text-sm font-semibold text-brand-hover hover:bg-blue-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Jump to latest</button>
                    </div>
                @endif
            </div>
            <div wire:cloak @if ($canSend) x-on:dragover.prevent x-on:drop.prevent="chooseFile($event.dataTransfer.files[0])" @endif class="shrink-0 border-t border-line bg-white px-3 pb-3 pt-3 shadow-[0_-8px_24px_rgb(15_23_42_/_0.04)] sm:px-5 sm:pb-4 sm:pt-4">
                @unless ($canSend)
                    <div class="mb-3 flex flex-wrap items-center gap-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900" role="status">
                        <p class="min-w-0 flex-1">{{ $canUnblock ? 'You blocked this contact, so messaging is paused for both of you.' : 'Messaging is unavailable for this conversation.' }}</p>
                        @if ($canUnblock)
                            <form method="POST" action="{{ route('messages.unblock', $conversation) }}" class="shrink-0">@csrf @method('DELETE')<button type="submit" class="inline-flex min-h-10 items-center rounded-md bg-amber-900 px-3 text-xs font-semibold text-white transition hover:bg-amber-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-900 sm:text-sm">Unblock</button></form>
                        @endif
                    </div>
                @endunless
                @if (! $isBuyerMessages && $canSend)
                    <div class="mb-3 flex max-w-full gap-2 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:overflow-visible" role="group" aria-label="Quick replies">@foreach (['Yes, this item is available.', 'Your order is being prepared.', 'We’ll update you once your order ships.', 'Could you provide more details?'] as $reply)<button x-on:click="useQuickReply(@js($reply))" type="button" class="inline-flex min-h-11 shrink-0 items-center rounded-md border border-blue-200 bg-blue-50 px-3 text-xs font-medium text-brand-hover hover:bg-blue-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand sm:min-w-0 sm:flex-1 sm:shrink sm:justify-center sm:text-center">{{ $reply }}</button>@endforeach</div>
                @endif
                @if ($canSend)
                    @if ($pendingContext)
                        <div class="mb-3 flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 p-2 text-sm text-ink">
                            <span class="min-w-0 flex-1 truncate">{{ $pendingContext['type'] === 'product' ? $pendingContext['title'].' · ₱'.$pendingContext['price'] : 'Order '.$pendingContext['number'].' · ₱'.$pendingContext['total'] }}</span>
                            <button type="button" wire:click="clearPendingContext" class="rounded px-2 py-1 text-brand-hover" aria-label="Remove context">Remove</button>
                        </div>
                    @endif
                    @if ($replyingTo)
                        @php($replyQuote = $replyingTo->quote())
                        <div data-replying-to class="mb-3 flex items-center gap-2 rounded-lg border-l-2 border-brand bg-blue-50 p-2 text-sm text-ink" role="status">
                            <span class="min-w-0 flex-1"><span class="block text-xs font-semibold text-brand-hover">Replying to {{ $replyQuote['sender_id'] === auth()->id() ? 'yourself' : ($isBuyerMessages ? $conversation->store->name : $conversation->buyer->name) }}</span><span class="block truncate">{{ collect([$replyQuote['context_label'], $replyQuote['body'] ?: null])->filter()->implode(' · ') }}</span></span>
                            <button type="button" wire:click="cancelReply" class="grid size-10 shrink-0 place-items-center rounded-lg text-muted hover:bg-white hover:text-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand" aria-label="Cancel reply"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
                        </div>
                    @endif
                    <div x-cloak x-show="selectedType" class="mb-3 flex max-w-full items-center gap-2 rounded-md border border-line bg-canvas px-2 py-1.5 text-sm text-ink" role="status">
                        <img x-cloak x-show="previewUrl" x-bind:src="previewUrl" alt="Selected photo preview" class="size-10 shrink-0 rounded-md object-cover">
                        <span x-show="selectedType === 'document'" class="grid size-10 shrink-0 place-items-center rounded-md bg-blue-50 text-xs font-semibold text-brand-hover" aria-hidden="true">PDF</span>
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
                            <button type="button" x-on:click="$refs.attachmentInput.click()" x-bind:disabled="uploading" aria-label="Add attachment" title="Attach a JPG, PNG, WebP or PDF (up to 5 MB)" class="grid size-11 place-items-center rounded-md border border-line bg-white text-ink transition hover:border-blue-300 hover:bg-blue-50 hover:text-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand disabled:cursor-not-allowed disabled:opacity-50"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 10.5 12 18.5a5 5 0 0 1-7-7L13 3.5a3.5 3.5 0 0 1 5 5l-8 8a2 2 0 0 1-3-3l7.5-7.5"/></svg></button>
                            <div wire:ignore><input id="chat-attachment" x-ref="attachmentInput" form="chat-attachment-form" name="attachment" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" aria-label="Image or PDF document" tabindex="-1" required class="sr-only" x-on:change="chooseFile($event.target.files[0])"></div>
                        </div>
                    @endif
                    <textarea wire:model="body" x-ref="messageBody" x-on:input="$wire.body = $el.value; fitComposer()" x-on:input.debounce.500ms="$wire.startTyping()" x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $refs.messageForm.requestSubmit(); }" id="message-body" rows="1" maxlength="2000" class="min-h-11 min-w-0 flex-1 resize-none overflow-y-auto rounded-md border-line bg-canvas px-3 py-2 text-sm leading-6 text-ink placeholder:text-muted focus:border-brand focus:bg-white focus:ring-brand" placeholder="Type your message..." aria-describedby="message-help" @disabled(! $canSend)></textarea>
                    <button type="submit" x-bind:aria-label="selectedType === 'photo' ? 'Send photo' : selectedType === 'document' ? 'Send document' : 'Send message'" x-bind:disabled="!online || uploading" class="grid size-11 shrink-0 place-items-center rounded-md bg-brand text-white transition hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-not-allowed disabled:opacity-50" @disabled(! $canSend)><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4 11 16-7-7 16-2-7-7-2ZM11 13l4-4"/></svg></button>
                </div>
                <div id="message-help" class="flex items-center justify-end text-xs text-muted"><span x-cloak x-show="Array.from($wire.body).length > 1800" class="mt-1 tabular-nums" x-text="Array.from($wire.body).length + '/2000'"></span></div>
                @error('body')<p class="mt-2 text-sm font-medium text-red-700" role="alert">{{ $message }}</p>@enderror
                @error('attachment')<p class="mt-2 text-sm font-medium text-red-700" role="alert">{{ $message }}</p>@enderror
                <div x-cloak x-show="uploading" class="mt-2 text-xs text-muted" role="status"><span>Uploading attachment</span><span class="ml-1 tabular-nums" x-text="uploadProgress + '%'">0%</span><div class="mt-1 h-1.5 overflow-hidden rounded-full bg-line" role="progressbar" aria-label="Attachment upload progress" aria-valuemin="0" aria-valuemax="100" x-bind:aria-valuenow="uploadProgress"><div class="h-full rounded-full bg-brand transition-[width]" x-bind:style="'width: ' + uploadProgress + '%'"></div></div></div>
                <p x-cloak x-show="uploadError" x-text="uploadError" class="mt-2 text-sm font-medium text-red-700" role="alert"></p>
                </form>
                @if ($canSend)
                    <form id="chat-attachment-form" x-ref="attachmentForm" wire:ignore method="POST" action="{{ route('messages.attachments.store', $conversation) }}" enctype="multipart/form-data" data-clerk-session-refresh="{{ route('clerk.session.refresh') }}" data-chat-upload class="hidden">@csrf<input type="hidden" name="client_message_id" value="{{ $clientMessageId }}"><input type="hidden" name="body" value=""><input type="hidden" name="product_id" value="{{ $pendingProductId }}"><input type="hidden" name="seller_order_id" value="{{ $pendingSellerOrderId }}"></form>
                @endif
            </div>
        </section>
