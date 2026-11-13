@php($official = $entry->author_type === 'seller')
@php($author = $official ? $product->store->name : $entry->user->name)
<div @class(['seller-review-message', 'seller-review-message-reply' => $entry->parent_id])>
    @if ($official && $product->store->logo_path)
        <img src="{{ Storage::disk('public')->url($product->store->logo_path) }}" alt="" class="seller-review-avatar-image" loading="lazy" width="31" height="31">
    @else
        <span @class(['seller-review-avatar', 'seller-review-avatar-store' => $official]) aria-hidden="true">{{ Str::upper(Str::substr($author, 0, 2)) }}</span>
    @endif
    <div class="min-w-0 flex-1">
        <div @class(['seller-review-bubble', 'is-seller-response' => $official])>
            <div class="flex flex-wrap items-center gap-2"><strong>{{ $author }}</strong>@if ($official)<span class="seller-review-badge">Seller</span>@endif</div>
            <p>{{ $entry->body }}</p>
        </div>
        <div class="seller-review-actions">
            <time datetime="{{ $entry->created_at->toIso8601String() }}" title="{{ $entry->created_at->format('M j, Y g:i A') }}">{{ $entry->created_at->diffForHumans() }}</time>
            @if ($entry->parent_id)<span>Reply to {{ $entry->parent->author_type === 'seller' ? $product->store->name : $entry->parent->user?->name }}</span>@endif
            @if ($canReply)
                <div class="seller-review-reply seller-inline-reply">
                    <form method="POST" action="{{ route('discussions.store', $product) }}" data-discussion-reply-form data-optimistic-submit data-clerk-session-refresh="{{ route('clerk.session.refresh') }}" data-author-name="{{ $product->store->name }}" data-author-initials="{{ Str::upper(Str::substr($product->store->name, 0, 2)) }}" data-author-is-seller="1" data-author-image="{{ $product->store->logo_path ? Storage::disk('public')->url($product->store->logo_path) : '' }}" data-reply-to="{{ $entry->author_type === 'seller' ? $product->store->name : $entry->user?->name }}">
                        @csrf
                        <input type="hidden" name="parent_id" value="{{ $entry->id }}">
                        <input type="hidden" name="seller_reviews" value="1">
                        <input type="hidden" name="discussion_page" value="{{ $discussionPage }}">
                        <label for="discussion-reply-{{ $entry->id }}" class="sr-only">Your reply</label>
                        <div class="seller-reply-field">
                            <input id="discussion-reply-{{ $entry->id }}" type="text" name="body" maxlength="2000" required placeholder="Write a reply..." value="{{ old('parent_id') === $entry->id ? old('body') : '' }}" class="field-control">
                            <button type="submit" class="seller-reply-send" aria-label="Send reply" title="Send reply"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z" /><path d="M22 2 11 13" /></svg></button>
                        </div>
                        @if (old('parent_id') === $entry->id)@error('body')<p class="text-xs text-danger" role="alert">{{ $message }}</p>@enderror @endif
                        <p data-discussion-feedback class="text-xs text-danger" role="alert" hidden></p>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>
