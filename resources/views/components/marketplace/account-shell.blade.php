@props(['active' => 'settings', 'title' => 'My account', 'subtitle' => null])

@php
    $user = auth()->user();
    $isBuyer = $user && in_array($user->account_type, ['buyer', 'seller'], true);
    $isSeller = $user?->account_type === 'seller';
    $canUseMessages = $user && in_array($user->account_type, ['buyer', 'seller'], true);
    $sellerTenant = $user?->sellerTenant();
    $isSellerAccount = $active === 'settings' && $isSeller && $user->preferred_workspace === 'seller' && $sellerTenant !== null;
    $navItem = 'flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2';
@endphp

<div class="page-shell min-h-[calc(100dvh-4.5rem)] {{ $active === 'messages' ? 'py-3 sm:py-5 lg:py-10' : '' }}">
    <div class="page-container max-w-[1320px]">
        <nav aria-label="Breadcrumb" class="min-w-0 items-center gap-2 text-sm text-muted {{ $active === 'messages' ? 'hidden lg:flex' : 'flex' }}">
            <a href="{{ $isSellerAccount ? route('seller.dashboard') : route('home') }}" class="shrink-0 font-medium transition hover:text-brand-hover">{{ $isSellerAccount ? 'Seller Center' : 'Home' }}</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page" class="min-w-0 truncate font-semibold text-ink">{{ $title }}</span>
            @if ($isBuyer)<span class="ml-1 hidden shrink-0 rounded-full bg-canvas px-2.5 py-1 text-xs font-medium text-muted ring-1 ring-inset ring-line sm:inline">{{ $isSellerAccount ? 'Seller view' : 'Customer view' }}</span>@endif
        </nav>

        <div class="grid items-start {{ $isSellerAccount ? '' : 'lg:grid-cols-[264px_minmax(0,1fr)]' }} {{ $active === 'messages' ? 'gap-0 lg:mt-5 lg:gap-6' : 'mt-5 gap-6' }}">
            @unless ($isSellerAccount)
            <nav class="rounded-[14px] border border-line bg-surface p-3 lg:sticky lg:top-24 {{ $active === 'messages' ? 'hidden lg:block' : '' }}" aria-label="Account" data-od-id="account-nav" data-role-navigation="customer">
                <div class="grid gap-1">
                    @if ($isBuyer)
                        <a href="{{ route('orders.index') }}" @class([$navItem, 'bg-blue-50 font-semibold text-brand' => $active === 'orders', 'font-medium text-ink hover:bg-canvas' => $active !== 'orders']) @if ($active === 'orders') aria-current="page" @endif>
                            <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 4h2l2.5 12h10L21 8H6" /><circle cx="9" cy="20" r="1" /><circle cx="18" cy="20" r="1" /></svg>
                            My Purchases
                        </a>
                    @endif
                    @if ($isBuyer)
                        <a href="{{ route('reviews.index') }}" @class([$navItem, 'bg-blue-50 font-semibold text-brand' => $active === 'reviews', 'font-medium text-ink hover:bg-canvas' => $active !== 'reviews']) @if ($active === 'reviews') aria-current="page" @endif>
                            <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z" /></svg>
                            My Reviews
                        </a>
                        <a href="{{ route('delivery-addresses.index') }}" @class([$navItem, 'bg-blue-50 font-semibold text-brand' => $active === 'addresses', 'font-medium text-ink hover:bg-canvas' => $active !== 'addresses']) @if ($active === 'addresses') aria-current="page" @endif>
                            <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 21s7-6 7-12a7 7 0 1 0-14 0c0 6 7 12 7 12Z" /><circle cx="12" cy="9" r="2" /></svg>
                            Delivery Addresses
                        </a>
                    @endif
                    @if ($canUseMessages)
                        <a href="{{ $isSeller ? route('messages.index', ['context' => 'buying']) : route('messages.index') }}" @class([$navItem, 'bg-blue-50 font-semibold text-brand' => $active === 'messages', 'font-medium text-ink hover:bg-canvas' => $active !== 'messages']) @if ($active === 'messages') aria-current="page" @endif>
                            <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M20 15a4 4 0 0 1-4 4H8l-4 3V7a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4z" /></svg>
                            Messages
                        </a>
                    @endif
                    <a href="{{ route('profile.edit') }}" @class([$navItem, 'bg-blue-50 font-semibold text-brand' => $active === 'settings', 'font-medium text-ink hover:bg-canvas' => $active !== 'settings']) @if ($active === 'settings') aria-current="page" @endif>
                        <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="3" /><path d="M19 12a7 7 0 0 0-.1-1.2l2-1.6-2-3.4-2.4 1a7 7 0 0 0-2-1.2L14 3h-4l-.5 2.6a7 7 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.6A7 7 0 0 0 5 12c0 .4 0 .8.1 1.2l-2 1.6 2 3.4 2.4-1a7 7 0 0 0 2 1.2L10 21h4l.5-2.6a7 7 0 0 0 2-1.2l2.4 1 2-3.4-2-1.6c.1-.4.1-.8.1-1.2Z" /></svg>
                        Settings
                    </a>
                </div>
                <div class="mt-3 border-t border-line pt-3">
                    @if ($isSeller && ! $sellerTenant)
                        <a href="{{ route('seller-application.create') }}" class="{{ $navItem }} font-semibold text-brand hover:bg-blue-50">
                            <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 10v10h16V10M3 10l2-6h14l2 6M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0" /></svg>
                            Seller application
                        </a>
                    @elseif ($isBuyer)
                        <a href="{{ route('seller-application.create') }}" class="{{ $navItem }} font-semibold text-brand hover:bg-blue-50">
                            <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                            Start selling
                        </a>
                    @endif
                </div>
            </nav>
            @endunless

            <div class="account-shell-content min-w-0">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
