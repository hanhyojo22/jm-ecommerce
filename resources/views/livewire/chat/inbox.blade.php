<div>
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 sm:py-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><p class="text-sm font-semibold uppercase tracking-[.16em] text-emerald-700">{{ auth()->user()->account_type === 'seller' ? 'Seller Center' : 'Your account' }}</p><h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Messages</h1></div>
            <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-900 ring-1 ring-inset ring-emerald-200"><span class="size-1.5 rounded-full bg-emerald-600"></span>Private conversations</span>
        </div>
        </div>
    </header>

    <div class="page-shell"><div class="page-container max-w-6xl">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 p-4 sm:p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <label class="flex min-h-11 min-w-0 flex-1 items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 focus-within:border-emerald-600 focus-within:bg-white focus-within:ring-2 focus-within:ring-emerald-700/15" for="conversation-search"><svg class="size-4 shrink-0 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><span class="sr-only">Search conversations</span><input wire:model.live.debounce.300ms="search" id="conversation-search" type="search" placeholder="Search messages" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-slate-900 placeholder:text-slate-500 focus:ring-0"></label>
                    <div class="flex gap-2 overflow-x-auto pb-1 lg:pb-0" aria-label="Conversation filters">
                        @foreach (['all' => 'All', 'unread' => 'Unread', 'product' => 'Products', 'order' => 'Orders'] as $value => $label)
                            <button wire:key="filter-{{ $value }}" wire:click="$set('filter', '{{ $value }}')" type="button" class="min-h-10 shrink-0 rounded-lg px-3 text-sm font-semibold transition {{ $filter === $value ? 'bg-slate-950 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-600 hover:border-emerald-300 hover:text-emerald-900' }}" aria-pressed="{{ $filter === $value ? 'true' : 'false' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse ($conversations as $conversation)
                    @php($latest = $conversation->messages->first())
                    @php($lastReadAt = $conversation->readStates->first()?->last_read_at)
                    @php($isUnread = $latest && $latest->sender_id !== auth()->id() && ($lastReadAt === null || $latest->created_at->greaterThan($lastReadAt)))
                    @php($isBuyer = auth()->user()->account_type === 'buyer')
                    <a wire:key="conversation-{{ $conversation->id }}" href="{{ route('messages.show', $conversation) }}" class="group flex min-w-0 gap-3 px-4 py-4 transition hover:bg-emerald-50/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-700 sm:px-5">
                        <div class="grid size-11 shrink-0 place-items-center overflow-hidden rounded-2xl bg-emerald-900 text-sm font-bold text-white shadow-sm">@if ($isBuyer && $conversation->store->logo_path)<img src="{{ Storage::disk('public')->url($conversation->store->logo_path) }}" alt="" class="size-full object-cover">@else{{ Str::upper(Str::substr($isBuyer ? $conversation->store->name : $conversation->buyer->name, 0, 1)) }}@endif</div>
                        <div class="min-w-0 flex-1">
                            <div class="flex min-w-0 items-center gap-3"><p class="truncate text-sm font-semibold {{ $isUnread ? 'text-slate-950' : 'text-slate-800' }}">{{ $isBuyer ? $conversation->store->name : $conversation->buyer->name }}</p><time class="ml-auto shrink-0 text-xs {{ $isUnread ? 'font-semibold text-emerald-800' : 'text-slate-500' }}">{{ $conversation->latest_message_at?->diffForHumans() }}</time></div>
                            <div class="mt-1 flex min-w-0 items-center gap-2"><span class="inline-flex shrink-0 items-center rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">{{ $conversation->product ? 'Product' : 'Order' }}</span><p class="truncate text-xs font-medium text-slate-500">{{ $conversation->product?->name ?? '#'.$conversation->sellerOrder?->order?->number }}</p></div>
                            <div class="mt-1 flex min-w-0 items-center gap-2"><p class="truncate min-w-0 text-sm {{ $isUnread ? 'font-medium text-slate-800' : 'text-slate-500' }}">{{ $latest?->body ?? 'No messages yet' }}</p>@if ($isUnread)<span class="size-2 shrink-0 rounded-full bg-emerald-600" aria-label="Unread"></span>@endif</div>
                        </div>
                    </a>
                @empty
                    <div class="empty-state"><div class="mx-auto grid size-12 place-items-center rounded-2xl bg-emerald-50 text-emerald-800"><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M20 15a4 4 0 0 1-4 4H8l-4 3V7a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4z"/></svg></div><h2 class="mt-4 text-lg font-semibold text-slate-950">{{ auth()->user()->account_type === 'seller' ? 'No customer messages yet' : 'No messages yet' }}</h2><p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-slate-500">{{ auth()->user()->account_type === 'seller' ? 'New product and order inquiries will appear here.' : 'Questions about a product or order will appear here.' }}</p></div>
                @endforelse
            </div>
            @if ($conversations->hasPages())<div class="border-t border-slate-100 px-4 py-3 sm:px-5">{{ $conversations->links() }}</div>@endif
        </div>
    </div></div>
</div>
