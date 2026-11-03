<?php

namespace App\Policies;

use App\Models\ReturnCase;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantMembership;
use Illuminate\Auth\Access\Response;

class ReturnCasePolicy
{
    use ChecksTenantMembership;

    /**
     * Determine whether the user can view the return case.
     */
    public function view(User $user, ReturnCase $returnCase): bool|Response
    {
        if ($user->isPlatformAdministrator()) {
            return true;
        }

        if ($this->isBuyer($user, $returnCase)) {
            return true;
        }

        $tenantId = $returnCase->sellerOrder()->value('tenant_id');

        return $this->isActiveTenantMember($user, $tenantId)
            ? true
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the buyer can answer the case: accept, appeal, hand off, or confirm.
     */
    public function respond(User $user, ReturnCase $returnCase): bool|Response
    {
        return $this->isBuyer($user, $returnCase)
            ? true
            : Response::denyAsNotFound();
    }

    private function isBuyer(User $user, ReturnCase $returnCase): bool
    {
        return $user->is_active && $returnCase->buyer_id === $user->id;
    }
}
