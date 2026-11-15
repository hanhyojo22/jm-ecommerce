<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksTenantMembership
{
    /**
     * Whether the user is an active member of the record's company who covers its branch: head
     * office covers every branch, branch staff only their own (and only while it is active).
     *
     * The tenant and store ids always come from the record being authorised, never from request input.
     */
    private function isActiveTenantMember(User $user, ?string $tenantId, ?string $storeId): bool
    {
        if ($tenantId === null || ! $user->is_active || $user->account_type !== 'seller') {
            return false;
        }

        return $user->tenantMemberships()
            ->where('tenant_id', $tenantId)
            ->whereIn('role', ['owner', 'manager', 'staff'])
            ->whereHas('tenant', fn ($query) => $query->where('status', 'active'))
            ->where(fn ($query) => $query
                ->whereNull('store_id')
                ->orWhere(fn ($branch) => $branch->where('store_id', $storeId)->whereHas('store', fn ($store) => $store->where('status', 'active'))))
            ->exists();
    }
}
