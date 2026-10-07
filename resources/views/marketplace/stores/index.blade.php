<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <meta name="clerk-publishable-key" content="{{ config('services.clerk.publishable_key') }}">
    <meta name="clerk-sign-in-url" content="{{ config('services.clerk.sign_in_url') }}">
    <meta name="clerk-sign-up-url" content="{{ config('services.clerk.sign_up_url') }}">
    <title>Stores · JMMart</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-canvas font-sans text-ink antialiased">
    <x-marketplace.header :search="$search" />

    <main class="mx-auto min-h-[calc(100dvh-4.5rem)] max-w-[1320px] px-[18px] pb-16 sm:px-8">
        <div>
            <nav aria-label="Breadcrumb" class="mb-2 mt-5 flex items-center gap-2 text-[13px] text-muted">
                <a href="{{ route('catalog.index') }}" class="font-medium transition hover:text-brand-hover">Marketplace</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page" class="font-semibold text-ink">Stores</span>
            </nav>

            <div class="flex flex-col gap-5 pt-2 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-2xl">
                    <h1 class="text-[28px] font-bold leading-[1.55] tracking-[-0.02em] text-ink">Discover stores</h1>
                    <p class="mt-1 text-sm text-muted">Browse active JMMart sellers and the products they offer.</p>
                </div>
                <form method="GET" action="{{ route('catalog.stores') }}" class="flex w-full gap-2 sm:max-w-md">
                    <label for="store-search" class="flex min-h-11 min-w-0 flex-1 items-center gap-3 rounded-[10px] border border-line bg-surface px-3 text-muted focus-within:border-brand focus-within:ring-2 focus-within:ring-brand/20">
                        <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                        <span class="sr-only">Search stores</span>
                        <input id="store-search" name="q" value="{{ $search }}" type="search" placeholder="Search stores or sellers" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-ink placeholder:text-muted focus:ring-0">
                    </label>
                    <button class="min-h-11 shrink-0 rounded-[10px] bg-brand px-4 text-sm font-semibold text-white transition hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Search</button>
                </form>
            </div>

            @if ($search)
                <p class="mt-7 text-sm text-muted">{{ number_format($stores->total()) }} {{ Str::plural('store', $stores->total()) }} matching <span class="font-semibold text-ink">“{{ $search }}”</span></p>
            @endif

            <section class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="Marketplace stores">
                @forelse ($stores as $store)
                    <a href="{{ route('catalog.store', $store->slug) }}" class="group min-w-0 rounded-[14px] border border-line bg-surface p-5 transition hover:border-brand/50 hover:shadow-[0_12px_28px_rgba(15,23,42,.10)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                        <div class="flex items-center gap-3">
                            <span class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-[10px] bg-blue-50 text-sm font-bold text-brand-hover">
                                @if ($store->logo_path)
                                    <img src="{{ Storage::disk('public')->url($store->logo_path) }}" alt="{{ $store->name }} logo" class="size-full object-cover">
                                @else
                                    {{ Str::upper(Str::substr($store->name, 0, 1)) }}
                                @endif
                            </span>
                            <div class="min-w-0">
                                <h2 class="truncate text-base font-semibold text-ink group-hover:text-brand-hover">{{ $store->name }}</h2>
                                <p class="truncate text-sm text-muted">{{ $store->city ?: 'JMMart seller' }}</p>
                            </div>
                        </div>
                        <div class="mt-5 flex items-center justify-between gap-3 border-t border-line pt-4 text-sm">
                            <span class="font-medium text-muted">{{ number_format($store->public_products_count) }} {{ Str::plural('product', $store->public_products_count) }}</span>
                            <span class="font-semibold text-brand group-hover:text-brand-hover">Visit store <span aria-hidden="true">→</span></span>
                        </div>
                    </a>
                @empty
                    <div class="col-span-full rounded-[14px] border border-dashed border-line bg-surface px-6 py-16 text-center">
                        <h2 class="font-semibold text-ink">No stores found</h2>
                        @if ($search)
                            <p class="mt-2 text-sm text-muted">Try another store or seller name.</p>
                            <a href="{{ route('catalog.stores') }}" class="mt-4 inline-flex min-h-11 items-center rounded-[10px] px-3 text-sm font-semibold text-brand hover:bg-blue-50 hover:text-brand-hover">Clear search</a>
                        @endif
                    </div>
                @endforelse
            </section>

            @if ($stores->hasPages())
                <div class="mt-8">{{ $stores->links('components.marketplace.pagination') }}</div>
            @endif
        </div>
    </main>

    <x-marketplace.footer />

    @livewireScripts
</body>
</html>
