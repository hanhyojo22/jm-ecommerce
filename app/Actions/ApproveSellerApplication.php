<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\SellerApplication;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class ApproveSellerApplication
{
    public function handle(User $administrator, SellerApplication $application, ?string $ipAddress = null): Tenant
    {
        return DB::transaction(function () use ($administrator, $application, $ipAddress): Tenant {
            $application = SellerApplication::query()->lockForUpdate()->findOrFail($application->id);

            if (! in_array($application->status, ['pending', 'submitted', 'under_review'], true)) {
                throw new LogicException('Only pending seller applications can be approved.');
            }

            $seller = User::query()->lockForUpdate()->findOrFail($application->user_id);

            if (! $seller->is_active || ! $seller->hasVerifiedClerkEmail()) {
                throw new LogicException('Only active sellers with a verified Clerk email can be approved.');
            }

            if ($seller->tenantMemberships()->exists()) {
                throw new LogicException('This seller already has a store.');
            }

            // Applications submitted before the fee terms existed accepted an older version;
            // those stores accept the terms from their dashboard before they are charged.
            $acceptedFeeTerms = $application->policyAcceptances()
                ->where('policy_type', 'fees')
                ->where('version', (string) config('marketplace.platform_fee.policy_version'))
                ->exists();

            $tenant = Tenant::query()->create([
                'name' => $application->store_name,
                'slug' => $this->uniqueSlug($application->store_slug ?? $application->store_name, Tenant::class),
                'status' => 'active',
                'platform_fee_accepted_at' => $acceptedFeeTerms ? now() : null,
                'platform_fee_policy_version' => $acceptedFeeTerms ? (string) config('marketplace.platform_fee.policy_version') : null,
                'platform_fee_rate' => $acceptedFeeTerms ? (string) config('marketplace.platform_fee.rate') : null,
            ]);

            TenantMember::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $application->user_id,
                'role' => 'owner',
            ]);

            Store::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $application->store_name,
                'slug' => $this->uniqueSlug($application->store_slug ?? $application->store_name, Store::class),
                'description' => $application->store_description,
                'logo_path' => $application->logo_path,
                'banner_path' => $application->banner_path,
                'phone' => $application->phone,
                'address_line' => $application->address_line,
                'city' => $application->city,
                ...$application->only(Store::LOCATION_DETAILS),
                'status' => 'active',
            ]);

            $oldStatus = $application->status;
            $application->update([
                'tenant_id' => $tenant->id,
                'status' => 'approved',
                'reviewed_at' => now(),
                'reviewed_by' => $administrator->id,
            ]);

            $application->statusHistory()->create(['actor_user_id' => $administrator->id, 'from_status' => $oldStatus, 'to_status' => 'approved', 'created_at' => now()]);
            $application->reviews()->create(['reviewer_id' => $administrator->id, 'action' => 'approved']);

            $seller->update(['account_type' => 'seller', 'preferred_workspace' => 'seller']);

            AuditLog::query()->create([
                'actor_user_id' => $administrator->id,
                'action' => 'seller_application.approved',
                'entity_type' => SellerApplication::class,
                'entity_id' => $application->id,
                'new_values' => ['tenant_id' => $tenant->id, 'status' => 'approved'],
                'ip_address' => $ipAddress,
            ]);

            return $tenant;
        });
    }

    private function uniqueSlug(string $name, string $model): string
    {
        $base = Str::slug($name) ?: 'store';
        $slug = $base;

        while ($model::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::ulid());
        }

        return $slug;
    }
}
