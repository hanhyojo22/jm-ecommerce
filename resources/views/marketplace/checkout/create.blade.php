<x-app-layout>
    @php($itemsByStore = $cart?->items->groupBy(fn ($item) => $item->product->store_id) ?? collect())

    <div class="page-shell min-h-[calc(100dvh-4.5rem)]">
        <div class="page-container max-w-[1120px]" x-data="{
            step: 1,
            placing: false,
            errors: {},
            scrollBehavior() {
                return window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
            },
            go(n) {
                if (n > this.step && !this.validate(this.step)) { return; }
                this.step = n;
                this.$nextTick(() => {
                    const heading = document.getElementById(`step-${n}-heading`);
                    heading?.focus();
                    heading?.scrollIntoView({ behavior: this.scrollBehavior(), block: 'start' });
                });
            },
            validate(n) {
                this.errors = {};
                if (n === 1) {
                    const name = document.getElementById('recipient_name').value.trim();
                    const phone = document.getElementById('phone').value.trim();
                    const address = document.getElementById('address_line').value.trim();
                    const city = document.getElementById('city').value.trim();
                    if (!name) { this.errors.recipient_name = 'Enter the recipient name.'; }
                    if (!phone) { this.errors.phone = 'Enter a mobile number.'; }
                    if (!address) { this.errors.address_line = 'Enter the street address.'; }
                    if (!city) { this.errors.city = 'Enter the city.'; }
                    if (Object.keys(this.errors).length) {
                        const firstKey = Object.keys(this.errors)[0];
                        const first = document.getElementById(firstKey);
                        if (first && this.step === n) { first.focus(); }
                        return false;
                    }
                }
                return true;
            },
        }">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('cart.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand transition hover:text-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">← Back to cart</a>
                    <span class="text-line" aria-hidden="true">/</span>
                    <h1 class="customer-page-title text-2xl font-bold tracking-[-0.03em] text-ink">Secure checkout</h1>
                </div>
                <p class="text-xs font-medium text-muted">Cash on delivery · Free shipping</p>
            </div>
            <ol class="grid grid-cols-4 gap-2" aria-label="Checkout steps">
                <template x-for="s in [1,2,3,4]" :key="s">
                    <li>
                        <button type="button" @click="go(s)" :disabled="s > step" :aria-current="step === s ? 'step' : null" :class="s <= step ? 'border-brand text-ink' : 'border-line text-muted'" class="block min-h-11 w-full border-t-[3px] px-1 pt-2 text-center text-xs font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand disabled:cursor-not-allowed sm:text-[13px]">
                            <span x-text="s + ' · ' + ['Shipping', 'Delivery', 'Payment', 'Review'][s-1]"></span>
                        </button>
                    </li>
                </template>
            </ol>

            <div class="mt-6 grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_340px]">
                <form id="checkout-form" method="POST" action="{{ route('checkout.store') }}" @submit="if (step !== 4) { $event.preventDefault(); go(step + 1); } else if (!validate(1)) { $event.preventDefault(); step = 1; $nextTick(() => { const first = document.getElementById(Object.keys(errors)[0]); first?.focus(); first?.scrollIntoView({ behavior: scrollBehavior(), block: 'center' }); }); } else { placing = true; }" class="min-w-0">
                    @csrf

                    @if ($errors->has('cart'))
                        <div class="mb-5 rounded-[10px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-950" role="alert">{{ $errors->first('cart') }} Return to your cart to update it, then try again.</div>
                    @endif

                    {{-- Step 1: Shipping --}}
                    <section x-show="step === 1" class="rounded-[14px] border border-line bg-surface p-5 sm:p-8" aria-labelledby="step-1-heading" data-od-id="ship">
                        <h3 id="step-1-heading" tabindex="-1" class="text-lg font-semibold text-ink">1 · Shipping address</h3>
                        <p class="mt-1 text-sm leading-6 text-muted">Enter the address where your order should be delivered.</p>
                        <div class="mt-6 grid gap-5 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label for="recipient_name" class="text-sm font-semibold text-ink">Recipient name</label>
                                <input id="recipient_name" name="recipient_name" value="{{ old('recipient_name') }}" autocomplete="name" required :aria-invalid="errors.recipient_name ? 'true' : null" class="mt-2 min-h-12 w-full rounded-[10px] border-line text-sm text-ink placeholder:text-muted focus:border-brand focus:ring-brand" />
                                <p x-show="errors.recipient_name" x-text="errors.recipient_name" class="mt-2 text-sm font-medium text-danger"></p>
                                @error('recipient_name')<p class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="phone" class="text-sm font-semibold text-ink">Mobile number</label>
                                <input id="phone" name="phone" value="{{ old('phone') }}" type="tel" inputmode="tel" autocomplete="tel" required class="mt-2 min-h-12 w-full rounded-[10px] border-line text-sm text-ink placeholder:text-muted focus:border-brand focus:ring-brand" />
                                <p x-show="errors.phone" x-text="errors.phone" class="mt-2 text-sm font-medium text-danger"></p>
                                @error('phone')<p class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="address_line" class="text-sm font-semibold text-ink">Street address</label>
                                <input id="address_line" name="address_line" value="{{ old('address_line') }}" autocomplete="street-address" required class="mt-2 min-h-12 w-full rounded-[10px] border-line text-sm text-ink placeholder:text-muted focus:border-brand focus:ring-brand" />
                                <p x-show="errors.address_line" x-text="errors.address_line" class="mt-2 text-sm font-medium text-danger"></p>
                                @error('address_line')<p class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="city" class="text-sm font-semibold text-ink">City</label>
                                <input id="city" name="city" value="{{ old('city') }}" autocomplete="address-level2" required class="mt-2 min-h-12 w-full rounded-[10px] border-line text-sm text-ink placeholder:text-muted focus:border-brand focus:ring-brand" />
                                <p x-show="errors.city" x-text="errors.city" class="mt-2 text-sm font-medium text-danger"></p>
                                @error('city')<p class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="mt-7 flex justify-end">
                            <button type="button" @click="go(2)" class="inline-flex min-h-12 items-center justify-center rounded-[10px] bg-brand px-6 text-sm font-semibold text-white transition hover:bg-brand-hover">Continue to delivery</button>
                        </div>
                    </section>

                    {{-- Step 2: Delivery (grouped by seller) --}}
                    <section x-show="step === 2" x-cloak class="rounded-[14px] border border-line bg-surface p-5 sm:p-8" aria-labelledby="step-2-heading">
                        <h3 id="step-2-heading" tabindex="-1" class="text-lg font-semibold text-ink">2 · Delivery options</h3>
                        <p class="mt-1 text-sm leading-6 text-muted">Each seller ships their items separately. Standard delivery is free.</p>
                        <div class="mt-6 space-y-5">
                            @foreach ($itemsByStore as $storeItems)
                                @php($store = $storeItems->first()->product->store)
                                <fieldset class="rounded-[14px] border border-line p-4">
                                    <legend class="px-2 text-sm font-semibold text-ink">{{ $store->name }} · {{ $storeItems->sum('quantity') }} {{ Str::plural('item', $storeItems->sum('quantity')) }}</legend>
                                    <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-[10px] border border-brand bg-blue-50/50 px-4">
                                        <input type="radio" checked class="size-4 border-line text-brand focus:ring-brand" aria-label="Standard delivery, free">
                                        <span class="flex-1 text-sm font-medium">Standard delivery <span class="text-muted">· 2–5 days</span></span>
                                        <span class="text-sm font-semibold text-success">Free</span>
                                    </label>
                                </fieldset>
                            @endforeach
                        </div>
                        <div class="mt-7 flex justify-between gap-3">
                            <button type="button" @click="go(1)" class="inline-flex min-h-12 items-center justify-center rounded-[10px] border border-line bg-white px-5 text-sm font-semibold transition hover:border-brand">Back</button>
                            <button type="button" @click="go(3)" class="inline-flex min-h-12 items-center justify-center rounded-[10px] bg-brand px-6 text-sm font-semibold text-white transition hover:bg-brand-hover">Continue to payment</button>
                        </div>
                    </section>

                    {{-- Step 3: Payment --}}
                    <section x-show="step === 3" x-cloak class="rounded-[14px] border border-line bg-surface p-5 sm:p-8" aria-labelledby="step-3-heading">
                        <h3 id="step-3-heading" tabindex="-1" class="text-lg font-semibold text-ink">3 · Payment method</h3>
                        <p class="mt-1 text-sm leading-6 text-muted">Pay the delivery rider when your order arrives.</p>
                        <div class="mt-6 grid gap-3">
                            <label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-[10px] border border-brand bg-blue-50/50 px-4 ring-1 ring-inset ring-brand/20">
                                <input type="radio" checked class="size-4 border-line text-brand focus:ring-brand" aria-label="Cash on delivery">
                                <span class="flex-1"><span class="block text-sm font-semibold">Cash on delivery</span><span class="block text-xs text-muted">No prepayment needed</span></span>
                                <span class="rounded-lg bg-success/10 px-2.5 py-1 text-xs font-semibold text-success">Available</span>
                            </label>
                        </div>
                        <div class="mt-7 flex justify-between gap-3">
                            <button type="button" @click="go(2)" class="inline-flex min-h-12 items-center justify-center rounded-[10px] border border-line bg-white px-5 text-sm font-semibold transition hover:border-brand">Back</button>
                            <button type="button" @click="go(4)" class="inline-flex min-h-12 items-center justify-center rounded-[10px] bg-brand px-6 text-sm font-semibold text-white transition hover:bg-brand-hover">Review order</button>
                        </div>
                    </section>

                    {{-- Step 4: Review --}}
                    <section x-show="step === 4" x-cloak class="rounded-[14px] border border-line bg-surface p-5 sm:p-8" aria-labelledby="step-4-heading">
                        <h3 id="step-4-heading" tabindex="-1" class="text-lg font-semibold text-ink">4 · Review &amp; place order</h3>
                        <p class="mt-1 text-sm leading-6 text-muted">Check your items and delivery details before placing the order.</p>
                        <div class="mt-6 space-y-5">
                            @foreach ($itemsByStore as $storeItems)
                                @php($store = $storeItems->first()->product->store)
                                <div>
                                    <h4 class="text-xs font-semibold uppercase tracking-[0.12em] text-muted">{{ $store->name }}</h4>
                                    <ul class="mt-3 space-y-3">
                                        @foreach ($storeItems as $item)
                                            <li class="flex items-start justify-between gap-4 text-sm"><span class="min-w-0 leading-5 text-muted"><span class="block break-words font-medium text-ink">{{ $item->product->name }}</span>Qty {{ $item->quantity }} · Standard (Free)</span><span class="shrink-0 font-semibold text-ink">₱{{ number_format($item->line_total, 2) }}</span></li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-6 flex justify-between gap-3">
                            <button type="button" @click="go(3)" class="inline-flex min-h-12 items-center justify-center rounded-[10px] border border-line bg-white px-5 text-sm font-semibold transition hover:border-brand">Back</button>
                            <button type="submit" :disabled="placing" class="inline-flex min-h-12 items-center justify-center rounded-[10px] bg-brand px-6 text-sm font-semibold text-white transition hover:bg-brand-hover disabled:cursor-wait disabled:bg-slate-300" x-text="placing ? 'Placing order…' : 'Place order · ₱{{ number_format($subtotal, 2) }}'"></button>
                        </div>
                    </section>
                </form>

                <aside class="lg:sticky lg:top-24 lg:self-start">
                    <div class="rounded-[14px] border border-line bg-surface p-5 sm:p-6">
                        <h3 class="text-lg font-semibold text-ink">Your order</h3>
                        <div class="mt-5 space-y-5">
                            @forelse ($itemsByStore as $storeItems)
                                @php($store = $storeItems->first()->product->store)
                                <section aria-labelledby="checkout-store-{{ $store->id }}">
                                    <h4 id="checkout-store-{{ $store->id }}" class="text-xs font-semibold uppercase tracking-[0.12em] text-muted">{{ $store->name }}</h4>
                                    <ul class="mt-3 space-y-3">
                                        @foreach ($storeItems as $item)
                                            <li class="flex items-start justify-between gap-4 text-sm"><span class="min-w-0 leading-5 text-muted"><span class="block break-words font-medium text-ink">{{ $item->product->name }}</span>Qty {{ $item->quantity }}</span><span class="shrink-0 font-semibold text-ink">₱{{ number_format($item->line_total, 2) }}</span></li>
                                        @endforeach
                                    </ul>
                                </section>
                            @empty
                                <p class="text-sm leading-6 text-muted">Your cart is empty. Add products before checking out.</p>
                            @endforelse
                        </div>
                        <div class="mt-6 space-y-3 border-t border-line pt-5 text-sm"><div class="flex justify-between text-muted"><span>Subtotal</span><span>₱{{ number_format($subtotal, 2) }}</span></div><div class="flex justify-between text-muted"><span>Shipping</span><span class="font-semibold text-success">Free</span></div><div class="flex items-end justify-between pt-2 text-ink"><span class="font-semibold">Total</span><span class="text-2xl font-bold tracking-[-0.04em]">₱{{ number_format($subtotal, 2) }}</span></div></div>
                        <p class="mt-5 text-xs leading-5 text-muted">You’ll pay the delivery rider when your order arrives. Each seller receives only the items from their store.</p>
                    </div>
                </aside>
            </div>
        </div>

        <div class="sticky bottom-0 z-20 mt-6 border-t border-line bg-canvas/95 px-[18px] py-3 backdrop-blur lg:hidden"><div class="mx-auto flex max-w-[1120px] items-center gap-3"><span class="min-w-0 flex-1 text-sm font-semibold text-ink">Total · ₱{{ number_format($subtotal, 2) }}</span><button type="button" x-show="step < 4" @click="go(step + 1)" x-text="['', 'Delivery', 'Payment', 'Review'][step] + ' →'" class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover">Continue</button><button type="submit" form="checkout-form" x-show="step === 4" x-cloak :disabled="placing" class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover disabled:cursor-wait disabled:bg-slate-300">Place order</button></div></div>
    </div>
</x-app-layout>
