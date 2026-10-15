<article class="min-w-0 border-b border-line pb-5 last:border-0">
    @if ($sellerView)
        <p class="mb-3 text-sm font-semibold text-brand">{{ $review->product->name }}</p>
    @endif
    <div class="flex min-w-0 gap-3">
        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-slate-100 text-sm font-semibold text-slate-600" aria-hidden="true">{{ Str::upper(Str::substr($review->user->name, 0, 1)) }}</span>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-sm">
                <span class="font-semibold text-ink">{{ $review->user->name }}</span>
                <time class="text-xs text-muted" datetime="{{ $review->created_at->toIso8601String() }}" title="{{ $review->created_at->format('M j, Y g:i A') }}">{{ $review->created_at->diffForHumans(short: true) }}</time>
                <span class="text-xs font-medium text-success">Verified purchase</span>
            </div>
            <div class="mt-1 flex gap-0.5 text-amber-500" role="img" aria-label="{{ $review->rating }} out of 5 stars">@foreach (range(1, 5) as $star)<span class="text-sm {{ $star <= $review->rating ? '' : 'text-slate-200' }}">&#9733;</span>@endforeach</div>
            @if ($review->title)<h3 class="mt-1 text-sm font-semibold text-ink">{{ $review->title }}</h3>@endif
            @if ($review->body)<p class="mt-1 whitespace-pre-line break-words text-sm leading-6 text-ink">{{ $review->body }}</p>@endif
            @if ($review->photos->isNotEmpty())
                <div x-data="{ open: false, source: '' }" class="mt-3 flex flex-wrap gap-2">
                    @foreach ($review->photos as $photo)
                        <button type="button" @click="source = '{{ Storage::disk('public')->url($photo->path) }}'; open = true" class="size-16 overflow-hidden rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand"><img src="{{ Storage::disk('public')->url($photo->path) }}" alt="Review photo {{ $loop->iteration }}" loading="lazy" class="h-full w-full object-cover"></button>
                    @endforeach
                    <div x-show="open" x-cloak @keydown.escape.window="open = false" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/80 p-4" role="dialog" aria-modal="true" aria-label="Expanded review photo"><button type="button" @click="open = false" class="absolute right-5 top-5 min-h-11 min-w-11 rounded-lg bg-white px-3 text-sm font-semibold">Close</button><img :src="source" alt="Expanded review photo" class="max-h-full max-w-full rounded-xl"></div>
                </div>
            @endif
            @if (! $sellerView)
                @auth
                    @if (auth()->id() === $review->user_id)
                        <a href="{{ route('orders.show', $review->orderItem->sellerOrder->order_id) }}" class="mt-2 inline-flex min-h-9 items-center text-xs font-semibold text-brand hover:text-brand-hover">Manage your review</a>
                    @endif
                @endauth
            @endif
        </div>
    </div>

    @if (count($thread))
        <button type="button" wire:click="$toggle('repliesExpanded')" class="ml-12 mt-2 min-h-9 text-xs font-semibold text-brand hover:text-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">{{ $repliesExpanded ? 'Hide replies' : 'View replies ('.count($thread).')' }}</button>
    @endif
    @if ($repliesExpanded)
        <div class="mt-1 space-y-3">
            @foreach ($thread as $entry)
                @php($response = $entry['response'])
                @php($official = $response->author_type === 'seller')
                <div wire:key="reply-{{ $response->id }}" class="min-w-0 border-l-2 border-line pl-3 sm:pl-4 {{ $entry['depth'] === 1 ? 'ml-5 sm:ml-6' : 'ml-9 sm:ml-12' }}">
                    <div class="flex min-w-0 gap-2.5">
                        @if ($official && $store->logo_path)
                            <img src="{{ Storage::disk('public')->url($store->logo_path) }}" alt="" class="size-8 shrink-0 rounded-full object-cover">
                        @else
                            <span class="grid size-8 shrink-0 place-items-center rounded-full {{ $official ? 'bg-blue-50 text-brand' : 'bg-slate-100 text-slate-600' }} text-xs font-bold" aria-hidden="true">{{ Str::upper(Str::substr($official ? $store->name : $response->user->name, 0, 1)) }}</span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-sm"><span class="font-semibold text-ink">{{ $official ? $store->name : $response->user->name }}</span>@if ($official)<span class="rounded-full bg-blue-50 px-1.5 py-0.5 text-[10px] font-semibold text-brand">Seller</span>@endif<time class="text-xs text-muted" datetime="{{ $response->created_at->toIso8601String() }}" title="{{ $response->created_at->format('M j, Y g:i A') }}">{{ $response->created_at->diffForHumans(short: true) }}</time></div>
                            <p class="mt-0.5 whitespace-pre-line break-words text-sm leading-6 text-ink">{{ $response->body }}</p>
                            @auth
                                @if ($review->status === 'published' && ! $official && auth()->user()->account_type === 'seller' && auth()->user()->sellerTenant()?->id === $store->tenant_id)
                                    <button type="button" wire:click="replyTo('{{ $response->id }}')" class="mt-0.5 min-h-9 text-xs font-semibold text-muted hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Reply</button>
                                @endif
                            @endauth
                            @if ($composerOpen && $replyToId === $response->id)
                                @include('livewire.reviews.reply-composer')
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
            @if ($canCustomerReply)
                @foreach ($thread as $entry)
                    @if ($entry['response']->author_type === 'seller')
                        @php($response = $entry['response'])
                        <form wire:key="customer-reply-form-{{ $response->id }}" wire:submit="submitCustomerReply('{{ $response->id }}')" class="ml-9 flex min-w-0 items-start gap-2 border-l-2 border-line pl-3 sm:ml-12 sm:pl-4" aria-label="Reply to {{ $store->name }}">
                            <span class="grid size-7 shrink-0 place-items-center rounded-full bg-slate-100 text-[11px] font-bold text-muted" aria-hidden="true">{{ Str::upper(Str::substr(auth()->user()->name, 0, 1)) }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="flex min-w-0 items-center gap-2">
                                    <label for="customer-reply-{{ $response->id }}" class="sr-only">Write a reply</label>
                                    <input id="customer-reply-{{ $response->id }}" type="text" wire:model="replyBodies.{{ $response->id }}" maxlength="2000" required placeholder="Write a reply..." class="field-control min-w-0 flex-1 text-sm">
                                    <button type="submit" wire:loading.attr="disabled" wire:target="submitCustomerReply('{{ $response->id }}')" class="min-h-10 shrink-0 rounded-lg bg-brand px-3 text-sm font-semibold text-white hover:bg-brand-hover disabled:opacity-60">Send</button>
                                </div>
                                @error('replyBodies.'.$response->id)<p class="mt-1 text-xs text-danger" role="alert">{{ $message }}</p>@enderror
                                <span wire:loading wire:target="submitCustomerReply('{{ $response->id }}')" class="mt-1 text-xs text-muted" role="status">Sending...</span>
                                @if ($savedReplyId === $response->id)<p class="mt-1 text-xs font-medium text-success" role="status">Reply sent.</p>@endif
                            </div>
                        </form>
                    @endif
                @endforeach
            @endif
        </div>
    @endif

    @if ($composerOpen && $replyToId === null)
        <div class="ml-5 border-l-2 border-line pl-3 sm:ml-6 sm:pl-4">@include('livewire.reviews.reply-composer')</div>
    @endif
    @if ($saved)<p class="ml-12 mt-2 text-xs font-medium text-success" role="status">Your response was posted.</p>@endif
</article>
