<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <a href="{{ route('admin.seller-applications.index') }}" class="text-sm font-semibold text-brand underline decoration-brand/30 underline-offset-4 focus:outline-none focus:ring-2 focus:ring-brand">Seller applications</a>
                <h1 class="mt-2 text-[28px] font-bold tracking-[-0.02em] text-ink leading-[1.55]">Review application</h1>
            </div>
            <span class="inline-flex h-fit w-fit rounded-full px-3 py-1.5 text-sm font-semibold ring-1 ring-inset {{ ['pending' => 'bg-warning/10 text-warning-text ring-warning/20', 'approved' => 'bg-success/10 text-success ring-success/20', 'rejected' => 'bg-danger/10 text-danger ring-danger/20'][$application->status] }}">{{ ucfirst($application->status) }}</span>
        </div>
    </x-slot>

    <div class="bg-canvas py-8 sm:py-10">
        <div class="mx-auto max-w-5xl px-[18px] sm:px-8">
            @if (session('status'))
                <div class="mb-5 rounded-[14px] border border-success/20 bg-success/10 px-4 py-3 text-sm font-medium text-success" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->has('application'))
                <div class="mb-5 rounded-[14px] border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">{{ $errors->first('application') }}</div>
            @endif

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div class="space-y-6">
                    <section class="rounded-[14px] border border-line bg-surface p-5 sm:p-6" aria-labelledby="applicant-details-heading">
                        <div class="flex items-center gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand/10 text-sm font-bold text-brand" aria-hidden="true">{{ mb_strtoupper(mb_substr($application->user->name, 0, 1)) }}</div>
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
                    </section>

                    <section class="rounded-[14px] border border-line bg-surface p-5 sm:p-6" aria-labelledby="store-details-heading">
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
                        <section class="rounded-[14px] border border-line bg-surface p-5 sm:p-6" aria-labelledby="submitted-documents-heading">
                            <h2 id="submitted-documents-heading" class="font-semibold text-ink">Submitted documents</h2>
                            <div class="mt-5 flex items-center gap-4 border-t border-line pt-5">
                                <img src="{{ Storage::disk('public')->url($application->logo_path) }}" alt="Submitted logo for {{ $application->store_name }}" class="h-16 w-16 rounded-[14px] border border-line object-cover">
                                <div><p class="font-medium text-ink">Store logo</p><a href="{{ Storage::disk('public')->url($application->logo_path) }}" target="_blank" rel="noopener" class="mt-1 inline-flex text-sm font-semibold text-brand underline decoration-brand/30 underline-offset-4 focus:outline-none focus:ring-2 focus:ring-brand">Open image</a></div>
                            </div>
                        </section>
                    @endif
                </div>

                <aside class="space-y-6 lg:sticky lg:top-6 lg:h-fit">
                    <section class="rounded-[14px] border border-line bg-surface p-5 " aria-labelledby="application-summary-heading">
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

                    @if ($application->status === 'pending')
                        <section class="rounded-[14px] border border-success/20 bg-surface p-5 " aria-labelledby="decision-heading">
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
                                <button type="submit" class="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-danger/20 bg-danger/10 px-4 text-sm font-semibold text-danger transition hover:border-danger/30 hover:bg-danger/15 focus:outline-none focus:ring-2 focus:ring-danger focus:ring-offset-2">Reject application</button>
                            </form>
                        </section>
                    @endif
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
