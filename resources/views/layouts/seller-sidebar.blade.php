<aside class="sticky top-0 hidden h-screen self-start flex-col overflow-y-auto border-r border-white/10 bg-[#0F172A] text-slate-300 lg:flex" aria-label="Seller Center sidebar">
    <div class="border-b border-white/10 px-4 py-5">
        <a href="{{ route('home') }}" class="flex min-h-11 items-center gap-3 rounded-lg px-2 text-sm font-semibold text-white transition hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-brand" aria-label="JMMart shopping">
            <x-application-logo class="size-8 shrink-0 fill-current text-white" />
            <span class="truncate text-white">JMMart</span>
        </a>
        <div class="mt-5 flex items-center gap-2 px-2">
            <p class="text-[11px] font-semibold uppercase tracking-[.14em] text-slate-400">Seller Center</p>
            <span class="inline-flex items-center gap-1 rounded-full bg-success/10 px-2 py-0.5 text-[11px] font-semibold text-success ring-1 ring-inset ring-success/20"><span class="size-1.5 rounded-full bg-success" aria-hidden="true"></span>Active</span>
        </div>
            <p class="mt-1 truncate px-2 text-sm font-semibold text-white">{{ $sellerTenant?->name ?? 'Your store' }}</p>
        <div class="mt-3 grid grid-cols-2 gap-1 rounded-[10px] bg-white/10 p-1 text-center text-[13px] font-semibold" role="group" aria-label="Workspace switcher">
            <a href="{{ route('home') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg text-slate-300 transition hover:bg-white/10 hover:text-white">Shopping</a>
            <span class="inline-flex min-h-10 items-center justify-center rounded-lg bg-white text-[#0F172A] shadow-sm" aria-current="page">Seller Center</span>
        </div>
    </div>

    <x-marketplace.seller-nav-links />

    <div class="space-y-1 border-t border-white/10 p-3">
        <a href="{{ route('home') }}" class="flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white focus-visible:ring-2 focus-visible:ring-brand">
            <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 3h2l2.4 12.1a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 2-1.6L21 7H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
            <span>Shopping</span>
        </a>
        @if ($sellerTenant)
            <a href="{{ route('catalog.store', $sellerTenant->slug) }}" target="_blank" rel="noopener noreferrer" class="flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white focus-visible:ring-2 focus-visible:ring-brand" aria-label="View {{ $sellerTenant->name }} storefront in a new tab">
                <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9" /><path d="M18 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5" /></svg>
                <span>View storefront</span>
            </a>
        @endif
        <a href="{{ route('profile.edit') }}" class="flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium text-slate-300 transition hover:bg-white/10 hover:text-white focus-visible:ring-2 focus-visible:ring-brand">
            <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M5 21a7 7 0 0 1 14 0" /></svg>
            <span>Account</span>
        </a>
        <button type="button" data-clerk-sign-out class="flex min-h-11 w-full items-center gap-3 rounded-lg px-3 text-left text-sm font-medium text-slate-300 transition hover:bg-white/10 hover:text-white focus-visible:ring-2 focus-visible:ring-brand">
            <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3" /><path d="M12 3h6a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3h-6" /></svg>
            <span>Sign out</span>
        </button>
    </div>
</aside>
