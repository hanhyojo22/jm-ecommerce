<x-app-layout>
    @php
        // Same pill language as My purchases: quiet sunken ground, one dot, colour only where it means something.
        $statusPills = [
            'published' => ['Published', 'bg-[var(--color-sunken)] text-success', 'bg-success'],
            'hidden' => ['Hidden', 'bg-[var(--color-warning-wash)] text-warning-text ring-1 ring-inset ring-[var(--color-warning-line)]', 'bg-warning'],
            'removed' => ['Removed', 'bg-[var(--color-sunken)] text-danger', 'bg-danger'],
        ];
        $inlineLink = 'font-semibold text-brand underline-offset-2 hover:text-brand-hover hover:underline';
    @endphp

    <x-marketplace.account-shell active="reviews">
        <header class="min-w-0">
            <h1 class="text-3xl font-semibold tracking-tight text-ink">My reviews</h1>
            <p class="mt-1 text-meta text-muted">
                @if ($reviews->total() > 0)
                    {{ $reviews->total() }} {{ Str::plural('review', $reviews->total()) }}<span aria-hidden="true" class="px-1.5">·</span><span class="tabular-nums">{{ number_format((float) $averageRating, 1) }}</span> average rating<span aria-hidden="true" class="px-1.5">·</span>
                @endif
                <a href="{{ route('orders.index', ['tab' => 'to_review']) }}" class="{{ $inlineLink }}">Review delivered items <span aria-hidden="true">→</span></a>
            </p>
        </header>

        @if ($reviews->isEmpty())
            <section class="account-card mt-6 px-6 py-14 text-center" aria-labelledby="empty-reviews-heading">
                <span class="mx-auto grid size-12 place-items-center rounded-full bg-[var(--color-sunken)] text-muted ring-1 ring-inset ring-[var(--color-line-soft)]" aria-hidden="true"><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"><path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z" /></svg></span>
                <h2 id="empty-reviews-heading" class="mt-4 text-lg font-semibold text-ink">No reviews yet</h2>
                <p class="mx-auto mt-2 max-w-sm text-meta text-muted">Once an order is delivered, you can rate each item and tell other buyers how it held up.</p>
                <a href="{{ route('orders.index', ['tab' => 'to_review']) }}" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-4 text-meta font-semibold text-white transition-colors hover:bg-brand-hover lg:min-h-10">See items to review</a>
            </section>
        @else
            <section class="mt-6 flex flex-col gap-4" aria-label="Your reviews">
                @foreach ($reviews as $review)
                    @php
                        $item = $review->orderItem;
                        $store = $review->product?->store;
                        [$pillLabel, $pillClass, $pillDot] = $statusPills[$review->status] ?? [ucfirst($review->status), 'bg-[var(--color-sunken)] text-muted', 'bg-muted'];
                        $thumb = $item->image_path ?? $review->product?->thumbnail?->path;
                        $productUrl = $review->product?->status === 'active' && $store?->status === 'active' ? route('catalog.show', [$store->slug, $review->product->slug]) : null;
                    @endphp
                    <article class="account-card min-w-0" aria-labelledby="review-{{ $review->id }}-product">
                        <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                            <div class="flex min-w-0 items-center gap-2.5">
                                @if ($store?->logo_path)
                                    <img src="{{ Storage::disk('public')->url($store->logo_path) }}" alt="" width="32" height="32" loading="lazy" class="size-8 shrink-0 rounded-md border border-line bg-white object-cover">
                                @else
                                    <span class="grid size-8 shrink-0 place-items-center rounded-md bg-[var(--color-sunken)] text-muted ring-1 ring-inset ring-[var(--color-line-soft)]" aria-hidden="true"><svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9.5 5.5 4h13L20 9.5"/><path d="M4 9.5a2.67 2.67 0 0 0 5.33 0 2.67 2.67 0 0 0 5.34 0 2.67 2.67 0 0 0 5.33 0"/><path d="M5.5 12.5V20h13v-7.5"/><path d="M10 20v-4h4v4"/></svg></span>
                                @endif
                                <span class="min-w-0">
                                    <span class="block truncate text-[13px] font-semibold leading-[17px] text-ink">{{ $store?->name ?? 'Store unavailable' }}</span>
                                    <span class="block text-[11.5px] leading-[15px] text-muted">Reviewed <time datetime="{{ $review->created_at->toDateString() }}">{{ $review->created_at->format('M j, Y') }}</time>@if ($review->updated_at->gt($review->created_at->copy()->addMinute()))<span aria-hidden="true" class="px-1">·</span>edited {{ $review->updated_at->format('M j') }}@endif</span>
                                </span>
                            </div>
                            <span @class(['inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold', $pillClass])>
                                <span class="size-[7px] rounded-full {{ $pillDot }}" aria-hidden="true"></span>
                                {{ $pillLabel }}
                            </span>
                        </div>

                        <div class="px-4 py-4">
                            <div class="grid grid-cols-[48px_minmax(0,1fr)] items-center gap-3">
                                <div class="relative size-12 overflow-hidden rounded-md border border-line-soft bg-paper">
                                    @if ($thumb)
                                        <img src="{{ Storage::disk('product_images')->url($thumb) }}" alt="" width="48" height="48" loading="lazy" class="size-full object-cover">
                                    @else
                                        <svg class="absolute inset-0 m-auto size-5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M4 8h16l-1 12H5L4 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <h2 id="review-{{ $review->id }}-product" class="truncate text-[13px] font-semibold leading-[17px] text-ink">
                                        @if ($productUrl)<a href="{{ $productUrl }}" class="hover:text-brand-hover hover:underline underline-offset-2">{{ $item->product_name }}</a>@else{{ $item->product_name }}@endif
                                    </h2>
                                    @if ($item->variant_label)<p class="mt-0.5 truncate text-[11.5px] leading-[15px] text-brand">{{ $item->variant_label }}</p>@endif
                                    <div class="mt-1.5 flex items-center gap-2">
                                        <span class="inline-flex gap-0.5 text-amber-500" role="img" aria-label="{{ $review->rating }} out of 5 stars">
                                            @foreach (range(1, 5) as $star)
                                                <svg class="size-4 {{ $star <= $review->rating ? 'fill-current' : 'fill-slate-200' }}" viewBox="0 0 20 20" aria-hidden="true"><path d="m10 1.6 2.5 5.1 5.6.8-4 3.9.9 5.5-5-2.7-5 2.7.9-5.5-4-3.9 5.6-.8L10 1.6Z" /></svg>
                                            @endforeach
                                        </span>
                                        <span class="text-xs font-semibold tabular-nums text-ink" aria-hidden="true">{{ $review->rating }}.0</span>
                                    </div>
                                </div>
                            </div>

                            @if ($review->title)<h3 class="mt-4 text-sm font-semibold text-ink">{{ $review->title }}</h3>@endif
                            @if ($review->body)
                                <p class="{{ $review->title ? 'mt-1' : 'mt-4' }} max-w-[70ch] whitespace-pre-line break-words text-meta leading-6 text-ink">{{ $review->body }}</p>
                            @elseif (! $review->title)
                                <p class="mt-4 text-meta text-muted">You left a rating without a written review.</p>
                            @endif

                            @if ($review->photos->isNotEmpty())
                                <ul class="mt-3 flex flex-wrap gap-2" aria-label="Your photos">
                                    @foreach ($review->photos as $photo)
                                        <li><a href="{{ Storage::disk('public')->url($photo->path) }}" target="_blank" rel="noopener" class="block size-16 overflow-hidden rounded-md border border-line-soft bg-paper focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"><img src="{{ Storage::disk('public')->url($photo->path) }}" alt="Your review photo {{ $loop->iteration }}" width="64" height="64" loading="lazy" class="size-full object-cover"></a></li>
                                    @endforeach
                                </ul>
                            @endif

                            @if ($review->status !== 'published')
                                <div class="mt-4 rounded-md border border-[var(--color-warning-line)] bg-[var(--color-warning-wash)] px-3.5 py-2.5" role="note">
                                    <p class="text-meta font-semibold text-ink">{{ $review->status === 'removed' ? 'JMMart removed this review' : 'This review is hidden from the product page' }}</p>
                                    {{-- The moderation reason is an internal admin note, so the buyer gets the outcome only. --}}
                                    <p class="mt-0.5 text-xs leading-5 text-muted">Other buyers can't see it. JMMart support can explain the decision if you ask.</p>
                                </div>
                            @endif

                            @if ($review->response)
                                <div class="mt-4 rounded-md bg-[var(--color-sunken)] px-3.5 py-3 ring-1 ring-inset ring-[var(--color-line-soft)]">
                                    <p class="text-xs font-semibold text-ink">Reply from {{ $store?->name ?? 'the store' }}<span class="ml-1.5 font-medium text-muted">{{ $review->response->created_at->format('M j, Y') }}</span></p>
                                    <p class="mt-1 max-w-[70ch] whitespace-pre-line break-words text-meta leading-6 text-ink">{{ $review->response->body }}</p>
                                </div>
                            @endif
                        </div>

                        <div class="flex items-center justify-between gap-3 border-t border-line-soft bg-[#fbfcfe] px-4 py-2.5">
                            <p class="flex min-w-0 items-center gap-1.5 truncate text-[11.5px] text-muted">
                                <svg class="size-3.5 shrink-0 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.5 4.5L19 7"/></svg>
                                <span class="truncate">Verified purchase<span aria-hidden="true" class="px-1">·</span><span class="tabular-nums">{{ $item->sellerOrder->order->number }}</span></span>
                            </p>
                            <a href="{{ route('orders.show', $item->sellerOrder->order_id) }}" class="inline-flex min-h-11 shrink-0 items-center gap-1 rounded-md px-1.5 text-xs font-semibold text-brand hover:text-brand-hover lg:min-h-10">View purchase
                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
                            </a>
                        </div>
                    </article>
                @endforeach
            </section>
            @if ($reviews->hasPages())<div class="mt-6">{{ $reviews->links() }}</div>@endif
        @endif
    </x-marketplace.account-shell>
</x-app-layout>
