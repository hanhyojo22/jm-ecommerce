<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksTenantMembership
{
    /**
     * Whether the user is an active member of a tenant whose store is also active.
     *
     * The tenant id always comes from the record being authorised, never from request input.
     */
    private function isActiveTenantMember(User $user, ?string $tenantId): bool
    {
        if ($tenantId === null || ! $user->is_active || $user->account_type !== 'seller') {
            return false;
        }

        return $user->tenantMemberships()
            ->where('tenant_id', $tenantId)
            ->whereIn('role', ['owner', 'manager', 'staff'])
            ->whereHas('tenant', fn ($query) => $query
                ->where('status', 'active')
                ->whereHas('store', fn ($store) => $store->where('status', 'active')))
            ->exists();
    }
}
