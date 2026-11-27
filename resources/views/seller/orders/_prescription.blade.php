{{-- Shown on an order with a prescription medicine. Branch staff can open the pages and pre-check them; only a licensed pharmacist decides. --}}
@php($prescription = $sellerOrder->order->prescription)
@php($viewerMembership = auth()->user()->sellerMembership())
@php($canCheckPrescription = $viewerMembership?->canWorkOrders() ?? false)
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
                            {{-- A file lost from storage says so, instead of leaving the browser's alt text in an empty box. --}}
                            <div class="sc-document-viewer" x-data="{ missing: false }"><img x-show="! missing" x-on:error="missing = true" src="{{ route('seller.orders.prescription.images.show', [$sellerOrder, $image, 'preview' => 1]) }}" alt="Prescription page {{ $loop->iteration }}" loading="lazy"><p x-show="missing" x-cloak class="sc-document-missing" role="status"><strong>This photo is no longer available.</strong> Use “Ask for a clearer photo” so the customer sends it again.</p></div>
                        @endif
                        <div class="sc-document-meta"><strong>{{ $image->original_name }}</strong><span>{{ $prescription->images->count() > 1 ? 'Page '.$loop->iteration.' of '.$prescription->images->count() : '' }}</span><span class="sr-only">{{ $image->original_name }}</span><a href="{{ route('seller.orders.prescription.images.show', [$sellerOrder, $image, 'preview' => 1]) }}" target="_blank" rel="noopener" class="sc-document-open">Open document <span class="sr-only">page {{ $loop->iteration }} in a new tab</span></a>@if ($loop->first)            @if ($prescription->status === 'pending' && $canCheckPrescription && ! $canReviewPrescription && $prescription->checked_at === null)
                <form method="POST" action="{{ route('seller.orders.prescription.check', $sellerOrder) }}" class="mt-3">@csrf

                    <button type="submit" class="seller-outline-button mt-2 min-h-11">Mark as checked</button>
                </form>
            @endif@endif</div>
                    </li>
                @endforeach
            </ul>

            @if ($prescription->status === 'pending' && ($canCheckPrescription || $canReviewPrescription))
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
        @if ($canReviewPrescription)
            {{-- The pharmacist's directions replace the product label for this purchase when the buyer sets up medicine reminders. --}}
            <details class="sc-clean-history" @if ($errors->hasAny(['medicine_kind', 'dose_quantity', 'dose_unit', 'doses_per_day', 'course_days', 'max_doses_per_day'])) open @endif><summary>Reminder directions</summary>
                @foreach ($sellerOrder->items as $item)
                    @php($directions = $item->medicationDirections() ?? [])
                    <form method="POST" action="{{ route('seller.orders.items.directions', [$sellerOrder, $item]) }}" class="mt-3 grid gap-2">@csrf @method('PATCH')
                        <p class="font-semibold">{{ $item->product_name }}</p>
                        <label class="sr-only" for="kind-{{ $item->id }}">Kind</label>
                        <select id="kind-{{ $item->id }}" name="medicine_kind" class="sc-input">@foreach (['maintenance' => 'Every day', 'course' => 'Course', 'as_needed' => 'As needed', 'none' => 'No reminders'] as $value => $label)<option value="{{ $value }}" @selected(($directions['medicine_kind'] ?? 'maintenance') === $value)>{{ $label }}</option>@endforeach</select>
                        <div class="grid grid-cols-2 gap-2">
                            <input name="dose_quantity" inputmode="decimal" value="{{ $directions['dose_quantity'] ?? '' }}" class="sc-input" placeholder="Dose" aria-label="Dose">
                            <input name="dose_unit" maxlength="20" value="{{ $directions['dose_unit'] ?? '' }}" class="sc-input" placeholder="Unit" aria-label="Unit">
                            <input name="doses_per_day" inputmode="numeric" value="{{ $directions['doses_per_day'] ?? '' }}" class="sc-input" placeholder="Doses a day" aria-label="Doses a day">
                            <input name="course_days" inputmode="numeric" value="{{ $directions['course_days'] ?? '' }}" class="sc-input" placeholder="Course days" aria-label="Course days">
                            <input name="max_doses_per_day" inputmode="numeric" value="{{ $directions['max_doses_per_day'] ?? '' }}" class="sc-input" placeholder="Most a day (as needed)" aria-label="Most doses a day">
                            <input name="min_hours_between" inputmode="numeric" value="{{ $directions['min_hours_between'] ?? '' }}" class="sc-input" placeholder="Hours between" aria-label="Hours between doses">
                        </div>
                        <input name="directions_note" maxlength="200" value="{{ $directions['directions_note'] ?? '' }}" class="sc-input" placeholder="Note, e.g. after meals" aria-label="Note for the buyer">
                        <button type="submit" class="sc-btn w-full justify-center">Save directions</button>
                    </form>
                @endforeach
                @foreach (['medicine_kind', 'dose_quantity', 'dose_unit', 'doses_per_day', 'course_days', 'max_doses_per_day'] as $field)<x-input-error :messages="$errors->get($field)" class="mt-2" />@endforeach
            </details>
        @endif
        @unless ($approvalInFooter ?? false)
            <details class="sc-clean-history"><summary>Review history</summary>@include('seller.orders._prescription-history', ['prescription' => $prescription])</details>
        @endunless
        <x-input-error :messages="$errors->get('prescription')" class="mt-2" />
        <x-input-error :messages="$errors->get('reason')" class="mt-2" />
    </section>
@endif
