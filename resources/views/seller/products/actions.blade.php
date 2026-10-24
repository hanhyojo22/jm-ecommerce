<div class="flex items-center justify-end gap-2 max-xl:justify-start">
    @if ($canManageProducts && in_array($product->status, ['draft', 'changes_required', 'rejected'], true))
        <form method="POST" action="{{ route('seller.products.submit', $product) }}">@csrf<button type="submit" aria-label="Submit for review" class="sc-link min-h-10 px-1">Submit</button></form>
        <a href="{{ route('seller.products.edit', $product) }}" aria-label="Edit listing" title="Edit listing" class="seller-outline-button !w-10 !px-0"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 20 4-.8L19 8.2 15.8 5 4.8 16zM13.7 7.1l3.2 3.2"/></svg></a>
    @endif
    <a href="{{ route('seller.products.show', $product) }}" class="seller-outline-button">Details</a>
</div>
