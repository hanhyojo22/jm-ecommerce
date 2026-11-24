@props(['mode' => 'desktop'])

@php
    // The menu follows the signed-in role: pages a role can't open are left out, and pages it can
    // open but not change say "View only", matching the checks in the seller controllers.
    // Returns stay "View only" for staff: deciding a return is an owner's or manager's call.
    $member = auth()->user()?->sellerMembership();
    $canManage = in_array($member?->role, ['owner', 'manager'], true);
    $isHeadOffice = (bool) $member?->isHeadOffice();
    $canManageStore = $canManage && $isHeadOffice;
    $isPrescriptionQueue = request()->routeIs('seller.orders.index') && request('review') === 'prescription';
    $prescriptionCount = $member?->is_pharmacist
        // Prescriptions waiting on the customer for a clearer photo are not the pharmacist's move yet.
        ? $member->sellerOrders()->where('status', 'pending')->whereHas('order.prescription', fn ($prescription) => $prescription->where('status', 'pending'))->count()
        : 0;
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
                ['label' => 'Orders', 'href' => route('seller.orders.index'), 'icon' => 'orders', 'active' => request()->routeIs('seller.orders.*') && ! $isPrescriptionQueue],
                ...($member?->is_pharmacist
                    ? [['label' => 'Prescriptions', 'href' => route('seller.orders.index', ['review' => 'prescription']), 'icon' => 'prescription', 'active' => $isPrescriptionQueue, 'count' => $prescriptionCount, 'countLabel' => 'waiting for a pharmacist']]
                    : []),
                ['label' => 'Returns', 'href' => route('seller.returns.index'), 'icon' => 'returns', 'active' => request()->routeIs('seller.returns.*'), 'viewOnly' => ! $canManage],
            ],
        ],
        [
            'label' => $isHeadOffice ? 'Catalog' : 'Branch',
            'items' => [
                ['label' => 'Products', 'href' => route('seller.products.index'), 'icon' => 'products', 'active' => request()->routeIs('seller.products.*'), 'viewOnly' => ! $canManageStore],
                ...($canManageStore
                    ? [['label' => 'Other names', 'href' => route('seller.ingredient-aliases.index'), 'icon' => 'tag', 'active' => request()->routeIs('seller.ingredient-aliases.*')]]
                    : []),
                // Branch staff count their own branch's stock; head-office staff have no branch to count.
                ...($canManage || ! $isHeadOffice
                    ? [['label' => 'Branch stock', 'href' => route('seller.branch-stock.index'), 'icon' => 'stock', 'active' => request()->routeIs('seller.branch-stock.*')]]
                    : []),
                ...($canManage
                    ? [['label' => 'Opening hours', 'href' => route('seller.opening-hours.edit'), 'icon' => 'clock', 'active' => request()->routeIs('seller.opening-hours.*')]]
                    : []),
                // A branch manager runs their own branch's details and staff; head office has these under Business.
                ...($canManage && ! $isHeadOffice
                    ? [
                        ['label' => 'Branch details', 'href' => route('seller.store-settings.edit'), 'icon' => 'settings', 'active' => request()->routeIs('seller.store-settings.*')],
                        ['label' => 'Staff', 'href' => route('seller.staff.index'), 'icon' => 'staff', 'active' => request()->routeIs('seller.staff.*')],
                    ]
                    : []),
            ],
        ],
        [
            'label' => 'Customers',
            'items' => [
                ['label' => 'Messages', 'href' => route('messages.index', ['context' => 'selling']), 'icon' => 'messages', 'active' => request()->routeIs('messages.*')],
                ['label' => 'Reviews', 'href' => route('seller.reviews.index'), 'icon' => 'reviews', 'active' => request()->routeIs('seller.reviews.*')],
            ],
        ],
        ...($canManageStore
            ? [[
                'label' => 'Business',
                'items' => [
                    ['label' => 'Branches', 'href' => route('seller.branches.index'), 'icon' => 'storefront', 'active' => request()->routeIs('seller.branches.*')],
                    ['label' => 'Staff', 'href' => route('seller.staff.index'), 'icon' => 'staff', 'active' => request()->routeIs('seller.staff.*')],
                    ['label' => 'Branch details', 'href' => route('seller.store-settings.edit'), 'icon' => 'settings', 'active' => request()->routeIs('seller.store-settings.*')],
                ],
            ]]
            : []),
    ];
@endphp

<nav @class([
    'flex-1 space-y-4 overflow-y-auto px-0 py-3.5' => $mode === 'desktop',
    'flex-1 space-y-4 overflow-y-auto p-3.5' => $mode === 'mobile',
]) aria-label="Seller workspace navigation">
    @foreach ($groups as $group)
        <div class="space-y-0.5">
            @if ($group['label'])
                <p class="workspace-nav-label">{{ $group['label'] }}</p>
            @endif
            @foreach ($group['items'] as $item)
                <a href="{{ $item['href'] }}" @if ($item['label'] === 'Orders') data-seller-shortcut="orders" aria-keyshortcuts="O" @elseif ($item['label'] === 'Products') data-seller-shortcut="search" aria-keyshortcuts="S" @endif class="workspace-nav-link" {{ $item['active'] ? 'aria-current=page' : '' }}>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        @switch($item['icon'])
                            @case('overview')<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z" />@break
                            @case('products')<path d="m12 3 9 5v8l-9 5-9-5V8z" /><path d="m3 8 9 5 9-5M12 13v8" />@break
                            @case('stock')<path d="M4 7h16M4 12h16M4 17h10" />@break
                            @case('tag')<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8Z" /><circle cx="7.5" cy="7.5" r="1.5" />@break
                            @case('prescription')<path d="M7 3h7a4 4 0 0 1 0 8H7zM7 11v10M11 11l8 10M19 11l-8 10" />@break
                            @case('staff')<circle cx="9" cy="8" r="3.5" /><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14.2a6.5 6.5 0 0 1 3.5 5.8" />@break
                            @case('orders')<path d="M3 4h2l2.5 12h10L21 8H6" /><circle cx="9" cy="20" r="1" /><circle cx="18" cy="20" r="1" />@break
                            @case('clock')<circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />@break
                            @case('returns')<path d="M3 12a9 9 0 1 0 3-6.7" /><path d="M3 4v5h5" />@break
                            @case('messages')<path d="M20 15a4 4 0 0 1-4 4H8l-4 3V7a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4z" />@break
                            @case('reviews')<path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z" />@break
                            @case('fees')<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z" /><path d="M9 8h6M9 12h6M9 16h3" />@break
                            @case('earnings')<rect x="3" y="5" width="18" height="14" rx="2" /><path d="M3 10h18M7 15h3" />@break
                            @case('storefront')<path d="M4 10v10h16V10M3 10l2-6h14l2 6M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0" />@break
                            @case('settings')<circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z" />@break
                        @endswitch
                    </svg>
                    <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                    @if ($item['viewOnly'] ?? false)<span class="workspace-nav-note">View only</span>@endif
                    @if (($item['count'] ?? 0) > 0)<span class="workspace-nav-count" aria-label="{{ number_format($item['count']) }} {{ $item['countLabel'] }}">{{ number_format($item['count']) }}</span>@endif
                </a>
            @endforeach
        </div>
    @endforeach
</nav>
