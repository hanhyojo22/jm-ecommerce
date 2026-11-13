@php
    $user = auth()->user();
    $sellerTenant = $user?->account_type === 'seller' ? $user->sellerTenant() : null;
@endphp

<aside data-header-promo aria-label="Store announcement" class="border-b border-ink bg-ink text-[12px] leading-4 text-[#c5d1e0]">
    <div class="marketplace-container flex min-h-9 items-center justify-between gap-4 py-1.5">
        <div class="flex min-w-0 items-center gap-2 text-white">
            <svg class="hidden size-3.5 shrink-0 text-[#8fb7ff] sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>
            <p class="truncate">
                <span class="sm:hidden">Free shipping · COD —</span>
                <span class="hidden sm:inline">Free shipping offers · Cash on delivery · Verified local stores</span>
            </p>
        </div>

        <div class="flex shrink-0 items-center gap-3">
            <a href="{{ route('catalog.top-products') }}" class="font-semibold text-white underline decoration-white/40 underline-offset-4 transition hover:text-blue-200 hover:decoration-blue-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">Shop best sellers</a>
            <span class="hidden h-3 w-px bg-white/20 md:inline-block" aria-hidden="true"></span>
            <a href="{{ route('catalog.stores') }}" class="hidden font-medium text-[#c5d1e0] transition hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white md:inline-flex">Local stores</a>
            @if (! $user?->isPlatformAdministrator() && ! $sellerTenant)
                <span class="hidden h-3 w-px bg-white/20 lg:inline-block" aria-hidden="true"></span>
                <a href="{{ auth()->check() ? route('seller-application.create') : route('login', ['redirect' => '/start-selling']) }}" class="hidden font-medium text-[#c5d1e0] transition hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white lg:inline-flex">Sell on JMMart</a>
            @endif
        </div>
    </div>
</aside>
