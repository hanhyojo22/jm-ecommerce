<section id="returns" class="rounded-[18px] border border-line bg-surface p-5 sm:p-6">
    <h2 class="text-lg font-semibold text-ink">Returns and replacements</h2>
    <p class="mt-1 text-sm text-muted">For damaged, defective, wrong, or materially misdescribed goods, ask the seller to help within seven days of delivery. Later warranty and statutory claims can still go to support.</p>
    @foreach ($order->sellerOrders as $sellerOrder)
        @if ($sellerOrder->status === 'delivered')
            @php($windowEndsAt = $sellerOrder->delivered_at?->copy()->addDays(7))
            @php($windowOpen = $windowEndsAt && now()->lessThanOrEqualTo($windowEndsAt))
            @php($resubmitted = old('store_order') === $sellerOrder->id)
            <div class="mt-5 border-t border-line pt-5">
                <h3 class="font-semibold text-ink">{{ $sellerOrder->store->name }}</h3>
                @foreach ($sellerOrder->returnCases as $case)
                    @php($caseDeadline = $case->deadline())
                    <a href="{{ route('returns.show', $case) }}" class="mt-2 block text-sm font-semibold text-brand underline">
                        Case {{ str($case->status)->replace('_', ' ')->headline() }}@if ($caseDeadline) · next step due {{ $caseDeadline->format('M j, g:i A') }}@endif · view history and next action
                    </a>
                @endforeach
                @if ($windowOpen)
                    <p class="mt-3 text-sm text-muted">You can request help with this store's items until <strong>{{ $windowEndsAt->format('M j, Y · g:i A') }}</strong>.</p>
                    <details class="mt-3" @if ($resubmitted || $order->sellerOrders->count() === 1) open @endif>
                    <summary class="min-h-11 cursor-pointer py-2 text-sm font-semibold text-brand">Request a return from {{ $sellerOrder->store->name }}</summary>
                    <form method="POST" action="{{ route('returns.store', $sellerOrder) }}" enctype="multipart/form-data" class="mt-4 grid gap-4" x-data="{ units: 0 }">
                        @csrf
                        <input type="hidden" name="store_order" value="{{ $sellerOrder->id }}">
                        <fieldset class="grid gap-2" x-on:input="units = [...$el.querySelectorAll('input[type=number]')].reduce((total, field) => total + (Number(field.value) || 0), 0)">
                            <legend class="field-label">Affected items and quantities</legend>
                            @foreach ($sellerOrder->items as $item)
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <label for="return-qty-{{ $item->id }}">{{ $item->product_name }} (purchased {{ $item->quantity }})</label>
                                    <input type="hidden" name="items[{{ $loop->index }}][order_item_id]" value="{{ $item->id }}">
                                    <input id="return-qty-{{ $item->id }}" type="number" min="0" max="{{ $item->quantity }}" value="{{ $resubmitted ? old('items.'.$loop->index.'.quantity', 0) : 0 }}" name="items[{{ $loop->index }}][quantity]" class="field-control w-20">
                                </div>
                            @endforeach
                        </fieldset>
                        <label><span class="field-label">What went wrong?</span><select name="reason" required class="field-control">@foreach (['damaged' => 'Damaged', 'defective' => 'Defective', 'wrong_item' => 'Wrong item', 'misdescribed' => 'Materially misdescribed'] as $value => $label)<option value="{{ $value }}" @selected($resubmitted && old('reason') === $value)>{{ $label }}</option>@endforeach</select></label>
                        <label><span class="field-label">Preferred remedy</span><select name="requested_remedy" required class="field-control"><option value="refund" @selected($resubmitted && old('requested_remedy') === 'refund')>Refund</option><option value="replacement" @selected($resubmitted && old('requested_remedy') === 'replacement')>Replacement</option></select></label>
                        <label><span class="field-label">Details</span><textarea name="details" required minlength="10" maxlength="2000" rows="3" class="field-control">{{ $resubmitted ? old('details') : '' }}</textarea></label>
                        <label><span class="field-label">Photos (optional, up to three, 5 MB each)</span><input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" class="field-control"><span class="mt-1 block text-xs text-muted">Photos have to be picked again if the form comes back with an error.</span></label>
                        <button x-bind:disabled="units < 1" class="min-h-11 justify-self-start rounded-lg bg-brand px-5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:bg-slate-300">Request seller review</button>
                        <p x-show="units < 1" x-cloak class="text-sm text-muted">Set a quantity above zero for at least one item.</p>
                    </form>
                    </details>
                @else
                    <p class="mt-2 text-sm text-muted">The seven-day online request window {{ $windowEndsAt ? 'closed on '.$windowEndsAt->format('M j, Y · g:i A').'.' : 'cannot be applied because the delivery date is uncertain.' }} Your legal rights may continue.</p>
                    <a href="mailto:itservices@jmsolutionitservices.com?subject=Later%20return%20claim%20{{ urlencode($order->number) }}" class="mt-2 inline-flex min-h-11 items-center font-semibold text-brand underline">Contact support</a>
                @endif
            </div>
        @endif
    @endforeach
</section>
