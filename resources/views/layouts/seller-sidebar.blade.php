<aside data-workspace-header="seller" class="sticky top-0 hidden h-screen self-start flex-col overflow-y-auto border-r border-line bg-surface px-2 py-3 lg:flex" aria-label="Seller Center sidebar">
    <div class="px-3 pb-5 pt-3">
        <a href="{{ route('seller.dashboard') }}" class="workspace-sidebar-brand" aria-label="JMMart Seller Center overview">
            <img src="{{ asset('favicon.svg') }}" alt="" class="size-8 rounded-lg" width="32" height="32">
            <span>
                <span class="workspace-sidebar-brand-name">JMMart</span>
                <span class="workspace-sidebar-brand-meta">Seller Center</span>
            </span>
        </a>
    </div>

    @php($storeStatus = $sellerTenant?->store?->status ?? 'unknown')
    <div class="mx-2 mb-2 workspace-sidebar-card">
        <span class="workspace-sidebar-avatar" aria-hidden="true">
            @if ($sellerTenant?->store?->logo_path)
                <img src="{{ Storage::disk('public')->url($sellerTenant->store->logo_path) }}" alt="" class="size-full object-cover">
            @else
                {{ Str::upper(Str::substr($sellerTenant?->name ?? 'S', 0, 1)) }}
            @endif
        </span>
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-ink">{{ $sellerTenant?->name ?? 'Your store' }}</p>
            <p class="mt-0.5 flex items-center gap-1.5 text-xs text-muted">
                <span @class(['size-1.5 rounded-full', 'bg-success' => $storeStatus === 'active', 'bg-danger' => $storeStatus === 'suspended', 'bg-muted' => ! in_array($storeStatus, ['active', 'suspended'], true)]) aria-hidden="true"></span>
                {{ ucfirst($storeStatus) }} store
            </p>
        </div>
    </div>

    <x-marketplace.seller-nav-links />

    <div class="mt-auto space-y-1 border-t border-line px-1 pt-3">
        <a href="{{ route('profile.edit') }}" class="workspace-nav-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M5 21a7 7 0 0 1 14 0" /></svg>
            <span>Account</span>
        </a>
        <button type="button" data-clerk-sign-out class="workspace-nav-link w-full text-left">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3" /><path d="M12 3h6a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3h-6" /></svg>
            <span>Sign out</span>
        </button>
    </div>
</aside>
