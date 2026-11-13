@props(['search' => ''])

@php
    $user = auth()->user();
    $isBuyer = $user && in_array($user->account_type, ['buyer', 'seller'], true);
    $canUseMessages = $user && in_array($user->account_type, ['buyer', 'seller'], true);
    $cartBadge = $cartItemCount > 99 ? '99+' : $cartItemCount;
    $buyerMessagesUrl = $user?->account_type === 'seller' ? route('messages.index', ['context' => 'buying']) : route('messages.index');
    $sellerEntry = $sellerTenant ? 'Seller Center' : match ($sellerApplication?->status) {
        'pending' => 'Seller application',
        'rejected' => 'Update seller application',
        default => 'Start selling',
    };
    $isSellerMessages = request()->routeIs('messages.index') ? request('context') !== 'buying' : (request()->routeIs('messages.show') && request()->route('conversation') instanceof \App\Models\Conversation && request()->route('conversation')->buyer_id !== $user?->id);
    $isSellerWorkspace = $user?->account_type === 'seller' && (request()->routeIs('seller.dashboard', 'seller.products.*', 'seller.orders.*', 'seller.returns.*', 'seller.reviews.*', 'seller.earnings.*', 'seller.platform-fees.*', 'seller.store-settings.*') || $isSellerMessages || (request()->routeIs('profile.edit') && $user->preferred_workspace === 'seller' && $sellerTenant !== null));
    $isCustomerWorkspace = $isBuyer && ! $isSellerWorkspace && request()->routeIs('orders.*', 'returns.show', 'reviews.index', 'delivery-addresses.*', 'messages.*', 'profile.edit');
    $customerPageTitle = match (true) {
        request()->routeIs('orders.show') => 'Order details',
        request()->routeIs('returns.show') => 'Return case',
        request()->routeIs('orders.*') => 'My Purchases',
        request()->routeIs('reviews.index') => 'My Reviews',
        request()->routeIs('delivery-addresses.*') => 'Delivery Addresses',
        request()->routeIs('messages.*') => 'Messages',
        default => 'Settings',
    };
    $isHomepage = request()->routeIs('home') && ! $isSellerWorkspace;
    $sellerPageTitle = match (true) {
        request()->routeIs('seller.dashboard') => 'Overview',
        request()->routeIs('seller.orders.*') => 'Orders',
        request()->routeIs('seller.returns.*') => 'Returns',
        request()->routeIs('seller.products.*') => 'Products',
        request()->routeIs('messages.*') => 'Messages',
        request()->routeIs('seller.reviews.*') => 'Reviews',
        request()->routeIs('seller.earnings.*') => 'Earnings',
        request()->routeIs('seller.platform-fees.*') => 'Platform fees',
        request()->routeIs('seller.store-settings.*') => 'Store settings',
        request()->routeIs('profile.edit') => 'Account settings',
        default => 'Seller Center',
    };
    $selectedSearchCategory = request()->routeIs('catalog.index') ? (string) request('category', '') : '';
    $isNewArrivalsNav = request()->routeIs('catalog.index') && request('sort') === 'newest';
    $isDealsNav = request()->routeIs('catalog.index') && request()->boolean('on_sale');
    $isBestSellersNav = request()->routeIs('catalog.top-products');
    $isStoresNav = request()->routeIs('catalog.stores', 'catalog.store');
    $isCategoryNav = request()->routeIs('catalog.index') && filled(request('category'));
    $isAllProductsNav = request()->routeIs('catalog.index') && ! $isNewArrivalsNav && ! $isDealsNav && ! $isCategoryNav;
    $showCategoryMenu = $headerCategories->count() > 1 && ! request()->routeIs('catalog.store');
@endphp

@if ($user?->isPlatformAdministrator() && request()->routeIs('dashboard', 'admin.*'))
    <x-marketplace.admin-header :user="$user" />
@else
    @unless ($isSellerWorkspace || $isCustomerWorkspace)
        <x-marketplace.announce-bar />
    @endunless
    <header
        data-header-design="bold-modular"
        data-header-role="{{ $isSellerWorkspace ? 'seller' : ($isCustomerWorkspace ? 'customer-workspace' : ($isBuyer ? 'customer' : 'marketplace')) }}"
        x-data="{
            accountOpen: false,
            accountButton: null,
            categoriesOpen: false,
            categoryButton: null,
            drawerOpen: false,
            menuButton: null,
            closeDrawer() { this.drawerOpen = false; this.$nextTick(() => this.menuButton?.focus()) },
            toggleAccount(event) { this.categoriesOpen = false; this.accountButton = event.currentTarget; this.accountOpen = !this.accountOpen },
            closeAccount() { this.accountOpen = false; this.$nextTick(() => this.accountButton?.focus()) },
            toggleCategories(event) { this.accountOpen = false; this.categoryButton = event.currentTarget; this.categoriesOpen = !this.categoriesOpen },
            closeCategories() { this.categoriesOpen = false; this.$nextTick(() => this.categoryButton?.focus()) },
            toggleDrawer(event) { this.categoriesOpen = false; this.accountOpen = false; this.menuButton = event.currentTarget; this.drawerOpen = !this.drawerOpen; if (this.drawerOpen) this.$nextTick(() => this.$refs.drawer.querySelector('[data-drawer-focus]')?.focus()) },
            trap(event) { const items = [...this.$refs.drawer.querySelectorAll('a,button,input')].filter((item) => !item.disabled && item.offsetParent); const first = items[0], last = items.at(-1); if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus() } else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus() } },
        }"
        x-init="$watch('drawerOpen', (open) => document.body.classList.toggle('overflow-hidden', open))"
        @keydown.escape.window="if (drawerOpen) closeDrawer(); if (accountOpen) closeAccount(); if (categoriesOpen) closeCategories()"
        class="sticky top-0 z-40 border-b border-line bg-white"
    >
        <div class="marketplace-container flex flex-wrap items-center gap-x-2.5 gap-y-3 py-3 sm:gap-x-5 sm:py-3.5">
            <div class="order-1 flex min-w-0 items-center gap-1.5 min-[641px]:order-none sm:gap-2.5">
                <button type="button" @click="toggleDrawer($event)" :aria-expanded="drawerOpen.toString()" aria-controls="marketplace-drawer" class="{{ $isSellerWorkspace || $isCustomerWorkspace ? 'lg:hidden' : 'min-[641px]:hidden' }} grid size-11 shrink-0 place-items-center rounded-md text-muted transition hover:bg-canvas hover:text-brand focus-visible:ring-2 focus-visible:ring-brand" aria-label="Open navigation menu">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" /></svg>
                </button>
                @if ($isSellerWorkspace)
                    <button type="button" @click="$dispatch('seller-sidebar-toggle')" class="hidden size-8 shrink-0 place-items-center rounded-md border border-line text-ink shadow-sm transition hover:bg-canvas hover:text-brand focus-visible:ring-2 focus-visible:ring-brand lg:grid" aria-label="Toggle Seller Center sidebar"><svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" /></svg></button>
                    <a href="{{ route('seller.dashboard') }}" class="inline-flex items-center gap-2.5 rounded-md focus-visible:ring-2 focus-visible:ring-brand lg:hidden" aria-label="JMMart Seller Center overview">
                        <img src="{{ asset('favicon.svg') }}" alt="" class="size-8 shrink-0 rounded-md" width="32" height="32">
                        <span class="text-sm font-semibold text-ink sm:text-base">Seller Center</span>
                    </a>
                    <div class="hidden min-w-0 items-center gap-2 text-sm lg:flex">
                        <span class="font-medium text-muted">Seller Center</span>
                        <span class="text-slate-300" aria-hidden="true">/</span>
                        <strong class="truncate font-semibold text-ink">{{ $sellerPageTitle }}</strong>
                    </div>
                @elseif ($isCustomerWorkspace)
                    <button type="button" @click="$dispatch('customer-sidebar-toggle')" class="hidden size-8 shrink-0 place-items-center rounded-md border border-line text-ink shadow-sm transition hover:bg-canvas hover:text-brand focus-visible:ring-2 focus-visible:ring-brand lg:grid" aria-label="Toggle My account sidebar"><svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" /></svg></button>
                    <a href="{{ route('orders.index') }}" class="inline-flex items-center gap-2.5 rounded-md focus-visible:ring-2 focus-visible:ring-brand lg:hidden" aria-label="JMMart My account">
                        <img src="{{ asset('favicon.svg') }}" alt="" class="size-8 shrink-0 rounded-md" width="32" height="32">
                        <span class="text-sm font-semibold text-ink sm:text-base">My account</span>
                    </a>
                    <div class="hidden min-w-0 items-center gap-2 text-sm lg:flex">
                        <span class="font-medium text-muted">My account</span>
                        <span class="text-slate-300" aria-hidden="true">/</span>
                        <strong class="truncate font-semibold text-ink">{{ $customerPageTitle }}</strong>
                    </div>
                @else
                    <a href="{{ route('home') }}" class="group flex shrink-0 items-center gap-2 rounded-md focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 sm:gap-2.5" aria-label="JMMart marketplace home">
                        <span class="hidden size-8 shrink-0 place-items-center rounded-md bg-brand text-sm font-bold leading-none tracking-[-0.03em] text-white transition group-hover:bg-brand-hover min-[400px]:grid sm:size-9 sm:text-[15px]" aria-hidden="true">jm</span>
                        <span class="text-xl font-bold leading-none tracking-[-0.04em] text-ink sm:text-2xl">JM<span class="text-brand">Mart.</span></span>
                    </a>
                @endif
            </div>

            <form
                method="GET"
                action="{{ $isSellerWorkspace ? route('seller.products.index') : route('catalog.index') }}"
                role="search"
                aria-label="{{ $isSellerWorkspace ? 'Search your seller catalog' : 'Search the marketplace' }}"
                x-data="{{ $isSellerWorkspace ? '{ query: '.Js::from(request('search', $search)).', loading: false }' : 'marketplaceSuggestions('.Js::from($search).', '.Js::from(route('catalog.suggestions')).', '.Js::from(route('catalog.index')).')' }}"
                @submit="loading = true"
                @unless ($isSellerWorkspace) @click.outside="open = false" @endunless
                class="relative order-3 min-w-0 basis-full min-[641px]:order-none min-[641px]:basis-0 min-[641px]:flex-1"
            >
                <div data-header-search-shell class="flex h-11 items-center rounded-md border border-[#cbd4e0] bg-canvas transition focus-within:border-brand focus-within:bg-white focus-within:ring-4 focus-within:ring-brand/10">
                    @if (! $isSellerWorkspace && ! $isCustomerWorkspace && $showCategoryMenu)
                        <div class="relative hidden h-full shrink-0 items-center border-r border-[#cbd4e0] md:flex">
                            <select
                                name="category"
                                aria-label="Search category"
                                class="h-full max-w-[144px] cursor-pointer truncate border-0 bg-transparent py-0 pl-3 pr-7 text-xs font-semibold text-ink focus:ring-0"
                            >
                                <option value="">All</option>
                                @foreach ($headerCategories as $category)
                                    <option value="{{ $category->slug }}" @selected($selectedSearchCategory === $category->slug)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <label for="marketplace-search" class="flex h-full min-w-0 flex-1 items-center gap-2 pl-3 pr-1.5 text-muted">
                        <svg class="size-[18px] shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg>
                        <input
                            id="marketplace-search"
                            name="{{ $isSellerWorkspace ? 'search' : 'q' }}"
                            aria-label="{{ $isSellerWorkspace ? 'Search your products by name or SKU' : 'Search products, brands, and stores' }}"
                            @if ($isSellerWorkspace)
                                data-seller-shortcut="search"
                                aria-keyshortcuts="S"
                            @else
                                role="combobox"
                                aria-autocomplete="list"
                                aria-controls="marketplace-search-results"
                                :aria-expanded="open.toString()"
                                :aria-activedescendant="active >= 0 ? 'marketplace-result-' + active : null"
                                @input.debounce.275ms="load()"
                                @focus="load()"
                                @keydown.arrow-down.prevent="move(1)"
                                @keydown.arrow-up.prevent="move(-1)"
                                @keydown.enter="select($event)"
                                @keydown.escape="open = false"
                            @endif
                            x-model="query"
                            value="{{ $isSellerWorkspace ? request('search', $search) : $search }}"
                            autocomplete="off"
                            placeholder="{{ $isSellerWorkspace ? 'Search your products' : 'Search products, brands, and stores' }}"
                            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-[15px] text-ink placeholder:text-muted focus:ring-0"
                        >
                        <button x-cloak x-show="query.length" type="button" @click="{{ $isSellerWorkspace ? "query = ''" : 'clear()' }}; $nextTick(() => $el.previousElementSibling.focus())" class="grid size-7 shrink-0 place-items-center rounded-md text-muted transition hover:bg-slate-200/70 hover:text-ink" aria-label="Clear search">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg>
                        </button>
                        <button type="submit" class="inline-flex h-8 shrink-0 items-center justify-center rounded-[5px] bg-brand px-3.5 text-sm font-semibold text-white transition hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand sm:px-4">Search</button>
                    </label>
                </div>
                @unless ($isSellerWorkspace)
                    @include('components.marketplace.search-suggestions', ['listId' => 'marketplace-search-results', 'optionPrefix' => 'marketplace-result-'])
                @endunless
            </form>

            <div class="order-2 ml-auto flex shrink-0 items-center justify-end gap-1 sm:gap-2 min-[641px]:order-none min-[641px]:ml-0">
                @if ($isSellerWorkspace || $isCustomerWorkspace)
                    <a href="{{ route('home') }}" class="hidden min-h-10 items-center gap-1.5 rounded-md px-3 text-xs font-semibold text-brand transition hover:bg-[#eef3ff] hover:text-brand-hover focus-visible:ring-2 focus-visible:ring-brand xl:inline-flex">
                        <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 4l9 6.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5Z"/></svg>
                        <span>Marketplace</span>
                    </a>
                @endif

                @if ($canUseMessages)
                    <livewire:message-header-badge :seller-workspace="$isSellerWorkspace" />
                @endif

                @if ($isBuyer && ! $isSellerWorkspace)
                    <a href="{{ route('cart.index') }}" data-cart-link class="inline-flex min-h-11 min-w-11 items-center justify-center gap-2 rounded-md px-2 text-ink transition hover:bg-canvas focus-visible:ring-2 focus-visible:ring-brand lg:px-3" aria-label="Cart: {{ $cartItemCount }} {{ Str::plural('item', $cartItemCount) }}">
                        <span class="relative inline-flex shrink-0 items-center justify-center">
                            <svg class="size-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M6 6h15l-1.7 7H7.2L6 6z" /><circle cx="9" cy="20" r="1.4" /><circle cx="17" cy="20" r="1.4" /><path d="M6 6 5 3H2" /></svg>
                            {{-- The link's own label already names the count, so the badge repeats it for the eye only. --}}
                            {{-- Anchored outside the icon's corner: a wide count grows leftward over empty padding, so the cart stays legible at any number. --}}
                            <span data-cart-badge aria-hidden="true" class="absolute -right-2 -top-1.5 grid h-[19px] min-w-[19px] place-items-center whitespace-nowrap rounded-full border-2 border-white bg-brand px-[5px] text-xs font-semibold leading-none tabular-nums text-white" @if ($cartItemCount === 0) style="display: none" @endif>{{ $cartBadge }}</span>
                        </span>
                        @unless ($isCustomerWorkspace)
                            <span class="hidden text-sm font-medium text-ink lg:inline">Cart</span>
                        @endunless
                    </a>
                @endif

                @auth
                    <livewire:notification-bell />

                    <span class="hidden h-6 w-px bg-line sm:inline-block" aria-hidden="true"></span>

                    <div class="relative" @click.outside="accountOpen = false">
                        <button x-ref="accountButton" type="button" @click="toggleAccount($event)" :aria-expanded="accountOpen.toString()" aria-controls="header-account-menu" aria-label="Account menu" class="flex min-h-11 min-w-11 max-w-48 items-center justify-center gap-2 rounded-md border border-[#cbd4e0] bg-white px-2 py-1.5 text-left text-sm font-medium text-ink transition hover:border-ink focus-visible:ring-2 focus-visible:ring-brand min-[641px]:px-3">
                            <span class="grid size-7 shrink-0 place-items-center rounded-full bg-[#eef3ff] text-xs font-bold leading-none text-brand" aria-hidden="true">{{ Str::upper(Str::substr($user->name, 0, 1)) }}</span>
                            <span class="hidden truncate {{ $isSellerWorkspace ? 'lg:inline' : 'min-[641px]:inline' }}">Hi, {{ Str::of($user->name)->before(' ') }}</span>
                            <svg class="hidden size-3.5 shrink-0 text-muted transition-transform duration-150 {{ $isSellerWorkspace ? 'lg:block' : 'min-[641px]:block' }}" :class="accountOpen && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                        </button>
                        <div id="header-account-menu" x-cloak x-show="accountOpen" x-transition.origin.top.right class="absolute right-0 z-50 mt-2 w-72 max-w-[calc(100vw-1rem)] overflow-hidden rounded-lg border border-line bg-white p-2 shadow-[0_18px_40px_rgba(24,44,72,0.16)]">
                            <div class="flex min-w-0 items-center gap-3 px-2 py-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-full bg-[#eef3ff] text-sm font-semibold leading-none text-brand" aria-hidden="true">{{ Str::upper(Str::substr($user->name, 0, 1)) }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-ink">{{ $user->name }}</p>
                                    <p class="truncate text-xs text-muted">{{ $user->email }}</p>
                                </div>
                            </div>
                            <nav aria-label="Account links" class="border-t border-line py-1">
                                @if ($isBuyer && ! $isSellerWorkspace)
                                    <a href="{{ route('orders.index') }}" class="flex min-h-11 items-center gap-2.5 rounded-md px-3 text-sm font-medium text-ink transition hover:bg-canvas">
                                        <svg class="size-[18px] shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9Z" /><path d="m4 7.5 8 4.5 8-4.5M12 12v9" /></svg>
                                        My Purchases
                                    </a>
                                    <a href="{{ route('delivery-addresses.index') }}" class="flex min-h-11 items-center gap-2.5 rounded-md px-3 text-sm font-medium text-ink transition hover:bg-canvas">
                                        <svg class="size-[18px] shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-6-4.35-6-10a6 6 0 1 1 12 0c0 5.65-6 10-6 10Z" /><circle cx="12" cy="11" r="2.2" /></svg>
                                        Delivery Addresses
                                    </a>
                                @endif
                                @if ($canUseMessages)
                                    <a href="{{ $isSellerWorkspace ? route('messages.index', ['context' => 'selling']) : $buyerMessagesUrl }}" class="flex min-h-11 items-center gap-2.5 rounded-md px-3 text-sm font-medium text-ink transition hover:bg-canvas">
                                        <svg class="size-[18px] shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-8 8H4l2-3a8 8 0 1 1 15-5z" /></svg>
                                        Messages
                                    </a>
                                @endif
                                <a href="{{ route('profile.edit') }}" class="flex min-h-11 items-center gap-2.5 rounded-md px-3 text-sm font-medium text-ink transition hover:bg-canvas">
                                    <svg class="size-[18px] shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5" /></svg>
                                    My account
                                </a>
                                @if ($user->account_type === 'seller' && ! $sellerTenant)
                                    <a href="{{ route('seller-application.create') }}" class="flex min-h-11 items-center rounded-md px-3 text-sm font-medium text-ink transition hover:bg-canvas">{{ $sellerEntry }}</a>
                                @endif
                                @if ($user->isPlatformAdministrator())
                                    <a href="{{ route('dashboard') }}" class="flex min-h-11 items-center rounded-md px-3 text-sm font-medium text-ink transition hover:bg-canvas">Admin workspace</a>
                                @endif
                            </nav>
                            @if ($sellerTenant)
                                <div class="border-t border-line py-2">
                                    <form method="POST" action="{{ route('profile.workspace.update') }}" data-workspace-switch>
                                        @csrf
                                        <input type="hidden" name="workspace" value="{{ $isSellerWorkspace ? 'customer' : 'seller' }}">
                                        <button type="submit" class="flex min-h-14 w-full items-center gap-3 rounded-md bg-[#eef3ff] px-3 text-left transition hover:bg-blue-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
                                            <svg class="size-5 shrink-0 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h15m0 0-4-4m4 4-4 4M20 17H5m0 0 4-4m-4 4 4 4"/></svg>
                                            <span class="min-w-0 flex-1"><span class="block text-sm font-semibold text-brand">Switch Account</span><span class="block text-xs text-muted">{{ $isSellerWorkspace ? 'Go to shopping' : 'Go to Seller Center' }}</span></span>
                                            <svg class="size-4 shrink-0 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
                                        </button>
                                    </form>
                                </div>
                            @endif
                            <div class="border-t border-line pt-1"><button type="button" data-clerk-sign-out class="flex min-h-11 w-full items-center gap-2.5 rounded-md px-3 text-left text-sm font-medium text-muted transition hover:bg-rose-50 hover:text-rose-800"><svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3" /><path d="m10 17 5-5-5-5M15 12H3" /></svg>Sign out</button></div>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login', ['redirect' => '/cart']) }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md px-2.5 text-ink transition hover:bg-canvas focus-visible:ring-2 focus-visible:ring-brand lg:px-3" aria-label="Sign in to view cart">
                        <svg class="size-[22px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M6 6h15l-1.7 7H7.2L6 6z" /><circle cx="9" cy="20" r="1.4" /><circle cx="17" cy="20" r="1.4" /><path d="M6 6 5 3H2" /></svg>
                        <span class="hidden text-sm font-medium text-ink lg:inline">Cart</span>
                    </a>
                    <span class="hidden h-6 w-px bg-line sm:inline-block" aria-hidden="true"></span>
                    <a href="{{ route('login') }}" class="flex min-h-11 items-center gap-2 rounded-md border border-[#cbd4e0] bg-white px-3 py-2 text-sm font-semibold text-ink transition hover:border-brand hover:text-brand focus-visible:ring-2 focus-visible:ring-brand" aria-label="Sign In"><svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m10 17 5-5-5-5M15 12H3" /><path d="M12 3h6a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3h-6" /></svg><span class="hidden min-[641px]:inline">Sign In</span></a>
                @endauth
            </div>
        </div>

        @unless ($isSellerWorkspace || $isCustomerWorkspace)
            <nav data-header-category-nav @click.outside="categoriesOpen = false" class="relative border-t border-line bg-white" aria-label="Marketplace sections">
                <div class="marketplace-container flex min-h-12 items-center gap-4">
                    <div class="marketplace-nav-scroll flex min-w-0 flex-1 items-center gap-6 overflow-x-auto text-sm font-medium text-muted sm:gap-7">
                        @if ($showCategoryMenu)
                            <button
                                type="button"
                                @click="toggleCategories($event)"
                                :aria-expanded="categoriesOpen.toString()"
                                aria-controls="header-categories-menu"
                                class="inline-flex min-h-12 shrink-0 items-center gap-2 border-b-2 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-brand {{ $isCategoryNav ? 'border-brand text-ink' : 'border-transparent text-ink hover:text-brand' }}"
                                :class="categoriesOpen ? 'border-brand text-brand' : ''"
                            >
                                <svg class="size-4 shrink-0 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                                <span>Categories</span>
                                <svg class="size-3.5 shrink-0 text-muted transition-transform duration-150" :class="categoriesOpen && 'rotate-180 text-brand'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                            </button>
                            <span class="h-4 w-px shrink-0 bg-line" aria-hidden="true"></span>
                        @else
                            <a href="{{ $isHomepage ? '#shop-by-category' : route('home').'#shop-by-category' }}" class="inline-flex min-h-12 shrink-0 items-center gap-1.5 border-b-2 {{ $isCategoryNav ? 'border-brand text-ink' : 'border-transparent text-ink hover:text-brand' }} font-semibold transition">Categories</a>
                        @endif
                        <a href="{{ route('catalog.index') }}" @if ($isAllProductsNav) aria-current="page" @endif class="inline-flex min-h-12 shrink-0 items-center border-b-2 transition {{ $isAllProductsNav ? 'border-brand font-semibold text-ink' : 'border-transparent hover:text-brand' }}">All Products</a>
                        <a href="{{ route('catalog.index', ['sort' => 'newest']) }}" @if ($isNewArrivalsNav) aria-current="page" @endif class="inline-flex min-h-12 shrink-0 items-center border-b-2 transition {{ $isNewArrivalsNav ? 'border-brand font-semibold text-ink' : 'border-transparent hover:text-brand' }}">New Arrivals</a>
                        <a href="{{ route('catalog.top-products') }}" @if ($isBestSellersNav) aria-current="page" @endif class="inline-flex min-h-12 shrink-0 items-center border-b-2 transition {{ $isBestSellersNav ? 'border-brand font-semibold text-ink' : 'border-transparent hover:text-brand' }}">Best Sellers</a>
                        <a href="{{ route('catalog.index', ['on_sale' => 1]) }}" @if ($isDealsNav) aria-current="page" @endif class="inline-flex min-h-12 shrink-0 items-center gap-1.5 border-b-2 transition {{ $isDealsNav ? 'border-brand font-semibold text-ink' : 'border-transparent hover:text-brand' }}">
                            <span class="size-1.5 rounded-full bg-danger" aria-hidden="true"></span>
                            Deals
                        </a>
                        <a href="{{ route('catalog.stores') }}" @if ($isStoresNav) aria-current="page" @endif class="inline-flex min-h-12 shrink-0 items-center border-b-2 transition {{ $isStoresNav ? 'border-brand font-semibold text-ink' : 'border-transparent hover:text-brand' }}">Stores</a>
                    </div>

                    @if (! $user?->isPlatformAdministrator() && ! $sellerTenant)
                        <a href="{{ auth()->check() ? route('seller-application.create') : route('login', ['redirect' => '/start-selling']) }}" class="hidden min-h-12 shrink-0 items-center gap-1.5 border-b-2 border-transparent text-sm font-semibold text-brand transition hover:text-brand-hover sm:inline-flex">
                            <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 9 5 4h14l2 5"/><path d="M3 9v1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0V9"/><path d="M5 13v7h14v-7"/></svg>
                            Sell on JMMart
                        </a>
                    @endif
                </div>

                @if ($showCategoryMenu)
                    <div
                        id="header-categories-menu"
                        x-cloak
                        x-show="categoriesOpen"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 -translate-y-1"
                        class="absolute inset-x-0 top-full z-40 border-t border-line bg-white shadow-[0_24px_48px_rgba(24,44,72,0.16)]"
                    >
                        <div class="marketplace-container max-h-[min(75vh,34rem)] overflow-y-auto py-6">
                            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
                                <div>
                                    <div class="mb-4 flex items-center justify-between gap-4 border-b border-line pb-3">
                                        <div>
                                            <p class="text-sm font-semibold text-ink">Browse by category</p>
                                            <p class="text-xs text-muted">Explore verified stores across {{ $headerCategories->count() }} active departments.</p>
                                        </div>
                                        <a href="{{ route('catalog.index') }}" @click="categoriesOpen = false" class="inline-flex min-h-11 items-center gap-1 rounded-md px-2 text-sm font-semibold text-brand transition hover:bg-[#eef3ff] hover:text-brand-hover">
                                            <span>Explore catalog</span>
                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
                                        </a>
                                    </div>
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 md:grid-cols-3">
                                        @foreach ($headerCategories as $category)
                                            <a
                                                href="{{ route('catalog.index', ['category' => $category->slug]) }}"
                                                @click="categoriesOpen = false"
                                                class="group flex min-h-11 items-center gap-3 rounded-md border border-transparent px-2.5 py-2 text-sm font-medium text-ink transition hover:border-line hover:bg-canvas hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand"
                                            >
                                                <span class="grid size-8 shrink-0 place-items-center rounded-md bg-canvas text-ink transition group-hover:bg-brand group-hover:text-white">
                                                    <x-category-icon :category="$category" class="size-4" />
                                                </span>
                                                <span class="min-w-0 flex-1 truncate">{{ $category->name }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="hidden rounded-lg bg-ink p-5 text-white lg:flex lg:flex-col lg:justify-between">
                                    <div>
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-[#8ea2ba]">Marketplace picks</p>
                                        <h3 class="mt-2 text-lg font-semibold leading-snug tracking-[-0.02em] text-white">Price drops &amp; top-rated local stores</h3>
                                        <ul class="mt-4 space-y-1.5 text-sm text-[#c5d1e0]">
                                            <li><a href="{{ route('catalog.index', ['on_sale' => 1]) }}" @click="categoriesOpen = false" class="inline-flex min-h-9 items-center gap-2 transition hover:text-white"><span>&rarr;</span> Biggest price drops</a></li>
                                            <li><a href="{{ route('catalog.top-products') }}" @click="categoriesOpen = false" class="inline-flex min-h-9 items-center gap-2 transition hover:text-white"><span>&rarr;</span> Trending best sellers</a></li>
                                            <li><a href="{{ route('catalog.stores') }}" @click="categoriesOpen = false" class="inline-flex min-h-9 items-center gap-2 transition hover:text-white"><span>&rarr;</span> Discover local stores</a></li>
                                        </ul>
                                    </div>
                                    <a href="{{ route('catalog.index') }}" @click="categoriesOpen = false" class="mt-5 inline-flex min-h-11 w-full items-center justify-center rounded-md bg-white px-4 text-sm font-semibold text-ink transition hover:bg-[#eef3ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                                        Browse all products
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </nav>
        @endunless

        <div x-cloak x-show="drawerOpen" x-transition.opacity class="fixed inset-0 z-50 bg-slate-950/35 lg:hidden">
            <button type="button" @click="closeDrawer" class="h-full w-full" tabindex="-1" aria-label="Close navigation menu"></button>
        </div>
        <aside id="marketplace-drawer" x-ref="drawer" x-cloak x-show="drawerOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-end="-translate-x-full" @keydown.tab="trap($event)" class="fixed inset-y-0 left-0 z-[60] flex w-[min(20rem,calc(100vw-2.5rem))] flex-col shadow-[0_16px_32px_rgba(15,23,42,.18)] lg:hidden {{ $isSellerWorkspace || $isCustomerWorkspace ? 'workspace-sidebar' : 'bg-surface text-ink' }}" role="dialog" aria-modal="true" aria-label="{{ $isSellerWorkspace ? 'Seller Center navigation' : ($isCustomerWorkspace ? 'My account navigation' : 'Marketplace navigation') }}">
            <div class="flex h-[56px] items-center justify-between border-b border-line px-5">
                <a href="{{ $isSellerWorkspace ? route('seller.dashboard') : ($isCustomerWorkspace ? route('orders.index') : route('home')) }}" data-drawer-focus @click="closeDrawer" class="workspace-sidebar-brand">
                    @if ($isSellerWorkspace || $isCustomerWorkspace)<img src="{{ asset('favicon.svg') }}" alt="" class="size-8 rounded-md" width="32" height="32">@else<x-application-logo class="size-8 fill-current text-brand" />@endif
                    <span class="workspace-sidebar-brand-name">{{ $isSellerWorkspace ? 'Seller Center' : ($isCustomerWorkspace ? 'My account' : 'JMMart') }}</span>
                </a>
                <button type="button" @click="closeDrawer" class="workspace-drawer-close grid size-11 place-items-center rounded-md text-muted transition hover:bg-canvas hover:text-ink focus-visible:ring-2 focus-visible:ring-brand" aria-label="Close navigation menu"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg></button>
            </div>

            @if (! $isSellerWorkspace && ! $isCustomerWorkspace)
                <form method="GET" action="{{ route('catalog.index') }}" x-data="marketplaceSuggestions(@js($search), @js(route('catalog.suggestions')), @js(route('catalog.index')))" @submit="loading = true" @click.outside="open = false" class="relative border-b border-line px-4 py-4">
                    <label for="marketplace-search-drawer" class="flex h-11 items-center gap-3 rounded-md border border-[#cbd4e0] bg-canvas px-3.5 text-muted transition focus-within:border-brand focus-within:bg-white focus-within:ring-4 focus-within:ring-brand/10">
                        <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg>
                        <input id="marketplace-search-drawer" name="q" aria-label="Search products, brands, and stores" role="combobox" aria-autocomplete="list" aria-controls="marketplace-search-results-drawer" :aria-expanded="open.toString()" :aria-activedescendant="active >= 0 ? 'marketplace-drawer-result-' + active : null" @input.debounce.275ms="load()" @focus="load()" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter="select($event)" @keydown.escape="open = false" x-model="query" value="{{ $search }}" autocomplete="off" placeholder="Search products, brands, and stores" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-ink placeholder:text-muted focus:ring-0">
                        <button x-cloak x-show="query.length" type="button" @click="clear(); $nextTick(() => $el.previousElementSibling.focus())" class="grid size-7 place-items-center rounded-md transition hover:bg-canvas" aria-label="Clear search"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg></button>
                    </label>
                    @include('components.marketplace.search-suggestions', ['listId' => 'marketplace-search-results-drawer', 'optionPrefix' => 'marketplace-drawer-result-'])
                </form>
            @endif

            @if ($isSellerWorkspace)
                <div class="mx-4 mt-4 flex items-center gap-3 workspace-sidebar-card">
                    <span class="workspace-sidebar-avatar" aria-hidden="true">
                        @if ($sellerTenant?->store?->logo_path)
                            <img src="{{ Storage::disk('public')->url($sellerTenant->store->logo_path) }}" alt="" class="size-full object-cover">
                        @else
                            {{ Str::upper(Str::substr($sellerTenant?->name ?? 'S', 0, 1)) }}
                        @endif
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs text-muted">Your store</p>
                        <p class="mt-0.5 truncate text-sm font-semibold text-ink">{{ $sellerTenant?->name ?? 'Your store' }}</p>
                    </div>
                </div>
                <x-marketplace.seller-nav-links mode="mobile" />
                <div class="space-y-1 border-t border-line p-4">
                    @if ($sellerTenant)
                        <a href="{{ route('catalog.store', $sellerTenant->slug) }}" @click="closeDrawer" class="workspace-nav-link">View storefront</a>
                    @endif
                    <a href="{{ route('profile.edit') }}" @click="closeDrawer" class="workspace-nav-link">Account</a>
                    <button type="button" data-clerk-sign-out @click="closeDrawer" class="workspace-nav-link w-full text-left">Sign out</button>
                </div>
            @elseif ($isCustomerWorkspace)
                <x-marketplace.customer-nav-links mode="mobile" />
                <div class="border-t border-line p-4">
                    <button type="button" data-clerk-sign-out @click="closeDrawer" class="workspace-nav-link w-full text-left">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3" /><path d="M12 3h6a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3h-6" /></svg>
                        <span>Sign out</span>
                    </button>
                </div>
            @else
                <nav class="flex-1 space-y-1 overflow-y-auto p-4" aria-label="Mobile marketplace navigation">
                    <a href="{{ route('home') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-md px-3 text-sm font-medium text-ink transition hover:bg-canvas">Marketplace</a>
                    <a href="{{ route('catalog.index') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-md px-3 text-sm font-medium text-ink transition hover:bg-canvas">All products</a>
                    <a href="{{ route('catalog.index', ['sort' => 'newest']) }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-md px-3 text-sm font-medium text-ink transition hover:bg-canvas">New arrivals</a>
                    <a href="{{ route('catalog.top-products') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-md px-3 text-sm font-medium text-ink transition hover:bg-canvas">Best sellers</a>
                    <a href="{{ route('catalog.index', ['on_sale' => 1]) }}" @click="closeDrawer" class="flex min-h-11 items-center gap-2 rounded-md px-3 text-sm font-medium text-ink transition hover:bg-canvas">
                        <span class="size-1.5 rounded-full bg-danger" aria-hidden="true"></span>
                        Deals
                    </a>
                    <a href="{{ route('catalog.stores') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-md px-3 text-sm font-medium text-ink transition hover:bg-canvas">Stores</a>
                    @if ($showCategoryMenu)
                        <div x-data="{ drawerCatsOpen: false }" class="pt-1">
                            <button type="button" @click="drawerCatsOpen = !drawerCatsOpen" :aria-expanded="drawerCatsOpen.toString()" class="flex min-h-11 w-full items-center justify-between rounded-md px-3 text-sm font-medium text-ink transition hover:bg-canvas">
                                <span>Categories</span>
                                <svg class="size-4 text-muted transition-transform duration-150" :class="drawerCatsOpen && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                            </button>
                            <div x-cloak x-show="drawerCatsOpen" class="mt-1 space-y-0.5 pl-2">
                                @foreach ($headerCategories as $category)
                                    <a href="{{ route('catalog.index', ['category' => $category->slug]) }}" @click="closeDrawer" class="flex min-h-11 items-center gap-2.5 rounded-md px-3 text-sm text-muted transition hover:bg-canvas hover:text-ink">
                                        <x-category-icon :category="$category" class="size-4 shrink-0 text-ink" />
                                        <span class="truncate">{{ $category->name }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <a href="{{ route('home') }}#shop-by-category" @click="closeDrawer" class="flex min-h-11 items-center rounded-md px-3 text-sm font-medium text-ink transition hover:bg-canvas">Categories</a>
                    @endif
                    @if ($isBuyer)
                        <div class="my-2 border-t border-line pt-2">
                            <a href="{{ route('orders.index') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-md px-3 text-sm font-medium text-ink transition hover:bg-canvas">My Purchases</a>
                            <a href="{{ route('delivery-addresses.index') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-md px-3 text-sm font-medium text-ink transition hover:bg-canvas">Delivery Addresses</a>
                            <a href="{{ route('reviews.index') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-md px-3 text-sm font-medium text-ink transition hover:bg-canvas">My Reviews</a>
                        </div>
                    @endif
                    @if ($canUseMessages)
                        <a href="{{ $buyerMessagesUrl }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-md px-3 text-sm font-medium text-ink transition hover:bg-canvas">Messages</a>
                    @endif
                    @if ($user?->account_type === 'seller' && ! $sellerTenant)
                        <a href="{{ route('seller-application.create') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-md px-3 text-sm font-semibold text-brand transition hover:bg-[#eef3ff]">{{ $sellerEntry }}</a>
                    @elseif ($isBuyer && ! $sellerTenant)
                        <a href="{{ route('seller-application.create') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-md px-3 text-sm font-semibold text-brand transition hover:bg-[#eef3ff]">Start selling</a>
                    @elseif ($user?->isPlatformAdministrator())
                        <a href="{{ route('dashboard') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-md px-3 text-sm font-semibold text-brand transition hover:bg-[#eef3ff]">Admin workspace</a>
                    @endif
                </nav>
                <div class="space-y-1 border-t border-line p-4">
                    @auth
                        <a href="{{ route('profile.edit') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-md px-3 text-sm font-medium text-ink transition hover:bg-canvas">Account</a>
                        <button type="button" data-clerk-sign-out @click="closeDrawer" class="flex min-h-11 w-full items-center rounded-md px-3 text-left text-sm font-medium text-muted transition hover:bg-rose-50 hover:text-rose-800">Sign out</button>
                    @else
                        <a href="{{ route('login') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-md px-3 text-sm font-semibold text-ink transition hover:bg-canvas">Sign in</a>
                        <a href="{{ route('register') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-md px-3 text-sm font-semibold text-brand transition hover:bg-[#eef3ff]">Create account</a>
                        <a href="{{ route('home') }}" @click="closeDrawer" class="flex min-h-11 items-center rounded-md px-3 text-sm font-medium text-muted transition hover:bg-canvas">Continue shopping</a>
                    @endauth
                </div>
            @endif
        </aside>
    </header>
@endif
