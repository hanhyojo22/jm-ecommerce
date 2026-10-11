<x-app-layout>
    <x-marketplace.account-shell active="reviews" title="My Reviews">
        <div class="rounded-[14px] border border-line bg-white p-5 sm:p-[26px]">
            <h1 class="text-2xl font-semibold tracking-[-0.02em] text-ink">My Reviews</h1>
            <p class="mt-1 text-sm text-muted">Reviews from your purchases across the marketplace.</p>

            <div class="mt-6 divide-y divide-line">
                @forelse ($reviews as $review)
                    <article class="py-5 first:pt-0">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="font-semibold text-ink">{{ $review->product?->name ?? $review->orderItem->product_name }}</h2>
                                <p class="mt-1 text-xs text-muted">{{ $review->product?->store?->name }} · {{ $review->created_at->format('M j, Y') }}</p>
                            </div>
                            <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-brand">{{ $review->rating }} / 5 · {{ ucfirst($review->status) }}</span>
                        </div>
                        @if ($review->title)<p class="mt-3 text-sm font-semibold text-ink">{{ $review->title }}</p>@endif
                        @if ($review->body)<p class="mt-2 whitespace-pre-wrap break-words text-sm text-muted">{{ $review->body }}</p>@endif
                        <a href="{{ route('orders.show', $review->orderItem->sellerOrder->order_id) }}" class="mt-3 inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:text-brand-hover">View purchase</a>
                    </article>
                @empty
                    <p class="py-8 text-sm text-muted">You have not reviewed a purchase yet.</p>
                @endforelse
            </div>
            <div class="mt-5">{{ $reviews->links() }}</div>
        </div>
    </x-marketplace.account-shell>
</x-app-layout>
