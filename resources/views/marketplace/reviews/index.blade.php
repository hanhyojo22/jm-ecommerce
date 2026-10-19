<x-app-layout>
    <x-marketplace.account-shell active="reviews" title="My Reviews">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-brand">Your feedback</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-ink">My Reviews</h1>
            </div>
            @if ($reviews->total() > 0)
                <p class="rounded-full border border-line bg-white px-3 py-2 text-sm font-semibold text-ink">{{ $reviews->total() }} {{ Str::plural('review', $reviews->total()) }} shared</p>
            @endif
        </header>

        @if ($reviews->isEmpty())
            <section class="mt-7 rounded-2xl border border-dashed border-line bg-white px-6 py-14 text-center sm:py-16" aria-labelledby="empty-reviews-heading">
                <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-blue-50 text-brand" aria-hidden="true"><svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z" /></svg></span>
                <h2 id="empty-reviews-heading" class="mt-5 font-semibold text-ink">No reviews yet</h2>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-muted">After an order is delivered, open the purchase to share your experience with the store.</p>
                <a href="{{ route('orders.index') }}" class="mt-6 inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">View my purchases</a>
            </section>
        @else
            <section class="mt-7 space-y-4" aria-label="Your reviews">
                @foreach ($reviews as $review)
                    @php($statusStyle = match ($review->status) {
                        'published' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
                        'hidden' => 'bg-amber-50 text-amber-800 ring-amber-200',
                        'removed' => 'bg-rose-50 text-rose-800 ring-rose-200',
                        default => 'bg-slate-100 text-slate-700 ring-line',
                    })
                    <article class="overflow-hidden rounded-2xl border border-line bg-white shadow-sm">
                        <div class="flex flex-wrap items-start gap-4 p-5 sm:flex-nowrap sm:p-6">
                            <span class="grid size-20 shrink-0 place-items-center overflow-hidden rounded-xl bg-slate-100 text-slate-400" aria-hidden="true">
                                @if ($review->product?->thumbnail)
                                    <img src="{{ Storage::disk('product_images')->url($review->product->thumbnail->path) }}" alt="" width="80" height="80" loading="lazy" class="size-full object-contain">
                                @else
                                    <svg class="size-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="16" rx="2" /><circle cx="8" cy="9" r="1.5" /><path d="m4 18 5-5 3 3 3-3 5 5" /></svg>
                                @endif
                            </span>
                            <div class="min-w-0 flex-1">
                                <h2 class="break-words font-semibold text-ink">{{ $review->orderItem->product_name }}</h2>
                                <p class="mt-1 text-sm text-muted">{{ $review->product?->store?->name ?? 'Store unavailable' }}</p>
                                <p class="mt-2 text-xs text-muted">Reviewed <time datetime="{{ $review->created_at->toDateString() }}">{{ $review->created_at->format('M j, Y') }}</time></p>
                            </div>
                            <span class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusStyle }}">{{ ucfirst($review->status) }}</span>
                        </div>

                        <div class="border-t border-line px-5 py-5 sm:px-6">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span class="inline-flex gap-0.5 text-amber-500" role="img" aria-label="{{ $review->rating }} out of 5 stars">
                                    @foreach (range(1, 5) as $star)
                                        <svg class="size-5 {{ $star <= $review->rating ? 'fill-current' : 'fill-slate-200 text-slate-200' }}" viewBox="0 0 20 20" aria-hidden="true"><path d="m10 1.6 2.5 5.1 5.6.8-4 3.9.9 5.5-5-2.7-5 2.7.9-5.5-4-3.9 5.6-.8L10 1.6Z" /></svg>
                                    @endforeach
                                </span>
                                <span class="text-sm font-semibold text-ink">{{ $review->rating }} / 5</span>
                                <span class="text-xs font-medium text-muted">Verified purchase</span>
                            </div>
                            @if ($review->title)<h3 class="mt-4 text-base font-semibold text-ink">{{ $review->title }}</h3>@endif
                            @if ($review->body)
                                <p class="mt-2 max-w-3xl whitespace-pre-line break-words text-sm leading-6 text-slate-700">{{ $review->body }}</p>
                            @elseif (! $review->title)
                                <p class="mt-3 text-sm text-muted">You left a rating without a written review.</p>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line bg-slate-50 px-5 py-3 sm:px-6">
                            <span class="text-xs text-muted">Review from a delivered order</span>
                            <a href="{{ route('orders.show', $review->orderItem->sellerOrder->order_id) }}" class="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-semibold text-brand transition hover:bg-blue-50 hover:text-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">View purchase <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg></a>
                        </div>
                    </article>
                @endforeach
            </section>
            @if ($reviews->hasPages())<div class="mt-7">{{ $reviews->links() }}</div>@endif
        @endif
    </x-marketplace.account-shell>
</x-app-layout>
