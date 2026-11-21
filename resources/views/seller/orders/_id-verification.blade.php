@php
    $discountOrder = $sellerOrder->order;
    $cardLabel = \App\Support\SeniorPwdDiscount::CARD_TYPES[$discountOrder->discount_card_type] ?? 'SC/PWD';
    $documentUrl = $discountOrder->discount_document_path ? route('seller.orders.discount-document.show', $sellerOrder) : null;
    $documentIsImage = str_starts_with($discountOrder->discount_document_mime ?? '', 'image/');
    $reviewed = $sellerOrder->discount_reviewed_at !== null;
    $discountActive = ! in_array($sellerOrder->status, ['delivered', 'cancelled'], true);
    // Other customer accounts that used this same ID number with this company.
    $otherAccounts = $discountOrder->discount_card_fingerprint === null ? 0 : \App\Models\Order::query()
        ->where('discount_card_fingerprint', $discountOrder->discount_card_fingerprint)
        ->where('user_id', '!=', $discountOrder->user_id)
        ->whereHas('sellerOrders', fn ($query) => $query->where('tenant_id', $sellerOrder->tenant_id))
        ->distinct()->count('user_id');
@endphp
<section class="sc-card sc-id-card" aria-labelledby="id-verification-heading" x-data="{}">
    <div class="sc-card-head sc-id-head">
        <div class="min-w-0">
            <h2 id="id-verification-heading" class="sc-card-title">SC / PWD ID Verification</h2>
            <p class="sc-card-note">{{ $cardLabel }} discount ID</p>
        </div>
        <span class="sc-badge" data-tone="{{ $reviewed ? 'success' : 'warning' }}">{{ $reviewed ? 'Reviewed' : 'Needs review' }}</span>
    </div>

    <div class="sc-id-card-body">
        @if ($otherAccounts > 0)
            <p role="note" class="sc-id-warning">This ID number was also used by {{ $otherAccounts }} other customer {{ $otherAccounts === 1 ? 'account' : 'accounts' }}. It may be family ordering for the same person; check that the card and name match at handover.</p>
        @endif

        <div class="sc-id-flex">
            @if ($documentUrl && $documentIsImage)
                <button type="button" class="sc-id-thumb" @click="$refs.idPreview.showModal()" aria-label="Open {{ $cardLabel }} ID in a full-screen preview">
                    <img src="{{ $documentUrl }}" alt="{{ $cardLabel }} ID submitted by {{ $discountOrder->discount_card_name }}" loading="lazy">
                </button>
                <dialog x-ref="idPreview" class="sc-id-lightbox" aria-label="{{ $cardLabel }} ID preview" @click="if ($event.target === $el) { $el.close(); }">
                    <div class="sc-id-lightbox-inner">
                        <button type="button" class="sc-id-lightbox-close" @click="$refs.idPreview.close()" aria-label="Close ID preview">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
                        </button>
                        <img src="{{ $documentUrl }}" alt="{{ $cardLabel }} ID submitted by {{ $discountOrder->discount_card_name }}">
                    </div>
                </dialog>
            @elseif ($documentUrl)
                <a href="{{ $documentUrl }}" target="_blank" rel="noopener" class="sc-id-thumb is-file" aria-label="Open {{ $cardLabel }} ID document in a new tab">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg>
                    <span>PDF</span>
                </a>
            @else
                <span class="sc-id-thumb is-empty" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 7h16v10H4z"/><circle cx="9" cy="12" r="2"/><path d="M14 11h3M14 14h3"/></svg>
                </span>
            @endif

            <div class="sc-id-meta">
                @if ($documentUrl)
                    <strong>{{ $discountOrder->discount_document_name ?: $cardLabel.' ID' }}</strong>
                    <p>{{ $discountOrder->discount_card_name }}</p>
                    <p class="sc-id-number">{{ \App\Support\SeniorPwdDiscount::mask($discountOrder->discount_card_number) }}</p>
                    <a href="{{ $documentUrl }}" target="_blank" rel="noopener" class="sc-id-open">Open ID <span class="sr-only">in a new tab</span></a>
                @elseif ($discountOrder->discount_document_name)
                    <strong>The ID photo was deleted {{ \App\Models\Order::DISCOUNT_PHOTO_DAYS }} days after the order ended.</strong>
                    <p>{{ $discountOrder->discount_card_name }}</p>
                    <p class="sc-id-number">{{ \App\Support\SeniorPwdDiscount::mask($discountOrder->discount_card_number) }}</p>
                @else
                    <strong>No ID photo was submitted.</strong>
                    <p>Ask the customer for the original card at handover.</p>
                    <p>{{ $discountOrder->discount_card_name }}</p>
                    <p class="sc-id-number">{{ \App\Support\SeniorPwdDiscount::mask($discountOrder->discount_card_number) }}</p>
                @endif
            </div>
        </div>

        @if ($reviewed)
            <p class="sc-id-reviewed">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                <span>{{ $sellerOrder->discount_reviewed_at->lt($discountOrder->created_at) ? 'Same ID photo reviewed on an earlier order' : 'ID reviewed' }} · {{ $sellerOrder->discount_reviewed_at->format('M j, Y · g:i A') }}</span>
            </p>
        @elseif ($discountActive)
            <p class="sc-id-pending">Photo not yet reviewed. Approve it to confirm the uploaded card is valid for this discount.</p>
        @endif

        @if ($sellerOrder->discount_card_checked_at)
            <p class="sc-id-reviewed">Original ID checked at handover · {{ $sellerOrder->discount_card_checked_at->format('M j, Y · g:i A') }}</p>
        @elseif ($canCheckDiscountId && $discountActive)
            <form method="POST" action="{{ route('seller.orders.discount-card.check', $sellerOrder) }}" class="sc-id-handover">
                @csrf
                <button type="submit" class="sc-id-handover-button">Original ID checked</button>
            </form>
        @endif

        <x-input-error :messages="$errors->get('discount')" class="sc-id-error" />

        @if (($canCheckDiscountId && ! $reviewed && $discountActive) || ($canSupervise && $discountActive))
            <div class="sc-id-actions">
                @if ($canCheckDiscountId && ! $reviewed && $discountActive)
                    <form method="POST" action="{{ route('seller.orders.discount-document.review', $sellerOrder) }}">
                        @csrf
                        <button type="submit" class="sc-id-approve">Approve ID</button>
                    </form>
                @endif
                @if ($canSupervise && $discountActive)
                    <form method="POST" action="{{ route('seller.orders.senior-pwd-discount.destroy', $sellerOrder) }}" data-confirm-title="Flag this ID as invalid?" data-confirm-reference="Order {{ $discountOrder->number }}" data-confirm-amount="₱{{ number_format((float) bcadd((string) $discountOrder->subtotal, (string) $discountOrder->shipping_amount, 2), 2) }}" data-confirm-from="Discounted" data-confirm-to="Full price" data-confirm="The discount is removed and the customer is charged full price. Check the original card at handover before releasing the order." data-confirm-action="Remove discount" data-confirm-dismiss="Keep discount" data-confirm-tone="danger">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="sc-id-flag">Flag Invalid ID</button>
                    </form>
                @endif
            </div>
        @endif
    </div>
</section>
