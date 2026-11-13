<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public const string SESSION_KEY = 'owned_orders';

    /**
     * Owners and backend users working with orders can view an order; guests only the orders they
     * placed in this session.
     */
    public function view(?User $user, Order $order): bool
    {
        if ($user !== null && ($user->worksIn('orders') || $order->user_id === $user->id)) {
            return true;
        }

        return in_array($order->uuid, session(self::SESSION_KEY, []), true);
    }
}
