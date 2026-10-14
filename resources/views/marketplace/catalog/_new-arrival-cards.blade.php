@foreach ($products as $product)
    <x-marketplace.top-product-card :product="$product" />
@endforeach
