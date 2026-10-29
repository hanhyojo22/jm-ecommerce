@props(['compact' => false])

<span aria-hidden="true" class="pointer-events-none absolute inset-x-[8%] top-1/2 z-[5] -translate-y-1/2 rounded-md bg-ink/80 px-2 py-3 text-center text-white shadow-sm {{ $compact ? '' : 'sm:py-4' }}">
    <span class="block font-semibold uppercase tracking-[0.02em] {{ $compact ? 'text-sm sm:text-base' : 'text-xl sm:text-2xl' }}">Sold out</span>
    <span class="mt-1 block leading-snug {{ $compact ? 'text-xs sm:text-xs' : 'text-xs sm:text-sm' }}">This product is currently unavailable</span>
</span>
