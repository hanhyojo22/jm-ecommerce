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
    $isSellerWorkspace = $user?->account_type === 'seller' && request()->routeIs('seller.dashboard', 'seller.products.*', 'seller.orders.*', 'seller.reviews.*', 'seller.earnings.*', 'seller.store-settings.*', 'messages.*');
    $isHomepage = request()->routeIs('home') && ! $isSellerWorkspace;
    $usesHomepageHeaderLayout = $isHomepage || $isSellerWorkspace;
    $hasMarketplaceNavigation = request()->routeIs('home', 'catalog.index', 'catalog.top-products', 'catalog.stores', 'catalog.store', 'catalog.show') && ! $isSellerWorkspace;
@endphp

@if ($user?->isPlatformAdministrator() && request()->routeIs('dashboard', 'admin.*'))
    <x-marketplace.admin-header :user="$user" />
@else
    <header
        data-header-design="bold-modular"
        data-header-role="{{ $isSellerWorkspace ? 'seller' : ($isBuyer ? 'customer' : 'marketplace') }}"
        x-data="{
            accountOpen: false,
            accountButton: null,
            drawerOpen: false,
            menuButton: null,
            closeDrawer() { this.drawerOpen = false; this.$nextTick(() => this.menuButton?.focus()) },
            toggleAccount(event) { this.accountButton = event.currentTarget; this.accountOpen = !this.accountOpen },
            closeAccount() { this.accountOpen = false; this.$nextTick(() => this.accountButton?.focus()) },
            toggleDrawer(event) { this.menuButton = event.currentTarget; this.drawerOpen = !this.drawerOpen; if (this.drawerOpen) this.$nextTick(() => this.$refs.drawer.querySelector('[data-drawer-focus]')?.focus()) },
            trap(event) { const items = [...this.$refs.drawer.querySelectorAll('a,button,input')].filter((item) => !item.disabled && item.offsetParent); const first = items[0], last = items.at(-1); if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus() } else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus() } },
        }"
        x-init="$watch('drawerOpen', (open) => document.body.classList.toggle('overflow-hidden', open))"
        @keydown.escape.window="if (drawerOpen) closeDrawer(); if (accountOpen) closeAccount()"
        class="sticky top-0 z-40 border-b border-line bg-white"
    >
        <div class="mx-auto max-w-[1320px] {{ $usesHomepageHeaderLayout ? 'flex flex-wrap items-center gap-5 px-[18px] py-[14px] min-[641px]:px-8' : 'grid h-[52px] grid-cols-[1fr_auto] items-center gap-3 px-[18px] min-[641px]:h-[70px] min-[641px]:grid-cols-[auto_minmax(12rem,1fr)_auto] min-[641px]:gap-[18px] min-[1021px]:grid-cols-[auto_minmax(15rem,1fr)_auto] min-[1021px]:px-0' }}">
            <div class="flex min-w-0 items-center {{ $usesHomepageHeaderLayout ? 'order-1 gap-2.5 min-[641px]:order-none' : 'gap-2 min-[1021px]:mr-5 min-[1021px]:gap-5' }}">
                <button type="button" @click="toggleDrawer($event)" :aria-expanded="drawerOpen.toString()" aria-controls="marketplace-drawer" class="{{ $isSellerWorkspace ? 'lg:hidden' : ($isHomepage ? 'hidden' : 'min-[641px]:hidden') }} grid size-11 shrink-0 place-items-center rounded-[10px] text-muted transition hover:bg-blue-50 hover:text-brand focus-visible:ring-2 focus-visible:ring-brand" aria-label="Open navigation menu">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" /></svg>
                </button>
                <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5 rounded-lg focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 {{ $usesHomepageHeaderLayout ? 'min-w-[140px]' : '' }}" aria-label="JMMart marketplace home">
                    @if ($usesHomepageHeaderLayout)
                        <span class="grid size-8 shrink-0 place-items-center rounded-[8px] bg-brand text-lg font-bold leading-none text-white" aria-hidden="true">J</span>
                    @else
                        <x-application-logo class="size-9 fill-current text-brand" />
                    @endif
                    <span class="{{ $usesHomepageHeaderLayout ? 'text-xl tracking-[-0.02em]' : 'text-lg tracking-[-0.025em]' }} font-bold text-ink">JMMart</span><span class="inline-block size-1.5 rounded-full bg-warning" aria-hidden="true"></span>
                </a>

                @if ($isSellerWorkspace)
                    <span class="hidden h-8 w-px bg-line sm:block" aria-hidden="true"></span>
                    <div class="hidden min-w-0 flex-col gap-0.5 sm:flex">
                        <span class="text-[10px] font-semibold uppercase tracking-[.12em] text-muted">Seller Center</span>
                        <strong class="max-w-64 truncate text-sm font-semibold text-ink">{{ $sellerTenant?->name ?? 'Your store' }}</strong>
                    </div>
                @endif
            </div>

            <form method="GET" action="{{ $isSellerWorkspace ? route('seller.products.index') : route('catalog.index') }}" x-data="{ query: @js($isSellerWorkspace ? request('search', $search) : $search), loading: false }" @submit="loading = true" class="{{ $usesHomepageHeaderLayout ? 'order-3 basis-full min-w-0 min-[641px]:order-none min-[641px]:basis-0 min-[641px]:flex-1' : 'hidden min-w-0 min-[641px]:block min-[1021px]:ml-1' }}">
                <label for="{{ $usesHomepageHeaderLayout ? 'marketplace-search-home-mobile' : 'marketplace-search-desktop' }}" class="{{ $usesHomepageHeaderLayout ? 'flex h-[46px] items-center gap-2 rounded-[10px] border border-line bg-canvas pl-3.5 pr-2 text-muted transition focus-within:border-brand focus-within:bg-white focus-within:ring-4 focus-within:ring-brand/10' : 'flex h-11 items-center gap-3 rounded-[10px] border border-line bg-canvas px-3.5 text-muted transition focus-within:border-brand focus-within:bg-white focus-within:ring-4 focus-within:ring-brand/10' }}">
                    <svg class="{{ $usesHomepageHeaderLayout ? 'size-[18px]' : 'size-5' }} shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg>
<input id="{{ $usesHomepageHeaderLayout ? 'marketplace-search-home-mobile' : 'marketplace-search-desktop' }}" name="{{ $isSellerWorkspace ? 'search' : 'q' }}" aria-label="{{ $isSellerWorkspace ? 'Search your products by name or SKU' : 'Search products, brands, and stores' }}" @if ($isSellerWorkspace) data-seller-shortcut="search" aria-keyshortcuts="S" @endif x-model="query" value="{{ $isSellerWorkspace ? request('search', $search) : $search }}" autocomplete="off" placeholder="{{ $isSellerWorkspace ? 'Search your products' : 'Search products and stores' }}" class="min-w-0 flex-1 border-0 bg-transparent p-0 {{ $usesHomepageHeaderLayout ? 'text-[15px]' : 'text-sm' }} text-ink placeholder:text-muted focus:ring-0">
                    <button x-cloak x-show="query.length" type="button" @click="query = ''; $nextTick(() => $el.previousElementSibling.focus())" class="grid size-7 place-items-center rounded-md transition hover:bg-canvas" aria-label="Clear search"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg></button>
                    <button type="submit" class="{{ $usesHomepageHeaderLayout ? 'h-[34px] rounded-[8px] px-[18px] py-0 text-[15px]' : 'rounded-[8px] px-4 py-2 text-sm' }} bg-brand font-semibold text-white transition hover:bg-brand-hover">Search</button>
                    <span x-cloak x-show="loading" class="text-xs font-semibold text-brand">Searching…</span>
                </label>
            </form>

            <div class="flex shrink-0 items-center justify-end {{ $usesHomepageHeaderLayout ? 'order-2 ml-auto gap-1 min-[641px]:order-none min-[641px]:ml-0' : 'gap-1 sm:gap-2 min-[641px]:gap-[18px]' }}">
                @if ($isSellerWorkspace && $sellerTenant)
                    <a href="{{ route('catalog.store', $sellerTenant->slug) }}" target="_blank" rel="noopener noreferrer" class="hidden min-h-10 items-center rounded-[10px] border border-line bg-white px-4 text-sm font-semibold text-ink transition hover:bg-canvas focus-visible:ring-2 focus-visible:ring-brand sm:inline-flex">View storefront</a>
                @elseif ($canUseMessages && ! request()->routeIs('catalog.index'))
                    <a href="{{ route('messages.index') }}" class="{{ $usesHomepageHeaderLayout ? 'relative grid size-11 place-items-center rounded-[10px] text-ink transition hover:bg-canvas focus-visible:ring-2 focus-visible:ring-brand' : 'relative grid size-11 place-items-center rounded-[10px] text-muted transition hover:bg-blue-50 hover:text-brand focus-visible:ring-2 focus-visible:ring-brand' }}" aria-label="Messages{{ $unreadConversationCount ? ', '.$unreadConversationCount.' unread' : '' }}">
                        <svg class="{{ $usesHomepageHeaderLayout ? 'size-[22px]' : 'size-5' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M21 12a8 8 0 0 1-8 8H4l2-3a8 8 0 1 1 15-5z" /></svg>
                        @if ($unreadConversationCount > 0)
                            <span class="absolute {{ $usesHomepageHeaderLayout ? 'right-1 top-1 grid h-[19px] min-w-[19px] place-items-center border-2 border-white px-[5px] text-[11px]' : 'right-0 top-0 inline-flex min-w-5 translate-x-1/4 -translate-y-1/4 px-1 py-0.5 text-[10px] ring-2 ring-white' }} rounded-full bg-brand font-bold leading-none text-white">{{ $messageBadge }}</span>
                        @endif
                    </a>
                @endif

                @if ($isBuyer)
                    <a href="{{ route('cart.index') }}" data-cart-link class="{{ $isHomepage ? 'relative grid size-11 place-items-center rounded-[10px] text-ink transition hover:bg-canvas focus-visible:ring-2 focus-visible:ring-brand' : 'relative order-2 inline-flex min-h-11 items-center justify-center gap-2 rounded-[10px] px-3 text-sm font-semibold text-ink transition hover:bg-blue-50 hover:text-brand focus-visible:ring-2 focus-visible:ring-brand min-[641px]:order-2' }}" aria-label="Cart: {{ $cartItemCount }} {{ Str::plural('item', $cartItemCount) }}">
                        <svg class="{{ $isHomepage ? 'size-[22px]' : 'size-5 min-[641px]:hidden' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M6 6h15l-1.7 7H7.2L6 6z" /><circle cx="9" cy="20" r="1.4" /><circle cx="17" cy="20" r="1.4" /><path d="M6 6 5 3H2" /></svg>
                        @unless ($isHomepage)<span data-cart-count-text class="hidden min-[641px]:inline">Cart ({{ $cartItemCount }})</span>@endunless
                        <span data-cart-badge class="absolute {{ $isHomepage ? 'right-1 top-1 grid h-[19px] min-w-[19px] place-items-center border-2 border-white px-[5px] text-[11px]' : 'right-0 top-0 inline-flex min-w-5 translate-x-1/4 -translate-y-1/4 px-1 py-0.5 text-[10px] ring-2 ring-white min-[641px]:hidden' }} rounded-full bg-brand font-bold leading-none text-white" @if ($cartItemCount === 0) style="display: none" @endif>{{ $cartBadge }}</span>
                    </a>
                @endif

                @auth
                    <div class="relative {{ $usesHomepageHeaderLayout ? '' : 'min-[641px]:order-1' }}" @click.outside="accountOpen = false">
                        <button x-ref="accountButton" type="button" @click="toggleAccount($event)" :aria-expanded="accountOpen.toString()" aria-controls="header-account-menu" class="{{ $usesHomepageHeaderLayout ? 'flex h-10 max-w-48 items-center gap-2 rounded-[10px] border border-line bg-white px-3 py-2 text-left text-sm font-medium text-ink transition hover:border-ink focus-visible:ring-2 focus-visible:ring-brand' : 'flex min-h-11 max-w-48 items-center gap-2 rounded-[10px] px-3 text-left text-ink transition hover:bg-blue-50 focus-visible:ring-2 focus-visible:ring-brand '.($isSellerWorkspace ? '' : 'min-[641px]:px-0') }}">
                            @if ($usesHomepageHeaderLayout)<svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5" /></svg>@endif
                            <span class="{{ $usesHomepageHeaderLayout ? 'hidden min-[641px]:inline' : 'hidden text-sm font-semibold min-[641px]:inline' }}">{{ $usesHomepageHeaderLayout ? 'Hi, '.Str::of($user->name)->before(' ') : 'Account' }}</span>
                            <span class="{{ $usesHomepageHeaderLayout ? 'hidden' : 'grid size-8 shrink-0 place-items-center rounded-full bg-blue-100 text-xs font-semibold text-blue-800 min-[641px]:hidden' }}">{{ Str::upper(Str::substr($user->name, 0, 1)) }}</span>
                        </button>
                        <div id="header-account-menu" x-cloak x-show="accountOpen" x-transition.origin.top.right class="absolute right-0 z-50 mt-2 w-72 overflow-hidden rounded-[14px] border border-line bg-white p-2 shadow-[0_12px_28px_rgba(15,23,42,.10)]">
                            <div class="min-w-0 border-b border-line px-3 py-3"><p class="truncate text-sm font-semibold text-ink">{{ $user->name }}</p><p class="truncate text-xs text-muted">{{ $user->email }}</p></div>
                            <div class="space-y-1 py-2">
                                <a href="{{ route('profile.edit') }}" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-ink transition hover:bg-canvas">My account</a>
                                @if ($isBuyer)
                                    <a href="{{ route('orders.index') }}" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-ink transition hover:bg-canvas">Orders</a>
                                @endif
                                @if ($canUseMessages)
                                    <a href="{{ route('messages.index') }}" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-ink transition hover:bg-canvas">Messages</a>
                                @endif
                                @if ($user->account_type === 'seller')
                                    <a href="{{ $sellerTenant ? route('seller.dashboard') : route('seller-application.create') }}" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-ink transition hover:bg-canvas">{{ $sellerEntry }}</a>
                                @endif
                                @if ($user->isPlatformAdministrator())
                                    <a href="{{ route('dashboard') }}" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-ink transition hover:bg-canvas">Admin workspace</a>
                                @endif
                            </div>
                            <button type="button" data-clerk-sign-out class="flex min-h-11 w-full items-center rounded-lg border-t border-line px-3 pt-2 text-left text-sm font-medium text-muted transition hover:bg-rose-50 hover:text-rose-800">Sign out</button>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login', ['redirect' => '/cart']) }}" class="{{ $isHomepage ? 'relative grid size-11 place-items-center rounded-[10px] text-ink transition hover:bg-canvas focus-visible:ring-2 focus-visible:ring-brand' : 'relative order-2 inline-flex min-h-11 items-center justify-center gap-2 rounded-[10px] px-3 text-sm font-semibold text-ink transition hover:bg-blue-50 hover:text-brand focus-visible:ring-2 focus-visible:ring-brand min-[641px]:order-2' }}" aria-label="Sign in to view cart">
                        <svg class="{{ $isHomepage ? 'size-[22px]' : 'size-5 min-[641px]:hidden' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M6 6h15l-1.7 7H7.2L6 6z" /><circle cx="9" cy="20" r="1.4" /><circle cx="17" cy="20" r="1.4" /><path d="M6 6 5 3H2" /></svg>
                        @unless ($isHomepage)<span class="hidden min-[641px]:inline">Cart</span>@endunless
                    </a>
                    <a href="{{ route('login') }}" class="{{ $isHomepage ? 'flex h-10 items-center gap-2 rounded-[10px] border border-line bg-white px-3 py-2 text-sm font-medium text-ink transition hover:border-ink focus-visible:ring-2 focus-visible:ring-brand' : 'order-1 hidden min-h-11 items-center rounded-[10px] px-3 text-sm font-semibold text-ink transition hover:bg-blue-50 hover:text-brand focus-visible:ring-2 focus-visible:ring-brand min-[641px]:inline-flex min-[641px]:order-1 min-[641px]:px-0' }}" aria-label="Sign in to your account">@if ($isHomepage)<svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5" /></svg><span class="hidden min-[641px]:inline">Account</span>@else<span>Account</span>@endif</a>
                @endauth
            </div>
        </div>

        @if ($hasMarketplaceNavigation)
            <nav data-header-category-nav class="border-t border-line" aria-label="Marketplace sections">
                <div class="page-container flex min-h-12 items-center gap-2 overflow-x-auto py-2 text-sm font-semibold text-muted">
                    <a href="{{ $isHomepage ? '#shop-by-category' : route('home').'#shop-by-category' }}" class="inline-flex min-h-11 shrink-0 items-center gap-1 rounded-xl bg-brand px-3 text-white">Categories ▾</a>
                    <a href="{{ route('catalog.index', ['sort' => 'newest']) }}" class="inline-flex min-h-11 shrink-0 items-center rounded-xl px-3 transition hover:bg-blue-50 hover:text-brand">New Arrivals</a>
                    <a href="{{ route('catalog.top-products') }}" class="inline-flex min-h-11 shrink-0 items-center rounded-xl px-3 transition hover:bg-blue-50 hover:text-brand">Best Sellers</a>
                    <a href="{{ $isHomepage ? '#flash-deals' : route('home').'#flash-deals' }}" class="inline-flex min-h-11 shrink-0 items-center rounded-xl px-3 transition hover:bg-blue-50 hover:text-brand">Deals</a>
                    <a href="{{ route('catalog.stores') }}" class="inline-flex min-h-11 shrink-0 items-center rounded-xl px-3 transition hover:bg-blue-50 hover:text-brand">Stores</a>
                    @if (! $user?->isPlatformAdministrator())
                        <a href="{{ $user?->account_type === 'seller' ? route('seller.dashboard') : (auth()->check() ? route('seller-application.create') : route('login', ['redirect' => '/start-selling'])) }}" class="inline-flex min-h-11 shrink-0 items-center rounded-xl bg-orange-50 px-3 font-bold text-orange-800 transition hover:bg-orange-100">Sell on JMMart</a>
                    @endif
                </div>
            </nav>
        @endif

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
<input id="marketplace-search-drawer" name="q" aria-label="{{ $isSellerWorkspace ? 'Search the marketplace' : 'Search products, brands, and stores' }}" x-model="query" value="{{ $search }}" autocomplete="off" placeholder="{{ $isSellerWorkspace ? 'Search the marketplace' : 'Search products, brands, and stores' }}" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-ink placeholder:text-muted focus:ring-0">
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
