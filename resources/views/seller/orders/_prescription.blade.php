{{-- Shown on an order with a prescription medicine. Branch staff can open the pages and pre-check them; only a licensed pharmacist decides. --}}
@php($prescription = $sellerOrder->order->prescription)
@php($viewerMembership = auth()->user()->sellerMembership())
@php($canReviewPrescription = $prescription !== null && ($viewerMembership?->canReviewPrescriptions() ?? false))
@if ($prescription)
    <section class="sc-notice !block {{ $class ?? '' }}" data-tone="{{ ['pending' => 'warning', 'approved' => 'info', 'rejected' => 'danger', 'needs_photo' => 'warning'][$prescription->status] ?? 'info' }}" aria-labelledby="prescription-heading-{{ $sellerOrder->id }}">
        <h2 id="prescription-heading-{{ $sellerOrder->id }}" class="font-semibold">
            Prescription · {{ ['pending' => 'Waiting for pharmacist', 'approved' => 'Approved', 'rejected' => 'Rejected', 'needs_photo' => 'Waiting for a clearer photo'][$prescription->status] ?? ucfirst($prescription->status) }}
        </h2>
        @if ($prescription->status === 'pending')
            <p class="mt-1">This order has a prescription medicine. A pharmacist must approve the customer’s prescription before the order can be prepared. Approving accepts the order.</p>
            @if ($prescription->checker)
                <p class="mt-1 font-semibold">Checked by {{ $prescription->checker->name }} on {{ $prescription->checked_at->format('M j, Y · g:i A') }}. Waiting for a pharmacist to approve.</p>
            @endif
        @elseif ($prescription->reviewer)
            <p class="mt-1">{{ $prescription->status === 'needs_photo' ? 'Clearer photo asked for' : ucfirst($prescription->status) }} by {{ $prescription->reviewer->name }}@if ($prescription->reviewer_license_number) (PRC license {{ $prescription->reviewer_license_number }})@endif on {{ $prescription->reviewed_at->format('M j, Y · g:i A') }}.@if ($prescription->reason) Reason: {{ $prescription->reason }}@endif</p>
        @endif
        @if ($viewerMembership?->is_pharmacist && ! $canReviewPrescription)
            <p class="mt-2 font-semibold">Your PRC license is missing or expired, so you cannot approve prescriptions. Ask the owner to update it on the Staff page.</p>
        @endif
        @if ($viewerMembership)
            <p class="mt-2 font-semibold">Prescription pages ({{ $prescription->images->count() }})</p>
            <ul class="mt-1 list-disc space-y-1 pl-5">
                @foreach ($prescription->images as $image)
                    <li><a href="{{ route('seller.orders.prescription.images.show', [$sellerOrder, $image]) }}" class="font-semibold underline">{{ $image->original_name }}</a></li>
                @endforeach
            </ul>
            @if ($prescription->status === 'pending' && ! $canReviewPrescription && $prescription->checked_at === null)
                <form method="POST" action="{{ route('seller.orders.prescription.check', $sellerOrder) }}" class="mt-3">@csrf
                    <p class="text-sm">Check that the photo is clear, the patient name matches the order, and the prescription has not expired. A pharmacist still makes the final approval.</p>
                    <button type="submit" class="seller-outline-button mt-2 min-h-11">Mark as checked</button>
                </form>
            @endif
            @if ($prescription->status === 'pending')
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    @if ($canReviewPrescription)
                        <form method="POST" action="{{ route('seller.orders.prescription.update', $sellerOrder) }}">@csrf @method('PATCH')
                            <input type="hidden" name="decision" value="approve">
                            <button type="submit" class="seller-primary-button w-full">Approve prescription</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('seller.orders.prescription.update', $sellerOrder) }}" class="grid gap-2">@csrf @method('PATCH')
                        <label for="prescription-reason-{{ $sellerOrder->id }}" class="text-sm font-semibold">Message to the customer</label>
                        <input id="prescription-reason-{{ $sellerOrder->id }}" name="reason" required maxlength="500" class="sc-input" placeholder="{{ $canReviewPrescription ? 'For example: the photo is blurry, or the prescription has expired' : 'For example: the photo is blurry' }}">
                        <button type="submit" name="decision" value="needs_photo" class="sc-btn w-full justify-center">Ask for a clearer photo</button>
                        @if ($canReviewPrescription)
                            <button type="submit" name="decision" value="reject" class="sc-btn sc-btn-danger w-full justify-center">Reject and cancel order</button>
                        @endif
                    </form>
                </div>
            @endif
        @endif
        @php($timeline = $prescription->timeline())
        @if ($timeline->isNotEmpty())
            <details class="mt-3">
                <summary class="cursor-pointer text-sm font-semibold">Review history</summary>
                <ol class="mt-2 space-y-2 text-sm">
                    @foreach ($timeline as $event)
                        <li>
                            <span class="font-medium">{{ ['prescription.submitted' => 'Customer sent the prescription', 'prescription.resubmitted' => 'Customer sent a new copy', 'prescription.checked' => 'Checked by staff', 'prescription.approved' => 'Prescription approved', 'prescription.rejected' => 'Prescription rejected', 'prescription.needs_photo' => 'Clearer photo requested'][$event->action] ?? $event->action }}</span>
                            <span class="text-muted">· {{ $event->created_at->format('M j, Y · g:i A') }}@if ($event->actor) · {{ $event->actor->name }}@endif</span>
                            @if ($event->new_values['reason'] ?? null)
                                <span class="block text-muted">{{ $event->new_values['reason'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </details>
        @endif
        <x-input-error :messages="$errors->get('prescription')" class="mt-2" />
        <x-input-error :messages="$errors->get('reason')" class="mt-2" />
    </section>
@endif
