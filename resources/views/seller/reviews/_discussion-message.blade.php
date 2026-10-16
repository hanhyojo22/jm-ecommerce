@php($official = $entry->author_type === 'seller')
@php($author = $official ? $product->store->name : $entry->user->name)
<div @class(['seller-review-message', 'seller-review-message-reply' => $entry->parent_id])>
    @if ($official && $product->store->logo_path)
        <img src="{{ Storage::disk('public')->url($product->store->logo_path) }}" alt="" class="seller-review-avatar-image" loading="lazy" width="31" height="31">
    @else
        <span @class(['seller-review-avatar', 'seller-review-avatar-store' => $official]) aria-hidden="true">{{ Str::upper(Str::substr($author, 0, 2)) }}</span>
    @endif
    <div class="min-w-0 flex-1">
        <div class="seller-review-bubble">
            <div class="flex flex-wrap items-center gap-2"><strong>{{ $author }}</strong>@if ($official)<span class="seller-review-badge">Seller</span>@endif</div>
            <p>{{ $entry->body }}</p>
        </div>
        <div class="seller-review-actions">
            <time datetime="{{ $entry->created_at->toIso8601String() }}" title="{{ $entry->created_at->format('M j, Y g:i A') }}">{{ $entry->created_at->diffForHumans() }}</time>
            @if ($entry->parent_id)<span>Reply to {{ $entry->parent->author_type === 'seller' ? $product->store->name : $entry->parent->user?->name }}</span>@endif
            @if ($canReply)
                <details class="seller-review-reply" @if (old('parent_id') === $entry->id && $errors->any()) open @endif>
                    <summary>Reply</summary>
                    <form method="POST" action="{{ route('discussions.store', $product) }}" data-discussion-reply-form data-clerk-session-refresh="{{ route('clerk.session.refresh') }}">
                        @csrf
                        <input type="hidden" name="parent_id" value="{{ $entry->id }}">
                        <input type="hidden" name="seller_reviews" value="1">
                        <input type="hidden" name="discussion_page" value="{{ $discussionPage }}">
                        <label for="discussion-reply-{{ $entry->id }}" class="sr-only">Your reply</label>
                        <textarea id="discussion-reply-{{ $entry->id }}" name="body" rows="2" maxlength="2000" required placeholder="Write a reply...">{{ old('parent_id') === $entry->id ? old('body') : '' }}</textarea>
                        @if (old('parent_id') === $entry->id)@error('body')<p class="text-xs text-danger" role="alert">{{ $message }}</p>@enderror @endif
                        <button type="submit">Post reply</button>
                        <p data-discussion-feedback class="text-xs text-danger" role="alert" hidden></p>
                    </form>
                </details>
            @endif
        </div>
    </div>
</div>
