@props(['active' => 'overview', 'title' => 'My account', 'subtitle' => null])

@php
    $user = auth()->user();
    $isBuyer = $user?->account_type === 'buyer';
    $isSeller = $user?->account_type === 'seller';
    $canUseMessages = $user && in_array($user->account_type, ['buyer', 'seller'], true);
    $sellerTenant = $user?->sellerTenant();
    $navItem = 'flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2';
@endphp

<div class="page-shell min-h-[calc(100dvh-4.5rem)]">
    <div class="page-container max-w-[1320px]">
        <nav aria-label="Breadcrumb" class="flex min-w-0 items-center gap-2 text-sm text-muted">
            <a href="{{ route('home') }}" class="shrink-0 font-medium transition hover:text-brand-hover">Home</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page" class="min-w-0 truncate font-semibold text-ink">{{ $title }}</span>
            @if ($isBuyer)<span class="ml-1 hidden shrink-0 rounded-full bg-canvas px-2.5 py-1 text-xs font-medium text-muted ring-1 ring-inset ring-line sm:inline">Customer context · Shopping</span>@endif
        </nav>

        <div class="mt-5 grid items-start gap-6 lg:grid-cols-[264px_minmax(0,1fr)]">
            <nav class="rounded-[14px] border border-line bg-surface p-3 lg:sticky lg:top-24" aria-label="Account" data-od-id="account-nav" data-role-navigation="customer">
                <div class="grid gap-1">
                    <a href="{{ route('profile.edit') }}" @class([$navItem, 'bg-blue-50 font-semibold text-brand' => $active === 'overview', 'font-medium text-ink hover:bg-canvas' => $active !== 'overview']) @if ($active === 'overview') aria-current="page" @endif>
                        <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z" /></svg>
                        Overview
                    </a>
                    @if ($isBuyer)
                        <a href="{{ route('orders.index') }}" @class([$navItem, 'bg-blue-50 font-semibold text-brand' => $active === 'orders', 'font-medium text-ink hover:bg-canvas' => $active !== 'orders']) @if ($active === 'orders') aria-current="page" @endif>
                            <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 4h2l2.5 12h10L21 8H6" /><circle cx="9" cy="20" r="1" /><circle cx="18" cy="20" r="1" /></svg>
                            Orders
                        </a>
                    @endif
                    @if ($canUseMessages)
                        <a href="{{ route('messages.index') }}" @class([$navItem, 'bg-blue-50 font-semibold text-brand' => $active === 'messages', 'font-medium text-ink hover:bg-canvas' => $active !== 'messages']) @if ($active === 'messages') aria-current="page" @endif>
                            <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M20 15a4 4 0 0 1-4 4H8l-4 3V7a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4z" /></svg>
                            Messages
                        </a>
                    @endif
                    <a href="{{ route('profile.edit') }}#settings" @class([$navItem, 'bg-blue-50 font-semibold text-brand' => $active === 'settings', 'font-medium text-ink hover:bg-canvas' => $active !== 'settings'])>
                        <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="3" /><path d="M19 12a7 7 0 0 0-.1-1.2l2-1.6-2-3.4-2.4 1a7 7 0 0 0-2-1.2L14 3h-4l-.5 2.6a7 7 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.6A7 7 0 0 0 5 12c0 .4 0 .8.1 1.2l-2 1.6 2 3.4 2.4-1a7 7 0 0 0 2 1.2L10 21h4l.5-2.6a7 7 0 0 0 2-1.2l2.4 1 2-3.4-2-1.6c.1-.4.1-.8.1-1.2Z" /></svg>
                        Settings
                    </a>
                </div>
                <div class="mt-3 border-t border-line pt-3">
                    @if ($isSeller)
                        <a href="{{ $sellerTenant ? route('seller.dashboard') : route('seller-application.create') }}" class="{{ $navItem }} font-semibold text-brand hover:bg-blue-50">
                            <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 10v10h16V10M3 10l2-6h14l2 6M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0" /></svg>
                            Seller Center
                        </a>
                    @elseif ($isBuyer)
                        <a href="{{ route('seller-application.create') }}" class="{{ $navItem }} font-semibold text-brand hover:bg-blue-50">
                            <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                            Start selling
                        </a>
                    @endif
                </div>
            </nav>

            <div class="account-shell-content min-w-0">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
