<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

class StorePolicy
{
    /**
     * Determine whether the user can view any stores.
     */
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdministrator();
    }

    /**
     * Determine whether the user can view the store.
     */
    public function view(User $user, Store $store): bool
    {
        return $user->isPlatformAdministrator();
    }

    /**
     * Determine whether the user can suspend or reactivate the store.
     */
    public function moderate(User $user, Store $store): bool
    {
        return $user->isPlatformAdministrator();
    }
}
