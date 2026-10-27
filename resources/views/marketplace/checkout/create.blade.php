<x-app-layout>
    @php($itemsByStore = $cart->items->groupBy(fn ($item) => $item->product->store_id))

    {{-- Room at the foot so the fixed phone action bar never covers the last of the summary. --}}
    <div class="page-shell min-h-[calc(100dvh-4.5rem)] max-lg:pb-28">
        <div class="page-container flex flex-col gap-6" x-data="{
            placing: false,
            method: @js(old('payment_method', 'cod')),
            methodLabels: @js(\App\Models\Payment::METHOD_LABELS),
            get placeLabel() { return this.method === 'cod' ? 'Place order' : (this.method === 'bank' ? 'Place order & show bank details' : 'Place order & show ' + this.methodLabels[this.method] + ' QR') },
            addresses: @js($addresses->map(fn ($address) => $address->only(['id', 'recipient_name', 'phone', 'address_line', 'city', 'province', 'postal_code']))),
            chooseAddress(event) {
                const address = this.addresses.find((item) => item.id === event.target.value);
                for (const field of ['recipient_name', 'phone', 'address_line', 'city', 'province', 'postal_code']) {
                    document.getElementById(field).value = address?.[field] ?? '';
                }
            },
        }">
            <a href="{{ route('cart.index') }}" class="inline-flex min-h-11 items-center self-start text-sm font-semibold text-brand hover:text-brand-hover"><span aria-hidden="true">←</span> Back to cart</a>
            <div class="grid gap-2">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand">Almost there</p>
                <h1 class="marketplace-page-title">Checkout</h1>
            </div>

            <form id="checkout-form" method="POST" action="{{ route('checkout.store') }}" @submit="placing = true" class="grid items-start gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                @csrf
                <div class="grid min-w-0 gap-6">
                    @if ($orderLimitReached)
                        <div class="rounded-[10px] border border-amber-200 bg-amber-50 p-6 text-sm leading-6 text-amber-950" role="alert">
                            <p class="font-semibold">You can place this order once your current one is delivered.</p>
                            <p class="mt-1">Cash-on-delivery parcels on your account were refused recently, so you can have one order in progress at a time. <a href="{{ route('orders.index') }}" class="font-semibold underline">Track your current order</a>. If a refusal wasn't your fault, ask for order help on that order.</p>
                        </div>
                    @elseif ($errors->has('cart'))
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
                    <section class="marketplace-surface p-6" aria-labelledby="delivery-heading">
                        <div class="grid gap-2"><h2 id="delivery-heading" class="text-lg font-semibold text-ink">Delivery address</h2><p class="text-sm text-muted">Where should your order be delivered?</p></div>
                        @if ($addresses->isNotEmpty())
                            <div class="mt-6 rounded-[10px] border border-blue-200 bg-blue-50/40 p-4">
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
                        <h3 class="mt-6 border-t border-line pt-6 text-xs font-semibold uppercase tracking-[0.12em] text-muted">Delivery details</h3>
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
                    <section class="marketplace-surface p-6" aria-labelledby="store-delivery-heading">
                        <h2 id="store-delivery-heading" class="text-lg font-semibold text-ink">Delivery by store</h2>
                        <div class="mt-6 grid gap-4">
                            @foreach ($itemsByStore as $storeItems)
                                <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-4 text-sm"><span class="font-semibold text-ink">{{ $storeItems->first()->product->store->name }}</span><span class="font-semibold text-success">Free shipping</span></div>
                            @endforeach
                        </div>
                    </section>
                    <section class="marketplace-surface p-6" aria-labelledby="payment-heading">
                        <div class="grid gap-2"><h2 id="payment-heading" class="text-lg font-semibold text-ink">Payment method</h2><p class="text-sm text-muted">{{ $walletMethods === [] ? 'Pay when your items arrive.' : 'Pay on delivery, or pay each store directly by e-wallet or bank.' }}</p></div>
                        <fieldset class="mt-6 grid gap-3 sm:grid-cols-2">
                            <legend class="sr-only">Payment method</legend>
                            @foreach ([
                                'cod' => ['label' => 'Cash on delivery', 'hint' => 'Pay ₱'.number_format($subtotal, 2).' when your items arrive.', 'tile' => null, 'mark' => null],
                                'gcash' => ['label' => 'GCash', 'hint' => 'Scan each store\'s GCash QR after you place the order.', 'tile' => 'bg-[#0070F0] text-white', 'mark' => 'G'],
                                'maya' => ['label' => 'Maya', 'hint' => 'Scan each store\'s Maya QR after you place the order.', 'tile' => 'bg-[#101820] text-[#3DDC84]', 'mark' => 'M'],
                                'bank' => ['label' => 'Bank transfer', 'hint' => 'InstaPay or PESONet to each store\'s account.', 'tile' => 'bg-slate-100 text-slate-700', 'mark' => null],
                            ] as $method => $option)
                                @continue($method !== 'cod' && ! in_array($method, $walletMethods, true))
                                <label class="flex min-h-[4.5rem] cursor-pointer items-center gap-3 rounded-[14px] border border-line bg-white p-4 transition hover:border-brand/50 has-[:checked]:border-brand has-[:checked]:bg-blue-50/40 has-[:checked]:shadow-[0_10px_24px_-18px_rgba(32,91,244,0.8)] has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-brand">
                                    <input type="radio" name="payment_method" value="{{ $method }}" x-model="method" @checked(old('payment_method', 'cod') === $method) class="size-5 shrink-0 border-line text-brand focus:ring-brand">
                                    <span class="min-w-0 flex-1"><span class="block text-sm font-semibold text-ink">{{ $option['label'] }}</span><span class="mt-0.5 block text-xs leading-5 text-muted">{{ $option['hint'] }}</span></span>
                                    @if ($option['tile'])
                                        <span class="grid size-9 shrink-0 place-items-center rounded-[10px] text-sm font-bold {{ $option['tile'] }}" aria-hidden="true">
                                            @if ($option['mark']){{ $option['mark'] }}@else<svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 10 9-6 9 6M5 10v8M9.5 10v8M14.5 10v8M19 10v8M3 21h18"/></svg>@endif
                                        </span>
                                    @endif
                                </label>
                            @endforeach
                        </fieldset>
                        @error('payment_method')<p class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
                        @if ($walletMethods !== [])
                            <div x-cloak x-show="method !== 'cod'" x-transition.opacity.duration.150ms class="mt-5 rounded-[18px] border border-blue-100 bg-[#F8FAFF] p-5" role="group" aria-labelledby="direct-pay-heading">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p id="direct-pay-heading" class="text-sm font-semibold text-ink">How paying by <span x-text="methodLabels[method]"></span> works</p>
                                    <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-brand-hover ring-1 ring-blue-100">{{ $storeTotals->count() }} {{ Str::plural('store', $storeTotals->count()) }} · paid separately</span>
                                </div>
                                <ul class="mt-4 grid gap-3 {{ $storeTotals->count() > 1 ? 'sm:grid-cols-2' : '' }}" role="list">
                                    @foreach ($storeTotals as $storeTotal)
                                        <li class="flex items-center gap-3 rounded-[14px] border border-line bg-white p-3">
                                            <span class="grid size-12 shrink-0 place-items-center rounded-[10px] border border-line bg-[repeating-linear-gradient(45deg,#EEF2F7_0_4px,#FFFFFF_4px_8px)]" aria-hidden="true"><svg class="size-5 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg></span>
                                            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-ink">{{ $storeTotal['name'] }}</span><span class="block text-xs text-muted">QR unlocks after you order</span></span>
                                            <span class="text-sm font-semibold tabular-nums text-ink">₱{{ number_format($storeTotal['total'], 2) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                                <ol class="mt-4 grid gap-3 text-xs leading-5 text-muted sm:grid-cols-3">
                                    <li><span class="block text-sm font-semibold text-ink">1 · Place your order</span>Your items are reserved for <span x-text="method === 'bank' ? {{ (int) config('marketplace.wallet.payment_hours.bank') }} : {{ (int) config('marketplace.wallet.payment_hours.gcash') }}"></span> hours.</li>
                                    <li><span class="block text-sm font-semibold text-ink">2 · Pay each store</span>Scan its QR or send to its <span x-text="method === 'bank' ? 'account' : 'number'"></span>, exact amount.</li>
                                    <li><span class="block text-sm font-semibold text-ink">3 · Send the reference</span>The store confirms it, then ships.</li>
                                </ol>
                                <div class="mt-4 rounded-[12px] border border-amber-200 bg-amber-50 p-4 text-xs leading-5 text-ink" role="note">
                                    <p>Your money goes <strong>straight to each store</strong>. JMMart never holds it, so we can't reverse a transfer; refunds come from the store or your bank's or e-wallet's dispute process. We keep your reference number, review order help requests, and can suspend stores that don't deliver or refund. <strong>Only pay the QR or account shown on your JMMart order page</strong>, and never share your MPIN or OTP.</p>
                                    <label class="mt-3 flex items-start gap-3 text-sm font-medium">
                                        <input type="checkbox" name="wallet_notice" value="1" :required="method !== 'cod'" @checked(old('wallet_notice')) class="mt-0.5 size-4 rounded border-line text-brand focus:ring-brand">
                                        <span>I understand how paying a store directly works, as explained in the <a href="{{ route('terms') }}#wallet-payments" target="_blank" class="font-semibold text-brand underline">Terms of Use</a>.</span>
                                    </label>
                                </div>
                            </div>
                            @error('wallet_notice')<p class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
                        @endif
                    </section>
                    <p class="rounded-[10px] bg-blue-50 p-6 text-sm leading-6 text-muted">Each store confirms delivery availability and timing after receiving your order. Shipping is free.</p>
                </div>

                <aside class="min-w-0 lg:sticky lg:top-24 lg:self-start" aria-labelledby="checkout-summary-heading">
                    <div class="marketplace-surface p-6">
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
                        <dl class="mt-6 grid gap-4 text-sm"><div class="grid grid-cols-[minmax(0,1fr)_auto] gap-4 text-muted"><dt>Subtotal</dt><dd class="text-right tabular-nums text-ink">₱{{ number_format($subtotal, 2) }}</dd></div><div class="grid grid-cols-[minmax(0,1fr)_auto] gap-4 text-muted"><dt>Shipping</dt><dd class="text-right font-semibold text-success">Free</dd></div><div class="grid grid-cols-[minmax(0,1fr)_auto] items-end gap-4 border-t border-line pt-6 font-semibold text-ink"><dt>Total to pay</dt><dd class="whitespace-nowrap text-right text-xl font-semibold tabular-nums">₱{{ number_format($subtotal, 2) }}</dd></div></dl>
                        <button type="submit" :disabled="placing" class="mt-6 flex min-h-12 w-full items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand disabled:cursor-wait disabled:bg-slate-300 max-lg:hidden" x-text="placing ? 'Placing order…' : (method === 'cod' ? 'Place order · ₱{{ number_format($subtotal, 2) }}' : placeLabel)">Place order · ₱{{ number_format($subtotal, 2) }}</button>
                        <p class="mt-3 text-center text-xs leading-5 text-muted max-lg:hidden" x-text="method === 'cod' ? 'You will pay on delivery.' : 'Each store\'s payment details appear on the next screen. Nothing is paid until you send it.'">You will pay on delivery.</p>
                    </div>
                </aside>

                {{--
                    On a phone the summary and its button sit a few screens below the form, so the
                    total and the way to act on it stay in reach instead of being scrolled to.
                --}}
                <div class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface/95 px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 shadow-[0_-2px_12px_rgb(15_23_42/8%)] backdrop-blur lg:hidden">
                    <div class="marketplace-container flex items-center gap-4">
                        <div class="min-w-0">
                            <p class="text-xs text-muted">Total to pay</p>
                            <p class="truncate text-lg font-semibold tabular-nums text-ink">₱{{ number_format($subtotal, 2) }}</p>
                        </div>
                        <button type="submit" :disabled="placing" class="ml-auto flex min-h-12 shrink-0 items-center justify-center rounded-[10px] bg-brand px-6 text-sm font-semibold text-white hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand disabled:cursor-wait disabled:bg-slate-300" x-text="placing ? 'Placing order…' : (method === 'cod' ? 'Place order' : 'Place order &amp; pay')">Place order</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
