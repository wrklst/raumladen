<?php

namespace App\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Orders\MarkOrderPaid;
use Illuminate\Http\Request;
use LogicException;

/**
 * Vorkasse: the customer transfers the amount, an admin marks the order paid.
 */
class BankTransferGateway implements PaymentGateway
{
    public function __construct(private MarkOrderPaid $markOrderPaid) {}

    public function key(): string
    {
        return 'bank_transfer';
    }

    public function available(): bool
    {
        return config('shop.seller.iban') !== '';
    }

    public function start(Order $order, string $returnUrl, string $webhookUrl): string
    {
        // One transfer per order: a second attempt reuses the pending payment.
        $order->payments()->firstOrCreate([
            'gateway' => $this->key(),
            'gateway_id' => $order->number,
        ], [
            'status' => PaymentStatus::Pending,
            'amount_cents' => $order->total_cents,
            'currency' => $order->currency,
        ]);

        return $returnUrl;
    }

    public function sync(Payment $payment): Payment
    {
        return $payment;
    }

    /**
     * Called by the admin when the transfer arrived.
     */
    public function confirm(Payment $payment): void
    {
        $payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);
        $this->markOrderPaid->handle($payment->order, $this->key());
    }

    public function handleWebhook(Request $request): ?Payment
    {
        return null;
    }

    public function refund(Payment $payment, int $amountCents): void
    {
        throw new LogicException('Bank transfer refunds are made manually.');
    }
}
