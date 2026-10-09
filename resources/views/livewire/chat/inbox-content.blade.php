<div class="{{ $isBuyerMessages ? 'min-w-0 py-6 sm:py-8' : 'page-shell min-h-[calc(100dvh-4.5rem)] py-6 sm:py-8' }}">
        <div class="{{ $isBuyerMessages ? 'mx-auto w-full max-w-5xl' : 'page-container max-w-5xl' }}">
            <header class="mb-5 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">Messages</h1>
                    <p class="mt-1 text-sm text-muted">{{ $isBuyerMessages ? 'Contact stores about products and orders.' : 'Respond to customers about your products and orders.' }}</p>
                </div>
            </header>

            <section class="overflow-hidden rounded-[14px] border border-line bg-surface" aria-label="Your conversations">
                <div class="border-b border-line p-3 sm:p-4">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <label class="flex min-h-11 min-w-0 flex-1 items-center gap-3 rounded-[10px] bg-canvas px-4 transition focus-within:bg-white focus-within:ring-2 focus-within:ring-brand/20" for="conversation-search">
                            <svg class="size-4 shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                            <span class="sr-only">Search conversations</span>
                            <input wire:model.live.debounce.300ms="search" id="conversation-search" type="search" placeholder="Search conversations" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-ink placeholder:text-muted focus:ring-0">
                        </label>
                        <div class="flex gap-1 overflow-x-auto pb-1 md:pb-0" role="group" aria-label="Conversation filters">
                            @foreach (['all' => 'All', 'unread' => 'Unread', 'product' => 'Products', 'order' => 'Orders'] as $value => $label)
                                <button wire:key="filter-{{ $value }}" wire:click="$set('filter', '{{ $value }}')" type="button" class="min-h-10 shrink-0 rounded-[10px] px-4 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand {{ $filter === $value ? 'bg-brand-hover text-white' : 'text-muted hover:bg-canvas hover:text-ink' }}" aria-pressed="{{ $filter === $value ? 'true' : 'false' }}">{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="divide-y divide-line">
                    @forelse ($conversations as $conversation)
                        @php($latest = $conversation->messages->first())
                        @php($lastReadAt = $conversation->readStates->first()?->last_read_at)
                        @php($isUnread = $latest && $latest->sender_id !== auth()->id() && ($lastReadAt === null || $latest->created_at->greaterThan($lastReadAt)))
                        @php($isBuyer = auth()->user()->account_type === 'buyer')
                        @php($name = $isBuyer ? $conversation->store->name : $conversation->buyer->name)
                        @php($context = $conversation->product ? 'Product · '.$conversation->product->name : ($conversation->sellerOrder ? 'Order · #'.$conversation->sellerOrder->order->number : 'Marketplace chat'))
                        <a wire:key="conversation-{{ $conversation->id }}" data-presence-conversation="{{ $conversation->id }}" href="{{ route('messages.show', $conversation) }}" class="group flex min-w-0 items-center gap-3 px-4 py-4 transition hover:bg-blue-50/50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-brand sm:gap-4 sm:px-5">
                            <div class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-full bg-blue-50 text-base font-semibold text-brand-hover">
                                @if ($isBuyer && $conversation->store->logo_path)
                                    <img src="{{ Storage::disk('public')->url($conversation->store->logo_path) }}" alt="" class="size-full object-cover">
                                @else
                                    {{ Str::upper(Str::substr($name, 0, 1)) }}
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex min-w-0 items-center gap-3">
                                    <p class="truncate text-sm {{ $isUnread ? 'font-bold text-ink' : 'font-semibold text-ink' }}">{{ $name }}</p>
                                    <time @if ($conversation->latest_message_at) datetime="{{ $conversation->latest_message_at->toIso8601String() }}" @endif class="ml-auto shrink-0 text-xs {{ $isUnread ? 'font-semibold text-brand-hover' : 'text-muted' }}">{{ $conversation->latest_message_at?->diffForHumans() }}</time>
                                </div>
                                <div class="mt-1 flex min-w-0 items-center gap-2">
                                    <p class="truncate text-sm {{ $isUnread ? 'font-medium text-ink' : 'text-muted' }}">{{ $latest?->body ?? 'No messages yet' }}</p>
                                    @if ($isUnread)<span class="size-2.5 shrink-0 rounded-full bg-brand" aria-label="Unread message"></span>@endif
                                </div>
                                <p class="mt-1 truncate text-xs text-muted">{{ $context }}</p>
                            </div>
                            <svg class="size-4 shrink-0 text-muted transition group-hover:translate-x-0.5 group-hover:text-brand-hover" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
                        </a>
                    @empty
                        <div class="px-6 py-16 text-center">
                            <div class="mx-auto grid size-14 place-items-center rounded-full bg-blue-50 text-brand-hover" aria-hidden="true">
                                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H5l-1 2v-6.5a7.5 7.5 0 1 1 16-3Z"/><path stroke-linecap="round" d="M8 11.5h8M8 14.5h5"/></svg>
                            </div>
                            <h2 class="mt-4 text-base font-semibold text-ink">{{ auth()->user()->account_type === 'seller' ? 'No customer messages yet' : 'No messages yet' }}</h2>
                            <p class="mx-auto mt-1 max-w-sm text-sm leading-6 text-muted">{{ auth()->user()->account_type === 'seller' ? 'Product and order questions from customers will show up here.' : 'Start a conversation from a product or order when you need help.' }}</p>
                        </div>
                    @endforelse
                </div>
                @if ($conversations->hasPages())<div class="border-t border-line px-4 py-3 sm:px-5">{{ $conversations->links() }}</div>@endif
            </section>
        </div>
    </div>
