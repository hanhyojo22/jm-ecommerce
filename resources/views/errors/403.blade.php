<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>Access restricted · JMMart</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-canvas font-sans text-ink antialiased">
    <main class="mx-auto flex min-h-screen w-[92%] max-w-xl items-center px-[18px] py-16 sm:px-8">
        <div class="w-full rounded-[14px] border border-line bg-surface p-6 text-center sm:p-10">
            <span class="mx-auto grid size-14 place-items-center rounded-full bg-canvas text-muted" aria-hidden="true">
                <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="5" y="10" width="14" height="10" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3" /></svg>
            </span>
            <h1 class="mt-5 text-3xl font-bold tracking-[-0.03em] sm:text-4xl">Access restricted</h1>
            <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-muted">Sellers need an approved store for Seller Center, and admins need a platform administrator account. Your cart and browsing are unaffected.</p>
            <a href="{{ route('home') }}" class="mt-6 inline-flex min-h-12 items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Return to marketplace</a>
            <p class="mt-5 text-sm text-muted">
                Need access?
                <a href="{{ route('seller-application.create') }}" class="font-semibold text-brand hover:text-brand-hover">Apply to sell</a>
                <span aria-hidden="true"> · </span>
                <a href="{{ route('login') }}" class="font-semibold text-brand hover:text-brand-hover">Sign in</a>
            </p>
        </div>
    </main>
</body>
</html>
