<x-app-layout>
    <x-slot name="header">
        <div class="mx-auto flex max-w-[860px] items-center gap-3">
            <a href="{{ route('profile.edit') }}" class="inline-flex min-h-11 items-center gap-1.5 text-sm font-semibold text-muted transition hover:text-ink" aria-label="Back to account">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" /></svg>
                Account
            </a>
            <span aria-hidden="true" class="text-line">|</span>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-ink">Become a seller</h1>
                <p class="mt-0.5 text-sm text-muted">No new login — this upgrades your customer account.</p>
            </div>
        </div>
    </x-slot>

    <div class="page-shell">
        <div class="mx-auto w-full max-w-[860px] px-[18px] sm:px-8">
            @if (session('status'))
                <p class="mb-6 rounded-[14px] border border-success/20 bg-success/10 px-4 py-3 text-sm font-medium text-success" role="status">{{ session('status') }}</p>
            @endif

            @if ($workspaceUnavailable)
                @if ($workspaceSuspended)
                    <div class="rounded-[14px] border border-line bg-white p-5 shadow-sm sm:p-8">
                        <div class="flex items-start gap-3 rounded-[14px] border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900" role="status">
                            <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M12 8v4m0 4h.01" /></svg>
                            <div>
                                <p class="font-semibold">Your store is currently suspended.</p>
                                <p class="mt-1">You cannot submit another seller application while this is being resolved. Scope: selling is paused, shopping is unaffected.</p>
                                @if ($suspensionReason)
                                    <p class="mt-3"><span class="font-semibold">Suspension reason:</span> {{ $suspensionReason }}</p>
                                @endif
                            </div>
                        </div>

                        @if ($suspensionAppeal?->status === 'pending')
                            <div class="mt-4 rounded-[14px] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="status">
                                <p class="font-semibold">Your appeal is awaiting review.</p>
                                <p class="mt-1 whitespace-pre-line">{{ $suspensionAppeal->statement }}</p>
                                <p class="mt-2 text-xs">Submitted {{ $suspensionAppeal->created_at->format('M j, Y · g:i A') }}</p>
                            </div>
                        @elseif ($suspensionAppeal?->status === 'rejected')
                            <div class="mt-4 rounded-[14px] border border-line bg-canvas p-4 text-sm text-ink" role="status">
                                <p class="font-semibold text-ink">Your previous appeal was not approved.</p>
                                @if ($suspensionAppeal->review_note)
                                    <p class="mt-1 whitespace-pre-line">{{ $suspensionAppeal->review_note }}</p>
                                @endif
                                <p class="mt-2 whitespace-pre-line">{{ $suspensionAppeal->statement }}</p>
                            </div>
                        @endif

                        @if ($canSubmitSuspensionAppeal)
                            <form method="POST" action="{{ route('seller.suspension-appeals.store') }}" class="mt-5 space-y-4">
                                @csrf
                                <div>
                                    <x-input-label for="appeal-statement" value="Explain why this suspension should be reviewed" />
                                    <textarea id="appeal-statement" name="statement" rows="6" minlength="20" maxlength="5000" required class="mt-1.5 block w-full rounded-[10px] border-line text-sm shadow-sm focus:border-brand focus:ring-brand" placeholder="Share what happened, what you have corrected, and any information that may help us review your appeal.">{{ old('statement') }}</textarea>
                                    <p class="mt-1 text-xs text-muted">20 to 5,000 characters.</p>
                                    <x-input-error :messages="$errors->get('statement')" class="mt-2" />
                                    <x-input-error :messages="$errors->get('appeal')" class="mt-2" />
                                </div>
                                <x-primary-button>Submit an appeal</x-primary-button>
                            </form>
                        @endif
                    </div>
                @else
                    <p class="rounded-[14px] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="status">Your existing seller workspace is unavailable, so you cannot submit another seller application. Contact JMMart support if you need help.</p>
                @endif
            @elseif ($application?->status === 'pending')
                <ol class="flex gap-1.5" aria-label="Application progress">
                    @foreach (['Intro', 'Info', 'Store', 'Verify', 'Review', 'Ready'] as $index => $label)
                        <li class="flex-1 border-t-[3px] {{ $index < 5 ? 'border-brand' : 'border-line' }} pt-2 text-xs font-semibold {{ $index < 5 ? 'text-brand' : 'text-muted' }}">{{ $label }}</li>
                    @endforeach
                </ol>
                <div class="mt-5 rounded-[14px] border border-line bg-white p-5 shadow-sm sm:p-8">
                    <div class="flex items-start gap-3">
                        <svg class="size-6 shrink-0 text-warning-text" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M12 7v5l3 2" /></svg>
                        <div>
                            <h2 class="text-lg font-semibold">Application pending verification</h2>
                            <p class="mt-1 text-sm leading-6 text-muted">We review applications manually and will notify you once there’s a decision. Shopping with your account is unaffected while you wait.</p>
                            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                <div><dt class="text-xs font-semibold uppercase tracking-wide text-muted">Store</dt><dd class="mt-0.5 font-medium">{{ $application->store_name }}</dd></div>
                                <div><dt class="text-xs font-semibold uppercase tracking-wide text-muted">Contact</dt><dd class="mt-0.5 font-medium">{{ $application->seller_name }}</dd></div>
                            </dl>
                            <a href="{{ route('catalog.index') }}" class="mt-5 inline-flex min-h-11 items-center rounded-[10px] border border-line px-4 text-sm font-semibold transition hover:border-brand">Continue shopping</a>
                        </div>
                    </div>
                </div>
            @else
                @php
                    $initialStep = 1;
                    if ($errors->hasAny(['seller_name', 'phone'])) { $initialStep = 2; }
                    elseif ($errors->hasAny(['store_name', 'store_description', 'store_category'])) { $initialStep = 3; }
                    elseif ($errors->hasAny(['address_line', 'city'])) { $initialStep = 4; }
                    elseif ($errors->any()) { $initialStep = 5; }
                @endphp
                @if ($application?->status === 'rejected')
                    <div class="mb-5 flex items-start gap-3 rounded-[14px] border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900" role="status">
                        <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="m9 9 6 6M15 9l-6 6" /></svg>
                        <div><p class="font-semibold">Your previous application was not approved.</p>@if (filled($application->review_note))<p class="mt-1">{{ $application->review_note }}</p>@endif<p class="mt-2">Fix the details below and submit again.</p></div>
                    </div>
                @elseif ($application?->status === 'approved')
                    <p class="mb-5 rounded-[14px] border border-success/20 bg-success/10 p-4 text-sm text-success" role="status">Your application has been approved. Your seller workspace will be available once the store is active.</p>
                @endif

                <div x-data="{
                    step: {{ $initialStep }},
                    form: {
                        seller_name: @js(old('seller_name', $application?->seller_name ?? auth()->user()->name)),
                        phone: @js(old('phone', $application?->phone)),
                        store_name: @js(old('store_name', $application?->store_name)),
                        store_description: @js(old('store_description', $application?->store_description)),
                        store_category: @js(old('store_category', $application?->store_category)),
                        address_line: @js(old('address_line', $application?->address_line)),
                        city: @js(old('city', $application?->city)),
                    },
                    go(n) { this.step = n; window.scrollTo({ top: 0, behavior: 'smooth' }); },
                }">
                    <ol class="flex gap-1.5" aria-label="Application steps">
                        <template x-for="(label, i) in ['Intro', 'Info', 'Store', 'Verify', 'Review', 'Ready']" :key="label">
                            <li class="flex-1 border-t-[3px] pt-2 text-xs font-semibold" :class="(i + 1) <= step ? 'border-brand text-brand' : 'border-line text-muted'"><span x-text="label"></span></li>
                        </template>
                    </ol>

                    <div class="mt-5 rounded-[14px] border border-line bg-white p-5 shadow-sm sm:p-8">
                        {{-- Step 1: Intro --}}
                        <section x-show="step === 1" aria-labelledby="sell-intro">
                            <h2 id="sell-intro" class="text-xl font-semibold">Sell to JMMart shoppers</h2>
                            <ul class="mt-4 space-y-3 text-sm leading-6 text-ink">
                                <li class="flex gap-2.5"><span class="text-success" aria-hidden="true">✓</span>Keep shopping normally — selling never locks your customer account.</li>
                                <li class="flex gap-2.5"><span class="text-success" aria-hidden="true">✓</span>Cash on delivery and free shipping are built in for every order.</li>
                                <li class="flex gap-2.5"><span class="text-success" aria-hidden="true">✓</span>Manage products, orders, earnings, and messages from Seller Center.</li>
                            </ul>
                            <div class="mt-6 flex flex-wrap gap-2">
                                <button type="button" @click="go(2)" class="inline-flex min-h-12 items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover">Start application</button>
                                <a href="{{ route('catalog.index') }}" class="inline-flex min-h-12 items-center justify-center rounded-[10px] border border-line px-5 text-sm font-semibold transition hover:border-brand">Not now</a>
                            </div>
                        </section>

                        <form method="POST" action="{{ route('seller-application.store') }}" x-show="step >= 2" @submit="submitting = true" x-data="{ submitting: false }">
                            @csrf
                            {{-- Step 2: Info --}}
                            <section x-show="step === 2" aria-labelledby="sell-info">
                                <h2 id="sell-info" class="text-xl font-semibold">Your contact info</h2>
                                <p class="mt-1 text-sm text-muted">We use this to verify your identity and reach you about your store.</p>
                                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                                    <div>
                                        <x-input-label for="seller_name" value="Seller contact name" />
                                        <input id="seller_name" name="seller_name" x-model="form.seller_name" class="mt-1.5 block min-h-12 w-full rounded-[10px] border-line text-sm shadow-sm focus:border-brand focus:ring-brand" required autofocus />
                                        <x-input-error :messages="$errors->get('seller_name')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="phone" value="Phone" />
                                        <input id="phone" name="phone" x-model="form.phone" class="mt-1.5 block min-h-12 w-full rounded-[10px] border-line text-sm shadow-sm focus:border-brand focus:ring-brand" required />
                                        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                                    </div>
                                </div>
                                <div class="mt-6 flex justify-between gap-2">
                                    <button type="button" @click="go(1)" class="inline-flex min-h-12 items-center rounded-[10px] border border-line px-5 text-sm font-semibold transition hover:border-brand">Back</button>
                                    <button type="button" @click="go(3)" class="inline-flex min-h-12 items-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover">Continue</button>
                                </div>
                            </section>

                            {{-- Step 3: Store --}}
                            <section x-show="step === 3" x-cloak aria-labelledby="sell-store">
                                <h2 id="sell-store" class="text-xl font-semibold">Your store</h2>
                                <p class="mt-1 text-sm text-muted">This is how shoppers will see you in the marketplace.</p>
                                <div class="mt-5 space-y-5">
                                    <div>
                                        <x-input-label for="store_name" value="Store name" />
                                        <input id="store_name" name="store_name" x-model="form.store_name" class="mt-1.5 block min-h-12 w-full rounded-[10px] border-line text-sm shadow-sm focus:border-brand focus:ring-brand" required />
                                        <x-input-error :messages="$errors->get('store_name')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="store_description" value="Store description" />
                                        <textarea id="store_description" name="store_description" x-model="form.store_description" rows="4" class="mt-1.5 block w-full rounded-[10px] border-line text-sm shadow-sm focus:border-brand focus:ring-brand"></textarea>
                                        <x-input-error :messages="$errors->get('store_description')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="store_category" value="Store category (optional)" />
                                        <input id="store_category" name="store_category" x-model="form.store_category" class="mt-1.5 block min-h-12 w-full rounded-[10px] border-line text-sm shadow-sm focus:border-brand focus:ring-brand" />
                                        <x-input-error :messages="$errors->get('store_category')" class="mt-2" />
                                    </div>
                                </div>
                                <div class="mt-6 flex justify-between gap-2">
                                    <button type="button" @click="go(2)" class="inline-flex min-h-12 items-center rounded-[10px] border border-line px-5 text-sm font-semibold transition hover:border-brand">Back</button>
                                    <button type="button" @click="go(4)" class="inline-flex min-h-12 items-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover">Continue</button>
                                </div>
                            </section>

                            {{-- Step 4: Verify --}}
                            <section x-show="step === 4" x-cloak aria-labelledby="sell-verify">
                                <h2 id="sell-verify" class="text-xl font-semibold">Verify your address</h2>
                                <p class="mt-1 text-sm text-muted">Shopping with your account is unaffected while we review. We notify you once there’s a decision.</p>
                                <div class="mt-5 space-y-5">
                                    <div>
                                        <x-input-label for="address_line" value="Address" />
                                        <input id="address_line" name="address_line" x-model="form.address_line" class="mt-1.5 block min-h-12 w-full rounded-[10px] border-line text-sm shadow-sm focus:border-brand focus:ring-brand" required />
                                        <x-input-error :messages="$errors->get('address_line')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="city" value="City" />
                                        <input id="city" name="city" x-model="form.city" class="mt-1.5 block min-h-12 w-full rounded-[10px] border-line text-sm shadow-sm focus:border-brand focus:ring-brand" required />
                                        <x-input-error :messages="$errors->get('city')" class="mt-2" />
                                    </div>
                                </div>
                                <div class="mt-6 flex justify-between gap-2">
                                    <button type="button" @click="go(3)" class="inline-flex min-h-12 items-center rounded-[10px] border border-line px-5 text-sm font-semibold transition hover:border-brand">Back</button>
                                    <button type="button" @click="go(5)" class="inline-flex min-h-12 items-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover">Review application</button>
                                </div>
                            </section>

                            {{-- Step 5: Review --}}
                            <section x-show="step === 5" x-cloak aria-labelledby="sell-review">
                                <h2 id="sell-review" class="text-xl font-semibold">Review and submit</h2>
                                <p class="mt-1 text-sm text-muted">After submitting, your application will be pending until we approve it.</p>
                                <dl class="mt-5 divide-y divide-line rounded-[10px] border border-line text-sm">
                                    <div class="grid grid-cols-[8rem_1fr] gap-3 px-4 py-3"><dt class="text-muted">Contact</dt><dd class="font-medium" x-text="form.seller_name || '—'"></dd></div>
                                    <div class="grid grid-cols-[8rem_1fr] gap-3 px-4 py-3"><dt class="text-muted">Phone</dt><dd class="font-medium" x-text="form.phone || '—'"></dd></div>
                                    <div class="grid grid-cols-[8rem_1fr] gap-3 px-4 py-3"><dt class="text-muted">Store</dt><dd class="font-medium" x-text="form.store_name || '—'"></dd></div>
                                    <div class="grid grid-cols-[8rem_1fr] gap-3 px-4 py-3"><dt class="text-muted">Address</dt><dd class="font-medium"><span x-text="form.address_line || '—'"></span>, <span x-text="form.city || '—'"></span></dd></div>
                                </dl>
                                <div class="mt-6 flex justify-between gap-2">
                                    <button type="button" @click="go(4)" class="inline-flex min-h-12 items-center rounded-[10px] border border-line px-5 text-sm font-semibold transition hover:border-brand">Back</button>
                                    <button type="submit" :disabled="submitting" class="inline-flex min-h-12 items-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover disabled:cursor-wait disabled:bg-slate-300"><span x-show="!submitting">Submit application</span><span x-cloak x-show="submitting">Submitting…</span></button>
                                </div>
                            </section>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
