{{-- How to pay one store directly: its QR, the exact amount, and the reference form. Shared by the
     payment checklist and the guided dialog shown right after checkout. --}}
@php
    $method = $order->payment->method;
    $label = \App\Models\Payment::METHOD_LABELS[$method];
    $store = $sellerOrder->store;
    $qrUrl = $store->{$method.'_qr_path'} ? Storage::disk('public')->url($store->{$method.'_qr_path'}) : null;
    $number = $store->{$method.'_number'};
    $inputId = 'payment_reference_'.$sellerOrder->id.($guided ? '_guided' : '');
@endphp
<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-5">
    <div class="mx-auto shrink-0 rounded-[20px] p-2.5 sm:mx-0 {{ ['gcash' => 'bg-[#0070F0]', 'maya' => 'bg-[#101820]', 'bank' => 'bg-slate-200'][$method] }}">
        <div class="grid size-48 place-items-center overflow-hidden rounded-[14px] bg-white p-2 sm:size-44">
            @if ($qrUrl)
                <img src="{{ $qrUrl }}" alt="{{ $store->name }} {{ $label }} QR code" class="size-full object-contain">
            @else
                <p class="px-3 text-center text-xs leading-5 text-muted">{{ $method === 'bank' ? 'Use the account details to transfer.' : 'QR not available. Use the number instead.' }}</p>
            @endif
        </div>
    </div>
    <div class="min-w-0 flex-1 space-y-3">
        <div>
            <p class="text-xs font-medium text-muted">Send exactly</p>
            <p class="text-3xl font-semibold tracking-[-0.03em] tabular-nums text-ink">₱{{ number_format($sellerOrder->total, 2) }}</p>
            <p class="mt-0.5 text-sm text-muted">to {{ $store->name }} by {{ $label }}</p>
        </div>
        @if ($method === 'bank')
            <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1 rounded-xl border border-line bg-white p-3 text-sm">
                <dt class="text-muted">Bank</dt><dd class="font-semibold text-ink">{{ $store->bank_name ?? '—' }}</dd>
                <dt class="text-muted">Name</dt><dd class="font-semibold text-ink">{{ $store->bank_account_name ?? '—' }}</dd>
                <dt class="text-muted">Account</dt><dd class="font-semibold tabular-nums text-ink">{{ $number ?? '—' }}</dd>
            </dl>
        @endif
        <div class="flex flex-wrap gap-2" x-data="{ copied: false }">
            @if ($number)
                <button type="button" @click="navigator.clipboard?.writeText(@js($number)); copied = true; setTimeout(() => copied = false, 1800)" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-line bg-white px-3.5 text-sm font-semibold text-ink transition hover:bg-canvas">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h8"/></svg>
                    <span x-text="copied ? 'Copied' : @js('Copy '.($method === 'bank' ? 'account no.' : 'number'))">Copy {{ $method === 'bank' ? 'account no.' : 'number' }}</span>
                </button>
            @endif
            @if ($qrUrl)
                <a href="{{ $qrUrl }}" download="{{ Str::slug($store->name) }}-{{ $method }}-qr" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-line bg-white px-3.5 text-sm font-semibold text-ink transition hover:bg-canvas">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M5 21h14"/></svg>Save QR
                </a>
            @endif
        </div>
        <p class="text-xs leading-5 text-muted"><span class="lg:hidden">On this phone, save the QR and upload it in {{ $method === 'bank' ? 'your bank app' : $label }}, or copy the {{ $method === 'bank' ? 'account' : 'number' }}.</span><span class="hidden lg:inline">Open {{ $method === 'bank' ? 'your bank app' : $label }} on your phone and scan this QR.</span></p>
    </div>
</div>
<form method="POST" action="{{ route('orders.payment-reference', $sellerOrder) }}" class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-end" x-data="{ sending: false }" @submit="sending = true">
    @csrf
    @method('PATCH')
    @if ($guided)<input type="hidden" name="guided" value="1">@endif
    <input type="hidden" name="seller_order_id" value="{{ $sellerOrder->id }}">
    <div class="min-w-0 flex-1">
        <label for="{{ $inputId }}" class="text-sm font-semibold text-ink">{{ $label }} reference number</label>
        <input id="{{ $inputId }}" name="payment_reference" value="{{ old('seller_order_id') === $sellerOrder->id ? old('payment_reference') : $sellerOrder->payment_reference }}" required maxlength="40" inputmode="text" autocomplete="off" placeholder="From your {{ $method === 'bank' ? 'transfer' : $label }} receipt" class="field-control mt-1.5 text-base tabular-nums lg:text-sm">
    </div>
    <button type="submit" :disabled="sending" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-brand px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-hover disabled:cursor-wait disabled:opacity-70">
        <span x-show="!sending">{{ $submitLabel ?? ($sellerOrder->payment_reference ? 'Update reference' : 'I\'ve paid · send reference') }}</span><span x-cloak x-show="sending">Sending…</span>
    </button>
</form>
