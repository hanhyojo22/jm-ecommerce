<?php

namespace App\Policies;

use App\Models\DiscountCard;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DiscountCardPolicy
{
    /**
     * Only the customer who saved the ID can remove it; anyone else's is a 404.
     */
    public function delete(User $user, DiscountCard $discountCard): bool|Response
    {
        return $user->is_active && $discountCard->user_id === $user->id
            ? true
            : Response::denyAsNotFound();
    }
}
