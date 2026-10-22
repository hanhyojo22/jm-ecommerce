<x-app-layout>
    <x-slot name="header">
        <div class="mx-auto max-w-4xl">
            <a href="{{ route('profile.edit') }}" class="text-sm font-semibold text-brand">Account</a>
            <h1 class="mt-2 text-2xl font-semibold text-ink">Become a seller</h1>
            <p class="mt-1 text-sm text-muted">Apply with your existing account. You can keep shopping throughout review.</p>
        </div>
    </x-slot>
    @php
        $labels = [1 => 'Seller type', 2 => 'Your information', 3 => 'Store', 4 => 'Address and fulfillment', 5 => 'Verification', 6 => 'Payout', 7 => 'Review and submit'];
        $step = min(7, max(1, (int) request('step', 1)));
        $completed = $application?->completed_steps ?? [];
        $editable = ! $workspaceUnavailable && (! $application || in_array($application->status, ['draft', 'more_information_required'], true));
        if ($application?->status === 'more_information_required' && $step <= 6 && ! in_array($step, $application->correction_steps ?? [], true)) { $step = 7; }
    @endphp
    <div class="page-shell bg-canvas"><div class="mx-auto max-w-4xl px-[18px] sm:px-8">
        @if (session('status'))<p role="status" class="mb-5 rounded-xl border border-success/20 bg-success/10 p-4 text-sm text-success">{{ session('status') }}</p>@endif
        @if ($errors->any())<div role="alert" class="mb-5 rounded-xl border border-danger/20 bg-danger/10 p-4 text-sm text-danger"><p class="font-semibold">Please correct these fields.</p><ul class="mt-2 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @if ($workspaceUnavailable)
            <section class="rounded-xl border border-line bg-white p-6">
                <h2 class="text-lg font-semibold">Your seller workspace is unavailable</h2>
                @if ($workspaceSuspended)
                    <p class="mt-2 text-sm text-muted">Your store is currently suspended. Shopping is unaffected.</p>
                    @if ($suspensionReason)<p class="mt-2 text-sm">Reason: {{ $suspensionReason }}</p>@endif
                    @if ($suspensionAppeal?->status === 'pending')<p class="mt-3 text-sm">Your appeal is awaiting review.</p>@endif
                    @if ($suspensionAppeal?->status === 'rejected')<p class="mt-3 text-sm">Your previous appeal was not approved. {{ $suspensionAppeal->review_note }}</p>@endif
                    @if ($canSubmitSuspensionAppeal)
                        <form method="POST" action="{{ route('seller.suspension-appeals.store') }}" class="mt-5 space-y-3">@csrf
                            <label for="appeal" class="block text-sm font-semibold">Appeal statement</label>
                            <textarea id="appeal" name="statement" required minlength="20" maxlength="5000" rows="5" class="w-full rounded-lg border-line focus:border-brand focus:ring-brand">{{ old('statement') }}</textarea>
                            <x-primary-button>Submit an appeal</x-primary-button>
                        </form>
                    @endif
                @else
                    <p class="mt-2 text-sm text-muted">Contact JMMart support for help with this store.</p>
                @endif
            </section>
        @elseif (! $editable)
            <section class="rounded-xl border border-line bg-white p-6">
                <h2 class="text-lg font-semibold">Application {{ str_replace('_', ' ', $application->status) }}</h2>
                <p class="mt-2 text-sm text-muted">Store: {{ $application->store_name }}. You can continue shopping while we review your application.</p>
                @if ($application->review_note)<p class="mt-3 rounded-lg bg-canvas p-3 text-sm">{{ $application->review_note }}</p>@endif
                <ol class="mt-5 space-y-2 border-l-2 border-line pl-4 text-sm" aria-label="Application status timeline">
                    @foreach ($application->statusHistory()->oldest()->get() as $event)<li><span class="font-semibold">{{ str_replace('_', ' ', ucfirst($event->to_status)) }}</span> · {{ $event->created_at->format('M j, Y g:i A') }}</li>@endforeach
                </ol>
                <a href="{{ route('catalog.index') }}" class="mt-5 inline-flex min-h-11 items-center rounded-lg border border-line px-4 text-sm font-semibold">Continue shopping</a>
            </section>
        @else
            @if ($application?->status === 'more_information_required')<p role="status" class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">More information required: {{ $application->review_note }}. Update the requested steps, then resubmit.</p>@endif
            <nav aria-label="Application progress" class="mb-6 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7">
                @foreach ($labels as $number => $label)
                    @php($allowed = $application?->status !== 'more_information_required' || $number === 7 || in_array($number, $application->correction_steps ?? [], true))
                    <a href="{{ $allowed ? route('seller-application.create', ['step' => $number]) : '#' }}" @if ($number === $step) aria-current="step" @endif class="rounded-lg border px-3 py-2 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-brand {{ $number === $step ? 'border-brand bg-brand text-white' : (in_array($number, $completed) ? 'border-success/30 bg-success/10 text-success' : 'border-line bg-white text-muted') }}"><span class="block text-[10px] uppercase">Step {{ $number }} @if (in_array($number, $completed)) · Done @endif</span>{{ $label }}</a>
                @endforeach
            </nav>
            <section class="rounded-xl border border-line bg-white p-5 shadow-sm sm:p-8" aria-labelledby="step-title">
                <h2 id="step-title" class="text-xl font-semibold text-ink">{{ $labels[$step] }}</h2>
                <p class="mt-1 text-sm text-muted">{{ $step === 7 ? 'Check your details and accept each policy before submitting.' : 'Save this step to continue. Your draft will be here when you return.' }}</p>
                @if ($step < 7)
                    <form method="POST" enctype="multipart/form-data" action="{{ route('seller-application.step', $step) }}" class="mt-6 space-y-5" x-data="{ saving: false, sameAddresses: false, uploadProgress: 0, uploadError: '', validationErrors: [], save(event) { this.saving = true; this.uploadError = ''; this.validationErrors = []; const request = new XMLHttpRequest(); request.open('POST', event.target.action); request.setRequestHeader('Accept', 'application/json'); request.upload.onprogress = (progress) => { if (progress.lengthComputable) this.uploadProgress = Math.round(progress.loaded / progress.total * 100) }; request.onload = () => { if (request.status === 200) { window.location.assign(JSON.parse(request.responseText).redirect); return; } this.saving = false; if (request.status === 422) { this.validationErrors = Object.values(JSON.parse(request.responseText).errors).flat(); this.$nextTick(() => this.$refs.formErrors?.focus()); } else { this.uploadError = 'Could not save this step. Please try again.'; } }; request.onerror = () => { this.saving = false; this.uploadError = 'Upload failed. Please check your connection and retry.'; }; request.send(new FormData(event.target)); } }" @submit.prevent="save($event)">@csrf
                        <div x-cloak x-show="validationErrors.length || uploadError" x-ref="formErrors" tabindex="-1" role="alert" class="rounded-lg border border-danger/20 bg-danger/10 p-4 text-sm text-danger"><p x-text="uploadError"></p><template x-for="error in validationErrors"><p x-text="error"></p></template></div>
                        @if ($step === 1)
                            <fieldset class="space-y-3"><legend class="text-sm font-semibold">How will you sell?</legend>
                                @foreach (['individual' => 'Individual seller', 'business' => 'Registered business'] as $value => $label)
                                    <label class="flex min-h-12 items-center gap-3 rounded-lg border border-line px-4 text-sm"><input type="radio" name="seller_type" value="{{ $value }}" @checked(old('seller_type', $application?->seller_type ?? 'individual') === $value) required> {{ $label }}</label>
                                @endforeach
                            </fieldset>
                        @elseif ($step === 2)
                            <p class="rounded-lg bg-canvas p-3 text-sm">Verified email: {{ auth()->user()->email }}</p>
                            @include('seller-applications.field', ['name' => 'seller_name', 'label' => 'Full legal name', 'required' => true])
                            @include('seller-applications.field', ['name' => 'phone', 'label' => 'Mobile number', 'required' => true, 'type' => 'tel'])
                            @if ($application?->seller_type === 'business')
                                @include('seller-applications.field', ['name' => 'business_name', 'label' => 'Registered business name', 'required' => true])
                                @include('seller-applications.field', ['name' => 'registration_number', 'label' => 'DTI or SEC registration number', 'required' => true])
                                @include('seller-applications.field', ['name' => 'bir_details', 'label' => 'BIR registration details, if applicable'])
                                @include('seller-applications.field', ['name' => 'representative_name', 'label' => 'Authorized representative', 'required' => true])
                            @else
                                @include('seller-applications.field', ['name' => 'date_of_birth', 'label' => 'Date of birth', 'type' => 'date', 'required' => true])
                            @endif
                        @elseif ($step === 3)
                            @include('seller-applications.field', ['name' => 'store_name', 'label' => 'Store name', 'required' => true])
                            @include('seller-applications.field', ['name' => 'store_slug', 'label' => 'Storefront URL slug', 'required' => true, 'help' => 'Lowercase letters, numbers and hyphens.'])
                            @include('seller-applications.field', ['name' => 'store_description', 'label' => 'Store description', 'textarea' => true, 'required' => true])
                            @include('seller-applications.field', ['name' => 'store_category', 'label' => 'Primary product category', 'required' => true])
                            @include('seller-applications.field', ['name' => 'support_contact', 'label' => 'Customer support contact', 'required' => true])
                            @include('seller-applications.field', ['name' => 'logo', 'label' => 'Store logo', 'type' => 'file', 'accept' => 'image/jpeg,image/png,image/webp'])
                            @if ($application?->logo_path)<p class="text-xs text-success">Logo saved.</p>@endif
                            @include('seller-applications.field', ['name' => 'banner', 'label' => 'Store banner (optional)', 'type' => 'file', 'accept' => 'image/jpeg,image/png,image/webp'])
                        @elseif ($step === 4)
                            <p class="text-sm text-muted">Use a Philippine residential address. Enter pickup and return addresses separately if they differ.</p>
                            <label class="flex gap-2 text-sm"><input type="checkbox" name="same_addresses" value="1" x-model="sameAddresses"> Use residential address for pickup and returns</label>
                            @foreach (['region' => 'Region', 'province' => 'Province', 'city' => 'City or municipality', 'barangay' => 'Barangay', 'address_line' => 'Street address', 'postal_code' => 'Postal code', 'processing_days' => 'Order processing days', 'fulfillment_methods' => 'Fulfillment methods', 'delivery_areas' => 'Supported delivery areas'] as $name => $label)
                                @include('seller-applications.field', ['name' => $name, 'label' => $label, 'required' => true, 'type' => $name === 'processing_days' ? 'number' : 'text'])
                            @endforeach
                            <div x-show="!sameAddresses">@include('seller-applications.field', ['name' => 'pickup_address', 'label' => 'Pickup address'])</div>
                            <div x-show="!sameAddresses">@include('seller-applications.field', ['name' => 'return_address', 'label' => 'Return address'])</div>
                        @elseif ($step === 5)
                            <p class="text-sm text-muted">Documents are stored privately and can only be viewed by authorized administrators. JPEG, PNG, or PDF, up to 5 MB each.</p>
                            <div><label for="id_type" class="block text-sm font-semibold">Government ID type</label><select id="id_type" name="id_type" required class="mt-1 w-full rounded-lg border-line focus:border-brand focus:ring-brand">@foreach (['passport' => 'Passport', 'drivers_license' => 'Driver’s license', 'national_id' => 'National ID', 'other' => 'Other government ID'] as $value => $label)<option value="{{ $value }}" @selected(old('id_type', $application?->id_type) === $value)>{{ $label }}</option>@endforeach</select></div>
                            @include('seller-applications.field', ['name' => 'id_number', 'label' => 'ID number', 'required' => true])
                            @foreach (['id_front' => 'Front of ID', 'id_back' => 'Back of ID, if applicable', 'selfie' => 'Identity selfie', 'business_registration' => 'Business registration document', 'bir_certificate' => 'BIR certificate, if applicable'] as $name => $label)
                                @if (! in_array($name, ['business_registration', 'bir_certificate'], true) || $application?->seller_type === 'business')
                                    @include('seller-applications.field', ['name' => $name, 'label' => $label, 'type' => 'file', 'accept' => $name === 'selfie' ? 'image/jpeg,image/png' : 'image/jpeg,image/png,application/pdf'])
                                    @if ($application?->documents()->where('type', $name)->exists())<p class="text-xs text-success">{{ $label }} saved.</p>@endif
                                @endif
                            @endforeach
                        @elseif ($step === 6)
                            <div><label for="method" class="block text-sm font-semibold">Payout method</label><select id="method" name="method" required class="mt-1 w-full rounded-lg border-line focus:border-brand focus:ring-brand"><option value="bank" @selected(old('method', $application?->payoutProfile?->method) === 'bank')>Bank</option><option value="wallet" @selected(old('method', $application?->payoutProfile?->method) === 'wallet')>Wallet</option></select></div>
                            @include('seller-applications.field', ['name' => 'provider', 'label' => 'Bank or wallet provider', 'required' => true, 'value' => $application?->payoutProfile?->provider])
                            @include('seller-applications.field', ['name' => 'holder_name', 'label' => 'Account holder name', 'required' => true, 'value' => $application?->payoutProfile?->holder_name])
                            @include('seller-applications.field', ['name' => 'account_number', 'label' => 'Account or wallet number', 'value' => '', 'help' => $application?->payoutProfile ? 'Saved number: '.$application->payoutProfile->maskedAccountNumber().'. Leave blank to keep it.' : ''])
                        @endif
                        <div class="flex flex-wrap justify-between gap-3 border-t border-line pt-5">
                            @if ($step > 1)<a href="{{ route('seller-application.create', ['step' => $step - 1]) }}" class="inline-flex min-h-11 items-center rounded-lg border border-line px-4 text-sm font-semibold">Back</a>@endif
                            <button type="submit" :disabled="saving" class="inline-flex min-h-11 items-center rounded-lg bg-brand px-5 text-sm font-semibold text-white hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2 disabled:opacity-60"><span x-show="!saving">Save and continue</span><span x-show="saving" x-cloak>Saving…</span></button>
                        </div>
                        <div x-cloak x-show="saving && uploadProgress > 0" role="status" class="text-sm text-muted"><label for="upload-progress" class="font-semibold">Upload progress: <span x-text="uploadProgress"></span>%</label><progress id="upload-progress" :value="uploadProgress" max="100" class="mt-2 block w-full"></progress></div>
                    </form>
                @else
                    <dl class="mt-6 grid gap-4 rounded-lg bg-canvas p-5 text-sm sm:grid-cols-2">
                        <div><dt class="font-semibold">Seller</dt><dd>{{ $application?->seller_name ?? 'Not completed' }}</dd></div>
                        <div><dt class="font-semibold">Type</dt><dd>{{ ucfirst($application?->seller_type ?? 'Not completed') }}</dd></div>
                        <div><dt class="font-semibold">Store</dt><dd>{{ $application?->store_name ?? 'Not completed' }}</dd></div>
                        <div><dt class="font-semibold">Storefront</dt><dd>{{ $application?->store_slug ?? 'Not completed' }}</dd></div>
                        <div><dt class="font-semibold">Address</dt><dd>{{ collect([$application?->address_line, $application?->barangay, $application?->city, $application?->province])->filter()->implode(', ') ?: 'Not completed' }}</dd></div>
                        <div><dt class="font-semibold">Payout</dt><dd>{{ $application?->payoutProfile?->provider ?? 'Not completed' }} {{ $application?->payoutProfile?->maskedAccountNumber() }}</dd></div>
                    </dl>
                    <form method="POST" action="{{ route('seller-application.store') }}" class="mt-6 space-y-4">@csrf
                        @foreach (['seller_agreement' => 'Seller Agreement', 'terms' => 'Terms and Conditions', 'privacy' => 'Privacy Policy', 'prohibited_products' => 'Prohibited Products Policy', 'returns_refunds' => 'Returns and Refunds Policy', 'fees' => 'Marketplace Fee Policy'] as $key => $label)
                            <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="agreements[{{ $key }}]" value="1" required class="mt-1 rounded border-line text-brand focus:ring-brand"> <span>I accept the {{ $label }} (version {{ $key === 'fees' ? config('marketplace.platform_fee.policy_version') : '1.0' }}).</span></label>
                            @if ($key === 'fees')
                                @include('partials.platform-fee-terms', ['class' => 'ml-6'])
                            @endif
                            @if (in_array($key, ['terms', 'privacy', 'returns_refunds'], true))<a href="{{ route(['terms' => 'terms', 'privacy' => 'privacy-policy', 'returns_refunds' => 'returns'][$key]) }}" target="_blank" rel="noopener" class="ml-6 text-xs font-semibold text-brand underline">Read {{ $label }}</a>@endif
                        @endforeach
                        <button type="submit" class="inline-flex min-h-12 items-center rounded-lg bg-brand px-5 text-sm font-semibold text-white hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Submit application</button>
                    </form>
                @endif
            </section>
        @endif
    </div></div>
</x-app-layout>
