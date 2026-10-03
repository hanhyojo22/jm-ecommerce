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

    @case('babies-kids')
        <svg {{ $attributes->merge(['class' => 'h-4 w-4']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="3"/><path d="M7 20v-2a5 5 0 0 1 10 0v2M9 13l-2 2M15 13l2 2"/></svg>
        @break

    @case('sports-outdoors')
        <svg {{ $attributes->merge(['class' => 'h-4 w-4']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 9v6M8 7v10M16 7v10M19 9v6M8 12h8"/></svg>
        @break

    @case('pet-supplies')
        <svg {{ $attributes->merge(['class' => 'h-4 w-4']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="8" cy="9" r="1.5"/><circle cx="16" cy="9" r="1.5"/><circle cx="6" cy="14" r="1.5"/><circle cx="18" cy="14" r="1.5"/><path d="M8 19c0-2.2 1.8-4 4-4s4 1.8 4 4c0 1-.8 1.5-1.8 1.5H9.8C8.8 20.5 8 20 8 19Z"/></svg>
        @break

    @case('automotive')
        <svg {{ $attributes->merge(['class' => 'h-4 w-4']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m5 17 1.5-6h11L19 17"/><path d="M4 17h16v3H4zM7 20v1M17 20v1M8 8h8"/><circle cx="7.5" cy="17" r="1"/><circle cx="16.5" cy="17" r="1"/></svg>
        @break

    @case('toys-collectibles')
        <svg {{ $attributes->merge(['class' => 'h-4 w-4']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M7 9h10a4 4 0 0 1 4 4v2a3 3 0 0 1-5.1 2.1L14 15h-4l-1.9 2.1A3 3 0 0 1 3 15v-2a4 4 0 0 1 4-4Z"/><path d="M8 12v4M6 14h4M16 13h.01M18 15h.01"/></svg>
        @break

    @case('office-school')
        <svg {{ $attributes->merge(['class' => 'h-4 w-4']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 4h11a3 3 0 0 1 3 3v13H8a3 3 0 0 0-3 3V4Z"/><path d="M8 4v16M11 9h5M11 13h5"/></svg>
        @break

    @case('appliances')
        <svg {{ $attributes->merge(['class' => 'h-4 w-4']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><circle cx="12" cy="14" r="4"/><path d="M8 7h.01M11 7h5"/></svg>
        @break

    @case('bags-accessories')
        <svg {{ $attributes->merge(['class' => 'h-4 w-4']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 8h14l-1 12H6L5 8Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/></svg>
        @break

    @case('hobbies-crafts')
        <svg {{ $attributes->merge(['class' => 'h-4 w-4']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 4a8 8 0 1 0 8 8c0-1.1-.9-2-2-2h-2a2 2 0 0 0-2 2c0 1.1-.9 2-2 2a2 2 0 0 1-2-2 8 8 0 0 0 2-8Z"/><circle cx="8" cy="10" r=".7"/><circle cx="10" cy="7" r=".7"/><circle cx="14" cy="7" r=".7"/></svg>
        @break

    @default
        <svg {{ $attributes->merge(['class' => 'h-4 w-4']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7.5V5a1 1 0 0 1 1-1h2.5L20 16.5a2.12 2.12 0 0 1 0 3 2.12 2.12 0 0 1-3 0L4.5 7A2.12 2.12 0 0 1 4 7.5Z"/><circle cx="8.5" cy="8.5" r="1"/></svg>
@endswitch
