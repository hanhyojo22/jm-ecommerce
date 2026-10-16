@php($canDiscuss = auth()->check() && in_array(auth()->user()->account_type, ['buyer', 'seller'], true) && auth()->user()->hasVerifiedClerkEmail())
@php($actingAsStore = $canDiscuss && auth()->user()->account_type === 'seller' && auth()->user()->isMemberOfTenant($product->tenant_id))
<section id="discussions" aria-label="Product discussions">
    @foreach ($discussions as $rootDiscussion)
        @foreach (collect([$rootDiscussion])->concat($rootDiscussion->replies) as $discussion)
            @php($official = $discussion->author_type === 'seller')
            @php($author = $official ? $product->store->name : $discussion->user->name)
            <article id="discussion-{{ $discussion->id }}" class="pd-discussion {{ $discussion->parent_id ? 'ml-5' : '' }}">
                <span @class(['pd-avatar', 'seller' => $official])>@if ($official && $product->store->logo_path)<img src="{{ Storage::disk('public')->url($product->store->logo_path) }}" alt="" class="size-full object-cover">@else{{ Str::upper(Str::substr($author, 0, 2)) }}@endif</span>
                <div class="min-w-0 flex-1">
                    <div class="pd-discussion-bubble"><strong>{{ $author }}</strong>@if ($official)<span class="ml-1 rounded bg-blue-50 px-1 text-[9px] font-semibold text-brand">Seller</span>@endif<p>{{ $discussion->body }}</p></div>
                    <div class="pd-discussion-actions"><time datetime="{{ $discussion->created_at->toIso8601String() }}">{{ $discussion->created_at->diffForHumans() }}</time>@if ($discussion->parent)<span>Reply to {{ $discussion->parent->author_type === 'seller' ? $product->store->name : $discussion->parent->user?->name }}</span>@endif
                    @if ($canDiscuss)
                        <details class="pd-discussion-reply"><summary class="inline-flex min-h-10 cursor-pointer items-center font-semibold text-brand">Reply</summary>
                            <form method="POST" action="{{ route('discussions.store', $product) }}" class="pd-comment-form">
                                @csrf<input type="hidden" name="parent_id" value="{{ $discussion->id }}"><label for="reply-{{ $discussion->id }}" class="sr-only">Your reply</label><textarea id="reply-{{ $discussion->id }}" name="body" rows="2" maxlength="2000" required placeholder="Write a reply..."></textarea><button type="submit" class="pd-reply-submit">Post reply</button>
                            </form>
                        </details>
                    @endif
                    </div>
                </div>
            </article>
        @endforeach
    @endforeach
    {{ $discussions->fragment('discussions')->links() }}
    @if ($canDiscuss)
        <form method="POST" action="{{ route('discussions.store', $product) }}" class="mt-7">
            @csrf
            <label for="discussion-body" class="pd-small">Posting as <b class="text-ink">{{ $actingAsStore ? $product->store->name : auth()->user()->name }}</b>@if ($actingAsStore) &middot; Seller @endif</label>
            <div class="pd-comment-form"><span @class(['pd-avatar', 'seller' => $actingAsStore]) aria-hidden="true">@if ($actingAsStore && $product->store->logo_path)<img src="{{ Storage::disk('public')->url($product->store->logo_path) }}" alt="" class="size-full object-cover">@else{{ Str::upper(Str::substr($actingAsStore ? $product->store->name : auth()->user()->name, 0, 2)) }}@endif</span><textarea id="discussion-body" name="body" rows="2" maxlength="2000" required placeholder="Ask a question or share a comment...">{{ old('body') }}</textarea><button type="submit" class="pd-btn small">Post message</button></div>
            @error('body')<p class="mt-2 text-xs text-danger" role="alert">{{ $message }}</p>@enderror
            <p class="pd-small mt-3">Everyone can join the conversation. Product ratings are for verified buyers.</p>
        </form>
    @elseif (! auth()->check())
        <a href="{{ route('login', ['redirect' => request()->getPathInfo()]) }}" class="pd-small mt-6 inline-flex min-h-11 items-center font-semibold text-brand">Sign in to join the discussion</a>
    @endif
</section>
