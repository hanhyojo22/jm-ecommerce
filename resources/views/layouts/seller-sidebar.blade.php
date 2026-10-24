<aside data-workspace-header="seller" class="sticky top-0 hidden h-screen self-start flex-col overflow-y-auto border-r border-slate-800 bg-[#111c32] px-2 py-3 text-slate-300 lg:flex" aria-label="Seller Center sidebar">
    <div class="px-3 pb-5 pt-3">
        <a href="{{ route('seller.dashboard') }}" class="inline-flex items-center gap-3 rounded-lg focus-visible:ring-2 focus-visible:ring-white" aria-label="JMMart Seller Center overview">
            <img src="{{ asset('favicon.svg') }}" alt="" class="size-8 rounded-lg" width="32" height="32">
            <span><strong class="block text-[15px] leading-5 text-white">JMMart</strong><span class="block text-xs font-medium text-slate-400">Seller Center</span></span>
        </a>
    </div>

    @php($storeStatus = $sellerTenant?->store?->status ?? 'unknown')
    <div class="mx-2 mb-2 rounded-lg border border-white/10 bg-white/[.04] p-3">
        <div class="flex min-w-0 items-center gap-3">
            <span class="grid size-9 shrink-0 place-items-center overflow-hidden rounded-lg bg-white/10 text-sm font-semibold text-white" aria-hidden="true">
                @if ($sellerTenant?->store?->logo_path)
                    <img src="{{ Storage::disk('public')->url($sellerTenant->store->logo_path) }}" alt="" class="size-full object-cover">
                @else
                    {{ Str::upper(Str::substr($sellerTenant?->name ?? 'S', 0, 1)) }}
                @endif
            </span>
            <div class="min-w-0"><p class="truncate text-sm font-semibold text-white">{{ $sellerTenant?->name ?? 'Your store' }}</p><p class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-300"><span @class(['size-1.5 rounded-full', 'bg-emerald-400' => $storeStatus === 'active', 'bg-rose-400' => $storeStatus === 'suspended', 'bg-slate-400' => ! in_array($storeStatus, ['active', 'suspended'], true)]) aria-hidden="true"></span>{{ ucfirst($storeStatus) }} store</p></div>
        </div>
    </div>

    <x-marketplace.seller-nav-links />

    <div class="mt-auto space-y-1 border-t border-white/10 px-1 pt-3">
        <a href="{{ route('profile.edit') }}" class="flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium text-slate-300 transition hover:bg-white/5 hover:text-white focus-visible:ring-2 focus-visible:ring-white">
            <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M5 21a7 7 0 0 1 14 0" /></svg>
            <span>Account</span>
        </a>
        <button type="button" data-clerk-sign-out class="flex min-h-11 w-full items-center gap-3 rounded-lg px-3 text-left text-sm font-medium text-slate-300 transition hover:bg-white/5 hover:text-white focus-visible:ring-2 focus-visible:ring-white">
            <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3" /><path d="M12 3h6a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3h-6" /></svg>
            <span>Sign out</span>
        </button>
    </div>
</aside>
