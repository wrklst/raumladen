<?php

namespace App\Orders;

use App\Enums\OrderStatus;
use App\Invoicing\InvoiceIssuer;
use App\Mail\OrderConfirmation;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Idempotent: a payment notification for a paid or cancelled order does nothing.
 */
class MarkOrderPaid
{
    public function __construct(private InvoiceIssuer $issuer, private AnnounceOrder $announceOrder) {}

    public function handle(Order $order, string $gateway): void
    {
        $invoice = DB::transaction(function () use ($order, $gateway) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            // Only an open order can be paid: a late notification must not
            // revive a cancelled one whose stock is already back on the shelf.
            if ($locked->isPaid() || ! $locked->isOpen()) {
                return null;
            }

            $locked->update([
                'status' => OrderStatus::Paid,
                'placed_at' => $locked->placed_at ?? now(),
                'paid_at' => now(),
                'payment_gateway' => $gateway,
            ]);

            return $this->issuer->issueInvoice($locked);
        });

        if ($invoice === null) {
            return;
        }

        // Paid online, the order is placed by this payment: now is when the shop's people hear of it.
        $placedNow = ! $order->isPlaced();
        $order->refresh();

        // ponytail: synchronous send; queue it once a worker runs in production.
        Mail::to($order->email)->locale($order->locale)->send(new OrderConfirmation($order, $invoice));

        if ($placedNow) {
            $this->announceOrder->handle($order);
        }
    }
}
