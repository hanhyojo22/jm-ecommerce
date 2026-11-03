<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderPolicy
{
    /**
     * Determine whether the user can browse their own orders.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_active
            && (in_array($user->account_type, ['buyer', 'seller'], true) || $user->isPlatformAdministrator());
    }

    /**
     * Determine whether the user can view the order.
     *
     * Buyers only ever see their own orders. Another buyer's order is reported as
     * missing so its id cannot be used to probe for other accounts.
     */
    public function view(User $user, Order $order): bool|Response
    {
        if ($user->isPlatformAdministrator()) {
            return true;
        }

        return $user->is_active && $order->user_id === $user->id
            ? true
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can open a payment session for the order.
     */
    public function pay(User $user, Order $order): bool|Response
    {
        return $this->view($user, $order);
    }
}
