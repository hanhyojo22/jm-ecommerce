@props(['active' => 'settings', 'title' => 'My account', 'subtitle' => null])
@php($isSellerAccount = $active === 'settings' && auth()->user()?->account_type === 'seller' && auth()->user()?->preferred_workspace === 'seller' && auth()->user()?->sellerTenant() !== null)

<div class="page-shell min-h-[calc(100dvh-4.5rem)] {{ $active === 'messages' ? 'py-3 sm:py-5 lg:py-10' : '' }}">
    <div class="page-container max-w-[1320px]">
        <nav aria-label="Breadcrumb" class="mb-5 flex min-w-0 items-center gap-2 text-sm text-muted {{ $active === 'messages' ? 'hidden lg:flex' : '' }}">
            <a href="{{ $isSellerAccount ? route('seller.dashboard') : route('orders.index') }}" class="shrink-0 font-medium transition hover:text-brand-hover">{{ $isSellerAccount ? 'Seller Center' : 'My account' }}</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page" class="min-w-0 truncate font-semibold text-ink">{{ $title }}</span>
        </nav>
        <div class="account-shell-content min-w-0">
            {{ $slot }}
        </div>
    </div>
</div>