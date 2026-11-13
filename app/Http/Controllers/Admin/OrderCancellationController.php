<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Invoicing\InvoiceIssuer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Orders\CancelOrder;
use App\Payments\PaymentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

/**
 * Cancels an order. Paid orders get a credit note and a refund through the
 * gateway (bank transfers are refunded manually); stock is returned. Units
 * already credited by partial refunds are not credited or restocked again.
 */
class OrderCancellationController extends Controller
{
    public function store(Order $order, InvoiceIssuer $issuer, PaymentManager $payments, CancelOrder $cancelOrder): RedirectResponse
    {
        abort_if(in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Refunded], true), 422, __('Order is already cancelled.'));

        if (! $order->isPaid()) {
            $cancelOrder->handle($order);
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Order cancelled.')]);

            return back();
        }

        DB::transaction(function () use ($order, $issuer): void {
            $order->load('items.product');

            // Restock before the credit note raises refunded_quantity.
            $order->items->each(fn (OrderItem $item) => $item->restock($item->refundableQuantity()));

            $issuer->issueCreditNote($order);

            $order->update(['status' => OrderStatus::Refunded, 'cancelled_at' => now()]);
        });

        $message = __('Order refunded and credit note issued.');
        $payment = $order->payments()->where('status', PaymentStatus::Paid)->latest('id')->first();

        try {
            if ($payment !== null && $payment->gateway !== 'bank_transfer') {
                $payments->gateway($payment->gateway)->refund($payment, $payment->amount_cents - $payment->refunded_cents);
            } else {
                $message = __('Order cancelled and credit note issued. Refund the bank transfer manually.');
            }
        } catch (Throwable $e) {
            report($e);
            $message = __('Credit note issued, but the gateway refund failed: :error', ['error' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
