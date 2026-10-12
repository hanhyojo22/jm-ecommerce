<x-app-layout>
    @php($itemsByStore = $cart?->items->groupBy(fn ($item) => $item->product->store_id) ?? collect())

    <div class="page-shell min-h-[calc(100dvh-4.5rem)]">
        <div class="page-container max-w-[1220px]" x-data="{
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
                    if (!/^(\+63|0)9\d{9}$/.test(phone)) { this.errors.phone = 'Enter a Philippine mobile number, such as 09171234567.'; }
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
            <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-muted">
                <nav aria-label="Breadcrumb" class="flex items-center gap-2"><a href="{{ route('cart.index') }}" class="inline-flex min-h-11 items-center font-semibold text-brand hover:text-brand-hover">Cart</a><span aria-hidden="true">/</span><span aria-current="page" class="font-semibold text-ink">Checkout</span></nav>
                <span class="text-xs font-medium">Cash on delivery · Free shipping</span>
            </div>
            <div class="mb-6 mt-7">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand" x-text="step === 1 ? 'Almost there' : 'Final check'"></p>
                <h1 class="customer-page-title mt-2 text-3xl font-bold tracking-[-0.04em] text-ink sm:text-4xl" x-text="step === 1 ? 'Checkout' : 'Review your order'"></h1>
                <p class="mt-2 text-sm leading-6 text-muted" x-text="step === 1 ? 'One clear place for delivery, payment, and your total.' : 'Confirm your delivery details and total before placing your order.'"></p>
            </div>
            <ol class="grid grid-cols-3 gap-2" aria-label="Checkout steps">
                <li><button type="button" @click="go(1)" :aria-current="step === 1 ? 'step' : null" class="w-full border-t-4 border-brand pt-3 text-left text-xs font-semibold text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand sm:text-sm">Delivery details</button></li>
                <li :aria-current="step === 2 ? 'step' : null" :class="step === 2 ? 'border-brand text-brand' : 'border-line text-muted'" class="border-t-4 pt-3 text-left text-xs font-semibold sm:text-sm">Review order</li>
                <li class="border-t-4 border-line pt-3 text-right text-xs font-semibold text-muted sm:text-sm">Order placed</li>
            </ol>

            <form id="checkout-form" method="POST" action="{{ route('checkout.store') }}" @submit="if (step !== 2) { $event.preventDefault(); go(2); } else if (!validate(1)) { $event.preventDefault(); step = 1; $nextTick(() => { const first = document.getElementById(Object.keys(errors)[0]); first?.focus(); first?.scrollIntoView({ behavior: scrollBehavior(), block: 'center' }); }); } else { placing = true; }" class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_350px]">
                <div class="min-w-0 space-y-5">
                    @csrf

                    @if ($errors->has('cart'))
                        <div class="mb-5 rounded-[10px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-950" role="alert">{{ $errors->first('cart') }} Return to your cart to update it, then try again.</div>
                    @endif

                    {{-- Step 1: Shipping --}}
                    <section x-show="step === 1" class="rounded-[18px] border border-line bg-surface p-5 shadow-sm sm:p-7" aria-labelledby="step-1-heading" data-od-id="ship">
                        <div class="flex items-start gap-3"><span class="grid size-10 shrink-0 place-items-center rounded-xl bg-blue-50 text-brand" aria-hidden="true"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 11 12 3l9 8v9H3v-9Z"/><path d="M9 20v-7h6v7"/></svg></span><div><h2 id="step-1-heading" tabindex="-1" class="text-lg font-semibold text-ink">Delivery address</h2><p class="mt-1 text-sm text-muted">Where should your order be delivered?</p></div></div>
                        @if ($addresses->isNotEmpty())
                            <div class="mt-6 rounded-xl border border-blue-200 bg-blue-50/40 p-4">
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
                        <h3 class="mt-7 border-t border-line pt-6 text-xs font-bold uppercase tracking-[0.12em] text-muted">Delivery details</h3>
                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="recipient_name" class="text-sm font-semibold text-ink">Recipient name</label>
                                <input id="recipient_name" name="recipient_name" value="{{ old('recipient_name') }}" autocomplete="name" required :aria-invalid="errors.recipient_name || @js($errors->has('recipient_name')) ? 'true' : null" :aria-describedby="errors.recipient_name || @js($errors->has('recipient_name')) ? 'recipient_name-error' : null" class="mt-2 min-h-12 w-full rounded-[10px] border-line text-sm text-ink placeholder:text-muted focus:border-brand focus:ring-brand" />
                                <p id="recipient_name-error" x-show="errors.recipient_name || @js($errors->has('recipient_name'))" x-text="errors.recipient_name || @js($errors->first('recipient_name'))" x-cloak class="mt-2 text-sm font-medium text-danger" role="alert"></p>
                            </div>
                            <div>
                                <label for="phone" class="text-sm font-semibold text-ink">Mobile number</label>
                                <input id="phone" name="phone" value="{{ old('phone') }}" type="tel" inputmode="tel" autocomplete="tel" placeholder="09171234567" required :aria-invalid="errors.phone || @js($errors->has('phone')) ? 'true' : null" :aria-describedby="errors.phone || @js($errors->has('phone')) ? 'phone-error' : null" class="mt-2 min-h-12 w-full rounded-[10px] border-line text-sm text-ink placeholder:text-muted focus:border-brand focus:ring-brand" />
                                <p id="phone-error" x-show="errors.phone || @js($errors->has('phone'))" x-text="errors.phone || @js($errors->first('phone'))" x-cloak class="mt-2 text-sm font-medium text-danger" role="alert"></p>
                            </div>
                            <div class="sm:col-span-2">
                                <label for="address_line" class="text-sm font-semibold text-ink">Street address</label>
                                <input id="address_line" name="address_line" value="{{ old('address_line') }}" autocomplete="street-address" required :aria-invalid="errors.address_line || @js($errors->has('address_line')) ? 'true' : null" :aria-describedby="errors.address_line || @js($errors->has('address_line')) ? 'address_line-error' : null" class="mt-2 min-h-12 w-full rounded-[10px] border-line text-sm text-ink placeholder:text-muted focus:border-brand focus:ring-brand" />
                                <p class="mt-1 text-xs leading-5 text-muted">Include barangay, province, and postal code.</p>
                                <p id="address_line-error" x-show="errors.address_line || @js($errors->has('address_line'))" x-text="errors.address_line || @js($errors->first('address_line'))" x-cloak class="mt-2 text-sm font-medium text-danger" role="alert"></p>
                            </div>
                            <div>
                                <label for="city" class="text-sm font-semibold text-ink">City</label>
                                <input id="city" name="city" value="{{ old('city') }}" autocomplete="address-level2" required :aria-invalid="errors.city || @js($errors->has('city')) ? 'true' : null" :aria-describedby="errors.city || @js($errors->has('city')) ? 'city-error' : null" class="mt-2 min-h-12 w-full rounded-[10px] border-line text-sm text-ink placeholder:text-muted focus:border-brand focus:ring-brand" />
                                <p id="city-error" x-show="errors.city || @js($errors->has('city'))" x-text="errors.city || @js($errors->first('city'))" x-cloak class="mt-2 text-sm font-medium text-danger" role="alert"></p>
                            </div>
                        </div>
                    </section>

                    <section x-show="step === 1" class="rounded-[18px] border border-line bg-surface p-5 shadow-sm sm:p-7" aria-labelledby="payment-heading">
                        <div class="flex items-start gap-3"><span class="grid size-10 shrink-0 place-items-center rounded-xl bg-blue-50 text-brand" aria-hidden="true"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M7 15h4"/></svg></span><div><h2 id="payment-heading" class="text-lg font-semibold text-ink">Payment method</h2><p class="mt-1 text-sm text-muted">Pay when your items arrive.</p></div></div>
                        <div class="mt-6 flex items-start gap-3 rounded-xl border border-blue-200 p-4"><span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full border-[5px] border-brand" aria-hidden="true"></span><div><p class="text-sm font-semibold text-ink">Cash on delivery</p><p class="mt-1 text-sm leading-6 text-muted">Have ₱{{ number_format($subtotal, 2) }} ready for the delivery rider. No online payment is required.</p></div><span class="ml-auto hidden shrink-0 rounded-md bg-green-50 px-2 py-1 text-xs font-semibold text-success sm:inline">Only option</span></div>
                    </section>
                    <p x-show="step === 1" class="rounded-xl bg-blue-50 px-4 py-3 text-sm leading-6 text-muted"><strong class="text-ink">Delivery timing:</strong> Each seller confirms availability and timing after you place your order. Shipping is free.</p>

                    {{-- Step 2: Review --}}
                    <section x-show="step === 2" x-cloak class="rounded-[18px] border border-line bg-surface p-5 shadow-sm sm:p-7" aria-labelledby="step-2-heading">
                        <div class="flex items-center justify-between gap-3">
                            <h2 id="step-2-heading" tabindex="-1" class="text-lg font-semibold text-ink">Deliver to <span x-text="shipping.recipient_name"></span></h2>
                            <div>
                                <button type="button" @click="go(1)" class="min-h-11 text-sm font-semibold text-brand hover:text-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Edit address</button>
                            </div>
                        </div>
                        <p class="mt-1 text-sm text-muted" x-text="shipping.phone"></p>
                        <p class="mt-1 text-sm text-muted"><span x-text="shipping.address_line"></span>, <span x-text="shipping.city"></span></p>
                    </section>
                    <section x-show="step === 2" x-cloak class="rounded-[18px] border border-line bg-surface p-5 shadow-sm sm:p-7" aria-labelledby="review-payment-heading">
                        <h2 id="review-payment-heading" class="text-lg font-semibold text-ink">Cash on delivery</h2>
                        <p class="mt-1 text-sm leading-6 text-muted"><span class="font-semibold text-ink">Payment:</span> Cash on delivery. Pay ₱{{ number_format($subtotal, 2) }} when your order arrives.</p>
                        <p class="mt-2 text-sm leading-6 text-muted"><span class="font-semibold text-ink">Delivery:</span> Free within the Philippines where the seller can deliver. Each seller confirms availability and timing after receiving the order.</p>
                    </section>
                    <section x-show="step === 2" x-cloak class="rounded-[18px] border border-line bg-surface p-5 shadow-sm sm:p-7" aria-labelledby="review-items-heading">
                        <h2 id="review-items-heading" class="text-lg font-semibold text-ink">Items from {{ $itemsByStore->count() }} {{ Str::plural('store', $itemsByStore->count()) }}</h2>
                        <div class="mt-5 divide-y divide-line">
                            @foreach ($itemsByStore as $storeItems)
                                @php($store = $storeItems->first()->product->store)
                                <section class="py-4 first:pt-0 last:pb-0" aria-label="{{ $store->name }}">
                                    <h3 class="text-sm font-semibold text-ink">{{ $store->name }}</h3>
                                    <ul class="mt-3 space-y-3">
                                        @foreach ($storeItems as $item)
                                            <li class="flex items-start justify-between gap-4 text-sm"><span class="min-w-0 leading-5 text-muted"><span class="block break-words font-medium text-ink">{{ $item->product->name }}</span>@if ($item->variant)<span class="block text-xs font-medium text-brand">{{ $item->variant->label() }}</span>@endif Qty {{ $item->quantity }} · Standard (Free)</span><span class="shrink-0 font-semibold text-ink">₱{{ number_format($item->line_total, 2) }}</span></li>
                                        @endforeach
                                    </ul>
                                </section>
                            @endforeach
                        </div>
                    </section>
                    <p x-show="step === 2" x-cloak class="rounded-xl bg-blue-50 px-4 py-3 text-sm leading-6 text-muted">Each seller confirms delivery availability and timing after receiving your order.</p>
                </div>

                <aside class="min-w-0 lg:sticky lg:top-24 lg:self-start" aria-labelledby="checkout-summary-heading">
                    <div class="rounded-[18px] border border-line bg-surface p-5 shadow-sm sm:p-6">
                        <h2 id="checkout-summary-heading" class="text-lg font-semibold text-ink">Your order <span class="font-normal text-muted">({{ $cart->items->sum('quantity') }} {{ Str::plural('item', $cart->items->sum('quantity')) }})</span></h2>
                        <div class="mt-5 space-y-5">
                            @forelse ($itemsByStore as $storeItems)
                                @php($store = $storeItems->first()->product->store)
                                <section class="border-b border-line pb-5 last:border-b-0 last:pb-0" aria-labelledby="checkout-store-{{ $store->id }}">
                                    <h3 id="checkout-store-{{ $store->id }}" class="text-sm font-semibold text-ink">{{ $store->name }}</h3>
                                    <ul class="mt-3 space-y-4">
                                        @foreach ($storeItems as $item)
                                            <li class="flex min-w-0 items-start gap-3 text-sm">
                                                <span class="grid size-16 shrink-0 place-items-center overflow-hidden rounded-xl bg-slate-100">@if($item->product->images->first())<img src="{{ Storage::disk('product_images')->url($item->product->images->first()->path) }}" alt="" class="size-full object-contain">@else<svg viewBox="0 0 24 24" class="size-6 text-muted" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8" cy="9" r="1.5"/><path d="m4 18 5-5 3 3 3-3 5 5"/></svg>@endif</span>
                                                <span class="min-w-0 flex-1 leading-5 text-muted"><span class="block break-words font-medium text-ink">{{ $item->product->name }}</span>@if ($item->variant)<span class="block text-xs font-medium text-brand">{{ $item->variant->label() }}</span>@endif <span class="block text-xs">Qty {{ $item->quantity }}</span></span>
                                                <span class="shrink-0 font-semibold tabular-nums text-ink">₱{{ number_format($item->line_total, 2) }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <p class="mt-4 flex justify-between pl-[4.75rem] text-xs text-muted"><span>Shipping from this store</span><strong class="text-success">Free</strong></p>
                                </section>
                            @empty
                                <p class="text-sm leading-6 text-muted">Your cart is empty. Add products before checking out.</p>
                            @endforelse
                        </div>
                        <dl class="mt-6 space-y-3 border-t border-line pt-5 text-sm"><div class="flex justify-between text-muted"><dt>Subtotal</dt><dd class="tabular-nums text-ink">₱{{ number_format($subtotal, 2) }}</dd></div><div class="flex justify-between text-muted"><dt>Shipping</dt><dd class="font-semibold text-success">Free</dd></div><div class="grid grid-cols-[minmax(0,1fr)_auto] items-end gap-3 border-t border-line pt-5 text-ink"><dt class="min-w-0 font-semibold" x-text="step === 1 ? 'Total to pay' : 'Total to pay on delivery'"></dt><dd class="whitespace-nowrap text-xl font-bold tracking-[-0.04em] tabular-nums">₱{{ number_format($subtotal, 2) }}</dd></div></dl>
                        <button x-show="step === 1" type="button" @click="go(2)" class="mt-6 flex min-h-12 w-full items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Review order</button>
                        <button x-show="step === 2" x-cloak type="submit" :disabled="placing" class="mt-6 flex min-h-12 w-full items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand disabled:cursor-wait disabled:bg-slate-300" x-text="placing ? 'Placing order…' : 'Place order · ₱{{ number_format($subtotal, 2) }}'"></button>
                        <p class="mt-3 text-center text-xs leading-5 text-muted" x-text="step === 1 ? 'You can review every detail before placing your order.' : 'You will pay the delivery rider when the order arrives.'"></p>
                        <div class="mt-5 border-t border-line pt-5 text-xs leading-5 text-muted"><p class="font-semibold text-ink">Need help?</p><p>After checkout, your order details page lets you contact each seller or request help.</p></div>
                    </div>
                </aside>
            </form>
        </div>

    </div>
</x-app-layout>
