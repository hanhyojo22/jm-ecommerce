<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-medium text-muted">{{ $store->name }}</p><h1 class="mt-1 text-[28px] font-bold text-ink leading-[1.55]">Edit product</h1><p class="mt-2 text-sm text-muted">Update what customers see in your store.</p></div></x-slot>

    @include('seller.products.form', ['formAction' => route('seller.products.update', $product), 'formMethod' => 'PATCH', 'submitLabel' => 'Save changes'])
</x-app-layout>
