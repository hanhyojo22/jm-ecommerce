<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="clerk-publishable-key" content="{{ config('services.clerk.publishable_key') }}">
    <meta name="clerk-sign-in-url" content="{{ config('services.clerk.sign_in_url') }}">
    <meta name="clerk-sign-up-url" content="{{ config('services.clerk.sign_up_url') }}">
    <title>Stores · JMMart</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-[#f7f7f2] font-sans text-slate-900 antialiased">
    <x-marketplace.header :search="$search" />

    <main class="mx-auto max-w-[88rem] px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <div class="max-w-2xl"><p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-800">Marketplace directory</p><h1 class="mt-2 text-3xl font-bold tracking-[-.035em] text-[#111827] sm:text-4xl">Discover independent stores</h1><p class="mt-3 text-base leading-7 text-slate-600">Browse active JMMart sellers and the products they offer.</p></div>
        @if($search)<p class="mt-8 text-sm text-slate-600">Stores matching <span class="font-semibold text-slate-900">“{{ $search }}”</span></p>@endif
        <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="Marketplace stores">
            @forelse($stores as $store)
                <a href="{{ route('catalog.store', $store->slug) }}" class="group min-w-0 rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2">
                    <div class="flex items-center gap-3"><span class="grid size-11 shrink-0 place-items-center overflow-hidden rounded-xl bg-emerald-100 text-sm font-bold text-emerald-900">@if($store->logo_path)<img src="{{ Storage::disk('public')->url($store->logo_path) }}" alt="{{ $store->name }} logo" class="size-full object-cover">@else{{ Str::upper(Str::substr($store->name, 0, 1)) }}@endif</span><div class="min-w-0"><h2 class="truncate text-base font-bold text-[#111827] group-hover:text-emerald-900">{{ $store->name }}</h2><p class="truncate text-sm text-slate-500">{{ $store->city ?: 'JMMart seller' }}</p></div></div>
                    <div class="mt-5 flex items-center justify-between gap-3 border-t border-slate-100 pt-4 text-sm"><span class="font-semibold text-slate-700">{{ $store->public_products_count }} {{ Str::plural('product', $store->public_products_count) }}</span><span class="font-semibold text-emerald-800">Visit store →</span></div>
                </a>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center"><p class="font-semibold text-slate-900">No stores found</p><a href="{{ route('catalog.stores') }}" class="mt-3 inline-flex text-sm font-semibold text-emerald-800 hover:text-emerald-950">View all stores</a></div>
            @endforelse
        </section>
        @if($stores->hasPages())<div class="mt-8">{{ $stores->links() }}</div>@endif
    </main>
</body>
</html>
