<x-app-layout>
    <x-slot name="header"><div class="sc-page-head"><div><a href="{{ route('seller.products.show', $product) }}" class="sc-back"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>{{ Str::limit($product->name, 40) }}</a><h1 class="sc-title">Edit product</h1></div></div></x-slot>

    @include('seller.products.form', ['formAction' => route('seller.products.update', $product), 'formMethod' => 'PATCH', 'submitLabel' => 'Save changes'])
</x-app-layout>
