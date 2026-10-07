<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-medium text-muted">{{ $store->name }}</p><h1 class="mt-1 text-[28px] font-bold text-ink leading-[1.55]">Add product</h1><p class="mt-2 text-sm text-muted">Start with the essentials. You can refine this listing later.</p></div></x-slot>

    @include('seller.products.form', ['product' => null, 'formAction' => route('seller.products.store'), 'formMethod' => 'POST', 'submitLabel' => 'Save product'])
</x-app-layout>
