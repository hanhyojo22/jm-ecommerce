@php
    $groups = [
        [
            'label' => 'Workspace',
            'items' => [
                ['label' => 'Overview', 'href' => route('dashboard'), 'icon' => 'overview', 'active' => request()->routeIs('dashboard')],
            ],
        ],
        [
            'label' => 'Marketplace',
            'items' => [
                ['label' => 'Orders', 'href' => route('admin.orders.index'), 'icon' => 'orders', 'active' => request()->routeIs('admin.orders.*')],
                ['label' => 'Order requests', 'href' => route('admin.order-issues.index'), 'icon' => 'reports', 'active' => request()->routeIs('admin.order-issues.*')],
                ['label' => 'Returns', 'href' => route('admin.returns.index'), 'icon' => 'reports', 'active' => request()->routeIs('admin.returns.*')],
                ['label' => 'Products', 'href' => route('admin.products.index'), 'icon' => 'products', 'active' => request()->routeIs('admin.products.*')],
                ['label' => 'Stores', 'href' => route('admin.stores.index'), 'icon' => 'stores', 'active' => request()->routeIs('admin.stores.*')],
            ],
        ],
        [
            'label' => 'People',
            'items' => [
                ['label' => 'Seller applications', 'href' => route('admin.seller-applications.index'), 'icon' => 'applications', 'active' => request()->routeIs('admin.seller-applications.*')],
                ['label' => 'Customers', 'href' => route('admin.customers.index'), 'icon' => 'customers', 'active' => request()->routeIs('admin.customers.*')],
                ['label' => 'Admin accounts', 'href' => route('admin.accounts.index'), 'icon' => 'accounts', 'active' => request()->routeIs('admin.accounts.*')],
            ],
        ],
        [
            'label' => 'Moderation',
            'items' => [
                ['label' => 'Moderation', 'href' => route('admin.moderation'), 'icon' => 'reports', 'active' => request()->routeIs('admin.moderation')],
                ['label' => 'Reviews', 'href' => route('admin.reviews.index'), 'icon' => 'reviews', 'active' => request()->routeIs('admin.reviews.*')],
                ['label' => 'Chat reports', 'href' => route('admin.chat-reports.index'), 'icon' => 'reports', 'active' => request()->routeIs('admin.chat-reports.*')],
            ],
        ],
        [
            'label' => 'Operations',
            'items' => [
                ['label' => 'Payouts', 'href' => route('admin.payouts.index'), 'icon' => 'payouts', 'active' => request()->routeIs('admin.payouts.*')],
                ['label' => 'Notifications', 'href' => route('admin.announcements.create'), 'icon' => 'announcements', 'active' => request()->routeIs('admin.announcements.*')],
                ['label' => 'Platform fees', 'href' => route('admin.platform-fees.index'), 'icon' => 'fees', 'active' => request()->routeIs('admin.platform-fees.*')],
                ['label' => 'Audit log', 'href' => route('admin.audit-logs.index'), 'icon' => 'audit', 'active' => request()->routeIs('admin.audit-logs.*')],
            ],
        ],
    ];
@endphp

<div class="contents" x-data="{
    visible: window.innerWidth >= 1024,
    trigger: null,
    toggle(event) {
        this.trigger = event.detail.trigger;
        this.visible = event.detail.visible;
        if (this.visible) {
            this.$nextTick(() => this.$refs.sidebar.querySelector('a')?.focus());
        } else {
            this.$nextTick(() => this.trigger?.focus());
        }
    },
    close() {
        this.visible = false;
        this.$dispatch('admin-navigation-close');
        this.$nextTick(() => this.trigger?.focus());
    },
}"
    @admin-navigation-toggle.window="toggle($event)"
    @resize.window="visible = window.innerWidth >= 1024"
    @keydown.escape.window="if (visible && window.innerWidth < 1024) close()"
>
    <aside id="admin-workspace-nav" data-workspace-header="admin" x-ref="sidebar" aria-label="Admin workspace navigation" :class="visible ? 'block lg:sticky lg:top-0 lg:h-screen lg:w-auto' : 'hidden lg:sticky lg:top-0 lg:block lg:h-screen lg:w-auto'" class="overflow-y-auto border-r workspace-sidebar px-2 py-3">
        <div class="flex min-h-full flex-col">
            <div class="px-3 pb-5 pt-3">
                <p class="workspace-sidebar-brand-name">Administration</p>
                <p class="workspace-sidebar-brand-meta">Protected workspace</p>
            </div>

            <nav class="flex-1 space-y-5 px-1 py-4" aria-label="Admin sections">
                @foreach ($groups as $group)
                    <div>
                        <h2 class="workspace-nav-label">{{ $group['label'] }}</h2>
                        <div class="space-y-1">
                            @foreach ($group['items'] as $item)
                                @if ($item['icon'] !== 'accounts' || auth()->user()?->isOwner())
                                    <a href="{{ $item['href'] }}" class="workspace-nav-link" {{ $item['active'] ? 'aria-current=page' : '' }}>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            @switch($item['icon'])
                                                @case('overview')<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z" />@break
                                                @case('orders')<path d="M3 4h2l2.5 12h10L21 8H6" /><circle cx="9" cy="20" r="1" /><circle cx="18" cy="20" r="1" />@break
                                                @case('products')<path d="m12 3 9 5v8l-9 5-9-5V8z" /><path d="m3 8 9 5 9-5M12 13v8" />@break
                                                @case('stores')<path d="M4 10v10h16V10M3 10l2-6h14l2 6M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0" />@break
                                                @case('applications')<path d="M8 3h7l4 4v14H5V3z" /><path d="M15 3v5h5M8 12h8M8 16h6" />@break
                                                @case('customers')<circle cx="9" cy="8" r="3" /><path d="M3.5 20v-1a5.5 5.5 0 0 1 11 0v1zM16 5.5a3 3 0 0 1 0 5.8M17 14a5 5 0 0 1 3.5 4.8v1" />@break
                                                @case('accounts')<path d="M12 3 5 6v5c0 4.6 2.8 8 7 10 4.2-2 7-5.4 7-10V6z" /><circle cx="12" cy="10" r="2" /><path d="M8.5 16a3.5 3.5 0 0 1 7 0" />@break
                                                @case('reviews')<path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z" />@break
                                                @case('reports')<path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H5l-2 2v-9.5A7.5 7.5 0 0 1 10.5 4h2A7.5 7.5 0 0 1 20 11.5Z" />@break
                                                @case('fees')<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z" /><path d="M9 8h6M9 12h6M9 16h3" />@break
                                                @case('payouts')<path d="M12 2v20M17 6.5c-.7-1.2-2.1-2-4.2-2-2.4 0-4 1.2-4 3s1.6 2.7 4.2 3.3 4.2 1.4 4.2 3.4-1.8 3.6-4.5 3.6c-2.2 0-3.8-.8-4.8-2.2" />@break
                                                @case('announcements')<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />@break
                                                @case('audit')<circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />@break
                                            @endswitch
                                        </svg>
                                        <span class="min-w-0 truncate">{{ $item['label'] }}</span>
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>

            <div class="mt-auto border-t border-line px-1 pt-3">
                <a href="{{ route('home') }}" class="workspace-nav-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6M9 12h12" /><path d="M3 4v16" /></svg>
                    <span>View marketplace</span>
                </a>
            </div>
        </div>
    </aside>
</div>
