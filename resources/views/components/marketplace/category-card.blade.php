@props(['category', 'href', 'selected' => false])

@php
    $categoryImage = match ($category->slug) {
        'electronics', 'fashion', 'health-beauty', 'home-living', 'food-grocery', 'babies-kids', 'sports-outdoors', 'pet-supplies', 'automotive', 'toys-collectibles', 'office-school', 'appliances', 'bags-accessories', 'hobbies-crafts', 'books-media', 'jewelry-watches', 'garden-diy', 'travel-luggage' => "images/categories/{$category->slug}.png",
        default => null,
    };
@endphp

<a href="{{ $href }}" @if ($selected) aria-current="page" @endif @class([
    'group flex min-h-36 min-w-0 flex-col items-center justify-center border bg-white px-3 py-4 text-center transition duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2 motion-reduce:transform-none',
    'border-transparent bg-emerald-50/50' => $selected,
    'border-slate-100 hover:border-emerald-200' => ! $selected,
])>
    <span class="grid h-16 w-16 shrink-0 place-items-center">
        @if ($categoryImage)
            <img src="{{ asset($categoryImage) }}" alt="" class="h-full w-full object-contain transition duration-200 group-hover:scale-105" />
        @else
            <x-category-icon :category="$category" class="h-8 w-8 text-slate-500" />
        @endif
    </span>
    <span class="mt-3 line-clamp-2 text-sm font-medium leading-5 text-slate-900">{{ $category->name }}</span>
</a>
