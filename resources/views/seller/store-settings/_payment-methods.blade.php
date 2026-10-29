@php
    $isOwner = auth()->user()->sellerTenant(['owner'])?->id === $store->tenant_id;
    [$statusTone, $statusText, $summary] = match ($store->paymongo_account_status) {
        'activated' => ['success', 'Online payments on', 'Buyers can pay your store with GCash, Maya, or card. Your share of each payment goes to your PayMongo account automatically.'],
        'under_review' => ['warning', 'Under review', 'PayMongo is reviewing your details. This usually takes a few business days.'],
        'declined' => ['danger', 'Declined', 'PayMongo couldn’t verify your account. Check your email from PayMongo, or start verification again.'],
        'pending' => ['warning', 'Verification needed', 'Finish PayMongo’s ID check to start taking online payments.'],
        default => ['neutral', 'Not set up', 'Connect PayMongo so buyers can pay online. Until then, your store takes cash on delivery only.'],
    };
@endphp

<section id="payment-methods" class="seller-form-card mt-5 min-w-0 scroll-mt-24 overflow-hidden" aria-labelledby="payment-methods-heading">
    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-line bg-gradient-to-br from-white to-slate-50/80 p-5 sm:p-6">
        <div class="flex min-w-0 items-start gap-3">
            <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-ink text-white shadow-sm" aria-hidden="true">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="6" width="18" height="13" rx="2.5"/><path d="M3 10h18M16 15h2"/><path d="M6 6V5a2 2 0 0 1 2-2h8"/></svg>
            </span>
            <div class="min-w-0">
                <h2 id="payment-methods-heading" class="text-lg font-semibold tracking-[-0.01em] text-ink">Get paid online</h2>
                <p class="mt-0.5 max-w-2xl text-sm leading-6 text-muted">{{ $summary }}</p>
            </div>
        </div>
        <span class="sc-badge" data-tone="{{ $statusTone }}">{{ $statusText }}</span>
    </div>

    <div class="flex flex-wrap items-center gap-3 p-5 sm:px-6">
        <x-input-error :messages="$errors->get('online_payments')" class="w-full" />
        @if (! $isOwner)
            <p class="text-sm text-muted">Only the store owner can connect PayMongo.</p>
        @elseif ($store->paymongo_account_status !== 'activated')
            <form method="POST" action="{{ route('seller.online-payments.store') }}">@csrf
                <button type="submit" class="seller-primary-button min-h-11">{{ $store->paymongo_account_id ? 'Continue verification' : 'Connect PayMongo' }}</button>
            </form>
            @if ($store->paymongo_account_id)
                <form method="POST" action="{{ route('seller.online-payments.refresh') }}">@csrf
                    <button type="submit" class="seller-outline-button min-h-11">Check status</button>
                </form>
            @endif
        @endif
        <p class="w-full text-xs leading-5 text-muted">JMMart keeps its {{ rtrim(rtrim(bcmul((string) config('marketplace.platform_fee.rate'), '100', 2), '0'), '.') }}% platform fee from each online payment, so online orders never appear on your monthly fee statement. Buyers pay PayMongo’s processing fee. Never share your MPIN or OTP; JMMart will never ask for them.</p>
    </div>
</section>
