<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function key(): string;

    /**
     * Whether the gateway is configured and may be offered at checkout.
     */
    public function available(): bool;

    /**
     * Create a payment for the order and return the URL to send the customer to.
     */
    public function start(Order $order, string $returnUrl, string $webhookUrl): string;

    /**
     * Pull the current status from the provider and apply it.
     */
    public function sync(Payment $payment): Payment;

    /**
     * Handle a provider webhook. Returns the affected payment, or null when unknown.
     */
    public function handleWebhook(Request $request): ?Payment;

    public function refund(Payment $payment, int $amountCents): void;
}
