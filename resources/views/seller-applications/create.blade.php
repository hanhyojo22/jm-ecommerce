<x-app-layout>
    @php
        $steps = [
            1 => ['label' => 'Seller type', 'hint' => 'Individual or business', 'phase' => 'About you', 'title' => 'How will you sell?', 'intro' => 'This sets which documents we ask for. Changing it later clears steps 2, 5 and 6.'],
            2 => ['label' => 'Your information', 'hint' => 'Name and contact', 'phase' => 'About you', 'title' => 'Your information', 'intro' => 'Use the name on your government ID. Buyers won’t see it.'],
            3 => ['label' => 'Store', 'hint' => 'Name, link and branding', 'phase' => 'Your store', 'title' => 'Make your storefront', 'intro' => 'This is what buyers see first. You can edit it later in Store settings.'],
            4 => ['label' => 'Address and fulfillment', 'hint' => 'Pickup and shipping', 'phase' => 'Your store', 'title' => 'Address and fulfillment', 'intro' => 'Use a Philippine residential address. Couriers pick up from here and returns come back here.'],
            5 => ['label' => 'Verification', 'hint' => 'ID and documents', 'phase' => 'Verify and get paid', 'title' => 'Verify your identity', 'intro' => 'Only authorized JMMart reviewers can see these. Buyers never do.'],
            6 => ['label' => 'Payout', 'hint' => 'Bank or e-wallet', 'phase' => 'Verify and get paid', 'title' => 'Where should we send your earnings?', 'intro' => 'The account holder name should match your legal or business name.'],
            7 => ['label' => 'Review and submit', 'hint' => 'Check and accept', 'phase' => 'Submit', 'title' => 'Review and submit', 'intro' => 'Check your details and accept each policy. We’ll let you know when review is done.'],
        ];
        $step = min(7, max(1, (int) request('step', 1)));
        $completed = $application?->completed_steps ?? [];
        $editable = ! $workspaceUnavailable && (! $application || in_array($application->status, ['draft', 'more_information_required'], true));
        if ($application?->status === 'more_information_required' && $step <= 6 && ! in_array($step, $application->correction_steps ?? [], true)) { $step = 7; }
        $progress = (int) round(count(array_intersect(range(1, 6), $completed)) / 6 * 100);
        $isBusiness = $application?->seller_type === 'business';
        $actionBarClass = 'sticky bottom-0 z-10 -mx-4 flex items-center gap-3 border-t border-line bg-white/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-2xl sm:border xl:static xl:border-0 xl:bg-transparent xl:px-0 xl:py-2 xl:backdrop-blur-none';
        $submitClass = 'button-primary flex-1 shadow-[0_6px_16px_-6px_rgba(32,91,244,.55)] xl:min-w-[200px] xl:flex-none';
        $canEditStep = fn (int $number): bool => $application?->status !== 'more_information_required' || $number === 7 || in_array($number, $application->correction_steps ?? [], true);
    @endphp
    <div class="page-shell bg-canvas"><div class="mx-auto max-w-6xl px-4 sm:px-8">
        @if (! $editable)
            <div class="mb-6 pt-6">
                <a href="{{ route('profile.edit') }}" class="text-sm font-semibold text-brand">Account</a>
                <h1 class="mt-2 text-2xl font-semibold text-ink">Become a seller</h1>
            </div>
        @endif
        @if ($errors->any())<div role="alert" class="mb-5 rounded-xl border border-danger/20 bg-danger/10 p-4 text-sm text-danger"><p class="font-semibold">Please correct these fields.</p><ul class="mt-2 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @if ($workspaceUnavailable)
            @if (session('status'))<p role="status" class="mb-5 rounded-xl border border-success/20 bg-success/10 p-4 text-sm text-success">{{ session('status') }}</p>@endif
            <section class="section-card max-w-3xl">
                <h2 class="text-lg font-semibold">Your seller workspace is unavailable</h2>
                @if ($workspaceSuspended)
                    <p class="mt-2 text-sm text-muted">Your store is currently suspended. Shopping is unaffected.</p>
                    @if ($suspensionReason)<p class="mt-2 text-sm">Reason: {{ $suspensionReason }}</p>@endif
                    @if ($suspensionAppeal?->status === 'pending')<p class="mt-3 text-sm">Your appeal is awaiting review.</p>@endif
                    @if ($suspensionAppeal?->status === 'rejected')<p class="mt-3 text-sm">Your previous appeal was not approved. {{ $suspensionAppeal->review_note }}</p>@endif
                    @if ($canSubmitSuspensionAppeal)
                        <form method="POST" action="{{ route('seller.suspension-appeals.store') }}" class="mt-5 space-y-3">@csrf
                            <label for="appeal" class="block text-sm font-semibold">Appeal statement</label>
                            <textarea id="appeal" name="statement" required minlength="20" maxlength="5000" rows="5" class="field-control">{{ old('statement') }}</textarea>
                            <x-primary-button>Submit an appeal</x-primary-button>
                        </form>
                    @endif
                @else
                    <p class="mt-2 text-sm text-muted">Contact JMMart support for help with this store.</p>
                @endif
            </section>
        @elseif (! $editable)
            @if (session('status'))<p role="status" class="mb-5 rounded-xl border border-success/20 bg-success/10 p-4 text-sm text-success">{{ session('status') }}</p>@endif
            <section class="section-card max-w-3xl">
                <h2 class="text-lg font-semibold">Application {{ str_replace('_', ' ', $application->status) }}</h2>
                <p class="mt-2 text-sm text-muted">Store: {{ $application->store_name }}. You can continue shopping while we review your application.</p>
                @if ($application->review_note)<p class="mt-3 rounded-lg bg-canvas p-3 text-sm">{{ $application->review_note }}</p>@endif
                <ol class="mt-5 space-y-2 border-l-2 border-line pl-4 text-sm" aria-label="Application status timeline">
                    @foreach ($application->statusHistory()->oldest()->get() as $event)<li><span class="font-semibold">{{ str_replace('_', ' ', ucfirst($event->to_status)) }}</span> · {{ $event->created_at->format('M j, Y g:i A') }}</li>@endforeach
                </ol>
                <a href="{{ route('catalog.index') }}" class="button-secondary mt-5">Continue shopping</a>
            </section>
        @else
            <div class="grid grid-cols-1 gap-6 pt-4 xl:grid-cols-[300px_minmax(0,1fr)] xl:gap-10 xl:pt-6">
                {{-- One rail for every width: a compact card with a swipeable step strip on phones, a sticky side panel from xl. --}}
                <aside class="onboarding-rail self-start overflow-hidden rounded-2xl p-5 text-slate-300 xl:sticky xl:top-6 xl:p-6">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-blue-300">JMMart Seller</p>
                    <h1 class="mt-2 text-2xl font-semibold tracking-tight text-white">Become a seller</h1>
                    <p class="mt-2 hidden text-sm text-slate-400 sm:block">About 10 minutes. Your draft saves at every step, so you can finish later. You can keep shopping throughout review.</p>
                    <div class="mt-5 rounded-xl border border-white/10 bg-white/5 p-3.5">
                        <div class="flex items-baseline justify-between text-xs"><span class="font-semibold text-white">{{ $progress }}% complete</span><span>Step {{ $step }} of 7</span></div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/10" role="progressbar" aria-label="Application completion" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"><span class="block h-full rounded-full bg-gradient-to-r from-brand to-blue-400" style="width: {{ $progress }}%"></span></div>
                    </div>
                    <nav aria-label="Application progress" class="relative -mx-5 mt-4 overflow-x-auto px-5 [scrollbar-width:none] xl:mx-0 xl:mt-2 xl:overflow-visible xl:px-0" x-data x-init="const current = $el.querySelector('[aria-current]'); if (current) $el.scrollLeft = current.offsetLeft - 20">
                        <ol class="flex gap-2 xl:grid xl:gap-0.5">
                            @foreach ($steps as $number => $item)
                                @if ($number === 1 || $steps[$number - 1]['phase'] !== $item['phase'])
                                    <li aria-hidden="true" class="hidden pb-1.5 pt-4 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500 xl:block">{{ $item['phase'] }}</li>
                                @endif
                                @php($done = in_array($number, $completed, true) && $number !== $step)
                                <li class="flex-none">
                                    <a @if ($canEditStep($number)) href="{{ route('seller-application.create', ['step' => $number]) }}" @else role="link" aria-disabled="true" @endif @if ($number === $step) aria-current="step" @endif @class(['flex min-h-11 items-center gap-3 rounded-[10px] px-3 py-2 text-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-300', 'bg-white text-ink' => $number === $step, 'text-slate-300 hover:bg-white/5' => $number !== $step, 'opacity-50' => ! $canEditStep($number)])>
                                        <span aria-hidden="true" @class(['grid size-[26px] flex-none place-items-center rounded-full border-[1.5px] text-xs font-bold', 'border-brand bg-brand text-white' => $number === $step, 'border-green-500 bg-green-500 text-green-950' => $done, 'border-white/25' => $number !== $step && ! $done])>{{ $done ? '✓' : $number }}</span>
                                        <span class="whitespace-nowrap"><span class="sr-only">Step {{ $number }} </span>{{ $item['label'] }}<span @class(['hidden text-xs xl:block', 'text-muted' => $number === $step, 'text-slate-400' => $number !== $step])>{{ $item['hint'] }}</span>@if ($done)<span class="sr-only">, done</span>@endif</span>
                                    </a>
                                </li>
                            @endforeach
                        </ol>
                    </nav>
                    <p class="mt-4 border-t border-white/10 pt-4 text-xs text-slate-400 xl:mt-5">Not ready yet? <a href="{{ route('profile.edit') }}" class="font-semibold text-white underline-offset-4 hover:underline">Back to your account</a></p>
                </aside>

                <section class="min-w-0" aria-labelledby="step-title">
                    @if (session('status'))<p role="status" class="mb-4 inline-flex items-center gap-2 rounded-full bg-success/10 px-3 py-1 text-xs font-semibold text-success"><span class="size-1.5 rounded-full bg-green-500" aria-hidden="true"></span>{{ session('status') }}</p>@endif
                    @if ($application?->status === 'more_information_required')<p role="status" class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">More information required: {{ $application->review_note }}. Update the requested steps, then resubmit.</p>@endif
                    <p class="eyebrow">Step {{ $step }} · {{ $steps[$step]['phase'] }}</p>
                    <h2 id="step-title" class="mt-1.5 text-2xl font-semibold tracking-tight text-ink">{{ $steps[$step]['title'] }}</h2>
                    <p class="mt-1.5 max-w-[60ch] text-sm text-muted">{{ $steps[$step]['intro'] }}</p>

                    @if ($step < 7)
                        <form method="POST" enctype="multipart/form-data" action="{{ route('seller-application.step', $step) }}" class="mt-6 space-y-4" x-data="{ saving: false, sameAddresses: false, uploadProgress: 0, uploadError: '', validationErrors: [], save(event) { this.saving = true; this.uploadError = ''; this.validationErrors = []; const request = new XMLHttpRequest(); request.open('POST', event.target.action); request.setRequestHeader('Accept', 'application/json'); request.upload.onprogress = (progress) => { if (progress.lengthComputable) this.uploadProgress = Math.round(progress.loaded / progress.total * 100) }; request.onload = () => { if (request.status === 200) { window.location.assign(JSON.parse(request.responseText).redirect); return; } this.saving = false; if (request.status === 422) { this.validationErrors = Object.values(JSON.parse(request.responseText).errors).flat(); this.$nextTick(() => this.$refs.formErrors?.focus()); } else { this.uploadError = 'Could not save this step. Please try again.'; } }; request.onerror = () => { this.saving = false; this.uploadError = 'Upload failed. Please check your connection and retry.'; }; request.send(new FormData(event.target)); } }" @submit.prevent="save($event)">@csrf
                            <div x-cloak x-show="validationErrors.length || uploadError" x-ref="formErrors" tabindex="-1" role="alert" class="rounded-xl border border-danger/20 bg-danger/10 p-4 text-sm text-danger"><p x-text="uploadError"></p><template x-for="error in validationErrors"><p x-text="error"></p></template></div>
                            @if ($step === 1)
                                <fieldset class="grid gap-3 sm:grid-cols-2"><legend class="sr-only">How will you sell?</legend>
                                    @foreach (['individual' => ['Individual seller', 'You sell under your own name.', ['One government ID', 'A selfie for identity check']], 'business' => ['Registered business', 'A DTI or SEC registered company.', ['DTI or SEC registration', 'BIR certificate, if you have one']]] as $option => [$title, $description, $needs])
                                        <label class="relative flex cursor-pointer gap-4 rounded-2xl border-[1.5px] border-line bg-white p-5 transition hover:border-blue-200 has-[:checked]:border-brand has-[:checked]:ring-4 has-[:checked]:ring-brand/10 has-[:focus-visible]:ring-4 has-[:focus-visible]:ring-brand/30">
                                            <input type="radio" name="seller_type" value="{{ $option }}" @checked(old('seller_type', $application?->seller_type ?? 'individual') === $option) required class="peer absolute right-5 top-5 size-5 border-slate-300 text-brand focus:ring-0">
                                            <span class="grid size-11 flex-none place-items-center rounded-xl bg-slate-100 text-ink peer-checked:bg-brand/10 peer-checked:text-brand" aria-hidden="true">
                                                @if ($option === 'individual')
                                                    <svg class="size-[22px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                                                @else
                                                    <svg class="size-[22px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 13h18"/></svg>
                                                @endif
                                            </span>
                                            <span class="pr-6">
                                                <span class="block text-[15px] font-semibold text-ink">{{ $title }}</span>
                                                <span class="block text-sm text-muted">{{ $description }}</span>
                                                <span class="mt-3 grid gap-1 text-xs text-muted">@foreach ($needs as $need)<span><span class="mr-1.5 font-bold text-success" aria-hidden="true">✓</span>{{ $need }}</span>@endforeach</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </fieldset>
                                <p class="flex gap-2.5 rounded-xl bg-brand/5 p-3.5 text-sm text-blue-900"><svg class="mt-0.5 size-4 flex-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/></svg>Keep your ID and payout account handy. You’ll need them in steps 5 and 6.</p>
                            @elseif ($step === 2)
                                <div class="section-card">
                                    <p class="mb-5 flex items-center gap-2 rounded-[10px] bg-canvas px-3 py-2.5 text-sm"><span class="text-muted">Verified email</span><span class="truncate font-semibold">{{ auth()->user()->email }}</span></p>
                                    <div class="grid gap-5 sm:grid-cols-2">
                                        @include('seller-applications.field', ['name' => 'seller_name', 'label' => 'Full legal name', 'required' => true, 'class' => 'sm:col-span-2'])
                                        @include('seller-applications.field', ['name' => 'phone', 'label' => 'Mobile number', 'required' => true, 'type' => 'tel', 'inputmode' => 'tel', 'placeholder' => '+63 917 555 0142'])
                                        @if ($isBusiness)
                                            @include('seller-applications.field', ['name' => 'representative_name', 'label' => 'Authorized representative', 'required' => true])
                                            @include('seller-applications.field', ['name' => 'business_name', 'label' => 'Registered business name', 'required' => true, 'class' => 'sm:col-span-2'])
                                            @include('seller-applications.field', ['name' => 'registration_number', 'label' => 'DTI or SEC registration number', 'required' => true])
                                            @include('seller-applications.field', ['name' => 'bir_details', 'label' => 'BIR registration details, if applicable'])
                                        @else
                                            @include('seller-applications.field', ['name' => 'date_of_birth', 'label' => 'Date of birth', 'type' => 'date', 'required' => true])
                                        @endif
                                    </div>
                                </div>
                            @elseif ($step === 3)
                                <div class="grid gap-4 2xl:grid-cols-[minmax(0,1fr)_260px] 2xl:items-start" x-data="{ store_name: @js(old('store_name', $application?->store_name)), store_slug: @js(old('store_slug', $application?->store_slug)), store_description: @js(old('store_description', $application?->store_description)), store_category: @js(old('store_category', $application?->store_category)), logoPreview: null }" @file-preview="if ($event.detail.name === 'logo') logoPreview = $event.detail.url" @input="if ($event.target.name in $data) $data[$event.target.name] = $event.target.value">
                                    <div class="space-y-4">
                                        <div class="section-card">
                                            <h3 class="text-[15px] font-semibold">Store details</h3>
                                            <div class="mt-4 grid gap-5 sm:grid-cols-2">
                                                @include('seller-applications.field', ['name' => 'store_name', 'label' => 'Store name', 'required' => true, 'class' => 'sm:col-span-2'])
                                                @include('seller-applications.field', ['name' => 'store_slug', 'label' => 'Store link', 'required' => true, 'prefix' => '/stores/', 'help' => 'Lowercase letters, numbers and hyphens.', 'class' => 'sm:col-span-2'])
                                                @include('seller-applications.field', ['name' => 'store_description', 'label' => 'Store description', 'textarea' => true, 'required' => true, 'maxlength' => 2000, 'class' => 'sm:col-span-2'])
                                                @include('seller-applications.field', ['name' => 'store_category', 'label' => 'Primary product category', 'required' => true, 'placeholder' => 'Home and living'])
                                                @include('seller-applications.field', ['name' => 'support_contact', 'label' => 'Customer support contact', 'required' => true])
                                            </div>
                                        </div>
                                        <div class="section-card">
                                            <h3 class="text-[15px] font-semibold">Branding</h3>
                                            <p class="mt-0.5 text-xs text-muted">JPG, PNG or WebP. A square logo and a wide banner work best.</p>
                                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                                @include('seller-applications.field', ['name' => 'logo', 'label' => 'Store logo', 'type' => 'file', 'accept' => 'image/jpeg,image/png,image/webp', 'saved' => filled($application?->logo_path), 'help' => 'Required · up to 2 MB'])
                                                @include('seller-applications.field', ['name' => 'banner', 'label' => 'Store banner (optional)', 'type' => 'file', 'accept' => 'image/jpeg,image/png,image/webp', 'saved' => filled($application?->banner_path), 'help' => 'Optional · up to 4 MB'])
                                            </div>
                                        </div>
                                    </div>
                                    <div class="w-full max-w-sm 2xl:sticky 2xl:top-6 2xl:max-w-none" aria-hidden="true">
                                        <div class="overflow-hidden rounded-2xl border border-line bg-white shadow-sm">
                                            <div class="h-20 bg-gradient-to-br from-blue-200 via-blue-50 to-amber-100"></div>
                                            <div class="px-4 pb-4">
                                                <div class="-mt-7 grid size-14 place-items-center overflow-hidden rounded-[14px] border-[3px] border-white bg-ink text-lg font-bold text-white shadow-sm">
                                                    <img x-cloak x-show="logoPreview" :src="logoPreview" alt="" class="size-full object-cover">
                                                    <span x-show="!logoPreview" class="contents">@if ($application?->logo_path)<img src="{{ Storage::disk('public')->url($application->logo_path) }}" alt="" class="size-full object-cover">@else<span x-text="(store_name || 'S').trim().charAt(0).toUpperCase()"></span>@endif</span>
                                                </div>
                                                <p class="mt-2.5 truncate text-[15px] font-semibold text-ink" x-text="store_name || 'Your store name'"></p>
                                                <p class="truncate text-xs text-brand">/stores/<span x-text="store_slug || 'your-store'"></span></p>
                                                <p class="mt-1.5 line-clamp-3 text-xs leading-[18px] text-muted" x-text="store_description || 'Your store description appears here.'"></p>
                                                <div class="mt-2.5 flex flex-wrap gap-1.5 text-[11px] font-medium text-muted"><span class="rounded-full bg-slate-100 px-2 py-0.5" x-show="store_category" x-text="store_category"></span><span class="rounded-full bg-slate-100 px-2 py-0.5">New seller</span></div>
                                            </div>
                                        </div>
                                        <p class="mt-2 text-center text-xs text-muted">How buyers will see your store</p>
                                    </div>
                                </div>
                            @elseif ($step === 4)
                                <div class="section-card">
                                    <h3 class="text-[15px] font-semibold">Residential address</h3>
                                    <div class="mt-4 grid gap-5 sm:grid-cols-2">
                                        @foreach (['region' => 'Region', 'province' => 'Province', 'city' => 'City or municipality', 'barangay' => 'Barangay', 'address_line' => 'Street address', 'postal_code' => 'Postal code'] as $name => $label)
                                            @include('seller-applications.field', ['name' => $name, 'label' => $label, 'required' => true, 'class' => $name === 'address_line' ? 'sm:col-span-2' : null, 'inputmode' => $name === 'postal_code' ? 'numeric' : null])
                                        @endforeach
                                    </div>
                                    <label class="mt-5 flex min-h-11 cursor-pointer items-center gap-3 rounded-[10px] bg-canvas px-3.5 text-sm font-semibold"><input type="checkbox" name="same_addresses" value="1" x-model="sameAddresses" class="size-[18px] rounded border-slate-300 text-brand focus:ring-brand"> Use this address for pickup and returns</label>
                                    <div x-show="!sameAddresses" class="mt-5 grid gap-5 sm:grid-cols-2">
                                        @include('seller-applications.field', ['name' => 'pickup_address', 'label' => 'Pickup address'])
                                        @include('seller-applications.field', ['name' => 'return_address', 'label' => 'Return address'])
                                    </div>
                                </div>
                                <div class="section-card">
                                    <h3 class="text-[15px] font-semibold">How you ship</h3>
                                    <div class="mt-4 grid gap-5 sm:grid-cols-2">
                                        <div class="sm:col-span-2" x-data="{ days: @js((int) old('processing_days', $application?->processing_days ?? 2)) }">
                                            <label for="processing_days" class="mb-1.5 block text-sm font-semibold text-ink">Order processing days</label>
                                            <div class="flex items-center gap-3">
                                                <div class="inline-flex items-center overflow-hidden rounded-[10px] border border-line bg-white">
                                                    <button type="button" class="size-11 text-lg text-muted hover:bg-slate-50 hover:text-ink" aria-label="Fewer days" @click="days = Math.max(1, days - 1)">−</button>
                                                    <input id="processing_days" name="processing_days" type="number" min="1" max="30" required value="{{ old('processing_days', $application?->processing_days ?? 2) }}" x-model.number="days" class="h-11 w-14 border-0 text-center text-sm font-semibold focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none">
                                                    <button type="button" class="size-11 text-lg text-muted hover:bg-slate-50 hover:text-ink" aria-label="More days" @click="days = Math.min(30, days + 1)">+</button>
                                                </div>
                                                <span class="text-xs text-muted">business days before you ship</span>
                                            </div>
                                            <x-input-error :messages="$errors->get('processing_days')" class="mt-1" />
                                        </div>
                                        @include('seller-applications.field', ['name' => 'fulfillment_methods', 'label' => 'Fulfillment methods', 'required' => true, 'help' => 'For example: courier pickup, drop-off'])
                                        @include('seller-applications.field', ['name' => 'delivery_areas', 'label' => 'Supported delivery areas', 'required' => true, 'help' => 'For example: Metro Manila, Luzon'])
                                    </div>
                                </div>
                            @elseif ($step === 5)
                                <div class="section-card">
                                    <div class="grid gap-5 sm:grid-cols-2">
                                        <div><label for="id_type" class="mb-1.5 block text-sm font-semibold text-ink">Government ID type</label><select id="id_type" name="id_type" required class="field-control">@foreach (['passport' => 'Passport', 'drivers_license' => 'Driver’s license', 'national_id' => 'National ID', 'other' => 'Other government ID'] as $option => $label)<option value="{{ $option }}" @selected(old('id_type', $application?->id_type) === $option)>{{ $label }}</option>@endforeach</select></div>
                                        @include('seller-applications.field', ['name' => 'id_number', 'label' => 'ID number', 'required' => true])
                                    </div>
                                </div>
                                <div class="section-card">
                                    <h3 class="text-[15px] font-semibold">Upload documents</h3>
                                    <p class="mt-0.5 text-xs text-muted">Clear photos with all four corners visible. JPEG, PNG or PDF, up to 5 MB each.</p>
                                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                        @php($savedDocuments = $application?->documents()->pluck('type')->all() ?? [])
                                        @foreach (['id_front' => 'Front of ID', 'id_back' => 'Back of ID, if applicable', 'selfie' => 'Identity selfie', 'business_registration' => 'Business registration document', 'bir_certificate' => 'BIR certificate, if applicable'] as $name => $label)
                                            @if (! in_array($name, ['business_registration', 'bir_certificate'], true) || $isBusiness)
                                                @include('seller-applications.field', ['name' => $name, 'label' => $label, 'type' => 'file', 'accept' => $name === 'selfie' ? 'image/jpeg,image/png' : 'image/jpeg,image/png,application/pdf', 'saved' => in_array($name, $savedDocuments, true), 'help' => ['selfie' => $isBusiness ? 'Optional for businesses' : 'A photo of you holding your ID', 'id_back' => 'Needed for driver’s license and National ID'][$name] ?? null])
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                                <p class="flex gap-2.5 rounded-xl bg-slate-100 p-3.5 text-sm text-muted"><svg class="mt-0.5 size-4 flex-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>Documents are stored privately and used only to confirm who you are.</p>
                            @elseif ($step === 6)
                                <fieldset class="grid gap-3 sm:grid-cols-2"><legend class="sr-only">Payout method</legend>
                                    @foreach (['wallet' => ['E-wallet', 'GCash or Maya'], 'bank' => ['Bank account', 'BDO, BPI, UnionBank and more']] as $option => [$title, $description])
                                        <label class="relative flex min-h-[76px] cursor-pointer items-center gap-4 rounded-2xl border-[1.5px] border-line bg-white p-4 transition hover:border-blue-200 has-[:checked]:border-brand has-[:checked]:ring-4 has-[:checked]:ring-brand/10 has-[:focus-visible]:ring-4 has-[:focus-visible]:ring-brand/30">
                                            <input type="radio" name="method" value="{{ $option }}" @checked(old('method', $application?->payoutProfile?->method ?? 'bank') === $option) required class="peer absolute right-4 top-1/2 size-5 -translate-y-1/2 border-slate-300 text-brand focus:ring-0">
                                            <span class="grid size-11 flex-none place-items-center rounded-xl bg-slate-100 text-ink peer-checked:bg-brand/10 peer-checked:text-brand" aria-hidden="true">
                                                @if ($option === 'wallet')
                                                    <svg class="size-[22px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="3"/><path d="M11 18h2"/></svg>
                                                @else
                                                    <svg class="size-[22px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 10l9-6 9 6M5 10v8m4-8v8m6-8v8m4-8v8M3 21h18"/></svg>
                                                @endif
                                            </span>
                                            <span class="pr-8"><span class="block text-[15px] font-semibold text-ink">{{ $title }}</span><span class="block text-sm text-muted">{{ $description }}</span></span>
                                        </label>
                                    @endforeach
                                </fieldset>
                                <x-input-error :messages="$errors->get('method')" class="mt-1" />
                                <div class="section-card">
                                    <div class="grid gap-5 sm:grid-cols-2">
                                        @include('seller-applications.field', ['name' => 'provider', 'label' => 'Bank or wallet provider', 'required' => true, 'value' => $application?->payoutProfile?->provider, 'placeholder' => 'Bank or e-wallet name'])
                                        @include('seller-applications.field', ['name' => 'holder_name', 'label' => 'Account holder name', 'required' => true, 'value' => $application?->payoutProfile?->holder_name])
                                        @include('seller-applications.field', ['name' => 'account_number', 'label' => 'Account or wallet number', 'value' => '', 'inputmode' => 'numeric', 'class' => 'sm:col-span-2', 'help' => $application?->payoutProfile ? 'Saved number: '.$application->payoutProfile->maskedAccountNumber().'. Leave blank to keep it.' : ''])
                                    </div>
                                </div>
                                <p class="flex gap-2.5 rounded-xl bg-slate-100 p-3.5 text-sm text-muted"><svg class="mt-0.5 size-4 flex-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>Only the last 4 digits of your account number are ever shown.</p>
                            @endif
                            <div x-cloak x-show="saving && uploadProgress > 0" role="status" class="text-sm text-muted"><label for="upload-progress" class="font-semibold">Upload progress: <span x-text="uploadProgress"></span>%</label><progress id="upload-progress" :value="uploadProgress" max="100" class="mt-2 block w-full"></progress></div>
                            {{-- Sticks to the bottom of the screen on phones so the next action is always in reach. --}}
                            <div class="{{ $actionBarClass }}">
                                <span class="mr-auto hidden text-xs text-muted xl:block">Your progress saves when you continue.</span>
                                @if ($step > 1)<a href="{{ route('seller-application.create', ['step' => $step - 1]) }}" class="button-secondary">Back</a>@endif
                                <button type="submit" :disabled="saving" class="{{ $submitClass }}"><span x-show="!saving">Save and continue</span><span x-show="saving" x-cloak>Saving…</span></button>
                            </div>
                        </form>
                    @else
                        <div class="mt-6 section-card">
                            <div class="grid gap-3">
                                @foreach ([
                                    ['About you', 2, ['Type' => $application?->seller_type ? ucfirst($application->seller_type) : null, 'Seller' => $application?->seller_name, 'Mobile' => $application?->phone, ...($isBusiness ? ['Business' => $application->business_name, 'Registration' => $application->registration_number, 'Representative' => $application->representative_name] : [])]],
                                    ['Store', 3, ['Store' => $application?->store_name, 'Storefront' => $application?->store_slug, 'Ships from' => collect([$application?->address_line, $application?->barangay, $application?->city, $application?->province])->filter()->implode(', ')]],
                                    ['Payout', 6, ['Payout' => $application?->payoutProfile ? $application->payoutProfile->provider.' '.$application->payoutProfile->maskedAccountNumber() : null]],
                                ] as [$heading, $editStep, $rows])
                                    <div class="rounded-xl border border-line px-4 py-3.5">
                                        <div class="flex items-center justify-between gap-3">
                                            <h3 class="text-sm font-semibold">{{ $heading }}</h3>
                                            @if ($canEditStep($editStep))<a href="{{ route('seller-application.create', ['step' => $editStep]) }}" class="text-xs font-semibold text-brand hover:underline">Edit<span class="sr-only"> {{ $heading }}</span></a>@endif
                                        </div>
                                        <dl class="mt-2 grid grid-cols-[7.5rem_minmax(0,1fr)] gap-x-4 gap-y-1 text-sm">
                                            @foreach ($rows as $term => $detail)<dt class="text-muted">{{ $term }}</dt><dd class="break-words font-medium">{{ filled($detail) ? $detail : 'Not completed' }}</dd>@endforeach
                                        </dl>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <form method="POST" action="{{ route('seller-application.store') }}" class="mt-4 space-y-4" x-data="{ accepted: 0, total: 6, sync() { this.accepted = this.$root.querySelectorAll('[data-policy]:checked').length } }" @change="sync()">@csrf
                            <div class="section-card">
                                <h3 class="text-[15px] font-semibold">Policies</h3>
                                <p class="mt-0.5 text-xs text-muted">You must accept each one to submit.</p>
                                <label class="mt-4 flex min-h-11 cursor-pointer items-center gap-3 rounded-[10px] bg-canvas px-3.5 text-sm font-semibold"><input type="checkbox" class="size-[18px] rounded border-slate-300 text-brand focus:ring-brand" :checked="accepted === total" :indeterminate="accepted > 0 && accepted < total" @change="$root.querySelectorAll('[data-policy]').forEach((box) => box.checked = $event.target.checked)"> Accept all six policies</label>
                                <div class="mt-1 divide-y divide-line">
                                    @foreach (['seller_agreement' => 'Seller Agreement', 'terms' => 'Terms and Conditions', 'privacy' => 'Privacy Policy', 'prohibited_products' => 'Prohibited Products Policy', 'returns_refunds' => 'Returns and Refunds Policy', 'fees' => 'Marketplace Fee Policy'] as $key => $label)
                                        <div class="py-3">
                                            <div class="flex items-start gap-3">
                                                <label class="flex flex-1 cursor-pointer items-start gap-3 text-sm"><input type="checkbox" name="agreements[{{ $key }}]" value="1" required data-policy class="mt-0.5 size-[18px] rounded border-slate-300 text-brand focus:ring-brand"> <span>I accept the {{ $label }} (version {{ $key === 'fees' ? config('marketplace.platform_fee.policy_version') : '1.0' }}).</span></label>
                                                @if (in_array($key, ['terms', 'privacy', 'returns_refunds'], true))<a href="{{ route(['terms' => 'terms', 'privacy' => 'privacy-policy', 'returns_refunds' => 'returns'][$key]) }}" target="_blank" rel="noopener" class="flex-none text-xs font-semibold text-brand hover:underline">Read<span class="sr-only"> {{ $label }}</span></a>@endif
                                            </div>
                                            @if ($key === 'fees')
                                                @include('partials.platform-fee-terms', ['class' => 'ml-[30px] mt-2'])
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="{{ $actionBarClass }}">
                                <span class="mr-auto hidden text-xs text-muted xl:block" x-text="accepted === total ? 'Ready to submit.' : `${accepted} of ${total} policies accepted.`"></span>
                                <a href="{{ route('seller-application.create', ['step' => 6]) }}" class="button-secondary">Back</a>
                                <button type="submit" class="{{ $submitClass }}">Submit application</button>
                            </div>
                        </form>
                    @endif
                </section>
            </div>
        @endif
    </div></div>
</x-app-layout>
