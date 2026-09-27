@props(['category'])

@switch($category->slug)
    @case('electronics')
        <svg {{ $attributes->merge(['class' => 'h-4 w-4']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
        @break

    @case('fashion')
        <svg {{ $attributes->merge(['class' => 'h-4 w-4']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m8 4 4 2 4-2 4 4-3 3v9H7v-9L4 8l4-4Z"/><path d="M9 4 12 8l3-4"/></svg>
        @break

    @case('home-living')
        <svg {{ $attributes->merge(['class' => 'h-4 w-4']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m3 11 9-7 9 7v9H3v-9Z"/><path d="M9 20v-6h6v6"/></svg>
        @break

    @case('food-grocery')
        <svg {{ $attributes->merge(['class' => 'h-4 w-4']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16l-1.5 13h-13L4 7Z"/><path d="M9 10v3M12 10v3M15 10v3M8 7l1-3h6l1 3"/></svg>
        @break

    @case('health-beauty')
        <svg {{ $attributes->merge(['class' => 'h-4 w-4']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 4h6M10 4v4l-3 4v7h10v-7l-3-4V4"/><path d="M9 14h6"/></svg>
        @break

    @default
        <svg {{ $attributes->merge(['class' => 'h-4 w-4']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7.5V5a1 1 0 0 1 1-1h2.5L20 16.5a2.12 2.12 0 0 1 0 3 2.12 2.12 0 0 1-3 0L4.5 7A2.12 2.12 0 0 1 4 7.5Z"/><circle cx="8.5" cy="8.5" r="1"/></svg>
@endswitch
