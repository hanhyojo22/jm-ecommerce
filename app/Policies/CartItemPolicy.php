<?php

namespace App\Policies;

use App\Models\CartItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CartItemPolicy
{
    /**
     * Determine whether the user can change the cart item.
     */
    public function update(User $user, CartItem $cartItem): bool|Response
    {
        return $this->owns($user, $cartItem)
            ? true
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can remove the cart item.
     */
    public function delete(User $user, CartItem $cartItem): bool|Response
    {
        return $this->owns($user, $cartItem)
            ? true
            : Response::denyAsNotFound();
    }

    private function owns(User $user, CartItem $cartItem): bool
    {
        return $user->is_active
            && $cartItem->cart()->where('user_id', $user->id)->exists();
    }
}
