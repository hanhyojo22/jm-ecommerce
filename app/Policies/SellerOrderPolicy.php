<?php

namespace App\Policies;

use App\Models\SellerOrder;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantMembership;
use Illuminate\Auth\Access\Response;

class SellerOrderPolicy
{
    use ChecksTenantMembership;

    /**
     * Determine whether the user can view the store order.
     *
     * The buyer who placed it, an active member of the selling tenant, and a
     * platform administrator may read it. Everyone else gets a 404.
     */
    public function view(User $user, SellerOrder $sellerOrder): bool|Response
    {
        if ($user->isPlatformAdministrator()) {
            return true;
        }

        if ($this->isBuyer($user, $sellerOrder) || $this->isActiveTenantMember($user, $sellerOrder->tenant_id)) {
            return true;
        }

        return Response::denyAsNotFound();
    }

    /**
     * Determine whether the buyer can confirm the parcel arrived.
     */
    public function receive(User $user, SellerOrder $sellerOrder): bool|Response
    {
        return $this->isBuyer($user, $sellerOrder)
            ? true
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the buyer can open a support request about the store order.
     */
    public function openIssue(User $user, SellerOrder $sellerOrder): bool|Response
    {
        return $this->receive($user, $sellerOrder);
    }

    /**
     * Determine whether the buyer can open a return case for the store order.
     */
    public function openReturn(User $user, SellerOrder $sellerOrder): bool|Response
    {
        return $this->receive($user, $sellerOrder);
    }

    private function isBuyer(User $user, SellerOrder $sellerOrder): bool
    {
        return $user->is_active && $sellerOrder->order()->where('user_id', $user->id)->exists();
    }
}
