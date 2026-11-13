<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Orders\CancelOrder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Releases the stock held by orders that were never paid. Orders with a payment
 * that is paid, or still pending at a gateway other than bank transfer (money
 * may yet arrive), are skipped.
 */
#[Signature('orders:cancel-unpaid')]
#[Description('Cancel pending orders older than shop.orders.unpaid_cancel_days and return their stock')]
class CancelUnpaidOrders extends Command
{
    public function handle(CancelOrder $cancelOrder): int
    {
        $cancelled = 0;

        Order::query()
            ->where('status', OrderStatus::Pending)
            ->where('created_at', '<=', now()->subDays((int) config('shop.orders.unpaid_cancel_days')))
            ->whereDoesntHave('payments', fn ($payments) => $payments
                ->where('status', PaymentStatus::Paid)
                ->orWhere(fn ($pending) => $pending->where('status', PaymentStatus::Pending)->where('gateway', '!=', 'bank_transfer')))
            ->each(function (Order $order) use ($cancelOrder, &$cancelled): void {
                $cancelled += (int) $cancelOrder->handle($order);
            });

        $this->info("Cancelled {$cancelled} unpaid order(s).");

        return self::SUCCESS;
    }
}
