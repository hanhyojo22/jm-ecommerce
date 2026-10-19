<x-app-layout>
    <x-slot name="header"><div><h1 class="text-[28px] font-bold text-ink leading-[1.3]">Add product</h1></div></x-slot>

    @include('seller.products.form', ['product' => null, 'formAction' => route('seller.products.store'), 'formMethod' => 'POST', 'submitLabel' => 'Save product'])
</x-app-layout>
