<section id="returns" class="rounded-[18px] border border-line bg-surface p-5 shadow-sm sm:p-6">
    <div class="flex items-start gap-3">
        <span class="grid size-10 shrink-0 place-items-center rounded-[10px] bg-blue-50 text-brand-hover" aria-hidden="true">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7" /><path d="M3 4v5h5" /></svg>
        </span>
        <div class="min-w-0">
            <h2 class="text-lg font-semibold text-ink">Returns and replacements</h2>
            <p class="mt-1 text-sm leading-6 text-muted">For damaged, defective, wrong, or materially misdescribed goods, ask the seller to help within seven days of delivery. Later warranty and statutory claims can still go to support.</p>
        </div>
    </div>
    @foreach ($order->sellerOrders as $sellerOrder)
        @if ($sellerOrder->status === 'delivered')
            @php
                $windowEndsAt = $sellerOrder->delivered_at?->copy()->addDays(7);
                $windowOpen = $windowEndsAt && now()->lessThanOrEqualTo($windowEndsAt);
                $windowClosingSoon = $windowOpen && $windowEndsAt->lessThan(now()->addHours(48));
                $resubmitted = old('store_order') === $sellerOrder->id;
                $oldItems = $resubmitted && is_array(old('items')) ? old('items') : [];
                $initialUnits = (int) collect($oldItems)->filter('is_array')->sum(fn (array $line): int => (int) ($line['quantity'] ?? 0));
            @endphp
            <div class="mt-5 border-t border-line pt-5">
                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                    <div class="min-w-0">
                        <h3 class="truncate font-semibold text-ink">{{ $sellerOrder->store->name }}</h3>
                        @if ($sellerOrder->delivered_at)
                            <p class="mt-0.5 text-xs text-muted">Delivered <time datetime="{{ $sellerOrder->delivered_at->toIso8601String() }}">{{ $sellerOrder->delivered_at->format('M j, Y · g:i A') }}</time></p>
                        @endif
                    </div>
                    @if ($windowOpen)
                        <span @class([
                            'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset',
                            'bg-amber-50 text-warning-text ring-amber-200' => $windowClosingSoon,
                            'bg-green-50 text-success ring-green-200' => ! $windowClosingSoon,
                        ])>
                            <svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
                            {{ $windowClosingSoon ? 'Closing soon' : 'Open' }} · until {{ $windowEndsAt->format('M j, g:i A') }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-muted ring-1 ring-inset ring-line">
                            <svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg>
                            Request window closed
                        </span>
                    @endif
                </div>

                @if ($sellerOrder->returnCases->isNotEmpty())
                    <ul class="mt-3 grid gap-2" aria-label="Return cases for {{ $sellerOrder->store->name }}">
                        @foreach ($sellerOrder->returnCases as $case)
                            <li>
                                <a href="{{ route('returns.show', $case) }}" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 rounded-[10px] border border-line bg-white px-3.5 py-3 transition hover:border-blue-300 hover:bg-blue-50/40 focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-ink">Return case · opened {{ $case->created_at->format('M j, Y') }}</span>
                                        <span class="mt-0.5 block text-xs text-muted">View the case history and your next step.</span>
                                    </span>
                                    <span class="flex shrink-0 flex-wrap items-center gap-2">
                                        <x-returns.status-badge :status="$case->status" />
                                        <x-returns.deadline :case="$case" />
                                        <svg class="size-4 shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6" /></svg>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($windowOpen)
                    <details class="group mt-3 overflow-hidden rounded-[14px] border border-line bg-canvas" @if ($resubmitted || $order->sellerOrders->count() === 1) open @endif>
                        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-semibold text-brand transition hover:text-brand-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand [&::-webkit-details-marker]:hidden">
                            <span class="flex min-w-0 items-center gap-2">
                                <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                                <span class="truncate">Request a return from {{ $sellerOrder->store->name }}</span>
                            </span>
                            <svg class="size-4 shrink-0 transition group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                        </summary>
                        <div class="border-t border-line bg-surface p-4 sm:p-5">
                            <p class="text-sm leading-6 text-muted">Tell the seller what is wrong. They aim to respond within 48 hours. When the goods have to go back, the seller arranges a no-cost return.</p>
                            <form method="POST" action="{{ route('returns.store', $sellerOrder) }}" enctype="multipart/form-data" class="mt-4 grid gap-5" x-data="{
                                units: {{ $initialUnits }},
                                photos: [],
                                pickPhotos(event) {
                                    this.photos.forEach((photo) => URL.revokeObjectURL(photo.url));
                                    this.photos = [...event.target.files].map((file) => ({ url: URL.createObjectURL(file), name: file.name }));
                                },
                            }">
                                @csrf
                                <input type="hidden" name="store_order" value="{{ $sellerOrder->id }}">

                                <fieldset class="grid gap-2" x-on:input="units = [...$el.querySelectorAll('input[type=number]')].reduce((total, field) => total + (Number(field.value) || 0), 0)">
                                    <legend class="field-label">Which items are affected?</legend>
                                    <p class="-mt-1 text-xs leading-5 text-muted">Set a quantity for every item you are claiming. Leave the rest at zero.</p>
                                    <ul class="divide-y divide-line rounded-[10px] border border-line bg-white">
                                        @foreach ($sellerOrder->items as $item)
                                            <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-3.5 py-3">
                                                <div class="min-w-0">
                                                    <label for="return-qty-{{ $item->id }}" class="block text-sm font-medium text-ink">{{ $item->product_name }}</label>
                                                    <p class="mt-0.5 text-xs text-muted">{{ $item->quantity }} purchased · <span class="tabular-nums">₱{{ number_format((float) $item->unit_price, 2) }}</span> each</p>
                                                </div>
                                                <div class="flex shrink-0 items-center">
                                                    <input type="hidden" name="items[{{ $loop->index }}][order_item_id]" value="{{ $item->id }}">
                                                    <div class="flex items-center overflow-hidden rounded-[10px] border border-line bg-white focus-within:ring-2 focus-within:ring-brand focus-within:ring-offset-2">
                                                        <button type="button" class="grid h-11 w-10 place-items-center text-muted transition hover:bg-canvas hover:text-ink focus:outline-none" aria-label="Remove one unit of {{ $item->product_name }}" @click="$refs.qty{{ $loop->index }}.stepDown(); $refs.qty{{ $loop->index }}.dispatchEvent(new Event('input', { bubbles: true }))">
                                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14" /></svg>
                                                        </button>
                                                        <input id="return-qty-{{ $item->id }}" x-ref="qty{{ $loop->index }}" type="number" min="0" max="{{ $item->quantity }}" value="{{ $resubmitted ? old('items.'.$loop->index.'.quantity', 0) : 0 }}" name="items[{{ $loop->index }}][quantity]" class="h-11 w-12 border-0 border-x border-line bg-transparent p-0 text-center text-sm font-semibold tabular-nums text-ink [appearance:textfield] focus:ring-0 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                                                        <button type="button" class="grid h-11 w-10 place-items-center text-muted transition hover:bg-canvas hover:text-ink focus:outline-none" aria-label="Add one unit of {{ $item->product_name }}" @click="$refs.qty{{ $loop->index }}.stepUp(); $refs.qty{{ $loop->index }}.dispatchEvent(new Event('input', { bubbles: true }))">
                                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <x-input-error :messages="$errors->get('items')" class="mt-2" />
                                </fieldset>

                                <fieldset class="grid gap-2">
                                    <legend class="field-label">What went wrong?</legend>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        @foreach ([
                                            'damaged' => ['Damaged', 'It arrived broken, spoiled, or unusable.'],
                                            'defective' => ['Defective', 'It does not work the way it should.'],
                                            'wrong_item' => ['Wrong item', 'A different item or variant arrived.'],
                                            'misdescribed' => ['Materially misdescribed', 'It differs from the listing in an important way.'],
                                        ] as $value => [$label, $hint])
                                            <label class="flex h-full cursor-pointer items-start gap-3 rounded-[10px] border border-line bg-white p-3.5 transition hover:border-blue-300 has-[:checked]:border-brand has-[:checked]:bg-blue-50/60 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand has-[:focus-visible]:ring-offset-2">
                                                <input type="radio" name="reason" value="{{ $value }}" required class="peer sr-only" @checked($resubmitted ? old('reason') === $value : $loop->first)>
                                                <span class="mt-0.5 size-4 shrink-0 rounded-full border border-slate-400 bg-white transition peer-checked:border-brand peer-checked:bg-brand" aria-hidden="true"></span>
                                                <span class="min-w-0">
                                                    <span class="block text-sm font-semibold text-ink">{{ $label }}</span>
                                                    <span class="mt-0.5 block text-xs leading-5 text-muted">{{ $hint }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                                </fieldset>

                                <fieldset class="grid gap-2">
                                    <legend class="field-label">Preferred remedy</legend>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <label class="flex h-full cursor-pointer items-start gap-3 rounded-[10px] border border-line bg-white p-3.5 transition hover:border-blue-300 has-[:checked]:border-brand has-[:checked]:bg-blue-50/60 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand has-[:focus-visible]:ring-offset-2">
                                            <input type="radio" name="requested_remedy" value="refund" required class="peer sr-only" @checked($resubmitted ? old('requested_remedy') === 'refund' : true)>
                                            <span class="mt-0.5 size-4 shrink-0 rounded-full border border-slate-400 bg-white transition peer-checked:border-brand peer-checked:bg-brand" aria-hidden="true"></span>
                                            <span class="min-w-0">
                                                <span class="block text-sm font-semibold text-ink">Refund</span>
                                                <span class="mt-0.5 block text-xs leading-5 text-muted">Get your money back for the affected units.</span>
                                            </span>
                                        </label>
                                        <label class="flex h-full cursor-pointer items-start gap-3 rounded-[10px] border border-line bg-white p-3.5 transition hover:border-blue-300 has-[:checked]:border-brand has-[:checked]:bg-blue-50/60 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand has-[:focus-visible]:ring-offset-2">
                                            <input type="radio" name="requested_remedy" value="replacement" required class="peer sr-only" @checked($resubmitted && old('requested_remedy') === 'replacement')>
                                            <span class="mt-0.5 size-4 shrink-0 rounded-full border border-slate-400 bg-white transition peer-checked:border-brand peer-checked:bg-brand" aria-hidden="true"></span>
                                            <span class="min-w-0">
                                                <span class="block text-sm font-semibold text-ink">Replacement</span>
                                                <span class="mt-0.5 block text-xs leading-5 text-muted">Receive the same item again, if stock allows.</span>
                                            </span>
                                        </label>
                                    </div>
                                    <x-input-error :messages="$errors->get('requested_remedy')" class="mt-2" />
                                </fieldset>

                                <div>
                                    <label for="return-details-{{ $sellerOrder->id }}" class="field-label">Details</label>
                                    <textarea id="return-details-{{ $sellerOrder->id }}" name="details" required minlength="10" maxlength="2000" rows="3" class="field-control" placeholder="Describe the problem and what you have tried.">{{ $resubmitted ? old('details') : '' }}</textarea>
                                    <p class="mt-1.5 text-xs text-muted">At least 10 characters. JMMart support sees this if the case is escalated.</p>
                                    <x-input-error :messages="$errors->get('details')" class="mt-2" />
                                </div>

                                <div>
                                    <label for="return-photos-{{ $sellerOrder->id }}" class="field-label">Photos (optional, up to three, 5 MB each)</label>
                                    <input id="return-photos-{{ $sellerOrder->id }}" type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" class="field-control" @change="pickPhotos($event)">
                                    <p class="mt-1.5 text-xs text-muted">Photos have to be picked again if the form comes back with an error.</p>
                                    <ul x-show="photos.length" x-cloak class="mt-2 flex flex-wrap gap-2" aria-label="Selected photos">
                                        <template x-for="photo in photos" :key="photo.url">
                                            <li class="overflow-hidden rounded-[10px] border border-line bg-canvas">
                                                <img :src="photo.url" :alt="photo.name" class="size-16 object-cover">
                                            </li>
                                        </template>
                                    </ul>
                                    <x-input-error :messages="$errors->get('photos')" class="mt-2" />
                                </div>

                                <ol class="grid gap-2 rounded-[10px] border border-line bg-canvas px-4 py-3.5 text-xs leading-5 text-muted">
                                    <li><span class="font-semibold text-ink">1 · Seller review.</span> The seller approves with a remedy or rejects with reasons within 48 hours.</li>
                                    <li><span class="font-semibold text-ink">2 · Return, if needed.</span> Approved returns use a no-cost method, or the seller waives the return entirely.</li>
                                    <li><span class="font-semibold text-ink">3 · Remedy.</span> Refund or replacement, then you confirm the outcome to close the case.</li>
                                </ol>

                                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-line pt-4">
                                    <button type="submit" x-bind:disabled="units < 1" class="inline-flex min-h-11 items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover disabled:cursor-not-allowed disabled:bg-slate-300 focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Request seller review</button>
                                    <p x-show="units < 1" x-cloak class="text-sm text-muted">Set a quantity above zero for at least one item.</p>
                                    <p x-show="units > 0" x-cloak class="text-sm text-muted"><span class="font-semibold tabular-nums text-ink" x-text="units"></span> <span x-text="units === 1 ? 'unit' : 'units'"></span> selected for return.</p>
                                </div>
                            </form>
                        </div>
                    </details>
                @else
                    <p class="mt-3 text-sm leading-6 text-muted">The seven-day online request window {{ $windowEndsAt ? 'closed on '.$windowEndsAt->format('M j, Y · g:i A').'.' : 'cannot be applied because the delivery date is uncertain.' }} Your legal rights may continue.</p>
                    <a href="mailto:itservices@jmsolutionitservices.com?subject=Later%20return%20claim%20{{ urlencode($order->number) }}" class="mt-3 inline-flex min-h-11 items-center justify-center rounded-[10px] border border-line bg-white px-4 text-sm font-semibold text-brand transition hover:border-brand hover:text-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Contact support</a>
                @endif
            </div>
        @endif
    @endforeach
</section>
