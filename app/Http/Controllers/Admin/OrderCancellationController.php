<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Invoicing\InvoiceIssuer;
use App\Models\Order;
use App\Payments\PaymentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

/**
 * Cancels an order. Paid orders get a credit note and a refund through the
 * gateway (bank transfers are refunded manually); stock is returned.
 */
class OrderCancellationController extends Controller
{
    public function store(Order $order, InvoiceIssuer $issuer, PaymentManager $payments): RedirectResponse
    {
        abort_if(in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Refunded], true), 422, 'Order is already cancelled.');

        $wasPaid = $order->isPaid();

        DB::transaction(function () use ($order, $issuer, $wasPaid): void {
            $order->load('items.product');

            foreach ($order->items as $item) {
                if ($item->product?->track_stock) {
                    $item->product->increment('stock', $item->quantity);
                }
            }

            if ($wasPaid) {
                $issuer->issueCreditNote($order);
            }

            $order->update([
                'status' => $wasPaid ? OrderStatus::Refunded : OrderStatus::Cancelled,
                'cancelled_at' => now(),
            ]);
        });

        $message = $wasPaid ? 'Order refunded and credit note issued.' : 'Order cancelled.';

        if ($wasPaid) {
            $payment = $order->payments()->where('status', PaymentStatus::Paid)->latest('id')->first();

            try {
                if ($payment !== null && $payment->gateway !== 'bank_transfer') {
                    $payments->gateway($payment->gateway)->refund($payment, $payment->amount_cents - $payment->refunded_cents);
                } else {
                    $message = 'Order cancelled and credit note issued. Refund the bank transfer manually.';
                }
            } catch (Throwable $e) {
                report($e);
                $message = 'Credit note issued, but the gateway refund failed: '.$e->getMessage();
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
