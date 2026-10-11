<x-app-layout>
    @php($itemsByStore = $cart?->items->groupBy(fn ($item) => $item->product->store_id) ?? collect())

    <div class="page-shell min-h-[calc(100dvh-4.5rem)]">
        <div class="page-container max-w-[1120px]" x-data="{
            step: 1,
            placing: false,
            errors: {},
            shipping: {},
            addresses: @js($addresses->map(fn ($address) => $address->only(['id', 'recipient_name', 'phone', 'address_line', 'city']))),
            chooseAddress(event) {
                const address = this.addresses.find((item) => item.id === event.target.value);
                for (const field of ['recipient_name', 'phone', 'address_line', 'city']) {
                    document.getElementById(field).value = address?.[field] ?? '';
                }
                this.errors = {};
            },
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
                    this.shipping = { recipient_name: name, phone, address_line: address, city };
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
            <ol class="grid grid-cols-2 gap-2" aria-label="Checkout steps">
                <template x-for="s in [1,2]" :key="s">
                    <li>
                        <button type="button" @click="go(s)" :disabled="s > step" :aria-current="step === s ? 'step' : null" :class="s <= step ? 'border-brand text-ink' : 'border-line text-muted'" class="block min-h-11 w-full border-t-[3px] px-1 pt-2 text-center text-xs font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand disabled:cursor-not-allowed sm:text-[13px]">
                            <span x-text="s + ' · ' + ['Shipping', 'Review'][s-1]"></span>
                        </button>
                    </li>
                </template>
            </ol>

            <div class="mt-6 grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_340px]">
                <form id="checkout-form" method="POST" action="{{ route('checkout.store') }}" @submit="if (step !== 2) { $event.preventDefault(); go(2); } else if (!validate(1)) { $event.preventDefault(); step = 1; $nextTick(() => { const first = document.getElementById(Object.keys(errors)[0]); first?.focus(); first?.scrollIntoView({ behavior: scrollBehavior(), block: 'center' }); }); } else { placing = true; }" class="min-w-0">
                    @csrf

                    @if ($errors->has('cart'))
                        <div class="mb-5 rounded-[10px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-950" role="alert">{{ $errors->first('cart') }} Return to your cart to update it, then try again.</div>
                    @endif

                    {{-- Step 1: Shipping --}}
                    <section x-show="step === 1" class="rounded-[14px] border border-line bg-surface p-5 sm:p-8" aria-labelledby="step-1-heading" data-od-id="ship">
                        <h3 id="step-1-heading" tabindex="-1" class="text-lg font-semibold text-ink">1 · Shipping address</h3>
                        <p class="mt-1 text-sm leading-6 text-muted">Enter the address where your order should be delivered.</p>
                        @if ($addresses->isNotEmpty())
                            <div class="mt-5">
                                <label for="saved-address" class="text-sm font-semibold text-ink">Use a saved address</label>
                                <select id="saved-address" @change="chooseAddress($event)" class="mt-2 min-h-12 w-full rounded-[10px] border-line text-sm text-ink focus:border-brand focus:ring-brand">
                                    <option value="">Enter a new address</option>
                                    @foreach ($addresses as $address)
                                        <option value="{{ $address->id }}">{{ $address->recipient_name }} · {{ $address->address_line }}, {{ $address->city }}</option>
                                    @endforeach
                                </select>
                                <a href="{{ route('delivery-addresses.index') }}" class="mt-2 inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:text-brand-hover">Manage delivery addresses</a>
                            </div>
                        @endif
                        <div class="mt-6 grid gap-5 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label for="recipient_name" class="text-sm font-semibold text-ink">Recipient name</label>
                                <input id="recipient_name" name="recipient_name" value="{{ old('recipient_name') }}" autocomplete="name" required :aria-invalid="errors.recipient_name || @js($errors->has('recipient_name')) ? 'true' : null" :aria-describedby="errors.recipient_name || @js($errors->has('recipient_name')) ? 'recipient_name-error' : null" class="mt-2 min-h-12 w-full rounded-[10px] border-line text-sm text-ink placeholder:text-muted focus:border-brand focus:ring-brand" />
                                <p id="recipient_name-error" x-show="errors.recipient_name || @js($errors->has('recipient_name'))" x-text="errors.recipient_name || @js($errors->first('recipient_name'))" x-cloak class="mt-2 text-sm font-medium text-danger" role="alert"></p>
                            </div>
                            <div class="sm:col-span-2">
                                <label for="phone" class="text-sm font-semibold text-ink">Mobile number</label>
                                <input id="phone" name="phone" value="{{ old('phone') }}" type="tel" inputmode="tel" autocomplete="tel" required :aria-invalid="errors.phone || @js($errors->has('phone')) ? 'true' : null" :aria-describedby="errors.phone || @js($errors->has('phone')) ? 'phone-error' : null" class="mt-2 min-h-12 w-full rounded-[10px] border-line text-sm text-ink placeholder:text-muted focus:border-brand focus:ring-brand" />
                                <p id="phone-error" x-show="errors.phone || @js($errors->has('phone'))" x-text="errors.phone || @js($errors->first('phone'))" x-cloak class="mt-2 text-sm font-medium text-danger" role="alert"></p>
                            </div>
                            <div class="sm:col-span-2">
                                <label for="address_line" class="text-sm font-semibold text-ink">Street address</label>
                                <input id="address_line" name="address_line" value="{{ old('address_line') }}" autocomplete="street-address" required :aria-invalid="errors.address_line || @js($errors->has('address_line')) ? 'true' : null" :aria-describedby="errors.address_line || @js($errors->has('address_line')) ? 'address_line-error' : null" class="mt-2 min-h-12 w-full rounded-[10px] border-line text-sm text-ink placeholder:text-muted focus:border-brand focus:ring-brand" />
                                <p id="address_line-error" x-show="errors.address_line || @js($errors->has('address_line'))" x-text="errors.address_line || @js($errors->first('address_line'))" x-cloak class="mt-2 text-sm font-medium text-danger" role="alert"></p>
                            </div>
                            <div>
                                <label for="city" class="text-sm font-semibold text-ink">City</label>
                                <input id="city" name="city" value="{{ old('city') }}" autocomplete="address-level2" required :aria-invalid="errors.city || @js($errors->has('city')) ? 'true' : null" :aria-describedby="errors.city || @js($errors->has('city')) ? 'city-error' : null" class="mt-2 min-h-12 w-full rounded-[10px] border-line text-sm text-ink placeholder:text-muted focus:border-brand focus:ring-brand" />
                                <p id="city-error" x-show="errors.city || @js($errors->has('city'))" x-text="errors.city || @js($errors->first('city'))" x-cloak class="mt-2 text-sm font-medium text-danger" role="alert"></p>
                            </div>
                        </div>
                        <div class="mt-7 flex justify-end">
                            <button type="button" @click="go(2)" class="inline-flex min-h-12 items-center justify-center rounded-[10px] bg-brand px-6 text-sm font-semibold text-white transition hover:bg-brand-hover">Review order</button>
                        </div>
                    </section>

                    {{-- Step 2: Review --}}
                    <section x-show="step === 2" x-cloak class="rounded-[14px] border border-line bg-surface p-5 sm:p-8" aria-labelledby="step-2-heading">
                        <h3 id="step-2-heading" tabindex="-1" class="text-lg font-semibold text-ink">2 · Review &amp; place order</h3>
                        <p class="mt-1 text-sm leading-6 text-muted">Check your items and delivery details before placing the order.</p>
                        <div class="mt-6 rounded-[10px] border border-line p-4 text-sm leading-6">
                            <div class="flex items-center justify-between gap-3">
                                <h4 class="font-semibold text-ink">Deliver to</h4>
                                <button type="button" @click="go(1)" class="min-h-11 text-sm font-semibold text-brand hover:text-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Edit address</button>
                            </div>
                            <p class="font-medium text-ink" x-text="shipping.recipient_name"></p>
                            <p class="text-muted" x-text="shipping.phone"></p>
                            <p class="text-muted"><span x-text="shipping.address_line"></span>, <span x-text="shipping.city"></span></p>
                        </div>
                        <div class="mt-4 rounded-[10px] border border-line p-4 text-sm leading-6">
                            <p><span class="font-semibold text-ink">Delivery:</span> Standard, free. Each seller ships their items separately.</p>
                            <p><span class="font-semibold text-ink">Payment:</span> Cash on delivery.</p>
                        </div>
                        <div class="mt-6 space-y-5">
                            @foreach ($itemsByStore as $storeItems)
                                @php($store = $storeItems->first()->product->store)
                                <div>
                                    <h4 class="text-xs font-semibold uppercase tracking-[0.12em] text-muted">{{ $store->name }}</h4>
                                    <ul class="mt-3 space-y-3">
                                        @foreach ($storeItems as $item)
                                            <li class="flex items-start justify-between gap-4 text-sm"><span class="min-w-0 leading-5 text-muted"><span class="block break-words font-medium text-ink">{{ $item->product->name }}</span>@if ($item->variant)<span class="block text-xs font-medium text-brand">{{ $item->variant->label() }}</span>@endif Qty {{ $item->quantity }} · Standard (Free)</span><span class="shrink-0 font-semibold text-ink">₱{{ number_format($item->line_total, 2) }}</span></li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-6 flex justify-between gap-3">
                            <button type="button" @click="go(1)" class="inline-flex min-h-12 items-center justify-center rounded-[10px] border border-line bg-white px-5 text-sm font-semibold transition hover:border-brand">Back</button>
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
                                            <li class="flex items-start justify-between gap-4 text-sm"><span class="min-w-0 leading-5 text-muted"><span class="block break-words font-medium text-ink">{{ $item->product->name }}</span>@if ($item->variant)<span class="block text-xs font-medium text-brand">{{ $item->variant->label() }}</span>@endif Qty {{ $item->quantity }}</span><span class="shrink-0 font-semibold text-ink">₱{{ number_format($item->line_total, 2) }}</span></li>
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

    </div>
</x-app-layout>
