<div class="flex flex-wrap items-center gap-2">
    <a href="{{ route('seller.products.show', $product) }}" class="inline-flex min-h-11 items-center rounded-lg border border-line px-3 text-xs font-semibold text-ink hover:border-brand hover:text-brand">Details</a>
    @if ($canManageProducts && in_array($product->status, ['draft', 'changes_required', 'rejected'], true))
        <a href="{{ route('seller.products.edit', $product) }}" aria-label="Edit listing" class="grid size-11 place-items-center rounded-lg border border-line text-ink hover:border-brand hover:text-brand"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 20 4-.8L19 8.2 15.8 5 4.8 16zM13.7 7.1l3.2 3.2"/></svg></a>
        <form method="POST" action="{{ route('seller.products.submit', $product) }}">@csrf<button type="submit" aria-label="Submit for review" class="inline-flex min-h-11 items-center rounded-lg bg-brand px-3 text-xs font-semibold text-white hover:bg-brand-hover">Submit</button></form>
    @endif
</div>
