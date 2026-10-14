<?php

namespace App\Http\Controllers;

use App\Actions\StorePublicImage;
use App\Http\Requests\StoreSellerApplicationRequest;
use App\Models\AuditLog;
use App\Models\SellerApplication;
use App\Models\Store;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SellerApplicationController extends Controller
{
    public function create(): View|RedirectResponse
    {
        $user = request()->user();

        if ($user->sellerTenant() !== null) {
            return to_route('seller.dashboard');
        }

        $membership = $user->tenantMemberships()->with('tenant.store')->oldest('created_at')->first();
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
            if ($user->sellerApplications()->whereIn('status', ['pending', 'submitted', 'under_review', 'approved', 'suspended'])->exists()) {
                throw ValidationException::withMessages(['application' => 'You already have an active seller application.']);
            }
            $application = $user->sellerApplications()->latest()->first() ?? new SellerApplication;
            abort_unless($application->status === null || in_array($application->status, ['draft', 'more_information_required'], true), 403);
            abort_if($application->status === 'more_information_required' && ! in_array($step, $application->correction_steps ?? [], true), 403);
            if ($step > 1 && ! in_array(1, $application->completed_steps ?? [], true)) {
                throw ValidationException::withMessages(['application' => 'Choose a seller type first.']);
            }

            $rules = match ($step) {
                1 => ['seller_type' => ['required', Rule::in(['individual', 'business'])]],
                2 => ['seller_name' => ['required', 'string', 'max:120'], 'phone' => ['required', 'regex:/^\\+?[0-9 ]{10,18}$/'], 'date_of_birth' => [$application->seller_type === 'individual' ? 'required' : 'nullable', 'date', 'before:-18 years'], 'business_name' => [$application->seller_type === 'business' ? 'required' : 'nullable', 'string', 'max:255'], 'registration_number' => [$application->seller_type === 'business' ? 'required' : 'nullable', 'string', 'max:100'], 'bir_details' => ['nullable', 'string', 'max:255'], 'representative_name' => [$application->seller_type === 'business' ? 'required' : 'nullable', 'string', 'max:120']],
                3 => ['store_name' => ['required', 'string', 'min:3', 'max:120', Rule::unique('seller_applications')->ignore($application->id), Rule::unique('stores', 'name')], 'store_slug' => ['required', 'string', 'min:3', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::notIn(['admin', 'seller', 'support', 'jmmart', 'official', 'help', 'login']), Rule::unique('seller_applications')->ignore($application->id), Rule::unique('stores', 'slug')], 'store_description' => ['required', 'string', 'max:2000'], 'store_category' => ['required', 'string', 'max:100'], 'support_contact' => ['required', 'string', 'max:120'], 'logo' => [$application->logo_path ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'], 'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']],
                4 => ['region' => ['required', 'string', 'max:100'], 'province' => ['required', 'string', 'max:100'], 'city' => ['required', 'string', 'max:100'], 'barangay' => ['required', 'string', 'max:100'], 'address_line' => ['required', 'string', 'max:255'], 'postal_code' => ['required', 'digits:4'], 'same_addresses' => ['nullable', 'boolean'], 'pickup_address' => ['required_unless:same_addresses,1', 'nullable', 'string', 'max:255'], 'return_address' => ['required_unless:same_addresses,1', 'nullable', 'string', 'max:255'], 'processing_days' => ['required', 'integer', 'between:1,30'], 'fulfillment_methods' => ['required', 'string', 'max:120'], 'delivery_areas' => ['required', 'string', 'max:1000']],
                5 => ['id_type' => ['required', Rule::in(['passport', 'drivers_license', 'national_id', 'other'])], 'id_number' => ['required', 'string', 'max:100'], 'id_front' => [$application->documents()->where('type', 'id_front')->exists() ? 'nullable' : 'required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'], 'id_back' => [in_array($request->input('id_type'), ['drivers_license', 'national_id'], true) && ! $application->documents()->where('type', 'id_back')->exists() ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'], 'selfie' => [$application->seller_type === 'individual' && ! $application->documents()->where('type', 'selfie')->exists() ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'], 'business_registration' => [$application->seller_type === 'business' && ! $application->documents()->where('type', 'business_registration')->exists() ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'], 'bir_certificate' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120']],
                6 => ['method' => ['required', Rule::in(['bank', 'wallet'])], 'provider' => ['required', 'string', 'max:120'], 'holder_name' => ['required', 'string', 'max:120'], 'account_number' => [$application->payoutProfile()->exists() ? 'nullable' : 'required', 'string', 'min:6', 'max:40', 'regex:/^[0-9A-Za-z -]+$/']],
            };
            if ($step === 3) {
                $request->merge(['store_slug' => Str::slug($request->input('store_slug', ''))]);
            }
            $data = $request->validate($rules);
            if ($step === 4 && ($data['same_addresses'] ?? false)) {
                $data['pickup_address'] = $data['return_address'] = $data['address_line'].', '.$data['barangay'].', '.$data['city'].', '.$data['province'].', '.$data['region'].' '.$data['postal_code'];
                if (mb_strlen($data['pickup_address']) > 255) {
                    throw ValidationException::withMessages(['address_line' => 'The combined address must be 255 characters or fewer.']);
                }
            }
            unset($data['same_addresses']);
            if ($step === 3 && preg_match('/\\b(official|jmmart|admin|support|fuck|shit|scam)\\b/i', $data['store_name'])) {
                throw ValidationException::withMessages(['store_name' => 'Choose a name that does not imply marketplace affiliation.']);
            }
            if ($step === 3 && preg_match('/(^|-)(official|jmmart|admin|support|fuck|shit|scam)(-|$)/', $data['store_slug'])) {
                throw ValidationException::withMessages(['store_slug' => 'Choose a different storefront URL.']);
            }
            if ($step === 3 && (SellerApplication::query()->whereRaw('LOWER(store_name) = ?', [mb_strtolower($data['store_name'])])->whereKeyNot($application->id)->exists() || Store::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($data['store_name'])])->exists())) {
                throw ValidationException::withMessages(['store_name' => 'This store name is already taken.']);
            }
            $sellerTypeChanged = $step === 1 && $application->seller_type !== null && $application->seller_type !== $data['seller_type'];
            if ($step === 6) {
                $accountNumber = $data['account_number'] ?? null;
                unset($data['account_number']);
                $data['name_requires_review'] = ! in_array(mb_strtolower(trim($data['holder_name'])), [mb_strtolower(trim($application->seller_name ?? '')), mb_strtolower(trim($application->business_name ?? ''))], true);
                if (filled($accountNumber)) {
                    $data['account_number'] = $accountNumber;
                }
                $application->payoutProfile()->updateOrCreate([], $data);
            } else {
                foreach (['logo', 'banner', 'id_front', 'id_back', 'selfie', 'business_registration', 'bir_certificate'] as $upload) {
                    if ($request->hasFile($upload)) {
                        $file = $request->file($upload);
                        $path = $step === 3
                            ? $storePublicImage->handle($file, 'seller-applications/'.$application->id, 'public')
                            : ($file->store('seller-applications/'.$application->id, 'chat_attachments') ?: throw new RuntimeException('Document upload failed.'));
                        if ($step === 3) {
                            $data[$upload.'_path'] = $path;
                        } else {
                            $application->documents()->updateOrCreate(['type' => $upload], ['path' => $path, 'mime_type' => $file->getMimeType()]);
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
            $completed[] = $step;
            $application->completed_steps = array_values(array_unique($completed));
            $application->status ??= 'draft';
            $user->sellerApplications()->save($application);
            if ($application->wasRecentlyCreated) {
                $application->statusHistory()->create(['actor_user_id' => $user->id, 'from_status' => null, 'to_status' => 'draft', 'created_at' => now()]);
            }

            $redirect = route('seller-application.create', ['step' => $step + 1]);
            if ($request->expectsJson()) {
                session()->flash('status', 'Draft saved.');

                return response()->json(['redirect' => $redirect]);
            }

            return redirect($redirect)->with('status', 'Draft saved.');
        });
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
                $application->policyAcceptances()->firstOrCreate(['policy_type' => $policy, 'version' => '1.0'], ['user_id' => $user->id, 'ip_address' => $request->ip(), 'user_agent' => substr($request->userAgent() ?? '', 0, 500), 'accepted_at' => now()]);
            }
            $old = $application->status;
            $application->update(['status' => 'submitted', 'submitted_at' => now(), 'correction_steps' => null]);
            $application->statusHistory()->create(['actor_user_id' => $user->id, 'from_status' => $old, 'to_status' => 'submitted', 'created_at' => now()]);
            AuditLog::query()->create(['actor_user_id' => $user->id, 'action' => 'seller_application.submitted', 'entity_type' => SellerApplication::class, 'entity_id' => $application->id, 'new_values' => ['status' => 'submitted'], 'ip_address' => $request->ip()]);

            return to_route('seller-application.create')->with('status', 'Seller application submitted for review.');
        });
    }
}
