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

        $tenantId = $orderIssue->sellerOrder()->value('tenant_id');

        return $this->isActiveTenantMember($user, $tenantId)
            ? true
            : Response::denyAsNotFound();
    }
}
