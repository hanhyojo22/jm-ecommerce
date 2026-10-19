<section id="returns" class="rounded-[18px] border border-line bg-surface p-5 sm:p-6">
    <h2 class="text-lg font-semibold text-ink">Returns and replacements</h2>
    <p class="mt-1 text-sm text-muted">For damaged, defective, wrong, or materially misdescribed goods, ask the seller to help within seven days of delivery. Later warranty and statutory claims can still go to support.</p>
    @foreach ($order->sellerOrders as $sellerOrder)
        @if ($sellerOrder->status === 'delivered')
            <div class="mt-5 border-t border-line pt-5">
                <h3 class="font-semibold text-ink">{{ $sellerOrder->store->name }}</h3>
                @foreach ($sellerOrder->returnCases as $case)
                    <a href="{{ route('returns.show', $case) }}" class="mt-2 block text-sm font-semibold text-brand underline">Case {{ str($case->status)->replace('_', ' ')->headline() }} · view history and next action</a>
                @endforeach
                @if ($sellerOrder->delivered_at && now()->lessThanOrEqualTo($sellerOrder->delivered_at->copy()->addDays(7)))
                    <form method="POST" action="{{ route('returns.store', $sellerOrder) }}" enctype="multipart/form-data" class="mt-4 grid gap-4">
                        @csrf
                        <fieldset class="grid gap-2">
                            <legend class="field-label">Affected items and quantities</legend>
                            @foreach ($sellerOrder->items as $item)
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <label for="return-qty-{{ $item->id }}">{{ $item->product_name }} (purchased {{ $item->quantity }})</label>
                                    <input type="hidden" name="items[{{ $loop->index }}][order_item_id]" value="{{ $item->id }}">
                                    <input id="return-qty-{{ $item->id }}" type="number" min="0" max="{{ $item->quantity }}" value="0" name="items[{{ $loop->index }}][quantity]" class="field-control w-20">
                                </div>
                            @endforeach
                        </fieldset>
                        <label><span class="field-label">What went wrong?</span><select name="reason" required class="field-control"><option value="damaged">Damaged</option><option value="defective">Defective</option><option value="wrong_item">Wrong item</option><option value="misdescribed">Materially misdescribed</option></select></label>
                        <label><span class="field-label">Preferred remedy</span><select name="requested_remedy" required class="field-control"><option value="refund">Refund</option><option value="replacement">Replacement</option></select></label>
                        <label><span class="field-label">Details</span><textarea name="details" required minlength="10" maxlength="2000" rows="3" class="field-control">{{ old('details') }}</textarea></label>
                        <label><span class="field-label">Photos (optional, up to three)</span><input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" class="field-control"></label>
                        <button class="min-h-11 justify-self-start rounded-lg bg-brand px-5 text-sm font-semibold text-white">Request seller review</button>
                    </form>
                @else
                    <p class="mt-2 text-sm text-muted">The seven-day online request window has ended, or the delivery date is uncertain. Your legal rights may continue.</p>
                    <a href="mailto:itservices@jmsolutionitservices.com?subject=Later%20return%20claim%20{{ urlencode($order->number) }}" class="mt-2 inline-flex min-h-11 items-center font-semibold text-brand underline">Contact support</a>
                @endif
            </div>
        @endif
    @endforeach
</section>
