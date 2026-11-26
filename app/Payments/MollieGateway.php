<?php

namespace App\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Orders\MarkOrderPaid;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Payment as MolliePayment;
use RuntimeException;
use Throwable;

class MollieGateway implements PaymentGateway
{
    /**
     * The methods listed first at the checkout, in this order.
     */
    private const array FIRST = ['wero', 'applepay', 'googlepay', 'creditcard', 'paypal'];

    private ?MollieApiClient $client = null;

    public function __construct(private MarkOrderPaid $markOrderPaid) {}

    public function key(): string
    {
        return 'mollie';
    }

    public function available(): bool
    {
        return $this->apiKey() !== '';
    }

    public function paysLater(): bool
    {
        return false;
    }

    public function start(Order $order, string $returnUrl, string $webhookUrl, array $options = []): string
    {
        $payment = $order->payments()->create([
            'gateway' => $this->key(),
            'status' => PaymentStatus::Open,
            'amount_cents' => $order->total_cents,
            'currency' => $order->currency,
        ]);

        $applePay = ($options['method'] ?? null) === 'applepay' && ! empty($options['apple_pay_token']);

        $remote = $this->client()->payments->create(array_filter([
            'amount' => ['currency' => $order->currency, 'value' => number_format($order->total_cents / 100, 2, '.', '')],
            'description' => config('shop.seller.name').' '.$order->number,
            'redirectUrl' => $returnUrl,
            'webhookUrl' => app()->isLocal() ? null : $webhookUrl,
            'locale' => $order->locale === 'de' ? 'de_DE' : 'en_US',
            // Chosen at checkout: the provider's own page then starts at that method,
            // and a card entered at checkout is charged without a second form.
            // Apple Pay confirmed at the checkout arrives as a token and is charged as a card.
            'method' => $applePay ? 'creditcard' : ($options['method'] ?? null),
            'cardToken' => ($options['method'] ?? null) === 'creditcard' ? ($options['card_token'] ?? null) : null,
            'applePayPaymentToken' => $applePay ? $options['apple_pay_token'] : null,
            'metadata' => ['order_id' => $order->id, 'order_number' => $order->number, 'payment_id' => $payment->id],
        ], fn (mixed $value): bool => $value !== null));

        $payment->update(['gateway_id' => $remote->id]);

        // A card that needs no confirmation by its bank is paid (or refused) at once.
        $this->apply($payment, $remote);

        return $remote->getCheckoutUrl() ?? $returnUrl;
    }

    /**
     * The payment methods switched on in the Mollie account, to choose from
     * at checkout. Empty when Mollie cannot be asked: the checkout then
     * offers Mollie as one entry and the choice is made on Mollie's page.
     *
     * @return list<array{id: string, label: string, image: string|null}>
     */
    public function methods(string $locale): array
    {
        // ponytail: every method is offered for every amount; pass the amount when a method's limits matter.
        return Cache::remember('mollie:methods:'.$locale.':'.md5($this->apiKey()), now()->addMinutes(10), function () use ($locale): array {
            $query = ['locale' => $locale === 'de' ? 'de_DE' : 'en_US'];

            // Wallets are only listed when asked for; an account that knows none of them is asked without.
            foreach ([['includeWallets' => 'applepay,googlepay'], ['includeWallets' => 'applepay'], []] as $wallets) {
                try {
                    $methods = [];

                    foreach ($this->client()->methods->allEnabled([...$query, ...$wallets]) as $method) {
                        $methods[] = ['id' => (string) $method->id, 'label' => (string) $method->description, 'image' => $method->image->svg ?? null];
                    }

                    // What most customers reach for comes first; the rest keeps Mollie's order.
                    $rank = fn (array $method): int => ($at = array_search($method['id'], self::FIRST, true)) === false ? count(self::FIRST) : $at;
                    usort($methods, fn (array $a, array $b): int => $rank($a) <=> $rank($b));

                    return $methods;
                } catch (Throwable $e) {
                    $failure = $e;
                }
            }

            report($failure);

            return [];
        });
    }

    /**
     * Apple Pay at the checkout: Apple asks the shop to prove who it is, and
     * Mollie answers for it. The answer is handed to Apple's sheet as it is.
     *
     * @return array<string, mixed>
     */
    public function applePaySession(string $validationUrl): array
    {
        return $this->client()->wallets->requestApplePayPaymentSession((string) parse_url((string) config('app.url'), PHP_URL_HOST), $validationUrl)->toArray();
    }

    /**
     * What the card fields at checkout need (Mollie Components): the public
     * id of the profile the API key belongs to, and whether it is a test key.
     *
     * @return array{profile_id: string, testmode: bool}|null
     */
    public function components(): ?array
    {
        $profile = Cache::remember('mollie:profile:'.md5($this->apiKey()), now()->addDay(), function (): ?string {
            try {
                return (string) $this->client()->profiles->getCurrent()->id;
            } catch (Throwable $e) {
                report($e);

                return null;
            }
        });

        return $profile === null ? null : ['profile_id' => $profile, 'testmode' => str_starts_with($this->apiKey(), 'test_')];
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

        $refunded = $payment->refunded_cents + $amountCents;

        // A partial refund leaves the payment Paid so later refunds can still find it.
        $payment->update([
            'refunded_cents' => $refunded,
            'status' => $refunded >= $payment->amount_cents ? PaymentStatus::Refunded : $payment->status,
        ]);
    }

    private function apply(Payment $payment, MolliePayment $remote): Payment
    {
        // mollie-api-php 4 returns the status as an enum; older versions as a string.
        $remoteStatus = $remote->status instanceof BackedEnum ? $remote->status->value : (string) $remote->status;

        $status = match ($remoteStatus) {
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
            'payload' => ['status' => $remoteStatus, 'amount' => $remote->amount, 'method' => $remote->method],
        ]);

        if ($status === PaymentStatus::Paid) {
            $order = $payment->order;
            $this->markOrderPaid->handle($order, $this->key());

            // Money for an attempt that was given up (its draft is cancelled) goes straight back.
            if (! $order->refresh()->isPlaced() && $payment->refunded_cents === 0) {
                report(new RuntimeException("Payment {$payment->gateway_id} arrived for abandoned order {$order->number}; refunding."));
                $this->refund($payment, $payment->amount_cents);
            }
        }

        return $payment;
    }

    private function apiKey(): string
    {
        return (string) config('shop.payments.mollie.api_key');
    }

    protected function client(): MollieApiClient
    {
        return $this->client ??= (new MollieApiClient)->setApiKey($this->apiKey());
    }
}
