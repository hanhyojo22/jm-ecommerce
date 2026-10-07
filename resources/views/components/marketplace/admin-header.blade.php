@props(['user'])

<header class="sticky top-0 z-40 border-b border-line bg-surface">
    <div class="mx-auto flex h-[72px] max-w-[1320px] items-center gap-3 px-[18px] sm:gap-4 sm:px-8">
        <button type="button" x-data="{ open: false }" @click="open = !open; $dispatch('admin-navigation-toggle', { open, trigger: $event.currentTarget })" @admin-navigation-close.window="open = false" :aria-expanded="open.toString()" aria-controls="admin-workspace-nav" :aria-label="open ? 'Close admin navigation' : 'Open admin navigation'" class="inline-flex size-11 shrink-0 items-center justify-center rounded-lg text-muted transition-colors hover:bg-canvas hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 lg:hidden">
            <svg x-show="!open" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
            <svg x-cloak x-show="open" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg>
        </button>

        <a href="{{ route('dashboard') }}" class="flex shrink-0 items-center gap-2.5 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2" aria-label="JMMart admin overview">
            <x-application-logo class="size-9 fill-current text-brand" />
            <span class="text-lg font-bold tracking-[-0.03em] text-ink">JMMart</span>
        </a>

        <span class="hidden h-8 w-px bg-line sm:block" aria-hidden="true"></span>
        <div class="hidden min-w-0 sm:block">
            <p class="truncate text-sm font-semibold text-ink">Administration</p>
            <p class="text-xs text-muted">Protected workspace</p>
        </div>

        <form method="GET" action="{{ route('admin.orders.index') }}" x-data="{ scope: 'orders', query: '' }" @submit="if (!query.trim()) { $event.preventDefault(); }" :action="{ orders: @js(route('admin.orders.index')), products: @js(route('admin.products.index')), stores: @js(route('admin.stores.index')), customers: @js(route('admin.customers.index')) }[scope]" class="ml-auto hidden min-w-0 flex-1 max-w-md items-center gap-2 md:flex" role="search" aria-label="Global admin search">
            <label class="flex h-11 min-w-0 flex-1 items-center gap-2 rounded-[10px] border border-line bg-canvas px-3 text-muted transition focus-within:border-brand focus-within:bg-white focus-within:ring-4 focus-within:ring-brand/10">
                <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg>
                <span class="sr-only">Search admin records</span>
                <input name="search" x-model="query" type="search" placeholder="Search orders, products, stores…" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-ink placeholder:text-muted focus:ring-0">
                <select x-model="scope" aria-label="Search scope" class="shrink-0 border-0 bg-transparent py-0 pl-1 pr-6 text-xs font-semibold text-muted focus:ring-0">
                    <option value="orders">Orders</option>
                    <option value="products">Products</option>
                    <option value="stores">Stores</option>
                    <option value="customers">Customers</option>
                </select>
            </label>
        </form>

        <div class="ml-auto flex shrink-0 items-center gap-2 sm:gap-3 md:ml-0">
            <a href="{{ route('home') }}" class="hidden min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-brand transition-colors hover:bg-brand/5 hover:text-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 sm:inline-flex">View marketplace</a>

            <details class="group relative">
                <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-lg px-2 text-sm font-semibold text-ink transition-colors hover:bg-canvas focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 [&::-webkit-details-marker]:hidden">
                    <span class="grid size-9 place-items-center rounded-full bg-blue-100 text-xs font-semibold text-blue-800">{{ Str::upper(Str::substr($user->name, 0, 1)) }}</span>
                    <span class="hidden max-w-36 truncate sm:inline">{{ $user->name }}</span>
                    <svg class="size-4 text-muted transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                </summary>
                <div class="absolute right-0 z-50 mt-2 w-56 rounded-xl border border-line bg-surface p-2 shadow-lg shadow-ink/10">
                    <a href="{{ route('home') }}" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-ink transition-colors hover:bg-canvas focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand">View marketplace</a>
                    <a href="{{ route('profile.edit') }}" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-ink transition-colors hover:bg-canvas focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand">Account settings</a>
                    <button type="button" data-clerk-sign-out class="flex min-h-11 w-full items-center rounded-lg px-3 text-left text-sm font-medium text-muted transition-colors hover:bg-danger/10 hover:text-danger focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand">Sign out</button>
                </div>
            </details>
        </div>
    </div>
</header>
