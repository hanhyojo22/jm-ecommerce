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
        <meta name="reverb-app-key" content="{{ config('broadcasting.connections.reverb.key') }}">
        <meta name="reverb-host" content="{{ config('broadcasting.connections.reverb.options.host') }}">
        <meta name="reverb-port" content="{{ config('broadcasting.connections.reverb.options.port') }}">
        <meta name="reverb-scheme" content="{{ config('broadcasting.connections.reverb.options.scheme') }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-slate-50 font-sans antialiased text-slate-900">
        <a href="#main-content" class="sr-only fixed left-4 top-4 z-[100] rounded-lg bg-slate-950 px-4 py-3 text-sm font-semibold text-white focus:not-sr-only focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2">Skip to content</a>
        <div class="min-h-screen bg-slate-50">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="border-b border-slate-200 bg-white">
                    <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 sm:py-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            @if (auth()->user()?->isPlatformAdministrator())
                <div class="min-h-[calc(100vh-72px)] bg-[#faf8f5] lg:grid lg:grid-cols-[15rem_minmax(0,1fr)]">
                    @include('layouts.admin-sidebar')
                    <main id="main-content" tabindex="-1" class="min-w-0">
                        {{ $slot }}
                    </main>
                </div>
            @else
                <main id="main-content" tabindex="-1">
                    {{ $slot }}
                </main>
            @endif
        </div>
        @livewireScripts
    </body>
</html>
