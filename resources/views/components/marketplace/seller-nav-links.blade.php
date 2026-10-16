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
    'flex-1 space-y-4' => $mode === 'desktop',
    'flex-1 space-y-4 overflow-y-auto' => $mode === 'mobile',
    'px-3 py-4' => $mode === 'desktop',
    'p-4' => $mode === 'mobile',
]) aria-label="Seller workspace navigation">
    @foreach ($groups as $group)
        <div class="space-y-1">
            @if ($group['label'])
                <p class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-[.16em] text-muted">{{ $group['label'] }}</p>
            @endif
            @foreach ($group['items'] as $item)
                <a href="{{ $item['href'] }}" @if ($item['label'] === 'Orders') data-seller-shortcut="orders" aria-keyshortcuts="O" @endif @class([
                    'group flex min-h-11 items-center gap-3 rounded px-3 text-sm transition focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2',
                    'bg-blue-50 font-semibold text-brand' => $item['active'],
                    'font-medium text-muted hover:bg-canvas hover:text-ink' => ! $item['active'],
                ]) {{ $item['active'] ? 'aria-current=page' : '' }}>
                    <svg @class([
                        'size-[18px] shrink-0',
                        'text-brand' => $item['active'],
                        'text-slate-400 group-hover:text-ink' => ! $item['active'],
                    ]) viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        @switch($item['icon'])
                            @case('overview')<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z" />@break
                            @case('products')<path d="m12 3 9 5v8l-9 5-9-5V8z" /><path d="m3 8 9 5 9-5M12 13v8" />@break
                            @case('orders')<path d="M3 4h2l2.5 12h10L21 8H6" /><circle cx="9" cy="20" r="1" /><circle cx="18" cy="20" r="1" />@break
                            @case('messages')<path d="M20 15a4 4 0 0 1-4 4H8l-4 3V7a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4z" />@break
                            @case('reviews')<path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z" />@break
                            @case('earnings')<rect x="3" y="5" width="18" height="14" rx="2" /><path d="M3 10h18M7 15h3" />@break
                            @case('storefront')<path d="M4 10v10h16V10M3 10l2-6h14l2 6M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0" />@break
                            @case('settings')<circle cx="12" cy="12" r="3" /><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.6a7.8 7.8 0 0 1-1.7 1l-.3 1.8h-2.8l-.3-1.8a7.8 7.8 0 0 1-1.7-1l-1.7.6-1.4-2.4 1.4-1.1a7.4 7.4 0 0 1 0-2l-1.4-1.1 1.4-2.4 1.7.6a7.8 7.8 0 0 1 1.7-1l.3-1.8h2.8l.3 1.8a7.8 7.8 0 0 1 1.7 1l1.7-.6 1.4 2.4-1.4 1.1a7.4 7.4 0 0 1 0 2Z" />@break
                        @endswitch
                    </svg>
                    <span class="min-w-0 truncate">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>
    @endforeach
</nav>
