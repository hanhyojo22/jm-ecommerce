<aside data-workspace-header="seller" class="seller-studio-sidebar sticky top-0 hidden h-screen self-start flex-col border-r workspace-sidebar lg:flex" aria-label="Seller Center sidebar">
    <div>
        <a href="{{ route('seller.dashboard') }}" class="workspace-sidebar-brand" aria-label="Everyday Pharmacy Seller Center overview">
            <span class="seller-studio-mark" aria-hidden="true">EP</span>
            <span class="min-w-0">
                <span class="workspace-sidebar-brand-name truncate">Everyday Pharmacy</span>
                <span class="workspace-sidebar-brand-meta">Seller Center</span>
            </span>
        </a>
    </div>

    <x-marketplace.seller-identity />

    <x-marketplace.seller-nav-links />

    <x-marketplace.seller-account-footer :seller-tenant="$sellerTenant ?? auth()->user()?->sellerTenant()" />
</aside>
