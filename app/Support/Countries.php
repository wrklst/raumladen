<?php

namespace App\Support;

use Locale;

class Countries
{
    /** @var list<string> */
    public const array EU = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE',
        'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
    ];

    public static function isEu(string $country): bool
    {
        return in_array(strtoupper($country), self::EU, true);
    }

    /**
     * VAT ID prefix to ISO country (Greece uses EL on VAT IDs).
     */
    public static function fromVatPrefix(string $prefix): string
    {
        return strtoupper($prefix) === 'EL' ? 'GR' : strtoupper($prefix);
    }

    public static function name(string $country, ?string $locale = null): string
    {
        return Locale::getDisplayRegion('-'.strtoupper($country), $locale ?? app()->getLocale()) ?: $country;
    }

    /**
     * All ISO 3166-1 alpha-2 countries with localized names, EU first, home country on top.
     *
     * @return array<string, string>
     */
    public static function options(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $home = config('shop.tax.home_country');

        $all = [];

        foreach (range('A', 'Z') as $a) {
            foreach (range('A', 'Z') as $b) {
                $code = $a.$b;
                $name = Locale::getDisplayRegion('-'.$code, $locale);

                if ($name !== false && $name !== '' && $name !== $code && ! str_starts_with($name, 'Unknown')) {
                    $all[$code] = $name;
                }
            }
        }

        $eu = array_intersect_key($all, array_flip(self::EU));
        asort($eu);
        $rest = array_diff_key($all, $eu);
        asort($rest);

        return [$home => $all[$home]] + $eu + $rest;
    }
}
