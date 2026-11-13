<?php

namespace App\Shop;

use App\Models\CustomerGroup;
use App\Models\User;

/**
 * Pricing context of the current visitor.
 */
class CurrentCustomer
{
    public static function user(): ?User
    {
        return auth()->user();
    }

    /**
     * Remembered on the request, not for the process: a long-lived worker
     * (Octane, a test) serves many customers one after the other.
     */
    public static function group(): CustomerGroup
    {
        $attributes = request()->attributes;
        $user = self::user();
        $key = 'customer_group:'.($user->id ?? 'guest');

        if (! $attributes->has($key)) {
            $attributes->set($key, $user->customerGroup ?? CustomerGroup::default());
        }

        return $attributes->get($key);
    }

    public static function country(): string
    {
        $user = self::user();

        return $user?->shipping_address['country']
            ?? $user?->billing_address['country']
            ?? config('shop.tax.home_country');
    }

    public static function vatIdValid(): bool
    {
        return self::user()?->hasValidVatId() ?? false;
    }
}
