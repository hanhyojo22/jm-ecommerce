<aside data-workspace-header="customer" class="sticky top-0 hidden h-screen self-start flex-col overflow-y-auto border-r border-line bg-surface px-2 py-3 lg:flex" aria-label="My account sidebar">
    <div class="px-3 pb-5 pt-3">
        <a href="{{ route('orders.index') }}" class="workspace-sidebar-brand" aria-label="JMMart My account">
            <img src="{{ asset('favicon.svg') }}" alt="" class="size-8 rounded-lg" width="32" height="32">
            <span>
                <span class="workspace-sidebar-brand-name">JMMart</span>
                <span class="workspace-sidebar-brand-meta">My account</span>
            </span>
        </a>
    </div>

    <div class="mx-2 mb-2 workspace-sidebar-card">
        <span class="workspace-sidebar-avatar" aria-hidden="true">{{ Str::upper(Str::substr(auth()->user()->name, 0, 1)) }}</span>
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-ink">{{ auth()->user()->name }}</p>
            <p class="mt-0.5 text-xs text-muted">Customer account</p>
        </div>
    </div>

    <x-marketplace.customer-nav-links />

    <div class="border-t border-line px-1 py-3">
        <button type="button" data-clerk-sign-out class="workspace-nav-link w-full text-left">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3" /><path d="M12 3h6a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3h-6" /></svg>
            <span>Sign out</span>
        </button>
    </div>
</aside>
