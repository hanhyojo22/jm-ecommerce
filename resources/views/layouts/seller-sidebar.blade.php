<aside data-workspace-header="seller" class="sticky top-0 hidden h-screen self-start flex-col overflow-y-auto border-r border-slate-800 bg-[#111c32] text-slate-300 lg:flex" aria-label="Seller Center sidebar">
    <div class="px-5 pb-5 pt-6">
        <a href="{{ route('seller.dashboard') }}" class="inline-flex items-center gap-3 rounded-lg focus-visible:outline-white" aria-label="JMMart Seller Center overview">
            <span class="grid size-9 place-items-center rounded-xl bg-brand text-lg font-bold text-white">J</span>
            <span><strong class="block text-base leading-5 text-white">JMMart</strong><span class="block text-[10px] font-semibold uppercase tracking-[.16em] text-slate-400">Seller Center</span></span>
        </a>
    </div>

    @php($storeStatus = $sellerTenant?->store?->status ?? 'unknown')
    <div class="mx-3 rounded-xl border border-white/10 bg-white/5 p-3">
        <div class="flex min-w-0 items-center gap-3">
            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-white/10 text-sm font-semibold text-white" aria-hidden="true">{{ Str::upper(Str::substr($sellerTenant?->name ?? 'S', 0, 1)) }}</span>
            <div class="min-w-0"><p class="truncate text-sm font-semibold text-white">{{ $sellerTenant?->name ?? 'Your store' }}</p><p class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-300"><span @class(['size-1.5 rounded-full', 'bg-emerald-400' => $storeStatus === 'active', 'bg-rose-400' => $storeStatus === 'suspended', 'bg-slate-400' => ! in_array($storeStatus, ['active', 'suspended'], true)]) aria-hidden="true"></span>{{ ucfirst($storeStatus) }} store</p></div>
        </div>
    </div>

    <x-marketplace.seller-nav-links />

    <div class="space-y-1 border-t border-white/10 p-3">
        <a href="{{ route('home') }}" class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium text-slate-300 transition hover:bg-white/10 hover:text-white focus-visible:ring-2 focus-visible:ring-white">
            <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 3h2l2.4 12.1a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 2-1.6L21 7H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
            <span>Shopping</span>
        </a>
        @if ($sellerTenant)
            <a href="{{ route('catalog.store', $sellerTenant->slug) }}" target="_blank" rel="noopener noreferrer" class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium text-slate-300 transition hover:bg-white/10 hover:text-white focus-visible:ring-2 focus-visible:ring-white" aria-label="View {{ $sellerTenant->name }} storefront in a new tab">
                <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9" /><path d="M18 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5" /></svg>
                <span>View storefront</span>
            </a>
        @endif
        <a href="{{ route('profile.edit') }}" class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium text-slate-300 transition hover:bg-white/10 hover:text-white focus-visible:ring-2 focus-visible:ring-white">
            <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M5 21a7 7 0 0 1 14 0" /></svg>
            <span>Account</span>
        </a>
        <button type="button" data-clerk-sign-out class="flex min-h-11 w-full items-center gap-3 rounded-xl px-3 text-left text-sm font-medium text-slate-300 transition hover:bg-white/10 hover:text-white focus-visible:ring-2 focus-visible:ring-white">
            <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3" /><path d="M12 3h6a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3h-6" /></svg>
            <span>Sign out</span>
        </button>
    </div>
</aside>
