<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->account_type === 'seller' && $user->tenantMemberships()->whereIn('role', ['owner', 'manager', 'staff'])->exists();
    }

    public function create(User $user): bool
    {
        return $user->account_type === 'seller' && $user->tenantMemberships()->whereIn('role', ['owner', 'manager'])->exists();
    }

    public function view(User $user, Product $product): bool
    {
        return $user->isPlatformAdministrator() || $user->tenantMemberships()
            ->where('tenant_id', $product->tenant_id)
            ->exists();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->tenantMemberships()
            ->where('tenant_id', $product->tenant_id)
            ->whereIn('role', ['owner', 'manager'])
            ->exists();
    }
}
