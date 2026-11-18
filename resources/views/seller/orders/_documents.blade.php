@php
    $hasPrescription = $sellerOrder->order->prescription !== null;
    $hasDiscount = bccomp((string) $sellerOrder->discount_amount, '0', 2) > 0;
@endphp
<x-input-error :messages="$errors->get('discount')" class="mb-4" />
@if ($hasPrescription || $hasDiscount)
    <section class="sc-document-review {{ $class ?? '' }}" aria-label="Order documents" x-data="{ document: @js($hasPrescription ? 'prescription' : 'discount') }">
        <div class="sc-document-statuses">
            @if ($hasPrescription)
                <span class="sc-badge" data-tone="{{ $sellerOrder->order->prescription->status === 'approved' ? 'success' : 'warning' }}">Prescription · {{ ['pending' => 'Pharmacist approval', 'approved' => 'Approved', 'rejected' => 'Rejected', 'needs_photo' => 'Clearer photo requested'][$sellerOrder->order->prescription->status] ?? 'Review' }}</span>
            @endif
            @if ($hasDiscount)
                <span class="sc-badge" data-tone="{{ $sellerOrder->discount_reviewed_at ? 'success' : 'info' }}">SC/PWD · {{ $sellerOrder->discount_reviewed_at ? 'ID reviewed' : ($sellerOrder->order->discount_document_path ? 'ID to review' : 'Check ID at handover') }}</span>
            @endif
        </div>
        @if ($hasPrescription && $hasDiscount)
            <div class="sc-document-tabs" aria-label="Submitted documents">
                <button type="button" class="seller-outline-button" :aria-pressed="document === 'prescription'" @click="document = 'prescription'">Prescription <span>{{ $sellerOrder->order->prescription->images->count() }} {{ Str::plural('page', $sellerOrder->order->prescription->images->count()) }}</span></button>
                <button type="button" class="seller-outline-button" :aria-pressed="document === 'discount'" @click="document = 'discount'">{{ \App\Support\SeniorPwdDiscount::CARD_TYPES[$sellerOrder->order->discount_card_type] ?? 'SC/PWD' }} ID</button>
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
