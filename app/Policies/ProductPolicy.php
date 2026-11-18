<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->account_type === 'seller' && $user->sellerTenant() !== null;
    }

    public function create(User $user): bool
    {
        return $user->account_type === 'seller' && $user->headOfficeTenant() !== null;
    }

    public function view(User $user, Product $product): bool
    {
        return $user->isPlatformAdministrator() || $user->sellerTenant()?->id === $product->tenant_id;
    }

    public function update(User $user, Product $product): bool
    {
        return $user->headOfficeTenant()?->id === $product->tenant_id;
    }
}
