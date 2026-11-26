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
     * Whether the order is placed before the money arrives (a bank transfer).
     * Otherwise it is placed when the payment is confirmed.
     */
    public function paysLater(): bool;

    /**
     * Create a payment for the order and return the URL to send the customer
     * to. When the provider needs nothing more from the customer, that is the
     * return URL.
     *
     * @param  array{method?: string|null, card_token?: string|null, apple_pay_token?: string|null}  $options  what the customer chose at checkout
     */
    public function start(Order $order, string $returnUrl, string $webhookUrl, array $options = []): string;

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
