<x-app-layout>
    @php
        $steps = [
            1 => ['label' => 'Seller type', 'hint' => 'Individual or business', 'phase' => 'About you', 'title' => 'How will you sell?', 'intro' => 'Choose the type that matches your documents. It sets what we ask for later, and can’t be changed after approval.'],
            2 => ['label' => 'Your details', 'hint' => 'Contact and address', 'phase' => 'About you', 'title' => 'Your details', 'intro' => 'Use the name on your government ID. Buyers won’t see it — we only use it to verify you and pay you.'],
            3 => ['label' => 'Storefront', 'hint' => 'Name, logo, category', 'phase' => 'Your store', 'title' => 'Set up your storefront', 'intro' => 'This is what buyers see first. You can change any of it later in Store settings.'],
            4 => ['label' => 'Shipping', 'hint' => 'Rates and coverage', 'phase' => 'Your store', 'title' => 'Where you ship from', 'intro' => 'Couriers collect orders here, and returns come back here. Use a Philippine address.'],
            5 => ['label' => 'Identity', 'hint' => 'ID and documents', 'phase' => 'Verify & get paid', 'title' => 'Verify your identity', 'intro' => 'Only JMMart reviewers can see these documents. Buyers never do.'],
            6 => ['label' => 'Payout', 'hint' => 'Where earnings go', 'phase' => 'Verify & get paid', 'title' => 'Where should we send your earnings?', 'intro' => 'Use an account under your legal or business name. You can change it later in Payout settings.'],
            7 => ['label' => 'Review', 'hint' => 'Check and submit', 'phase' => 'Finish', 'title' => 'Review and submit', 'intro' => 'Check your details, accept the seller policies, and send your application. We’ll email you when review is done.'],
        ];
        $step = min(7, max(1, (int) request('step', 1)));
        $completed = $application?->completed_steps ?? [];
        $editable = ! $workspaceUnavailable && (! $application || in_array($application->status, ['draft', 'more_information_required'], true));
        if ($application?->status === 'more_information_required' && $step <= 6 && ! in_array($step, $application->correction_steps ?? [], true)) { $step = 7; }
        $completedCount = count(array_intersect(range(1, 6), $completed));
        $progress = (int) round($completedCount / 6 * 100);
        $isBusiness = $application?->seller_type === 'business';
        $canEditStep = fn (int $number): bool => $application?->status !== 'more_information_required' || $number === 7 || in_array($number, $application->correction_steps ?? [], true);
        $inReview = in_array($application?->status, ['pending', 'submitted', 'under_review'], true);
        $firstName = Str::of(auth()->user()->name)->explode(' ')->first();
        $idTypes = ['passport' => 'Passport', 'drivers_license' => 'Driver’s license', 'national_id' => 'National ID', 'other' => 'Other government ID'];
        $shippingAddress = collect([$application?->address_line, $application?->barangay, $application?->city, $application?->province, $application?->region, $application?->postal_code])->filter()->implode(', ');
        $sameAddresses = (bool) old('same_addresses', blank($application?->pickup_address) || ($application->pickup_address === $application->return_address && $application->pickup_address === $application->address_line.', '.$application->barangay.', '.$application->city.', '.$application->province.', '.$application->region.' '.$application->postal_code));
        $missingSteps = array_diff(range(1, 6), $completed);
        $lockedNote = 'Not open for changes in this correction';
    @endphp

    <div
        @class(['onboarding-app', 'min-h-screen'])
        @if ($editable && $step <= 6)
            x-data="{ saving: false, exitBlocked: false, uploadProgress: 0, uploadError: '', validationErrors: [], saveAndExit() { const form = this.$refs.stepForm; this.exitBlocked = false; if (! form.checkValidity()) { this.exitBlocked = true; form.reportValidity(); return; } this.send(form, true); }, save(event) { this.send(event.target, false); }, send(form, exit) { this.saving = true; this.exitBlocked = false; this.uploadError = ''; this.validationErrors = []; const data = new FormData(form); if (exit) data.set('exit', '1'); const request = new XMLHttpRequest(); request.open('POST', form.action); request.setRequestHeader('Accept', 'application/json'); request.upload.onprogress = (progress) => { if (progress.lengthComputable) this.uploadProgress = Math.round(progress.loaded / progress.total * 100) }; request.onload = () => { if (request.status === 200) { window.location.assign(JSON.parse(request.responseText).redirect); return; } this.saving = false; if (request.status === 422) { this.exitBlocked = exit; this.validationErrors = Object.values(JSON.parse(request.responseText).errors).flat(); this.$nextTick(() => this.$refs.formErrors?.focus()); } else { this.uploadError = request.status === 419 ? 'Your session expired. Refresh the page, then save this step again.' : (request.status === 429 ? 'Too many saves in a minute. Wait a moment, then try again.' : 'Could not save this step. Please try again.'); } }; request.onerror = () => { this.saving = false; this.uploadError = 'Upload failed. Please check your connection and retry.'; }; request.send(data); } }"
        @endif
    >
        <header class="onboarding-topbar">
            <div class="onboarding-topbar-inner">
                <a href="{{ route('home') }}" class="onboarding-brand" aria-label="JMMart home">
                    <img src="{{ asset('favicon.svg') }}" alt="" width="30" height="30">
                    <span class="onboarding-brand-name">JMMart</span>
                    <span class="onboarding-brand-divider" aria-hidden="true"></span>
                    <span class="onboarding-brand-context">Seller application</span>
                </a>
                <div class="onboarding-topbar-actions">
                    @if ($editable && $step <= 6)
                        <span class="onboarding-save-state" aria-live="polite">
                            <span class="onboarding-save-dot" aria-hidden="true"></span>
                            <span x-show="!saving">Draft saves when you continue</span>
                            <span x-cloak x-show="saving">Saving draft…</span>
                        </span>
                        <button type="button" class="onboarding-topbar-button" :disabled="saving" @click="saveAndExit()">
                            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 8l-4 4 4 4M6 12h10" /></svg>
                            Save &amp; exit
                        </button>
                    @elseif ($editable)
                        <a href="{{ route('profile.edit') }}" class="onboarding-topbar-button">
                            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 8l-4 4 4 4M6 12h10" /></svg>
                            Save &amp; exit
                        </a>
                    @else
                        <a href="{{ route('profile.edit') }}" class="onboarding-topbar-button">Back to account</a>
                    @endif
                </div>
            </div>
        </header>

        @if (! $editable)
            <main id="main-content" tabindex="-1" class="onboarding-state-shell">
                @if (session('status'))<p role="status" class="mb-5 rounded-md border border-success/20 bg-success/10 p-4 text-sm text-success">{{ session('status') }}</p>@endif
                @if ($errors->any())
                    <div role="alert" class="mb-5 rounded-md border border-danger/20 bg-danger/10 p-4 text-sm text-danger">
                        <p class="font-semibold">Please correct these fields.</p>
                        <ul class="mt-2 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                @if ($workspaceUnavailable)
                    <section class="onboarding-state-card" aria-labelledby="state-title">
                        <h1 id="state-title" class="!mt-0 !text-2xl">Your seller workspace is unavailable</h1>
                        @if ($workspaceSuspended)
                            <p class="onboarding-state-lead">Your store is currently suspended. Shopping is unaffected.</p>
                            @if ($suspensionReason)<p class="onboarding-state-lead">Reason: {{ $suspensionReason }}</p>@endif
                            @if ($suspensionAppeal?->status === 'pending')<p class="onboarding-state-ref">Your appeal is awaiting review.</p>@endif
                            @if ($suspensionAppeal?->status === 'rejected')<p class="onboarding-state-ref">Your previous appeal was not approved. {{ $suspensionAppeal->review_note }}</p>@endif
                            @if ($canSubmitSuspensionAppeal)
                                <form method="POST" action="{{ route('seller.suspension-appeals.store') }}" class="mt-5 space-y-3">@csrf
                                    <label for="appeal" class="field-label">Appeal statement</label>
                                    <textarea id="appeal" name="statement" required minlength="20" maxlength="5000" rows="5" class="field-control">{{ old('statement') }}</textarea>
                                    <x-primary-button>Submit an appeal</x-primary-button>
                                </form>
                            @endif
                        @else
                            <p class="onboarding-state-lead">Contact JMMart support for help with this store.</p>
                        @endif
                        <div class="onboarding-state-actions">
                            <a href="{{ route('profile.edit') }}" class="onboarding-button onboarding-button--outline">Back to account</a>
                        </div>
                    </section>
                @elseif ($inReview)
                    @php($submittedAt = $application->statusHistory()->where('to_status', 'submitted')->latest()->first()?->created_at ?? $application->updated_at)
                    <section class="onboarding-state-card" aria-labelledby="state-title">
                        <div class="onboarding-state-icon" aria-hidden="true">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="m5 12.5 4.2 4.2L19 7" /></svg>
                        </div>
                        <p class="onboarding-kicker">Application received</p>
                        <h1 id="state-title">You’re in review, {{ $firstName }}.</h1>
                        <p class="onboarding-state-lead">We’re checking your store and documents. Most applications take 1–2 business days, and we’ll email you the moment there’s news.</p>
                        <p class="onboarding-state-ref">Application <strong>#JM-{{ Str::upper($application->id) }}</strong>@if ($submittedAt) · Submitted {{ $submittedAt->format('M j, Y') }}@endif</p>
                        <ol class="onboarding-timeline" aria-label="Application status timeline">
                            <li class="is-done">
                                <span class="onboarding-timeline-dot" aria-hidden="true"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="m5 12.5 4.2 4.2L19 7" /></svg></span>
                                <div><strong>Application submitted</strong><span>{{ $submittedAt?->format('M j, Y') ?? 'Today' }}</span></div>
                            </li>
                            <li class="is-current">
                                <span class="onboarding-timeline-dot" aria-hidden="true"></span>
                                <div><strong>Under review</strong><span>Usually 1–2 business days</span></div>
                            </li>
                            <li>
                                <span class="onboarding-timeline-dot" aria-hidden="true"></span>
                                <div><strong>Decision</strong><span>We’ll email {{ auth()->user()->email }}</span></div>
                            </li>
                        </ol>
                        <div class="onboarding-state-actions">
                            <a href="{{ route('catalog.index') }}" class="onboarding-button onboarding-button--primary">Browse JMMart</a>
                            <a href="{{ route('profile.edit') }}" class="onboarding-button onboarding-button--quiet">View account</a>
                        </div>
                        <p class="onboarding-state-note">You can keep shopping while we review. Your store stays private until it’s approved.</p>
                    </section>
                @else
                    <section class="onboarding-state-card" aria-labelledby="state-title">
                        <p class="onboarding-kicker">Seller application</p>
                        <h1 id="state-title">Application {{ str_replace('_', ' ', $application->status) }}</h1>
                        <p class="onboarding-state-lead">Store: {{ $application->store_name }}. {{ $application->status === 'rejected' ? 'This application wasn’t approved. You can keep shopping on JMMart as usual.' : 'You can keep shopping on JMMart as usual.' }}</p>
                        @if ($application->review_note)<p class="mt-3 rounded-md bg-canvas p-3 text-sm">@if ($application->status === 'rejected')<strong>Reason:</strong> @endif{{ $application->review_note }}</p>@endif
                        <ol class="onboarding-timeline" aria-label="Application status timeline">
                            @foreach ($application->statusHistory()->oldest()->get() as $event)
                                <li class="is-done">
                                    <span class="onboarding-timeline-dot" aria-hidden="true"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="m5 12.5 4.2 4.2L19 7" /></svg></span>
                                    <div><strong>{{ str_replace('_', ' ', ucfirst($event->to_status)) }}</strong><span>{{ $event->created_at->format('M j, Y g:i A') }}</span></div>
                                </li>
                            @endforeach
                        </ol>
                        <div class="onboarding-state-actions">
                            <a href="{{ route('catalog.index') }}" class="onboarding-button onboarding-button--primary">Continue shopping</a>
                            <a href="{{ route('profile.edit') }}" class="onboarding-button onboarding-button--quiet">View account</a>
                        </div>
                    </section>
                @endif
            </main>
        @else
            <div class="onboarding-shell">
                <aside class="onboarding-rail">
                    <div class="onboarding-rail-intro">
                        <h1>Become a seller</h1>
                        <p>Seven short steps, about 10 minutes. Your answers save as you go.</p>
                    </div>

                    <div class="onboarding-progress" role="progressbar" aria-label="Application completion" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="onboarding-dial" aria-hidden="true">
                            <svg viewBox="0 0 46 46">
                                <circle class="onboarding-dial-track" cx="23" cy="23" r="20" />
                                <circle class="onboarding-dial-fill" cx="23" cy="23" r="20" pathLength="100" style="stroke-dashoffset: {{ 100 - $progress }}" />
                            </svg>
                            <span>{{ $progress }}%</span>
                        </div>
                        <div class="onboarding-progress-copy">
                            <span class="onboarding-progress-label">Application</span>
                            <strong>Step {{ $step }} of 7</strong>
                            <span>{{ $completedCount }} of 6 steps complete</span>
                        </div>
                    </div>

                    <div class="onboarding-mobile-progress">
                        <div class="onboarding-progress-row"><strong>{{ $progress }}% complete</strong><span>Step {{ $step }} of 7</span></div>
                        <div class="onboarding-track" role="progressbar" aria-label="Application completion" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"><span style="width: {{ $progress }}%"></span></div>
                        <div class="onboarding-dots" role="group" aria-label="Application steps">
                            @foreach ($steps as $number => $item)
                                @php($done = in_array($number, $completed, true) && $number !== $step)
                                @if ($canEditStep($number))
                                    <a href="{{ route('seller-application.create', ['step' => $number]) }}" class="onboarding-dot-link" @if ($number === $step) aria-current="step" @endif @if ($done) data-done="true" @endif aria-label="Step {{ $number }}: {{ $item['label'] }}">
                                        @if ($done)
                                            <svg class="size-3" fill="none" stroke="currentColor" stroke-width="2.6" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12l5 5L20 7" /></svg>
                                        @else
                                            {{ $number }}
                                        @endif
                                    </a>
                                @else
                                    <span class="onboarding-dot-link" aria-disabled="true" title="{{ $lockedNote }}" aria-label="Step {{ $number }}: {{ $item['label'] }}, {{ Str::lower($lockedNote) }}">
                                        @if ($done)
                                            <svg class="size-3" fill="none" stroke="currentColor" stroke-width="2.6" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12l5 5L20 7" /></svg>
                                        @else
                                            {{ $number }}
                                        @endif
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <nav class="onboarding-steps" aria-label="Application progress">
                        @foreach ($steps as $number => $item)
                            @if ($number === 1 || $steps[$number - 1]['phase'] !== $item['phase'])
                                <p class="onboarding-phase" aria-hidden="true">{{ $item['phase'] }}</p>
                            @endif
                            @php($done = in_array($number, $completed, true) && $number !== $step)
                            @if ($canEditStep($number))
                                <a href="{{ route('seller-application.create', ['step' => $number]) }}" class="onboarding-step" @if ($number === $step) aria-current="step" @endif @if ($done) data-done="true" @endif>
                                    <span aria-hidden="true" class="onboarding-step-dot">
                                        @if ($done)
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" /></svg>
                                        @else
                                            {{ $number }}
                                        @endif
                                    </span>
                                    <span class="onboarding-step-copy">
                                        <span class="onboarding-step-name"><span class="sr-only">Step {{ $number }} </span>{{ $item['label'] }}</span>
                                        <span class="onboarding-step-hint">{{ $item['hint'] }}@if ($done)<span class="sr-only">, done</span>@endif</span>
                                    </span>
                                </a>
                            @else
                                <span class="onboarding-step" role="link" aria-disabled="true" title="{{ $lockedNote }}" @if ($number === $step) aria-current="step" @endif @if ($done) data-done="true" @endif>
                                    <span aria-hidden="true" class="onboarding-step-dot">
                                        @if ($done)
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" /></svg>
                                        @else
                                            {{ $number }}
                                        @endif
                                    </span>
                                    <span class="onboarding-step-copy">
                                        <span class="onboarding-step-name"><span class="sr-only">Step {{ $number }} </span>{{ $item['label'] }}</span>
                                        <span class="onboarding-step-hint">{{ $item['hint'] }}@if ($done)<span class="sr-only">, done</span>@endif<span class="sr-only">, {{ Str::lower($lockedNote) }}</span></span>
                                    </span>
                                </span>
                            @endif
                        @endforeach
                    </nav>

                    <div class="onboarding-rail-foot">
                        <span class="onboarding-rail-foot-icon" aria-hidden="true">
                            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" /><path d="M9.5 9a2.6 2.6 0 0 1 5.1.7c0 1.8-2.6 2-2.6 3.8M12 17h.01" /></svg>
                        </span>
                        <div class="onboarding-rail-foot-copy">
                            <strong>Need a hand?</strong>
                            <a href="{{ route('profile.edit') }}">Back to your account <svg class="size-[13px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" /></svg></a>
                        </div>
                    </div>
                </aside>

                <main id="main-content" tabindex="-1" class="onboarding-panel" aria-labelledby="step-title">
                    @if (session('status'))
                        <p role="status" class="mb-4 inline-flex items-center gap-2 rounded-full bg-success/10 px-3 py-1 text-xs font-semibold text-success"><span class="size-1.5 rounded-full bg-success" aria-hidden="true"></span>{{ session('status') }}</p>
                    @endif
                    @if ($errors->any())
                        <div role="alert" class="mb-5 rounded-md border border-danger/20 bg-danger/10 p-4 text-sm text-danger">
                            <p class="font-semibold">Please correct these fields.</p>
                            <ul class="mt-2 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                        </div>
                    @endif
                    @if ($application?->status === 'more_information_required')
                        <p role="status" class="mb-5 rounded-md border border-warning/20 bg-warning/10 p-4 text-sm text-warning-text">More information required: {{ rtrim((string) $application->review_note, '.') }}. Update the requested steps, then resubmit.</p>
                    @endif

                    <header class="onboarding-step-head">
                        <p class="onboarding-kicker">{{ $steps[$step]['phase'] }} · Step {{ $step }}</p>
                        <h2 id="step-title">{{ $steps[$step]['title'] }}</h2>
                        <p class="onboarding-intro">{{ $steps[$step]['intro'] }}</p>
                    </header>

                    @if ($step < 7)
                        <form id="seller-step-form" x-ref="stepForm" method="POST" enctype="multipart/form-data" action="{{ route('seller-application.step', $step) }}" @submit.prevent="save($event)">@csrf
                            <div x-cloak x-show="validationErrors.length || uploadError || exitBlocked" x-ref="formErrors" tabindex="-1" role="alert" class="mb-5 rounded-md border border-danger/20 bg-danger/10 p-4 text-sm text-danger"><p x-text="uploadError"></p><template x-for="error in validationErrors"><p x-text="error"></p></template><p x-show="exitBlocked" class="mt-2">This step isn’t complete, so it can’t be saved yet. Finish it, or <a href="{{ route('profile.edit') }}" class="font-semibold underline underline-offset-2">leave without saving this step</a>. Your earlier steps are already saved.</p></div>

                            @if ($step === 1)
                                <div role="radiogroup" aria-label="Seller type">
                                    <div class="onboarding-choice-grid">
                                        @foreach (['individual' => ['Individual seller', 'You sell under your own name.', ['One government ID', 'A selfie holding your ID']], 'business' => ['Registered business', 'You sell as a DTI- or SEC-registered company.', ['DTI or SEC certificate', 'BIR certificate, if you have one']]] as $option => [$title, $description, $needs])
                                            <label class="onboarding-choice">
                                                <input type="radio" name="seller_type" value="{{ $option }}" @checked(old('seller_type', $application?->seller_type ?? 'individual') === $option) required>
                                                <span class="onboarding-choice-icon" aria-hidden="true">
                                                    @if ($option === 'individual')
                                                        <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4" /><path d="M4.5 21a7.5 7.5 0 0 1 15 0" /></svg>
                                                    @else
                                                        <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="14" rx="2" /><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 13h18" /></svg>
                                                    @endif
                                                </span>
                                                <span>
                                                    <span class="onboarding-choice-title">{{ $title }}</span>
                                                    <span class="onboarding-choice-desc">{{ $description }}</span>
                                                    <span class="onboarding-choice-needs">
                                                        @foreach ($needs as $need)
                                                            <span><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.5 4.2 4.2L19 7" /></svg>{{ $need }}</span>
                                                        @endforeach
                                                    </span>
                                                </span>
                                                <span class="onboarding-choice-radio" aria-hidden="true"></span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="onboarding-note">
                                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 11v5m0-8h.01" /></svg>
                                    <p><strong>Have your ID and payout details ready.</strong> You’ll add them in steps 5 and 6.</p>
                                </div>
                            @elseif ($step === 2)
                                <div class="onboarding-identity">
                                    <span class="onboarding-identity-icon" aria-hidden="true">
                                        <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4" /><path d="M4.5 21a7.5 7.5 0 0 1 15 0" /></svg>
                                    </span>
                                    <span class="onboarding-identity-copy">Signed in as<strong>{{ auth()->user()->email }}</strong></span>
                                    @if (auth()->user()->hasVerifiedEmail())<span class="onboarding-pill">Verified</span>@endif
                                </div>
                                <div class="onboarding-grid">
                                    @include('seller-applications.field', ['name' => 'seller_name', 'label' => 'Full legal name', 'required' => true, 'class' => 'onboarding-span-2'])
                                    @include('seller-applications.field', ['name' => 'phone', 'label' => 'Mobile number', 'required' => true, 'type' => 'tel', 'inputmode' => 'tel', 'placeholder' => '+63 917 555 0142'])
                                    @if ($isBusiness)
                                        @include('seller-applications.field', ['name' => 'representative_name', 'label' => 'Authorized representative', 'required' => true])
                                        @include('seller-applications.field', ['name' => 'business_name', 'label' => 'Registered business name', 'required' => true, 'class' => 'onboarding-span-2'])
                                        @include('seller-applications.field', ['name' => 'registration_number', 'label' => 'DTI or SEC registration number', 'required' => true])
                                        @include('seller-applications.field', ['name' => 'bir_details', 'label' => 'BIR registration details, if applicable'])
                                    @else
                                        @include('seller-applications.field', ['name' => 'date_of_birth', 'label' => 'Date of birth', 'type' => 'date', 'required' => true])
                                    @endif
                                </div>
                                <div class="onboarding-note">
                                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3" /></svg>
                                    <p>Your legal name and date of birth are private. Buyers only see your store name and the contact details you choose to publish.</p>
                                </div>
                            @elseif ($step === 3)
                                <div x-data="{ store_name: @js(old('store_name', $application?->store_name)), store_slug: @js(old('store_slug', $application?->store_slug)), store_description: @js(old('store_description', $application?->store_description)), store_category: @js(old('store_category', $application?->store_category)), logoPreview: null, bannerPreview: null, slugPreview() { return (this.store_slug || '').toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') } }" @file-preview="if ($event.detail.name === 'logo') logoPreview = $event.detail.url; if ($event.detail.name === 'banner') bannerPreview = $event.detail.url" @input="if ($event.target.name in $data) $data[$event.target.name] = $event.target.value">
                                    <div class="space-y-7">
                                        <section class="onboarding-section" aria-labelledby="store-details-heading">
                                            <div class="onboarding-section-head">
                                                <h3 id="store-details-heading">Store details</h3>
                                                <p>Keep it clear and specific — buyers scan these first.</p>
                                            </div>
                                            <div class="onboarding-grid">
                                                @include('seller-applications.field', ['name' => 'store_name', 'label' => 'Store name', 'required' => true, 'class' => 'onboarding-span-2'])
                                                @include('seller-applications.field', ['name' => 'store_slug', 'label' => 'Store link', 'required' => true, 'prefix' => '/stores/', 'help' => 'Lowercase letters, numbers and hyphens.', 'class' => 'onboarding-span-2'])
                                                @include('seller-applications.field', ['name' => 'store_description', 'label' => 'Store description', 'textarea' => true, 'required' => true, 'maxlength' => 2000, 'class' => 'onboarding-span-2'])
                                                @include('seller-applications.field', ['name' => 'store_category', 'label' => 'Primary product category', 'required' => true, 'placeholder' => 'Home and living'])
                                                @include('seller-applications.field', ['name' => 'support_contact', 'label' => 'Customer support contact', 'required' => true])
                                            </div>
                                        </section>

                                        <section class="onboarding-section" aria-labelledby="branding-heading">
                                            <div class="onboarding-section-head">
                                                <h3 id="branding-heading">Branding</h3>
                                                <p>A square logo and a wide banner look best on every screen.</p>
                                            </div>
                                            <div class="onboarding-branding">
                                                <div class="onboarding-upload-stack">
                                                    @include('seller-applications.field', ['name' => 'logo', 'label' => 'Store logo', 'type' => 'file', 'accept' => 'image/jpeg,image/png,image/webp', 'saved' => filled($application?->logo_path), 'help' => 'JPG, PNG or WebP · up to 2 MB'])
                                                    @include('seller-applications.field', ['name' => 'banner', 'label' => 'Store banner (optional)', 'type' => 'file', 'accept' => 'image/jpeg,image/png,image/webp', 'saved' => filled($application?->banner_path), 'help' => 'JPG, PNG or WebP · up to 4 MB'])
                                                </div>
                                                <div aria-hidden="true">
                                                    <p class="onboarding-preview-label">Buyer preview</p>
                                                    <div class="onboarding-preview-card">
                                                        <div class="onboarding-preview-banner" :style="bannerPreview ? { backgroundImage: 'url(' + bannerPreview + ')' } : {}" @if ($application?->banner_path) style="background-image: url('{{ Storage::disk('public')->url($application->banner_path) }}')" @endif></div>
                                                        <div class="onboarding-preview-body">
                                                            <div class="onboarding-preview-logo">
                                                                <img x-cloak x-show="logoPreview" :src="logoPreview" alt="">
                                                                <span x-show="!logoPreview" class="contents">@if ($application?->logo_path)<img src="{{ Storage::disk('public')->url($application->logo_path) }}" alt="">@else<span x-text="(store_name || 'S').trim().charAt(0).toUpperCase()"></span>@endif</span>
                                                            </div>
                                                            <p class="onboarding-preview-name" x-text="store_name || 'Your store name'"></p>
                                                            <p class="onboarding-preview-slug">/stores/<span x-text="slugPreview() || 'your-store'"></span></p>
                                                            <p class="onboarding-preview-desc" x-text="store_description || 'Your store description appears here.'"></p>
                                                            <span class="onboarding-chip" x-show="store_category" x-text="store_category"></span>
                                                        </div>
                                                    </div>
                                                    <p class="mt-2 text-center text-[11px] text-muted">How buyers will see your store</p>
                                                </div>
                                            </div>
                                        </section>
                                    </div>
                                </div>
                            @elseif ($step === 4)
                                <section class="onboarding-section" aria-labelledby="address-heading" x-data="{ sameAddresses: @js($sameAddresses) }">
                                    <div class="onboarding-section-head">
                                        <h3 id="address-heading">Pickup address</h3>
                                        <p>We’ll use this as your default pickup and return address.</p>
                                    </div>
                                    <div class="onboarding-grid">
                                        @foreach (['region' => 'Region', 'province' => 'Province', 'city' => 'City or municipality', 'barangay' => 'Barangay', 'address_line' => 'Street address', 'postal_code' => 'Postal code'] as $name => $label)
                                            @include('seller-applications.field', ['name' => $name, 'label' => $label, 'required' => true, 'class' => $name === 'address_line' ? 'onboarding-span-2' : null, 'inputmode' => $name === 'postal_code' ? 'numeric' : null])
                                        @endforeach
                                    </div>
                                    <label class="onboarding-check-strip">
                                        <input type="checkbox" name="same_addresses" value="1" x-model="sameAddresses" @checked($sameAddresses)>
                                        <span><strong>Use this address for pickup and returns.</strong> Uncheck if your courier collects from, or returns come to, a different place.</span>
                                    </label>
                                    <div x-show="!sameAddresses" class="onboarding-grid mt-[18px]">
                                        @include('seller-applications.field', ['name' => 'pickup_address', 'label' => 'Pickup address', 'requiredWhen' => '!sameAddresses'])
                                        @include('seller-applications.field', ['name' => 'return_address', 'label' => 'Return address', 'requiredWhen' => '!sameAddresses'])
                                    </div>
                                </section>

                                <section class="onboarding-section" aria-labelledby="shipping-heading">
                                    <div class="onboarding-section-head">
                                        <h3 id="shipping-heading">How you ship</h3>
                                        <p>Set expectations buyers can rely on. These can be adjusted anytime.</p>
                                    </div>
                                    <div class="onboarding-grid">
                                        <div class="onboarding-span-2" x-data="{ days: @js((int) old('processing_days', $application?->processing_days ?? 2)) }">
                                            <label for="processing_days" class="field-label">Order processing time</label>
                                            <div class="onboarding-stepper-wrap">
                                                <div class="onboarding-stepper">
                                                    <button type="button" aria-label="Decrease processing days" @click="days = Math.max(1, days - 1)">−</button>
                                                    <input id="processing_days" name="processing_days" type="number" min="1" max="30" required value="{{ old('processing_days', $application?->processing_days ?? 2) }}" x-model.number="days">
                                                    <button type="button" aria-label="Increase processing days" @click="days = Math.min(30, days + 1)">+</button>
                                                </div>
                                                <span class="onboarding-stepper-unit">business days before you hand orders to the courier</span>
                                            </div>
                                            <x-input-error :messages="$errors->get('processing_days')" class="mt-1" />
                                        </div>
                                        @include('seller-applications.field', ['name' => 'fulfillment_methods', 'label' => 'Fulfillment methods', 'required' => true, 'help' => 'For example: courier pickup, drop-off'])
                                        @include('seller-applications.field', ['name' => 'delivery_areas', 'label' => 'Supported delivery areas', 'required' => true, 'help' => 'For example: Metro Manila, Luzon'])
                                    </div>
                                </section>
                            @elseif ($step === 5)
                                <div x-data="{ idType: @js(old('id_type', $application?->id_type ?? '')) }">
                                <section class="onboarding-section" aria-labelledby="id-heading">
                                    <div class="onboarding-section-head">
                                        <h3 id="id-heading">Government ID</h3>
                                        <p>Use an ID that is valid and not expired.</p>
                                    </div>
                                    <div class="onboarding-grid">
                                        <div>
                                            <label for="id_type" class="field-label">ID type</label>
                                            <select id="id_type" name="id_type" required x-model="idType" class="field-control">
                                                <option value="" disabled @selected(! old('id_type', $application?->id_type))>Choose an ID type</option>
                                                @foreach ($idTypes as $option => $label)
                                                    <option value="{{ $option }}" @selected(old('id_type', $application?->id_type) === $option)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <x-input-error :messages="$errors->get('id_type')" class="mt-1" />
                                        </div>
                                        @include('seller-applications.field', ['name' => 'id_number', 'label' => 'ID number', 'required' => true])
                                    </div>
                                </section>

                                <section class="onboarding-section" aria-labelledby="documents-heading">
                                    <div class="onboarding-section-head">
                                        <h3 id="documents-heading">Documents</h3>
                                        <p>Clear photos with all four corners visible. JPEG, PNG or PDF, up to 5 MB each.</p>
                                    </div>
                                    <div class="onboarding-upload-stack">
                                        @php($savedDocuments = $application?->documents()->pluck('type')->all() ?? [])
                                        @foreach (['id_front' => 'Front of ID', 'id_back' => 'Back of ID, if applicable', 'selfie' => 'Identity selfie', 'business_registration' => 'Business registration document', 'bir_certificate' => 'BIR certificate, if applicable'] as $name => $label)
                                            @if (! in_array($name, ['business_registration', 'bir_certificate'], true) || $isBusiness)
                                                @include('seller-applications.field', ['name' => $name, 'label' => $label, 'type' => 'file', 'accept' => $name === 'selfie' ? 'image/jpeg,image/png' : 'image/jpeg,image/png,application/pdf', 'saved' => in_array($name, $savedDocuments, true), 'required' => $name === 'id_front' || ($name === 'selfie' && ! $isBusiness) || ($name === 'business_registration' && $isBusiness), 'requiredWhen' => $name === 'id_back' ? "['drivers_license', 'national_id'].includes(idType)" : null, 'help' => ['selfie' => $isBusiness ? 'Optional for businesses' : 'A photo of you holding your ID', 'id_back' => 'Needed for driver’s license and National ID'][$name] ?? null])
                                            @endif
                                        @endforeach
                                    </div>
                                </section>
                                </div>

                                <div class="onboarding-note">
                                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3" /></svg>
                                    <p><strong>Your documents stay private.</strong> They’re visible only to authorized reviewers, and never shared with buyers or other sellers.</p>
                                </div>
                            @elseif ($step === 6)
                                <div role="radiogroup" aria-label="Payout method">
                                    <div class="onboarding-choice-grid">
                                        @foreach (['bank' => ['Bank account', 'BDO, BPI, UnionBank and more'], 'wallet' => ['E-wallet', 'GCash or Maya']] as $option => [$title, $description])
                                            <label class="onboarding-choice onboarding-choice--compact">
                                                <input type="radio" name="method" value="{{ $option }}" @checked(old('method', $application?->payoutProfile?->method ?? 'bank') === $option) required>
                                                <span class="onboarding-choice-icon" aria-hidden="true">
                                                    @if ($option === 'wallet')
                                                        <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="5" y="3" width="14" height="18" rx="3" /><path d="M11 18h2" /></svg>
                                                    @else
                                                        <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="m3 9 9-5 9 5M5 9v8m4-8v8m6-8v8m4-8v8M3 20h18" /></svg>
                                                    @endif
                                                </span>
                                                <span>
                                                    <span class="onboarding-choice-title">{{ $title }}</span>
                                                    <span class="onboarding-choice-desc">{{ $description }}</span>
                                                </span>
                                                <span class="onboarding-choice-radio" aria-hidden="true"></span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                                <x-input-error :messages="$errors->get('method')" class="mt-1" />

                                <div class="onboarding-grid mt-[22px]">
                                    @include('seller-applications.field', ['name' => 'provider', 'label' => 'Bank or wallet provider', 'required' => true, 'value' => $application?->payoutProfile?->provider, 'placeholder' => 'Bank or e-wallet name'])
                                    @include('seller-applications.field', ['name' => 'holder_name', 'label' => 'Account holder name', 'required' => true, 'value' => $application?->payoutProfile?->holder_name])
                                    @include('seller-applications.field', ['name' => 'account_number', 'label' => 'Account or wallet number', 'value' => '', 'inputmode' => 'numeric', 'class' => 'onboarding-span-2', 'help' => $application?->payoutProfile ? 'Saved number: '.$application->payoutProfile->maskedAccountNumber().'. Leave blank to keep it.' : ''])
                                </div>

                                <div class="onboarding-note">
                                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 11v5m0-8h.01" /></svg>
                                    <p><strong>The name must match.</strong> Accounts under a different name take longer to verify. For business sellers, use the registered company name.</p>
                                </div>
                            @endif

                            <div x-cloak x-show="saving && uploadProgress > 0" role="status" class="mt-4 text-sm text-muted"><label for="upload-progress" class="font-semibold">Upload progress: <span x-text="uploadProgress"></span>%</label><progress id="upload-progress" :value="uploadProgress" max="100" class="mt-2 block w-full"></progress></div>

                            <div class="onboarding-actions">
                                <p class="onboarding-save-hint">Your progress saves when you continue.</p>
                                <div class="onboarding-action-buttons">
                                    @if ($step > 1)
                                        <a href="{{ route('seller-application.create', ['step' => $step - 1]) }}" class="onboarding-button onboarding-button--quiet">Back</a>
                                    @endif
                                    <button type="submit" :disabled="saving" class="onboarding-button onboarding-button--primary">
                                        <span x-show="!saving">Save and continue</span>
                                        <span x-cloak x-show="saving">Saving…</span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    @else
                        @if ($missingSteps)
                            <div role="status" class="mb-5 rounded-md border border-warning/20 bg-warning/10 p-4 text-sm text-warning-text">
                                <p class="font-semibold">Finish these steps before you submit:</p>
                                <ul class="mt-2 list-disc pl-5">
                                    @foreach ($missingSteps as $number)
                                        <li>@if ($canEditStep($number))<a href="{{ route('seller-application.create', ['step' => $number]) }}" class="font-semibold underline underline-offset-2">Step {{ $number }}: {{ $steps[$number]['label'] }}</a>@else Step {{ $number }}: {{ $steps[$number]['label'] }}@endif</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        {{-- Only the review reads payout and document details, so other steps skip those queries. --}}
                        @php($payout = $application?->payoutProfile)
                        @php($documentCount = $application?->documents()->count() ?? 0)
                        <div class="onboarding-review">
                            <section class="onboarding-review-block" aria-labelledby="review-about">
                                <div class="onboarding-review-head">
                                    <h3 id="review-about">About you</h3>
                                    @if ($canEditStep(2))<a href="{{ route('seller-application.create', ['step' => 2]) }}" class="onboarding-edit"><svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4l10.5-10.5a2.1 2.1 0 0 0-3-3L5 17v3Z" /><path d="m13.5 6.5 3 3" /></svg>Edit</a>@endif
                                </div>
                                <dl class="onboarding-review-grid">
                                    <div><dt>Seller type</dt><dd>{{ $application?->seller_type ? ucfirst($application->seller_type) : 'Not completed' }}</dd></div>
                                    <div><dt>Full legal name</dt><dd>{{ $application?->seller_name ?: 'Not completed' }}</dd></div>
                                    <div><dt>Mobile number</dt><dd>{{ $application?->phone ?: 'Not completed' }}</dd></div>
                                    <div><dt>Email</dt><dd>{{ auth()->user()->email }}</dd></div>
                                    @if ($isBusiness)
                                        <div><dt>Business name</dt><dd>{{ $application->business_name }}</dd></div>
                                        <div><dt>Registration no.</dt><dd>{{ $application->registration_number }}</dd></div>
                                    @endif
                                </dl>
                            </section>

                            <section class="onboarding-review-block" aria-labelledby="review-store">
                                <div class="onboarding-review-head">
                                    <h3 id="review-store">Storefront</h3>
                                    @if ($canEditStep(3))<a href="{{ route('seller-application.create', ['step' => 3]) }}" class="onboarding-edit"><svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4l10.5-10.5a2.1 2.1 0 0 0-3-3L5 17v3Z" /><path d="m13.5 6.5 3 3" /></svg>Edit</a>@endif
                                </div>
                                <dl class="onboarding-review-grid">
                                    <div><dt>Store name</dt><dd>{{ $application?->store_name ?: 'Not completed' }}</dd></div>
                                    <div><dt>Store link</dt><dd>{{ $application?->store_slug ? '/stores/'.$application->store_slug : 'Not completed' }}</dd></div>
                                    <div><dt>Primary category</dt><dd>{{ $application?->store_category ?: 'Not completed' }}</dd></div>
                                    <div><dt>Support contact</dt><dd>{{ $application?->support_contact ?: 'Not completed' }}</dd></div>
                                </dl>
                            </section>

                            <section class="onboarding-review-block" aria-labelledby="review-shipping">
                                <div class="onboarding-review-head">
                                    <h3 id="review-shipping">Shipping</h3>
                                    @if ($canEditStep(4))<a href="{{ route('seller-application.create', ['step' => 4]) }}" class="onboarding-edit"><svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4l10.5-10.5a2.1 2.1 0 0 0-3-3L5 17v3Z" /><path d="m13.5 6.5 3 3" /></svg>Edit</a>@endif
                                </div>
                                <dl class="onboarding-review-grid">
                                    <div><dt>Ships from</dt><dd>{{ $shippingAddress ?: 'Not completed' }}</dd></div>
                                    <div><dt>Processing time</dt><dd>{{ $application?->processing_days ? $application->processing_days.' business days' : 'Not completed' }}</dd></div>
                                    <div><dt>Delivery areas</dt><dd>{{ $application?->delivery_areas ?: 'Not completed' }}</dd></div>
                                    <div><dt>Fulfillment</dt><dd>{{ $application?->fulfillment_methods ?: 'Not completed' }}</dd></div>
                                </dl>
                            </section>

                            <section class="onboarding-review-block" aria-labelledby="review-verify">
                                <div class="onboarding-review-head">
                                    <h3 id="review-verify">Verification &amp; payout</h3>
                                    <div class="onboarding-review-actions">
                                        @if ($canEditStep(5))<a href="{{ route('seller-application.create', ['step' => 5]) }}" class="onboarding-edit"><svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4l10.5-10.5a2.1 2.1 0 0 0-3-3L5 17v3Z" /><path d="m13.5 6.5 3 3" /></svg>Documents</a>@endif
                                        @if ($canEditStep(6))<a href="{{ route('seller-application.create', ['step' => 6]) }}" class="onboarding-edit"><svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4l10.5-10.5a2.1 2.1 0 0 0-3-3L5 17v3Z" /><path d="m13.5 6.5 3 3" /></svg>Payout</a>@endif
                                    </div>
                                </div>
                                <dl class="onboarding-review-grid">
                                    <div><dt>Government ID</dt><dd>{{ $application?->id_type ? ($idTypes[$application->id_type] ?? ucfirst(str_replace('_', ' ', $application->id_type))) : 'Not completed' }}</dd></div>
                                    <div><dt>Payout method</dt><dd>{{ $payout ? ucfirst($payout->method).' · '.$payout->maskedAccountNumber() : 'Not completed' }}</dd></div>
                                    <div><dt>Documents</dt><dd>{{ $documentCount }} uploaded</dd></div>
                                    <div><dt>Account holder</dt><dd>{{ $payout?->holder_name ?: 'Not completed' }}</dd></div>
                                </dl>
                            </section>
                        </div>

                        <form method="POST" action="{{ route('seller-application.store') }}" x-data="{ accepted: 0, total: 6, sync() { this.accepted = this.$root.querySelectorAll('[data-policy]:checked').length } }" @change="sync()">@csrf
                            <section class="onboarding-policies" aria-labelledby="policies-heading">
                                <div class="onboarding-policies-head">
                                    <div>
                                        <h3 id="policies-heading">Seller policies</h3>
                                        <p>You must accept each one to submit your application.</p>
                                    </div>
                                    <span class="onboarding-count-pill" x-text="`${accepted} of ${total}`" aria-live="polite">0 of 6</span>
                                </div>

                                <label class="onboarding-policy-all">
                                    <input type="checkbox" :checked="accepted === total" :indeterminate="accepted > 0 && accepted < total" @change="$root.querySelectorAll('[data-policy]').forEach((box) => box.checked = $event.target.checked)">
                                    <span>Accept all six policies</span>
                                </label>

                                <div class="onboarding-policy-list">
                                    @foreach (['seller_agreement' => 'Seller Agreement', 'terms' => 'Terms and Conditions', 'privacy' => 'Privacy Policy', 'prohibited_products' => 'Prohibited Products Policy', 'returns_refunds' => 'Returns and Refunds Policy', 'fees' => 'Marketplace Fee Policy'] as $key => $label)
                                        <div @class(['onboarding-policy', 'onboarding-policy--fees' => $key === 'fees'])>
                                            <input id="policy-{{ $key }}" type="checkbox" name="agreements[{{ $key }}]" value="1" required data-policy>
                                            <label for="policy-{{ $key }}">I accept the <strong>{{ $label }}</strong> (version {{ $key === 'fees' ? config('marketplace.platform_fee.policy_version') : '1.0' }}).</label>
                                            @if (in_array($key, ['terms', 'privacy', 'returns_refunds'], true))
                                                <a href="{{ route(['terms' => 'terms', 'privacy' => 'privacy-policy', 'returns_refunds' => 'returns'][$key]) }}" target="_blank" rel="noopener">Read<span class="sr-only"> {{ $label }}</span></a>
                                            @endif
                                            @if ($key === 'fees')
                                                @include('partials.platform-fee-terms', ['class' => 'mt-2 pl-[25px]'])
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </section>

                            <div class="onboarding-actions">
                                <p class="onboarding-save-hint" x-text="@js((bool) $missingSteps) ? 'Finish every step to submit.' : accepted === total ? 'Ready to submit.' : `${accepted} of ${total} policies accepted.`">0 of 6 policies accepted.</p>
                                <div class="onboarding-action-buttons">
                                    <a href="{{ route('seller-application.create', ['step' => 6]) }}" class="onboarding-button onboarding-button--quiet">Back</a>
                                    <button type="submit" class="onboarding-button onboarding-button--primary" @disabled($missingSteps)>Submit application</button>
                                </div>
                            </div>
                        </form>
                    @endif
                </main>
            </div>
        @endif
    </div>
</x-app-layout>
