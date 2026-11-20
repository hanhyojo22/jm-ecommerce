<?php

namespace App\Http\Controllers\Seller;

use App\Actions\StorePublicImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateStoreSettingsRequest;
use App\Models\AuditLog;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class StoreSettingsController extends Controller
{
    /**
     * Saved fields in the order the undo payload is checked against.
     *
     * @var list<string>
     */
    private const UNDO_FIELDS = ['name', 'description', 'phone', 'address_line', 'city', 'status', ...Store::LOCATION_DETAILS, 'logo_path'];

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

        return view('seller.store-settings.edit', ['store' => $this->branch($request, $tenant), 'canUndo' => $canUndo, 'canManageBranches' => $this->member($request)->isHeadOffice()]);
    }

    public function update(UpdateStoreSettingsRequest $request, StorePublicImage $storePublicImage): RedirectResponse
    {
        $tenant = $this->managedTenant($request);
        $branch = $this->branch($request, $tenant);
        $data = [
            ...$request->safe()->only(['description', 'phone', 'address_line', 'city']),
            ...$this->locationDetails($request),
        ];
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
            $change = DB::transaction(function () use ($request, $tenant, $branch, $data, $newLogoPath): array {
                $lockedStore = Store::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereKey($branch->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $oldLogoPath = $lockedStore->logo_path;
                $logoPath = $newLogoPath ?? ($request->boolean('remove_logo') ? null : $oldLogoPath);
                $oldValues = $lockedStore->only(self::UNDO_FIELDS);
                // Head office opens or closes a branch; a branch manager cannot close their own. One the platform suspended stays suspended. Read from the locked row.
                $status = $lockedStore->status === 'suspended' || ! $this->member($request)->isHeadOffice() ? $lockedStore->status : ($request->validated('status') ?? $lockedStore->status);
                // Only head office renames a branch; the slug (store URL) keeps the original name.
                $name = $this->member($request)->isHeadOffice() && $request->filled('name') ? trim($request->validated('name')) : $lockedStore->name;
                // Same key order as UNDO_FIELDS, which undo checks.
                $newValues = array_replace(array_fill_keys(self::UNDO_FIELDS, null), $data, ['name' => $name, 'status' => $status, 'logo_path' => $logoPath]);

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

        return to_route('seller.store-settings.edit', ['branch' => $branch->id])->with('status', 'Branch details saved.');
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

        $fields = self::UNDO_FIELDS;
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

        return to_route('seller.store-settings.edit', ['branch' => $undo['store_id']])->with('status', 'Last branch details save undone.');
    }

    /**
     * Every location field, with numbers in the same form the model casts return, so a saved
     * value compares equal to what is read back for undo.
     *
     * @return array<string, string|int|float|null>
     */
    private function locationDetails(UpdateStoreSettingsRequest $request): array
    {
        return collect(Store::LOCATION_DETAILS)->mapWithKeys(function (string $field) use ($request): array {
            $value = $request->validated($field);

            return [$field => match (true) {
                $value === null => null,
                in_array($field, ['latitude', 'longitude'], true) => round((float) $value, 7),
                in_array($field, ['processing_days', 'delivery_radius_km'], true) => (int) $value,
                default => $value,
            }];
        })->all();
    }

    /**
     * The branch being edited. A branch manager always edits their own branch. Head office edits the
     * one named in the request, looked up inside its own company so another company's branch is a
     * 404, or the first branch when none is named.
     */
    private function branch(Request $request, Tenant $tenant): Store
    {
        $member = $this->member($request);

        if (! $member->isHeadOffice()) {
            return $member->store;
        }

        return $request->filled('branch')
            ? $tenant->stores()->whereKey($request->string('branch')->toString())->firstOrFail()
            : $tenant->store;
    }

    private function managedTenant(Request $request): Tenant
    {
        return $this->member($request)->tenant;
    }

    private function member(Request $request): TenantMember
    {
        return $request->user()->sellerMembership(['owner', 'manager']) ?? abort(403);
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
