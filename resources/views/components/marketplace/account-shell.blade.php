@props(['active' => 'settings', 'title' => 'My account', 'subtitle' => null, 'workspace' => null])
@php($isSellerAccount = $workspace === 'seller' || ($active === 'settings' && auth()->user()?->account_type === 'seller' && auth()->user()?->preferred_workspace === 'seller' && auth()->user()?->sellerTenant() !== null))
{{-- Each account page reads at its own measure; the width itself lives in app.css next to the other workspace measures. --}}
@php($measure = match ($active) {
    'orders' => 'account-measure-orders',
    'messages' => 'account-measure-messages',
    'reviews' => 'account-measure-reviews',
    'addresses' => 'account-measure-addresses',
    'settings' => 'account-measure-settings',
    default => '',
})

<div class="page-shell min-h-[calc(100dvh-4.5rem)] {{ $active === 'messages' ? 'account-messages-shell' : '' }}">
    <div class="page-container {{ $measure }}">
        @unless ($active === 'settings')
            <nav aria-label="Breadcrumb" class="mb-5 flex min-w-0 items-center gap-2 text-sm text-muted {{ $active === 'messages' ? 'hidden lg:flex' : '' }}">
                <a href="{{ $isSellerAccount ? route('seller.dashboard') : route('orders.index') }}" class="shrink-0 font-medium transition hover:text-brand-hover">{{ $isSellerAccount ? 'Seller Center' : 'My account' }}</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page" class="min-w-0 truncate font-semibold text-ink">{{ $title }}</span>
            </nav>
        @endunless
        <div class="account-shell-content min-w-0">
            {{ $slot }}
        </div>
    </div>
</div>
