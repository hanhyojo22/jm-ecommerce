@php
    $hasPrescription = $sellerOrder->order->prescription !== null;
    $hasDiscount = bccomp((string) $sellerOrder->discount_amount, '0', 2) > 0;
@endphp
<x-input-error :messages="$errors->get('discount')" class="mb-4" />
@if ($hasPrescription || $hasDiscount)
    <section class="sc-document-review {{ $class ?? '' }}" aria-label="Order documents" x-data="{ document: @js($hasPrescription ? 'prescription' : 'discount') }">
        @if ($hasPrescription && $hasDiscount)
            <div class="sc-document-tabs" aria-label="Submitted documents">
                <button type="button" class="sc-document-tab" :aria-pressed="document === 'prescription'" @click="document = 'prescription'">Prescription</button>
                <button type="button" class="sc-document-tab" :aria-pressed="document === 'discount'" @click="document = 'discount'">{{ \App\Support\SeniorPwdDiscount::CARD_TYPES[$sellerOrder->order->discount_card_type] ?? 'SC/PWD' }} ID</button>
            </div>
        @endif
        @if ($hasPrescription)
            <div x-show="document === 'prescription'">
                @include('seller.orders._prescription', ['sellerOrder' => $sellerOrder, 'class' => '', 'approvalInFooter' => $approvalInFooter ?? false])
            </div>
        @endif
        @if ($hasDiscount)
            <div x-show="document === 'discount'" @if ($hasPrescription) x-cloak @endif>
                @include('seller.orders._discount', ['sellerOrder' => $sellerOrder])
            </div>
        @endif
    </section>
@endif
