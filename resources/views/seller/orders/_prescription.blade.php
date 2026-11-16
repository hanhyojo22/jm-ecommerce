{{-- Shown on an order with a prescription medicine. Only pharmacists can open the file or decide. --}}
@php($prescription = $sellerOrder->order->prescription)
@php($canReviewPrescription = $prescription !== null && (auth()->user()->sellerMembership()?->is_pharmacist ?? false))
@if ($prescription)
    <section class="sc-notice !block {{ $class ?? '' }}" data-tone="{{ ['pending' => 'warning', 'approved' => 'info', 'rejected' => 'danger', 'needs_photo' => 'warning'][$prescription->status] ?? 'info' }}" aria-labelledby="prescription-heading-{{ $sellerOrder->id }}">
        <h2 id="prescription-heading-{{ $sellerOrder->id }}" class="font-semibold">
            Prescription · {{ ['pending' => 'Waiting for pharmacist', 'approved' => 'Approved', 'rejected' => 'Rejected', 'needs_photo' => 'Waiting for a clearer photo'][$prescription->status] ?? ucfirst($prescription->status) }}
        </h2>
        @if ($prescription->status === 'pending')
            <p class="mt-1">This order has a prescription medicine. A pharmacist must approve the customer’s prescription before the order can be prepared. Approving accepts the order.</p>
        @elseif ($prescription->reviewer)
            <p class="mt-1">{{ $prescription->status === 'needs_photo' ? 'Clearer photo asked for' : ucfirst($prescription->status) }} by {{ $prescription->reviewer->name }} on {{ $prescription->reviewed_at->format('M j, Y · g:i A') }}.@if ($prescription->reason) Reason: {{ $prescription->reason }}@endif</p>
        @endif
        @if ($canReviewPrescription)
            <p class="mt-2"><a href="{{ route('seller.orders.prescription.show', $sellerOrder) }}" class="font-semibold underline">View prescription ({{ $prescription->original_name }})</a></p>
            @if ($prescription->status === 'pending')
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <form method="POST" action="{{ route('seller.orders.prescription.update', $sellerOrder) }}">@csrf @method('PATCH')
                        <input type="hidden" name="decision" value="approve">
                        <button type="submit" class="seller-primary-button w-full">Approve prescription</button>
                    </form>
                    <form method="POST" action="{{ route('seller.orders.prescription.update', $sellerOrder) }}" class="grid gap-2">@csrf @method('PATCH')
                        <label for="prescription-reason-{{ $sellerOrder->id }}" class="text-sm font-semibold">Message to the customer</label>
                        <input id="prescription-reason-{{ $sellerOrder->id }}" name="reason" required maxlength="500" class="sc-input" placeholder="For example: the photo is blurry, or the prescription has expired">
                        <button type="submit" name="decision" value="needs_photo" class="sc-btn w-full justify-center">Ask for a clearer photo</button>
                        <button type="submit" name="decision" value="reject" class="sc-btn sc-btn-danger w-full justify-center">Reject and cancel order</button>
                    </form>
                </div>
            @endif
        @endif
        <x-input-error :messages="$errors->get('prescription')" class="mt-2" />
        <x-input-error :messages="$errors->get('reason')" class="mt-2" />
    </section>
@endif
