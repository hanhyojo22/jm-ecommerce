<section id="returns" class="returns-request scroll-mt-24">
    <div class="returns-request-header flex flex-wrap items-start justify-between gap-4">
        <div class="flex min-w-0 flex-1 items-start gap-3">
            <span class="returns-request-mark" aria-hidden="true">
                <x-returns.icon name="return" class="size-5" />
            </span>
            <div class="min-w-0">
                <p class="returns-eyebrow">Buyer protection</p>
                <h2 class="mt-1">Returns and replacements</h2>
                <p class="mt-1.5 max-w-2xl text-sm leading-6 text-muted">For damaged, defective, wrong, or materially misdescribed goods, ask the seller to help within seven days of delivery. Later warranty and statutory claims can still go to support.</p>
            </div>
        </div>
        <a href="{{ route('returns') }}" class="button-quiet shrink-0">
            Read the policy
            <x-returns.icon name="chevron-right" class="size-4" />
        </a>
    </div>

    <div class="divide-y divide-line">
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
                <div class="p-5 sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-[9px] border border-line bg-canvas font-display text-sm font-semibold text-brand" aria-hidden="true">{{ Str::upper(Str::substr($sellerOrder->store->name, 0, 1)) }}</span>
                            <div class="min-w-0">
                                <h3 class="truncate font-semibold text-ink">{{ $sellerOrder->store->name }}</h3>
                                @if ($sellerOrder->delivered_at)
                                    <p class="mt-0.5 text-xs text-muted">Delivered <time datetime="{{ $sellerOrder->delivered_at->toIso8601String() }}">{{ $sellerOrder->delivered_at->format('M j, Y · g:i A') }}</time></p>
                                @endif
                            </div>
                        </div>
                        @if ($windowOpen)
                            <span @class(['returns-window-chip', 'is-soon' => $windowClosingSoon, 'is-open' => ! $windowClosingSoon])>
                                <x-returns.icon name="clock" class="size-3.5 shrink-0" />
                                {{ $windowClosingSoon ? 'Closing soon' : 'Window open' }} · until {{ $windowEndsAt->format('M j, g:i A') }}
                            </span>
                        @else
                            <span class="returns-window-chip is-closed">
                                <x-returns.icon name="lock" class="size-3.5 shrink-0" />
                                Request window closed
                            </span>
                        @endif
                    </div>

                    @if ($sellerOrder->returnCases->isNotEmpty())
                        <div class="mt-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-muted">Your cases</p>
                            <ul class="mt-2 grid gap-2" aria-label="Return cases for {{ $sellerOrder->store->name }}">
                                @foreach ($sellerOrder->returnCases as $case)
                                    <li>
                                        <a href="{{ route('returns.show', $case) }}" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 rounded-[12px] border border-line bg-white px-3.5 py-3 transition hover:border-blue-300 hover:bg-blue-50/40 focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">
                                            <span class="min-w-0">
                                                <span class="block text-sm font-semibold text-ink">Return case · opened {{ $case->created_at->format('M j, Y') }}</span>
                                                <span class="mt-0.5 block text-xs text-muted">View the case history and your next step.</span>
                                            </span>
                                            <span class="flex min-w-0 flex-wrap items-center gap-2">
                                                <x-returns.status-badge :status="$case->status" />
                                                <x-returns.deadline :case="$case" />
                                                <x-returns.icon name="chevron-right" class="size-4 shrink-0 text-muted" />
                                            </span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($windowOpen)
                        <details class="group mt-4 overflow-hidden rounded-[14px] border border-line bg-canvas" @if ($resubmitted || ($order->sellerOrders->count() === 1 && $sellerOrder->returnCases->isEmpty())) open @endif>
                            <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-semibold text-brand transition hover:bg-blue-50/50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand [&::-webkit-details-marker]:hidden">
                                <span class="flex min-w-0 items-center gap-2">
                                    <span class="grid size-6 shrink-0 place-items-center rounded-[5px] border border-blue-200 bg-white text-brand" aria-hidden="true"><x-returns.icon name="plus" class="size-3.5" /></span>
                                    <span class="min-w-0 text-left leading-5">Request a return from {{ $sellerOrder->store->name }}</span>
                                </span>
                                <x-returns.icon name="chevron-down" class="size-4 shrink-0 transition group-open:rotate-180" />
                            </summary>
                            <div class="border-t border-line bg-surface p-4 sm:p-5">
                                <p class="text-sm leading-6 text-muted">Tell the seller what is wrong. They aim to respond within 48 hours. When the goods have to go back, the seller arranges a no-cost return.</p>
                                <form method="POST" action="{{ route('returns.store', $sellerOrder) }}" enctype="multipart/form-data" class="mt-5 grid gap-6" x-data="{
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
                                        <legend class="field-label flex items-center gap-2"><span class="returns-form-step" aria-hidden="true">1</span> Which items are affected?</legend>
                                        <p class="-mt-1 text-xs leading-5 text-muted">Set a quantity for every item you are claiming. Leave the rest at zero.</p>
                                        <ul class="divide-y divide-line overflow-hidden rounded-[12px] border border-line bg-white">
                                            @foreach ($sellerOrder->items as $item)
                                                <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 px-3.5 py-3">
                                                    <div class="flex min-w-0 items-center gap-3">
                                                        @if ($item->product?->images->first())
                                                            <img src="{{ Storage::disk('product_images')->url($item->product->images->first()->path) }}" alt="" class="size-11 shrink-0 rounded-[8px] border border-line object-cover" loading="lazy" decoding="async">
                                                        @else
                                                            <span class="grid size-11 shrink-0 place-items-center rounded-[8px] bg-canvas text-sm font-semibold text-muted" aria-hidden="true">{{ Str::upper(Str::substr($item->product_name, 0, 1)) }}</span>
                                                        @endif
                                                        <div class="min-w-0">
                                                            <label for="return-qty-{{ $item->id }}" class="block text-sm font-medium text-ink">{{ $item->product_name }}</label>
                                                            <p class="mt-0.5 text-xs text-muted">{{ $item->quantity }} purchased · <span class="tabular-nums">₱{{ number_format((float) $item->unit_price, 2) }}</span> each</p>
                                                        </div>
                                                    </div>
                                                    <div class="flex shrink-0 items-center">
                                                        <input type="hidden" name="items[{{ $loop->index }}][order_item_id]" value="{{ $item->id }}">
                                                        <div class="flex items-center overflow-hidden rounded-[10px] border border-line bg-white focus-within:ring-2 focus-within:ring-brand focus-within:ring-offset-2">
                                                            <button type="button" class="grid h-11 w-10 place-items-center text-muted transition hover:bg-canvas hover:text-ink focus:outline-none" aria-label="Remove one unit of {{ $item->product_name }}" @click="$refs.qty{{ $loop->index }}.stepDown(); $refs.qty{{ $loop->index }}.dispatchEvent(new Event('input', { bubbles: true }))">
                                                                <x-returns.icon name="minus" class="size-4" />
                                                            </button>
                                                            <input id="return-qty-{{ $item->id }}" x-ref="qty{{ $loop->index }}" type="number" min="0" max="{{ $item->quantity }}" value="{{ $resubmitted ? old('items.'.$loop->index.'.quantity', 0) : 0 }}" name="items[{{ $loop->index }}][quantity]" class="h-11 w-12 border-0 border-x border-line bg-transparent p-0 text-center text-sm font-semibold tabular-nums text-ink [appearance:textfield] focus:ring-0 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                                                            <button type="button" class="grid h-11 w-10 place-items-center text-muted transition hover:bg-canvas hover:text-ink focus:outline-none" aria-label="Add one unit of {{ $item->product_name }}" @click="$refs.qty{{ $loop->index }}.stepUp(); $refs.qty{{ $loop->index }}.dispatchEvent(new Event('input', { bubbles: true }))">
                                                                <x-returns.icon name="plus" class="size-4" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                        <x-input-error :messages="$errors->get('items')" class="mt-2" />
                                    </fieldset>

                                    <fieldset class="grid gap-2">
                                        <legend class="field-label flex items-center gap-2"><span class="returns-form-step" aria-hidden="true">2</span> What went wrong?</legend>
                                        <div class="grid gap-2 sm:grid-cols-2">
                                            @foreach ([
                                                'damaged' => ['Damaged', 'It arrived broken, spoiled, or unusable.', 'alert'],
                                                'defective' => ['Defective', 'It does not work the way it should.', 'wrench'],
                                                'wrong_item' => ['Wrong item', 'A different item or variant arrived.', 'swap'],
                                                'misdescribed' => ['Materially misdescribed', 'It differs from the listing in an important way.', 'file'],
                                            ] as $value => [$label, $hint, $icon])
                                                <label class="returns-choice">
                                                    <input type="radio" name="reason" value="{{ $value }}" required class="sr-only" @checked($resubmitted ? old('reason') === $value : $loop->first)>
                                                    <span class="returns-choice-icon"><x-returns.icon :name="$icon" class="size-4" /></span>
                                                    <span class="min-w-0 flex-1">
                                                        <span class="block text-sm font-semibold text-ink">{{ $label }}</span>
                                                        <span class="mt-0.5 block text-xs leading-5 text-muted">{{ $hint }}</span>
                                                    </span>
                                                    <span class="returns-choice-check"><x-returns.icon name="check-plain" class="size-2.5" /></span>
                                                </label>
                                            @endforeach
                                        </div>
                                        <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                                    </fieldset>

                                    <fieldset class="grid gap-2">
                                        <legend class="field-label flex items-center gap-2"><span class="returns-form-step" aria-hidden="true">3</span> Preferred remedy</legend>
                                        <div class="grid gap-2 sm:grid-cols-2">
                                            <label class="returns-choice">
                                                <input type="radio" name="requested_remedy" value="refund" required class="sr-only" @checked($resubmitted ? old('requested_remedy') === 'refund' : true)>
                                                <span class="returns-choice-icon"><x-returns.icon name="refund" class="size-4" /></span>
                                                <span class="min-w-0 flex-1">
                                                    <span class="block text-sm font-semibold text-ink">Refund</span>
                                                    <span class="mt-0.5 block text-xs leading-5 text-muted">Get your money back for the affected units.</span>
                                                </span>
                                                <span class="returns-choice-check"><x-returns.icon name="check-plain" class="size-2.5" /></span>
                                            </label>
                                            <label class="returns-choice">
                                                <input type="radio" name="requested_remedy" value="replacement" required class="sr-only" @checked($resubmitted && old('requested_remedy') === 'replacement')>
                                                <span class="returns-choice-icon"><x-returns.icon name="replacement" class="size-4" /></span>
                                                <span class="min-w-0 flex-1">
                                                    <span class="block text-sm font-semibold text-ink">Replacement</span>
                                                    <span class="mt-0.5 block text-xs leading-5 text-muted">Receive the same item again, if stock allows.</span>
                                                </span>
                                                <span class="returns-choice-check"><x-returns.icon name="check-plain" class="size-2.5" /></span>
                                            </label>
                                        </div>
                                        <x-input-error :messages="$errors->get('requested_remedy')" class="mt-2" />
                                    </fieldset>

                                    <div>
                                        <label for="return-details-{{ $sellerOrder->id }}" class="field-label flex items-center gap-2"><span class="returns-form-step" aria-hidden="true">4</span> Details</label>
                                        <textarea id="return-details-{{ $sellerOrder->id }}" name="details" required minlength="10" maxlength="2000" rows="3" class="field-control" placeholder="Describe the problem and what you have tried.">{{ $resubmitted ? old('details') : '' }}</textarea>
                                        <p class="mt-1.5 text-xs text-muted">At least 10 characters. JMMart support sees this if the case is escalated.</p>
                                        <x-input-error :messages="$errors->get('details')" class="mt-2" />
                                    </div>

                                    <div>
                                        <label for="return-photos-{{ $sellerOrder->id }}" class="field-label flex items-center gap-2"><span class="returns-form-step" aria-hidden="true">5</span> Photos <span class="font-normal text-muted">(optional, up to three, 5 MB each)</span></label>
                                        <label for="return-photos-{{ $sellerOrder->id }}" class="flex cursor-pointer items-center gap-3 rounded-[12px] border border-dashed border-slate-300 bg-canvas px-4 py-3.5 transition hover:border-brand hover:bg-blue-50/40 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand has-[:focus-visible]:ring-offset-2">
                                            <span class="grid size-9 shrink-0 place-items-center rounded-[8px] border border-line bg-white text-brand" aria-hidden="true"><x-returns.icon name="camera" class="size-4" /></span>
                                            <span class="min-w-0">
                                                <span class="block text-sm font-semibold text-ink">Add photos of the problem</span>
                                                <span class="mt-0.5 block text-xs text-muted">JPEG, PNG, or WebP · up to three files</span>
                                            </span>
                                            <input id="return-photos-{{ $sellerOrder->id }}" type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" class="sr-only" @change="pickPhotos($event)">
                                        </label>
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

                                    <ol class="returns-process">
                                        <li>
                                            <span class="returns-process-step" aria-hidden="true">01</span>
                                            <strong>Seller review</strong>
                                            <p>The seller approves with a remedy or rejects with reasons within 48 hours.</p>
                                        </li>
                                        <li>
                                            <span class="returns-process-step" aria-hidden="true">02</span>
                                            <strong>Return, if needed</strong>
                                            <p>Approved returns use a no-cost method, or the seller waives the return entirely.</p>
                                        </li>
                                        <li>
                                            <span class="returns-process-step" aria-hidden="true">03</span>
                                            <strong>Remedy</strong>
                                            <p>Refund or replacement, then you confirm the outcome to close the case.</p>
                                        </li>
                                    </ol>

                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-line pt-4">
                                        <button type="submit" x-bind:disabled="units < 1" class="button-primary">Request seller review</button>
                                        <p x-show="units < 1" x-cloak class="text-sm text-muted">Set a quantity above zero for at least one item.</p>
                                        <p x-show="units > 0" x-cloak class="text-sm text-muted"><span class="font-semibold tabular-nums text-ink" x-text="units"></span> <span x-text="units === 1 ? 'unit' : 'units'"></span> selected for return.</p>
                                    </div>
                                </form>
                            </div>
                        </details>
                    @else
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-[12px] border border-line bg-canvas px-4 py-3.5">
                            <p class="text-sm leading-6 text-muted">The seven-day online request window {{ $windowEndsAt ? 'closed on '.$windowEndsAt->format('M j, Y · g:i A').'.' : 'cannot be applied because the delivery date is uncertain.' }} Your legal rights may continue.</p>
                            <a href="mailto:itservices@jmsolutionitservices.com?subject=Later%20return%20claim%20{{ urlencode($order->number) }}" class="button-secondary shrink-0">Contact support</a>
                        </div>
                    @endif
                </div>
            @endif
        @endforeach
    </div>
</section>
