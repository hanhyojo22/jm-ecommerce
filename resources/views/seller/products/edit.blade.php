<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-semibold uppercase tracking-[.16em] text-emerald-700">{{ $store->name }}</p><h1 class="mt-1 text-3xl font-semibold text-slate-950">Edit product</h1><p class="mt-2 text-sm text-slate-600">Update what customers see in your store.</p></div></x-slot>

    @include('seller.products.form', ['formAction' => route('seller.products.update', $product), 'formMethod' => 'PATCH', 'submitLabel' => 'Save changes'])
</x-app-layout>
