<?php

namespace App\Orders;

use App\Enums\OrderStatus;
use App\Invoicing\InvoiceIssuer;
use App\Mail\OrderConfirmation;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Idempotent: a second payment notification for a paid order does nothing.
 */
class MarkOrderPaid
{
    public function __construct(private InvoiceIssuer $issuer) {}

    public function handle(Order $order, string $gateway): void
    {
        $invoice = DB::transaction(function () use ($order, $gateway) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->isPaid()) {
                return null;
            }

            $locked->update([
                'status' => OrderStatus::Paid,
                'paid_at' => now(),
                'payment_gateway' => $gateway,
            ]);

            return $this->issuer->issueInvoice($locked);
        });

        if ($invoice === null) {
            return;
        }

        $order->refresh();

        // ponytail: synchronous send; queue it once a worker runs in production.
        Mail::to($order->email)->locale($order->locale)->send(new OrderConfirmation($order, $invoice));
    }
}
