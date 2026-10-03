<x-app-layout>
    <x-slot name="header">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-semibold tracking-[-0.03em] text-slate-950">Review and place your order</h2>
                <p class="mt-1 text-sm text-slate-500">Cash on delivery · Free shipping</p>
            </div>
            <a href="{{ route('cart.index') }}" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-950 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">Edit cart</a>
        </div>
    </x-slot>

    @php($itemsByStore = $cart?->items->groupBy(fn ($item) => $item->product->store_id) ?? collect())

    <div class="min-h-screen bg-slate-50 py-8 sm:py-12">
        <div class="mx-auto grid max-w-6xl gap-8 px-4 sm:px-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:px-8">
            <form id="checkout-form" method="POST" action="{{ route('checkout.store') }}" class="rounded-2xl bg-white p-5 shadow-[0_16px_32px_-28px_rgba(15,23,42,0.45)] sm:p-8">
                @csrf
                <div class="border-b border-slate-100 pb-6">
                    <h3 class="text-lg font-semibold text-slate-950">Delivery details</h3>
                    <p class="mt-1 text-sm leading-6 text-slate-500">Enter the address where your order should be delivered.</p>
                </div>

                @if ($errors->has('cart'))
                    <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-950" role="alert">{{ $errors->first('cart') }} Return to your cart to update it, then try again.</div>
                @endif

                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="recipient_name" class="text-sm font-semibold text-slate-800">Recipient name</label>
                        <input id="recipient_name" name="recipient_name" value="{{ old('recipient_name') }}" autocomplete="name" required aria-describedby="recipient_name-error" class="mt-2 min-h-12 w-full rounded-xl border-slate-300 text-sm text-slate-950 placeholder:text-slate-400 focus:border-emerald-700 focus:ring-emerald-700" />
                        @error('recipient_name')<p id="recipient_name-error" class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="phone" class="text-sm font-semibold text-slate-800">Mobile number</label>
                        <input id="phone" name="phone" value="{{ old('phone') }}" type="tel" inputmode="tel" autocomplete="tel" required aria-describedby="phone-error" class="mt-2 min-h-12 w-full rounded-xl border-slate-300 text-sm text-slate-950 placeholder:text-slate-400 focus:border-emerald-700 focus:ring-emerald-700" />
                        @error('phone')<p id="phone-error" class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="address_line" class="text-sm font-semibold text-slate-800">Street address</label>
                        <input id="address_line" name="address_line" value="{{ old('address_line') }}" autocomplete="street-address" required aria-describedby="address_line-error" class="mt-2 min-h-12 w-full rounded-xl border-slate-300 text-sm text-slate-950 placeholder:text-slate-400 focus:border-emerald-700 focus:ring-emerald-700" />
                        @error('address_line')<p id="address_line-error" class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="city" class="text-sm font-semibold text-slate-800">City</label>
                        <input id="city" name="city" value="{{ old('city') }}" autocomplete="address-level2" required aria-describedby="city-error" class="mt-2 min-h-12 w-full rounded-xl border-slate-300 text-sm text-slate-950 placeholder:text-slate-400 focus:border-emerald-700 focus:ring-emerald-700" />
                        @error('city')<p id="city-error" class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="mt-8 hidden lg:block"><button class="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-emerald-700 px-4 text-sm font-semibold text-white transition duration-200 hover:bg-emerald-800 active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">Place order · ₱{{ number_format($subtotal, 2) }}</button></div>
            </form>

            <aside class="lg:sticky lg:top-6 lg:self-start">
                <div class="rounded-2xl bg-white p-5 shadow-[0_16px_32px_-28px_rgba(15,23,42,0.45)] sm:p-6">
                    <h3 class="text-lg font-semibold text-slate-950">Your order</h3>
                    <div class="mt-5 space-y-5">
                        @forelse ($itemsByStore as $storeItems)
                            @php($store = $storeItems->first()->product->store)
                            <section aria-labelledby="checkout-store-{{ $store->id }}">
                                <h4 id="checkout-store-{{ $store->id }}" class="text-xs font-bold uppercase tracking-[0.12em] text-emerald-800">{{ $store->name }}</h4>
                                <ul class="mt-3 space-y-3">
                                    @foreach ($storeItems as $item)
                                        <li class="flex items-start justify-between gap-4 text-sm"><span class="min-w-0 leading-5 text-slate-700"><span class="block break-words font-medium text-slate-950">{{ $item->product->name }}</span>Qty {{ $item->quantity }}</span><span class="shrink-0 font-semibold text-slate-950">₱{{ number_format($item->line_total, 2) }}</span></li>
                                    @endforeach
                                </ul>
                            </section>
                        @empty
                            <p class="text-sm leading-6 text-slate-500">Your cart is empty. Add products before checking out.</p>
                        @endforelse
                    </div>
                    <div class="mt-6 space-y-3 border-t border-slate-100 pt-5 text-sm"><div class="flex justify-between text-slate-600"><span>Subtotal</span><span>₱{{ number_format($subtotal, 2) }}</span></div><div class="flex justify-between text-slate-600"><span>Shipping</span><span class="font-semibold text-emerald-700">Free</span></div><div class="flex items-end justify-between pt-2 text-slate-950"><span class="font-semibold">Total</span><span class="text-2xl font-bold tracking-[-0.04em]">₱{{ number_format($subtotal, 2) }}</span></div></div>
                    <p class="mt-5 text-xs leading-5 text-slate-500">You’ll pay the delivery rider when your order arrives. Each seller receives only the items from their store.</p>
                </div>
            </aside>
        </div>

        <div class="sticky bottom-0 z-20 mt-6 border-t border-slate-200 bg-[#f7f7f2]/95 px-4 py-3 backdrop-blur lg:hidden"><div class="mx-auto flex max-w-6xl items-center gap-3"><span class="min-w-0 flex-1 text-sm font-semibold text-slate-950">Total · ₱{{ number_format($subtotal, 2) }}</span><button form="checkout-form" class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-xl bg-emerald-700 px-5 text-sm font-semibold text-white transition duration-200 hover:bg-emerald-800 active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">Place order</button></div></div>
    </div>
</x-app-layout>
