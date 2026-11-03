<?php

namespace App\Policies;

use App\Models\DeliveryAddress;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DeliveryAddressPolicy
{
    /**
     * Determine whether the user can list their saved addresses.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasBuyerAccess($user);
    }

    /**
     * Determine whether the user can save a new address.
     */
    public function create(User $user): bool
    {
        return $this->hasBuyerAccess($user);
    }

    /**
     * Determine whether the user can read the address.
     */
    public function view(User $user, DeliveryAddress $deliveryAddress): bool|Response
    {
        return $this->owns($user, $deliveryAddress)
            ? true
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can change the address.
     */
    public function update(User $user, DeliveryAddress $deliveryAddress): bool|Response
    {
        return $this->view($user, $deliveryAddress);
    }

    /**
     * Determine whether the user can remove the address.
     */
    public function delete(User $user, DeliveryAddress $deliveryAddress): bool|Response
    {
        return $this->view($user, $deliveryAddress);
    }

    private function owns(User $user, DeliveryAddress $deliveryAddress): bool
    {
        return $user->is_active && $deliveryAddress->user_id === $user->id;
    }

    private function hasBuyerAccess(User $user): bool
    {
        return $user->is_active && in_array($user->account_type, ['buyer', 'seller'], true);
    }
}
