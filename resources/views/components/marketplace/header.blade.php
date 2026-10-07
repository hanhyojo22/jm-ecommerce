@props(['search' => ''])

@php
    $user = auth()->user();
    $isBuyer = $user?->account_type === 'buyer';
    $canUseMessages = $user && in_array($user->account_type, ['buyer', 'seller'], true);
    $cartBadge = $cartItemCount > 99 ? '99+' : $cartItemCount;
    $messageBadge = $unreadConversationCount > 99 ? '99+' : $unreadConversationCount;
    $sellerEntry = $sellerTenant ? 'Seller Center' : match ($sellerApplication?->status) {
        'pending' => 'Seller application',
        'rejected' => 'Update seller application',
        default => 'Start selling',
    };
    $isSellerWorkspace = $user?->account_type === 'seller' && request()->routeIs('seller.dashboard', 'seller.products.*', 'seller.orders.*', 'seller.reviews.*', 'seller.earnings.*', 'messages.*');
@endphp

@if ($user?->isPlatformAdministrator() && request()->routeIs('dashboard', 'admin.*'))
    <x-marketplace.admin-header :user="$user" />
@else
    <header
        x-data="{
            accountOpen: false,
            drawerOpen: false,
            menuButton: null,
            closeDrawer() { this.drawerOpen = false; this.$nextTick(() => this.menuButton?.focus()) },
            toggleDrawer(event) { this.menuButton = event.currentTarget; this.drawerOpen = !this.drawerOpen; if (this.drawerOpen) this.$nextTick(() => this.$refs.drawer.querySelector('[data-drawer-focus]')?.focus()) },
            trap(event) { const items = [...this.$refs.drawer.querySelectorAll('a,button,input')].filter((item) => !item.disabled && item.offsetParent); const first = items[0], last = items.at(-1); if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus() } else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus() } },
        }"
        x-init="$watch('drawerOpen', (open) => document.body.classList.toggle('overflow-hidden', open))"
        @keydown.escape.window="if (drawerOpen) closeDrawer(); accountOpen = false"
        class="sticky top-0 z-40 border-b border-line bg-white"
    >
        <div class="mx-auto grid h-[52px] max-w-[1320px] grid-cols-[1fr_auto] items-center gap-3 px-[18px] min-[641px]:h-[70px] min-[641px]:grid-cols-[auto_minmax(12rem,1fr)_auto] {{ $isSellerWorkspace ? 'min-[641px]:gap-6' : 'min-[641px]:gap-[18px]' }} min-[1021px]:grid-cols-[auto_minmax(15rem,1fr)_auto] min-[1021px]:px-0">
            <div class="flex min-w-0 items-center gap-2 min-[1021px]:mr-5 min-[1021px]:gap-5">
                <button type="button" @click="toggleDrawer($event)" :aria-expanded="drawerOpen.toString()" aria-controls="marketplace-drawer" class="{{ $isSellerWorkspace ? 'lg:hidden' : 'min-[641px]:hidden' }} grid size-11 shrink-0 place-items-center rounded-[10px] text-muted transition hover:bg-blue-50 hover:text-brand focus-visible:ring-2 focus-visible:ring-brand" aria-label="Open navigation menu">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" /></svg>
                </button>
                <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5 rounded-lg focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2" aria-label="JMMart marketplace home">
                    <x-application-logo class="size-9 fill-current text-brand" />
                    <span class="text-lg font-bold tracking-[-0.025em] text-ink">JMMart</span>
                </a>

                @if ($isSellerWorkspace)
                    <span class="hidden h-8 w-px bg-line sm:block" aria-hidden="true"></span>
                    <div class="hidden min-w-0 flex-col gap-0.5 sm:flex">
                        <span class="text-[10px] font-semibold uppercase tracking-[.12em] text-muted">Seller Center</span>
                        <strong class="max-w-64 truncate text-sm font-semibold text-ink">{{ $sellerTenant?->name ?? 'Your store' }}</strong>
                    </div>
                @endif
            </div>

            @if (! $isSellerWorkspace)
                <form method="GET" action="{{ route('catalog.index') }}" x-data="{ query: @js($search), loading: false }" @submit="loading = true" class="hidden min-w-0 min-[641px]:block min-[1021px]:ml-1">
                    <label for="marketplace-search-desktop" class="flex h-11 items-center gap-3 rounded-[10px] border border-line bg-canvas px-3.5 text-muted transition focus-within:border-brand focus-within:bg-white focus-within:ring-4 focus-within:ring-brand/10">
                        <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg>
                        <input id="marketplace-search-desktop" name="q" aria-label="Search products, brands, and stores" x-model="query" value="{{ $search }}" autocomplete="off" placeholder="Search products, brands, and stores" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-ink placeholder:text-muted focus:ring-0">
                        <button x-cloak x-show="query.length" type="button" @click="query = ''; $nextTick(() => $el.previousElementSibling.focus())" class="grid size-7 place-items-center rounded-md transition hover:bg-canvas" aria-label="Clear search"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg></button>
                        <button type="submit" class="rounded-[8px] bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover">Search</button>
                        <span x-cloak x-show="loading" class="text-xs font-semibold text-brand">Searching…</span>
                    </label>
                </form>
            @endif

            <div class="flex shrink-0 items-center justify-end gap-1 sm:gap-2 min-[641px]:gap-[18px]">
                @if ($isSellerWorkspace && $sellerTenant)
                    <a href="{{ route('catalog.store', $sellerTenant->slug) }}" target="_blank" rel="noopener noreferrer" class="hidden min-h-10 items-center rounded-[10px] border border-line bg-white px-4 text-sm font-semibold text-ink transition hover:bg-canvas focus-visible:ring-2 focus-visible:ring-brand sm:inline-flex">View storefront</a>
                @elseif ($canUseMessages && ! request()->routeIs('catalog.index'))
                    <a href="{{ route('messages.index') }}" class="relative grid size-11 place-items-center rounded-[10px] text-muted transition hover:bg-blue-50 hover:text-brand focus-visible:ring-2 focus-visible:ring-brand" aria-label="Messages{{ $unreadConversationCount ? ', '.$unreadConversationCount.' unread' : '' }}">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linejoin="round" d="M20 15a4 4 0 0 1-4 4H8l-4 3V7a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4z" /></svg>
                        @if ($unreadConversationCount > 0)
                            <span class="absolute right-0 top-0 inline-flex min-w-5 translate-x-1/4 -translate-y-1/4 items-center justify-center rounded-full bg-brand px-1 py-0.5 text-[10px] font-bold leading-none text-white ring-2 ring-white">{{ $messageBadge }}</span>
                        @endif
                    </a>
                @endif

                @if ($isBuyer)
                    <a href="{{ route('cart.index') }}" class="relative order-2 inline-flex min-h-11 items-center justify-center gap-2 rounded-[10px] px-3 text-sm font-semibold text-ink transition hover:bg-blue-50 hover:text-brand focus-visible:ring-2 focus-visible:ring-brand min-[641px]:order-2" aria-label="Cart: {{ $cartItemCount }} {{ Str::plural('item', $cartItemCount) }}">
                        <svg class="size-5 min-[641px]:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l2.4 12.1a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 2-1.6L21 7H6" /><circle cx="10" cy="20" r="1" /><circle cx="18" cy="20" r="1" /></svg>
                        <span class="hidden min-[641px]:inline">Cart ({{ $cartItemCount }})</span>
                        @if ($cartItemCount > 0)
                            <span class="absolute right-0 top-0 inline-flex min-w-5 translate-x-1/4 -translate-y-1/4 items-center justify-center rounded-full bg-brand px-1 py-0.5 text-[10px] font-bold leading-none text-white ring-2 ring-white min-[641px]:hidden">{{ $cartBadge }}</span>
                        @endif
                    </a>
                @endif

                @auth
                    <div class="relative min-[641px]:order-1" @click.outside="accountOpen = false">
                        <button type="button" @click="accountOpen = !accountOpen" :aria-expanded="accountOpen.toString()" aria-haspopup="menu" aria-controls="header-account-menu" class="flex min-h-11 max-w-48 items-center gap-2 rounded-[10px] px-3 text-left text-ink transition hover:bg-blue-50 focus-visible:ring-2 focus-visible:ring-brand {{ $isSellerWorkspace ? '' : 'min-[641px]:px-0' }}">
                            <span class="hidden text-sm font-semibold min-[641px]:inline">Account</span>
                            <span class="grid size-8 shrink-0 place-items-center rounded-full bg-blue-100 text-xs font-semibold text-blue-800 min-[641px]:hidden">{{ Str::upper(Str::substr($user->name, 0, 1)) }}</span>
                        </button>
                        <div id="header-account-menu" x-cloak x-show="accountOpen" x-transition.origin.top.right role="menu" class="absolute right-0 z-50 mt-2 w-72 overflow-hidden rounded-[14px] border border-line bg-white p-2 shadow-[0_12px_28px_rgba(15,23,42,.10)]">
                            <div class="min-w-0 border-b border-line px-3 py-3"><p class="truncate text-sm font-semibold text-ink">{{ $user->name }}</p><p class="truncate text-xs text-muted">{{ $user->email }}</p></div>
                            <div class="space-y-1 py-2">
                                <a href="{{ route('profile.edit') }}" role="menuitem" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-ink transition hover:bg-canvas">My account</a>
                                @if ($isBuyer)
                                    <a href="{{ route('orders.index') }}" role="menuitem" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-ink transition hover:bg-canvas">Orders</a>
                                @endif
                                @if ($canUseMessages)
                                    <a href="{{ route('messages.index') }}" role="menuitem" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-ink transition hover:bg-canvas">Messages</a>
                                @endif
                                @if ($user->account_type === 'seller')
                                    <a href="{{ $sellerTenant ? route('seller.dashboard') : route('seller-application.create') }}" role="menuitem" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-ink transition hover:bg-canvas">{{ $sellerEntry }}</a>
                                @endif
                                @if ($user->isPlatformAdministrator())
                                    <a href="{{ route('dashboard') }}" role="menuitem" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-ink transition hover:bg-canvas">Admin workspace</a>
                                @endif
                            </div>
                            <button type="button" data-clerk-sign-out role="menuitem" class="flex min-h-11 w-full items-center rounded-lg border-t border-line px-3 pt-2 text-left text-sm font-medium text-muted transition hover:bg-rose-50 hover:text-rose-800">Sign out</button>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login', ['redirect' => '/cart']) }}" class="order-2 inline-flex min-h-11 items-center justify-center gap-2 rounded-[10px] px-3 text-sm font-semibold text-ink transition hover:bg-blue-50 hover:text-brand focus-visible:ring-2 focus-visible:ring-brand min-[641px]:order-2" aria-label="Sign in to view cart">
                        <svg class="size-5 min-[641px]:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l2.4 12.1a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 2-1.6L21 7H6" /><circle cx="10" cy="20" r="1" /><circle cx="18" cy="20" r="1" /></svg>
                        <span class="hidden min-[641px]:inline">Cart</span>
                    </a>
                    <a href="{{ route('login', ['redirect' => '/profile']) }}" class="order-1 hidden min-h-11 items-center rounded-[10px] px-3 text-sm font-semibold text-ink transition hover:bg-blue-50 hover:text-brand focus-visible:ring-2 focus-visible:ring-brand min-[641px]:inline-flex min-[641px]:order-1 min-[641px]:px-0">Account</a>
                @endauth
            </div>
        </div>



        <div x-cloak x-show="drawerOpen" x-transition.opacity class="fixed inset-0 z-50 bg-slate-950/35 lg:hidden">
            <button type="button" @click="closeDrawer" class="h-full w-full" tabindex="-1" aria-label="Close navigation menu"></button>
        </div>
        <aside id="marketplace-drawer" x-ref="drawer" x-cloak x-show="drawerOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" @keydown.tab="trap($event)" class="fixed inset-y-0 left-0 z-[60] flex w-[min(20rem,calc(100vw-2.5rem))] flex-col bg-white text-ink shadow-[0_16px_32px_rgba(15,23,42,.18)] lg:hidden" role="dialog" aria-modal="true" aria-label="{{ $isSellerWorkspace ? 'Seller Center navigation' : 'Marketplace navigation' }}">
            <div class="flex h-[52px] items-center justify-between border-b border-line px-5">
                <a href="{{ route('home') }}" data-drawer-focus @click="closeDrawer" class="flex items-center gap-2 font-semibold">
                    <x-application-logo class="size-8 fill-current text-brand" />
                    <span>JMMart</span>
                </a>
                <button type="button" @click="closeDrawer" class="grid size-11 place-items-center rounded-[10px] text-muted transition hover:bg-canvas hover:text-ink focus-visible:ring-2 focus-visible:ring-brand" aria-label="Close navigation menu"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg></button>
            </div>

            @if (! $isSellerWorkspace)
                <form method="GET" action="{{ route('catalog.index') }}" x-data="{ query: @js($search), loading: false }" @submit="loading = true" class="border-b border-line px-4 py-4">
                    <label for="marketplace-search-drawer" class="flex h-11 items-center gap-3 rounded-[10px] border border-line bg-canvas px-3.5 text-muted transition focus-within:border-brand focus-within:bg-white focus-within:ring-4 focus-within:ring-brand/10">
                        <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg>
                        <input id="marketplace-search-drawer" name="q" aria-label="Search products, brands, and stores" x-model="query" value="{{ $search }}" autocomplete="off" placeholder="Search products, brands, and stores" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-ink placeholder:text-muted focus:ring-0">
                        <button x-cloak x-show="query.length" type="button" @click="query = ''; $nextTick(() => $el.previousElementSibling.focus())" class="grid size-7 place-items-center rounded-md transition hover:bg-canvas" aria-label="Clear search"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg></button>
                    </label>
                </form>
            @endif

            @if ($isSellerWorkspace)
                <div class="border-b border-line px-4 py-4"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-muted">Seller Center</p><p class="mt-1 truncate text-sm font-semibold text-ink">{{ $sellerTenant?->name ?? 'Your store' }}</p></div>
                <x-marketplace.seller-nav-links mode="mobile" />
                <div class="space-y-1 border-t border-line p-4">
                    <a href="{{ route('home') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-ink transition hover:bg-canvas">Shopping</a>
                    @if ($sellerTenant)
                        <a href="{{ route('catalog.store', $sellerTenant->slug) }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-muted transition hover:bg-canvas hover:text-ink">View storefront</a>
                    @endif
                    <a href="{{ route('profile.edit') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-muted transition hover:bg-canvas hover:text-ink">Account</a>
                    <button type="button" data-clerk-sign-out @click="closeDrawer" class="flex min-h-11 w-full items-center rounded-lg px-3 text-left text-sm font-medium text-muted transition hover:bg-canvas hover:text-ink">Sign out</button>
                </div>
            @else
                <nav class="flex-1 space-y-1 overflow-y-auto p-4" aria-label="Mobile marketplace navigation">
                    <a href="{{ route('home') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-ink transition hover:bg-canvas">Marketplace</a>
                    <a href="{{ route('catalog.index', ['sort' => 'newest']) }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-ink transition hover:bg-canvas">New arrivals</a>
                    <a href="{{ route('catalog.top-products') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-ink transition hover:bg-canvas">Best sellers</a>
                    <a href="{{ route('catalog.stores') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-ink transition hover:bg-canvas">Stores</a>
                    <a href="{{ route('home') }}#shop-by-category" @click="closeDrawer" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-ink transition hover:bg-canvas">Categories</a>
                    @if ($isBuyer)
                        <a href="{{ route('orders.index') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-ink transition hover:bg-canvas">Orders</a>
                    @endif
                    @if ($canUseMessages)
                        <a href="{{ route('messages.index') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-ink transition hover:bg-canvas">Messages</a>
                    @endif
                    @if ($user?->account_type === 'seller')
                        <a href="{{ $sellerTenant ? route('seller.dashboard') : route('seller-application.create') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-brand transition hover:bg-blue-50">{{ $sellerEntry }}</a>
                    @elseif ($isBuyer)
                        <a href="{{ route('seller-application.create') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-brand transition hover:bg-blue-50">Start selling</a>
                    @elseif ($user?->isPlatformAdministrator())
                        <a href="{{ route('dashboard') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-brand transition hover:bg-blue-50">Admin workspace</a>
                    @endif
                </nav>
                <div class="space-y-1 border-t border-line p-4">
                    @auth
                        <a href="{{ route('profile.edit') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-ink transition hover:bg-canvas">Account</a>
                        <button type="button" data-clerk-sign-out @click="closeDrawer" class="flex min-h-11 w-full items-center rounded-lg px-3 text-left text-sm font-medium text-muted transition hover:bg-rose-50 hover:text-rose-800">Sign out</button>
                    @else
                        <a href="{{ route('login') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-ink transition hover:bg-canvas">Sign in</a>
                        <a href="{{ route('register') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-brand transition hover:bg-blue-50">Create account</a>
                        <a href="{{ route('home') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-muted transition hover:bg-canvas">Continue shopping</a>
                    @endauth
                </div>
            @endif
        </aside>
    </header>
@endif
