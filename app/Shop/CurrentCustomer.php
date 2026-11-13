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

    public static function group(): CustomerGroup
    {
        return once(fn (): CustomerGroup => self::user()?->customerGroup ?? CustomerGroup::default());
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
