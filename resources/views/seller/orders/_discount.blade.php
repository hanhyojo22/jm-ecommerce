@php
    $discountOrder = $sellerOrder->order;
    $cardLabel = \App\Support\SeniorPwdDiscount::CARD_TYPES[$discountOrder->discount_card_type] ?? 'SC/PWD';
@endphp
<div class="sc-discount-review">
    <h3 class="sr-only">{{ $cardLabel }} discount ID</h3>
    @if ($discountOrder->discount_document_path)
        <div class="sc-document-workspace"><div class="sc-document-viewer">
            @if (str_starts_with($discountOrder->discount_document_mime ?? '', 'image/'))
                <img src="{{ route('seller.orders.discount-document.show', $sellerOrder) }}" alt="{{ $cardLabel }} ID submitted by {{ $discountOrder->discount_card_name }}" loading="lazy">
            @else
                <p class="sc-meta">PDF document</p>
            @endif
        </div>
        <div class="sc-document-meta"><strong>{{ $discountOrder->discount_document_name }}</strong>    <dl class="sc-discount-identity">
        <div><dt class="sr-only">Cardholder</dt><dd>{{ $discountOrder->discount_card_name }}</dd></div>
        <div><dt class="sr-only">Card number</dt><dd>{{ \App\Support\SeniorPwdDiscount::mask($discountOrder->discount_card_number) }}</dd></div>
    </dl>
<a href="{{ route('seller.orders.discount-document.show', $sellerOrder) }}" target="_blank" rel="noopener" class="sc-document-open">Open ID <span class="sr-only">in a new tab</span></a>
        @if ($sellerOrder->discount_reviewed_at)
            <p class="sc-document-checked">ID reviewed · {{ $sellerOrder->discount_reviewed_at->format('M j, Y · g:i A') }}</p>
        @elseif (! in_array($sellerOrder->status, ['cancelled', 'delivered'], true))
            <form method="POST" action="{{ route('seller.orders.discount-document.review', $sellerOrder) }}" class="sc-document-check-form">
                @csrf

                <button type="submit" class="seller-outline-button">Mark ID reviewed</button>
            </form>
        @endif
        </div></div>
    @else
        <div class="sc-document-viewer"><p>No ID photo was submitted.</p></div>
    @endif
    <p class="sc-meta mt-4">Original ID required at handover.</p>
</div>
