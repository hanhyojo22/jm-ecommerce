<?php

namespace App\Http\Controllers\Seller;

use App\Actions\StorePublicImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateStoreSettingsRequest;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class StoreSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $tenant = $this->managedTenant($request);
        $undo = $request->session()->get('seller_store_settings_undo');
        $canUndo = is_array($undo)
            && ($undo['tenant_id'] ?? null) === $tenant->id
            && ($undo['actor_user_id'] ?? null) === $request->user()->id
            && ($undo['expires_at'] ?? 0) > now()->getTimestamp();

        if ($undo !== null && ! $canUndo) {
            $request->session()->forget('seller_store_settings_undo');
        }

        return view('seller.store-settings.edit', ['store' => $tenant->store, 'canUndo' => $canUndo]);
    }

    public function update(UpdateStoreSettingsRequest $request, StorePublicImage $storePublicImage): RedirectResponse
    {
        $tenant = $request->user()->sellerTenant() ?? abort(403);
        $data = $request->safe()->only(['description', 'phone', 'address_line', 'city']);
        try {
            $newLogoPath = $request->hasFile('logo')
                ? $storePublicImage->handle($request->file('logo'), 'store-logos', 'public')
                : null;
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['logo' => 'The logo could not be saved. Please try again.'])->withInput();
        }

        if ($request->hasFile('logo') && ! is_string($newLogoPath)) {
            return back()->withErrors(['logo' => 'The logo could not be saved. Please try again.'])->withInput();
        }

        try {
            $change = DB::transaction(function () use ($request, $tenant, $data, $newLogoPath): array {
                $lockedStore = Store::query()
                    ->where('tenant_id', $tenant->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $oldLogoPath = $lockedStore->logo_path;
                $logoPath = $newLogoPath ?? ($request->boolean('remove_logo') ? null : $oldLogoPath);
                $oldValues = $lockedStore->only(['description', 'phone', 'address_line', 'city', 'logo_path']);
                $newValues = [...$data, 'logo_path' => $logoPath];

                $lockedStore->update($newValues);

                AuditLog::query()->create([
                    'actor_user_id' => $request->user()->id,
                    'action' => 'store.updated',
                    'entity_type' => Store::class,
                    'entity_id' => $lockedStore->id,
                    'old_values' => $oldValues,
                    'new_values' => $newValues,
                    'ip_address' => $request->ip(),
                ]);

                return ['store_id' => $lockedStore->id, 'old_values' => $oldValues, 'new_values' => $newValues];
            });
        } catch (Throwable $exception) {
            $this->deleteUploadedLogo(is_string($newLogoPath) ? $newLogoPath : null);
            report($exception);

            return back()->withErrors(['settings' => 'We could not save these changes. Your existing shop details were kept. Please try again.'])->withInput();
        }

        $oldLogoPath = $change['old_values']['logo_path'];
        $logoPath = $change['new_values']['logo_path'];

        if ($oldLogoPath !== null && $oldLogoPath !== $logoPath) {
            $this->deleteUploadedLogo($oldLogoPath);
        }

        $request->session()->forget('seller_store_settings_undo');

        if ($oldLogoPath === $logoPath && $change['old_values'] !== $change['new_values']) {
            $request->session()->put('seller_store_settings_undo', [
                'tenant_id' => $tenant->id,
                'store_id' => $change['store_id'],
                'actor_user_id' => $request->user()->id,
                'before' => $change['old_values'],
                'after' => $change['new_values'],
                'expires_at' => now()->addMinutes(5)->getTimestamp(),
            ]);
        }

        return to_route('seller.store-settings.edit')->with('status', 'Store details saved.');
    }

    /**
     * Sets up, edits, or turns off one way buyers pay this store directly. Each method saves on its
     * own so editing Maya can never clear GCash. A method is offered at checkout only once it is
     * complete (see Store::acceptsWallet).
     */
    public function updateWallets(Request $request, StorePublicImage $storePublicImage): RedirectResponse
    {
        $tenant = $this->managedTenant($request);
        $method = $request->validate(['method' => ['required', Rule::in(Payment::DIRECT_METHODS)]])['method'];
        $label = Payment::METHOD_LABELS[$method];
        $fields = $method === 'bank' ? ['bank_name', 'bank_account_name', 'bank_number', 'bank_qr_path'] : ["{$method}_number", "{$method}_qr_path"];

        if ($request->boolean('disable')) {
            $this->saveWalletFields($request, $tenant, $fields, array_fill_keys($fields, null));

            return to_route('seller.store-settings.edit')->with('status', "{$label} is turned off. Buyers can no longer choose it at checkout.");
        }

        // Sellers copy numbers as "0917 123 4567" or "+63 917-123-4567"; store them as plain digits.
        if (is_string($request->input("{$method}_number"))) {
            $request->merge(["{$method}_number" => preg_replace('/[\s-]+/', '', $request->input("{$method}_number"))]);
        }
        $rules = [
            "{$method}_number" => $method === 'bank' ? ['required', 'string', 'regex:/^\d{6,30}$/'] : ['required', 'string', 'regex:/^(?:\+63|0)9\d{9}$/'],
            "{$method}_qr" => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
        if ($method === 'bank') {
            $rules['bank_name'] = ['required', 'string', 'max:100'];
            $rules['bank_account_name'] = ['required', 'string', 'max:150'];
        }
        $data = $request->validate($rules, [
            "{$method}_number.required" => $method === 'bank' ? 'Enter the account number.' : "Enter your {$label} mobile number.",
            "{$method}_number.regex" => $method === 'bank' ? 'Enter the account number using digits only.' : 'Enter a mobile number like 09171234567.',
            'bank_name.required' => 'Enter the bank name.',
            'bank_account_name.required' => 'Enter the account name exactly as it appears on the account.',
        ]);

        try {
            $uploadedPath = $request->hasFile("{$method}_qr") ? $storePublicImage->handle($request->file("{$method}_qr"), 'store-wallet-qr', 'public') : null;
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(["{$method}_qr" => 'The QR code could not be uploaded. Please try again.'])->withInput();
        }

        $values = Arr::only($data, array_diff($fields, ["{$method}_qr_path"]));
        if ($uploadedPath !== null) {
            $values["{$method}_qr_path"] = $uploadedPath;
        }

        try {
            $this->saveWalletFields($request, $tenant, $fields, $values);
        } catch (Throwable $exception) {
            $this->deleteUploadedLogo($uploadedPath);
            report($exception);

            return back()->withErrors(["{$method}_number" => "We could not save your {$label} details. Please try again."])->withInput();
        }

        $store = $tenant->store()->first();

        return to_route('seller.store-settings.edit')->with('status', $store->acceptsWallet($method)
            ? "{$label} saved. Buyers can now choose it at checkout."
            : "{$label} saved. Add your QR code to offer it at checkout.");
    }

    /**
     * @param  array<int, string>  $fields
     * @param  array<string, ?string>  $values
     */
    private function saveWalletFields(Request $request, Tenant $tenant, array $fields, array $values): void
    {
        $replacedQrPath = DB::transaction(function () use ($request, $tenant, $fields, $values): ?string {
            $store = Store::query()->where('tenant_id', $tenant->id)->lockForUpdate()->firstOrFail();
            $oldValues = $store->only($fields);
            $store->update($values);

            AuditLog::query()->create([
                'actor_user_id' => $request->user()->id,
                'action' => 'store.wallets_updated',
                'entity_type' => Store::class,
                'entity_id' => $store->id,
                'old_values' => $oldValues,
                'new_values' => [...$oldValues, ...$values],
                'ip_address' => $request->ip(),
            ]);

            $qrField = collect($fields)->first(fn (string $field): bool => str_ends_with($field, '_qr_path'));

            return array_key_exists($qrField, $values) && $oldValues[$qrField] !== $values[$qrField] ? $oldValues[$qrField] : null;
        });

        $this->deleteUploadedLogo($replacedQrPath);
    }

    public function undo(Request $request): RedirectResponse
    {
        $tenant = $this->managedTenant($request);
        $undo = $request->session()->get('seller_store_settings_undo');

        if (! is_array($undo)
            || ($undo['tenant_id'] ?? null) !== $tenant->id
            || ($undo['actor_user_id'] ?? null) !== $request->user()->id
            || ($undo['expires_at'] ?? 0) <= now()->getTimestamp()) {
            $request->session()->forget('seller_store_settings_undo');

            return back()->withErrors(['undo' => 'These saved changes are no longer available to undo.']);
        }

        $fields = ['description', 'phone', 'address_line', 'city', 'logo_path'];
        $before = $undo['before'] ?? null;
        $after = $undo['after'] ?? null;

        if (! is_array($before) || ! is_array($after)
            || array_keys($before) !== $fields
            || array_keys($after) !== $fields
            || $before['logo_path'] !== $after['logo_path']) {
            $request->session()->forget('seller_store_settings_undo');

            return back()->withErrors(['undo' => 'These saved changes are no longer available to undo.']);
        }

        $restored = DB::transaction(function () use ($request, $tenant, $undo, $before, $after): bool {
            $store = Store::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($undo['store_id'] ?? 0)
                ->lockForUpdate()
                ->first();

            if ($store === null || $store->only(array_keys($after)) !== $after) {
                return false;
            }

            $store->update($before);

            AuditLog::query()->create([
                'actor_user_id' => $request->user()->id,
                'action' => 'store.update_undone',
                'entity_type' => Store::class,
                'entity_id' => $store->id,
                'old_values' => $after,
                'new_values' => $before,
                'ip_address' => $request->ip(),
            ]);

            return true;
        });

        if (! $restored) {
            $request->session()->forget('seller_store_settings_undo');

            return back()->withErrors(['undo' => 'Store details changed after this save, so the older values were not restored.']);
        }

        $request->session()->forget('seller_store_settings_undo');

        return to_route('seller.store-settings.edit')->with('status', 'Last store details save undone.');
    }

    private function managedTenant(Request $request): Tenant
    {
        $tenant = $request->user()->sellerTenant();

        abort_unless(
            $tenant !== null && $request->user()->tenantMemberships()
                ->where('tenant_id', $tenant->id)
                ->whereIn('role', ['owner', 'manager'])
                ->exists(),
            403,
        );

        return $tenant;
    }

    private function deleteUploadedLogo(?string $path): void
    {
        if ($path === null) {
            return;
        }

        try {
            Storage::disk('public')->delete($path);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
