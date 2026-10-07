<div class="border-b border-ink bg-ink text-center text-[13px] text-white" role="note">
    <div class="mx-auto max-w-[1320px] px-[18px] {{ request()->routeIs('home') ? 'py-[9px]' : 'py-2 sm:px-8' }}">
        @if (request()->routeIs('home'))
            Free shipping over ₱50 · Extended returns through January —
            <a href="{{ route('home') }}#flash-deals" class="font-semibold text-white transition hover:text-blue-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white">Shop Flash Deals</a>
        @else
            Free shipping on every order · Cash on delivery —
            <a href="{{ route('catalog.top-products') }}" class="font-semibold text-white underline underline-offset-4 transition hover:text-blue-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white">Shop best sellers</a>
        @endif
    </div>
</div>
