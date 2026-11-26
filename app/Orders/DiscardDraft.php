<?php

namespace App\Orders;

use App\Models\Order;
use App\Payments\PaymentManager;
use Throwable;

/**
 * Ends an attempt to pay online that did not lead to a placed order: asks
 * the provider once more, and unless the money did arrive, cancels the draft
 * so its stock is back on the shelf. The draft stays in the database,
 * cancelled and never placed, so a payment that still arrives finds it and
 * is refunded (see MollieGateway).
 */
class DiscardDraft
{
    public function __construct(private PaymentManager $payments, private CancelOrder $cancelOrder) {}

    /**
     * @return bool whether the draft is gone (false: it has been paid or is being paid)
     */
    public function handle(Order $order): bool
    {
        if ($order->isPlaced()) {
            return false;
        }

        $payment = $order->payments()->latest('id')->first();

        if ($payment !== null) {
            try {
                $this->payments->gateway($payment->gateway)->sync($payment);
            } catch (Throwable $e) {
                report($e);
            }

            // Authorised by the customer, not yet confirmed by the provider: the money is on its way.
            if ($order->refresh()->isPlaced() || $payment->refresh()->status->isUnderway()) {
                return false;
            }
        }

        $this->cancelOrder->handle($order);

        return true;
    }
}
