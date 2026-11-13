@php($initialFeedbackTab = request()->has('discussion_page') ? 'conversations' : 'reviews')

<x-app-layout :error-summary="false">
    <x-slot name="header">
        <div class="sc-page-head">
            <div>
                <h1 class="sc-title">Reviews &amp; conversations</h1>
                <p class="sc-subtitle">Buyer feedback across your catalog. Reply to reviews and product questions from one place.</p>
            </div>
        </div>
    </x-slot>

    <div class="page-shell" data-seller-reviews x-data="{ feedbackTab: @js($initialFeedbackTab) }">
        <div class="page-container seller-workspace-page sc-stack">
            @if (session('status'))<p class="sc-notice" data-tone="success" role="status">{{ session('status') }}</p>@endif
            @if ($errors->any())<p class="sc-notice" data-tone="danger" role="alert">{{ $errors->first() }}</p>@endif

            <div class="seller-reviews-layout">
                <aside class="seller-reviews-rail">
                    <section class="sc-card seller-review-summary" aria-labelledby="review-summary-heading">
                        <h2 id="review-summary-heading" class="sr-only">Review summary</h2>
                        <p class="seller-review-summary-label">Average rating</p>
                        @if ($averageRating === null)
                            <p class="seller-review-score is-empty" aria-hidden="true">&mdash;</p>
                            <div class="seller-review-stars" role="img" aria-label="No ratings yet">
                                @foreach (range(1, 5) as $star)
                                    <svg viewBox="0 0 20 20" class="is-empty" aria-hidden="true"><path d="m10 1.6 2.5 5.1 5.6.8-4 3.9.9 5.5-5-2.7-5 2.7.9-5.5-4-3.9 5.6-.8L10 1.6Z" /></svg>
                                @endforeach
                            </div>
                        @else
                            <p class="seller-review-score">{{ number_format($averageRating, 1) }}<small>/ 5</small></p>
                            <div class="seller-review-stars" role="img" aria-label="{{ number_format($averageRating, 1) }} out of 5 stars">
                                @foreach (range(1, 5) as $star)
                                    <svg viewBox="0 0 20 20" @class(['is-empty' => $star > round($averageRating)]) aria-hidden="true"><path d="m10 1.6 2.5 5.1 5.6.8-4 3.9.9 5.5-5-2.7-5 2.7.9-5.5-4-3.9 5.6-.8L10 1.6Z" /></svg>
                                @endforeach
                            </div>
                        @endif
                        <p class="seller-review-summary-meta">{{ number_format($reviewCount) }} product {{ Str::plural('review', $reviewCount) }} &middot; {{ number_format($discussionCount) }} {{ Str::plural('conversation', $discussionCount) }}</p>

                        <div class="seller-review-bars" role="group" aria-label="Review rating distribution">
                            @foreach (range(5, 1) as $star)
                                @php($count = (int) $ratingCounts->get($star, 0))
                                <div class="seller-review-bar" role="img" aria-label="{{ $star }} star: {{ $count }} {{ Str::plural('review', $count) }}">
                                    <span class="seller-review-bar-label" aria-hidden="true">{{ $star }}<svg viewBox="0 0 20 20" aria-hidden="true"><path d="m10 1.6 2.5 5.1 5.6.8-4 3.9.9 5.5-5-2.7-5 2.7.9-5.5-4-3.9 5.6-.8L10 1.6Z" /></svg></span>
                                    <span class="seller-review-bar-track" aria-hidden="true"><span style="width: {{ $reviewCount ? round($count / $reviewCount * 100, 1) : 0 }}%"></span></span>
                                    <span class="seller-review-bar-count" aria-hidden="true">{{ number_format($count) }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="seller-review-response">
                            <p class="seller-review-summary-label">Seller responses</p>
                            @if ($reviewCount === 0)
                                <p class="seller-review-response-note">Replies to reviews appear publicly under each review.</p>
                            @else
                                <p class="seller-review-response-value">{{ $responseRate }}%</p>
                                <p class="seller-review-response-note">{{ number_format($respondedCount) }} of {{ number_format($reviewCount) }} reviews answered</p>
                                <div class="seller-review-response-track" aria-hidden="true"><span style="width: {{ $responseRate }}%"></span></div>
                                <p class="seller-review-awaiting">
                                    @if ($awaitingReplyCount > 0)
                                        <span><strong>{{ number_format($awaitingReplyCount) }}</strong> {{ Str::plural('review', $awaitingReplyCount) }} waiting for your reply</span>
                                        <a href="#customer-feedback" @click="feedbackTab = 'reviews'" class="sc-link">Review</a>
                                    @else
                                        <span>Every published review has a reply.</span>
                                    @endif
                                </p>
                            @endif
                        </div>
                    </section>
                </aside>

                <div class="seller-reviews-main">
                    <section id="customer-feedback" class="sc-card seller-feedback scroll-mt-24" aria-labelledby="customer-feedback-heading">
                        <div class="sc-card-head">
                            <div>
                                <h2 id="customer-feedback-heading" class="sc-card-title">Customer feedback</h2>
                                <p class="sc-card-note">Newest first</p>
                            </div>
                        </div>

                        <div class="sc-tabs" role="tablist" aria-label="Customer feedback type">
                            <button type="button" id="reviews-tab" role="tab" aria-controls="reviews-panel" x-ref="reviewsTab" @click="feedbackTab = 'reviews'" @keydown.arrow-right.prevent="feedbackTab = 'conversations'; $nextTick(() => $refs.conversationsTab.focus())" @keydown.end.prevent="feedbackTab = 'conversations'; $nextTick(() => $refs.conversationsTab.focus())" :aria-selected="feedbackTab === 'reviews' ? 'true' : 'false'" :tabindex="feedbackTab === 'reviews' ? 0 : -1" class="sc-tab">
                                Product reviews <span class="sc-tab-count" aria-hidden="true">{{ number_format($reviews->total()) }}</span>
                            </button>
                            <button type="button" id="conversations-tab" role="tab" aria-controls="conversations-panel" x-ref="conversationsTab" @click="feedbackTab = 'conversations'" @keydown.arrow-left.prevent="feedbackTab = 'reviews'; $nextTick(() => $refs.reviewsTab.focus())" @keydown.home.prevent="feedbackTab = 'reviews'; $nextTick(() => $refs.reviewsTab.focus())" :aria-selected="feedbackTab === 'conversations' ? 'true' : 'false'" :tabindex="feedbackTab === 'conversations' ? 0 : -1" class="sc-tab">
                                Product conversations <span class="sc-tab-count" aria-hidden="true">{{ number_format($discussionCount) }}</span>
                            </button>
                        </div>

                        <div id="reviews-panel" role="tabpanel" aria-labelledby="reviews-tab" x-show="feedbackTab === 'reviews'" @if ($initialFeedbackTab === 'conversations') x-cloak @endif>
                            <div class="seller-review-thread-list">
                                @forelse ($reviews as $review)
                                    <livewire:reviews.thread :review="$review" :seller-view="true" :key="'seller-review-'.$review->id" />
                                @empty
                                    <div class="sc-empty">
                                        <h3>No reviews yet</h3>
                                        <p>When buyers review delivered orders, their feedback appears here so you can reply.</p>
                                        <a href="{{ route('seller.products.index') }}" class="seller-outline-button">View your products</a>
                                    </div>
                                @endforelse
                            </div>
                            @if ($reviews->isNotEmpty())
                                <div class="sc-card-foot">
                                    <span>Showing {{ number_format($reviews->firstItem()) }}&ndash;{{ number_format($reviews->lastItem()) }} of {{ number_format($reviews->total()) }} {{ Str::plural('review', $reviews->total()) }}</span>
                                    @if ($reviews->hasPages())<div>{{ $reviews->appends(request()->except(['discussion_page', 'page']))->links() }}</div>@endif
                                </div>
                            @endif
                        </div>

                        <div id="conversations-panel" role="tabpanel" aria-labelledby="conversations-tab" x-show="feedbackTab === 'conversations'" @if ($initialFeedbackTab !== 'conversations') x-cloak @endif>
                            <p data-discussion-announcement class="sr-only" role="status" aria-live="polite"></p>
                            <div class="seller-review-thread-list seller-review-discussion-list">
                                @forelse ($discussions as $rootDiscussion)
                                    @php($product = $rootDiscussion->product)
                                    @php($canReply = $product->status === 'active' && $product->store->status === 'active' && $tenant->status === 'active')
                                    <article id="discussion-{{ $rootDiscussion->id }}" class="seller-review-discussion scroll-mt-24">
                                        <div class="seller-review-product">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5z" /><path d="m4 7.5 8 4.5 8-4.5M12 12v9" /></svg>
                                            <a href="{{ route('seller.products.show', $product) }}">{{ $product->name }}</a>
                                        </div>
                                        @foreach (collect([$rootDiscussion])->concat($rootDiscussion->replies) as $entry)
                                            @include('seller.reviews._discussion-message', ['discussionPage' => $discussions->currentPage()])
                                        @endforeach
                                    </article>
                                @empty
                                    <div class="sc-empty">
                                        <h3>No product conversations yet</h3>
                                        <p>Questions and comments about your products will appear here.</p>
                                    </div>
                                @endforelse
                            </div>
                            @if ($discussions->isNotEmpty())
                                <div class="sc-card-foot">
                                    <span>Showing {{ number_format($discussions->firstItem()) }}&ndash;{{ number_format($discussions->lastItem()) }} of {{ number_format($discussions->total()) }} {{ Str::plural('conversation', $discussions->total()) }}</span>
                                    @if ($discussions->hasPages())<div>{{ $discussions->links() }}</div>@endif
                                </div>
                            @endif
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
