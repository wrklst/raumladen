<?php

namespace App\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Cancels an unpaid order and puts its stock back. Idempotent: an order that
 * is no longer pending (paid in the meantime, already cancelled) is left alone.
 */
class CancelOrder
{
    public function handle(Order $order): bool
    {
        return DB::transaction(function () use ($order): bool {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                return false;
            }

            foreach ($locked->items()->with('product')->get() as $item) {
                $item->restock($item->quantity);
            }

            $locked->update(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);
            $order->refresh();

            return true;
        });
    }
}
