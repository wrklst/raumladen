<?php

namespace App\Orders;

use App\Mail\NewOrder;
use App\Models\Order;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Tells the people named in the shop settings (Email: who is told about a
 * new order) that an order has been placed. Called once per order, at the
 * moment it is placed: at checkout for prepayment, with the confirmed
 * payment otherwise.
 */
class AnnounceOrder
{
    public function handle(Order $order): void
    {
        foreach ((array) config('shop.orders.notify') as $address) {
            try {
                Mail::to($address)->send(new NewOrder($order));
            } catch (Throwable $e) {
                // The order stands; a mail problem must not undo it.
                report($e);
            }
        }
    }
}
