<?php

namespace App\Support;

use NumberFormatter;

class Money
{
    public static function format(int $cents, ?string $currency = null, ?string $locale = null): string
    {
        $formatter = new NumberFormatter($locale ?? app()->getLocale(), NumberFormatter::CURRENCY);

        $formatted = $formatter->formatCurrency($cents / 100, $currency ?? config('shop.currency'));

        return $formatted === false ? '' : $formatted;
    }

    /**
     * Decimal string with a dot, e.g. "12.90", for feeds and APIs.
     */
    public static function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
