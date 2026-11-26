<?php

namespace Tests\Fixtures;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Orders\MarkOrderPaid;
use App\Payments\PaymentGateway;
use Illuminate\Http\Request;

/**
 * An online payment provider for tests: what it will say about a payment is
 * set in $outcome, and what it was asked is kept in $started and $refunded.
 */
class OnlineGateway implements PaymentGateway
{
    public static PaymentStatus $outcome = PaymentStatus::Open;

    /** @var list<array<string, mixed>> */
    public static array $started = [];

    /** @var list<int> */
    public static array $refunded = [];

    public function __construct(private MarkOrderPaid $markOrderPaid) {}

    public function key(): string
    {
        return 'online';
    }

    public function available(): bool
    {
        return true;
    }

    public function paysLater(): bool
    {
        return false;
    }

    public function start(Order $order, string $returnUrl, string $webhookUrl, array $options = []): string
    {
        self::$started[] = [...$options, 'return' => $returnUrl];

        $payment = $order->payments()->create([
            'gateway' => $this->key(),
            'gateway_id' => 'tr_'.$order->id.'_'.count(self::$started),
            'status' => PaymentStatus::Open,
            'amount_cents' => $order->total_cents,
            'currency' => $order->currency,
        ]);

        $this->sync($payment);

        return 'https://pay.example/'.$payment->gateway_id;
    }

    public function sync(Payment $payment): Payment
    {
        $payment->update(['status' => self::$outcome]);

        if (self::$outcome === PaymentStatus::Paid) {
            $this->markOrderPaid->handle($payment->order, $this->key());
        }

        return $payment;
    }

    public function handleWebhook(Request $request): ?Payment
    {
        return null;
    }

    public function refund(Payment $payment, int $amountCents): void
    {
        self::$refunded[] = $amountCents;
    }
}
