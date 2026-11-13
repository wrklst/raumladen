<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderRefundRequest;
use App\Invoicing\InvoiceIssuer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Payments\PaymentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

/**
 * Partial refund (e.g. one returned book): credit note for the selected
 * quantities, optional restock, refund of the credited amount. The order
 * becomes Refunded once every item and the shipping are credited.
 */
class OrderRefundController extends Controller
{
    public function store(Order $order, OrderRefundRequest $request, InvoiceIssuer $issuer, PaymentManager $payments): RedirectResponse
    {
        $quantities = $request->creditQuantities();

        $creditNote = DB::transaction(function () use ($order, $request, $issuer, $quantities): Invoice {
            $order->load('items.product');

            $creditNote = $issuer->issueCreditNote($order, $quantities, $request->boolean('include_shipping'));

            if ($request->boolean('restock')) {
                $order->items->each(fn (OrderItem $item) => $item->restock($quantities[$item->id] ?? 0));
            }

            $order->load('items', 'invoices');

            if ($order->isFullyCredited()) {
                $order->update(['status' => OrderStatus::Refunded, 'cancelled_at' => now()]);
            }

            return $creditNote;
        });

        $message = __('Credit note :number issued.', ['number' => $creditNote->number]);
        $payment = $order->payments()->where('status', PaymentStatus::Paid)->latest('id')->first();

        try {
            if ($payment !== null && $payment->gateway !== 'bank_transfer') {
                $payments->gateway($payment->gateway)->refund($payment, $creditNote->total_gross_cents);
            } else {
                $message = __('Credit note :number issued. Refund the bank transfer manually.', ['number' => $creditNote->number]);
            }
        } catch (Throwable $e) {
            report($e);
            $message = __('Credit note issued, but the gateway refund failed: :error', ['error' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
