<?php

namespace App\Policies;

use App\Models\ProductReview;
use App\Models\User;

class ProductReviewPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdministrator() || $user->account_type === 'seller';
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ProductReview $productReview): bool
    {
        return $productReview->status === 'published'
            || $user->isPlatformAdministrator()
            || $productReview->user_id === $user->id
            || $user->tenantMemberships()->where('tenant_id', $productReview->product->tenant_id)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->account_type === 'buyer';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ProductReview $productReview): bool
    {
        return $productReview->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProductReview $productReview): bool
    {
        return $productReview->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ProductReview $productReview): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ProductReview $productReview): bool
    {
        return false;
    }
}
