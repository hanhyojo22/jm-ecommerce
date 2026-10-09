    <div class="{{ $isBuyerMessages ? 'min-w-0 py-0 sm:py-4' : 'page-shell py-0 sm:py-4' }}">
        <div class="mx-auto {{ $isBuyerMessages ? 'h-[min(78vh,58rem)]' : 'h-[calc(100dvh-5rem)] sm:h-[min(78vh,58rem)]' }} min-h-[28rem] w-full max-w-6xl sm:min-h-[34rem] sm:px-6 lg:px-8">
            <section class="flex h-full min-h-0 flex-col overflow-hidden bg-surface sm:rounded-[14px] sm:border sm:border-line">
                <header class="flex min-w-0 shrink-0 items-center justify-between gap-2 border-b border-line px-3 py-3 sm:gap-4 sm:px-5">
                <div class="flex min-w-0 items-center gap-2.5 sm:gap-3">
                    <a href="{{ route('messages.index') }}" class="grid size-11 shrink-0 place-items-center rounded-full text-muted transition hover:bg-canvas hover:text-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand" aria-label="Back to messages"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6M9 12h11"/></svg></a>
                    <div class="flex min-w-0 items-center gap-3">
                        @if (auth()->user()->account_type === 'buyer' && $conversation->store->logo_path)
                            <img src="{{ Storage::disk('public')->url($conversation->store->logo_path) }}" alt="" class="size-11 shrink-0 rounded-full object-cover">
                        @else
                            <span class="inline-flex size-11 shrink-0 items-center justify-center rounded-full bg-blue-50 text-sm font-bold text-brand-hover" aria-hidden="true">{{ Str::upper(Str::substr(auth()->user()->account_type === 'buyer' ? $conversation->store->name : $conversation->buyer->name, 0, 1)) }}</span>
                        @endif
                        <div class="min-w-0"><h1 class="truncate text-sm font-semibold text-ink sm:text-base">{{ auth()->user()->account_type === 'buyer' ? $conversation->store->name : $conversation->buyer->name }}</h1><p id="chat-presence" x-data="{ online: false }" x-on:chat-presence-status.window="if ($event.detail.conversationId === '{{ $conversation->id }}') online = $event.detail.online" class="flex items-center gap-1.5 truncate text-xs"><span data-presence-dot class="size-1.5 shrink-0 rounded-full" x-bind:class="{ 'bg-blue-500': online, 'bg-slate-400': ! online }"></span><span data-presence-label data-context="{{ $conversation->product ? 'Product inquiry' : 'Order conversation' }}" x-text="(online ? 'Online' : 'Offline') + ' · ' + $el.dataset.context">Offline · {{ $conversation->product ? 'Product inquiry' : 'Order conversation' }}</span></p><p id="chat-typing" class="hidden truncate text-xs font-medium text-brand-hover" aria-live="polite">Typing…</p></div>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    @if (auth()->user()->account_type === 'buyer')
                        <a href="{{ route('catalog.store', $conversation->store->slug) }}" aria-label="Open store" class="inline-flex min-h-10 items-center gap-2 rounded-[10px] px-3 text-xs font-semibold text-brand-hover transition hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand sm:text-sm"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.25h16.5l-1.5 10h-13.5l-1.5-10ZM5 9.25l2-5h10l2 5M9 19.25v-6h6v6"/></svg><span class="hidden sm:inline">Store</span></a>
                    @endif
                    <form method="POST" action="{{ route('messages.block', $conversation) }}">@csrf<button type="submit" class="inline-flex min-h-10 items-center rounded-[10px] px-3 text-xs font-medium text-muted transition hover:bg-rose-50 hover:text-rose-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-rose-700 sm:text-sm" onclick="return confirm('Block this conversation?')">Block</button></form>
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
                    @if (auth()->user()->account_type === 'buyer')<a href="{{ route('orders.show', $conversation->sellerOrder->order) }}" class="shrink-0 rounded-[10px] px-3 py-2 text-xs font-semibold text-brand-hover transition hover:bg-amber-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand sm:text-sm">View order</a>@else<a href="{{ route('seller.orders.show', $conversation->sellerOrder) }}" class="shrink-0 rounded-[10px] px-3 py-2 text-xs font-semibold text-brand-hover transition hover:bg-amber-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand sm:text-sm">View order</a>@endif
                </div>
            @endif

            <div wire:offline class="mx-3 mt-2 shrink-0 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 sm:mx-5" role="status">You’re offline. Messages will send when your connection returns.</div>
            <div id="conversation-messages" class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-5 sm:px-6" role="log" aria-live="polite" aria-label="Conversation messages" tabindex="0">
                @if ($hasMoreMessages)<div class="mb-5 text-center"><button wire:click="loadOlderMessages" wire:loading.attr="disabled" type="button" class="inline-flex min-h-11 items-center rounded-[10px] bg-canvas px-4 text-sm font-semibold text-ink transition hover:bg-line focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand disabled:opacity-60">Load earlier messages</button></div>@endif
                @php($previousDate = null)
                @php($previousSenderId = null)
                @forelse ($messages as $message)
                    <div wire:key="message-date-{{ $message->id }}" data-chat-date-separator data-chat-date="{{ $message->created_at->toIso8601String() }}" class="{{ $previousDate?->toDateString() === $message->created_at->toDateString() ? 'hidden ' : '' }}my-5 flex items-center gap-3 text-xs font-medium text-muted"><span class="h-px flex-1 bg-slate-100"></span><span data-chat-date-label>{{ $message->created_at->isToday() ? 'Today' : ($message->created_at->isYesterday() ? 'Yesterday' : $message->created_at->format('F j, Y')) }}</span><span class="h-px flex-1 bg-slate-100"></span></div>
                    <article wire:key="message-{{ $message->id }}" class="mb-1 flex items-end gap-2 {{ $message->sender_id === auth()->id() ? 'justify-end' : 'justify-start' }}">
                        @if ($message->sender_id !== auth()->id())
                            <span class="grid size-7 shrink-0 place-items-center rounded-full bg-blue-50 text-[11px] font-bold text-brand-hover {{ $message->sender_id === $previousSenderId ? 'invisible' : '' }}" aria-hidden="true">{{ Str::upper(Str::substr($message->sender->name, 0, 1)) }}</span>
                        @endif
                        <div class="min-w-0 max-w-[82%] sm:max-w-[70%] {{ $message->sender_id === $previousSenderId ? '' : 'mt-3' }}">
                            <div class="rounded-[20px] px-4 py-2.5 text-sm leading-6 {{ $message->sender_id === auth()->id() ? 'rounded-br-md bg-brand text-white' : 'rounded-bl-md bg-canvas text-ink' }}">
                                @if ($message->type === 'file' && $message->attachments->isNotEmpty())
                                    @php($attachment = $message->attachments->first())
                                    @if (in_array($attachment->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true))
                                        <a href="{{ route('messages.attachments.show', $attachment) }}" target="_blank" rel="noopener" class="block">
                                            <img src="{{ route('messages.attachments.show', $attachment) }}" alt="{{ $attachment->original_name }}" loading="lazy" decoding="async" class="max-h-80 max-w-full rounded-lg object-contain">
                                            <span class="mt-1 block text-xs underline underline-offset-2">{{ $attachment->original_name }}</span>
                                        </a>
                                    @else
                                        <a href="{{ route('messages.attachments.show', $attachment) }}" class="flex w-56 max-w-full min-w-0 items-center gap-3 rounded-[14px] border border-line bg-white/80 p-3 text-left transition hover:bg-white">
                                            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg {{ $message->sender_id === auth()->id() ? 'bg-brand text-white' : 'bg-slate-100 text-muted' }}" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3.75h6l5 5v11.5H7a2 2 0 0 1-2-2v-12a2 2 0 0 1 2-2Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13 4v5h5M8.5 14h7M8.5 17h5"/></svg>
                                            </span>
                                            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold">{{ $attachment->original_name }}</span><span class="mt-0.5 block text-xs opacity-75">PDF document · Download</span></span>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4 shrink-0 opacity-75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75v11m0 0 4-4m-4 4-4-4M5 16.75v3.5h14v-3.5"/></svg>
                                        </a>
                                    @endif
                                    @if ($message->body !== $attachment->original_name)
                                        <p class="mt-2 whitespace-pre-wrap break-words [overflow-wrap:anywhere]">{{ $message->body }}</p>
                                    @endif
                                @else
                                    <p class="whitespace-pre-wrap break-words [overflow-wrap:anywhere]">{{ $message->body }}</p>
                                @endif
                                <time datetime="{{ $message->created_at->toIso8601String() }}" data-chat-local-time class="mt-1 block text-[11px] {{ $message->sender_id === auth()->id() ? 'text-blue-50' : 'text-muted' }}">{{ $message->created_at->format('g:i A') }}</time>
                                @if ($message->id === $lastMessage?->id && $message->sender_id === auth()->id())
                                    <span class="mt-0.5 block text-right text-[11px] text-blue-50">{{ $receipt }}</span>
                                @endif
                            </div>
                            @if ($message->sender_id !== auth()->id())<details class="mt-1 px-1 text-xs"><summary class="cursor-pointer text-muted">Report message</summary><form method="POST" action="{{ route('messages.report', [$conversation, $message]) }}" class="mt-2 space-y-2">@csrf<select name="reason" required class="rounded-lg border-line text-xs"><option value="Spam">Spam</option><option value="Abuse">Abusive content</option><option value="Other">Other</option></select><textarea name="details" maxlength="1000" rows="2" aria-label="Report details" class="block w-full rounded-lg border-line text-xs"></textarea><button class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-3 font-semibold text-white transition hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Submit report</button></form></details>@endif
                        </div>
                    </article>
                    @php($previousDate = $message->created_at)
                    @php($previousSenderId = $message->sender_id)
                @empty
                    <div class="flex min-h-64 flex-col items-center justify-center px-6 text-center"><div class="grid size-14 place-items-center rounded-full bg-blue-50 text-brand-hover" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H5l-1 2v-6.5a7.5 7.5 0 1 1 16-3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 11.5h8M8 14.5h5"/></svg></div><p class="mt-3 text-sm font-semibold text-ink">Start the conversation</p><p class="mt-1 text-sm text-muted">Send a message about this {{ $conversation->product ? 'product' : 'order' }}.</p></div>
                @endforelse
            </div>
            <div class="shrink-0 border-t border-line bg-surface p-3 sm:p-4" x-data="{
                selectedType: null,
                fileName: '',
                previewUrl: null,
                selectFile(type, event) {
                    const file = event.target.files[0];
                    if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
                    this.$refs[type === 'photo' ? 'documentInput' : 'photoInput'].value = '';
                    this.selectedType = file ? type : null;
                    this.fileName = file?.name ?? '';
                    this.previewUrl = file && type === 'photo' ? URL.createObjectURL(file) : null;
                },
                clearFile() {
                    if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
                    this.$refs.photoInput.value = '';
                    this.$refs.documentInput.value = '';
                    this.selectedType = null;
                    this.fileName = '';
                    this.previewUrl = null;
                }
            }" x-init="const key = 'chat-draft-{{ auth()->id() }}-{{ $conversation->id }}'; const draft = sessionStorage.getItem(key); if (draft !== null && ! @js(session('status') === 'Attachment sent.')) { $wire.body = draft; } sessionStorage.removeItem(key);">
                @cannot('send', $conversation)<p class="mb-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900" role="status">Messaging is unavailable for this conversation.</p>@endcannot
                @if (auth()->user()->account_type !== 'buyer' && auth()->user()->can('send', $conversation))
                    <div class="mb-3 flex gap-2 overflow-x-auto pb-1" role="group" aria-label="Quick replies">@foreach (['Yes, this item is available.', 'Your order is being prepared.', 'We’ll update you once your order ships.', 'Could you provide more details?'] as $reply)<button x-on:click="$wire.body = @js($reply)" type="button" class="inline-flex min-h-11 shrink-0 items-center rounded-[10px] border border-blue-200 bg-blue-50 px-3 text-xs font-medium text-brand-hover hover:bg-blue-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">{{ $reply }}</button>@endforeach</div>
                @endif
                @can('send', $conversation)
                    <div x-cloak x-show="selectedType" class="mb-3 flex max-w-full items-center gap-2 rounded-[10px] border border-line bg-canvas px-2 py-1.5 text-sm text-ink" role="status">
                        <img x-cloak x-show="previewUrl" x-bind:src="previewUrl" alt="Selected photo preview" class="size-10 shrink-0 rounded-md object-cover">
                        <span x-show="selectedType === 'document'" class="grid size-10 shrink-0 place-items-center rounded-md bg-blue-50 text-xs font-bold text-brand-hover" aria-hidden="true">PDF</span>
                        <span x-text="fileName" class="min-w-0 flex-1 truncate font-medium"></span>
                        <button type="button" x-on:click="clearFile()" class="grid size-10 shrink-0 place-items-center rounded-lg text-muted hover:bg-line hover:text-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand" aria-label="Remove attachment"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
                    </div>
                @endcan
                <label for="message-body" class="sr-only">Write a message</label>
                <form wire:submit="send" x-ref="messageForm" x-on:submit.capture="if (selectedType) { $event.preventDefault(); $event.stopImmediatePropagation(); sessionStorage.setItem('chat-draft-{{ auth()->id() }}-{{ $conversation->id }}', $wire.body); $refs[selectedType === 'photo' ? 'photoForm' : 'documentForm'].requestSubmit(); }">
                <div class="flex min-w-0 items-end gap-2">
                    @can('send', $conversation)
                        <label for="chat-photo" class="inline-flex min-h-12 shrink-0 cursor-pointer items-center gap-2 rounded-[10px] border border-line bg-white px-3 text-sm font-semibold text-ink transition hover:border-blue-300 hover:bg-blue-50 hover:text-brand-hover focus-within:ring-2 focus-within:ring-brand" title="Add photo">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5" aria-hidden="true"><rect x="3.5" y="4.5" width="15" height="15" rx="2.5"/><circle cx="8.75" cy="9.5" r="1.3"/><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 17 4.5-4 3 2.5 2-1.5M19.5 14v7m-3.5-3.5h7"/></svg>
                            <span class="hidden sm:inline">Photo</span>
                            <input id="chat-photo" x-ref="photoInput" form="chat-photo-form" name="attachment" type="file" accept="image/jpeg,image/png,image/webp" required class="sr-only" x-on:change="selectFile('photo', $event)">
                        </label>
                        <label for="chat-document" class="inline-flex min-h-12 shrink-0 cursor-pointer items-center gap-2 rounded-[10px] border border-line bg-white px-3 text-sm font-semibold text-ink transition hover:border-blue-300 hover:bg-blue-50 hover:text-brand-hover focus-within:ring-2 focus-within:ring-brand" title="Add document">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 10.5 12 18.5a5 5 0 0 1-7-7L13 3.5a3.5 3.5 0 0 1 5 5l-8 8a2 2 0 0 1-3-3l7.5-7.5"/></svg>
                            <span class="hidden sm:inline">Document</span>
                            <input id="chat-document" x-ref="documentInput" form="chat-document-form" name="attachment" type="file" accept="application/pdf" required class="sr-only" x-on:change="selectFile('document', $event)">
                        </label>
                    @endcan
                    <textarea wire:model="body" wire:input.debounce.500ms="startTyping" x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $refs.messageForm.requestSubmit(); }" id="message-body" rows="2" maxlength="2000" class="min-h-12 min-w-0 flex-1 resize-y rounded-[10px] border-transparent bg-canvas text-sm text-ink placeholder:text-muted focus:border-brand focus:bg-white focus:ring-brand" placeholder="Write a message…" aria-describedby="message-help" @disabled(! auth()->user()->can('send', $conversation))></textarea>
                    <button type="submit" x-bind:aria-label="selectedType === 'photo' ? 'Send photo' : selectedType === 'document' ? 'Send document' : 'Send message'" class="grid size-12 shrink-0 place-items-center rounded-full bg-brand text-white transition hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-not-allowed disabled:opacity-50" wire:loading.attr="disabled" wire:target="send" @disabled(! auth()->user()->can('send', $conversation))><svg wire:loading.remove wire:target="send" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4 11 16-7-7 16-2-7-7-2ZM11 13l4-4"/></svg><span wire:loading wire:target="send" class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span></button>
                </div>
                <div id="message-help" class="flex items-center justify-between gap-3 text-xs text-muted"><span class="hidden sm:inline">Enter to send · Shift + Enter for a new line</span><span class="ml-auto tabular-nums" x-text="Array.from($wire.body).length + '/2000'">{{ Str::length($body) }}/2000</span></div>
                @error('body')<p class="mt-2 text-sm font-medium text-red-700" role="alert">{{ $message }}</p>@enderror
                </form>
                @can('send', $conversation)
                    <p class="mt-1 text-xs text-muted">Photos: JPG, PNG, WebP · Documents: PDF · Up to 5 MB</p>
                    <form id="chat-photo-form" x-ref="photoForm" method="POST" action="{{ route('messages.attachments.store', $conversation) }}" enctype="multipart/form-data" data-clerk-session-refresh="{{ route('clerk.session.refresh') }}" class="hidden">@csrf<input type="hidden" name="client_message_id" value="{{ $clientMessageId }}"><input type="hidden" name="body" x-bind:value="$wire.body"></form>
                    <form id="chat-document-form" x-ref="documentForm" method="POST" action="{{ route('messages.attachments.store', $conversation) }}" enctype="multipart/form-data" data-clerk-session-refresh="{{ route('clerk.session.refresh') }}" class="hidden">@csrf<input type="hidden" name="client_message_id" value="{{ $clientMessageId }}"><input type="hidden" name="body" x-bind:value="$wire.body"></form>
                @endcan
            </div>
        </section>
        </div>
    </div>
