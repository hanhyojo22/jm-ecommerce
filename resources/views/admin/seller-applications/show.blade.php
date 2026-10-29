<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <a href="{{ route('admin.seller-applications.index') }}" class="text-sm font-semibold text-brand underline decoration-brand/30 underline-offset-4 focus:outline-none focus:ring-2 focus:ring-brand">Seller applications</a>
                <h1 class="mt-2 marketplace-page-title">Review application</h1>
            </div>
            <span class="inline-flex h-fit w-fit rounded-full px-3 py-1.5 text-sm font-semibold ring-1 ring-inset bg-canvas text-ink ring-line">{{ str_replace('_', ' ', ucfirst($application->status)) }}</span>
        </div>
    </x-slot>

    <div class="bg-canvas py-8 sm:py-10">
        <div class="mx-auto max-w-5xl px-[18px] sm:px-8">
            @if (session('status'))
                <div class="mb-5 rounded-lg border border-success/20 bg-success/10 px-4 py-3 text-sm font-medium text-success" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->has('application'))
                <div class="mb-5 rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">{{ $errors->first('application') }}</div>
            @endif

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div class="space-y-6">
                    <section class="rounded-lg border border-line bg-surface p-5 sm:p-6" aria-labelledby="applicant-details-heading">
                        <div class="flex items-center gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand/10 text-sm font-semibold text-brand" aria-hidden="true">{{ mb_strtoupper(mb_substr($application->user->name, 0, 1)) }}</div>
                            <div class="min-w-0">
                                <h2 id="applicant-details-heading" class="font-semibold text-ink">Applicant details</h2>
                                <p class="mt-0.5 text-sm text-muted">Submitted seller application</p>
                            </div>
                        </div>
                        <dl class="mt-5 grid gap-5 border-t border-line pt-5 sm:grid-cols-2">
                            <div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Account name</dt><dd class="mt-1 break-words font-medium text-ink">{{ $application->user->name }}</dd></div>
                            <div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Email</dt><dd class="mt-1 break-all font-medium text-ink"><a href="mailto:{{ $application->user->email }}" class="text-brand underline decoration-brand/30 underline-offset-4 focus:outline-none focus:ring-2 focus:ring-brand">{{ $application->user->email }}</a></dd></div>
                            @if (filled($application->seller_name) && $application->seller_name !== $application->user->name)
                                <div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Seller contact</dt><dd class="mt-1 break-words font-medium text-ink">{{ $application->seller_name }}</dd></div>
                            @endif
                            @if (filled($application->phone))
                                <div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Phone</dt><dd class="mt-1 break-words font-medium text-ink">{{ $application->phone }}</dd></div>
                            @endif
                        </dl>
                        <dl class="mt-5 grid gap-4 border-t border-line pt-5 text-sm sm:grid-cols-2">
                            @if ($application->id_type)<div><dt class="font-semibold text-muted">Government ID</dt><dd>{{ str_replace('_', ' ', ucfirst($application->id_type)) }} · •••• {{ substr($application->id_number, -4) }}</dd></div>@endif
                            @foreach (['seller_type' => 'Seller type', 'business_name' => 'Business name', 'registration_number' => 'DTI or SEC number', 'bir_details' => 'BIR details', 'representative_name' => 'Representative', 'date_of_birth' => 'Date of birth', 'region' => 'Region', 'province' => 'Province', 'barangay' => 'Barangay', 'postal_code' => 'Postal code', 'pickup_address' => 'Pickup address', 'return_address' => 'Return address', 'processing_days' => 'Processing days', 'fulfillment_methods' => 'Fulfillment', 'delivery_areas' => 'Delivery areas', 'support_contact' => 'Support contact', 'store_slug' => 'Storefront slug'] as $field => $label)
                                @if ($application->{$field})<div><dt class="font-semibold text-muted">{{ $label }}</dt><dd>{{ $application->{$field} instanceof \Carbon\CarbonInterface ? $application->{$field}->toDateString() : $application->{$field} }}</dd></div>@endif
                            @endforeach
                        </dl>
                    </section>

                    <section class="rounded-lg border border-line bg-surface p-5 sm:p-6" aria-labelledby="store-details-heading">
                        <h2 id="store-details-heading" class="font-semibold text-ink">Proposed store</h2>
                        <dl class="mt-5 grid gap-5 border-t border-line pt-5 sm:grid-cols-2">
                            @if (filled($application->store_name))
                                <div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Store name</dt><dd class="mt-1 break-words font-medium text-ink">{{ $application->store_name }}</dd></div>
                            @endif
                            @if (filled($application->store_category))
                                <div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Category</dt><dd class="mt-1 break-words font-medium text-ink">{{ $application->store_category }}</dd></div>
                            @endif
                            @if (filled($application->address_line) || filled($application->city))
                                <div class="sm:col-span-2"><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Location</dt><dd class="mt-1 break-words font-medium text-ink">{{ collect([$application->address_line, $application->city])->filter()->implode(', ') }}</dd></div>
                            @endif
                            @if (filled($application->store_description))
                                <div class="sm:col-span-2"><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Description</dt><dd class="mt-1 whitespace-pre-line break-words text-sm leading-6 text-ink">{{ $application->store_description }}</dd></div>
                            @endif
                        </dl>
                    </section>

                    @if ($application->logo_path)
                        <section class="rounded-lg border border-line bg-surface p-5 sm:p-6" aria-labelledby="submitted-documents-heading">
                            <h2 id="submitted-documents-heading" class="font-semibold text-ink">Submitted documents</h2>
                            <div class="mt-5 flex items-center gap-4 border-t border-line pt-5">
                                <img src="{{ Storage::disk('public')->url($application->logo_path) }}" alt="Submitted logo for {{ $application->store_name }}" class="h-16 w-16 rounded-lg border border-line object-cover">
                                <div><p class="font-medium text-ink">Store logo</p><a href="{{ Storage::disk('public')->url($application->logo_path) }}" target="_blank" rel="noopener" class="mt-1 inline-flex text-sm font-semibold text-brand underline decoration-brand/30 underline-offset-4 focus:outline-none focus:ring-2 focus:ring-brand">Open image</a></div>
                            </div>
                        </section>
                    @endif
                </div>

                <aside class="space-y-6 lg:sticky lg:top-6 lg:h-fit">
                    <section class="rounded-lg border border-line bg-surface p-5 " aria-labelledby="application-summary-heading">
                        <h2 id="application-summary-heading" class="font-semibold text-ink">Application summary</h2>
                        <dl class="mt-4 space-y-4 border-t border-line pt-4 text-sm">
                            <div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Submitted</dt><dd class="mt-1 font-medium text-ink">{{ $application->submitted_at?->format('M j, Y, g:i A') ?? 'Not submitted' }}</dd></div>
                            @if ($application->reviewed_at)
                                <div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Reviewed</dt><dd class="mt-1 font-medium text-ink">{{ $application->reviewed_at->format('M j, Y, g:i A') }}</dd></div>
                            @endif
                            @if (filled($application->review_note))
                                <div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Review note</dt><dd class="mt-1 whitespace-pre-line break-words text-ink">{{ $application->review_note }}</dd></div>
                            @endif
                        </dl>
                    </section>

                    @if (in_array($application->status, ['pending', 'submitted', 'under_review'], true))
                        <section class="rounded-lg border border-success/20 bg-surface p-5 " aria-labelledby="decision-heading">
                            <h2 id="decision-heading" class="font-semibold text-ink">Make a decision</h2>
                            <p class="mt-2 text-sm leading-6 text-muted">Approving creates an active store and an owner membership for this applicant. It will then appear in Stores.</p>
                            <form method="POST" action="{{ route('admin.seller-applications.approve', $application) }}" class="mt-4" onsubmit="return confirm('Approve this seller application? This will create an active store and owner membership.');">
                                @csrf
                                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-brand px-4 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Approve application</button>
                            </form>
                            <form method="POST" action="{{ route('admin.seller-applications.reject', $application) }}" class="mt-5 border-t border-line pt-5">
                                @csrf
                                <label for="reason" class="block text-sm font-semibold text-ink">Reject application</label>
                                <p class="mt-1 text-xs leading-5 text-muted">A reason is required and will be recorded with the application.</p>
                                <textarea id="reason" name="reason" rows="4" required aria-describedby="reason-error" class="mt-3 block w-full rounded-lg border-line text-sm focus:border-danger focus:ring-danger @error('reason') border-danger @enderror" placeholder="Explain why this application cannot be approved.">{{ old('reason') }}</textarea>
                                @error('reason')<p id="reason-error" class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                                <button type="submit" onclick="return confirm('Reject this application?')" class="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-danger/20 bg-danger/10 px-4 text-sm font-semibold text-danger transition hover:border-danger/30 hover:bg-danger/15 focus:outline-none focus:ring-2 focus:ring-danger focus:ring-offset-2">Reject application</button>
                            </form>
                            @if (in_array($application->status, ['pending', 'submitted'], true))<form method="POST" action="{{ route('admin.seller-applications.review', $application) }}" class="mt-4">@csrf<button class="min-h-11 rounded-lg border border-line px-4 text-sm font-semibold">Mark under review</button></form>@endif
                            <form method="POST" action="{{ route('admin.seller-applications.request-information', $application) }}" class="mt-5 space-y-3 border-t border-line pt-5">@csrf
                                <label for="correction_reason" class="block text-sm font-semibold">Request more information</label>
                                <textarea id="correction_reason" name="reason" required minlength="10" rows="3" class="w-full rounded-lg border-line text-sm" placeholder="Explain what needs correction"></textarea>
                                <fieldset><legend class="text-sm font-semibold">Reopen steps</legend>@foreach ([1 => 'Seller type', 2 => 'Personal or business', 3 => 'Store', 4 => 'Address', 5 => 'Documents', 6 => 'Payout'] as $number => $label)<label class="mt-2 flex gap-2 text-sm"><input type="checkbox" name="steps[]" value="{{ $number }}"> {{ $label }}</label>@endforeach</fieldset>
                                <button type="submit" onclick="return confirm('Request these corrections?')" class="min-h-11 rounded-lg border border-line px-4 text-sm font-semibold">Request corrections</button>
                            </form>
                        </section>
                    @endif
                    @if ($application->status === 'approved')<form method="POST" action="{{ route('admin.seller-applications.suspend', $application) }}" class="rounded-lg border border-danger/20 bg-white p-5">@csrf<label for="suspend_reason" class="block text-sm font-semibold">Suspend seller</label><textarea id="suspend_reason" name="reason" required minlength="10" class="mt-2 w-full rounded-lg border-line text-sm"></textarea><button type="submit" onclick="return confirm('Suspend this seller and store?')" class="mt-2 min-h-11 rounded-lg bg-danger px-4 text-sm font-semibold text-white">Suspend seller</button></form>@endif
                    <section class="rounded-lg border border-line bg-white p-5"><h2 class="font-semibold">Verification documents</h2><ul class="mt-3 space-y-2 text-sm">@forelse ($application->documents as $document)<li><a href="{{ route('admin.seller-applications.document', [$application, $document]) }}" target="_blank" rel="noopener" class="font-semibold text-brand underline">{{ str_replace('_', ' ', ucfirst($document->type)) }}</a></li>@empty<li class="text-muted">No documents uploaded.</li>@endforelse</ul></section>
                    <section class="rounded-lg border border-line bg-white p-5"><h2 class="font-semibold">Payout verification</h2><p class="mt-2 text-sm">{{ $application->payoutProfile?->provider ?? 'Not provided' }} · {{ $application->payoutProfile?->maskedAccountNumber() }}</p>@if ($application->payoutProfile?->name_requires_review)<p class="mt-2 text-sm text-warning-text">Account name needs manual review.</p>@endif</section>
                    <section class="rounded-lg border border-line bg-white p-5"><h2 class="font-semibold">Status history</h2><ol class="mt-3 space-y-2 text-sm">@forelse ($application->statusHistory as $event)<li>{{ $event->created_at->format('M j, Y g:i A') }} · {{ str_replace('_', ' ', $event->to_status) }} @if ($event->reason) · {{ $event->reason }} @endif</li>@empty<li class="text-muted">No status changes recorded.</li>@endforelse</ol></section>
                    <section class="rounded-lg border border-line bg-white p-5"><h2 class="font-semibold">Review notes</h2><ol class="mt-3 space-y-2 text-sm">@forelse ($application->reviews as $review)<li>{{ $review->created_at->format('M j, Y g:i A') }} · {{ str_replace('_', ' ', $review->action) }} @if ($review->note) · {{ $review->note }} @endif</li>@empty<li class="text-muted">No notes yet.</li>@endforelse</ol><form method="POST" action="{{ route('admin.seller-applications.note', $application) }}" class="mt-4">@csrf<label for="review_note" class="block text-sm font-semibold">Add review note</label><textarea id="review_note" name="note" required minlength="3" maxlength="2000" rows="3" class="mt-2 w-full rounded-lg border-line text-sm"></textarea><button class="mt-2 min-h-11 rounded-lg border border-line px-4 text-sm font-semibold">Save note</button></form></section>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
