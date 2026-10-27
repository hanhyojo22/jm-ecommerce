@php
    $methods = [
        'gcash' => ['label' => 'GCash', 'mark' => 'G', 'tile' => 'bg-[#0070F0] text-white', 'tagline' => 'Buyers scan your GCash QR or send to your number.', 'qrHelp' => 'In GCash, open your Receive / QR screen and save the QR image.'],
        'maya' => ['label' => 'Maya', 'mark' => 'M', 'tile' => 'bg-[#101820] text-[#3DDC84]', 'tagline' => 'Buyers scan your Maya QR or send to your number.', 'qrHelp' => 'In Maya, open your Receive money / QR screen and save the QR image.'],
        'bank' => ['label' => 'Bank transfer', 'mark' => null, 'tile' => 'bg-slate-100 text-slate-700', 'tagline' => 'Buyers transfer by InstaPay or PESONet to your account.', 'qrHelp' => 'Optional. Most bank apps can create a QR Ph code that buyers scan with any bank, GCash, or Maya.'],
    ];
    $liveCount = collect(array_keys($methods))->filter(fn ($method) => $store->acceptsWallet($method))->count();
    $openMethod = $errors->any() && in_array(old('method'), array_keys($methods), true) ? old('method') : null;
    $formatNumber = fn (?string $number): string => preg_match('/^(?:\+63|0)(9\d{2})(\d{3})(\d{4})$/', (string) $number, $m) ? "0{$m[1]} {$m[2]} {$m[3]}" : (string) $number;
    $maskNumber = fn (?string $number): string => strlen((string) $number) > 4 ? str_repeat('•', 4).' '.substr($number, -4) : (string) $number;
@endphp

<section id="payment-methods" class="seller-form-card mt-5 min-w-0 scroll-mt-24 overflow-hidden" aria-labelledby="payment-methods-heading" x-data="{ open: @js($openMethod) }">
    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-line bg-gradient-to-br from-white to-slate-50/80 p-5 sm:p-6">
        <div class="flex min-w-0 items-start gap-3">
            <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-ink text-white shadow-sm" aria-hidden="true">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="6" width="18" height="13" rx="2.5"/><path d="M3 10h18M16 15h2"/><path d="M6 6V5a2 2 0 0 1 2-2h8"/></svg>
            </span>
            <div class="min-w-0">
                <h2 id="payment-methods-heading" class="text-lg font-semibold tracking-[-0.01em] text-ink">Get paid</h2>
                <p class="mt-0.5 max-w-2xl text-sm leading-6 text-muted">Let buyers pay you directly by e-wallet or bank. The money goes straight to your account; JMMart never holds it. You confirm each payment before you ship.</p>
            </div>
        </div>
        <span class="sc-badge" data-tone="{{ $liveCount > 0 ? 'success' : 'neutral' }}">{{ $liveCount }} of {{ count($methods) }} live at checkout</span>
    </div>

    <ul class="divide-y divide-line" role="list">
        @foreach ($methods as $method => $meta)
            @php
                $isLive = $store->acceptsWallet($method);
                $number = $store->{$method.'_number'};
                $qrPath = $store->{$method.'_qr_path'};
                $qrUrl = $qrPath ? Storage::disk('public')->url($qrPath) : null;
                $isStarted = filled($number) || filled($qrPath);
                [$statusTone, $statusText] = match (true) {
                    $isLive => ['success', 'Live at checkout'],
                    $isStarted => ['warning', $method === 'bank' ? 'Incomplete' : (filled($number) ? 'Needs QR code' : 'Needs number')],
                    default => ['neutral', 'Not set up'],
                };
            @endphp
            <li class="min-w-0" x-data="{
                    qr: null,
                    qrName: '',
                    dragging: false,
                    number: @js(old($method.'_number', $method === 'bank' ? $number : $formatNumber($number))),
                    bankName: @js(old('bank_name', $store->bank_name)),
                    accountName: @js(old('bank_account_name', $store->bank_account_name)),
                    get digits() { return this.number.replace(/[\s-]/g, '') },
                    get numberValid() { return {{ $method === 'bank' ? '/^\\d{6,30}$/' : '/^(?:\\+63|0)9\\d{9}$/' }}.test(this.digits) },
                    get pretty() { const m = this.digits.match(/^(?:\+63|0)(9\d{2})(\d{3})(\d{4})$/); return m ? `0${m[1]} ${m[2]} ${m[3]}` : this.digits },
                    pick(files) { const file = files?.[0]; if (!file) return; if (this.qr) URL.revokeObjectURL(this.qr); this.qr = URL.createObjectURL(file); this.qrName = file.name; },
                    drop(event) { this.dragging = false; const files = event.dataTransfer.files; if (!files.length) return; this.$refs.qrInput.files = files; this.pick(files); },
                }">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-3 p-5 sm:px-6">
                    <span class="grid size-12 shrink-0 place-items-center rounded-xl text-lg font-bold shadow-sm ring-1 ring-black/5 {{ $meta['tile'] }}" aria-hidden="true">
                        @if ($meta['mark'])
                            {{ $meta['mark'] }}
                        @else
                            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 10 9-6 9 6M5 10v8M9.5 10v8M14.5 10v8M19 10v8M3 21h18"/></svg>
                        @endif
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold text-ink">{{ $meta['label'] }}</h3>
                            <span class="sc-badge" data-tone="{{ $statusTone }}">{{ $statusText }}</span>
                        </div>
                        <p class="mt-0.5 text-sm text-muted sm:truncate">
                            @if ($isStarted)
                                {{ $method === 'bank' ? collect([$store->bank_name, $maskNumber($number)])->filter()->join(' · ') : ($number ? $maskNumber($number) : 'No number yet') }}{{ $qrPath ? ' · QR added' : ($method === 'bank' ? '' : ' · No QR yet') }}
                            @else
                                {{ $meta['tagline'] }}
                            @endif
                        </p>
                    </div>
                    @if ($qrUrl)
                        <img src="{{ $qrUrl }}" alt="" class="hidden size-12 shrink-0 rounded-lg border border-line bg-white object-contain p-1 sm:block">
                    @endif
                    <div class="flex shrink-0 items-center gap-2 max-sm:w-full">
                        <button type="button" @click="open = open === '{{ $method }}' ? null : '{{ $method }}'" :aria-expanded="open === '{{ $method }}'" aria-controls="payment-editor-{{ $method }}"
                            class="{{ $isStarted ? 'seller-outline-button' : 'seller-primary-button' }} min-h-11 max-sm:flex-1">
                            <span x-text="open === '{{ $method }}' ? 'Close' : '{{ $isStarted ? 'Edit' : 'Set up' }}'">{{ $isStarted ? 'Edit' : 'Set up' }}</span>
                        </button>
                    </div>
                </div>

                <div id="payment-editor-{{ $method }}" x-cloak x-show="open === '{{ $method }}'" x-transition.opacity.duration.150ms class="border-t border-line bg-slate-50/70">
                    <form method="POST" action="{{ route('seller.store-settings.wallets') }}" enctype="multipart/form-data" class="grid min-w-0 gap-6 p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_17rem]"
                        x-data="{ saving: false }" @submit="saving = true">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="method" value="{{ $method }}">

                        <div class="min-w-0 space-y-6">
                            <fieldset class="min-w-0">
                                <legend class="flex items-center gap-2 text-sm font-semibold text-ink"><span class="grid size-6 place-items-center rounded-full bg-ink text-xs text-white" aria-hidden="true">1</span>{{ $method === 'bank' ? 'Account details' : 'Your '.$meta['label'].' number' }}</legend>
                                <div class="mt-3 grid min-w-0 gap-4 {{ $method === 'bank' ? 'sm:grid-cols-2' : '' }}">
                                    @if ($method === 'bank')
                                        <div class="min-w-0">
                                            <label for="bank_name" class="block text-sm font-medium text-ink">Bank</label>
                                            <input id="bank_name" name="bank_name" x-model="bankName" list="ph-banks" maxlength="100" required autocomplete="off" placeholder="e.g. BDO Unibank" class="field-control mt-1.5 text-base lg:text-sm @error('bank_name') border-rose-500 @enderror" @error('bank_name') aria-invalid="true" aria-describedby="bank_name-error" @enderror>
                                            <datalist id="ph-banks">@foreach (['BDO Unibank', 'Bank of the Philippine Islands (BPI)', 'Metrobank', 'Land Bank of the Philippines', 'Security Bank', 'UnionBank', 'China Bank', 'RCBC', 'PNB', 'EastWest Bank', 'Development Bank of the Philippines', 'GoTyme Bank', 'Maya Bank', 'SeaBank', 'CIMB Bank'] as $bank)<option value="{{ $bank }}"></option>@endforeach</datalist>
                                            <x-input-error id="bank_name-error" :messages="$errors->get('bank_name')" class="mt-1.5" />
                                        </div>
                                        <div class="min-w-0">
                                            <label for="bank_account_name" class="block text-sm font-medium text-ink">Account name</label>
                                            <input id="bank_account_name" name="bank_account_name" x-model="accountName" maxlength="150" required autocomplete="off" placeholder="As printed on the account" class="field-control mt-1.5 text-base lg:text-sm @error('bank_account_name') border-rose-500 @enderror" @error('bank_account_name') aria-invalid="true" aria-describedby="bank_account_name-error" @enderror>
                                            <x-input-error id="bank_account_name-error" :messages="$errors->get('bank_account_name')" class="mt-1.5" />
                                        </div>
                                    @endif
                                    <div class="min-w-0 {{ $method === 'bank' ? 'sm:col-span-2' : 'max-w-sm' }}">
                                        <label for="{{ $method }}_number" class="block text-sm font-medium text-ink">{{ $method === 'bank' ? 'Account number' : 'Mobile number' }}</label>
                                        <div class="relative mt-1.5">
                                            <input id="{{ $method }}_number" name="{{ $method }}_number" x-model="number" required inputmode="{{ $method === 'bank' ? 'numeric' : 'tel' }}" autocomplete="off" maxlength="40" placeholder="{{ $method === 'bank' ? 'Digits only' : '0917 123 4567' }}"
                                                aria-describedby="{{ $method }}_number-help @error($method.'_number') {{ $method }}_number-error @enderror" @error($method.'_number') aria-invalid="true" @enderror
                                                class="field-control pr-10 text-base tabular-nums lg:text-sm @error($method.'_number') border-rose-500 @enderror">
                                            <svg x-cloak x-show="numberValid" class="pointer-events-none absolute right-3 top-1/2 size-5 -translate-y-1/2 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
                                        </div>
                                        <p id="{{ $method }}_number-help" class="mt-1.5 text-xs text-muted">{{ $method === 'bank' ? 'Spaces and dashes are removed for you.' : 'The number registered to your '.$meta['label'].' account. Spaces are fine.' }}</p>
                                        <x-input-error id="{{ $method }}_number-error" :messages="$errors->get($method.'_number')" class="mt-1.5" />
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="min-w-0">
                                <legend class="flex items-center gap-2 text-sm font-semibold text-ink"><span class="grid size-6 place-items-center rounded-full bg-ink text-xs text-white" aria-hidden="true">2</span>{{ $method === 'bank' ? 'QR Ph code' : 'Your '.$meta['label'].' QR code' }} @if ($method === 'bank')<span class="font-normal text-muted">(optional)</span>@endif</legend>
                                <p class="mt-1 text-xs text-muted">{{ $meta['qrHelp'] }}</p>
                                <input id="{{ $method }}_qr" x-ref="qrInput" name="{{ $method }}_qr" type="file" accept="image/jpeg,image/png,image/webp" class="peer sr-only" @change="pick($event.target.files)"
                                    aria-describedby="{{ $method }}_qr-hint @error($method.'_qr') {{ $method }}_qr-error @enderror">
                                <label for="{{ $method }}_qr" @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="drop($event)"
                                    :class="dragging ? 'border-brand bg-blue-50' : 'border-slate-300 bg-white hover:border-brand/60 hover:bg-blue-50/40'"
                                    class="mt-3 flex min-h-32 cursor-pointer items-center gap-4 rounded-xl border-2 border-dashed p-4 transition peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand @error($method.'_qr') !border-rose-400 @enderror">
                                    <span class="grid size-24 shrink-0 place-items-center overflow-hidden rounded-lg border border-line bg-white">
                                        <img x-cloak x-show="qr" :src="qr" alt="Selected QR code preview" class="size-full object-contain p-1">
                                        @if ($qrUrl)
                                            <img x-show="!qr" src="{{ $qrUrl }}" alt="Current {{ $meta['label'] }} QR code" class="size-full object-contain p-1">
                                        @else
                                            <svg x-show="!qr" class="size-8 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 14h2M14 18h2v2M18 18h2v2"/></svg>
                                        @endif
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-ink" x-text="qr ? 'New QR code selected' : '{{ $qrUrl ? 'Replace QR code' : 'Upload your QR code' }}'">{{ $qrUrl ? 'Replace QR code' : 'Upload your QR code' }}</span>
                                        <span id="{{ $method }}_qr-hint" class="mt-0.5 block text-xs text-muted" x-text="qrName || 'Drag an image here or click to choose · JPEG, PNG, or WebP up to 2 MB'">Drag an image here or click to choose · JPEG, PNG, or WebP up to 2 MB</span>
                                    </span>
                                </label>
                                <x-input-error id="{{ $method }}_qr-error" :messages="$errors->get($method.'_qr')" class="mt-1.5" />
                            </fieldset>

                            <div class="flex flex-wrap items-center gap-2 border-t border-line pt-5">
                                <button type="submit" :disabled="saving" :aria-busy="saving" class="seller-primary-button min-h-11 disabled:cursor-wait disabled:opacity-70">
                                    <span x-show="!saving">Save {{ $meta['label'] }}</span><span x-cloak x-show="saving">Saving…</span>
                                </button>
                                <button type="button" @click="open = null" class="seller-outline-button min-h-11">Cancel</button>
                                @if ($isStarted)
                                    <button type="submit" form="payment-disable-{{ $method }}" class="ml-auto inline-flex min-h-11 items-center px-2 text-sm font-semibold text-rose-700 hover:text-rose-800">Turn off {{ $meta['label'] }}</button>
                                @endif
                            </div>
                        </div>

                        <aside class="min-w-0" aria-label="Preview of what buyers see">
                            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-muted">What buyers see</p>
                            <div class="mt-2 rounded-2xl border border-line bg-white p-4 shadow-[0_8px_24px_-12px_rgba(24,44,72,0.25)]">
                                <div class="flex items-center justify-between gap-2 text-sm">
                                    <span class="truncate font-semibold text-ink">{{ $store->name }}</span>
                                    <span class="font-semibold tabular-nums text-ink">₱1,250.00</span>
                                </div>
                                <p class="mt-1 text-xs text-muted">Pay by {{ $meta['label'] }}</p>
                                <div class="mx-auto mt-3 grid size-36 place-items-center overflow-hidden rounded-xl border border-line bg-white">
                                    <img x-cloak x-show="qr" :src="qr" alt="" class="size-full object-contain p-1.5">
                                    @if ($qrUrl)
                                        <img x-show="!qr" src="{{ $qrUrl }}" alt="" class="size-full object-contain p-1.5">
                                    @else
                                        <span x-show="!qr" class="px-3 text-center text-xs text-muted">{{ $method === 'bank' ? 'No QR · buyers use the account details' : 'Your QR code appears here' }}</span>
                                    @endif
                                </div>
                                @if ($method === 'bank')
                                    <dl class="mt-3 grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1 text-xs">
                                        <dt class="text-muted">Bank</dt><dd class="truncate font-semibold text-ink" x-text="bankName || '—'"></dd>
                                        <dt class="text-muted">Name</dt><dd class="truncate font-semibold text-ink" x-text="accountName || '—'"></dd>
                                        <dt class="text-muted">Account</dt><dd class="truncate font-semibold tabular-nums text-ink" x-text="digits || '—'"></dd>
                                    </dl>
                                @else
                                    <p class="mt-3 text-center text-xs text-muted">{{ $meta['label'] }} number <span class="block text-sm font-semibold tabular-nums text-ink" x-text="pretty || '—'"></span></p>
                                @endif
                                <div class="mt-3 rounded-lg border border-dashed border-line px-3 py-2 text-xs text-muted">Buyer enters their reference number here</div>
                            </div>
                            <p class="mt-3 text-xs leading-5 text-muted">Check that the QR opens <strong class="font-semibold text-ink">your</strong> account before saving. Buyers are told to pay only what's shown here.</p>
                        </aside>
                    </form>
                    @if ($isStarted)
                        <form id="payment-disable-{{ $method }}" method="POST" action="{{ route('seller.store-settings.wallets') }}" class="hidden" onsubmit="return confirm('Turn off {{ $meta['label'] }}? Buyers will no longer be able to choose it at checkout, and its saved details are removed.')">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="method" value="{{ $method }}">
                            <input type="hidden" name="disable" value="1">
                        </form>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>

    <p class="flex items-start gap-2 border-t border-line bg-slate-50/70 px-5 py-4 text-xs leading-5 text-muted sm:px-6">
        <svg class="mt-0.5 size-4 shrink-0 text-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.4 7.5 9.5 4.3-1.1 7.5-4.9 7.5-9.5V6L12 3Z"/><path d="m9 12 2 2 4-4"/></svg>
        <span>A method appears at checkout only when every store in the buyer's cart offers it. Every change here is recorded. Never share your MPIN or OTP. JMMart will never ask for them.</span>
    </p>
</section>
