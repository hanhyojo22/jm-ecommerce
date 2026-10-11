<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Seller Center unavailable · {{ config('app.name', 'JMMart') }}</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="bg-canvas font-sans text-ink">
        <main class="grid min-h-screen place-items-center px-4 py-12">
            <section class="w-full max-w-xl rounded-[14px] border border-line bg-white p-6 text-center shadow-sm sm:p-10" role="alert">
                <h1 class="mt-3 text-2xl font-bold tracking-tight">We couldn’t load your overview</h1>
                <p class="mt-3 text-sm leading-6 text-muted">The latest store data is temporarily unavailable. Your listings and orders haven’t been changed.</p>
                <p class="mt-3 text-sm leading-6 text-muted">You can review products while we restore the overview, or try again in a few minutes.</p>
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <a href="{{ route('seller.products.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-line bg-white px-5 text-sm font-semibold text-ink transition hover:border-blue-300 hover:text-brand focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Open products</a>
                    <a href="{{ route('seller.dashboard') }}" class="inline-flex min-h-11 items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Try again</a>
                </div>
            </section>
        </main>
        <x-cookie-consent />
    </body>
</html>
