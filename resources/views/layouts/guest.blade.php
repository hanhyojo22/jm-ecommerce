<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="clerk-publishable-key" content="{{ config('services.clerk.publishable_key') }}">
        <meta name="clerk-sign-in-url" content="{{ config('services.clerk.sign_in_url') }}">
        <meta name="clerk-sign-up-url" content="{{ config('services.clerk.sign_up_url') }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#f7f7f5] font-sans text-slate-900 antialiased">
        <main class="flex min-h-screen items-center justify-center px-4 py-10 sm:px-6">
            <section class="w-full max-w-md text-center">
                <a href="{{ route('catalog.index') }}" class="inline-flex items-center gap-2 text-lg font-semibold tracking-tight text-slate-950">
                    <span class="grid size-10 place-items-center rounded-xl bg-slate-950 text-sm font-bold tracking-tight text-white">JM</span>
                    JMMart
                </a>

                <div class="mt-8 text-left">
                    {{ $slot }}
                </div>

                <a href="{{ route('catalog.index') }}" class="mt-6 inline-block text-sm font-medium text-slate-500 transition hover:text-slate-950">Back to marketplace</a>
            </section>
        </main>
    </body>
</html>
