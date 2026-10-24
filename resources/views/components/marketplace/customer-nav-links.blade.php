@props(['mode' => 'desktop'])

@php
    $user = auth()->user();
    $items = [
        ['label' => 'My Purchases', 'href' => route('orders.index'), 'icon' => 'orders', 'active' => request()->routeIs('orders.*', 'returns.show')],
        ['label' => 'My Reviews', 'href' => route('reviews.index'), 'icon' => 'reviews', 'active' => request()->routeIs('reviews.index')],
        ['label' => 'Delivery Addresses', 'href' => route('delivery-addresses.index'), 'icon' => 'addresses', 'active' => request()->routeIs('delivery-addresses.*')],
        ['label' => 'Messages', 'href' => $user?->account_type === 'seller' ? route('messages.index', ['context' => 'buying']) : route('messages.index'), 'icon' => 'messages', 'active' => request()->routeIs('messages.*')],
        ['label' => 'Settings', 'href' => route('profile.edit'), 'icon' => 'settings', 'active' => request()->routeIs('profile.edit')],
    ];
@endphp

<nav class="flex-1 space-y-1 {{ $mode === 'mobile' ? 'overflow-y-auto p-4' : 'px-3 py-4' }}" aria-label="Customer account navigation" data-role-navigation="customer">
    <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-[.14em] text-slate-400">My account</p>
    @foreach ($items as $item)
        <a href="{{ $item['href'] }}" @class([
            'group flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm transition focus-visible:ring-2 focus-visible:ring-white',
            'bg-white/10 font-semibold text-white' => $item['active'],
            'font-medium text-slate-300 hover:bg-white/5 hover:text-white' => ! $item['active'],
        ]) @if ($item['active']) aria-current="page" @endif>
            <svg class="size-[18px] shrink-0 {{ $item['active'] ? 'text-[#8db6ff]' : 'text-slate-400 group-hover:text-white' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                @switch($item['icon'])
                    @case('orders')<path d="M3 4h2l2.5 12h10L21 8H6" /><circle cx="9" cy="20" r="1" /><circle cx="18" cy="20" r="1" />@break
                    @case('reviews')<path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z" />@break
                    @case('addresses')<path d="M12 21s7-6 7-12a7 7 0 1 0-14 0c0 6 7 12 7 12Z" /><circle cx="12" cy="9" r="2" />@break
                    @case('messages')<path d="M20 15a4 4 0 0 1-4 4H8l-4 3V7a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4z" />@break
                    @case('settings')<circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z" />@break
                @endswitch
            </svg>
            <span class="min-w-0 truncate">{{ $item['label'] }}</span>
        </a>
    @endforeach
    <div class="mt-5 space-y-1 border-t border-white/10 pt-4">
        <a href="{{ route('home') }}" class="group flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium text-slate-300 transition hover:bg-white/5 hover:text-white">
            <svg class="size-[18px] shrink-0 text-slate-400 group-hover:text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 10v10h16V10M3 10l2-6h14l2 6M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0" /></svg>
            <span class="min-w-0 truncate">Marketplace</span>
        </a>
        <a href="{{ route('seller-application.create') }}" class="group flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium text-slate-300 transition hover:bg-white/5 hover:text-white">
            <svg class="size-[18px] shrink-0 text-slate-400 group-hover:text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.83z" /><path d="M7 7h.01" /></svg>
            <span class="min-w-0 truncate">Start selling</span>
        </a>
    </div>
</nav>
