<?php

namespace App\Policies;

use App\Models\SellerApplication;
use App\Models\User;

class SellerApplicationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdministrator();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SellerApplication $sellerApplication): bool
    {
        return $user->isPlatformAdministrator() || $sellerApplication->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return in_array($user->account_type, ['buyer', 'seller'], true) && $user->hasVerifiedClerkEmail() && ! $user->tenantMemberships()->exists();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SellerApplication $sellerApplication): bool
    {
        return $sellerApplication->user_id === $user->id && in_array($sellerApplication->status, ['draft', 'more_information_required'], true);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SellerApplication $sellerApplication): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SellerApplication $sellerApplication): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SellerApplication $sellerApplication): bool
    {
        return false;
    }

    public function approve(User $user, SellerApplication $sellerApplication): bool
    {
        return $user->isPlatformAdministrator() && in_array($sellerApplication->status, ['pending', 'submitted', 'under_review'], true);
    }
}
