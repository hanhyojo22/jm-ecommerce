@php
    $items = [
        ['label' => 'Overview', 'href' => route('dashboard'), 'icon' => 'overview', 'active' => request()->routeIs('dashboard')],
        ['label' => 'Seller applications', 'href' => route('admin.seller-applications.index'), 'icon' => 'application', 'active' => request()->routeIs('admin.seller-applications.*')],
        ['label' => 'Products', 'href' => route('admin.products.index'), 'icon' => 'box', 'active' => request()->routeIs('admin.products.*')],
        ['label' => 'Orders', 'href' => route('admin.orders.index'), 'icon' => 'orders', 'active' => request()->routeIs('admin.orders.*')],
        ['label' => 'Audit log', 'href' => route('admin.audit-logs.index'), 'icon' => 'audit', 'active' => request()->routeIs('admin.audit-logs.*')],
    ];
@endphp

<aside class="sticky top-[72px] hidden h-[calc(100vh-72px)] self-start overflow-y-auto border-r border-slate-200 bg-white lg:block" aria-label="Admin workspace navigation">
    <nav class="space-y-1 px-3 py-5">
        @foreach ($items as $item)
            <a href="{{ $item['href'] }}" @class([
                'group flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2',
                'bg-emerald-50 font-semibold text-emerald-950' => $item['active'],
                'text-slate-700 hover:bg-slate-50 hover:text-slate-950' => ! $item['active'],
            ]) {{ $item['active'] ? 'aria-current=page' : '' }}>
                <svg class="size-5 shrink-0 {{ $item['active'] ? 'text-emerald-800' : 'text-slate-500 group-hover:text-slate-700' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    @switch($item['icon'])
                        @case('overview')<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z" />@break
                        @case('application')<path d="M8 3h7l4 4v14H5V3z" /><path d="M15 3v5h5M8 12h8M8 16h8" />@break
                        @case('box')<path d="m12 3 9 5v8l-9 5-9-5V8z" /><path d="m3 8 9 5 9-5M12 13v8M7.5 5.5l9 5" />@break
                        @case('orders')<path d="M3 4h2l2.5 12h10L21 8H6" /><circle cx="9" cy="20" r="1" /><circle cx="18" cy="20" r="1" />@break
                        @case('audit')<circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />@break
                    @endswitch
                </svg>
                <span class="min-w-0 truncate">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>
</aside>

<nav class="-mx-4 mb-6 flex gap-1 overflow-x-auto border-b border-slate-200 bg-white px-4 py-2.5 sm:-mx-6 sm:px-6 lg:hidden" aria-label="Admin workspace navigation">
    @foreach ($items as $item)
        <a href="{{ $item['href'] }}" @class([
            'min-h-10 shrink-0 rounded-lg px-3 py-2 text-sm font-semibold focus-visible:ring-2 focus-visible:ring-emerald-700',
            'bg-emerald-50 text-emerald-900' => $item['active'],
            'text-slate-600 hover:bg-slate-50 hover:text-slate-950' => ! $item['active'],
        ]) {{ $item['active'] ? 'aria-current=page' : '' }}>{{ $item['label'] }}</a>
    @endforeach
</nav>
