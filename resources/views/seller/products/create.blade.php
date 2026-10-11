<x-app-layout>
    <x-slot name="header"><div><h1 class="text-[28px] font-bold text-ink leading-[1.3]">Add product</h1><p class="mt-2 text-sm text-muted">Add photos and details, then set stock for each color and size you sell.</p></div></x-slot>

    @include('seller.products.form', ['product' => null, 'formAction' => route('seller.products.store'), 'formMethod' => 'POST', 'submitLabel' => 'Save product'])
</x-app-layout>
