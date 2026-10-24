<aside data-workspace-header="customer" class="sticky top-0 hidden h-screen self-start flex-col overflow-y-auto border-r border-slate-800 bg-[#111c32] text-slate-300 lg:flex" aria-label="Customer account sidebar">
    <div class="px-5 pb-5 pt-6">
        <a href="{{ route('orders.index') }}" class="inline-flex items-center gap-3 rounded-lg focus-visible:outline-white" aria-label="JMMart My account">
            <img src="{{ asset('favicon.svg') }}" alt="" class="size-9 rounded-lg" width="36" height="36">
            <span><strong class="block text-base leading-5 text-white">JMMart</strong><span class="block text-xs font-semibold uppercase tracking-[.16em] text-slate-400">My account</span></span>
        </a>
    </div>

    <div class="mx-3 flex min-w-0 items-center gap-3 rounded-lg border border-white/10 bg-white/5 p-3">
        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-white/10 text-sm font-semibold text-white" aria-hidden="true">{{ Str::upper(Str::substr(auth()->user()->name, 0, 1)) }}</span>
        <div class="min-w-0"><p class="truncate text-sm font-semibold text-white">{{ auth()->user()->name }}</p><p class="mt-0.5 text-xs text-slate-300">Customer account</p></div>
    </div>

    <x-marketplace.customer-nav-links />

    <div class="border-t border-white/10 p-3">
        <button type="button" data-clerk-sign-out class="group flex min-h-11 w-full items-center gap-3 rounded-lg px-3 text-left text-sm font-medium text-slate-300 transition hover:bg-white/10 hover:text-white focus-visible:ring-2 focus-visible:ring-white">
            <svg class="size-[18px] shrink-0 text-slate-400 group-hover:text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3" /><path d="M12 3h6a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3h-6" /></svg>
            <span>Sign out</span>
        </button>
    </div>
</aside>
