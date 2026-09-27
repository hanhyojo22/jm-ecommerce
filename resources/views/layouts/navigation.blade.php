<nav x-data="{ open: false }" class="border-b border-gray-100 bg-white">
    @php($user = Auth::user())
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 justify-between">
            <div class="flex">
                <div class="flex shrink-0 items-center"><a href="{{ route($user->landingRouteName()) }}"><x-application-logo class="block h-9 w-auto fill-current text-gray-800" /></a></div>
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    @if ($user->account_type === 'buyer')
                        <x-nav-link :href="route('catalog.index')" :active="request()->routeIs('catalog.*')">{{ __('Shop') }}</x-nav-link>
                        <x-nav-link :href="route('cart.index')" :active="request()->routeIs('cart.*')" class="inline-flex items-center gap-2">{{ __('Cart') }} @if ($cartItemCount > 0)<span class="inline-flex min-w-5 items-center justify-center rounded-full bg-emerald-700 px-1.5 py-0.5 text-xs font-bold text-white" aria-label="{{ $cartItemCount }} items in cart">{{ $cartItemCount }}</span>@endif</x-nav-link>
                        <x-nav-link :href="route('orders.index')" :active="request()->routeIs('orders.*')">{{ __('My Orders') }}</x-nav-link>
                    @elseif ($user->account_type === 'seller')
                        <x-nav-link :href="route('seller.dashboard')" :active="request()->routeIs('seller.dashboard') || request()->routeIs('seller-application.*')">{{ __('Seller Center') }}</x-nav-link>
                        @if ($user->tenantMemberships()->exists())
                            <x-nav-link :href="route('seller.products.index')" :active="request()->routeIs('seller.products.*')">{{ __('My Products') }}</x-nav-link>
                            <x-nav-link :href="route('seller.orders.index')" :active="request()->routeIs('seller.orders.*')">{{ __('Seller Orders') }}</x-nav-link>
                        @endif
                    @else
                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">{{ __('Dashboard') }}</x-nav-link>
                        <x-nav-link :href="route('admin.seller-applications.index')" :active="request()->routeIs('admin.seller-applications.*')">{{ __('Applications') }}</x-nav-link>
                        <x-nav-link :href="route('admin.stores.index')" :active="request()->routeIs('admin.stores.*')">{{ __('Stores') }}</x-nav-link>
                        <x-nav-link :href="route('admin.customers.index')" :active="request()->routeIs('admin.customers.*')">{{ __('Customers') }}</x-nav-link>
                        <x-nav-link :href="route('admin.products.index')" :active="request()->routeIs('admin.products.*')">{{ __('Products') }}</x-nav-link>
                        <x-nav-link :href="route('admin.orders.index')" :active="request()->routeIs('admin.orders.*')">{{ __('Orders') }}</x-nav-link>
                        @if ($user->isOwner())<x-nav-link :href="route('admin.accounts.index')" :active="request()->routeIs('admin.accounts.*')">{{ __('Admin Management') }}</x-nav-link>@endif
                    @endif
                </div>
            </div>
            <div class="hidden sm:flex sm:items-center sm:ms-6"><x-dropdown align="right" width="48"><x-slot name="trigger"><button class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 shadow-sm transition hover:border-emerald-700 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2" aria-label="Open account menu"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="3.25"/><path d="M4.5 20c.8-3.45 3.35-5.25 7.5-5.25s6.7 1.8 7.5 5.25"/></svg></button></x-slot><x-slot name="content"><x-dropdown-link :href="route('profile.edit')">{{ __('My account') }}</x-dropdown-link><form method="POST" action="{{ route('logout') }}">@csrf <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">{{ __('Log out') }}</x-dropdown-link></form></x-slot></x-dropdown></div>
            <div class="-me-2 flex items-center sm:hidden"><button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-controls="app-navigation-menu" class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-slate-600 transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2" aria-label="Open navigation menu"><svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10m-6 6h6" /></svg></button></div>
        </div>
    </div>
    <div id="app-navigation-menu" :class="{'block': open, 'hidden': ! open}" @keydown.escape.window="open = false" class="hidden border-t border-slate-200 bg-[#f7f7f2] sm:hidden"><div class="space-y-1 pb-3 pt-2">
        @if ($user->account_type === 'buyer')
            <x-responsive-nav-link :href="route('catalog.index')" :active="request()->routeIs('catalog.*')">{{ __('Shop') }}</x-responsive-nav-link><x-responsive-nav-link :href="route('cart.index')" :active="request()->routeIs('cart.*')">{{ __('Cart') }} @if ($cartItemCount > 0)<span class="ml-1 inline-flex min-w-5 items-center justify-center rounded-full bg-emerald-700 px-1.5 py-0.5 text-xs font-bold text-white">{{ $cartItemCount }}</span>@endif</x-responsive-nav-link><x-responsive-nav-link :href="route('orders.index')" :active="request()->routeIs('orders.*')">{{ __('My Orders') }}</x-responsive-nav-link>
        @elseif ($user->account_type === 'seller')
            <x-responsive-nav-link :href="route('seller.dashboard')" :active="request()->routeIs('seller.dashboard') || request()->routeIs('seller-application.*')">{{ __('Seller Center') }}</x-responsive-nav-link>@if ($user->tenantMemberships()->exists())<x-responsive-nav-link :href="route('seller.products.index')" :active="request()->routeIs('seller.products.*')">{{ __('My Products') }}</x-responsive-nav-link><x-responsive-nav-link :href="route('seller.orders.index')" :active="request()->routeIs('seller.orders.*')">{{ __('Seller Orders') }}</x-responsive-nav-link>@endif
        @else
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">{{ __('Dashboard') }}</x-responsive-nav-link><x-responsive-nav-link :href="route('admin.seller-applications.index')" :active="request()->routeIs('admin.seller-applications.*')">{{ __('Applications') }}</x-responsive-nav-link><x-responsive-nav-link :href="route('admin.stores.index')" :active="request()->routeIs('admin.stores.*')">{{ __('Stores') }}</x-responsive-nav-link><x-responsive-nav-link :href="route('admin.customers.index')" :active="request()->routeIs('admin.customers.*')">{{ __('Customers') }}</x-responsive-nav-link><x-responsive-nav-link :href="route('admin.products.index')" :active="request()->routeIs('admin.products.*')">{{ __('Products') }}</x-responsive-nav-link><x-responsive-nav-link :href="route('admin.orders.index')" :active="request()->routeIs('admin.orders.*')">{{ __('Orders') }}</x-responsive-nav-link>@if ($user->isOwner())<x-responsive-nav-link :href="route('admin.accounts.index')" :active="request()->routeIs('admin.accounts.*')">{{ __('Admin Management') }}</x-responsive-nav-link>@endif
        @endif
    </div><div class="border-t border-slate-200 py-3"><x-responsive-nav-link :href="route('profile.edit')">{{ __('My account') }}</x-responsive-nav-link><form method="POST" action="{{ route('logout') }}">@csrf <x-responsive-nav-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">{{ __('Log out') }}</x-responsive-nav-link></form></div></div>
</nav>
