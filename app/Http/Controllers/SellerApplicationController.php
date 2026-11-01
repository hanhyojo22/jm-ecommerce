<?php

namespace App\Http\Controllers;

use App\Actions\StorePublicImage;
use App\Http\Requests\StoreSellerApplicationRequest;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\SellerApplication;
use App\Models\Store;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SellerApplicationController extends Controller
{
    public function create(): View|RedirectResponse
    {
        $user = request()->user();
        // Every query here is a network round trip, and most applicants belong to no tenant yet.
        $membership = $user->tenantMemberships()->with('tenant.store')->oldest('created_at')->first();

        if ($membership !== null && $user->sellerTenant() !== null) {
            return to_route('seller.dashboard');
        }

        $store = $membership?->tenant?->store;
        $workspaceSuspended = $membership?->tenant?->status === 'suspended'
            || $store?->status === 'suspended';
        $suspensionAppeal = $membership?->role === 'owner' && $workspaceSuspended
            ? $store?->appeals()->latest()->first()
            : null;
        $suspensionReason = $workspaceSuspended && $store !== null
            ? AuditLog::query()
                ->where('action', 'store.suspended')
                ->where('entity_type', Store::class)
                ->where('entity_id', $store->id)
                ->latest('created_at')
                ->first()?->new_values['reason'] ?? null
            : null;

        return view('seller-applications.create', [
            'application' => $user->sellerApplications()->latest()->first(),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->pluck('name'),
            'workspaceUnavailable' => $membership !== null,
            'workspaceSuspended' => $workspaceSuspended,
            'suspensionAppeal' => $suspensionAppeal,
            'suspensionReason' => $suspensionReason,
            'canSubmitSuspensionAppeal' => $membership?->role === 'owner'
                && $workspaceSuspended
                && $suspensionAppeal?->status !== 'pending',
        ]);
    }

    public function saveStep(Request $request, int $step, StorePublicImage $storePublicImage): RedirectResponse|JsonResponse
    {
        abort_unless($step >= 1 && $step <= 6, 404);

        return DB::transaction(function () use ($request, $step, $storePublicImage): RedirectResponse|JsonResponse {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            abort_if($user->tenantMemberships()->exists(), 403);
            $applications = $user->sellerApplications()->latest()->get();
            if ($applications->contains(fn (SellerApplication $existing): bool => in_array($existing->status, ['pending', 'submitted', 'under_review', 'approved', 'suspended'], true))) {
                throw ValidationException::withMessages(['application' => 'You already have an active seller application.']);
            }
            $application = $applications->first() ?? new SellerApplication;
            $documents = $step === 5 && $application->exists ? $application->documents()->get()->keyBy('type') : collect();
            $replacedFiles = [];
            abort_unless($application->status === null || in_array($application->status, ['draft', 'more_information_required'], true), 403);
            abort_if($application->status === 'more_information_required' && ! in_array($step, $application->correction_steps ?? [], true), 403);
            if ($step > 1 && ! in_array(1, $application->completed_steps ?? [], true)) {
                throw ValidationException::withMessages(['application' => 'Choose a seller type first.']);
            }

            $rules = match ($step) {
                1 => ['seller_type' => ['required', Rule::in(['individual', 'business'])]],
                2 => ['seller_name' => ['required', 'string', 'max:120'], 'phone' => ['required', 'regex:/^\\+?[0-9 ]{10,18}$/'], 'date_of_birth' => [$application->seller_type === 'individual' ? 'required' : 'nullable', 'date', 'before:-18 years'], 'business_name' => [$application->seller_type === 'business' ? 'required' : 'nullable', 'string', 'max:255'], 'registration_number' => [$application->seller_type === 'business' ? 'required' : 'nullable', 'string', 'max:100'], 'bir_details' => ['nullable', 'string', 'max:255'], 'representative_name' => [$application->seller_type === 'business' ? 'required' : 'nullable', 'string', 'max:120']],
                3 => ['store_name' => ['required', 'string', 'min:3', 'max:120'], 'store_slug' => ['required', 'string', 'min:3', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::notIn(['admin', 'seller', 'support', 'jmmart', 'official', 'help', 'login']), Rule::unique('seller_applications')->ignore($application->id), Rule::unique('stores', 'slug')], 'store_description' => ['required', 'string', 'max:2000'], 'store_category' => ['required', 'string', 'max:100'], 'support_contact' => ['required', 'string', 'max:120'], 'logo' => [$application->logo_path ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'], 'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']],
                4 => ['region' => ['required', 'string', 'max:100'], 'province' => ['required', 'string', 'max:100'], 'city' => ['required', 'string', 'max:100'], 'barangay' => ['required', 'string', 'max:100'], 'address_line' => ['required', 'string', 'max:255'], 'postal_code' => ['required', 'digits:4'], 'same_addresses' => ['nullable', 'boolean'], 'pickup_address' => ['required_unless:same_addresses,1', 'nullable', 'string', 'max:255'], 'return_address' => ['required_unless:same_addresses,1', 'nullable', 'string', 'max:255'], 'processing_days' => ['required', 'integer', 'between:1,30'], 'fulfillment_methods' => ['required', 'string', 'max:120'], 'delivery_areas' => ['required', 'string', 'max:1000']],
                5 => ['id_type' => ['required', Rule::in(['passport', 'drivers_license', 'national_id', 'other'])], 'id_number' => ['required', 'string', 'max:100'], 'id_front' => [$documents->has('id_front') ? 'nullable' : 'required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'], 'id_back' => [in_array($request->input('id_type'), ['drivers_license', 'national_id'], true) && ! $documents->has('id_back') ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'], 'selfie' => [$application->seller_type === 'individual' && ! $documents->has('selfie') ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'], 'business_registration' => [$application->seller_type === 'business' && ! $documents->has('business_registration') ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'], 'bir_certificate' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120']],
                6 => ['method' => ['required', Rule::in(['bank', 'wallet'])], 'provider' => ['required', 'string', 'max:120'], 'holder_name' => ['required', 'string', 'max:120'], 'account_number' => [$application->payoutProfile !== null ? 'nullable' : 'required', 'string', 'min:6', 'max:40', 'regex:/^[0-9A-Za-z -]+$/']],
            };
            if ($step === 3) {
                $request->merge(['store_slug' => Str::slug($request->input('store_slug', ''))]);
            }

            $partial = $request->boolean('draft');

            if ($partial) {
                /*
                 * A draft keeps whatever has been typed so far. Required rules only bind when the
                 * step is continued, so they drop out here, while format, size and type checks stay
                 * on any value that was actually sent. Empty inputs are left out entirely so a
                 * draft can never wipe a value that is already saved.
                 */
                $rules = array_map(function (array $fieldRules): array {
                    $fieldRules = array_values(array_filter(
                        $fieldRules,
                        fn ($rule): bool => ! is_string($rule) || ! str_starts_with($rule, 'required'),
                    ));

                    if (! in_array('nullable', $fieldRules, true)) {
                        $fieldRules[] = 'nullable';
                    }

                    return $fieldRules;
                }, $rules);

                $request->replace(collect($request->all())
                    ->reject(fn ($value): bool => $value === null || $value === '')
                    ->all());
            }

            $data = $request->validate($rules);
            if ($step === 4 && ($data['same_addresses'] ?? false) && isset($data['address_line'], $data['barangay'], $data['city'], $data['province'], $data['region'], $data['postal_code'])) {
                $data['pickup_address'] = $data['return_address'] = $data['address_line'].', '.$data['barangay'].', '.$data['city'].', '.$data['province'].', '.$data['region'].' '.$data['postal_code'];
                if (mb_strlen($data['pickup_address']) > 255) {
                    throw ValidationException::withMessages(['address_line' => 'The combined address must be 255 characters or fewer.']);
                }
            }
            unset($data['same_addresses']);
            if ($step === 3 && isset($data['store_name']) && preg_match('/\\b(official|jmmart|admin|support|fuck|shit|scam)\\b/i', $data['store_name'])) {
                throw ValidationException::withMessages(['store_name' => 'Choose a name that does not imply marketplace affiliation.']);
            }
            if ($step === 3 && isset($data['store_slug']) && preg_match('/(^|-)(official|jmmart|admin|support|fuck|shit|scam)(-|$)/', $data['store_slug'])) {
                throw ValidationException::withMessages(['store_slug' => 'Choose a different storefront URL.']);
            }
            if ($step === 3 && isset($data['store_name']) && (SellerApplication::query()->whereRaw('LOWER(store_name) = ?', [mb_strtolower($data['store_name'])])->whereKeyNot($application->id)->exists() || Store::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($data['store_name'])])->exists())) {
                throw ValidationException::withMessages(['store_name' => 'This store name is already taken.']);
            }
            $sellerTypeChanged = $step === 1 && $application->seller_type !== null && isset($data['seller_type']) && $application->seller_type !== $data['seller_type'];
            if ($step === 6) {
                $accountNumber = $data['account_number'] ?? null;
                unset($data['account_number']);

                if (isset($data['holder_name']) || $application->payoutProfile !== null) {
                    $holder = mb_strtolower(trim((string) ($data['holder_name'] ?? $application->payoutProfile?->holder_name)));
                    $owners = array_values(array_filter([
                        mb_strtolower(trim((string) $application->seller_name)),
                        mb_strtolower(trim((string) $application->business_name)),
                    ]));

                    $data['name_requires_review'] = ! in_array($holder, $owners, true);
                }

                if (filled($accountNumber)) {
                    $data['account_number'] = $accountNumber;
                }

                if ($data !== []) {
                    ($application->payoutProfile ?? $application->payoutProfile()->make())->fill($data)->save();
                }
            } else {
                foreach (['logo', 'banner', 'id_front', 'id_back', 'selfie', 'business_registration', 'bir_certificate'] as $upload) {
                    // Only a file this step validated is stored; any other is ignored, never saved unchecked.
                    if (array_key_exists($upload, $rules) && $request->hasFile($upload)) {
                        $file = $request->file($upload);
                        $path = $step === 3
                            ? $storePublicImage->handle($file, 'seller-applications/'.$application->id, 'public')
                            : ($file->store('seller-applications/'.$application->id, 'chat_attachments') ?: throw new RuntimeException('Document upload failed.'));
                        if ($step === 3) {
                            if ($application->{$upload.'_path'}) {
                                $replacedFiles[] = ['public', $application->{$upload.'_path'}];
                            }
                            $data[$upload.'_path'] = $path;
                        } else {
                            // A replaced ID document must not linger on disk once its record points elsewhere.
                            $document = $documents->get($upload) ?? $application->documents()->make(['type' => $upload]);
                            if ($document->exists) {
                                $replacedFiles[] = ['chat_attachments', $document->path];
                            }
                            $document->fill(['path' => $path, 'mime_type' => $file->getMimeType()])->save();
                        }
                        unset($data[$upload]);
                    }
                }
                $application->fill($data);
            }
            $completed = $application->completed_steps ?? [];
            if ($sellerTypeChanged) {
                $completed = array_values(array_diff($completed, [2, 5, 6]));
            }
            if (! $partial) {
                $completed[] = $step;
            }
            $application->completed_steps = array_values(array_unique($completed));
            $application->status ??= 'draft';
            try {
                $user->sellerApplications()->save($application);
            } catch (UniqueConstraintViolationException) {
                // Another applicant claimed the name or link between the check above and this save.
                throw ValidationException::withMessages(['store_name' => 'This store name or link was just taken. Choose another.']);
            }
            if ($application->wasRecentlyCreated) {
                $application->statusHistory()->create(['actor_user_id' => $user->id, 'from_status' => null, 'to_status' => 'draft', 'created_at' => now()]);
            }
            DB::afterCommit(function () use ($replacedFiles): void {
                foreach ($replacedFiles as [$disk, $path]) {
                    Storage::disk($disk)->delete($path);
                }
            });

            $exit = $request->boolean('exit');
            $redirect = $partial
                ? ($exit ? route('profile.edit') : route('seller-application.create', ['step' => $step]))
                : ($exit ? route('profile.edit') : route('seller-application.create', ['step' => $step + 1]));

            if ($request->expectsJson()) {
                if ($partial && ! $exit) {
                    // Autosave: nothing navigates, so the page only needs to know it landed.
                    return response()->json(['saved_at' => now()->toIso8601String()]);
                }

                session()->flash('status', $exit ? 'Draft saved. You can finish your application anytime.' : 'Draft saved.');

                return response()->json(['redirect' => $redirect]);
            }

            return redirect($redirect)->with('status', $exit ? 'Draft saved. You can finish your application anytime.' : 'Draft saved.');
        });
    }

    public function storeLink(Request $request): JsonResponse
    {
        $slug = Str::slug((string) $request->query('slug', ''));

        if (mb_strlen($slug) < 3) {
            return response()->json(['available' => false, 'message' => 'Use at least 3 letters or numbers.']);
        }

        if (in_array($slug, ['admin', 'seller', 'support', 'jmmart', 'official', 'help', 'login'], true)) {
            return response()->json(['available' => false, 'message' => 'That link is reserved.']);
        }

        $application = $request->user()->sellerApplications()->latest()->first();

        $taken = SellerApplication::query()
            ->where('store_slug', $slug)
            ->when($application, fn ($query) => $query->whereKeyNot($application->id))
            ->exists()
            || Store::query()->where('slug', $slug)->exists();

        return response()->json([
            'available' => ! $taken,
            'message' => $taken ? 'That link is already taken.' : 'Available.',
        ]);
    }

    public function store(StoreSellerApplicationRequest $request): RedirectResponse
    {
        return DB::transaction(function () use ($request): RedirectResponse {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if ($user->tenantMemberships()->exists()) {
                return to_route('seller.dashboard');
            }
            $application = $user->sellerApplications()->latest()->lockForUpdate()->first();
            if (! $application) {
                throw ValidationException::withMessages(['application' => 'Complete the application steps before submitting.']);
            }
            abort_unless(in_array($application->status, ['draft', 'more_information_required'], true), 403);
            if (array_diff(range(1, 6), $application->completed_steps ?? [])) {
                throw ValidationException::withMessages(['application' => 'Complete every application step before submitting.']);
            }
            $application->payoutProfile?->update(['name_requires_review' => ! in_array(mb_strtolower(trim($application->payoutProfile->holder_name)), [mb_strtolower(trim($application->seller_name ?? '')), mb_strtolower(trim($application->business_name ?? ''))], true)]);
            $policies = ['seller_agreement', 'terms', 'privacy', 'prohibited_products', 'returns_refunds', 'fees'];
            foreach ($policies as $policy) {
                if (($request->input('agreements.'.$policy)) !== '1') {
                    throw ValidationException::withMessages(['agreements.'.$policy => 'You must accept this policy.']);
                }
                $version = $policy === 'fees' ? (string) config('marketplace.platform_fee.policy_version') : '1.0';
                $application->policyAcceptances()->firstOrCreate(['policy_type' => $policy, 'version' => $version], ['user_id' => $user->id, 'ip_address' => $request->ip(), 'user_agent' => substr($request->userAgent() ?? '', 0, 500), 'accepted_at' => now()]);
            }
            $old = $application->status;
            $application->update(['status' => 'submitted', 'submitted_at' => now(), 'correction_steps' => null]);
            $application->statusHistory()->create(['actor_user_id' => $user->id, 'from_status' => $old, 'to_status' => 'submitted', 'created_at' => now()]);
            AuditLog::query()->create(['actor_user_id' => $user->id, 'action' => 'seller_application.submitted', 'entity_type' => SellerApplication::class, 'entity_id' => $application->id, 'new_values' => ['status' => 'submitted'], 'ip_address' => $request->ip()]);

            return to_route('seller-application.create')->with('status', 'Seller application submitted for review.');
        });
    }
}
