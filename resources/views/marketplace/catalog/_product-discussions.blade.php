@php($canDiscuss = auth()->check() && in_array(auth()->user()->account_type, ['buyer', 'seller'], true) && auth()->user()->hasVerifiedClerkEmail())
@php($actingAsStore = $canDiscuss && auth()->user()->account_type === 'seller' && auth()->user()->isMemberOfTenant($product->tenant_id))
@php($posterName = $actingAsStore ? $product->store->name : auth()->user()?->name ?? '')
@php($posterImage = $actingAsStore && $product->store->logo_path ? Storage::disk('public')->url($product->store->logo_path) : '')
<section id="discussions" aria-label="Product discussions">
    <p data-discussion-announcement class="sr-only" role="status" aria-live="polite"></p>
    <div data-discussion-list>
        @foreach ($discussions as $rootDiscussion)
            @foreach (collect([$rootDiscussion])->concat($rootDiscussion->replies) as $discussion)
                @include('marketplace.catalog._discussion-message', ['discussion' => $discussion, 'product' => $product, 'canDiscuss' => $canDiscuss, 'rootDiscussionId' => $rootDiscussion->id])
            @endforeach
        @endforeach
    </div>
    {{ $discussions->fragment('discussions')->links() }}
    @if ($canDiscuss)
        <template data-discussion-pending-template>
            <article class="pd-discussion" data-pending-discussion aria-busy="true">
                <span class="pd-avatar" data-pending-avatar aria-hidden="true"></span>
                <div class="min-w-0 flex-1">
                    <div class="pd-discussion-bubble">
                        <strong data-pending-author></strong>
                        <span class="ml-1 rounded-md bg-blue-50 px-1 text-xs font-semibold text-brand" data-pending-seller hidden>Seller</span>
                        <p data-pending-body></p>
                    </div>
                    <div class="pd-discussion-actions">
                        <time>Just now</time>
                        <span data-pending-reply-to hidden></span>
                        <span data-pending-status role="status">Sending...</span>
                    </div>
                </div>
            </article>
        </template>
        <form method="POST" action="{{ route('discussions.store', $product) }}" class="mt-7" data-discussion-form data-optimistic-submit data-clerk-session-refresh="{{ route('clerk.session.refresh') }}" data-author-name="{{ $posterName }}" data-author-initials="{{ Str::upper(Str::substr($posterName, 0, 2)) }}" data-author-is-seller="{{ $actingAsStore ? '1' : '0' }}" data-author-image="{{ $posterImage }}" data-reply-to="">
            @csrf
            <label for="discussion-body" class="pd-small">Posting as <b class="text-ink">{{ $actingAsStore ? $product->store->name : auth()->user()->name }}</b>@if ($actingAsStore) &middot; Seller @endif</label>
            <div class="pd-comment-form"><span @class(['pd-avatar', 'seller' => $actingAsStore]) aria-hidden="true">@if ($actingAsStore && $product->store->logo_path)<img src="{{ Storage::disk('public')->url($product->store->logo_path) }}" alt="" class="size-full object-cover">@else{{ Str::upper(Str::substr($posterName, 0, 2)) }}@endif</span><textarea id="discussion-body" name="body" rows="2" maxlength="2000" required placeholder="Ask a question or share a comment...">{{ old('body') }}</textarea><button type="submit" class="pd-btn small">Post message</button></div>
            @error('body')<p class="mt-2 text-xs text-danger" role="alert">{{ $message }}</p>@enderror
            <p data-discussion-feedback class="mt-2 text-xs text-danger" role="alert" hidden></p>
            <p class="pd-small mt-3">Everyone can join the conversation. Product ratings are for verified buyers.</p>
        </form>
    @elseif (! auth()->check())
        <a href="{{ route('login', ['redirect' => request()->getPathInfo()]) }}" class="pd-small mt-6 inline-flex min-h-11 items-center font-semibold text-brand">Sign in to join the discussion</a>
    @endif
</section>
