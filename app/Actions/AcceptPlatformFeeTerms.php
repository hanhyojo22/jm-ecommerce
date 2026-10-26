<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\SellerApplication;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcceptPlatformFeeTerms
{
    /**
     * The store agrees to the current fee terms: a store approved before the terms existed is
     * charged only on orders delivered from now on, and one on older terms moves to the new rate.
     */
    public function handle(User $owner, Tenant $tenant, ?string $ipAddress = null, ?string $userAgent = null): Tenant
    {
        return DB::transaction(function () use ($owner, $tenant, $ipAddress, $userAgent): Tenant {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);

            if ($tenant->hasAcceptedCurrentFeeTerms()) {
                return $tenant;
            }

            $version = (string) config('marketplace.platform_fee.policy_version');
            SellerApplication::query()->where('tenant_id', $tenant->id)->latest()->first()?->policyAcceptances()->firstOrCreate(
                ['policy_type' => 'fees', 'version' => $version],
                ['user_id' => $owner->id, 'ip_address' => $ipAddress, 'user_agent' => substr($userAgent ?? '', 0, 500), 'accepted_at' => now()],
            );
            $tenant->update([
                'platform_fee_accepted_at' => $tenant->platform_fee_accepted_at ?? now(),
                'platform_fee_policy_version' => $version,
                'platform_fee_rate' => (string) config('marketplace.platform_fee.rate'),
            ]);

            AuditLog::query()->create([
                'actor_user_id' => $owner->id,
                'action' => 'platform_fee.terms_accepted',
                'entity_type' => Tenant::class,
                'entity_id' => $tenant->id,
                'new_values' => ['policy_version' => $version],
                'ip_address' => $ipAddress,
            ]);

            return $tenant;
        });
    }
}
