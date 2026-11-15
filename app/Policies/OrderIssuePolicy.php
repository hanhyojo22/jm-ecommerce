<?php

namespace App\Policies;

use App\Models\OrderIssue;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantMembership;
use Illuminate\Auth\Access\Response;

class OrderIssuePolicy
{
    use ChecksTenantMembership;

    /**
     * Determine whether the user can view the support request.
     */
    public function view(User $user, OrderIssue $orderIssue): bool|Response
    {
        if ($user->isPlatformAdministrator()) {
            return true;
        }

        if ($user->is_active && $orderIssue->buyer_id === $user->id) {
            return true;
        }

        $sellerOrder = $orderIssue->sellerOrder()->first(['tenant_id', 'store_id']);

        return $this->isActiveTenantMember($user, $sellerOrder?->tenant_id, $sellerOrder?->store_id)
            ? true
            : Response::denyAsNotFound();
    }
}
