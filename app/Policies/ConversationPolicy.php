<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantMembership;
use Illuminate\Auth\Access\Response;

class ConversationPolicy
{
    use ChecksTenantMembership;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if (in_array($user->account_type, ['buyer', 'seller'], true)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Conversation $conversation): bool|Response
    {
        return $this->canView($user, $conversation) ? true : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can create models.
     */
    public function send(User $user, Conversation $conversation): bool
    {
        return $this->canView($user, $conversation) && ! $conversation->isBlocked();
    }

    private function canView(User $user, Conversation $conversation): bool
    {
        return $user->is_active && ($conversation->buyer_id === $user->id || $this->isActiveStoreMember($user, $conversation));
    }

    /**
     * Determine whether the user can update the model.
     */
    private function isActiveStoreMember(User $user, Conversation $conversation): bool
    {
        if ($user->account_type !== 'seller') {
            return false;
        }

        $conversation->loadMissing('store');

        return $this->isActiveTenantMember($user, $conversation->store?->tenant_id);
    }
}
