<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-semibold uppercase tracking-[.16em] text-emerald-700">{{ $store->name }}</p><h1 class="mt-1 text-3xl font-semibold text-slate-950">Add product</h1><p class="mt-2 text-sm text-slate-600">Start with the essentials. You can refine this listing later.</p></div></x-slot>

    @include('seller.products.form', ['product' => null, 'formAction' => route('seller.products.store'), 'formMethod' => 'POST', 'submitLabel' => 'Save product'])
</x-app-layout>
