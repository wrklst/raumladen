<?php

namespace App\Payments;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class PaymentManager
{
    public function __construct(private Container $container) {}

    public function gateway(string $key): PaymentGateway
    {
        $class = config("shop.payments.gateways.{$key}");

        if ($class === null) {
            throw new InvalidArgumentException("Unknown payment gateway [{$key}].");
        }

        return $this->container->make($class);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_values(array_filter(array_keys(config('shop.payments.gateways')), is_string(...)));
    }

    /**
     * What the customer chooses from at checkout: every payment method of
     * every available gateway. A gateway that cannot name its methods is one
     * entry, and the choice is made on the provider's page. Each says whether
     * the order waits for the money (a bank transfer) or ships once ordered.
     *
     * @return list<array{gateway: string, method: string|null, label: string|null, image: string|null, pays_later: bool}>
     */
    public function choices(string $locale): array
    {
        $choices = [];

        foreach ($this->availableKeys() as $key) {
            $gateway = $this->gateway($key);
            $methods = $gateway instanceof MollieGateway ? $gateway->methods($locale) : [];

            foreach ($methods as $method) {
                $choices[] = ['gateway' => $key, 'method' => $method['id'], 'label' => $method['label'], 'image' => $method['image'], 'pays_later' => $gateway->paysLater()];
            }

            if ($methods === []) {
                $choices[] = ['gateway' => $key, 'method' => null, 'label' => null, 'image' => null, 'pays_later' => $gateway->paysLater()];
            }
        }

        return $choices;
    }

    /**
     * Gateways that may be offered at checkout.
     *
     * @return list<string>
     */
    public function availableKeys(): array
    {
        return array_values(array_filter($this->keys(), fn (string $key) => $this->gateway($key)->available()));
    }
}
