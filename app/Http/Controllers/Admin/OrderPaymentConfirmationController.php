<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Payments\BankTransferGateway;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Admin confirms that a bank transfer for an order arrived.
 */
class OrderPaymentConfirmationController extends Controller
{
    public function store(Order $order, BankTransferGateway $gateway): RedirectResponse
    {
        abort_if($order->isPaid() || ! $order->isOpen(), 422, __('Only open orders can be marked paid.'));

        $payment = $order->payments()->where('gateway', $gateway->key())->latest('id')->first()
            ?? $order->payments()->create([
                'gateway' => $gateway->key(),
                'gateway_id' => $order->number,
                'status' => PaymentStatus::Pending,
                'amount_cents' => $order->total_cents,
                'currency' => $order->currency,
            ]);

        $gateway->confirm($payment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment confirmed, invoice issued and sent.')]);

        return back();
    }
}
