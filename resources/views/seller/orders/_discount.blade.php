@php
    $discountOrder = $sellerOrder->order;
    $cardLabel = \App\Support\SeniorPwdDiscount::CARD_TYPES[$discountOrder->discount_card_type] ?? 'SC/PWD';
@endphp
<div class="sc-discount-review">
    <div class="sc-document-heading"><h3>{{ $cardLabel }} discount ID</h3><span class="sc-meta">Private document</span></div>
    <dl class="sc-discount-identity">
        <div><dt>Cardholder</dt><dd>{{ $discountOrder->discount_card_name }}</dd></div>
        <div><dt>Card number</dt><dd>{{ \App\Support\SeniorPwdDiscount::mask($discountOrder->discount_card_number) }}</dd></div>
    </dl>
    @if ($discountOrder->discount_document_path)
        <div class="sc-document-viewer">
            @if (str_starts_with($discountOrder->discount_document_mime ?? '', 'image/'))
                <img src="{{ route('seller.orders.discount-document.show', $sellerOrder) }}" alt="{{ $cardLabel }} ID submitted by {{ $discountOrder->discount_card_name }}" loading="lazy">
            @else
                <p class="sc-meta">A PDF document was submitted. Open it to review the original.</p>
            @endif
        </div>
        <div class="sc-document-file"><span>{{ $discountOrder->discount_document_name }}</span><a href="{{ route('seller.orders.discount-document.show', $sellerOrder) }}" target="_blank" rel="noopener" class="seller-outline-button">Open ID document <span class="sr-only">in a new tab</span></a></div>
        @if ($sellerOrder->discount_reviewed_at)
            <p class="sc-document-checked">ID reviewed · {{ $sellerOrder->discount_reviewed_at->format('M j, Y · g:i A') }}</p>
        @elseif (! in_array($sellerOrder->status, ['cancelled', 'delivered'], true))
            <form method="POST" action="{{ route('seller.orders.discount-document.review', $sellerOrder) }}" class="sc-document-check-form">
                @csrf
                <p>Compare the cardholder with the prescription patient and order details. Reviewing the ID does not approve the prescription.</p>
                <button type="submit" class="seller-outline-button">Mark ID reviewed</button>
            </form>
        @endif
    @else
        <div class="sc-document-viewer"><p>No ID photo was submitted. Check the customer’s original {{ $cardLabel }} ID at handover.</p></div>
    @endif
    <p class="sc-meta mt-4">Check the original ID at handover, even if its photo has been reviewed.</p>
</div>
