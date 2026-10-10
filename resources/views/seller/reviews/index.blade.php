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
                    <div class="rounded-xl border border-line bg-white p-4 sm:p-5">
                        <livewire:reviews.thread :review="$review" :seller-view="true" :key="'seller-review-'.$review->id" />
                    </div>
                @empty
                    <div class="content-card px-6 py-10 text-center sm:py-12">
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
