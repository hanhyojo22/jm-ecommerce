@props(['mode' => 'desktop'])

@php
    $storefrontTenant = auth()->user()?->sellerTenant();
    $canManageStore = $storefrontTenant && auth()->user()->tenantMemberships()
        ->where('tenant_id', $storefrontTenant->id)
        ->whereIn('role', ['owner', 'manager'])
        ->exists();
    $groups = [
        [
            'label' => null,
            'items' => [
                ['label' => 'Overview', 'href' => route('seller.dashboard'), 'icon' => 'overview', 'active' => request()->routeIs('seller.dashboard')],
            ],
        ],
        [
            'label' => 'Manage',
            'items' => [
                ['label' => 'Orders', 'href' => route('seller.orders.index'), 'icon' => 'orders', 'active' => request()->routeIs('seller.orders.*')],
                ['label' => 'Returns', 'href' => route('seller.returns.index'), 'icon' => 'returns', 'active' => request()->routeIs('seller.returns.*')],
                ['label' => 'Products', 'href' => route('seller.products.index'), 'icon' => 'products', 'active' => request()->routeIs('seller.products.*')],
            ],
        ],
        [
            'label' => 'Engage',
            'items' => [
                ['label' => 'Messages', 'href' => route('messages.index', ['context' => 'selling']), 'icon' => 'messages', 'active' => request()->routeIs('messages.*')],
                ['label' => 'Reviews', 'href' => route('seller.reviews.index'), 'icon' => 'reviews', 'active' => request()->routeIs('seller.reviews.*')],
            ],
        ],
        [
            'label' => 'Business',
            'items' => [
                ['label' => 'Earnings', 'href' => route('seller.earnings.index'), 'icon' => 'earnings', 'active' => request()->routeIs('seller.earnings.*')],
                ['label' => 'Platform fees', 'href' => route('seller.platform-fees.index'), 'icon' => 'fees', 'active' => request()->routeIs('seller.platform-fees.*')],
            ],
        ],
    ];
    if ($storefrontTenant) {
        $storeItems = [
            ['label' => 'Storefront', 'href' => route('catalog.store', $storefrontTenant->slug), 'icon' => 'storefront', 'active' => false],
        ];

        if ($canManageStore) {
            $storeItems[] = ['label' => 'Store settings', 'href' => route('seller.store-settings.edit'), 'icon' => 'settings', 'active' => request()->routeIs('seller.store-settings.*')];
        }

        array_push($groups[array_key_last($groups)]['items'], ...$storeItems);
    }
@endphp

<nav @class([
    'flex-1 space-y-5' => $mode === 'desktop',
    'flex-1 space-y-5 overflow-y-auto' => $mode === 'mobile',
    'px-1 py-4' => $mode === 'desktop',
    'p-4' => $mode === 'mobile',
]) aria-label="Seller workspace navigation">
    @foreach ($groups as $group)
        <div class="space-y-1">
            @if ($group['label'])
                <p class="sc-nav-label">{{ $group['label'] }}</p>
            @endif
            @foreach ($group['items'] as $item)
                <a href="{{ $item['href'] }}" @if ($item['label'] === 'Orders') data-seller-shortcut="orders" aria-keyshortcuts="O" @endif @class([
                    'group flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm transition focus-visible:ring-2 focus-visible:ring-white',
                    'bg-white/10 font-semibold text-white' => $item['active'],
                    'font-medium text-slate-300 hover:bg-white/5 hover:text-white' => ! $item['active'],
                ]) {{ $item['active'] ? 'aria-current=page' : '' }}>
                    <svg @class([
                        'size-[18px] shrink-0',
                        'text-[#8db6ff]' => $item['active'],
                        'text-slate-400 group-hover:text-white' => ! $item['active'],
                    ]) viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        @switch($item['icon'])
                            @case('overview')<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z" />@break
                            @case('products')<path d="m12 3 9 5v8l-9 5-9-5V8z" /><path d="m3 8 9 5 9-5M12 13v8" />@break
                            @case('orders')<path d="M3 4h2l2.5 12h10L21 8H6" /><circle cx="9" cy="20" r="1" /><circle cx="18" cy="20" r="1" />@break
                            @case('returns')<path d="M3 12a9 9 0 1 0 3-6.7" /><path d="M3 4v5h5" />@break
                            @case('messages')<path d="M20 15a4 4 0 0 1-4 4H8l-4 3V7a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4z" />@break
                            @case('reviews')<path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z" />@break
                            @case('fees')<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z" /><path d="M9 8h6M9 12h6M9 16h3" />@break
                            @case('earnings')<rect x="3" y="5" width="18" height="14" rx="2" /><path d="M3 10h18M7 15h3" />@break
                            @case('storefront')<path d="M4 10v10h16V10M3 10l2-6h14l2 6M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0" />@break
                            @case('settings')<circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z" />@break
                        @endswitch
                    </svg>
                    <span class="min-w-0 truncate">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>
    @endforeach
</nav>
