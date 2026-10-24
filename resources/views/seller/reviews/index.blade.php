<x-app-layout>
    <x-slot name="header">
        <div class="sc-page-head">
            <div>
                <h1 class="sc-title">Reviews &amp; conversations</h1>            </div>
        </div>
    </x-slot>

    <div class="page-shell" data-seller-reviews>
        <div class="page-container seller-workspace-page">
            @if (session('status'))<p class="sc-notice mb-5" data-tone="success" role="status">{{ session('status') }}</p>@endif
            @if ($errors->any())<p class="sc-notice mb-5" data-tone="danger" role="alert">{{ $errors->first() }}</p>@endif
            <section class="seller-reviews-board" aria-label="Store reviews and conversations">
                <div class="seller-review-summary">
                    @if ($averageRating === null)
                        <div><p class="text-[15px] font-semibold text-ink">No ratings yet</p><p class="text-[13px] text-muted">{{ number_format($reviewCount) }} product {{ Str::plural('review', $reviewCount) }} · {{ number_format($reviewCount + $discussionCount) }} {{ Str::plural('conversation', $reviewCount + $discussionCount) }}</p></div>
                    @else
                    <strong class="seller-review-score">{{ number_format($averageRating, 1) }}</strong>
                    <div>
                        <div class="seller-review-stars" role="img" aria-label="{{ $averageRating === null ? 'No ratings yet' : number_format($averageRating, 1).' out of 5 stars' }}">
                            @foreach (range(1, 5) as $star)<span @class(['text-slate-300' => $star > round($averageRating ?? 0)])>&#9733;</span>@endforeach
                        </div>
                        <p class="text-[13px] text-muted">{{ number_format($reviewCount) }} product {{ Str::plural('review', $reviewCount) }} · {{ number_format($reviewCount + $discussionCount) }} {{ Str::plural('conversation', $reviewCount + $discussionCount) }}</p>
                    </div>
                    <div class="seller-review-bars" role="group" aria-label="Review rating distribution">
                        @foreach (range(5, 1) as $star)
                            @php($count = (int) $ratingCounts->get($star, 0))
                            <div class="seller-review-bar" role="img" aria-label="{{ $star }} star: {{ $count }} {{ Str::plural('review', $count) }}">
                                <span>{{ $star }}</span><span class="seller-review-bar-track"><span style="width: {{ $reviewCount ? round($count / $reviewCount * 100, 1) : 0 }}%"></span></span>
                            </div>
                        @endforeach
                    </div>
                    @endif
                </div>

                <section aria-labelledby="seller-product-reviews-heading">
                    <div class="seller-review-section-heading"><h2 id="seller-product-reviews-heading">Product reviews</h2><span>{{ number_format($reviews->total()) }} total</span></div>
                    <div class="seller-review-thread-list">
                        @forelse ($reviews as $review)
                            <livewire:reviews.thread :review="$review" :seller-view="true" :key="'seller-review-'.$review->id" />
                        @empty
                            <div class="seller-review-empty">
                                <h3>No reviews yet</h3>
                                <p>When buyers review your products, their feedback will appear here.</p>
                                <a href="{{ route('seller.products.index') }}">View products</a>
                            </div>
                        @endforelse
                    </div>
                    @if ($reviews->hasPages())<div class="seller-review-pagination">{{ $reviews->withQueryString()->links() }}</div>@endif
                </section>

                <section class="seller-review-discussions" aria-labelledby="seller-product-questions-heading">
                    <div class="seller-review-section-heading"><h2 id="seller-product-questions-heading">Product conversations</h2><span>{{ number_format($discussionCount) }} total</span></div>
                    <p data-discussion-announcement class="sr-only" role="status" aria-live="polite"></p>
                    <div class="seller-review-thread-list">
                        @forelse ($discussions as $rootDiscussion)
                            @php($product = $rootDiscussion->product)
                            @php($canReply = $product->status === 'active' && $product->store->status === 'active' && $tenant->status === 'active')
                            <article id="discussion-{{ $rootDiscussion->id }}" class="seller-review-discussion">
                                <p class="seller-review-product">{{ $product->name }}</p>
                                @foreach (collect([$rootDiscussion])->concat($rootDiscussion->replies) as $entry)
                                    @include('seller.reviews._discussion-message', ['discussionPage' => $discussions->currentPage()])
                                @endforeach
                            </article>
                        @empty
                            <div class="seller-review-empty"><h3>No product conversations yet</h3><p>Questions and comments about your products will appear here.</p></div>
                        @endforelse
                    </div>
                    @if ($discussions->hasPages())<div class="seller-review-pagination">{{ $discussions->links() }}</div>@endif
                </section>
            </section>
        </div>
    </div>
</x-app-layout>
