<x-app-layout>
    @php($itemsByStore = $cart->items->groupBy(fn ($item) => $item->product->store_id))
    @php($itemCount = $cart->items->sum('quantity'))
    @php($addressFields = ['recipient_name' => 'Recipient name', 'phone' => 'Mobile number', 'address_line' => 'Street address', 'city' => 'City', 'province' => 'Province', 'postal_code' => 'Postal code'])
    {{-- A rejected submission reopens the step that holds the first field to fix. --}}
    @php($initialStep = $errors->hasAny(array_keys($addressFields)) ? 1 : ($errors->hasAny(['payment_method', 'wallet_notice']) ? 2 : ($errors->has('delivery_note') ? 3 : 1)))
    @php($fieldInput = 'min-h-[46px] w-full rounded-[10px] border-[#CBD4E0] bg-white px-3 text-[14.5px] text-ink caret-brand transition placeholder:text-slate-500 focus:border-brand focus:shadow-[0_0_0_3px_rgb(32_91_244/12%)] focus:ring-0 aria-[invalid=true]:border-danger')

    {{-- Room at the foot so the fixed phone action bar never covers the last of the summary. --}}
    <div class="page-shell min-h-[calc(100dvh-4.5rem)] !pt-6 max-lg:pb-28 max-sm:!pt-3.5">
        <div class="page-container" x-data="{
            step: {{ $initialStep }},
            done: @js(range(1, $initialStep - 1)),
            placing: false,
            summaryOpen: false,
            method: @js(old('payment_method', 'cod')),
            methodLabels: @js(\App\Models\Payment::METHOD_LABELS),
            get placeLabel() { return this.method === 'cod' ? 'Place order' : (this.method === 'bank' ? 'Place order & show bank details' : 'Place order & show ' + this.methodLabels[this.method] + ' QR') },
            fields: @js(array_keys($addressFields)),
            addresses: @js($addresses->map(fn ($address) => $address->only(['id', 'recipient_name', 'phone', 'address_line', 'city', 'province', 'postal_code']))),
            choice: @js(session()->hasOldInput() || $addresses->isEmpty() ? 'new' : $addresses->first()->id),
            addressShort: '',
            addressFull: '',
            init() { this.syncAddress() },
            collapsed(number) { return this.step !== number && this.done.includes(number) },
            incomplete(id) { const address = this.addresses.find((item) => item.id === id); return ! address || this.fields.some((field) => ! address[field]) },
            get showFields() { return this.choice === 'new' || this.incomplete(this.choice) },
            choose(id) {
                this.choice = id;
                const address = this.addresses.find((item) => item.id === id);
                for (const field of this.fields) {
                    document.getElementById(field).value = address?.[field] ?? '';
                }
                this.syncAddress();
            },
            syncAddress() {
                const value = Object.fromEntries(this.fields.map((field) => [field, document.getElementById(field).value.trim()]));
                this.addressShort = [value.recipient_name, value.address_line, value.city].filter(Boolean).join(' · ');
                this.addressFull = [[value.recipient_name, value.phone].filter(Boolean).join(' · '), [value.address_line, value.city, value.province, value.postal_code].filter(Boolean).join(', ')].filter(Boolean).join('\n');
            },
            open(number, focus = true) {
                this.syncAddress();
                this.step = number;
                if (! focus) return;
                this.$nextTick(() => {
                    const head = document.getElementById('step-' + number + '-title');
                    head.scrollIntoView({ block: 'start', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
                    head.focus({ preventScroll: true });
                });
            },
            complete(number) { if (! this.done.includes(number)) this.done.push(number) },
            {{-- Hidden steps cannot show the browser's own validation bubble, so the step holding the problem is opened first. --}}
            validAddress() {
                if (this.$refs.form.noValidate) return true;
                const invalid = this.fields.map((field) => document.getElementById(field)).find((input) => ! input.checkValidity());
                if (! invalid) return true;
                this.open(1, false);
                this.$nextTick(() => invalid.reportValidity());
                return false;
            },
            validPayment() {
                if (this.$refs.form.noValidate || this.method === 'cod' || this.$refs.walletNotice.checked) return true;
                this.open(2, false);
                this.$nextTick(() => this.$refs.walletNotice.reportValidity());
                return false;
            },
            next() {
                if (this.step === 1 && this.validAddress()) { this.complete(1); this.open(2) }
                else if (this.step === 2 && this.validPayment()) { this.complete(2); this.open(3) }
            },
            place(event) { if (! this.validAddress() || ! this.validPayment()) event.preventDefault() },
            {{-- The phone bar's button stays type=button: swapping it to submit mid-click would place the order a step early. --}}
            submit() { if (this.validAddress() && this.validPayment()) this.$refs.form.requestSubmit() },
        }">
            <a href="{{ route('cart.index') }}" class="inline-flex min-h-10 items-center gap-1.5 text-[13px] font-semibold text-brand transition-colors hover:text-brand-hover">
                <svg class="size-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m0 0 6-6m-6 6 6 6"/></svg>
                Back to cart
            </a>
            <div class="mt-1">
                <h1 class="text-[30px] font-semibold leading-[1.1] tracking-[-0.035em] text-ink max-sm:text-[26px]">Checkout</h1>
                <p class="mt-1.5 text-sm text-muted">{{ $itemCount }} {{ Str::plural('item', $itemCount) }} from {{ $itemsByStore->count() }} {{ Str::plural('store', $itemsByStore->count()) }} · Free shipping</p>
            </div>

            <form id="checkout-form" x-ref="form" method="POST" action="{{ route('checkout.store') }}" @submit="placing = true" class="mt-[22px] grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_368px] lg:gap-7">
                @csrf
                <div class="grid min-w-0 gap-3.5">
                    @if ($orderLimitReached)
                        <div class="rounded-[10px] border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-950" role="alert">
                            <p class="font-semibold">You can place this order once your current one is delivered.</p>
                            <p class="mt-1">Cash-on-delivery parcels on your account were refused recently, so you can have one order in progress at a time. <a href="{{ route('orders.index') }}" class="font-semibold underline">Track your current order</a>. If a refusal wasn't your fault, ask for order help on that order.</p>
                        </div>
                    @elseif ($errors->has('cart'))
                        <div class="rounded-[10px] border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-950" role="alert">{{ $errors->first('cart') }} Return to your cart to update it, then try again.</div>
                    @endif
                    @php($fieldErrors = collect([...$addressFields, 'delivery_note' => 'Delivery note'])->filter(fn ($label, $field) => $errors->has($field)))
                    @if ($fieldErrors->isNotEmpty())
                        {{-- A rejected field can sit far below the fold, so the page says what to fix and takes the reader there. --}}
                        <div role="alert" tabindex="-1" x-init="$el.focus()" aria-labelledby="checkout-errors-title" class="flex gap-2.5 rounded-[10px] border border-[#F3C7CD] bg-[#FFF5F5] px-3.5 py-3 text-[13.5px] leading-[1.45] text-ink">
                            <svg class="mt-px size-[18px] shrink-0 text-danger" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v5"/><circle cx="12" cy="16.3" r=".9" fill="currentColor" stroke="none"/></svg>
                            <div>
                                <h2 id="checkout-errors-title" class="font-semibold text-danger">{{ $fieldErrors->count() === 1 ? 'There is 1 detail to fix' : 'There are '.$fieldErrors->count().' details to fix' }}</h2>
                                <ul class="mt-1 grid gap-0.5">
                                    @foreach ($fieldErrors as $field => $label)
                                        <li><a href="#{{ $field }}" class="font-medium text-danger underline underline-offset-2">{{ $label }}: {{ $errors->first($field) }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    {{-- One sheet keeps the flow legible as a single form rather than a stack of cards. --}}
                    <div class="divide-y divide-line overflow-hidden rounded-[16px] border border-line bg-surface">
                        <x-marketplace.checkout-step :number="1" title="Delivery address" sub="Where should we send your order?" value="addressShort">
                            @if ($addresses->isNotEmpty())
                                <div class="overflow-hidden rounded-xl border border-line" role="radiogroup" aria-label="Delivery address">
                                    @foreach ($addresses as $address)
                                        <label class="grid cursor-pointer grid-cols-[auto_minmax(0,1fr)] items-start gap-3 border-b border-line px-4 py-[15px] transition-colors hover:bg-slate-50 has-[:checked]:bg-[#EEF3FF] has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:-outline-offset-2 has-[:focus-visible]:outline-brand">
                                            <input type="radio" name="saved_address" value="{{ $address->id }}" :checked="choice === @js($address->id)" @change="choose(@js($address->id))" class="mt-[3px] size-[17px] border-[#CBD4E0] text-brand focus:ring-0 focus:ring-offset-0">
                                            <span class="min-w-0">
                                                <span class="block text-sm font-semibold leading-[1.4] text-ink">{{ $address->recipient_name }}</span>
                                                <span class="mt-0.5 block text-[13px] leading-normal text-muted [overflow-wrap:anywhere]">{{ collect([$address->address_line, $address->city, $address->province, $address->postal_code])->filter()->implode(', ') }}</span>
                                                <span class="mt-0.5 block text-[13px] leading-normal text-muted">{{ $address->phone }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                    <label class="grid cursor-pointer grid-cols-[auto_minmax(0,1fr)] items-start gap-3 px-4 py-[15px] transition-colors hover:bg-slate-50 has-[:checked]:bg-[#EEF3FF] has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:-outline-offset-2 has-[:focus-visible]:outline-brand">
                                        <input type="radio" name="saved_address" value="new" :checked="choice === 'new'" @change="choose('new')" class="mt-[3px] size-[17px] border-[#CBD4E0] text-brand focus:ring-0 focus:ring-offset-0">
                                        <span class="min-w-0">
                                            <span class="block text-sm font-semibold leading-[1.4] text-ink">Use a new address</span>
                                            <span class="mt-0.5 block text-[13px] leading-normal text-muted">Deliver somewhere else this time.</span>
                                        </span>
                                    </label>
                                </div>
                            @endif

                            <div x-show="showFields" @input="syncAddress()" @class(['mt-4 border-t border-line pt-[18px]' => $addresses->isNotEmpty()])>
                                <p x-show="choice !== 'new'" class="mb-3.5 text-[13px] text-muted">Fill in any missing details from this older saved address.</p>
                                <div class="grid grid-cols-2 gap-x-3 gap-y-3.5 max-sm:grid-cols-1">
                                    @foreach ($addressFields as $field => $label)
                                        @php($wide = $field === 'address_line')
                                        <div @class(['col-span-full' => $wide])>
                                            <label for="{{ $field }}" class="mb-1.5 block text-[13.5px] font-semibold text-ink">{{ $label }}</label>
                                            <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $preferredAddress?->{$field}) }}" required
                                                @switch($field)
                                                    @case('recipient_name') autocomplete="name" maxlength="150" placeholder="Full name" @break
                                                    @case('phone') type="tel" autocomplete="tel" maxlength="13" placeholder="09171234567" @break
                                                    @case('address_line') autocomplete="street-address" maxlength="255" placeholder="House number, street, and barangay" @break
                                                    @case('city') autocomplete="address-level2" maxlength="100" @break
                                                    @case('province') autocomplete="address-level1" maxlength="100" @break
                                                    @case('postal_code') inputmode="numeric" autocomplete="postal-code" pattern="[0-9]{4}" maxlength="4" placeholder="1600" @break
                                                @endswitch
                                                @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror class="{{ $fieldInput }}">
                                            @error($field)<p id="{{ $field }}-error" class="mt-[5px] text-[12.5px] font-medium leading-[1.45] text-danger" role="alert">{{ $message }}</p>@enderror
                                        </div>
                                    @endforeach
                                </div>
                                <p class="mt-3 text-xs text-muted">We'll save this address for your next checkout.</p>
                            </div>

                            <div class="mt-4 flex items-center justify-between gap-3.5">
                                <a href="{{ route('delivery-addresses.index') }}" class="inline-flex min-h-10 items-center gap-1.5 text-[13px] font-semibold text-brand transition-colors hover:text-brand-hover">
                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                    Manage saved addresses
                                </a>
                                <button type="button" @click="next()" class="inline-flex min-h-[46px] items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover active:translate-y-px max-sm:hidden">Continue to payment</button>
                            </div>
                        </x-marketplace.checkout-step>

                        <x-marketplace.checkout-step :number="2" title="Payment method" sub="Choose how you would like to pay." value="methodLabels[method]">
                            <fieldset class="grid grid-cols-2 gap-2.5 max-sm:grid-cols-1">
                                <legend class="sr-only">Payment method</legend>
                                @foreach ([
                                    'cod' => ['label' => 'Cash on delivery', 'hint' => 'Pay ₱'.number_format($subtotal, 2).' when it arrives.', 'tile' => 'bg-[#EEF3FF] text-brand'],
                                    'gcash' => ['label' => 'GCash', 'hint' => 'Scan each store\'s QR after ordering.', 'tile' => 'bg-[#0070F0] text-white'],
                                    'maya' => ['label' => 'Maya', 'hint' => 'Scan each store\'s Maya QR after ordering.', 'tile' => 'bg-[#101820] text-[#3DDC84]'],
                                    'bank' => ['label' => 'Bank transfer', 'hint' => 'InstaPay or PESONet to each store.', 'tile' => 'bg-slate-100 text-slate-600'],
                                ] as $method => $option)
                                    @continue($method !== 'cod' && ! in_array($method, $walletMethods, true))
                                    <label class="grid cursor-pointer grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-2.5 rounded-xl border border-line bg-white px-3.5 py-[13px] transition hover:border-[#CBD4E0] has-[:checked]:border-brand has-[:checked]:bg-[#EEF3FF] has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-brand">
                                        <input type="radio" name="payment_method" value="{{ $method }}" x-model="method" @checked(old('payment_method', 'cod') === $method) class="size-[17px] border-[#CBD4E0] text-brand focus:ring-0 focus:ring-offset-0">
                                        <span class="min-w-0"><span class="block text-sm font-semibold leading-[1.35] text-ink">{{ $option['label'] }}</span><span class="mt-0.5 block text-[12.5px] leading-[1.45] text-muted">{{ $option['hint'] }}</span></span>
                                        <span class="grid size-[30px] place-items-center rounded-lg text-xs font-bold leading-none {{ $option['tile'] }}" aria-hidden="true">
                                            @switch($method)
                                                @case('cod')<svg class="size-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><path stroke-linecap="round" d="M6 9.5h.01M18 14.5h.01"/></svg>@break
                                                @case('gcash')G@break
                                                @case('maya')M@break
                                                @case('bank')<svg class="size-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 10 9-6 9 6M5 10v8M9.5 10v8M14.5 10v8M19 10v8M3 21h18"/></svg>@break
                                            @endswitch
                                        </span>
                                    </label>
                                @endforeach
                            </fieldset>
                            @error('payment_method')<p class="mt-2 text-[12.5px] font-medium text-danger" role="alert">{{ $message }}</p>@enderror

                            <div x-show="method === 'cod'" class="mt-3.5 flex items-start gap-[9px] rounded-[10px] border border-line bg-slate-50 px-3.5 py-3 text-[13px] leading-normal text-muted">
                                <svg class="mt-px size-4 shrink-0 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/></svg>
                                <p><strong class="font-semibold text-ink">No card needed.</strong> Hand <strong class="font-semibold text-ink">₱{{ number_format($subtotal, 2) }}</strong> to the courier when your order arrives.</p>
                            </div>

                            @if ($walletMethods !== [])
                                <div x-cloak x-show="method !== 'cod'" class="mt-3.5 rounded-xl border border-[#D6E2FB] bg-[#F8FAFF] p-4" role="group" aria-labelledby="direct-pay-heading">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p id="direct-pay-heading" class="text-[13.5px] font-semibold text-ink">Paying by <span x-text="methodLabels[method]"></span></p>
                                        <span class="rounded-full bg-[#E8EFFF] px-[9px] py-[3px] text-[11.5px] font-semibold text-brand-hover">{{ $storeTotals->count() }} {{ Str::plural('store', $storeTotals->count()) }} · paid separately</span>
                                    </div>
                                    <ul class="mt-3 grid grid-cols-2 gap-2 max-sm:grid-cols-1" role="list">
                                        @foreach ($storeTotals as $storeTotal)
                                            <li class="flex items-center justify-between gap-2.5 rounded-[9px] border border-line bg-white px-2.5 py-2 text-xs font-semibold text-ink"><span class="min-w-0 truncate">{{ $storeTotal['name'] }}</span><span class="whitespace-nowrap tabular-nums">₱{{ number_format($storeTotal['total'], 2) }}</span></li>
                                        @endforeach
                                    </ul>
                                    <ol class="mt-3 grid grid-cols-3 gap-2.5 max-sm:grid-cols-1">
                                        @foreach ([
                                            ['Place your order', 'Items are held for <span x-text="method === \'bank\' ? '.(int) config('marketplace.wallet.payment_hours.bank').' : '.(int) config('marketplace.wallet.payment_hours.gcash').'"></span> hours.'],
                                            ['Pay each store', 'Scan its QR or send to its <span x-text="method === \'bank\' ? \'account\' : \'number\'"></span>, exact amount.'],
                                            ['Send the reference', 'The store confirms, then ships.'],
                                        ] as [$heading, $detail])
                                            <li class="grid grid-cols-[20px_minmax(0,1fr)] items-start gap-2">
                                                <span class="grid size-5 place-items-center rounded-full bg-[#E8EFFF] text-[11px] font-bold leading-none text-brand-hover" aria-hidden="true">{{ $loop->iteration }}</span>
                                                <span><strong class="block text-[12.5px] font-semibold leading-[1.35] text-ink">{{ $heading }}</strong><small class="mt-px block text-xs leading-[1.4] text-muted">{!! $detail !!}</small></span>
                                            </li>
                                        @endforeach
                                    </ol>
                                    <div class="mt-3 flex gap-2 border-t border-[#D6E2FB] pt-3 text-[12.5px] leading-normal text-muted" role="note">
                                        <svg class="mt-0.5 size-[15px] shrink-0 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3 4.5 6v6c0 4.4 3.2 7.7 7.5 9 4.3-1.3 7.5-4.6 7.5-9V6L12 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4"/></svg>
                                        <p>Your money goes <strong class="font-semibold text-ink">straight to each store</strong>. JMMart never holds it, so we can't reverse a transfer; refunds come from the store or your bank's or e-wallet's dispute process. We keep your reference number, review order help requests, and can suspend stores that don't deliver or refund. <strong class="font-semibold text-ink">Only pay the QR or account shown on your JMMart order page</strong>, and never share your MPIN or OTP.</p>
                                    </div>
                                    <label class="mt-2.5 flex cursor-pointer items-start gap-[9px] text-[12.5px] font-medium leading-[1.45] text-ink">
                                        <input type="checkbox" name="wallet_notice" value="1" x-ref="walletNotice" :required="method !== 'cod'" @checked(old('wallet_notice')) class="mt-px size-4 rounded border-[#CBD4E0] text-brand focus:ring-0 focus:ring-offset-0">
                                        <span>I understand how paying a store directly works, as explained in the <a href="{{ route('terms') }}#wallet-payments" target="_blank" class="font-semibold text-brand underline underline-offset-2">Terms of Use</a>.</span>
                                    </label>
                                </div>
                                @error('wallet_notice')<p class="mt-2 text-[12.5px] font-medium text-danger" role="alert">{{ $message }}</p>@enderror
                            @endif

                            <div class="mt-5 flex items-center justify-end gap-3.5 max-sm:flex-col-reverse max-sm:items-stretch max-sm:gap-1">
                                <button type="button" @click="open(1)" class="inline-flex min-h-11 items-center justify-center gap-1.5 text-[13px] font-semibold text-muted transition-colors hover:text-ink">
                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m0 0 6-6m-6 6 6 6"/></svg>
                                    Back to delivery
                                </button>
                                <button type="button" @click="next()" class="inline-flex min-h-[46px] items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover active:translate-y-px max-sm:hidden">Review order</button>
                            </div>
                        </x-marketplace.checkout-step>

                        <x-marketplace.checkout-step :number="3" title="Review & place order" sub="One last look before this goes to the stores." value="'Delivery and payment'" change="Review">
                            <dl class="border-t border-line">
                                <div class="flex items-start justify-between gap-4 border-b border-line py-3">
                                    <div class="min-w-0"><dt class="text-xs font-medium text-muted">Deliver to</dt><dd class="mt-0.5 max-w-[56ch] whitespace-pre-line text-[13px] leading-normal text-ink" x-text="addressFull"></dd></div>
                                    <button type="button" @click="open(1)" class="inline-flex min-h-10 shrink-0 items-center text-[12.5px] font-semibold text-brand underline-offset-[3px] hover:text-brand-hover hover:underline">Change</button>
                                </div>
                                <div class="flex items-start justify-between gap-4 border-b border-line py-3">
                                    <div class="min-w-0"><dt class="text-xs font-medium text-muted">Payment</dt><dd class="mt-0.5 text-[13px] leading-normal text-ink" x-text="method === 'cod' ? 'Cash on delivery · Pay when your order arrives' : methodLabels[method] + ' · Pay each store after you order'"></dd></div>
                                    <button type="button" @click="open(2)" class="inline-flex min-h-10 shrink-0 items-center text-[12.5px] font-semibold text-brand underline-offset-[3px] hover:text-brand-hover hover:underline">Change</button>
                                </div>
                            </dl>

                            <div class="mt-4">
                                <label for="delivery_note" class="mb-1.5 block text-[13.5px] font-semibold text-ink">Delivery note <span class="font-normal text-muted">(optional)</span></label>
                                <textarea id="delivery_note" name="delivery_note" rows="2" maxlength="1000" placeholder="Landmark, gate code, or anything the courier should know" @error('delivery_note') aria-invalid="true" aria-describedby="delivery_note-error" @enderror class="{{ $fieldInput }} min-h-[88px] resize-y py-[11px] leading-normal">{{ old('delivery_note') }}</textarea>
                                @error('delivery_note')<p id="delivery_note-error" class="mt-[5px] text-[12.5px] font-medium text-danger" role="alert">{{ $message }}</p>@enderror
                            </div>

                            <div class="mt-4 flex gap-[9px] rounded-[10px] border border-line bg-slate-50 px-3.5 py-3 text-[13px] leading-normal text-muted">
                                <svg class="mt-px size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17.5" cy="18" r="1.6"/></svg>
                                <p>Each store confirms delivery timing after receiving your order. Shipping is free.</p>
                            </div>

                            <div class="mt-5 grid gap-3">
                                <button type="submit" @click="place($event)" :disabled="placing" class="flex min-h-[46px] w-full items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover active:translate-y-px disabled:cursor-wait disabled:bg-[#DBE3EE] disabled:text-[#7A8AA1] max-sm:hidden" x-text="placing ? 'Placing order…' : (method === 'cod' ? 'Place order · ₱{{ number_format($subtotal, 2) }}' : placeLabel)">Place order · ₱{{ number_format($subtotal, 2) }}</button>
                                <p class="text-center text-xs leading-normal text-muted max-sm:hidden" x-text="method === 'cod' ? 'You will pay on delivery.' : 'Each store\'s payment details appear on the next screen. Nothing is paid until you send it.'">You will pay on delivery.</p>
                                <button type="button" @click="open(2)" class="inline-flex min-h-11 items-center justify-center gap-1.5 justify-self-center text-[13px] font-semibold text-muted transition-colors hover:text-ink">
                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m0 0 6-6m-6 6 6 6"/></svg>
                                    Back to payment
                                </button>
                            </div>
                        </x-marketplace.checkout-step>
                    </div>
                </div>

                <aside class="min-w-0 rounded-[16px] border border-line bg-surface max-lg:order-first lg:sticky lg:top-24" aria-label="Order summary">
                    <button type="button" @click="summaryOpen = ! summaryOpen" :aria-expanded="summaryOpen.toString()" aria-controls="checkout-summary-body" class="flex w-full items-center justify-between gap-2.5 rounded-[16px] px-4 py-[15px] text-left lg:hidden">
                        <span><span class="block text-[15.5px] font-semibold tracking-[-0.012em] text-ink">Order summary</span><span class="mt-0.5 block text-[12.5px] text-muted">{{ $itemCount }} {{ Str::plural('item', $itemCount) }} from {{ $itemsByStore->count() }} {{ Str::plural('store', $itemsByStore->count()) }}</span></span>
                        <span class="flex items-center gap-2">
                            <span class="text-[15px] font-semibold tabular-nums text-ink">₱{{ number_format($subtotal, 2) }}</span>
                            <svg class="size-[18px] text-muted transition-transform duration-200" :class="summaryOpen && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                        </span>
                    </button>
                    <div class="px-5 py-[18px] max-lg:hidden">
                        <h2 id="checkout-summary-heading" class="text-[15.5px] font-semibold tracking-[-0.012em] text-ink">Order summary</h2>
                        <p class="mt-0.5 text-[12.5px] text-muted">{{ $itemCount }} {{ Str::plural('item', $itemCount) }} from {{ $itemsByStore->count() }} {{ Str::plural('store', $itemsByStore->count()) }}</p>
                    </div>

                    <div id="checkout-summary-body" class="border-t border-[#EEF1F5] px-5 pb-5 pt-4 max-lg:hidden max-lg:border-line max-sm:p-4" :class="{ 'max-lg:hidden': ! summaryOpen }">
                        @foreach ($itemsByStore as $storeItems)
                            @php($store = $storeItems->first()->product->store)
                            <section @class(['mt-4 border-t border-line pt-4' => ! $loop->first]) aria-labelledby="checkout-store-{{ $store->id }}">
                                <h3 id="checkout-store-{{ $store->id }}" class="flex items-center gap-1.5 text-[12.5px] font-semibold text-muted">
                                    <svg class="size-[13px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 9h16v11H4zM3 9l1.5-5h15L21 9M9 20v-6h6v6"/></svg>
                                    {{ $store->name }}
                                </h3>
                                <ul class="mt-2.5 grid gap-3">
                                    @foreach ($storeItems as $item)
                                        <li class="grid grid-cols-[48px_minmax(0,1fr)_auto] items-start gap-2.5">
                                            <span class="grid size-12 place-items-center overflow-hidden rounded-[9px] border border-line bg-slate-100">@if ($item->imagePath())<img src="{{ Storage::disk('product_images')->url($item->imagePath()) }}" alt="" loading="lazy" decoding="async" width="48" height="48" class="size-full object-cover">@else<svg viewBox="0 0 24 24" class="size-6 text-muted" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8" cy="9" r="1.5"/><path d="m4 18 5-5 3 3 3-3 5 5"/></svg>@endif</span>
                                            <span class="min-w-0">
                                                <span class="block text-[13px] font-medium leading-[1.4] text-ink [overflow-wrap:anywhere]">{{ $item->product->name }}</span>
                                                <span class="mt-0.5 block text-[12.5px] leading-[1.4] text-muted">{{ $item->variant ? $item->variant->label().' · ' : '' }}Qty {{ $item->quantity }}</span>
                                            </span>
                                            <span class="whitespace-nowrap pt-px text-[13px] font-semibold tabular-nums text-ink">₱{{ number_format($item->line_total, 2) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </section>
                        @endforeach

                        <dl class="mt-4 grid gap-2 border-t border-dashed border-[#DBE3EF] pt-3.5 text-[13px]">
                            <div class="flex justify-between gap-3 text-muted"><dt>Subtotal</dt><dd class="tabular-nums text-ink">₱{{ number_format($subtotal, 2) }}</dd></div>
                            <div class="flex justify-between gap-3 text-muted"><dt>Shipping</dt><dd class="font-semibold text-success">Free</dd></div>
                            <div class="mt-1 flex items-baseline justify-between gap-3 border-t border-line pt-3 text-sm font-semibold text-ink"><dt>Total</dt><dd class="text-[20px] leading-none tracking-[-0.025em] tabular-nums">₱{{ number_format($subtotal, 2) }}</dd></div>
                        </dl>
                        <p class="mt-3.5 flex items-center gap-1.5 text-[12.5px] leading-[1.4] text-muted">
                            <svg class="size-[13px] shrink-0 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/></svg>
                            Free shipping · No hidden fees
                        </p>
                    </div>
                </aside>

                {{--
                    On a phone the step buttons give way to one bar that keeps the total in view and
                    moves the buyer through the same three steps.
                --}}
                <div class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-white/95 px-4 pb-[max(0.625rem,env(safe-area-inset-bottom))] pt-2.5 shadow-[0_-8px_24px_-18px_rgb(15_23_42/35%)] backdrop-blur lg:hidden">
                    <div class="mx-auto flex max-w-[560px] items-center gap-3.5">
                        <div class="min-w-0">
                            <p class="text-[11.5px] text-muted">Total to pay</p>
                            <p class="truncate text-lg/tight font-semibold tracking-[-0.025em] tabular-nums text-ink">₱{{ number_format($subtotal, 2) }}</p>
                        </div>
                        <button type="button" @click="step === 3 ? submit() : next()" :disabled="placing" class="ml-auto flex min-h-[46px] min-w-[140px] shrink-0 items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover active:translate-y-px disabled:cursor-wait disabled:bg-[#DBE3EE] disabled:text-[#7A8AA1] max-[380px]:min-w-[118px] max-[380px]:px-3.5" x-text="placing ? 'Placing order…' : ({ 1: 'Continue', 2: 'Review order' }[step] ?? 'Place order')">Continue</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
