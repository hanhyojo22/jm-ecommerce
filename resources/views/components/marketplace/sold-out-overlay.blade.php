@props(['compact' => false])

<span aria-hidden="true" class="pointer-events-none absolute inset-x-[8%] top-1/2 z-[5] -translate-y-1/2 rounded-[10px] bg-ink/80 px-2 py-3 text-center text-white shadow-sm {{ $compact ? '' : 'sm:py-4' }}">
    <span class="block font-bold uppercase tracking-[0.02em] {{ $compact ? 'text-sm sm:text-base' : 'text-xl sm:text-2xl' }}">Sold out</span>
    <span class="mt-1 block leading-snug {{ $compact ? 'text-[10px] sm:text-xs' : 'text-xs sm:text-sm' }}">This product is currently unavailable</span>
</span>
