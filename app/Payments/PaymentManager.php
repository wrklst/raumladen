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
     * Gateways that may be offered at checkout.
     *
     * @return list<string>
     */
    public function availableKeys(): array
    {
        return array_values(array_filter($this->keys(), fn (string $key) => $this->gateway($key)->available()));
    }
}
