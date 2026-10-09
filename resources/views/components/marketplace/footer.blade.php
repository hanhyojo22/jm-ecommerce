@php
    $user = auth()->user();
    $isBuyer = $user?->account_type === 'buyer';
    $isSeller = $user?->account_type === 'seller';
@endphp

<footer class="mt-12 border-t border-line bg-white">
    <div class="mx-auto max-w-[1320px] px-[18px] sm:px-8">
        <div class="grid gap-7 py-12 sm:grid-cols-2 lg:grid-cols-[1.3fr_repeat(4,1fr)]">
            <div>
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 font-bold tracking-[-0.02em] text-ink" aria-label="JMMart marketplace home">
                    <x-application-logo class="size-8 fill-current text-brand" />
                    <span>JMMart</span>
                </a>
                <p class="mt-4 max-w-[30ch] text-sm leading-6 text-muted">A multi-vendor marketplace for independent stores. Cash on delivery, verified sellers, and free shipping.</p>
            </div>
            <nav aria-label="Shop">
                <h2 class="text-[13px] font-semibold uppercase tracking-[0.05em] text-muted">Shop</h2>
                <div class="mt-3 space-y-0.5 text-sm">
                    <a href="{{ route('catalog.index') }}" class="block min-h-10 py-2 text-ink transition hover:text-brand">Marketplace</a>
                    <a href="{{ route('catalog.index', ['sort' => 'newest']) }}" class="block min-h-10 py-2 text-ink transition hover:text-brand">New arrivals</a>
                    <a href="{{ route('catalog.top-products') }}" class="block min-h-10 py-2 text-ink transition hover:text-brand">Best sellers</a>
                    <a href="{{ route('catalog.stores') }}" class="block min-h-10 py-2 text-ink transition hover:text-brand">Stores</a>
                </div>
            </nav>
            <nav aria-label="Sell">
                <h2 class="text-[13px] font-semibold uppercase tracking-[0.05em] text-muted">Sell</h2>
                <div class="mt-3 space-y-0.5 text-sm">
                    @if ($isSeller)
                        <a href="{{ route('seller.dashboard') }}" class="block min-h-10 py-2 text-ink transition hover:text-brand">Seller Center</a>
                        <a href="{{ route('seller.products.index') }}" class="block min-h-10 py-2 text-ink transition hover:text-brand">Manage products</a>
                    @else
                        <a href="{{ route('seller-application.create') }}" class="block min-h-10 py-2 text-ink transition hover:text-brand">Start selling</a>
                    @endif
                </div>
            </nav>
            <nav aria-label="Support">
                <h2 class="text-[13px] font-semibold uppercase tracking-[0.05em] text-muted">Support</h2>
                <div class="mt-3 space-y-0.5 text-sm">
                    @if ($isBuyer)
                        <a href="{{ route('orders.index') }}" class="block min-h-10 py-2 text-ink transition hover:text-brand">Track order</a>
                    @endif
                    <a href="{{ route('cart.index') }}" class="block min-h-10 py-2 text-ink transition hover:text-brand">Cart</a>
                    <a href="{{ route('checkout.create') }}" class="block min-h-10 py-2 text-ink transition hover:text-brand">Checkout</a>
                </div>
            </nav>
            <nav aria-label="Company">
                <h2 class="text-[13px] font-semibold uppercase tracking-[0.05em] text-muted">Account</h2>
                <div class="mt-3 space-y-0.5 text-sm">
                    <a href="{{ route('profile.edit') }}" class="block min-h-10 py-2 text-ink transition hover:text-brand">My account</a>
                    @if ($canUseMessages ?? ($user && in_array($user->account_type, ['buyer', 'seller'], true)))
                        <a href="{{ route('messages.index') }}" class="block min-h-10 py-2 text-ink transition hover:text-brand">Messages</a>
                    @endif
                    @if ($user?->isPlatformAdministrator())
                        <a href="{{ route('dashboard') }}" class="block min-h-10 py-2 text-ink transition hover:text-brand">Admin</a>
                    @endif
                </div>
            </nav>
        </div>
        <div class="flex flex-col gap-2 border-t border-line py-4 text-[13px] text-muted sm:flex-row sm:items-center sm:justify-between">
            <span>© {{ date('Y') }} JMMart · Buyer Protection · Privacy · Terms</span>
            <span>Customer app · Seller app · Admin</span>
        </div>
    </div>
</footer>

@if (request()->routeIs('home'))
    <nav class="sticky bottom-0 z-40 flex border-t border-line bg-white pb-1 sm:hidden" aria-label="Mobile marketplace navigation">
        <a href="{{ route('home') }}" aria-current="page" class="flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 text-[11px] font-semibold text-brand"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1V10Z"/></svg>Home</a>
        <a href="{{ route('catalog.index') }}" class="flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 text-[11px] text-muted"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>Shop</a>
        @if (! auth()->check() || auth()->user()->account_type === 'buyer')
            <a href="{{ auth()->check() ? route('cart.index') : route('login', ['redirect' => '/cart']) }}" class="flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 text-[11px] text-muted"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 3h2l2.4 12.1a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 2-1.6L21 7H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>Cart</a>
        @endif
        <a href="{{ auth()->check() ? route('profile.edit') : route('login') }}" class="flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 text-[11px] text-muted"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/></svg>Account</a>
    </nav>
@endif
