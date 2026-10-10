<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-[28px] font-bold tracking-[-0.02em] text-ink leading-[1.3]">Reviews</h1>
            <p class="mt-2 text-sm text-muted">Read customer feedback and respond to product reviews.</p>
        </div>
    </x-slot>

    <div class="page-shell">
        <div class="page-container">
            <div class="space-y-4">
                @forelse($reviews as $review)
                    <article class="section-card">
                        <p class="font-semibold text-ink">{{ $review->product->name }}</p>
                        <p class="mt-1 text-amber-500" aria-label="{{ $review->rating }} out of 5 stars">{{ str_repeat('★', $review->rating) }}</p>
                        <p class="mt-2 text-sm text-muted">{{ $review->user->name }} · {{ $review->created_at->format('M j, Y') }}</p>
                        <p class="mt-3 whitespace-pre-line text-sm text-ink">{{ $review->body }}</p>
                        <form method="POST" action="{{ route('seller.reviews.respond', $review) }}" class="mt-5 border-t border-line pt-5">
                            @csrf
                            <label for="response-{{ $review->id }}" class="field-label">Seller response</label>
                            <textarea id="response-{{ $review->id }}" name="body" rows="3" maxlength="2000" class="field-control" required>{{ $review->response?->body }}</textarea>
                            <button class="mt-3 min-h-11 rounded-[10px] bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">{{ $review->response ? 'Update response' : 'Post response' }}</button>
                        </form>
                    </article>
                @empty
                    <div class="content-card px-6 py-14 text-center sm:py-20">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-500" aria-hidden="true">
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8m-8 4h5m-9 6 3.1-2.3A8 8 0 1 0 4 14.4V20Z" /></svg>
                        </div>
                        <h2 class="mt-5 text-lg font-semibold text-ink">No reviews yet</h2>
                        <p class="mx-auto mt-2 max-w-sm text-sm text-muted">When customers review your products, their feedback will appear here.</p>
                        <a href="{{ route('seller.products.index') }}" class="mt-6 inline-flex min-h-11 items-center justify-center rounded-[10px] border border-line bg-white px-4 text-sm font-semibold text-ink transition hover:border-brand hover:text-brand focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">View products</a>
                    </div>
                @endforelse
            </div>
            @if($reviews->hasPages())
                <div class="mt-6">{{ $reviews->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
