{{-- Stacked photos of the first two items, then the first item's name and how many more. --}}
<span class="sc-items">
    <span class="sc-thumb-stack">@foreach ($sellerOrder->items->take(2) as $item)<span class="sc-thumb">@if ($item->product?->images->first())<img src="{{ Storage::disk('product_images')->url($item->product->images->first()->path) }}" alt="" loading="lazy">@endif</span>@endforeach</span>
    <span class="min-w-0 truncate">{{ $sellerOrder->items->first()?->product_name }}@if ($otherItems > 0) <span class="sc-meta">+{{ $otherItems }} more</span>@endif</span>
</span>
