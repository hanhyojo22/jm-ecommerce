@php($official = $discussion->author_type === 'seller')
@php($author = $official ? $product->store->name : $discussion->user->name)
@php($currentUser = auth()->user())
@php($actingAsStore = $canDiscuss && $currentUser?->account_type === 'seller' && $currentUser->isMemberOfTenant($product->tenant_id))
@php($replyAuthor = $actingAsStore ? $product->store->name : $currentUser?->name ?? '')
@php($replyAuthorImage = $actingAsStore && $product->store->logo_path ? Storage::disk('public')->url($product->store->logo_path) : '')
@php($parentAuthor = $discussion->parent ? ($discussion->parent->author_type === 'seller' ? $product->store->name : $discussion->parent->user?->name) : null)
<article id="discussion-{{ $discussion->id }}" data-discussion-id="{{ $discussion->id }}" data-discussion-root-id="{{ $rootDiscussionId }}" class="pd-discussion {{ $discussion->parent_id ? 'ml-5' : '' }}">
    <span @class(['pd-avatar', 'seller' => $official])>@if ($official && $product->store->logo_path)<img src="{{ Storage::disk('public')->url($product->store->logo_path) }}" alt="" class="size-full object-cover">@else{{ Str::upper(Str::substr($author, 0, 2)) }}@endif</span>
    <div class="min-w-0 flex-1">
        <div class="pd-discussion-bubble"><strong>{{ $author }}</strong>@if ($official)<span class="ml-1 rounded-md bg-blue-50 px-1 text-xs font-semibold text-brand">Seller</span>@endif<p>{{ $discussion->body }}</p></div>
        <div class="pd-discussion-actions"><time datetime="{{ $discussion->created_at->toIso8601String() }}">{{ $discussion->created_at->diffForHumans() }}</time>@if ($discussion->parent)<span>Reply to {{ $parentAuthor }}</span>@endif
        @if ($canDiscuss)
            <details class="pd-discussion-reply"><summary class="inline-flex min-h-10 cursor-pointer items-center font-semibold text-brand">Reply</summary>
                <form method="POST" action="{{ route('discussions.store', $product) }}" class="pd-comment-form" data-discussion-form data-optimistic-submit data-clerk-session-refresh="{{ route('clerk.session.refresh') }}" data-author-name="{{ $replyAuthor }}" data-author-initials="{{ Str::upper(Str::substr($replyAuthor, 0, 2)) }}" data-author-is-seller="{{ $actingAsStore ? '1' : '0' }}" data-author-image="{{ $replyAuthorImage }}" data-reply-to="{{ $author }}">
                    @csrf<input type="hidden" name="parent_id" value="{{ $discussion->id }}"><label for="reply-{{ $discussion->id }}" class="sr-only">Your reply</label><textarea id="reply-{{ $discussion->id }}" name="body" rows="2" maxlength="2000" required placeholder="Write a reply..."></textarea><button type="submit" class="pd-reply-submit">Post reply</button>
                    <p data-discussion-feedback class="mt-1 basis-full text-xs text-danger" role="alert" hidden></p>
                </form>
            </details>
        @endif
        </div>
    </div>
</article>
