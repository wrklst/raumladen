<?php

namespace App\Enums;

enum TaxClass: string
{
    case Standard = 'standard';
    case Reduced = 'reduced';
    case Zero = 'zero';

    /**
     * The VAT rate in percent for this class in the given country.
     */
    public function rateIn(string $country): float
    {
        if ($this === self::Zero) {
            return 0.0;
        }

        $rates = config('shop.tax.rates.'.strtoupper($country));

        if ($rates === null) {
            return 0.0;
        }

        return (float) $rates[$this->value];
    }
}
