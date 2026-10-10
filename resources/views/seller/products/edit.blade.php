<x-app-layout>
    <x-slot name="header"><div><h1 class="text-[28px] font-bold text-ink leading-[1.3]">Edit product</h1><p class="mt-2 text-sm text-muted">Update what customers see in your store.</p></div></x-slot>

    @include('seller.products.form', ['formAction' => route('seller.products.update', $product), 'formMethod' => 'PATCH', 'submitLabel' => 'Save changes'])
</x-app-layout>
