<?php

namespace App\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Orders\MarkOrderPaid;
use Illuminate\Http\Request;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Payment as MolliePayment;

class MollieGateway implements PaymentGateway
{
    private ?MollieApiClient $client = null;

    public function __construct(private MarkOrderPaid $markOrderPaid) {}

    public function key(): string
    {
        return 'mollie';
    }

    public function available(): bool
    {
        return (string) config('shop.payments.mollie.api_key') !== '';
    }

    public function start(Order $order, string $returnUrl, string $webhookUrl): string
    {
        $payment = $order->payments()->create([
            'gateway' => $this->key(),
            'status' => PaymentStatus::Open,
            'amount_cents' => $order->total_cents,
            'currency' => $order->currency,
        ]);

        $remote = $this->client()->payments->create([
            'amount' => ['currency' => $order->currency, 'value' => number_format($order->total_cents / 100, 2, '.', '')],
            'description' => config('shop.seller.name').' '.$order->number,
            'redirectUrl' => $returnUrl,
            'webhookUrl' => app()->isLocal() ? null : $webhookUrl,
            'locale' => $order->locale === 'de' ? 'de_DE' : 'en_US',
            'metadata' => ['order_id' => $order->id, 'order_number' => $order->number, 'payment_id' => $payment->id],
        ]);

        $payment->update(['gateway_id' => $remote->id]);

        return $remote->getCheckoutUrl() ?? $returnUrl;
    }

    public function sync(Payment $payment): Payment
    {
        return $this->apply($payment, $this->client()->payments->get($payment->gateway_id));
    }

    public function handleWebhook(Request $request): ?Payment
    {
        $id = $request->string('id')->toString();

        if ($id === '') {
            return null;
        }

        $payment = Payment::query()->where('gateway', $this->key())->where('gateway_id', $id)->first();

        return $payment === null ? null : $this->sync($payment);
    }

    public function refund(Payment $payment, int $amountCents): void
    {
        $this->client()->paymentRefunds->createForId($payment->gateway_id, [
            'amount' => ['currency' => $payment->currency, 'value' => number_format($amountCents / 100, 2, '.', '')],
        ]);

        $payment->update([
            'refunded_cents' => $payment->refunded_cents + $amountCents,
            'status' => PaymentStatus::Refunded,
        ]);
    }

    private function apply(Payment $payment, MolliePayment $remote): Payment
    {
        $status = match ($remote->status) {
            'paid' => PaymentStatus::Paid,
            'pending', 'authorized' => PaymentStatus::Pending,
            'canceled' => PaymentStatus::Cancelled,
            'expired' => PaymentStatus::Expired,
            'failed' => PaymentStatus::Failed,
            default => PaymentStatus::Open,
        };

        $payment->update([
            'status' => $status,
            'method' => $remote->method,
            'paid_at' => $remote->paidAt ? now()->parse($remote->paidAt) : $payment->paid_at,
            'payload' => ['status' => $remote->status, 'amount' => $remote->amount, 'method' => $remote->method],
        ]);

        if ($status === PaymentStatus::Paid) {
            $this->markOrderPaid->handle($payment->order, $this->key());
        }

        return $payment;
    }

    private function client(): MollieApiClient
    {
        return $this->client ??= (new MollieApiClient)->setApiKey((string) config('shop.payments.mollie.api_key'));
    }
}
