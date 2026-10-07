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

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-canvas font-sans text-ink antialiased">
        <main class="flex min-h-screen items-center justify-center px-[18px] py-10 sm:px-6">
            <section class="w-full max-w-md text-center">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-lg font-semibold tracking-tight text-ink">
                    <x-application-logo class="size-10 fill-current text-brand" />
                    JMMart
                </a>

                <div class="mt-8 text-left">
                    {{ $slot }}
                </div>

                <a href="{{ route('home') }}" class="mt-6 inline-block text-sm font-medium text-muted transition hover:text-ink">Back to marketplace</a>
            </section>
        </main>
    </body>
</html>
