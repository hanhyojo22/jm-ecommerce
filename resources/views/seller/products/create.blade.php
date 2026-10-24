<x-app-layout>
    <x-slot name="header"><div class="sc-page-head"><div><a href="{{ route('seller.products.index') }}" class="sc-back"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>Products</a><h1 class="sc-title">Add product</h1></div></div></x-slot>

    @include('seller.products.form', ['product' => null, 'formAction' => route('seller.products.store'), 'formMethod' => 'POST', 'submitLabel' => 'Save product'])
</x-app-layout>
