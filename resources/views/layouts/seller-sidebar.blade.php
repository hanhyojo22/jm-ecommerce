<aside data-workspace-header="seller" class="seller-studio-sidebar sticky top-0 hidden h-screen self-start flex-col border-r workspace-sidebar lg:flex" aria-label="Seller Center sidebar">
    <div>
        <a href="{{ route('seller.dashboard') }}" class="workspace-sidebar-brand" aria-label="JMMart Seller Center overview">
            <span class="seller-studio-mark" aria-hidden="true">JM</span>
            <span>
                <span class="workspace-sidebar-brand-name">JMMart</span>
                <span class="workspace-sidebar-brand-meta">Seller Center</span>
            </span>
        </a>
    </div>

    <x-marketplace.seller-identity />

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
        <a href="{{ route('profile.edit') }}" class="seller-studio-person">
            <span class="seller-studio-avatar" aria-hidden="true">{{ Str::upper(Str::substr(auth()->user()->name, 0, 1)) }}</span>
            <span class="min-w-0"><strong class="truncate">{{ auth()->user()->name }}</strong><small>{{ auth()->user()->sellerMembership()?->roleLabel() }}{{ auth()->user()->sellerMembership()?->is_pharmacist ? ' · Pharmacist' : '' }}</small></span>
        </a>
    </div>
</aside>
