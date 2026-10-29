@props(['category', 'href', 'selected' => false, 'showCount' => false])

@php
    $categoryImage = match ($category->slug) {
        'electronics', 'fashion', 'health-beauty', 'home-living', 'food-grocery', 'babies-kids', 'sports-outdoors', 'pet-supplies', 'automotive', 'toys-collectibles', 'office-school', 'appliances', 'bags-accessories', 'hobbies-crafts', 'books-media', 'jewelry-watches', 'garden-diy', 'travel-luggage' => "images/categories/{$category->slug}.png",
        default => null,
    };
@endphp

<a href="{{ $href }}" @if ($selected) aria-current="page" @endif @class([
    'group flex min-h-36 min-w-0 flex-col items-center justify-center rounded-lg border bg-surface px-3 py-4 text-center transition duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 motion-reduce:transform-none',
    'border-brand bg-blue-50' => $selected,
    'border-line hover:border-brand/50 hover:shadow-[0_12px_28px_rgba(15,23,42,.10)]' => ! $selected,
])>
    <span class="grid h-16 w-16 shrink-0 place-items-center">
        @if ($categoryImage)
            <img src="{{ asset($categoryImage) }}" alt="" class="h-full w-full object-contain transition duration-200 group-hover:scale-105" />
        @else
            <x-category-icon :category="$category" class="h-8 w-8 text-muted" />
        @endif
    </span>
    <span class="mt-3 line-clamp-2 text-sm font-semibold leading-5 text-ink">{{ $category->name }}</span>
    @if ($showCount)
        <span class="mt-1 text-xs text-muted">{{ number_format($category->product_count) }} {{ Str::plural('product', $category->product_count) }}</span>
    @endif
</a>
