{{-- Shown on an order with a prescription medicine. Branch staff can open the pages and pre-check them; only a licensed pharmacist decides. --}}
@php($prescription = $sellerOrder->order->prescription)
@php($viewerMembership = auth()->user()->sellerMembership())
@php($canReviewPrescription = $prescription !== null && ($viewerMembership?->canReviewPrescriptions() ?? false))
@if ($prescription)
    <section class="sc-prescription-panel {{ $class ?? '' }}" data-tone="{{ ['pending' => 'warning', 'approved' => 'info', 'rejected' => 'danger', 'needs_photo' => 'warning'][$prescription->status] ?? 'info' }}" aria-labelledby="prescription-heading-{{ $sellerOrder->id }}">
        <h3 id="prescription-heading-{{ $sellerOrder->id }}" class="sr-only">
            Prescription · {{ ['pending' => 'Waiting for pharmacist', 'approved' => 'Approved', 'rejected' => 'Rejected', 'needs_photo' => 'Waiting for a clearer photo'][$prescription->status] ?? ucfirst($prescription->status) }}
        </h3>
        @if ($prescription->status === 'pending')
            @if ($prescription->checker)
                <p class="mt-1 font-semibold">Checked by {{ $prescription->checker->name }} · {{ $prescription->checked_at->format('M j, g:i A') }}</p>
            @endif
        @elseif ($prescription->reviewer && $prescription->status === 'needs_photo')
            <p class="mt-1">{{ $prescription->status === 'needs_photo' ? 'Clearer photo asked for' : ucfirst($prescription->status) }} by {{ $prescription->reviewer->name }}@if ($prescription->reviewer_license_number) (PRC license {{ $prescription->reviewer_license_number }})@endif on {{ $prescription->reviewed_at->format('M j, Y · g:i A') }}.@if ($prescription->reason) Reason: {{ $prescription->reason }}@endif</p>
        @endif
        @if ($viewerMembership?->is_pharmacist && ! $canReviewPrescription)
            <p class="mt-2 font-semibold">Your PRC license is missing or expired, so you cannot approve prescriptions. Ask the owner to update it on the Staff page.</p>
        @endif
        @if ($viewerMembership)

            <ul class="sc-prescription-pages">
                @foreach ($prescription->images as $image)
                    <li class="sc-document-workspace">
                        @if (in_array(strtolower(pathinfo($image->original_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true))
                            <div class="sc-document-viewer"><img src="{{ route('seller.orders.prescription.images.show', [$sellerOrder, $image, 'preview' => 1]) }}" alt="Prescription page {{ $loop->iteration }}" loading="lazy"></div>
                        @endif
                        <div class="sc-document-meta"><strong>{{ $image->original_name }}</strong><span>{{ $prescription->images->count() > 1 ? 'Page '.$loop->iteration.' of '.$prescription->images->count() : '' }}</span><span class="sr-only">{{ $image->original_name }}</span><a href="{{ route('seller.orders.prescription.images.show', [$sellerOrder, $image, 'preview' => 1]) }}" target="_blank" rel="noopener" class="sc-document-open">Open document <span class="sr-only">page {{ $loop->iteration }} in a new tab</span></a>@if ($loop->first)            @if ($prescription->status === 'pending' && ! $canReviewPrescription && $prescription->checked_at === null)
                <form method="POST" action="{{ route('seller.orders.prescription.check', $sellerOrder) }}" class="mt-3">@csrf

                    <button type="submit" class="seller-outline-button mt-2 min-h-11">Mark as checked</button>
                </form>
            @endif@endif</div>
                    </li>
                @endforeach
            </ul>

            @if ($prescription->status === 'pending')
                <div class="sc-prescription-actions">
                    @if ($canReviewPrescription && ! ($approvalInFooter ?? false))
                        <form method="POST" action="{{ route('seller.orders.prescription.update', $sellerOrder) }}">@csrf @method('PATCH')
                            <input type="hidden" name="decision" value="approve">
                            <button type="submit" class="seller-primary-button w-full">Approve prescription</button>
                        </form>
                    @endif
                    @if ($approvalInFooter ?? false)
                    <dialog class="sc-prescription-dialog" id="prescription-change-{{ $sellerOrder->id }}" aria-labelledby="prescription-change-title-{{ $sellerOrder->id }}"><h3 id="prescription-change-title-{{ $sellerOrder->id }}">Request clearer photo</h3>
                    @else
                    <details class="sc-review-options" @if ($errors->has('reason')) open @endif><summary>Request a change</summary>
                    @endif
                    <form method="POST" action="{{ route('seller.orders.prescription.update', $sellerOrder) }}" class="grid gap-2">@csrf @method('PATCH')
                        <label for="prescription-reason-{{ $sellerOrder->id }}" class="sr-only">Message to the customer</label>
                        <input id="prescription-reason-{{ $sellerOrder->id }}" name="reason" required maxlength="500" class="sc-input" placeholder="Reason for the request">
                        <button type="submit" name="decision" value="needs_photo" class="sc-btn w-full justify-center">Ask for a clearer photo</button>
                        @if ($canReviewPrescription)
                            <button type="submit" name="decision" value="reject" class="sc-btn sc-btn-danger w-full justify-center">Reject and cancel order</button>
                        @endif
                    </form>
                    @if ($approvalInFooter ?? false)<button type="button" class="sc-btn" @click="$el.closest('dialog').close()">Cancel</button></dialog>@else</details>@endif
                </div>
            @endif
        @endif
        @unless ($approvalInFooter ?? false)
            <details class="sc-clean-history"><summary>Review history</summary>@include('seller.orders._prescription-history', ['prescription' => $prescription])</details>
        @endunless
        <x-input-error :messages="$errors->get('prescription')" class="mt-2" />
        <x-input-error :messages="$errors->get('reason')" class="mt-2" />
    </section>
@endif
