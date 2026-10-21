<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        <meta name="clerk-publishable-key" content="{{ config('services.clerk.publishable_key') }}">
        <meta name="clerk-sign-in-url" content="{{ config('services.clerk.sign_in_url') }}">
        <meta name="clerk-sign-up-url" content="{{ config('services.clerk.sign_up_url') }}">
        @auth('clerk')
            {{-- Only signed-in visitors get one of these: it is what lets navigation renew a lapsed
                 session cookie before it opens a protected page, and it keeps guests untouched. --}}
            <meta name="clerk-session-refresh-endpoint" content="{{ route('clerk.session.refresh') }}">
        @endauth
        <meta name="reverb-app-key" content="{{ config('broadcasting.connections.reverb.key') }}">
        <meta name="reverb-host" content="{{ config('broadcasting.connections.reverb.options.host') }}">
        <meta name="reverb-port" content="{{ config('broadcasting.connections.reverb.options.port') }}">
        <meta name="reverb-scheme" content="{{ config('broadcasting.connections.reverb.options.scheme') }}">

        <title>@yield('title', isset($title) ? $title : (isset($pageTitle) ? $pageTitle.' · '.config('app.name', 'Laravel') : config('app.name', 'Laravel')))</title>

        <!-- Fonts -->
        @include('partials.fonts')

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-canvas font-sans antialiased text-ink">
        <a href="#main-content" class="sr-only fixed left-4 top-4 z-[100] rounded-lg bg-ink px-4 py-3 text-sm font-semibold text-white focus:not-sr-only focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Skip to content</a>
        <div class="min-h-screen bg-canvas">
            @php($isSellerMessages = request()->routeIs('messages.index') ? request('context') !== 'buying' : (request()->routeIs('messages.show') && request()->route('conversation') instanceof \App\Models\Conversation && request()->route('conversation')->buyer_id !== auth()->id()))
            @php($isSellerWorkspace = auth()->user()?->account_type === 'seller' && (request()->routeIs('seller.dashboard', 'seller.products.*', 'seller.orders.*', 'seller.returns.*', 'seller.reviews.*', 'seller.earnings.*', 'seller.store-settings.*') || $isSellerMessages || (request()->routeIs('profile.edit') && auth()->user()->preferred_workspace === 'seller' && auth()->user()->sellerTenant() !== null)))
            @php($isAdminWorkspace = auth()->user()?->isPlatformAdministrator() && request()->routeIs('dashboard', 'admin.*'))
            @php($isCustomerWorkspace = in_array(auth()->user()?->account_type, ['buyer', 'seller'], true) && ! $isSellerWorkspace && request()->routeIs('orders.*', 'returns.show', 'reviews.index', 'delivery-addresses.*', 'messages.*', 'profile.edit'))
            @if ($isAdminWorkspace)
                {{-- The admin header belongs to the workspace content column. --}}
            @elseif (! $isSellerWorkspace && ! $isCustomerWorkspace)
                @include('layouts.navigation')
            @endif

            <!-- Page Content -->
            @if ($isAdminWorkspace)
                <div class="min-h-screen bg-canvas lg:grid lg:grid-cols-[258px_minmax(0,1fr)]">
                    @include('layouts.admin-sidebar')
                    <main id="main-content" tabindex="-1" class="workspace-main min-w-0">
                        <x-marketplace.admin-header :user="auth()->user()" />
                        @isset($header)
                            <header class="px-[18px] pb-2 pt-6 sm:px-8 lg:pt-8">
                                {{ $header }}
                            </header>
                        @endisset
                        {{ $slot }}
                    </main>
                </div>
            @elseif ($isSellerWorkspace)
                <div data-workspace="seller" x-data="{ sellerSidebarOpen: true }" @seller-sidebar-toggle.window="sellerSidebarOpen = !sellerSidebarOpen" :class="{ 'seller-sidebar-collapsed': !sellerSidebarOpen }" class="seller-workspace min-h-screen bg-canvas lg:grid lg:grid-cols-[224px_minmax(0,1fr)]">
                    @include('layouts.seller-sidebar', ['sellerTenant' => auth()->user()?->sellerTenant()])
                    <main id="main-content" tabindex="-1" class="workspace-main min-w-0">
                        @include('layouts.navigation')
                        @isset($header)
                            <header class="px-4 pb-2 pt-6 sm:px-6 lg:px-7 lg:pt-8">
                                {{ $header }}
                            </header>
                        @endisset
                        {{ $slot }}
                    </main>
                </div>
            @elseif ($isCustomerWorkspace)
                <div data-workspace="customer" x-data="{ customerSidebarOpen: true }" @customer-sidebar-toggle.window="customerSidebarOpen = !customerSidebarOpen" :class="{ 'customer-sidebar-collapsed': !customerSidebarOpen }" class="customer-workspace min-h-screen bg-canvas lg:grid lg:grid-cols-[224px_minmax(0,1fr)]">
                    @include('layouts.customer-sidebar')
                    <main id="main-content" tabindex="-1" class="workspace-main min-w-0">
                        @include('layouts.navigation')
                        @isset($header)
                            <header class="px-4 pb-2 pt-6 sm:px-6 lg:px-7 lg:pt-8">
                                {{ $header }}
                            </header>
                        @endisset
                        {{ $slot }}
                    </main>
                </div>
            @else
                @isset($header)
                    <header class="border-b border-line bg-white">
                        <div class="mx-auto max-w-[1320px] px-[18px] py-5 sm:px-8 sm:py-6">
                            {{ $header }}
                        </div>
                    </header>
                @endisset
                <main id="main-content" tabindex="-1">
                    {{ $slot }}
                </main>
            @endif
            @unless ($isSellerWorkspace || $isCustomerWorkspace)
                <x-marketplace.footer />
            @endunless
        </div>
        <x-cookie-consent />
        @livewireScripts
    </body>
</html>
