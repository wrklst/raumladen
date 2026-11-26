<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Orders\CancelOrder;
use App\Orders\DiscardDraft;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Releases the stock held by orders that were never paid. Orders with a payment
 * that is paid, or still pending at a gateway other than bank transfer (money
 * may yet arrive), are skipped.
 */
#[Signature('orders:cancel-unpaid')]
#[Description('Cancel unpaid orders older than shop.orders.unpaid_cancel_days and abandoned online payments, and return their stock')]
class CancelUnpaidOrders extends Command
{
    public function handle(CancelOrder $cancelOrder, DiscardDraft $discardDraft): int
    {
        $cancelled = 0;

        Order::query()
            ->placed()
            ->where('status', OrderStatus::Pending)
            ->where('created_at', '<=', now()->subDays((int) config('shop.orders.unpaid_cancel_days')))
            ->whereDoesntHave('payments', fn ($payments) => $payments
                ->where('status', PaymentStatus::Paid)
                ->orWhere(fn ($pending) => $pending->where('status', PaymentStatus::Pending)->where('gateway', '!=', 'bank_transfer')))
            ->each(function (Order $order) use ($cancelOrder, &$cancelled): void {
                $cancelled += (int) $cancelOrder->handle($order);
            });

        // Attempts to pay online that nobody is finishing: their stock goes back after a while.
        Order::query()
            ->whereNull('placed_at')
            ->where('status', OrderStatus::Pending)
            ->where('created_at', '<=', now()->subMinutes((int) config('shop.orders.draft_minutes')))
            ->each(function (Order $order) use ($discardDraft, &$cancelled): void {
                $cancelled += (int) $discardDraft->handle($order);
            });

        $this->info("Cancelled {$cancelled} unpaid order(s).");

        return self::SUCCESS;
    }
}
