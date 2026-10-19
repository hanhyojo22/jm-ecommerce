<x-app-layout>
    @php($itemsByStore = $cart->items->groupBy(fn ($item) => $item->product->store_id))

    {{-- Room at the foot so the fixed phone action bar never covers the last of the summary. --}}
    <div class="min-h-[calc(100dvh-4.5rem)] bg-canvas py-6 max-lg:pb-28">
        <div class="mx-auto flex w-full max-w-[1220px] flex-col gap-6 px-6" x-data="{
            placing: false,
            addresses: @js($addresses->map(fn ($address) => $address->only(['id', 'recipient_name', 'phone', 'address_line', 'city', 'province', 'postal_code']))),
            chooseAddress(event) {
                const address = this.addresses.find((item) => item.id === event.target.value);
                for (const field of ['recipient_name', 'phone', 'address_line', 'city', 'province', 'postal_code']) {
                    document.getElementById(field).value = address?.[field] ?? '';
                }
            },
        }">
            <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-sm text-muted">
                <a href="{{ route('cart.index') }}" class="inline-flex min-h-11 items-center font-semibold text-brand hover:text-brand-hover">Cart</a>
                <span aria-hidden="true">/</span><span aria-current="page" class="font-semibold text-ink">Checkout</span>
            </nav>
            <div class="grid gap-2">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand">Almost there</p>
                <h1 class="text-[30px] font-bold leading-[1.2] tracking-[-1px] text-ink min-[601px]:text-[27px] min-[801px]:text-[33px]">Checkout</h1>
            </div>

            <form id="checkout-form" method="POST" action="{{ route('checkout.store') }}" @submit="placing = true" class="grid items-start gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                @csrf
                <div class="grid min-w-0 gap-6">
                    @if ($errors->has('cart'))
                        <div class="rounded-[10px] border border-amber-200 bg-amber-50 p-6 text-sm leading-6 text-amber-950" role="alert">{{ $errors->first('cart') }} Return to your cart to update it, then try again.</div>
                    @endif
                    @php($fieldErrors = collect(['recipient_name' => 'Recipient name', 'phone' => 'Mobile number', 'address_line' => 'Street address', 'city' => 'City', 'province' => 'Province', 'postal_code' => 'Postal code', 'delivery_note' => 'Delivery note'])->filter(fn ($label, $field) => $errors->has($field)))
                    @if ($fieldErrors->isNotEmpty())
                        {{-- A rejected field can sit far below the fold, so the page says what to fix and takes the reader there. --}}
                        <div role="alert" tabindex="-1" x-init="$el.focus()" aria-labelledby="checkout-errors-title" class="rounded-[10px] border border-danger bg-red-50 p-6 text-sm leading-6 text-ink">
                            <h2 id="checkout-errors-title" class="font-semibold text-danger">{{ $fieldErrors->count() === 1 ? 'There is 1 detail to fix' : 'There are '.$fieldErrors->count().' details to fix' }}</h2>
                            <ul class="mt-2 grid gap-1">
                                @foreach ($fieldErrors as $field => $label)
                                    <li><a href="#{{ $field }}" class="font-semibold text-danger underline">{{ $label }}: {{ $errors->first($field) }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <section class="rounded-[18px] border border-line bg-surface p-6 shadow-sm" aria-labelledby="delivery-heading">
                        <div class="grid gap-2"><h2 id="delivery-heading" class="text-lg font-semibold text-ink">Delivery address</h2><p class="text-sm text-muted">Where should your order be delivered?</p></div>
                        @if ($addresses->isNotEmpty())
                            <div class="mt-6 rounded-xl border border-blue-200 bg-blue-50/40 p-4">
                                <label for="saved-address" class="text-sm font-semibold text-ink">Use a saved address</label>
                                <select id="saved-address" @change="chooseAddress($event)" class="mt-2 min-h-12 w-full rounded-[10px] border-line text-sm text-ink focus:border-brand focus:ring-brand">
                                    <option value="">Enter a new address</option>
                                    @foreach ($addresses as $address)
                                        <option value="{{ $address->id }}" @selected(! session()->hasOldInput() && $loop->first)>{{ $address->recipient_name }} · {{ $address->address_line }}, {{ $address->city }}</option>
                                    @endforeach
                                </select>
                                <a href="{{ route('delivery-addresses.index') }}" class="mt-2 inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:text-brand-hover">Manage delivery addresses</a>
                            </div>
                        @endif
                        <h3 class="mt-6 border-t border-line pt-6 text-xs font-bold uppercase tracking-[0.12em] text-muted">Delivery details</h3>
                        <p class="mt-2 text-xs text-muted">We'll save this address for your next checkout. Fill in any missing details from an older saved address.</p>
                        <div class="mt-6 grid gap-6 sm:grid-cols-2">
                            <div>
                                <label for="recipient_name" class="text-sm font-semibold text-ink">Recipient name</label>
                                <input id="recipient_name" name="recipient_name" value="{{ old('recipient_name', $preferredAddress?->recipient_name) }}" autocomplete="name" required maxlength="150" @error('recipient_name') aria-invalid="true" aria-describedby="recipient_name-error" @enderror class="mt-2 min-h-12 w-full rounded-[10px] border-line text-sm text-ink focus:border-brand focus:ring-brand">
                                @error('recipient_name')<p id="recipient_name-error" class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="phone" class="text-sm font-semibold text-ink">Mobile number</label>
                                <input id="phone" name="phone" value="{{ old('phone', $preferredAddress?->phone) }}" type="tel" autocomplete="tel" placeholder="09171234567" required @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror class="mt-2 min-h-12 w-full rounded-[10px] border-line text-sm text-ink focus:border-brand focus:ring-brand">
                                @error('phone')<p id="phone-error" class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="address_line" class="text-sm font-semibold text-ink">Street address</label>
                                <input id="address_line" name="address_line" value="{{ old('address_line', $preferredAddress?->address_line) }}" autocomplete="street-address" required maxlength="255" @error('address_line') aria-invalid="true" aria-describedby="address_line-error" @enderror class="mt-2 min-h-12 w-full rounded-[10px] border-line text-sm text-ink focus:border-brand focus:ring-brand">
                                <p class="mt-2 text-xs text-muted">Include house number and barangay.</p>
                                @error('address_line')<p id="address_line-error" class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="city" class="text-sm font-semibold text-ink">City</label>
                                <input id="city" name="city" value="{{ old('city', $preferredAddress?->city) }}" autocomplete="address-level2" required maxlength="100" @error('city') aria-invalid="true" aria-describedby="city-error" @enderror class="mt-2 min-h-12 w-full rounded-[10px] border-line text-sm text-ink focus:border-brand focus:ring-brand">
                                @error('city')<p id="city-error" class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="province" class="text-sm font-semibold text-ink">Province</label>
                                <input id="province" name="province" value="{{ old('province', $preferredAddress?->province) }}" autocomplete="address-level1" required maxlength="100" @error('province') aria-invalid="true" aria-describedby="province-error" @enderror class="mt-2 min-h-12 w-full rounded-[10px] border-line text-sm text-ink focus:border-brand focus:ring-brand">
                                @error('province')<p id="province-error" class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="postal_code" class="text-sm font-semibold text-ink">Postal code</label>
                                <input id="postal_code" name="postal_code" value="{{ old('postal_code', $preferredAddress?->postal_code) }}" inputmode="numeric" autocomplete="postal-code" pattern="[0-9]{4}" maxlength="4" required @error('postal_code') aria-invalid="true" aria-describedby="postal_code-error" @enderror class="mt-2 min-h-12 w-full rounded-[10px] border-line text-sm text-ink focus:border-brand focus:ring-brand">
                                @error('postal_code')<p id="postal_code-error" class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="delivery_note" class="text-sm font-semibold text-ink">Delivery note <span class="font-normal text-muted">(optional)</span></label>
                                <textarea id="delivery_note" name="delivery_note" rows="3" maxlength="1000" placeholder="Landmark or delivery instructions" @error('delivery_note') aria-invalid="true" aria-describedby="delivery_note-error" @enderror class="mt-2 w-full rounded-[10px] border-line text-sm text-ink focus:border-brand focus:ring-brand">{{ old('delivery_note') }}</textarea>
                                @error('delivery_note')<p id="delivery_note-error" class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </section>
                    <section class="rounded-[18px] border border-line bg-surface p-6 shadow-sm" aria-labelledby="store-delivery-heading">
                        <h2 id="store-delivery-heading" class="text-lg font-semibold text-ink">Delivery by store</h2>
                        <div class="mt-6 grid gap-4">
                            @foreach ($itemsByStore as $storeItems)
                                <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-4 text-sm"><span class="font-semibold text-ink">{{ $storeItems->first()->product->store->name }}</span><span class="font-semibold text-success">Free shipping</span></div>
                            @endforeach
                        </div>
                    </section>
                    <section class="rounded-[18px] border border-line bg-surface p-6 shadow-sm" aria-labelledby="payment-heading">
                        <div class="grid gap-2"><h2 id="payment-heading" class="text-lg font-semibold text-ink">Payment method</h2><p class="text-sm text-muted">Pay when your items arrive.</p></div>
                        <div class="mt-6 flex items-start gap-4 rounded-xl border border-blue-200 p-4"><span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full border-[5px] border-brand" aria-hidden="true"></span><div class="grid gap-2"><p class="text-sm font-semibold text-ink">Cash on delivery</p><p class="text-sm leading-6 text-muted">Pay ₱{{ number_format($subtotal, 2) }} on delivery. No online payment is required.</p></div></div>
                    </section>
                    <p class="rounded-xl bg-blue-50 p-6 text-sm leading-6 text-muted">Each store confirms delivery availability and timing after receiving your order. Shipping is free.</p>
                </div>

                <aside class="min-w-0 lg:sticky lg:top-24 lg:self-start" aria-labelledby="checkout-summary-heading">
                    <div class="rounded-[18px] border border-line bg-surface p-6 shadow-sm">
                        <h2 id="checkout-summary-heading" class="text-lg font-semibold text-ink">Your order <span class="font-normal text-muted">({{ $cart->items->sum('quantity') }} {{ Str::plural('item', $cart->items->sum('quantity')) }})</span></h2>
                        <div class="mt-6 grid gap-6">
                            @foreach ($itemsByStore as $storeItems)
                                @php($store = $storeItems->first()->product->store)
                                <section class="border-b border-line pb-6" aria-labelledby="checkout-store-{{ $store->id }}">
                                    <h3 id="checkout-store-{{ $store->id }}" class="text-sm font-semibold text-ink">{{ $store->name }}</h3>
                                    <ul class="mt-4 grid gap-4">
                                        @foreach ($storeItems as $item)
                                            <li class="grid min-w-0 grid-cols-[3rem_minmax(0,1fr)_auto] items-start gap-4 text-sm">
                                                <span class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-xl bg-slate-100">@if ($item->imagePath())<img src="{{ Storage::disk('product_images')->url($item->imagePath()) }}" alt="" loading="lazy" decoding="async" class="size-full object-contain">@else<svg viewBox="0 0 24 24" class="size-6 text-muted" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8" cy="9" r="1.5"/><path d="m4 18 5-5 3 3 3-3 5 5"/></svg>@endif</span>
                                                <span class="min-w-0 leading-5 text-muted"><span class="block break-words font-medium text-ink">{{ $item->product->name }}</span>@if ($item->variant)<span class="block text-xs font-medium text-brand">{{ $item->variant->label() }}</span>@endif<span class="block text-xs">Qty {{ $item->quantity }}</span></span>
                                                <span class="text-right font-semibold tabular-nums text-ink">₱{{ number_format($item->line_total, 2) }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </section>
                            @endforeach
                        </div>
                        <dl class="mt-6 grid gap-4 text-sm"><div class="grid grid-cols-[minmax(0,1fr)_auto] gap-4 text-muted"><dt>Subtotal</dt><dd class="text-right tabular-nums text-ink">₱{{ number_format($subtotal, 2) }}</dd></div><div class="grid grid-cols-[minmax(0,1fr)_auto] gap-4 text-muted"><dt>Shipping</dt><dd class="text-right font-semibold text-success">Free</dd></div><div class="grid grid-cols-[minmax(0,1fr)_auto] items-end gap-4 border-t border-line pt-6 font-semibold text-ink"><dt>Total to pay</dt><dd class="whitespace-nowrap text-right text-xl font-bold tabular-nums">₱{{ number_format($subtotal, 2) }}</dd></div></dl>
                        <button type="submit" :disabled="placing" class="mt-6 flex min-h-12 w-full items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand disabled:cursor-wait disabled:bg-slate-300 max-lg:hidden" x-text="placing ? 'Placing order…' : 'Place order · ₱{{ number_format($subtotal, 2) }}'">Place order · ₱{{ number_format($subtotal, 2) }}</button>
                        <p class="mt-3 text-center text-xs leading-5 text-muted max-lg:hidden">You will pay on delivery.</p>
                    </div>
                </aside>

                {{--
                    On a phone the summary and its button sit a few screens below the form, so the
                    total and the way to act on it stay in reach instead of being scrolled to.
                --}}
                <div class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface/95 px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 shadow-[0_-2px_12px_rgb(15_23_42/8%)] backdrop-blur lg:hidden">
                    <div class="mx-auto flex w-full max-w-[1220px] items-center gap-4">
                        <div class="min-w-0">
                            <p class="text-xs text-muted">Total to pay</p>
                            <p class="truncate text-lg font-bold tabular-nums text-ink">₱{{ number_format($subtotal, 2) }}</p>
                        </div>
                        <button type="submit" :disabled="placing" class="ml-auto flex min-h-12 shrink-0 items-center justify-center rounded-[10px] bg-brand px-6 text-sm font-semibold text-white hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand disabled:cursor-wait disabled:bg-slate-300" x-text="placing ? 'Placing order…' : 'Place order'">Place order</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
