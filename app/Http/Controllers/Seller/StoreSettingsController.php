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
     * The GCash, Maya, and bank accounts buyers pay this store into. Each is offered at checkout
     * only once it is complete (see Store::acceptsWallet).
     */
    public function updateWallets(Request $request, StorePublicImage $storePublicImage): RedirectResponse
    {
        $tenant = $this->managedTenant($request);
        // Sellers copy numbers as "0917 123 4567" or "+63 917-123-4567"; store them as plain digits.
        foreach (Payment::DIRECT_METHODS as $method) {
            if (is_string($request->input("{$method}_number"))) {
                $request->merge(["{$method}_number" => preg_replace('/[\s-]+/', '', $request->input("{$method}_number"))]);
            }
        }
        $rules = [
            'bank_name' => ['nullable', 'required_with:bank_number', 'string', 'max:100'],
            'bank_account_name' => ['nullable', 'required_with:bank_number', 'string', 'max:150'],
        ];
        foreach (Payment::DIRECT_METHODS as $method) {
            // A QR without its number would be dropped on save, so ask for the number instead.
            $rules["{$method}_number"] = $method === 'bank'
                ? ['nullable', "required_with:{$method}_qr", 'string', 'regex:/^\d{6,30}$/']
                : ['nullable', "required_with:{$method}_qr", 'string', 'regex:/^(?:\+63|0)9\d{9}$/'];
            $rules["{$method}_qr"] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'];
        }
        $data = $request->validate($rules, [
            'gcash_number.regex' => 'Enter a mobile number like 09171234567.',
            'maya_number.regex' => 'Enter a mobile number like 09171234567.',
            'bank_number.regex' => 'Enter the account number using digits only.',
            'gcash_number.required_with' => 'Add the GCash number that goes with this QR code.',
            'maya_number.required_with' => 'Add the Maya number that goes with this QR code.',
            'bank_number.required_with' => 'Add the account number that goes with this QR code.',
        ]);

        $uploadedPaths = [];
        try {
            foreach (Payment::DIRECT_METHODS as $method) {
                if ($request->hasFile("{$method}_qr")) {
                    $uploadedPaths["{$method}_qr_path"] = $storePublicImage->handle($request->file("{$method}_qr"), 'store-wallet-qr', 'public');
                }
            }

            $replacedPaths = DB::transaction(function () use ($request, $tenant, $data, $uploadedPaths): array {
                $store = Store::query()->where('tenant_id', $tenant->id)->lockForUpdate()->firstOrFail();
                $fields = ['gcash_number', 'gcash_qr_path', 'maya_number', 'maya_qr_path', 'bank_name', 'bank_account_name', 'bank_number', 'bank_qr_path'];
                $oldValues = $store->only($fields);
                $newValues = [...$oldValues, ...$uploadedPaths];
                foreach (Payment::DIRECT_METHODS as $method) {
                    $newValues["{$method}_number"] = $data["{$method}_number"] ?? null;
                    // Clearing the number turns the method off, so its QR goes with it.
                    if ($newValues["{$method}_number"] === null) {
                        $newValues["{$method}_qr_path"] = null;
                    }
                }
                $newValues['bank_name'] = $newValues['bank_number'] === null ? null : $data['bank_name'];
                $newValues['bank_account_name'] = $newValues['bank_number'] === null ? null : $data['bank_account_name'];

                $store->update($newValues);

                AuditLog::query()->create([
                    'actor_user_id' => $request->user()->id,
                    'action' => 'store.wallets_updated',
                    'entity_type' => Store::class,
                    'entity_id' => $store->id,
                    'old_values' => $oldValues,
                    'new_values' => $newValues,
                    'ip_address' => $request->ip(),
                ]);

                $qrFields = ['gcash_qr_path', 'maya_qr_path', 'bank_qr_path'];

                return array_diff(array_filter(Arr::only($oldValues, $qrFields)), Arr::only($newValues, $qrFields));
            });
        } catch (Throwable $exception) {
            array_map(fn (string $path) => $this->deleteUploadedLogo($path), $uploadedPaths);
            report($exception);

            return back()->withErrors(['wallets' => 'We could not save your payment accounts. Please try again.'])->withInput();
        }

        array_map(fn (string $path) => $this->deleteUploadedLogo($path), $replacedPaths);

        return to_route('seller.store-settings.edit')->with('status', 'Payment accounts saved.');
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
