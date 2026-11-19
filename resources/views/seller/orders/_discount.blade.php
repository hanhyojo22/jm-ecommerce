@php
    $discountOrder = $sellerOrder->order;
    $cardLabel = \App\Support\SeniorPwdDiscount::CARD_TYPES[$discountOrder->discount_card_type] ?? 'SC/PWD';
    // Other customer accounts that used this same ID number with this company.
    $otherAccounts = $discountOrder->discount_card_fingerprint === null ? 0 : \App\Models\Order::query()
        ->where('discount_card_fingerprint', $discountOrder->discount_card_fingerprint)
        ->where('user_id', '!=', $discountOrder->user_id)
        ->whereHas('sellerOrders', fn ($query) => $query->where('tenant_id', $sellerOrder->tenant_id))
        ->distinct()->count('user_id');
@endphp
<div class="sc-discount-review">
    <h3 class="sr-only">{{ $cardLabel }} discount ID</h3>
    @if ($otherAccounts > 0)
        <p role="note" class="mb-3 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">This ID number was also used by {{ $otherAccounts }} other customer {{ $otherAccounts === 1 ? 'account' : 'accounts' }}. It may be family ordering for the same person; check that the card and name match at handover.</p>
    @endif
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
            <p class="sc-document-checked">{{ $sellerOrder->discount_reviewed_at->lt($discountOrder->created_at) ? 'Same ID photo reviewed on an earlier order' : 'ID reviewed' }} · {{ $sellerOrder->discount_reviewed_at->format('M j, Y · g:i A') }}</p>
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
    @if ($sellerOrder->discount_card_checked_at)
        <p class="sc-document-checked mt-4">Original ID checked at handover · {{ $sellerOrder->discount_card_checked_at->format('M j, Y · g:i A') }}</p>
    @else
        <p class="sc-meta mt-4">Original ID required at handover.</p>
        @if ($sellerOrder->status !== 'cancelled')
            <form method="POST" action="{{ route('seller.orders.discount-card.check', $sellerOrder) }}" class="sc-document-check-form mt-2">
                @csrf
                <button type="submit" class="seller-outline-button">Original ID checked</button>
            </form>
        @endif
    @endif
</div>
